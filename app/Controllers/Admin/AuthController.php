<?php
namespace App\Controllers\Admin;

use Core\Request;
use Core\Response;
use Core\Session;
use Core\View;
use Core\Database;
use Core\Validator;
use Core\RateLimiter;

class AuthController
{
    public function showLogin(Request $request, array $params = []): void
    {
        if (Session::isAdmin()) {
            Response::redirect(View::url('admin'));
            return;
        }
        View::render('admin/login', [], null);
    }

    public function login(Request $request, array $params = []): void
    {
        // Rate limit: max 5 attempts per 15 minutes per IP (file-based, survives session clear)
        $ip = $request->ip();

        if (!RateLimiter::attempt('admin_login', $ip, 5, 900)) {
            $wait = ceil(RateLimiter::remainingSeconds('admin_login', $ip, 900) / 60);
            Session::flash('error', "Too many login attempts. Try again in {$wait} minutes.");
            Response::redirect(View::url('admin/login'));
            return;
        }

        $v = new Validator($request->all(), [
            'username' => 'required',
            'password' => 'required',
        ]);

        if ($v->fails()) {
            Session::flash('error', $v->firstError());
            Response::redirect(View::url('admin/login'));
            return;
        }

        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired. Please try again.');
            Response::redirect(View::url('admin/login'));
            return;
        }

        $username = $request->clean('username');
        $password = $request->input('password');

        $admin = Database::fetch(
            "SELECT id, username, password_hash, is_active FROM wk_admins WHERE (username = ? OR email = ?) LIMIT 1",
            [$username, $username]
        );

        // Timing attack prevention: always run password_verify
        $dummyHash = '$2y$12$WApznUPhDubmVqEwEFOdDOwTJMoCEBIBbrl2TmKnSHblMAAAAAAAA';
        $hash = $admin ? $admin['password_hash'] : $dummyHash;
        $passwordValid = password_verify($password, $hash);

        if (!$admin || !$admin['is_active'] || !$passwordValid) {
            // RateLimiter already tracked the attempt in attempt() call above
            Session::flash('error', 'Invalid username or password.');
            Response::redirect(View::url('admin/login'));
            return;
        }

        // Reset attempts on success
        RateLimiter::reset('admin_login', $ip);
        Session::setAdmin($admin['id']);
        Database::update('wk_admins', ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$admin['id']]);

        Response::redirect(View::url('admin'));
    }

    public function logout(Request $request, array $params = []): void
    {
        Session::destroy();
        Response::redirect(View::url('admin/login'));
    }

    public function showForgotPassword(Request $request, array $params = []): void
    {
        View::render('admin/forgot-password', [], null);
    }

    public function forgotPassword(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.');
            Response::redirect(View::url('admin/forgot-password'));
            return;
        }

        $ip = $request->ip();
        if (!RateLimiter::attempt('admin_forgot', $ip, 3, 3600)) {
            Session::flash('error', 'Too many attempts. Try again in 1 hour.');
            Response::redirect(View::url('admin/forgot-password'));
            return;
        }

        $email = trim($request->clean('email') ?? '');
        // Always show success message (don't reveal if email exists)
        Session::flash('success', 'If an admin account exists with that email, a reset link has been sent.');

        if ($email) {
            $admin = Database::fetch("SELECT id, email, username FROM wk_admins WHERE email=? LIMIT 1", [$email]);
            if ($admin) {
                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $expiresAt = date('Y-m-d H:i:s', time() + 3600);

                // Delete any existing reset tokens for this admin
                try { Database::query("DELETE FROM wk_password_resets WHERE user_type='admin' AND user_id=?", [$admin['id']]); } catch (\Exception $e) {}

                // Store hashed token in dedicated table
                try {
                    Database::insert('wk_password_resets', [
                        'user_type'  => 'admin',
                        'user_id'    => $admin['id'],
                        'token_hash' => $tokenHash,
                        'ip_address' => $request->ip(),
                        'expires_at' => $expiresAt,
                    ]);
                } catch (\Exception $e) {
                    // Fallback: table might not exist yet on old installs
                    Database::query(
                        "INSERT INTO wk_settings (setting_group, setting_key, setting_value) VALUES ('admin_reset_tokens', ?, ?)
                         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                        ['token_' . $admin['id'], json_encode(['token' => $token, 'expires' => time() + 3600])]
                    );
                }

                $resetUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . View::url('admin/reset-password') . '?token=' . $token . '&id=' . $admin['id'];

                \App\Services\EmailService::send($admin['email'], 'Password Reset — Admin', '
                    <h2>Password Reset</h2>
                    <p>Hello ' . htmlspecialchars($admin['username']) . ',</p>
                    <p>Click the link below to reset your admin password. This link expires in 1 hour.</p>
                    <p><a href="' . $resetUrl . '" style="display:inline-block;padding:12px 24px;background:#8b5cf6;color:#fff;border-radius:8px;text-decoration:none;font-weight:700">Reset Password</a></p>
                    <p style="color:#6b7280;font-size:13px">If you didn\'t request this, ignore this email.</p>
                ');
            }
        }

        Response::redirect(View::url('admin/forgot-password'));
    }

    public function showResetPassword(Request $request, array $params = []): void
    {
        $token = $request->query('token') ?? '';
        $id = (int)($request->query('id') ?? 0);

        if (!$token || !$id || !self::validateResetToken($id, $token)) {
            Session::flash('error', 'Invalid or expired reset link.');
            Response::redirect(View::url('admin/login'));
            return;
        }

        View::render('admin/reset-password', ['token' => $token, 'id' => $id], null);
    }

    public function resetPassword(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.');
            Response::redirect(View::url('admin/login'));
            return;
        }

        $token = $request->input('token') ?? '';
        $id = (int)($request->input('id') ?? 0);
        $password = $request->input('password') ?? '';
        $confirm = $request->input('confirm_password') ?? '';

        if (!$token || !$id || !self::validateResetToken($id, $token)) {
            Session::flash('error', 'Invalid or expired reset link.');
            Response::redirect(View::url('admin/login'));
            return;
        }

        if (strlen($password) < 8) {
            Session::flash('error', 'Password must be at least 8 characters.');
            Response::redirect(View::url('admin/reset-password') . '?token=' . urlencode($token) . '&id=' . $id);
            return;
        }

        if ($password !== $confirm) {
            Session::flash('error', 'Passwords do not match.');
            Response::redirect(View::url('admin/reset-password') . '?token=' . urlencode($token) . '&id=' . $id);
            return;
        }

        Database::query("UPDATE wk_admins SET password_hash=? WHERE id=?", [
            password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            $id
        ]);

        // Delete token from new table + old table (for compatibility)
        try { Database::query("DELETE FROM wk_password_resets WHERE user_type='admin' AND user_id=?", [$id]); } catch (\Exception $e) {}
        try { Database::query("DELETE FROM wk_settings WHERE setting_group='admin_reset_tokens' AND setting_key=?", ['token_' . $id]); } catch (\Exception $e) {}

        Session::flash('success', 'Password reset successfully. Please sign in.');
        Response::redirect(View::url('admin/login'));
    }

    private static function validateResetToken(int $id, string $token): bool
    {
        $tokenHash = hash('sha256', $token);

        // Try new dedicated table first
        try {
            $row = Database::fetch(
                "SELECT id FROM wk_password_resets WHERE user_type='admin' AND user_id=? AND token_hash=? AND expires_at > NOW() AND used_at IS NULL",
                [$id, $tokenHash]
            );
            if ($row) return true;
        } catch (\Exception $e) {}

        // Fallback: old wk_settings storage (for installs that haven't migrated yet)
        try {
            $old = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='admin_reset_tokens' AND setting_key=?", ['token_' . $id]);
            if ($old) {
                $data = json_decode($old, true);
                if ($data && ($data['expires'] ?? 0) >= time() && hash_equals($data['token'] ?? '', $token)) {
                    return true;
                }
            }
        } catch (\Exception $e) {}

        return false;
    }
}
