<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/menu.php';
require __DIR__ . '/includes/customers.php';

$databaseError = '';
$menuItems = [];
$customerAccount = null;
$notice = $_SESSION['shop_notice'] ?? '';
$noticeType = $_SESSION['shop_notice_type'] ?? 'success';
unset($_SESSION['shop_notice'], $_SESSION['shop_notice_type']);

try {
    $database = database_connection();
    $menuItems = array_values(array_filter(
        list_menu_items($database),
        static fn (array $item): bool => (int) $item['is_available'] === 1
    ));
    if (!empty($_SESSION['customer_id'])) {
        $accountQuery = $database->prepare(
            'SELECT id, full_name, phone, points FROM customer_accounts WHERE id = :id LIMIT 1'
        );
        $accountQuery->execute(['id' => (int) $_SESSION['customer_id']]);
        $customerAccount = $accountQuery->fetch() ?: null;
        if ($customerAccount === null) {
            unset($_SESSION['customer_id'], $_SESSION['customer_name'], $_SESSION['customer_phone']);
        }
    }
} catch (PDOException $exception) {
    error_log('Coffee O Clock customer menu database error: ' . $exception->getMessage());
    $databaseError = 'The menu is temporarily unavailable. Please try again later.';
}

$csrfToken = csrf_token();
$menuCategories = [];
foreach ($menuItems as $menuItem) {
    $category = trim((string) $menuItem['category']);
    $categoryKey = strtolower($category);
    if ($category !== '' && !isset($menuCategories[$categoryKey])) {
        $menuCategories[$categoryKey] = $category;
    }
}
$menuCategories = array_values($menuCategories);

function shop_h(string|int|float $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function shop_menu_image(string $name, string $category): string
{
    $itemImages = [
        'americano' => 'americano.jpg',
        'butter croissant' => 'butter-croissant.jpg',
        'cappuccino' => 'cappuccino-photo.jpg',
        'chicken sandwich' => 'chicken-sandwich.jpg',
        'chicken wrap' => 'chicken-wrap.jpg',
        'chocolate brownie' => 'chocolate-brownie.jpg',
        'chocolate cake slice' => 'chocolate-cake.jpg',
        'cold coffee' => 'cold-coffee.jpg',
        'espresso' => 'espresso.jpg',
        'garlic bread' => 'garlic-bread.jpg',
        'green tea' => 'green-tea.jpg',
        'iced latte' => 'iced-latte.jpg',
        'latte' => 'latte.jpg',
        'lemon tea' => 'lemon-tea.jpg',
        'milk tea' => 'milk-tea.jpg',
        'mocha' => 'mocha.jpg',
        'strawberry milkshake' => 'strawberry-milkshake.jpg',
    ];
    $normalizedName = strtolower(trim($name));
    if (isset($itemImages[$normalizedName])) {
        return 'assets/images/menu/' . $itemImages[$normalizedName];
    }

    $category = strtolower(trim($category));
    $image = match (true) {
        str_contains($category, 'coffee') || str_contains($category, 'hot') => 'coffee',
        str_contains($category, 'cold') || str_contains($category, 'drink') => 'cold-drinks',
        str_contains($category, 'tea') => 'tea',
        str_contains($category, 'bakery') || str_contains($category, 'cake')
            || str_contains($category, 'pastry') => 'bakery',
        str_contains($category, 'snack') || str_contains($category, 'sandwich') => 'snacks',
        default => 'menu-default',
    };

    return 'assets/images/menu/' . $image . '.svg';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#063b28">
    <title>Order online | Coffee O'Clock</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dashboard-page customer-page">
    <header class="topbar">
        <a class="topbar-brand" href="shop.php">
            <img src="assets/images/coffee-o-clock-logo.jpg" alt="">
            <span>Coffee O'Clock</span>
        </a>
        <nav class="topbar-actions" aria-label="Customer navigation">
            <a class="button button-outline" href="reserve.php">Reserve a table</a>
            <?php if ($customerAccount !== null): ?>
                <a class="button button-outline" href="customer-account.php">
                    <?= shop_h($customerAccount['points']) ?> points
                </a>
                <form action="actions/customer-logout.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= shop_h(csrf_token()) ?>">
                    <button class="button button-outline" type="submit">Sign out</button>
                </form>
            <?php else: ?>
                <a class="button button-outline" href="customer-account.php">Join rewards</a>
            <?php endif; ?>
            <a class="button button-primary customer-nav-primary" href="track-order.php">
                Track your order <span aria-hidden="true">→</span>
            </a>
            <a class="button button-outline" href="index.php">Staff sign in</a>
        </nav>
    </header>

    <main class="workspace">
        <section class="shop-hero shop-hero-home">
            <div class="shop-hero-copy">
                <p class="eyebrow"><span class="hero-eyebrow-dot" aria-hidden="true"></span>Your neighbourhood coffee stop</p>
                <h1>Good coffee.<br><span>Good moments.</span></h1>
                <p>Find your new favourite, order it fresh, and enjoy a little cafe moment wherever you are.</p>
                <div class="shop-hero-actions">
                    <a class="button button-primary shop-hero-button" href="#menu">
                        Explore the menu <span aria-hidden="true">→</span>
                    </a>
                    <span class="shop-hero-promise">Freshly made <span aria-hidden="true">·</span> Delivered with care</span>
                </div>
            </div>
            <div class="shop-hero-visual">
                <img src="assets/images/menu/latte.jpg" alt="Freshly made coffee with latte art">
                <span class="shop-hero-stamp">BREWED WITH CARE</span>
                <div class="shop-hero-photo-caption">
                    <span>YOUR DAILY LITTLE RITUAL</span>
                    <strong>A good day starts here.</strong>
                </div>
            </div>
            <div class="shop-hero-ribbon" aria-label="Order highlights">
                <span><b>01</b> Pick your favourite</span>
                <span><b>02</b> We make it fresh</span>
                <span><b>03</b> Pay when it arrives</span>
            </div>
        </section>
        <?php if ($databaseError !== ''): ?>
            <div class="notice notice-error" role="alert"><?= shop_h($databaseError) ?></div>
        <?php elseif ($notice !== ''): ?>
            <div class="notice notice-<?= shop_h($noticeType) ?>" role="status"><?= shop_h($notice) ?></div>
        <?php endif; ?>

        <?php if ($menuItems === [] && $databaseError === ''): ?>
            <section class="panel empty-shop">
                <h2>Our online menu is getting ready</h2>
                <p>Please check back soon or contact the cafe directly.</p>
            </section>
        <?php else: ?>
            <form action="actions/place-order.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= shop_h($csrfToken) ?>">
                <section class="shop-section" id="menu" aria-labelledby="menu-heading">
                    <div class="panel-heading">
                        <div>
                            <p class="eyebrow">Made for your day</p>
                            <h2 id="menu-heading">Explore the menu</h2>
                        </div>
                        <span class="count-pill" id="menu-result-count"><?= shop_h(count($menuItems)) ?> available</span>
                    </div>
                    <div class="menu-discovery">
                        <label class="menu-search">
                            <span aria-hidden="true">⌕</span>
                            <input id="menu-search" type="search" placeholder="Search coffee, tea, snacks..."
                                   aria-label="Search menu items">
                        </label>
                        <div class="menu-category-filters" role="group" aria-label="Filter menu by category">
                            <button class="menu-filter is-active" type="button" data-menu-filter="all"
                                    aria-pressed="true">Everything</button>
                            <?php foreach ($menuCategories as $category): ?>
                                <button class="menu-filter" type="button"
                                        data-menu-filter="<?= shop_h($category) ?>" aria-pressed="false">
                                    <?= shop_h($category) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="shop-menu-grid">
                        <?php foreach ($menuItems as $item): ?>
                            <article class="shop-item"
                                     data-menu-card
                                     data-menu-name="<?= shop_h($item['name']) ?>"
                                     data-menu-category="<?= shop_h($item['category']) ?>">
                                <img class="shop-item-image"
                                     src="<?= shop_h(shop_menu_image(
                                         (string) $item['name'],
                                         (string) $item['category']
                                     )) ?>"
                                     alt="<?= shop_h((string) $item['name']) ?>"
                                     loading="lazy" decoding="async">
                                <p class="shop-item-category"><?= shop_h($item['category']) ?></p>
                                <h3><?= shop_h($item['name']) ?></h3>
                                <div class="shop-item-order">
                                    <strong data-unit-price="<?= shop_h(number_format((float) $item['price'], 2, '.', '')) ?>">
                                        ৳<?= shop_h(number_format((float) $item['price'], 2)) ?>
                                    </strong>
                                    <label for="quantity-<?= shop_h($item['id']) ?>">Quantity</label>
                                    <input id="quantity-<?= shop_h($item['id']) ?>"
                                           name="quantities[<?= shop_h($item['id']) ?>]"
                                           type="number" min="0" max="20" step="1" value="0">
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <p class="menu-empty-filter" id="menu-empty-filter" hidden>
                        No menu items match your search. Try another name or category.
                    </p>
                    <aside class="cart-summary" aria-label="Current order summary">
                        <div class="cart-summary-count">
                            <span class="cart-summary-icon" aria-hidden="true">🛍</span>
                            <div>
                                <strong id="cart-item-count">0 items selected</strong>
                                <span id="cart-subtotal-note">Choose items to start your order</span>
                            </div>
                        </div>
                        <div class="cart-summary-total">
                            <span>Estimated total</span>
                            <strong id="cart-total">৳0.00</strong>
                        </div>
                        <div class="cart-summary-actions">
                            <button class="cart-clear-button" id="clear-order" type="button" disabled>
                                Clear
                            </button>
                            <a class="button button-primary cart-review-button" href="#delivery-heading">
                                Continue to details <span aria-hidden="true">→</span>
                            </a>
                        </div>
                    </aside>
                    <p class="cart-estimate-note">Final total is confirmed when your order is placed.</p>
                </section>

                <section class="panel checkout-panel" aria-labelledby="delivery-heading">
                    <div class="panel-heading">
                        <div>
                            <p class="eyebrow">Step 2 · Almost there</p>
                            <h2 id="delivery-heading">Delivery details</h2>
                            <p class="checkout-heading-note">Tell us where to bring your freshly made order.</p>
                        </div>
                        <span class="count-pill">Cash on delivery</span>
                    </div>
                    <div class="checkout-progress" aria-label="Checkout progress">
                        <span class="checkout-progress-step is-complete">
                            <span class="checkout-progress-number" aria-hidden="true">1</span>
                            Choose your items
                        </span>
                        <span class="checkout-progress-line" aria-hidden="true"></span>
                        <span class="checkout-progress-step is-current" aria-current="step">
                            <span class="checkout-progress-number" aria-hidden="true">2</span>
                            Delivery details
                        </span>
                    </div>
                    <div class="checkout-grid">
                        <div class="field">
                            <label for="customer-name">Your name</label>
                            <input id="customer-name" name="customer_name" maxlength="100"
                                   value="<?= shop_h((string) ($customerAccount['full_name'] ?? '')) ?>"
                                   autocomplete="name" placeholder="Name for your order" required>
                        </div>
                        <div class="field">
                            <label for="customer-phone">Phone number</label>
                            <input id="customer-phone" name="phone" type="tel" maxlength="20"
                                   value="<?= shop_h((string) ($customerAccount['phone'] ?? '')) ?>"
                                   autocomplete="tel" placeholder="01XXXXXXXXX" aria-describedby="phone-help" required>
                            <small class="field-help" id="phone-help">We’ll use this for delivery updates and private order tracking.</small>
                        </div>
                        <div class="field checkout-wide">
                            <label for="delivery-address">Full delivery address</label>
                            <textarea id="delivery-address" name="address" maxlength="500"
                                      autocomplete="street-address" placeholder="House or building, road, area, city"
                                      aria-describedby="address-help" rows="3" required></textarea>
                            <small class="field-help" id="address-help">Add a nearby landmark if it helps your rider find you.</small>
                        </div>
                        <div class="field checkout-wide">
                            <label for="customer-note">Delivery note <span class="optional">(optional)</span></label>
                            <textarea id="customer-note" name="note" maxlength="500"
                                      placeholder="Gate code, preferred entrance, or other helpful instructions"
                                      rows="2"></textarea>
                        </div>
                        <div class="field">
                            <label for="coupon-code">Coupon code <span class="optional">(optional)</span></label>
                            <input id="coupon-code" name="coupon_code" maxlength="30"
                                   placeholder="Try WELCOME10" autocomplete="off">
                        </div>
                        <div class="field">
                            <label for="redeem-points">Redeem loyalty points</label>
                            <?php if ($customerAccount !== null): ?>
                                <input id="redeem-points" name="redeem_points" type="number" min="0"
                                       max="<?= shop_h($customerAccount['points']) ?>" step="100" value="0">
                                <small class="field-help">Available: <?= shop_h($customerAccount['points']) ?> points. 100 points = ৳10; redeem up to 20% of your discounted subtotal.</small>
                            <?php else: ?>
                                <a class="field-help" href="customer-account.php">Sign in to earn and redeem reward points.</a>
                                <input id="redeem-points" name="redeem_points" type="hidden" value="0">
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="checkout-submit">
                        <div class="checkout-submit-copy">
                            <p class="checkout-submit-title">Ready for a little cafe moment?</p>
                            <p>Pay when your order arrives. We’ll show your order code and tracking details after you place it.</p>
                            <div class="checkout-assurances" aria-label="Checkout information">
                                <span><span aria-hidden="true">✓</span> No online payment</span>
                                <span><span aria-hidden="true">✓</span> Private order tracking</span>
                            </div>
                        </div>
                        <button class="button button-primary" type="submit">
                            Place my order <span aria-hidden="true">→</span>
                        </button>
                    </div>
                </section>
            </form>
        <?php endif; ?>
    </main>
    <footer class="page-footer">Coffee O'Clock · Freshly made, delivered with care</footer>
    <?php if ($menuItems !== []): ?>
        <script src="assets/js/shop.js" defer></script>
    <?php endif; ?>
</body>
</html>
