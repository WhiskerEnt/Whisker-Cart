<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — Whisker Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --wk-purple: #8b5cf6; --wk-pink: #ec4899; --wk-green: #10b981;
            --wk-bg: #faf8f6; --wk-surface: #fff; --wk-text: #1e1b2e;
            --wk-muted: #6b7280; --wk-border: #e5e2dc; --wk-danger: #ef4444;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Nunito', sans-serif;
            background: var(--wk-bg);
            color: var(--wk-text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            position: relative;
            overflow: hidden;
        }
        body::before {
            content: ''; position: fixed; width: 500px; height: 500px;
            border-radius: 50%; background: var(--wk-purple); filter: blur(80px);
            opacity: .1; top: -150px; right: -100px; z-index: 0;
            animation: blobFloat 12s ease-in-out infinite alternate;
        }
        body::after {
            content: ''; position: fixed; width: 400px; height: 400px;
            border-radius: 50%; background: var(--wk-pink); filter: blur(80px);
            opacity: .1; bottom: -100px; left: -80px; z-index: 0;
            animation: blobFloat 10s ease-in-out infinite alternate-reverse;
        }
        @keyframes blobFloat {
            0% { transform: translate(0,0) scale(1); }
            100% { transform: translate(40px,-30px) scale(1.1); }
        }

        .login-card {
            background: var(--wk-surface);
            border: 1px solid var(--wk-border);
            border-radius: 14px;
            padding: 40px;
            width: 100%; max-width: 420px;
            box-shadow: 0 20px 60px rgba(0,0,0,.06);
            position: relative; z-index: 1;
            animation: cardPop .5s cubic-bezier(.34,1.56,.64,1);
        }
        @keyframes cardPop {
            from { opacity: 0; transform: translateY(20px) scale(.97); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        .login-header { text-align: center; margin-bottom: 32px; }
        .login-header svg { margin-bottom: 16px; }
        .login-header h1 { font-size: 24px; font-weight: 900; letter-spacing: -.5px; }
        .login-header h1 span {
            background: linear-gradient(135deg, var(--wk-purple), var(--wk-pink));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .login-header p { font-size: 14px; color: var(--wk-muted); margin-top: 4px; }

        .alert {
            padding: 12px 16px; border-radius: 8px; font-size: 13px;
            font-weight: 600; margin-bottom: 20px;
            animation: shake .4s ease;
        }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: var(--wk-danger); }
        @keyframes shake { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-4px)} 75%{transform:translateX(4px)} }

        .field { margin-bottom: 18px; }
        .field label {
            display: block; font-size: 12px; font-weight: 800;
            text-transform: uppercase; letter-spacing: .8px;
            color: var(--wk-muted); margin-bottom: 6px;
        }
        .field input {
            width: 100%; padding: 12px 16px;
            border: 2px solid var(--wk-border); border-radius: 8px;
            font-family: 'Nunito', sans-serif; font-size: 14px; font-weight: 600;
            color: var(--wk-text); background: var(--wk-bg);
            outline: none; transition: border-color .2s, box-shadow .2s;
        }
        .field input:focus {
            border-color: var(--wk-purple);
            box-shadow: 0 0 0 4px rgba(139,92,246,.1);
        }
        .field input::placeholder { color: #b0adb8; font-weight: 500; }

        .btn-login {
            width: 100%; padding: 14px; border: none; border-radius: 8px;
            background: linear-gradient(135deg, var(--wk-purple), var(--wk-pink));
            color: #fff; font-family: 'Nunito', sans-serif;
            font-size: 15px; font-weight: 800; cursor: pointer;
            box-shadow: 0 4px 15px rgba(139,92,246,.25);
            transition: all .2s; margin-top: 8px;
        }
        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 25px rgba(139,92,246,.35);
        }
        .btn-login:active { transform: translateY(0); }

        .login-footer {
            text-align: center; margin-top: 28px; padding-top: 20px;
            border-top: 1px solid var(--wk-border);
            font-size: 12px; color: var(--wk-muted);
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <svg width="56" height="56" viewBox="0 0 56 56" fill="none">
            <circle cx="28" cy="28" r="26" fill="url(#lg)" stroke="url(#ls)" stroke-width="2"/>
            <path d="M16 10 L12 22 L22 18Z" fill="#8b5cf6"/>
            <path d="M40 10 L44 22 L34 18Z" fill="#ec4899"/>
            <path d="M16.5 13 L14 20 L20 17.5Z" fill="#a78bfa"/>
            <path d="M39.5 13 L42 20 L36 17.5Z" fill="#f472b6"/>
            <circle cx="21" cy="26" r="3.5" fill="#1e1b2e"/>
            <circle cx="35" cy="26" r="3.5" fill="#1e1b2e"/>
            <circle cx="22" cy="25" r="1.2" fill="#fff"/>
            <circle cx="36" cy="25" r="1.2" fill="#fff"/>
            <ellipse cx="28" cy="31" rx="2" ry="1.5" fill="#f472b6"/>
            <path d="M24 33 Q28 37 32 33" stroke="#1e1b2e" stroke-width="1.5" fill="none" stroke-linecap="round"/>
            <line x1="6" y1="26" x2="17" y2="28" stroke="#8b5cf6" stroke-width="1.5" stroke-linecap="round"/>
            <line x1="6" y1="30" x2="17" y2="30" stroke="#a78bfa" stroke-width="1.5" stroke-linecap="round"/>
            <line x1="6" y1="34" x2="17" y2="32" stroke="#c4b5fd" stroke-width="1.5" stroke-linecap="round"/>
            <line x1="50" y1="26" x2="39" y2="28" stroke="#ec4899" stroke-width="1.5" stroke-linecap="round"/>
            <line x1="50" y1="30" x2="39" y2="30" stroke="#f472b6" stroke-width="1.5" stroke-linecap="round"/>
            <line x1="50" y1="34" x2="39" y2="32" stroke="#f9a8d4" stroke-width="1.5" stroke-linecap="round"/>
            <defs>
                <linearGradient id="lg" x1="0" y1="0" x2="56" y2="56"><stop offset="0%" stop-color="#faf8f6"/><stop offset="100%" stop-color="#f3f0eb"/></linearGradient>
                <linearGradient id="ls" x1="0" y1="0" x2="56" y2="56"><stop offset="0%" stop-color="#8b5cf6"/><stop offset="100%" stop-color="#ec4899"/></linearGradient>
            </defs>
        </svg>
        <h1><span>Whisker</span></h1>
        <p>Sign in to your admin panel</p>
    </div>

    <?php foreach ($_flashes ?? [] as $flash): ?>
        <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
    <?php endforeach; ?>

    <form method="POST" action="<?= \Core\View::url('admin/login') ?>">
        <?= \Core\Session::csrfField() ?>
        <div class="field">
            <label>Username or Email</label>
            <input type="text" name="username" placeholder="admin" required autofocus>
        </div>
        <div class="field">
            <label>Password</label>
            <input type="password" name="password" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn-login">Sign In →</button>
    </form>

    <div style="text-align:center;margin-top:16px">
        <a href="<?= \Core\View::url('admin/forgot-password') ?>" style="font-size:13px;color:var(--wk-purple);font-weight:700;text-decoration:none">Forgot Password?</a>
    </div>

    <div class="login-footer">
        Whisker v<?= WK_VERSION ?> · Free Edition
    </div>
</div>

</body>
</html>
