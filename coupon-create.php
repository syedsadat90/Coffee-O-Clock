<?php
declare(strict_types=1);

require __DIR__ . '/../includes/action.php';
require __DIR__ . '/../includes/coupons.php';
require __DIR__ . '/../includes/form-validation.php';

require_post_action();
$code = post_text('code', 30);
$type = post_text('discount_type', 10);
$value = post_positive_number('discount_value');
$minimum = post_positive_number('minimum_order', true);
$limitInput = $_POST['usage_limit'] ?? '';
$limit = null;
if (is_string($limitInput) && trim($limitInput) !== '') {
    $limit = filter_var($limitInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
}
if ($code === null || preg_match('/^[A-Za-z0-9_-]{3,30}$/', $code) !== 1
    || !in_array($type, ['percent', 'fixed'], true) || $value === null
    || $minimum === null || ($limitInput !== '' && $limit === false)
    || ($type === 'percent' && (float) $value > 100)) {
    finish_action('Enter a valid code, discount, minimum order, and optional usage limit.', 'error');
}
try {
    create_coupon(database_connection(), $code, $type, $value, $minimum, $limit);
    finish_action('Coupon created.');
} catch (PDOException $exception) {
    if ($exception->getCode() === '23000') {
        finish_action('That coupon code already exists.', 'error');
    }
    error_log('Coffee O Clock coupon creation error: ' . $exception->getMessage());
    finish_action('Could not create the coupon. Check the database migration and try again.', 'error');
}
