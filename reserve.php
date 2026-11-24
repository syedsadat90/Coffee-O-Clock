<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/orders.php';

$notice = $_SESSION['reservation_notice'] ?? '';
$noticeType = $_SESSION['reservation_notice_type'] ?? 'success';
unset($_SESSION['reservation_notice'], $_SESSION['reservation_notice_type']);
$csrfToken = csrf_token();
$times = [];
for ($hour = 8; $hour <= 20; $hour++) {
    $times[] = sprintf('%02d:00', $hour);
}
function reservation_h(string|int $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reserve a table | Coffee O'Clock</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dashboard-page">
    <header class="topbar">
        <a class="topbar-brand" href="shop.php"><span>Coffee O'Clock</span></a>
        <nav class="topbar-actions" aria-label="Customer navigation">
            <a class="button button-outline" href="shop.php">Order online</a>
            <a class="button button-outline" href="customer-account.php">Rewards account</a>
        </nav>
    </header>
    <main class="workspace">
        <section class="page-heading">
            <p class="eyebrow">Save your seat</p>
            <h1>Meet us at the cafe.</h1>
            <p>Request a one-hour table reservation. The cafe will confirm your booking.</p>
        </section>
        <?php if ($notice !== ''): ?>
            <div class="notice notice-<?= reservation_h($noticeType) ?>" role="status"><?= reservation_h($notice) ?></div>
        <?php endif; ?>
        <section class="panel">
            <form class="stacked-form" action="actions/reservation-create.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= reservation_h($csrfToken) ?>">
                <div class="form-row">
                    <div class="field"><label for="reservation-name">Your name</label>
                        <input id="reservation-name" name="name" maxlength="100"
                               value="<?= reservation_h((string) ($_SESSION['customer_name'] ?? '')) ?>" required></div>
                    <div class="field"><label for="reservation-phone">Phone number</label>
                        <input id="reservation-phone" name="phone" type="tel" maxlength="20"
                               value="<?= reservation_h((string) ($_SESSION['customer_phone'] ?? '')) ?>" required></div>
                </div>
                <div class="form-row">
                    <div class="field"><label for="reservation-date">Date</label>
                        <input id="reservation-date" name="date" type="date"
                               min="<?= reservation_h(date('Y-m-d')) ?>" required></div>
                    <div class="field"><label for="reservation-time">Start time</label>
                        <select id="reservation-time" name="time" required>
                            <?php foreach ($times as $time): ?>
                                <option value="<?= reservation_h($time) ?>"><?= reservation_h(date('g:i a', strtotime($time))) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                </div>
                <div class="form-row">
                    <div class="field"><label for="reservation-guests">Guests</label>
                        <input id="reservation-guests" name="guest_count" type="number" min="1" max="12" value="2" required></div>
                    <div class="field"><label for="reservation-note">Special request <span class="optional">(optional)</span></label>
                        <input id="reservation-note" name="note" maxlength="500" placeholder="High chair, accessibility, etc."></div>
                </div>
                <p class="table-note">Booking hours: 8:00 AM–8:00 PM. Each hour has limited seating; your reservation is pending until confirmed.</p>
                <button class="button button-primary" type="submit">Request reservation</button>
            </form>
        </section>
    </main>
</body>
</html>
