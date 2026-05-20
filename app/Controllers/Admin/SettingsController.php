<?php
namespace App\Controllers\Admin;
use Core\{Request, View, Database, Response, Session};

class SettingsController
{
    public function index(Request $request, array $params = []): void
    {
        $settings = [];
        $rows = Database::fetchAll("SELECT setting_group, setting_key, setting_value FROM wk_settings");
        foreach ($rows as $row) {
            $settings[$row['setting_group']][$row['setting_key']] = $row['setting_value'];
        }
        View::render('admin/settings', ['pageTitle'=>'Settings','settings'=>$settings], 'admin/layouts/main');
    }

    public function update(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error','Session expired.');
            Response::redirect(View::url('admin/settings'));
            return;
        }
        $fields = [
            'general' => ['site_name','site_tagline','logo_url','store_theme','chatbot_name','chatbot_enabled','contact_email','currency','currency_symbol','timezone'],
            'checkout'=> ['guest_checkout','tax_rate'],
            'email'   => ['from_email','from_name','smtp_host','smtp_port','smtp_user','smtp_pass'],
        ];
        foreach ($fields as $group => $keys) {
            foreach ($keys as $key) {
                $val = $request->input("{$group}_{$key}");
                if ($val !== null) {
                    Database::query(
                        "INSERT INTO wk_settings (setting_group,setting_key,setting_value) VALUES(?,?,?)
                         ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)",
                        [$group, $key, trim($val)]
                    );
                }
            }
        }
        // M10/M14: drop the request-scoped settings cache so any later call
        // to Database::setting() in this request sees the new values. Without
        // this, code that read a setting earlier in the request (e.g. via a
        // shared service) would still see the pre-update value.
        Database::clearSettingsCache();

        Session::flash('success','Settings saved!');
        Response::redirect(View::url('admin/settings'));
    }

    public function testSmtp(Request $request, array $params = []): void
    {
        $action = $request->input('action');
        $host = trim($request->input('host') ?? '');
        $port = (int)($request->input('port') ?: 587);
        $user = trim($request->input('user') ?? '');
        $pass = $request->input('pass') ?? '';

        if (!$host) {
            Response::json(['success' => false, 'message' => 'SMTP host is required']);
            return;
        }

        // Finding 19: even though this endpoint is admin-only, the parameters
        // (host, port) are admin-controlled — and an SSRF here lets a
        // compromised admin session probe internal services on the same
        // network (RDS, Redis, internal admin panels, cloud metadata at
        // 169.254.169.254, etc.) and read SMTP banners for fingerprinting.
        // Restrict to (a) standard SMTP ports and (b) hosts that are not
        // private/loopback/link-local. Public SMTP providers all sit on the
        // standard ports so this doesn't break legitimate use.
        $allowedPorts = [25, 465, 587, 2525];
        if (!in_array($port, $allowedPorts, true)) {
            Response::json(['success' => false, 'message' => 'Only standard SMTP ports allowed: ' . implode(', ', $allowedPorts)]);
            return;
        }
        if (!self::isPublicSmtpHost($host)) {
            Response::json(['success' => false, 'message' => 'Host is not a valid public SMTP endpoint. Loopback, private, link-local, and cloud-metadata addresses are blocked.']);
            return;
        }

        if ($action === 'test_connection') {
            try {
                $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
                $conn = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
                if (!$conn) {
                    Response::json(['success' => false, 'message' => "Cannot connect to {$host}:{$port} — {$errstr}"]);
                    return;
                }
                $greeting = fgets($conn, 512);
                fwrite($conn, "EHLO whisker\r\n");
                $ehlo = fgets($conn, 512);

                // Try STARTTLS
                fwrite($conn, "STARTTLS\r\n");
                $tlsResp = fgets($conn, 512);
                $tlsOk = str_starts_with(trim($tlsResp), '220');

                if ($tlsOk) {
                    stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                    fwrite($conn, "EHLO whisker\r\n");
                    fgets($conn, 512);
                }

                // Try AUTH if credentials provided
                if ($user) {
                    fwrite($conn, "AUTH LOGIN\r\n");
                    $authResp = fgets($conn, 512);
                    fwrite($conn, base64_encode($user) . "\r\n");
                    fgets($conn, 512);
                    fwrite($conn, base64_encode($pass) . "\r\n");
                    $loginResp = trim(fgets($conn, 512));

                    fwrite($conn, "QUIT\r\n");
                    fclose($conn);

                    if (str_starts_with($loginResp, '235')) {
                        Response::json(['success' => true, 'message' => "Connected & authenticated! TLS: " . ($tlsOk ? 'Yes' : 'No')]);
                    } else {
                        Response::json(['success' => false, 'message' => "Connected but login failed: {$loginResp}"]);
                    }
                } else {
                    fwrite($conn, "QUIT\r\n");
                    fclose($conn);
                    Response::json(['success' => true, 'message' => "Connected to {$host}:{$port} — TLS: " . ($tlsOk ? 'Yes' : 'No') . ". No auth tested (no credentials)."]);
                }
            } catch (\Throwable $e) {
                Response::json(['success' => false, 'message' => 'SMTP connection failed. Check your settings.']);
            }
            return;
        }

        if ($action === 'test_email') {
            $to = trim($request->input('to') ?? '');
            if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                Response::json(['success' => false, 'message' => 'Invalid email address']);
                return;
            }

            // IMPORTANT: do NOT write the submitted SMTP credentials to
            // wk_settings here. If the test fails (e.g. wrong password), the
            // bad credentials would replace the working ones and break all
            // outgoing email. The test runs against the credentials the admin
            // typed; only the explicit "Save" form action persists them.
            $storeName = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='site_name'") ?: 'Whisker Store';
            $sent = \App\Services\EmailService::sendWithSmtp(
                $to,
                "Test Email from {$storeName}",
                '<div style="text-align:center;padding:20px"><div style="font-size:48px;margin-bottom:12px">✅</div><h1 style="font-size:24px;font-weight:900;margin-bottom:8px">SMTP Works!</h1><p style="color:#6b7280;font-size:15px">This test email was sent from <strong>' . htmlspecialchars($storeName) . '</strong> using your SMTP settings.</p><div style="background:#faf8f6;border-radius:8px;padding:16px;margin-top:20px;font-size:13px;text-align:left"><strong>Host:</strong> ' . htmlspecialchars($host) . '<br><strong>Port:</strong> ' . $port . '<br><strong>User:</strong> ' . htmlspecialchars($user) . '<br><strong>Time:</strong> ' . date('M j, Y g:i:s A') . '</div></div>',
                ['host' => $host, 'port' => $port, 'user' => $user, 'pass' => $pass]
            );

            Response::json([
                'success' => $sent,
                'message' => $sent ? "Test email sent to {$to}! Check your inbox." : "Failed to send. Check your SMTP credentials and try again."
            ]);
            return;
        }

        Response::json(['success' => false, 'message' => 'Unknown action']);
    }

    /**
     * Change admin password
     */
    public function changePassword(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.');
            Response::redirect(View::url('admin/settings'));
            return;
        }

        $currentPassword = $request->input('current_password') ?? '';
        $newPassword = $request->input('new_password') ?? '';
        $confirmPassword = $request->input('confirm_password') ?? '';

        // Validate
        if (!$currentPassword || !$newPassword || !$confirmPassword) {
            Session::flash('error', 'All password fields are required.');
            Response::redirect(View::url('admin/settings'));
            return;
        }

        if ($newPassword !== $confirmPassword) {
            Session::flash('error', 'New passwords do not match.');
            Response::redirect(View::url('admin/settings'));
            return;
        }

        if (strlen($newPassword) < 8) {
            Session::flash('error', 'New password must be at least 8 characters.');
            Response::redirect(View::url('admin/settings'));
            return;
        }

        // Verify current password
        $adminId = Session::adminId();

        // Finding 20: rate-limit current-password attempts. A stolen admin
        // session cookie is the realistic attack here — if the attacker has
        // the cookie, /admin/settings opens for them and they can try to
        // change the password (which would lock the real admin out). Without
        // a limit they can grind current_password offline-fast. 5 wrong
        // attempts per admin per 15 minutes is the same window as login.
        $rlKey = 'admin_pwchange_' . $adminId;
        if (!\Core\RateLimiter::attempt($rlKey, (string)$adminId, 5, 900)) {
            $wait = (int)ceil(\Core\RateLimiter::remainingSeconds($rlKey, (string)$adminId, 900) / 60);
            Session::flash('error', "Too many password change attempts. Try again in {$wait} minute" . ($wait === 1 ? '' : 's') . '.');
            Response::redirect(View::url('admin/settings'));
            return;
        }

        $admin = Database::fetch("SELECT password_hash FROM wk_admins WHERE id=?", [$adminId]);
        if (!$admin || !password_verify($currentPassword, $admin['password_hash'])) {
            Session::flash('error', 'Current password is incorrect.');
            Response::redirect(View::url('admin/settings'));
            return;
        }

        // Successful verify — reset the limiter so legitimate retries after
        // a typo don't accumulate against future attempts.
        \Core\RateLimiter::reset($rlKey, (string)$adminId);

        // Update password
        $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        Database::update('wk_admins', ['password_hash' => $newHash], 'id = ?', [$adminId]);

        Session::flash('success', 'Password changed successfully.');
        Response::redirect(View::url('admin/settings'));
    }

    /**
     * Finding 19: returns true if $host is a public, non-special-use SMTP
     * endpoint. Rejects loopback, RFC1918 private, link-local (including
     * 169.254.169.254 cloud metadata), broadcast, and IPv6 equivalents.
     * Hostnames are resolved to their IPv4 record and the IP checked too —
     * a clever admin can't bypass with a public DNS name that resolves to
     * 127.0.0.1.
     */
    private static function isPublicSmtpHost(string $host): bool
    {
        $host = strtolower(trim($host));
        if ($host === '' || strlen($host) > 253) return false;

        // Explicit rejects for hostname-form references to localhost.
        $denyNames = ['localhost', 'localhost.localdomain', 'ip6-localhost', 'ip6-loopback'];
        if (in_array($host, $denyNames, true)) return false;

        // Resolve hostname → IPs. If it's already an IP, gethostbynamel returns it.
        // We check ALL A records so an attacker can't pad with a public IP and a
        // private one.
        $ips = @gethostbynamel($host);
        if (!$ips) {
            // Could be IPv6-only; try DNS lookup for both.
            $records = @dns_get_record($host, DNS_A | DNS_AAAA);
            $ips = [];
            foreach ($records ?: [] as $r) {
                if (!empty($r['ip']))   $ips[] = $r['ip'];
                if (!empty($r['ipv6'])) $ips[] = $r['ipv6'];
            }
        }
        if (!$ips) return false; // unresolvable → reject

        foreach ($ips as $ip) {
            if (!filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            )) {
                // The flags reject: private (10/8, 172.16/12, 192.168/16, fc00::/7),
                // reserved (loopback, link-local, multicast, unspecified, broadcast,
                // documentation, 169.254/16 incl. cloud metadata).
                return false;
            }
        }
        return true;
    }
}
