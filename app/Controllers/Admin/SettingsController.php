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

            // Temporarily save SMTP settings for this test
            foreach (['smtp_host' => $host, 'smtp_port' => $port, 'smtp_user' => $user, 'smtp_pass' => $pass] as $k => $v) {
                Database::query("INSERT INTO wk_settings (setting_group,setting_key,setting_value) VALUES('email',?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)", [$k, $v]);
            }

            $storeName = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='site_name'") ?: 'Whisker Store';
            $sent = \App\Services\EmailService::send($to, "Test Email from {$storeName}",
                '<div style="text-align:center;padding:20px"><div style="font-size:48px;margin-bottom:12px">✅</div><h1 style="font-size:24px;font-weight:900;margin-bottom:8px">SMTP Works!</h1><p style="color:#6b7280;font-size:15px">This test email was sent from <strong>' . htmlspecialchars($storeName) . '</strong> using your SMTP settings.</p><div style="background:#faf8f6;border-radius:8px;padding:16px;margin-top:20px;font-size:13px;text-align:left"><strong>Host:</strong> ' . htmlspecialchars($host) . '<br><strong>Port:</strong> ' . $port . '<br><strong>User:</strong> ' . htmlspecialchars($user) . '<br><strong>Time:</strong> ' . date('M j, Y g:i:s A') . '</div></div>'
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
        $admin = Database::fetch("SELECT password_hash FROM wk_admins WHERE id=?", [$adminId]);
        if (!$admin || !password_verify($currentPassword, $admin['password_hash'])) {
            Session::flash('error', 'Current password is incorrect.');
            Response::redirect(View::url('admin/settings'));
            return;
        }

        // Update password
        $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        Database::update('wk_admins', ['password_hash' => $newHash], 'id = ?', [$adminId]);

        Session::flash('success', 'Password changed successfully.');
        Response::redirect(View::url('admin/settings'));
    }
}
