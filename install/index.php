<?php
/**
 * WHISKER — Web Installer
 * 6 steps: Requirements → Database → Store Setup → Admin Account → Gateway Setup → Complete
 *
 * Security model:
 *   - Dual-sentinel lockout: refuses to run if /config/config.php OR /storage/.installed exists.
 *   - HTTPS required (except localhost) — refuses to accept credentials over plain HTTP.
 *   - Per-session CSRF token on every POST.
 *   - MySQL identifier whitelisting for db_name (no SQLi via backtick interpolation).
 *   - var_export() for config file generation (no PHP injection via string interpolation).
 */

if (!defined('WK_ROOT')) {
    define('WK_ROOT', dirname(__DIR__));
}

// ── Dual-sentinel lockout ─────────────────────────────────────────────
// The front controller (index.php) treats /config/config.php as the
// authoritative installed marker. /storage/.installed is a second sentinel
// the installer maintains for its own check. If either is present, the
// installer refuses to run — a missing storage/.installed alone (deleted by
// accident, restore, or attack) cannot reopen a completed install.
if (file_exists(WK_ROOT . '/config/config.php') || file_exists(WK_ROOT . '/storage/.installed')) {
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Whisker</title></head><body style="font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#faf8f6">';
    echo '<div style="text-align:center"><h1>🐱 Already Installed</h1><p>Whisker is already installed and running.</p><p style="color:#6b7280;font-size:13px;margin-top:12px">For security, the installer is locked. To re-install, remove <code>config/config.php</code> and <code>storage/.installed</code>.</p><p><a href="/" style="color:#8b5cf6">Go to your store →</a></p></div>';
    echo '</body></html>';
    exit;
}

// ── HTTPS requirement ─────────────────────────────────────────────────
// The installer accepts gateway secrets and admin credentials. Refuse plain
// HTTP unless the request originates from the local host (developer setup).
$_install_is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    || (($_SERVER['HTTP_X_FORWARDED_SSL']   ?? '') === 'on');
$_install_remote = $_SERVER['REMOTE_ADDR'] ?? '';
$_install_is_local = in_array($_install_remote, ['127.0.0.1', '::1', 'localhost'], true);
if (!$_install_is_https && !$_install_is_local) {
    http_response_code(400);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Whisker — HTTPS Required</title></head><body style="font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#faf8f6">';
    echo '<div style="text-align:center;max-width:500px;padding:24px"><h1>🔒 HTTPS Required</h1><p style="color:#6b7280;margin-top:12px;line-height:1.6">The Whisker installer accepts database credentials, gateway secrets, and your admin password. Running it over plain HTTP would leak these in transit.</p><p style="color:#6b7280;margin-top:12px;line-height:1.6">Please install an SSL certificate (most hosts include free Let\'s Encrypt) and reload this page over <code>https://</code>.</p></div>';
    echo '</body></html>';
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_name('wk_install');
    session_start();
}

// ── Install CSRF token ────────────────────────────────────────────────
// Per-session token, generated once, verified on every POST. The token is
// surfaced to the view as $csrfToken and embedded in each form.
if (empty($_SESSION['wk_install_csrf'])) {
    $_SESSION['wk_install_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['wk_install_csrf'];

$_install_verify_csrf = static function (): bool {
    $submitted = $_POST['_install_csrf'] ?? '';
    $expected  = $_SESSION['wk_install_csrf'] ?? '';
    return is_string($submitted) && $expected !== '' && hash_equals($expected, $submitted);
};

// MySQL identifier rule: letters, digits, underscores, dollar sign. We omit
// the dollar sign for safety — Whisker doesn't need it and it complicates
// shell quoting in backups. 64 char max matches MySQL's hard limit.
$_install_valid_db_name = static function (string $name): bool {
    return (bool)preg_match('/^[A-Za-z0-9_]{1,64}$/', $name);
};

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$step = max(1, min(6, $step));
$error = '';

// ── AJAX: Test DB Connection ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_ajax_action'] ?? '') === 'test_db') {
    header('Content-Type: application/json');

    if (!$_install_verify_csrf()) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please reload the page.']);
        exit;
    }

    $dbHost = trim((string)($_POST['db_host'] ?? ''));
    $dbPort = (int)($_POST['db_port'] ?? 3306);
    $dbName = trim((string)($_POST['db_name'] ?? ''));
    $dbUser = trim((string)($_POST['db_user'] ?? ''));
    $dbPass = (string)($_POST['db_pass'] ?? '');

    if (!$_install_valid_db_name($dbName)) {
        echo json_encode(['success' => false, 'message' => 'Database name must contain only letters, numbers, and underscores (max 64 characters).']);
        exit;
    }

    try {
        $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $dbHost, $dbPort);
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        // $dbName is whitelist-validated above, safe to interpolate into backticks.
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$dbName}`");
        echo json_encode(['success' => true, 'message' => "Connected! Database '{$dbName}' is ready."]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ── Handle POST submissions ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['_ajax_action'])) {

    if (!$_install_verify_csrf()) {
        $error = 'Session expired. Please reload the page and try again.';
    } else {

    switch ($step) {
        case 1:
            // Auto-generate .htaccess if needed
            $htaccessPath = WK_ROOT . '/.htaccess';
            if (!file_exists($htaccessPath) || !str_contains(file_get_contents($htaccessPath), 'RewriteRule ^(.*)$ index.php')) {
                $existing = file_exists($htaccessPath) ? file_get_contents($htaccessPath) : '';
                $cpanelHandler = '';
                if (preg_match('/(# php -- BEGIN.*?# php -- END[^\n]*)/s', $existing, $matches)) {
                    $cpanelHandler = "\n\n" . $matches[1];
                }
                $htaccess = 'Options -MultiViews -Indexes
RewriteEngine On

RewriteCond %{HTTP:Authorization} .
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

RewriteRule ^app/ - [F,L]
RewriteRule ^core/ - [F,L]
RewriteRule ^config/ - [F,L]
RewriteRule ^storage/logs/ - [F,L]
RewriteRule ^storage/cache/ - [F,L]
RewriteRule ^sql/ - [F,L]
RewriteRule ^views/ - [F,L]
RewriteRule ^plugins/.*\.php$ - [F,L]

<FilesMatch "\.(env|sql|sh|lock|log|md|json)$">
    Require all denied
</FilesMatch>

<FilesMatch "^\.">
    Require all denied
</FilesMatch>

<IfModule mod_headers.c>
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-Content-Type-Options "nosniff"
    Header set X-XSS-Protection "1; mode=block"
    Header set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>

<IfModule mod_rewrite.c>
    RewriteRule ^storage/uploads/.*\.php$ - [F,L]
</IfModule>

AddDefaultCharset UTF-8' . $cpanelHandler . "\n";
                file_put_contents($htaccessPath, $htaccess);
            }
            $step = 2;
            break;

        case 2:
            $dbHost = trim($_POST['db_host'] ?? 'localhost');
            $dbPort = (int)($_POST['db_port'] ?? 3306);
            $dbName = trim($_POST['db_name'] ?? '');
            $dbUser = trim($_POST['db_user'] ?? '');
            $dbPass = $_POST['db_pass'] ?? '';

            if (empty($dbName) || empty($dbUser)) {
                $error = 'Database name and username are required.'; break;
            }

            if (!$_install_valid_db_name($dbName)) {
                $error = 'Database name must contain only letters, numbers, and underscores (max 64 characters).'; break;
            }

            try {
                $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $dbHost, $dbPort);
                $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

                // $dbName is whitelist-validated above, safe to interpolate into backticks.
                try {
                    $pdo->exec("USE `{$dbName}`");
                } catch (\Exception $e) {
                    try {
                        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                        $pdo->exec("USE `{$dbName}`");
                    } catch (\Exception $e2) {
                        $error = "Cannot access database '{$dbName}'. Make sure it exists and the user has access. On shared hosting, create the database through cPanel first.";
                        break;
                    }
                }

                // Execute schema — read file and split carefully
                $schemaFile = WK_ROOT . '/sql/schema.sql';
                if (!file_exists($schemaFile)) {
                    $error = 'Schema file not found: sql/schema.sql'; break;
                }

                $schema = file_get_contents($schemaFile);

                // Remove comments
                $schema = preg_replace('/--.*$/m', '', $schema);
                $schema = preg_replace('/\/\*.*?\*\//s', '', $schema);

                // Split by semicolons that are NOT inside quotes
                $statements = [];
                $current = '';
                $inString = false;
                $quote = '';
                for ($i = 0; $i < strlen($schema); $i++) {
                    $ch = $schema[$i];

                    if ($inString) {
                        $current .= $ch;
                        if ($ch === $quote && ($i === 0 || $schema[$i-1] !== '\\')) {
                            $inString = false;
                        }
                        continue;
                    }

                    if ($ch === "'" || $ch === '"') {
                        $inString = true;
                        $quote = $ch;
                        $current .= $ch;
                        continue;
                    }

                    if ($ch === ';') {
                        $stmt = trim($current);
                        if (!empty($stmt)) $statements[] = $stmt;
                        $current = '';
                        continue;
                    }

                    $current .= $ch;
                }
                // Don't forget the last statement
                $stmt = trim($current);
                if (!empty($stmt)) $statements[] = $stmt;

                // Execute each statement with error tracking
                $errors = [];
                $tableCount = 0;
                foreach ($statements as $stmt) {
                    if (empty($stmt)) continue;
                    try {
                        $pdo->exec($stmt);
                        if (stripos($stmt, 'CREATE TABLE') !== false) $tableCount++;
                    } catch (\Exception $e) {
                        // Duplicate key errors are OK (re-running installer)
                        if (strpos($e->getMessage(), '1062') === false) {
                            $errors[] = $e->getMessage();
                        }
                    }
                }

                // Verify critical tables exist
                $requiredTables = ['wk_admins', 'wk_settings', 'wk_products', 'wk_categories',
                    'wk_orders', 'wk_customers', 'wk_payment_gateways'];
                $missingTables = [];
                foreach ($requiredTables as $tbl) {
                    try {
                        $pdo->query("SELECT 1 FROM `{$tbl}` LIMIT 1");
                    } catch (\Exception $e) {
                        $missingTables[] = $tbl;
                    }
                }

                if (!empty($missingTables)) {
                    $error = 'Database setup incomplete. Missing tables: ' . implode(', ', $missingTables) . '. ';
                    if (!empty($errors)) {
                        $error .= 'Errors: ' . implode(' | ', array_slice($errors, 0, 3));
                    }
                    break;
                }

                $_SESSION['wk_install'] = [
                    'db_host' => $dbHost, 'db_port' => $dbPort,
                    'db_name' => $dbName, 'db_user' => $dbUser, 'db_pass' => $dbPass,
                ];
                $step = 3;
            } catch (PDOException $e) {
                $error = 'Connection failed: ' . $e->getMessage();
            }
            break;

        case 3:
            $storeName = trim($_POST['store_name'] ?? '');
            $storeUrl = rtrim(trim($_POST['store_url'] ?? ''), '/');
            if (empty($storeName)) { $error = 'Store name is required.'; break; }
            if (empty($storeUrl) || !filter_var($storeUrl, FILTER_VALIDATE_URL)) {
                $error = 'Please enter a valid Store URL (e.g. https://yourdomain.com).'; break;
            }
            $_SESSION['wk_install']['store_name']   = $storeName;
            $_SESSION['wk_install']['store_tagline'] = trim($_POST['store_tagline'] ?? '');
            $_SESSION['wk_install']['store_url']     = $storeUrl;
            $_SESSION['wk_install']['currency']      = strtoupper(trim($_POST['currency'] ?? 'INR'));
            $_SESSION['wk_install']['timezone']      = trim($_POST['timezone'] ?? 'Asia/Kolkata');
            $step = 4;
            break;

        case 4:
            $adminUser  = trim($_POST['admin_user'] ?? '');
            $adminEmail = trim($_POST['admin_email'] ?? '');
            $adminPass  = $_POST['admin_pass'] ?? '';
            $adminPass2 = $_POST['admin_pass2'] ?? '';

            if (empty($adminUser) || empty($adminEmail) || empty($adminPass)) {
                $error = 'All fields are required.'; break;
            }
            if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid email address.'; break;
            }
            if (strlen($adminPass) < 8) {
                $error = 'Password must be at least 8 characters.'; break;
            }
            if (!preg_match('/[0-9]/', $adminPass)) {
                $error = 'Password must contain at least 1 number.'; break;
            }
            if (!preg_match('/[^a-zA-Z0-9]/', $adminPass)) {
                $error = 'Password must contain at least 1 special character.'; break;
            }
            if ($adminPass !== $adminPass2) {
                $error = 'Passwords do not match.'; break;
            }

            $_SESSION['wk_install']['admin_user']  = $adminUser;
            $_SESSION['wk_install']['admin_email'] = $adminEmail;
            $_SESSION['wk_install']['admin_pass']  = $adminPass;
            $step = 5;
            break;

        case 5:
            $gwCode = $_POST['gateway'] ?? '';
            $_SESSION['wk_install']['gateway']       = $gwCode;
            $_SESSION['wk_install']['gateway_config'] = [];
            if ($gwCode) {
                foreach ($_POST as $k => $v) {
                    if (str_starts_with($k, 'gw_')) {
                        $_SESSION['wk_install']['gateway_config'][substr($k, 3)] = trim($v);
                    }
                }
            }

            // ── DO THE INSTALL ──
            $inst = $_SESSION['wk_install'];
            try {
                $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                    $inst['db_host'], $inst['db_port'], $inst['db_name']);
                $pdo = new PDO($dsn, $inst['db_user'], $inst['db_pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);

                // Admin account
                $hash = password_hash($inst['admin_pass'], PASSWORD_BCRYPT, ['cost' => 12]);
                $pdo->prepare("UPDATE wk_admins SET username=?, email=?, password_hash=? WHERE id=1")
                    ->execute([$inst['admin_user'], $inst['admin_email'], $hash]);

                // Store settings.
                // currency_symbol MUST be written together with currency —
                // schema.sql seeds it as '₹', and every price render reads the
                // symbol setting. Saving only the code left EUR stores showing ₹.
                $instCurrency = strtoupper($inst['currency'] ?? 'INR');
                $instSymbol   = wk_install_currencies()[$instCurrency]['symbol'] ?? $instCurrency;
                $stmtS = $pdo->prepare("UPDATE wk_settings SET setting_value=? WHERE setting_group=? AND setting_key=?");
                foreach ([
                    ['general','site_name', $inst['store_name']],
                    ['general','site_tagline', $inst['store_tagline']??''],
                    ['general','currency', $instCurrency],
                    ['general','currency_symbol', $instSymbol],
                    ['general','timezone', $inst['timezone']],
                ] as [$g,$k,$v]) { $stmtS->execute([$v,$g,$k]); }

                // Gateway config
                if (!empty($inst['gateway']) && !empty($inst['gateway_config'])) {
                    $gw = $pdo->prepare("SELECT config FROM wk_payment_gateways WHERE gateway_code=?");
                    $gw->execute([$inst['gateway']]);
                    $existing = json_decode($gw->fetchColumn() ?: '{}', true);
                    $merged = array_merge($existing, $inst['gateway_config']);
                    $pdo->prepare("UPDATE wk_payment_gateways SET config=?, is_active=1, is_test_mode=1 WHERE gateway_code=?")
                        ->execute([json_encode($merged), $inst['gateway']]);
                }

                // Use the store URL provided by user
                $baseUrl = rtrim($inst['store_url'], '/');
                $parsed = parse_url($baseUrl);
                $basePath = $parsed['path'] ?? '/';
                if ($basePath === '') $basePath = '/';

                // ── Write config files via var_export ──
                // Using var_export() instead of string interpolation prevents PHP
                // injection — even if any value below contained "');system('rm -rf
                // /'); //" it would be safely serialized as a quoted string
                // literal, not executable code.
                $salt = bin2hex(random_bytes(32));

                // Read the shipped version from app/version.php — the single
                // source of truth. Don't store version in config.php; the
                // front controller no longer reads it from there (this used
                // to require a brittle preg_replace at update time).
                $installedVersion = is_file(WK_ROOT . '/app/version.php')
                    ? (string) require WK_ROOT . '/app/version.php'
                    : '1.3.0';

                $appConfig = [
                    'app_name' => 'Whisker',
                    'base_url' => $baseUrl,
                    'base_path' => $basePath,
                    'debug'    => false,
                    'timezone' => $inst['timezone'],
                    'salt'     => $salt,
                ];
                $dbConfig = [
                    'host'    => $inst['db_host'],
                    'port'    => (int)$inst['db_port'],
                    'name'    => $inst['db_name'],
                    'user'    => $inst['db_user'],
                    'pass'    => $inst['db_pass'],
                    'charset' => 'utf8mb4',
                    'prefix'  => 'wk_',
                ];

                $appConfigPhp = "<?php\n// Whisker — Application Configuration\n// Generated by installer on " . date('Y-m-d H:i:s') . "\nreturn " . var_export($appConfig, true) . ";\n";
                $dbConfigPhp  = "<?php\n// Whisker — Database Configuration\n// Generated by installer on " . date('Y-m-d H:i:s') . "\nreturn " . var_export($dbConfig, true) . ";\n";

                if (file_put_contents(WK_ROOT . '/config/config.php', $appConfigPhp, LOCK_EX) === false) {
                    throw new \RuntimeException('Could not write config/config.php — check directory permissions.');
                }
                if (file_put_contents(WK_ROOT . '/config/database.php', $dbConfigPhp, LOCK_EX) === false) {
                    throw new \RuntimeException('Could not write config/database.php — check directory permissions.');
                }

                // Mark as installed (second sentinel — front controller already
                // uses config/config.php existence as the primary check).
                // L4: include SHA-256 checksums of the freshly-written config
                // files so a future integrity-check job can detect tampering
                // (a malicious actor swapping config/database.php to point at
                // their own DB would change the hash). The marker is plain
                // text and human-readable; a structured JSON line keeps it
                // both diff-friendly and parseable.
                //
                // whisker_version records what version this site was FIRST
                // installed at — never updated by the auto-updater, useful
                // as an audit trail and for support diagnosing schema state
                // on legacy installs.
                $integrity = json_encode([
                    'installed_at'         => date('Y-m-d H:i:s'),
                    'whisker_version'      => $installedVersion,
                    'config_php_sha256'    => hash_file('sha256', WK_ROOT . '/config/config.php'),
                    'database_php_sha256'  => hash_file('sha256', WK_ROOT . '/config/database.php'),
                ]);
                file_put_contents(
                    WK_ROOT . '/storage/.installed',
                    "DO NOT DELETE THIS FILE\n" . $integrity . "\n",
                    LOCK_EX
                );

                // Store base_url in settings too for SeoService
                $pdo->prepare("INSERT INTO wk_settings (setting_group, setting_key, setting_value) VALUES ('general', 'base_url', ?) ON DUPLICATE KEY UPDATE setting_value=?")
                    ->execute([$baseUrl, $baseUrl]);

                unset($_SESSION['wk_install']);
                $step = 6;
            } catch (Exception $e) {
                $error = 'Installation failed: ' . $e->getMessage();
            }
            break;
    }

    } // end CSRF-valid block

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error) && $step < 6) {
        header('Location: ?step=' . $step);
        exit;
    }
}

// ── Requirements ─────────────────────────────────────────────────────
$requirements = [
    ['PHP Version ≥ 8.0',       version_compare(PHP_VERSION, '8.0.0', '>=')],
    ['PDO Extension',            extension_loaded('pdo')],
    ['PDO MySQL Driver',         extension_loaded('pdo_mysql')],
    ['OpenSSL Extension',        extension_loaded('openssl')],
    ['cURL Extension',           extension_loaded('curl')],
    ['JSON Extension',           extension_loaded('json')],
    ['mbstring Extension',       extension_loaded('mbstring')],
    ['GD Extension (image processing)', extension_loaded('gd')],
    ['config/ is writable',      is_writable(WK_ROOT . '/config')],
    ['storage/ is writable',     is_writable(WK_ROOT . '/storage')],
    ['storage/uploads/ writable', is_writable(WK_ROOT . '/storage/uploads')],
    ['Root directory writable (for .htaccess)', is_writable(WK_ROOT)],
];
$allPassed = !in_array(false, array_column($requirements, 1));

/**
 * Currency choices for the install wizard. The installer runs standalone
 * (no autoloader / no DB yet), so this list is intentionally duplicated
 * from App\Services\CurrencyService::currencies() — keep the two in sync.
 */
function wk_install_currencies(): array
{
    return [
        'INR' => ['name' => 'Indian Rupee',      'symbol' => '₹'],
        'USD' => ['name' => 'US Dollar',          'symbol' => '$'],
        'EUR' => ['name' => 'Euro',               'symbol' => '€'],
        'GBP' => ['name' => 'British Pound',      'symbol' => '£'],
        'AUD' => ['name' => 'Australian Dollar',  'symbol' => 'A$'],
        'CAD' => ['name' => 'Canadian Dollar',    'symbol' => 'C$'],
        'JPY' => ['name' => 'Japanese Yen',       'symbol' => '¥'],
        'SGD' => ['name' => 'Singapore Dollar',   'symbol' => 'S$'],
        'AED' => ['name' => 'UAE Dirham',         'symbol' => 'د.إ'],
        'BRL' => ['name' => 'Brazilian Real',     'symbol' => 'R$'],
        'CNY' => ['name' => 'Chinese Yuan',       'symbol' => '¥'],
        'MYR' => ['name' => 'Malaysian Ringgit',  'symbol' => 'RM'],
        'THB' => ['name' => 'Thai Baht',          'symbol' => '฿'],
        'IDR' => ['name' => 'Indonesian Rupiah',  'symbol' => 'Rp'],
        'PHP' => ['name' => 'Philippine Peso',    'symbol' => '₱'],
        'KRW' => ['name' => 'South Korean Won',   'symbol' => '₩'],
        'ZAR' => ['name' => 'South African Rand', 'symbol' => 'R'],
        'SEK' => ['name' => 'Swedish Krona',      'symbol' => 'kr'],
        'NZD' => ['name' => 'New Zealand Dollar', 'symbol' => 'NZ$'],
        'CHF' => ['name' => 'Swiss Franc',        'symbol' => 'CHF'],
        'PLN' => ['name' => 'Polish Złoty',       'symbol' => 'zł'],
        'NOK' => ['name' => 'Norwegian Krone',    'symbol' => 'kr'],
        'DKK' => ['name' => 'Danish Krone',       'symbol' => 'kr'],
        'CZK' => ['name' => 'Czech Koruna',       'symbol' => 'Kč'],
        'HUF' => ['name' => 'Hungarian Forint',   'symbol' => 'Ft'],
        'RON' => ['name' => 'Romanian Leu',       'symbol' => 'lei'],
        'MXN' => ['name' => 'Mexican Peso',       'symbol' => 'MX$'],
        'HKD' => ['name' => 'Hong Kong Dollar',   'symbol' => 'HK$'],
    ];
}

$currencies = [];
foreach (wk_install_currencies() as $wkCode => $wkCur) {
    $currencies[$wkCode] = $wkCur['symbol'] . ' ' . $wkCur['name'];
}

$stepTitles = [1=>'Requirements',2=>'Database',3=>'Your Store',4=>'Admin Account',5=>'Payment Gateway',6=>'All Done!'];

require WK_ROOT . '/views/install/layout.php';
