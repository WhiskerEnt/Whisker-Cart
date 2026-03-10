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
}
