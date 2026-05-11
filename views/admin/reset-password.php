<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — Whisker Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root { --wk-purple: #8b5cf6; --wk-pink: #ec4899; --wk-green: #10b981; --wk-bg: #faf8f6; --wk-surface: #fff; --wk-text: #1e1b2e; --wk-muted: #6b7280; --wk-border: #e5e2dc; --wk-danger: #ef4444; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Nunito', sans-serif; background: var(--wk-bg); color: var(--wk-text); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .login-card { width: 100%; max-width: 420px; background: var(--wk-surface); border-radius: 16px; padding: 40px 36px; box-shadow: 0 1px 3px rgba(0,0,0,.04); }
        .login-header { text-align: center; margin-bottom: 28px; }
        .login-header h1 { font-size: 24px; font-weight: 900; margin-top: 12px; }
        .login-header h1 span { background: linear-gradient(135deg, var(--wk-purple), var(--wk-pink)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .login-header p { font-size: 14px; color: var(--wk-muted); margin-top: 4px; }
        .alert { padding: 12px 14px; border-radius: 8px; font-size: 13px; font-weight: 700; margin-bottom: 16px; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; }
        .alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #16a34a; }
        .field { margin-bottom: 18px; }
        .field label { display: block; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .8px; color: var(--wk-muted); margin-bottom: 6px; }
        .field input { width: 100%; padding: 12px 16px; border: 2px solid var(--wk-border); border-radius: 8px; font-family: 'Nunito', sans-serif; font-size: 14px; font-weight: 600; color: var(--wk-text); background: var(--wk-bg); outline: none; transition: border-color .2s; }
        .field input:focus { border-color: var(--wk-purple); box-shadow: 0 0 0 4px rgba(139,92,246,.1); }
        .btn-login { width: 100%; padding: 14px; border: none; border-radius: 8px; background: linear-gradient(135deg, var(--wk-purple), var(--wk-pink)); color: #fff; font-family: 'Nunito', sans-serif; font-size: 15px; font-weight: 800; cursor: pointer; box-shadow: 0 4px 15px rgba(139,92,246,.25); transition: all .2s; margin-top: 8px; }
        .btn-login:hover { transform: translateY(-1px); box-shadow: 0 6px 25px rgba(139,92,246,.35); }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <h1><span>Whisker</span></h1>
        <p>Set your new password</p>
    </div>

    <?php foreach ($_flashes ?? [] as $flash): ?>
        <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
    <?php endforeach; ?>

    <form method="POST" action="<?= \Core\View::url('admin/reset-password') ?>">
        <?= \Core\Session::csrfField() ?>
        <input type="hidden" name="token" value="<?= htmlspecialchars($token ?? '') ?>">
        <input type="hidden" name="id" value="<?= (int)($id ?? 0) ?>">
        <div class="field">
            <label>New Password</label>
            <input type="password" name="password" placeholder="Min 8 characters" required minlength="8" autofocus>
        </div>
        <div class="field">
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" placeholder="••••••••" required minlength="8">
        </div>
        <button type="submit" class="btn-login">Reset Password →</button>
    </form>
</div>

</body>
</html>
