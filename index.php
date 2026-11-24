<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);
$csrfToken = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#063b28">
    <title>Sign in | Coffee O'Clock</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <main class="auth-shell">
        <section class="brand-panel">
            <a class="brand-logo" href="index.php" aria-label="Coffee O'Clock home">
                <img src="assets/images/coffee-o-clock-logo.jpg" alt="Coffee O'Clock cafe logo">
            </a>
            <p class="brand-caption">A little pause in your day.</p>
        </section>

        <section class="form-panel" aria-labelledby="page-title">
            <p class="eyebrow">Coffee O'Clock staff</p>
            <h1 id="page-title">Welcome back.</h1>
            <p class="intro">Sign in to your cafe workspace.</p>

            <?php if ($error !== ''): ?>
                <div class="notice notice-error" role="alert">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form action="actions/login.php" method="post">
                <input type="hidden" name="csrf_token"
                       value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <div class="field">
                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email" autocomplete="username"
                           maxlength="254" placeholder="you@example.com" required autofocus>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password"
                           autocomplete="current-password" maxlength="128"
                           placeholder="Enter your password" required>
                </div>

                <button class="button button-primary" type="submit">Sign in</button>
            </form>

            <p class="form-footnote">Need an account? Ask the cafe project administrator.</p>
            <p class="form-footnote"><a href="shop.php">Want to order? Browse our menu.</a></p>
            <p class="form-footnote"><a href="customer-account.php">Customer rewards account</a></p>
        </section>
    </main>
    <footer class="page-footer">Coffee O'Clock · Cafe management project</footer>
</body>
</html>
