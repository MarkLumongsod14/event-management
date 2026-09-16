<?php require_once __DIR__ . '/includes/auth.php'; if (isLoggedIn()) { header('Location: index.php'); exit; } ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="assets/img/logo.png">
<title>Login — Event Management</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script>window.tailwind = window.tailwind || {}; window.tailwind.config = {corePlugins: {preflight: false}};</script>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-body">
<main class="login-shell">
    <section class="login-welcome">
        <img src="assets/img/logo.png" alt="Institution logo" class="login-logo">
        <span class="login-eyebrow">Event Management System</span>
        <h1>Hello, Welcome</h1>
        <p>Manage events, judging, participants, and results in one place.</p>
    </section>
    <section class="login-form-panel">
        <div class="login-form-heading">
            <span class="login-eyebrow">Event Management</span>
            <h2>Login</h2>
            <p>Sign in to continue to your account.</p>
        </div>
        <form id="loginForm">
            <div class="form-group">
                <label for="username">Username</label>
                <div class="login-input-wrap">
                    <input type="text" id="username" autocomplete="username" placeholder="Enter your username" required autofocus>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 21a8 8 0 0 0-16 0M12 13a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/></svg>
                </div>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-field login-input-wrap">
                    <input type="password" id="password" autocomplete="current-password" placeholder="Enter your password" required>
                    <button type="button" id="togglePassword" class="password-toggle" aria-label="Show password">Show</button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block" id="loginSubmit">Login</button>
            <div id="loginError" class="error-msg"></div>
        </form>
    </section>
</main>
<script>
document.getElementById('loginForm').addEventListener('submit', async e => {
    e.preventDefault();
    const err = document.getElementById('loginError');
    const submit = document.getElementById('loginSubmit');
    err.textContent = '';
    submit.disabled = true;
    submit.textContent = 'Signing in...';
    try {
        const res = await fetch('api/login.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({
                username: document.getElementById('username').value,
                password: document.getElementById('password').value
            })
        });
        const data = await res.json();
        if (data.success) window.location.href = 'index.php';
        else err.textContent = data.error || 'Login failed';
    } catch {
        err.textContent = 'Unable to connect. Please try again.';
    } finally {
        submit.disabled = false;
        submit.textContent = 'Sign In';
    }
});

document.getElementById('togglePassword').addEventListener('click', () => {
    const password = document.getElementById('password');
    const visible = password.type === 'text';
    password.type = visible ? 'password' : 'text';
    document.getElementById('togglePassword').textContent = visible ? 'Show' : 'Hide';
    document.getElementById('togglePassword').setAttribute('aria-label', visible ? 'Show password' : 'Hide password');
});
</script>
</body>
</html>