<?php
declare(strict_types=1);

require __DIR__ . '/../includes/action.php';
require __DIR__ . '/../includes/coupons.php';

require_post_action();
$id = post_record_id();
$active = $_POST['active'] ?? null;
if ($id === null || !in_array($active, ['0', '1'], true)) {
    finish_action('Choose a valid coupon.', 'error');
}
try {
    if (!set_coupon_active(database_connection(), $id, $active === '1')) {
        finish_action('Coupon not found or unchanged.', 'error');
    }
    finish_action('Coupon status updated.');
} catch (PDOException $exception) {
    error_log('Coffee O Clock coupon status error: ' . $exception->getMessage());
    finish_action('Could not update the coupon. Try again.', 'error');
}
