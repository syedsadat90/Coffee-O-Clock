<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/menu.php';
require __DIR__ . '/includes/inventory.php';
require __DIR__ . '/includes/orders.php';
require __DIR__ . '/includes/reports.php';
require_once __DIR__ . '/includes/coupons.php';
require __DIR__ . '/includes/reservations.php';

require_signed_in();
$csrfToken = csrf_token();
$fullName = (string) $_SESSION['full_name'];
$menuItems = [];
$inventoryItems = [];
$orders = [];
$coupons = [];
$reservations = [];
$recipes = [];
$sales = ['summary' => ['order_count' => 0, 'revenue' => 0, 'discounts' => 0], 'daily' => []];
$pageError = '';

try {
    $database = database_connection();
    $menuItems = list_menu_items($database);
    $inventoryItems = list_inventory_items($database);
    $orders = list_recent_customer_orders($database);
    $coupons = list_coupons($database);
    $reservations = list_reservations($database);
    $sales = sales_report($database);
    $recipes = $database->query(
        'SELECT r.menu_item_id, r.inventory_item_id, r.quantity_required,
                m.name AS menu_name, i.name AS inventory_name, i.unit
         FROM menu_recipes r
         JOIN menu_items m ON m.id = r.menu_item_id
         JOIN inventory_items i ON i.id = r.inventory_item_id
         ORDER BY m.name, i.name'
    )->fetchAll();
} catch (PDOException $exception) {
    error_log('Coffee O Clock dashboard database error: ' . $exception->getMessage());
    $pageError = 'The Update 4 database migration is not ready. Import Update-3/database/update-4.sql using phpMyAdmin, then reload this page.';
}

$summary = inventory_summary($inventoryItems);
$pendingOrders = count(array_filter(
    $orders,
    static fn (array $order): bool => $order['status'] === 'pending'
));
$statusLabels = order_status_labels();
$statusActionLabels = [
    'confirmed' => 'Confirm order',
    'preparing' => 'Mark preparing',
    'out_for_delivery' => 'Mark out for delivery',
    'delivered' => 'Mark delivered',
    'cancelled' => 'Cancel order',
];
$notice = $_SESSION['flash_message'] ?? '';
$noticeType = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

function h(string|int|float $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#063b28">
    <title>Cafe workspace | Coffee O'Clock</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dashboard-page">
    <header class="topbar">
        <a class="topbar-brand" href="dashboard.php">
            <img src="assets/images/coffee-o-clock-logo.jpg" alt="">
            <span>Coffee O'Clock</span>
        </a>
        <div class="topbar-actions">
            <span class="staff-name"><?= h($fullName) ?></span>
            <a class="button button-outline" href="shop.php">Open customer menu</a>
            <form action="actions/logout.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <button class="button button-outline" type="submit">Sign out</button>
            </form>
        </div>
    </header>

    <main class="workspace">
        <section class="page-heading dashboard-hero">
            <div class="dashboard-hero-copy">
                <h1>Your cafe, at a glance.</h1>
                <p>Menu, stock, and every delivery order—all in one place.</p>
            </div>
            <span class="today-date"><span aria-hidden="true">✦</span> <?= h(date('l, F j')) ?></span>
        </section>

        <?php if ($pageError !== ''): ?>
            <div class="notice notice-error" role="alert"><?= h($pageError) ?></div>
        <?php elseif ($notice !== ''): ?>
            <div class="notice notice-<?= h($noticeType) ?>" role="status"><?= h($notice) ?></div>
        <?php endif; ?>
        <section class="summary-grid" aria-label="Inventory summary">
            <article class="summary-card">
                <span class="summary-icon">☕</span>
                <span class="summary-value"><?= h(count($menuItems)) ?></span>
                <span class="summary-label">Menu items</span>
            </article>
            <article class="summary-card">
                <span class="summary-icon">📦</span>
                <span class="summary-value"><?= h($summary['total']) ?></span>
                <span class="summary-label">Stock records</span>
            </article>
            <article class="summary-card summary-warning">
                <span class="summary-icon">!</span>
                <span class="summary-value"><?= h($summary['low_stock']) ?></span>
                <span class="summary-label">Need reordering</span>
            </article>
            <article class="summary-card summary-empty">
                <span class="summary-icon">↻</span>
                <span class="summary-value"><?= h($summary['out_of_stock']) ?></span>
                <span class="summary-label">Out of stock</span>
            </article>
            <article class="summary-card summary-warning">
                <span class="summary-icon">↗</span>
                <span class="summary-value"><?= h($pendingOrders) ?></span>
                <span class="summary-label">New orders</span>
            </article>
        </section>

        <section class="panel sales-report-panel" aria-labelledby="sales-heading">
            <div class="panel-heading">
                <div><p class="eyebrow">Last 7 days</p><h2 id="sales-heading">Sales overview</h2></div>
                <span class="count-pill"><?= h($sales['summary']['order_count']) ?> delivered orders</span>
            </div>
            <div class="sales-report-grid">
                <article><span>Net sales</span><strong>৳<?= h(number_format((float) $sales['summary']['revenue'], 2)) ?></strong></article>
                <article><span>Coupon savings</span><strong>৳<?= h(number_format((float) $sales['summary']['discounts'], 2)) ?></strong></article>
                <article><span>Orders delivered</span><strong><?= h($sales['summary']['order_count']) ?></strong></article>
            </div>
            <?php if ($sales['daily'] !== []): ?>
                <div class="table-wrap">
                    <table><thead><tr><th>Date</th><th>Orders</th><th>Sales</th></tr></thead>
                        <tbody><?php foreach ($sales['daily'] as $day): ?>
                            <tr>
                                <td><?= h(date('M j, Y', strtotime($day['sale_date']))) ?></td>
                                <td><?= h($day['order_count']) ?></td>
                                <td>৳<?= h(number_format((float) $day['revenue'], 2)) ?></td>
                            </tr>
                        <?php endforeach; ?></tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="empty-cell">Delivered sales from the last 7 days will appear here.</p>
            <?php endif; ?>
        </section>

        <div class="content-grid">
            <section class="panel" aria-labelledby="menu-heading">
                <div class="panel-heading">
                    <div><p class="eyebrow">What we serve</p><h2 id="menu-heading">Menu items</h2></div>
                    <span class="count-pill"><?= h(count($menuItems)) ?> listed</span>
                </div>

                <form class="stacked-form" action="actions/menu-create.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <div class="form-row">
                        <div class="field">
                            <label for="menu-name">Item name</label>
                            <input id="menu-name" name="name" maxlength="100" placeholder="e.g. Latte" required>
                        </div>
                        <div class="field">
                            <label for="menu-category">Category</label>
                            <input id="menu-category" name="category" maxlength="60" placeholder="e.g. Coffee" required>
                        </div>
                    </div>
                    <div class="form-row form-row-action">
                        <div class="field">
                            <label for="menu-price">Selling price (৳)</label>
                            <input id="menu-price" name="price" type="number" min="0.01" max="99999999"
                                   step="0.01" placeholder="180.00" required>
                        </div>
                        <button class="button button-primary" type="submit">Add menu item</button>
                    </div>
                </form>

                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Item</th><th>Category</th><th>Price</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php if ($menuItems === []): ?>
                            <tr><td class="empty-cell" colspan="5">No menu items yet. Add the first item above.</td></tr>
                        <?php else: ?>
                            <?php foreach ($menuItems as $item): ?>
                                <tr>
                                    <td class="item-name"><?= h($item['name']) ?></td>
                                    <td><?= h($item['category']) ?></td>
                                    <td>৳<?= h(number_format((float) $item['price'], 2)) ?></td>
                                    <td><span class="status <?= (int) $item['is_available'] ? 'status-ready' : 'status-paused' ?>">
                                        <?= (int) $item['is_available'] ? 'Available' : 'Paused' ?>
                                    </span></td>
                                    <td>
                                        <div class="row-actions">
                                            <form action="actions/menu-availability.php" method="post">
                                                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                                <input type="hidden" name="id" value="<?= h($item['id']) ?>">
                                                <input type="hidden" name="available" value="<?= (int) $item['is_available'] ? '0' : '1' ?>">
                                                <button class="text-button" type="submit">
                                                    <?= (int) $item['is_available'] ? 'Pause' : 'Enable' ?>
                                                </button>
                                            </form>
                                            <form action="actions/menu-delete.php" method="post"
                                                  onsubmit="return confirm('Remove this menu item?')">
                                                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                                <input type="hidden" name="id" value="<?= h($item['id']) ?>">
                                                <button class="text-button text-danger" type="submit">Delete</button>
                                            </form>
                                            <details class="inline-editor">
                                                <summary>Edit item</summary>
                                                <form class="inline-editor-form" action="actions/menu-update.php" method="post">
                                                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                                    <input type="hidden" name="id" value="<?= h($item['id']) ?>">
                                                    <label for="edit-menu-name-<?= h($item['id']) ?>">Name</label>
                                                    <input id="edit-menu-name-<?= h($item['id']) ?>" name="name"
                                                           value="<?= h($item['name']) ?>" maxlength="100" required>
                                                    <label for="edit-menu-category-<?= h($item['id']) ?>">Category</label>
                                                    <input id="edit-menu-category-<?= h($item['id']) ?>" name="category"
                                                           value="<?= h($item['category']) ?>" maxlength="60" required>
                                                    <label for="edit-menu-price-<?= h($item['id']) ?>">Price (৳)</label>
                                                    <input id="edit-menu-price-<?= h($item['id']) ?>" name="price"
                                                           type="number" min="0.01" max="99999999" step="0.01"
                                                           value="<?= h(number_format((float) $item['price'], 2, '.', '')) ?>" required>
                                                    <button class="button button-primary" type="submit">Save item</button>
                                                </form>
                                            </details>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel" aria-labelledby="stock-heading">
                <div class="panel-heading">
                    <div><p class="eyebrow">Ingredients &amp; supplies</p><h2 id="stock-heading">Inventory</h2></div>
                    <span class="count-pill"><?= h($summary['total']) ?> tracked</span>
                </div>

                <form class="stacked-form" action="actions/inventory-create.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <div class="form-row">
                        <div class="field">
                            <label for="stock-name">Stock item</label>
                            <input id="stock-name" name="name" maxlength="100" placeholder="e.g. Coffee beans" required>
                        </div>
                        <div class="field">
                            <label for="stock-unit">Unit</label>
                            <input id="stock-unit" name="unit" maxlength="30" placeholder="kg, L, pieces" required>
                        </div>
                    </div>
                    <div class="form-row form-row-action">
                        <div class="field">
                            <label for="stock-quantity">Opening quantity</label>
                            <input id="stock-quantity" name="quantity" type="number" min="0" max="99999999"
                                   step="0.01" value="0" required>
                        </div>
                        <div class="field">
                            <label for="stock-reorder">Reorder at</label>
                            <input id="stock-reorder" name="reorder_level" type="number" min="0" max="99999999"
                                   step="0.01" value="1" required>
                        </div>
                        <button class="button button-primary" type="submit">Add stock item</button>
                    </div>
                </form>

                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Stock item</th><th>In stock</th><th>Level</th><th>Record purchase</th><th></th></tr></thead>
                        <tbody>
                        <?php if ($inventoryItems === []): ?>
                            <tr><td class="empty-cell" colspan="5">No stock records yet. Add ingredients above.</td></tr>
                        <?php else: ?>
                            <?php foreach ($inventoryItems as $item): ?>
                                <?php
                                $quantity = (float) $item['quantity'];
                                $reorderLevel = (float) $item['reorder_level'];
                                $isOut = $quantity <= 0;
                                $isLow = !$isOut && $quantity <= $reorderLevel;
                                ?>
                                <tr>
                                    <td class="item-name"><?= h($item['name']) ?></td>
                                    <td><?= h(number_format($quantity, 2)) ?> <?= h($item['unit']) ?></td>
                                    <td><span class="status <?= $isOut ? 'status-out' : ($isLow ? 'status-low' : 'status-ready') ?>">
                                        <?= $isOut ? 'Out of stock' : ($isLow ? 'Reorder' : 'In stock') ?>
                                    </span></td>
                                    <td class="row-actions">
                                        <form class="purchase-form" action="actions/stock-purchase.php" method="post">
                                            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                            <input type="hidden" name="id" value="<?= h($item['id']) ?>">
                                            <label class="sr-only" for="purchase-qty-<?= h($item['id']) ?>">Quantity received</label>
                                            <input id="purchase-qty-<?= h($item['id']) ?>" name="quantity"
                                                   type="number" min="0.01" step="0.01" placeholder="Qty" required>
                                            <label class="sr-only" for="purchase-cost-<?= h($item['id']) ?>">Unit cost</label>
                                            <input id="purchase-cost-<?= h($item['id']) ?>" name="unit_cost"
                                                   type="number" min="0" step="0.01" placeholder="Cost" required>
                                            <button class="text-button" type="submit">Add</button>
                                        </form>
                                    </td>
                                    <td>
                                        <div class="row-actions">
                                            <details class="inline-editor">
                                                <summary>Edit stock</summary>
                                                <form class="inline-editor-form" action="actions/inventory-update.php" method="post">
                                                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                                    <input type="hidden" name="id" value="<?= h($item['id']) ?>">
                                                    <label for="edit-stock-name-<?= h($item['id']) ?>">Stock item</label>
                                                    <input id="edit-stock-name-<?= h($item['id']) ?>" name="name"
                                                           value="<?= h($item['name']) ?>" maxlength="100" required>
                                                    <label for="edit-stock-unit-<?= h($item['id']) ?>">Unit</label>
                                                    <input id="edit-stock-unit-<?= h($item['id']) ?>" name="unit"
                                                           value="<?= h($item['unit']) ?>" maxlength="30" required>
                                                    <label for="edit-stock-quantity-<?= h($item['id']) ?>">Current quantity</label>
                                                    <input id="edit-stock-quantity-<?= h($item['id']) ?>" name="quantity"
                                                           type="number" min="0" max="99999999" step="0.01"
                                                           value="<?= h(number_format($quantity, 2, '.', '')) ?>" required>
                                                    <label for="edit-stock-reorder-<?= h($item['id']) ?>">Reorder level</label>
                                                    <input id="edit-stock-reorder-<?= h($item['id']) ?>" name="reorder_level"
                                                           type="number" min="0" max="99999999" step="0.01"
                                                           value="<?= h(number_format($reorderLevel, 2, '.', '')) ?>" required>
                                                    <button class="button button-primary" type="submit">Save stock</button>
                                                </form>
                                            </details>
                                            <form action="actions/inventory-delete.php" method="post"
                                                  onsubmit="return confirm('Remove this stock record and its purchase history?')">
                                                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                                <input type="hidden" name="id" value="<?= h($item['id']) ?>">
                                                <button class="text-button text-danger" type="submit">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <p class="table-note">Edit current quantities to correct the starting stock. Recorded purchases add to the current level.</p>
            </section>

            <section class="panel" aria-labelledby="recipes-heading">
                <div class="panel-heading">
                    <div><p class="eyebrow">Automatic stock</p><h2 id="recipes-heading">Menu recipes</h2></div>
                    <span class="count-pill"><?= h(count($recipes)) ?> ingredients</span>
                </div>
                <p class="table-note">Add ingredient quantities per menu serving. Stock is deducted when an order is placed and returned if it is cancelled. Unconfigured menu items do not deduct stock.</p>
                <?php if ($menuItems !== [] && $inventoryItems !== []): ?>
                    <form class="stacked-form" action="actions/recipe-save.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                        <div class="form-row">
                            <div class="field"><label for="recipe-menu">Menu item</label>
                                <select id="recipe-menu" name="menu_item_id" required>
                                    <?php foreach ($menuItems as $item): ?>
                                        <option value="<?= h($item['id']) ?>"><?= h($item['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field"><label for="recipe-stock">Ingredient</label>
                                <select id="recipe-stock" name="inventory_item_id" required>
                                    <?php foreach ($inventoryItems as $item): ?>
                                        <option value="<?= h($item['id']) ?>"><?= h($item['name']) ?> (<?= h($item['unit']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="field"><label for="recipe-quantity">Quantity per serving</label>
                                <input id="recipe-quantity" name="quantity_required" type="number" min="0.01"
                                       max="9999999" step="0.01" required></div>
                            <button class="button button-primary" type="submit">Save ingredient</button>
                        </div>
                    </form>
                <?php else: ?>
                    <p class="empty-cell">Add menu items and stock ingredients before configuring recipes.</p>
                <?php endif; ?>
                <?php if ($recipes !== []): ?>
                    <div class="table-wrap"><table>
                        <thead><tr><th>Menu item</th><th>Ingredient</th><th>Per serving</th><th></th></tr></thead>
                        <tbody><?php foreach ($recipes as $recipe): ?>
                            <tr>
                                <td><?= h($recipe['menu_name']) ?></td>
                                <td><?= h($recipe['inventory_name']) ?></td>
                                <td><?= h($recipe['quantity_required']) ?> <?= h($recipe['unit']) ?></td>
                                <td><form action="actions/recipe-delete.php" method="post">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                    <input type="hidden" name="menu_item_id" value="<?= h($recipe['menu_item_id']) ?>">
                                    <input type="hidden" name="inventory_item_id" value="<?= h($recipe['inventory_item_id']) ?>">
                                    <button class="text-button text-danger" type="submit">Remove</button>
                                </form></td>
                            </tr>
                        <?php endforeach; ?></tbody>
                    </table></div>
                <?php endif; ?>
            </section>

            <section class="panel" aria-labelledby="coupons-heading">
                <div class="panel-heading">
                    <div><p class="eyebrow">Promotions</p><h2 id="coupons-heading">Coupons</h2></div>
                    <span class="count-pill"><?= h(count($coupons)) ?> codes</span>
                </div>
                <form class="stacked-form" action="actions/coupon-create.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <div class="form-row">
                        <div class="field"><label for="coupon-new-code">Code</label>
                            <input id="coupon-new-code" name="code" maxlength="30" placeholder="SUMMER15" required></div>
                        <div class="field"><label for="coupon-type">Discount type</label>
                            <select id="coupon-type" name="discount_type">
                                <option value="percent">Percent</option><option value="fixed">Fixed amount (৳)</option>
                            </select></div>
                    </div>
                    <div class="form-row">
                        <div class="field"><label for="coupon-value">Discount value</label>
                            <input id="coupon-value" name="discount_value" type="number" min="0.01" step="0.01" required></div>
                        <div class="field"><label for="coupon-minimum">Minimum order (৳)</label>
                            <input id="coupon-minimum" name="minimum_order" type="number" min="0" step="0.01" value="0" required></div>
                    </div>
                    <div class="form-row">
                        <div class="field"><label for="coupon-limit">Usage limit <span class="optional">(optional)</span></label>
                            <input id="coupon-limit" name="usage_limit" type="number" min="1" step="1"></div>
                        <button class="button button-primary" type="submit">Create coupon</button>
                    </div>
                </form>
                <?php if ($coupons !== []): ?>
                    <div class="table-wrap"><table>
                        <thead><tr><th>Code</th><th>Discount</th><th>Uses</th><th>Status</th><th></th></tr></thead>
                        <tbody><?php foreach ($coupons as $coupon): ?>
                            <tr>
                                <td class="item-name"><?= h($coupon['code']) ?></td>
                                <td><?= h($coupon['discount_type'] === 'percent'
                                    ? number_format((float) $coupon['discount_value'], 2) . '%'
                                    : '৳' . number_format((float) $coupon['discount_value'], 2)) ?></td>
                                <td><?= h($coupon['usage_count']) ?> / <?= h($coupon['usage_limit'] ?? 'Unlimited') ?></td>
                                <td><?= (int) $coupon['is_active'] ? 'Active' : 'Paused' ?></td>
                                <td><form action="actions/coupon-status.php" method="post">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                    <input type="hidden" name="id" value="<?= h($coupon['id']) ?>">
                                    <input type="hidden" name="active" value="<?= (int) $coupon['is_active'] ? '0' : '1' ?>">
                                    <button class="text-button" type="submit"><?= (int) $coupon['is_active'] ? 'Pause' : 'Enable' ?></button>
                                </form></td>
                            </tr>
                        <?php endforeach; ?></tbody>
                    </table></div>
                <?php endif; ?>
                <p class="table-note">Demo offer: WELCOME10 gives 10% off orders of ৳200 or more.</p>
            </section>

            <section class="panel" aria-labelledby="reservations-heading">
                <div class="panel-heading">
                    <div><p class="eyebrow">In-cafe visits</p><h2 id="reservations-heading">Reservations</h2></div>
                    <a class="button button-outline" href="reserve.php">Booking page</a>
                </div>
                <?php if ($reservations === []): ?>
                    <p class="empty-cell">Upcoming reservations will appear here.</p>
                <?php else: ?>
                    <div class="table-wrap"><table>
                        <thead><tr><th>Guest</th><th>Party</th><th>Date &amp; time</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody><?php foreach ($reservations as $reservation): ?>
                            <tr>
                                <td><?= h($reservation['customer_name']) ?><br><span class="subtle-text"><?= h($reservation['customer_phone']) ?></span></td>
                                <td><?= h($reservation['guest_count']) ?></td>
                                <td><?= h(date('M j, Y', strtotime($reservation['reservation_date']))) ?><br><?= h(date('g:i a', strtotime($reservation['reservation_time']))) ?></td>
                                <td><?= h(ucfirst((string) $reservation['status'])) ?></td>
                                <td><?php if (in_array($reservation['status'], ['requested', 'confirmed'], true)): ?>
                                    <form action="actions/reservation-status.php" method="post" class="status-update-form">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                        <input type="hidden" name="id" value="<?= h($reservation['id']) ?>">
                                        <?php if ($reservation['status'] === 'requested'): ?>
                                            <button class="text-button" name="status" value="confirmed" type="submit">Confirm</button>
                                        <?php else: ?>
                                            <button class="text-button" name="status" value="completed" type="submit">Complete</button>
                                        <?php endif; ?>
                                        <button class="text-button text-danger" name="status" value="cancelled" type="submit">Cancel</button>
                                    </form>
                                <?php else: ?>Done<?php endif; ?></td>
                            </tr>
                        <?php endforeach; ?></tbody>
                    </table></div>
                <?php endif; ?>
            </section>

            <section class="panel orders-panel" aria-labelledby="orders-heading">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Customer deliveries</p>
                        <h2 id="orders-heading">Recent orders</h2>
                    </div>
                    <span class="count-pill">Latest <?= h(count($orders)) ?></span>
                </div>
                <p class="table-note order-workflow-note">
                    Move each order one step at a time. Use the status action beside the order; customers can see the change after refreshing tracking.
                </p>
                <?php if ($orders === []): ?>
                    <p class="empty-cell">No customer orders yet. Orders placed from <a href="shop.php">the online menu</a> will appear here.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="orders-table">
                            <thead><tr><th>Order</th><th>Customer</th><th>Items</th><th>Total</th><th>Status</th><th>Update status</th></tr></thead>
                            <tbody>
                            <?php foreach ($orders as $order): ?>
                                <?php $nextStatuses = next_order_statuses((string) $order['status']); ?>
                                <tr>
                                    <td>
                                        <span class="item-name"><?= h($order['order_code']) ?></span><br>
                                        <span class="subtle-text"><?= h(date('M j, g:i a', strtotime($order['created_at']))) ?></span>
                                    </td>
                                    <td><?= h($order['customer_name']) ?><br><span class="subtle-text"><?= h($order['customer_phone']) ?></span></td>
                                    <td>
                                        <?php foreach ($order['items'] as $item): ?>
                                            <span class="order-item-line"><?= h($item['quantity']) ?> × <?= h($item['item_name']) ?></span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td>৳<?= h(number_format((float) $order['total'], 2)) ?></td>
                                    <td><span class="status status-order-<?= h($order['status']) ?>"><?= h($statusLabels[$order['status']] ?? $order['status']) ?></span></td>
                                    <td>
                                        <?php if ($nextStatuses !== []): ?>
                                            <form action="actions/order-status.php" method="post" class="status-update-form">
                                                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                                <input type="hidden" name="id" value="<?= h($order['id']) ?>">
                                                <?php foreach ($nextStatuses as $nextStatus): ?>
                                                    <button class="text-button status-action-button<?= $nextStatus === 'cancelled' ? ' status-action-cancel' : '' ?>"
                                                            type="submit" name="status" value="<?= h($nextStatus) ?>">
                                                        <?= h($statusActionLabels[$nextStatus] ?? $statusLabels[$nextStatus]) ?>
                                                    </button>
                                                <?php endforeach; ?>
                                            </form>
                                        <?php else: ?>
                                            <span class="subtle-text">Complete</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="table-note">Customers can check these status updates with their order code and phone number.</p>
                <?php endif; ?>
            </section>
        </div>
    </main>
</body>
</html>
