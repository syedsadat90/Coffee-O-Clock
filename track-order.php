<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/orders.php';

$csrfToken = csrf_token();
$order = null;
$error = '';
$lookupCode = '';
$lookupPhone = '';
$confirmation = $_SESSION['order_confirmation'] ?? null;
unset($_SESSION['order_confirmation']);

if (is_array($confirmation)) {
    $lookupCode = (string) ($confirmation['code'] ?? '');
    $lookupPhone = (string) ($confirmation['phone'] ?? '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lookupCode = is_string($_POST['order_code'] ?? null)
        ? strtoupper(trim($_POST['order_code']))
        : '';
    $lookupPhone = is_string($_POST['phone'] ?? null)
        ? normalize_customer_phone($_POST['phone'])
        : '';

    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $error = 'This tracking form expired. Reload the page and try again.';
    } elseif (!preg_match('/^CO-[A-F0-9]{12}$/', $lookupCode)
        || !valid_customer_phone($lookupPhone)) {
        $error = 'Enter a valid order code and phone number.';
    } else {
        try {
            $order = find_customer_order(database_connection(), $lookupCode, $lookupPhone);
            if ($order === null) {
                $error = 'We could not find an order matching those details.';
            }
        } catch (PDOException $exception) {
            error_log('Coffee O Clock order tracking database error: ' . $exception->getMessage());
            http_response_code(503);
            $error = 'Order tracking is temporarily unavailable. Please try again later.';
        }
    }
} elseif (is_array($confirmation)) {
    try {
        $order = find_customer_order(database_connection(), $lookupCode, $lookupPhone);
        if ($order === null) {
            $error = 'Your order was placed, but we could not load its tracking details. Use your order code and phone number below.';
        }
    } catch (PDOException $exception) {
        error_log('Coffee O Clock order confirmation database error: ' . $exception->getMessage());
        http_response_code(503);
        $error = 'Your order was placed. Tracking is temporarily unavailable; please try again shortly.';
    }
}

function tracking_h(string|int|float $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$statusLabels = order_status_labels();
$activeStatus = is_array($order) ? (string) $order['status'] : '';
$deliveryEstimate = is_array($order) ? customer_delivery_estimate($order) : null;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#063b28">
    <title>Track order | Coffee O'Clock</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dashboard-page customer-page">
    <header class="topbar">
        <a class="topbar-brand" href="shop.php">
            <img src="assets/images/coffee-o-clock-logo.jpg" alt="">
            <span>Coffee O'Clock</span>
        </a>
        <nav class="topbar-actions" aria-label="Customer navigation">
            <a class="button button-primary customer-nav-primary" href="shop.php">
                Order from menu <span aria-hidden="true">→</span>
            </a>
            <a class="button button-outline" href="index.php">Staff sign in</a>
            <a class="button button-outline" href="customer-account.php">Rewards account</a>
        </nav>
    </header>

    <main class="workspace tracking-workspace">
        <section class="shop-hero tracking-hero">
            <span class="tracking-hero-mark">
                <img src="assets/images/coffee-o-clock-logo.jpg" alt="Coffee O'Clock logo">
            </span>
            <p class="eyebrow">Coffee O'Clock delivery</p>
            <h1>Track your order.</h1>
            <p>Enter the order code shown after checkout and the same phone number you used when placing the order.</p>
        </section>

        <?php if ($error !== ''): ?>
            <div class="notice notice-<?= $activeStatus === '' ? 'error' : 'success' ?>" role="status">
                <?= tracking_h($error) ?>
            </div>
        <?php endif; ?>

        <section class="panel tracking-lookup">
            <div class="tracking-lookup-heading">
                <span class="tracking-lookup-icon" aria-hidden="true">01</span>
                <div>
                    <h2>Find your delivery</h2>
                    <p>Your order code and checkout phone number are all you need.</p>
                </div>
            </div>
            <form action="track-order.php" method="post" class="checkout-grid">
                <input type="hidden" name="csrf_token" value="<?= tracking_h($csrfToken) ?>">
                <div class="field">
                    <label for="order-code">Order code</label>
                    <input id="order-code" name="order_code" value="<?= tracking_h($lookupCode) ?>"
                           placeholder="CO-1A2B3C4D5E6F" pattern="CO-[A-Fa-f0-9]{12}" required>
                </div>
                <div class="field">
                    <label for="tracking-phone">Phone number</label>
                    <input id="tracking-phone" name="phone" type="tel" value="<?= tracking_h($lookupPhone) ?>"
                           autocomplete="tel" placeholder="01XXXXXXXXX or +8801XXXXXXXXX" required>
                </div>
                <button class="button button-primary tracking-button" type="submit">Find my order</button>
            </form>
            <p class="tracking-form-note"><span aria-hidden="true">↳</span> Bangladesh numbers work in either 01… or +8801… format.</p>
        </section>

        <?php if ($order !== null): ?>
            <section class="panel order-result" aria-labelledby="order-result-heading">
                <header class="tracking-order-banner">
                    <div class="tracking-order-title">
                        <span class="tracking-order-mark" aria-hidden="true">✓</span>
                        <div>
                            <p class="eyebrow">YOUR ORDER · <?= tracking_h($order['order_code']) ?></p>
                        <h2 id="order-result-heading"><?= tracking_h($statusLabels[$activeStatus] ?? 'Order update') ?></h2>
                            <p>Placed <?= tracking_h(date('M j, Y · g:i a', strtotime($order['created_at']))) ?></p>
                        </div>
                    </div>
                    <span class="tracking-payment-pill"><span aria-hidden="true">৳</span> Pay on delivery</span>
                </header>

                <form action="track-order.php" method="post" class="tracking-refresh-form">
                    <input type="hidden" name="csrf_token" value="<?= tracking_h($csrfToken) ?>">
                    <input type="hidden" name="order_code" value="<?= tracking_h($order['order_code']) ?>">
                    <input type="hidden" name="phone" value="<?= tracking_h($lookupPhone) ?>">
                    <span>Status changes show after you refresh tracking.</span>
                    <button class="button button-outline" type="submit">Refresh status</button>
                </form>

                <?php if ($deliveryEstimate !== null): ?>
                    <aside class="delivery-eta-card" aria-label="<?= tracking_h($deliveryEstimate['title']) ?>">
                        <span class="delivery-eta-icon" aria-hidden="true">⌚</span>
                        <div>
                            <p><?= tracking_h($deliveryEstimate['title']) ?></p>
                            <strong><?= tracking_h($deliveryEstimate['window']) ?></strong>
                            <span><?= tracking_h($deliveryEstimate['note']) ?></span>
                        </div>
                    </aside>
                <?php endif; ?>

                <?php if ($activeStatus === 'cancelled'): ?>
                    <p class="order-cancelled">This order was cancelled. Contact the cafe if you need help.</p>
                <?php else: ?>
                    <?php
                    $progressStatuses = ['pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered'];
                    $currentIndex = array_search($activeStatus, $progressStatuses, true);
                    ?>
                    <section class="tracking-progress" aria-labelledby="progress-heading">
                        <div class="tracking-section-heading">
                            <div>
                                <p class="eyebrow">ORDER STATUS</p>
                                <h3 id="progress-heading">Your delivery journey</h3>
                            </div>
                            <span class="tracking-live-indicator"><span></span> Latest saved status</span>
                        </div>
                        <ol class="status-timeline" aria-label="Order progress">
                            <?php foreach ($progressStatuses as $index => $status): ?>
                                <?php $isCurrent = $index === $currentIndex; ?>
                                <li class="<?= $currentIndex !== false && $index <= $currentIndex ? 'is-complete' : '' ?><?= $isCurrent ? ' is-current' : '' ?>"
                                    <?= $isCurrent ? 'aria-current="step"' : '' ?>>
                                    <span class="timeline-dot" aria-hidden="true">
                                        <?php if ($currentIndex !== false && $index < $currentIndex): ?>✓<?php endif; ?>
                                    </span>
                                    <span><?= tracking_h($statusLabels[$status]) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </section>
                <?php endif; ?>

                <div class="tracking-details-grid">
                    <section class="tracking-detail-card tracking-items-card" aria-labelledby="items-heading">
                        <div class="tracking-detail-heading">
                            <span class="tracking-detail-icon" aria-hidden="true">▤</span>
                            <div>
                                <h3 id="items-heading">Order summary</h3>
                                <p><?= tracking_h(count($order['items'])) ?> items in this delivery</p>
                            </div>
                        </div>
                        <div class="table-wrap order-items-wrap">
                            <table>
                                <thead><tr><th>Item</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr></thead>
                                <tbody>
                                <?php foreach ($order['items'] as $item): ?>
                                    <tr>
                                        <td class="item-name"><?= tracking_h($item['item_name']) ?></td>
                                        <td><?= tracking_h($item['quantity']) ?></td>
                                        <td>৳<?= tracking_h(number_format((float) $item['unit_price'], 2)) ?></td>
                                        <td>৳<?= tracking_h(number_format((float) $item['line_total'], 2)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                                <?php if (!empty($order['coupon_code']) || (float) ($order['loyalty_discount'] ?? 0) > 0): ?>
                                    <tfoot>
                                        <tr><th colspan="3">Subtotal</th>
                                            <th>৳<?= tracking_h(number_format((float) $order['subtotal'], 2)) ?></th></tr>
                                        <?php if (!empty($order['coupon_code'])): ?>
                                            <tr><td colspan="3">Coupon <?= tracking_h($order['coupon_code']) ?></td>
                                                <td>−৳<?= tracking_h(number_format((float) $order['coupon_discount'], 2)) ?></td></tr>
                                        <?php endif; ?>
                                        <?php if ((float) ($order['loyalty_discount'] ?? 0) > 0): ?>
                                            <tr><td colspan="3">Loyalty points</td>
                                                <td>−৳<?= tracking_h(number_format((float) $order['loyalty_discount'], 2)) ?></td></tr>
                                        <?php endif; ?>
                                        <tr><th colspan="3">Total due on delivery</th>
                                            <th>৳<?= tracking_h(number_format((float) $order['total'], 2)) ?></th></tr>
                                    </tfoot>
                                <?php else: ?>
                                <tfoot><tr><th colspan="3">Total due on delivery</th>
                                    <th>৳<?= tracking_h(number_format((float) $order['total'], 2)) ?></th></tr></tfoot>
                                <?php endif; ?>
                            </table>
                        </div>
                    </section>

                    <aside class="tracking-detail-card delivery-card">
                        <div class="tracking-detail-heading">
                            <span class="tracking-detail-icon" aria-hidden="true">⌖</span>
                            <div>
                                <h3>Delivery details</h3>
                                <p>We’re bringing it to you</p>
                            </div>
                        </div>
                        <p class="delivery-address"><strong>Delivering to</strong><?= tracking_h($order['delivery_address']) ?></p>
                        <?php if (!empty($order['customer_note'])): ?>
                            <p class="delivery-address delivery-note"><strong>Your note</strong><?= tracking_h($order['customer_note']) ?></p>
                        <?php endif; ?>
                    </aside>
                </div>

                <section class="status-history" aria-labelledby="history-heading">
                    <div class="tracking-detail-heading">
                        <span class="tracking-detail-icon" aria-hidden="true">↻</span>
                        <div>
                            <h3 id="history-heading">Order activity</h3>
                            <p>Updates from the cafe</p>
                        </div>
                    </div>
                    <div class="status-history-list">
                        <?php foreach ($order['history'] as $event): ?>
                            <div class="status-history-event">
                                <span class="history-event-dot" aria-hidden="true"></span>
                                <span><?= tracking_h($statusLabels[$event['status']] ?? $event['status']) ?></span>
                                <time><?= tracking_h(date('M j, Y · g:i a', strtotime($event['changed_at']))) ?></time>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            </section>
        <?php endif; ?>
    </main>
    <footer class="page-footer">Keep your order code and phone number handy for tracking.</footer>
</body>
</html>
