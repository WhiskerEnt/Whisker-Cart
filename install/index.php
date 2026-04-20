<?php
/**
 * WHISKER — Web Installer v1.0.0
 * 6 steps: Requirements → Database → Store Setup → Admin Account → Gateway Setup → Complete
 * Author: Lohit T (mail@lohit.me)
 */

if (!defined('WK_ROOT')) {
    define('WK_ROOT', dirname(__DIR__));
}

// Already installed? Block access
if (file_exists(WK_ROOT . '/storage/.installed')) {
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Whisker</title></head><body style="font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#faf8f6">';
    echo '<div style="text-align:center"><h1>🐱 Already Installed</h1><p>Whisker is already installed and running.</p><p style="color:#6b7280;font-size:13px;margin-top:12px">For security, the installer is locked. Delete <code>storage/.installed</code> to re-run.</p><p><a href="/" style="color:#8b5cf6">Go to your store →</a></p></div>';
    echo '</body></html>';
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_name('wk_install');
    session_start();
}

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$step = max(1, min(6, $step));
$error = '';

// ── AJAX: Test DB Connection ─────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_ajax_action'] ?? '') === 'test_db') {
    header('Content-Type: application/json');
    try {
        $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', trim($_POST['db_host']), (int)$_POST['db_port']);
        $pdo = new PDO($dsn, trim($_POST['db_user']), $_POST['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5,
        ]);
        $dbName = trim($_POST['db_name']);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$dbName}`");
        echo json_encode(['success' => true, 'message' => "Connected! Database '{$dbName}' is ready."]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ── Handle POST submissions ──────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['_ajax_action'])) {

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

            try {
                $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $dbHost, $dbPort);
                $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

                // Try to use existing database (don't try to CREATE — shared hosting blocks it)
                try {
                    $pdo->exec("USE `{$dbName}`");
                } catch (\Exception $e) {
                    // If USE fails, try creating it (works on VPS/dedicated)
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
            $_SESSION['wk_install']['currency']      = trim($_POST['currency'] ?? 'INR');
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

                // Store settings
                $stmtS = $pdo->prepare("UPDATE wk_settings SET setting_value=? WHERE setting_group=? AND setting_key=?");
                foreach ([
                    ['general','site_name', $inst['store_name']],
                    ['general','site_tagline', $inst['store_tagline']??''],
                    ['general','currency', $inst['currency']],
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

                // Write config files
                $salt = bin2hex(random_bytes(32));
                file_put_contents(WK_ROOT . '/config/config.php', "<?php\nreturn [\n    'app_name' => 'Whisker',\n    'base_url' => '{$baseUrl}',\n    'base_path' => '{$basePath}',\n    'debug' => false,\n    'timezone' => '{$inst['timezone']}',\n    'version' => '1.0.0',\n    'salt' => '{$salt}',\n];\n");
                file_put_contents(WK_ROOT . '/config/database.php', "<?php\nreturn [\n    'host' => '{$inst['db_host']}',\n    'port' => {$inst['db_port']},\n    'name' => '{$inst['db_name']}',\n    'user' => '{$inst['db_user']}',\n    'pass' => '{$inst['db_pass']}',\n    'charset' => 'utf8mb4',\n    'prefix' => 'wk_',\n];\n");

                // Mark as installed
                file_put_contents(WK_ROOT . '/storage/.installed', date('Y-m-d H:i:s') . "\nDO NOT DELETE THIS FILE\n");

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

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error) && $step < 6) {
        header('Location: ?step=' . $step);
        exit;
    }
}

// ── Requirements ─────────────────────────────
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

$currencies = [
    'INR'=>'₹ Indian Rupee','USD'=>'$ US Dollar','EUR'=>'€ Euro','GBP'=>'£ British Pound',
    'AUD'=>'A$ Australian Dollar','CAD'=>'C$ Canadian Dollar','JPY'=>'¥ Japanese Yen',
    'SGD'=>'S$ Singapore Dollar','AED'=>'د.إ UAE Dirham',
];

$stepTitles = [1=>'Requirements',2=>'Database',3=>'Your Store',4=>'Admin Account',5=>'Payment Gateway',6=>'All Done!'];

require WK_ROOT . '/views/install/layout.php';
