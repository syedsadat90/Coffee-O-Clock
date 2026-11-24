<?php
declare(strict_types=1);

require __DIR__ . '/../includes/action.php';
require __DIR__ . '/../includes/orders.php';

require_post_action();
$id = post_record_id();
$status = post_text('status', 30);
if ($id === null || $status === null) {
    finish_action('Choose a valid order and status.', 'error');
}

try {
    if (!change_customer_order_status(
        database_connection(),
        $id,
        $status,
        (int) $_SESSION['user_id']
    )) {
        finish_action('That order status change is not allowed. Refresh and try again.', 'error');
    }
    finish_action('Order status updated.');
} catch (PDOException $exception) {
    error_log('Coffee O Clock order status error: ' . $exception->getMessage());
    finish_action('Could not update the order. Try again.', 'error');
}
