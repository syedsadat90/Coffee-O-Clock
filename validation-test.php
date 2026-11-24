<?php
declare(strict_types=1);

require __DIR__ . '/../includes/validation.php';
require __DIR__ . '/../includes/form-validation.php';
require __DIR__ . '/../includes/inventory.php';
require __DIR__ . '/../includes/orders.php';
require_once __DIR__ . '/../includes/coupons.php';

$checks = [
    'valid email and password' => valid_login_input('staff@example.com', 'coffee123'),
    'email is normalized' => normalize_email('  STAFF@EXAMPLE.COM ') === 'staff@example.com',
    'invalid email rejected' => !valid_login_input('not-an-email', 'coffee123'),
    'empty password rejected' => !valid_login_input('staff@example.com', ''),
    'non-string input rejected' => !valid_login_input([], null),
    'menu text is trimmed' => (static function (): bool {
        $_POST = ['name' => ' Latte '];
        return post_text('name', 20) === 'Latte';
    })(),
    'menu text length is limited' => (static function (): bool {
        $_POST = ['name' => str_repeat('a', 21)];
        return post_text('name', 20) === null;
    })(),
    'positive decimal is normalized' => (static function (): bool {
        $_POST = ['price' => '12.5'];
        return post_positive_number('price') === '12.50';
    })(),
    'zero quantity is accepted when requested' => (static function (): bool {
        $_POST = ['quantity' => '0'];
        return post_positive_number('quantity', true) === '0.00';
    })(),
    'negative quantity is rejected' => (static function (): bool {
        $_POST = ['quantity' => '-1'];
        return post_positive_number('quantity', true) === null;
    })(),
    'amount above decimal column limit is rejected' => (static function (): bool {
        $_POST = ['price' => '100000000'];
        return post_positive_number('price') === null;
    })(),
    'record ID must be a positive integer' => (static function (): bool {
        $_POST = ['id' => '2'];
        $valid = post_record_id() === 2;
        $_POST = ['id' => '2.5'];
        return $valid && post_record_id() === null;
    })(),
    'inventory summary separates low and out-of-stock items' => inventory_summary([
        ['quantity' => '0', 'reorder_level' => '2'],
        ['quantity' => '1', 'reorder_level' => '2'],
        ['quantity' => '5', 'reorder_level' => '2'],
    ]) === ['total' => 3, 'low_stock' => 1, 'out_of_stock' => 1],
    'customer phone is normalized and validated' =>
        normalize_customer_phone('+880 1712-345-678') === '+8801712345678'
        && valid_customer_phone('+880 1712-345-678'),
    'Bangladesh local and international phone formats match' =>
        normalize_customer_phone('01712-345678') === '+8801712345678'
        && normalize_customer_phone('+880 1712-345-678') === '+8801712345678'
        && normalize_customer_phone('8801712345678') === '+8801712345678',
    'invalid customer phone is rejected' => !valid_customer_phone('phone-number'),
    'percentage coupon calculates a bounded discount' => calculate_coupon_discount([
        'is_active' => 1,
        'usage_limit' => null,
        'usage_count' => 0,
        'minimum_order' => '200.00',
        'discount_type' => 'percent',
        'discount_value' => '10.00',
        'starts_at' => null,
        'ends_at' => null,
    ], 25000) === 2500,
    'coupon rejects unmet minimum, exhausted use, and expired codes' =>
        calculate_coupon_discount([
            'is_active' => 1, 'usage_limit' => null, 'usage_count' => 0,
            'minimum_order' => '200.00', 'discount_type' => 'percent',
            'discount_value' => '10.00', 'starts_at' => null, 'ends_at' => null,
        ], 19999) === null
        && calculate_coupon_discount([
            'is_active' => 1, 'usage_limit' => 1, 'usage_count' => 1,
            'minimum_order' => '0.00', 'discount_type' => 'fixed',
            'discount_value' => '50.00', 'starts_at' => null, 'ends_at' => null,
        ], 10000) === null
        && calculate_coupon_discount([
            'is_active' => 1, 'usage_limit' => null, 'usage_count' => 0,
            'minimum_order' => '0.00', 'discount_type' => 'fixed',
            'discount_value' => '50.00', 'starts_at' => null,
            'ends_at' => '2020-01-01 00:00:00',
        ], 10000, strtotime('2026-01-01 00:00:00')) === null,
    'fixed coupon never discounts more than the subtotal' => calculate_coupon_discount([
        'is_active' => 1,
        'usage_limit' => null,
        'usage_count' => 0,
        'minimum_order' => '0.00',
        'discount_type' => 'fixed',
        'discount_value' => '500.00',
        'starts_at' => null,
        'ends_at' => null,
    ], 1000) === 1000,
    'order quantities ignore zero and retain selected items' =>
        valid_order_quantities(['2' => '0', '5' => '3']) === [5 => 3],
    'empty or excessive order quantities are rejected' =>
        valid_order_quantities(['2' => '0']) === null
        && valid_order_quantities(['2' => '21']) === null,
    'order statuses follow delivery progression' =>
        next_order_statuses('pending') === ['confirmed', 'cancelled']
        && next_order_statuses('confirmed') === ['preparing', 'cancelled']
        && next_order_statuses('preparing') === ['out_for_delivery', 'cancelled']
        && next_order_statuses('out_for_delivery') === ['delivered']
        && next_order_statuses('delivered') === [],
    'delivery estimate updates with order status and formats midnight clearly' => (static function (): bool {
        $morning = strtotime('2026-01-01 10:00:00');
        $pending = customer_delivery_estimate(['status' => 'pending'], $morning);
        $confirmed = customer_delivery_estimate(['status' => 'confirmed'], $morning);
        $preparing = customer_delivery_estimate(
            ['status' => 'preparing'],
            strtotime('2026-01-01 23:40:00')
        );
        $dispatch = customer_delivery_estimate(['status' => 'out_for_delivery'], $morning);
        $anchored = customer_delivery_estimate([
            'status' => 'pending',
            'created_at' => '2026-01-01 23:16:00',
            'history' => [
                ['status' => 'pending', 'changed_at' => '2026-01-01 23:16:00'],
            ],
        ], strtotime('2026-01-01 18:00:00'));

        return is_array($pending)
            && $pending['window'] === '10:30 am – 10:45 am Jan 1'
            && str_contains($pending['note'], '30–45 minutes based on the current order status')
            && is_array($confirmed)
            && $confirmed['window'] === '10:25 am – 10:35 am Jan 1'
            && is_array($preparing)
            && $preparing['window'] === '11:55 pm Jan 1 – 12:10 am Jan 2'
            && is_array($dispatch)
            && $dispatch['title'] === 'Approximate arrival'
            && $dispatch['window'] === '10:10 am – 10:20 am Jan 1'
            && str_contains($dispatch['note'], '10–20 minutes based on the current order status')
            && is_array($anchored)
            && $anchored['window'] === '11:46 pm Jan 1 – 12:01 am Jan 2';
    })(),
    'delivered orders show actual delivery time' => customer_delivery_estimate([
        'status' => 'delivered',
        'history' => [
            ['status' => 'delivered', 'changed_at' => '2026-01-01 10:42:00'],
        ],
    ])['title'] === 'Delivered'
        && customer_delivery_estimate([
            'status' => 'delivered',
            'history' => [
                ['status' => 'delivered', 'changed_at' => '2026-01-01 10:42:00'],
            ],
        ])['window'] === 'Jan 1, 2026 · 10:42 am',
    'cancelled orders have no delivery estimate' =>
        customer_delivery_estimate(['status' => 'cancelled']) === null,
];

$failed = false;
foreach ($checks as $name => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . " - {$name}\n";
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
