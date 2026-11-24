<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/customers.php';

$customer = null;
$history = [];
$pointsHistory = [];
$error = '';
if (!empty($_SESSION['customer_id'])) {
    try {
        $database = database_connection();
        $statement = $database->prepare(
            'SELECT id, full_name, phone, points
             FROM customer_accounts WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => (int) $_SESSION['customer_id']]);
        $customer = $statement->fetch() ?: null;
        if ($customer) {
            $history = customer_order_history($database, (string) $customer['phone']);
            $pointsHistory = customer_loyalty_history($database, (int) $customer['id']);
        } else {
            unset($_SESSION['customer_id'], $_SESSION['customer_name'], $_SESSION['customer_phone']);
        }
    } catch (PDOException $exception) {
        error_log('Coffee O Clock customer account error: ' . $exception->getMessage());
        $error = 'Customer accounts are unavailable. Import database/update-4.sql and try again.';
    }
}
$notice = $_SESSION['customer_notice'] ?? '';
$noticeType = $_SESSION['customer_notice_type'] ?? 'success';
unset($_SESSION['customer_notice'], $_SESSION['customer_notice_type']);
$csrfToken = csrf_token();

function customer_h(string|int|float $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rewards account | Coffee O'Clock</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dashboard-page">
    <header class="topbar">
        <a class="topbar-brand" href="shop.php"><span>Coffee O'Clock</span></a>
        <nav class="topbar-actions" aria-label="Customer navigation">
            <a class="button button-outline" href="shop.php">Order online</a>
            <a class="button button-outline" href="reserve.php">Reserve a table</a>
        </nav>
    </header>
    <main class="workspace">
        <section class="page-heading">
            <p class="eyebrow">Coffee O'Clock rewards</p>
            <h1>Your visits, rewarded.</h1>
            <p>Earn 1 point for every ৳100 on delivered orders. Redeem 100 points for ৳10 off.</p>
        </section>
        <?php if ($error !== ''): ?>
            <div class="notice notice-error" role="alert"><?= customer_h($error) ?></div>
        <?php elseif ($notice !== ''): ?>
            <div class="notice notice-<?= customer_h($noticeType) ?>" role="status"><?= customer_h($notice) ?></div>
        <?php endif; ?>
        <?php if ($customer): ?>
            <section class="summary-grid">
                <article class="summary-card">
                    <span class="summary-value"><?= customer_h($customer['points']) ?></span>
                    <span class="summary-label">Available points</span>
                </article>
                <article class="summary-card">
                    <span class="summary-value"><?= customer_h($customer['full_name']) ?></span>
                    <span class="summary-label"><?= customer_h($customer['phone']) ?></span>
                </article>
            </section>
            <section class="panel">
                <div class="panel-heading"><div><p class="eyebrow">Recent activity</p><h2>Point history</h2></div></div>
                <?php if ($pointsHistory === []): ?>
                    <p class="empty-cell">Your first point update will appear after a delivered order.</p>
                <?php else: ?>
                    <div class="table-wrap"><table>
                        <thead><tr><th>Points</th><th>Activity</th><th>Date</th></tr></thead>
                        <tbody><?php foreach ($pointsHistory as $entry): ?>
                            <tr>
                                <td><?= (int) $entry['points_change'] > 0 ? '+' : '' ?><?= customer_h($entry['points_change']) ?></td>
                                <td><?= customer_h(ucfirst((string) $entry['reason'])) ?></td>
                                <td><?= customer_h(date('M j, Y g:i a', strtotime($entry['created_at']))) ?></td>
                            </tr>
                        <?php endforeach; ?></tbody>
                    </table></div>
                <?php endif; ?>
            </section>
            <section class="panel">
                <div class="panel-heading"><div><p class="eyebrow">Order history</p><h2>Your orders</h2></div></div>
                <?php if ($history === []): ?>
                    <p class="empty-cell">No orders are linked to your phone yet.</p>
                <?php else: ?>
                    <div class="table-wrap"><table>
                        <thead><tr><th>Order</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
                        <tbody><?php foreach ($history as $order): ?>
                            <tr>
                                <td><?= customer_h($order['order_code']) ?></td>
                                <td>৳<?= customer_h(number_format((float) $order['total'], 2)) ?></td>
                                <td><?= customer_h(ucwords(str_replace('_', ' ', (string) $order['status']))) ?></td>
                                <td><?= customer_h(date('M j, Y g:i a', strtotime($order['created_at']))) ?></td>
                            </tr>
                        <?php endforeach; ?></tbody>
                    </table></div>
                <?php endif; ?>
                <form action="actions/customer-logout.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= customer_h($csrfToken) ?>">
                    <button class="button button-outline" type="submit">Sign out</button>
                </form>
            </section>
        <?php else: ?>
            <div class="content-grid">
                <section class="panel">
                    <p class="eyebrow">Welcome back</p><h2>Sign in</h2>
                    <form class="stacked-form" action="actions/customer-login.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= customer_h($csrfToken) ?>">
                        <div class="field"><label for="login-phone">Phone number</label>
                            <input id="login-phone" name="phone" type="tel" maxlength="20" required></div>
                        <div class="field"><label for="login-password">Password</label>
                            <input id="login-password" name="password" type="password" minlength="8" required></div>
                        <button class="button button-primary" type="submit">Sign in</button>
                    </form>
                </section>
                <section class="panel">
                    <p class="eyebrow">Join the cafe</p><h2>Create your account</h2>
                    <form class="stacked-form" action="actions/customer-register.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= customer_h($csrfToken) ?>">
                        <div class="field"><label for="register-name">Full name</label>
                            <input id="register-name" name="name" maxlength="100" autocomplete="name" required></div>
                        <div class="field"><label for="register-phone">Phone number</label>
                            <input id="register-phone" name="phone" type="tel" maxlength="20" autocomplete="tel" required></div>
                        <div class="field"><label for="register-password">Password</label>
                            <input id="register-password" name="password" type="password" minlength="8" required></div>
                        <button class="button button-primary" type="submit">Create account</button>
                    </form>
                </section>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
