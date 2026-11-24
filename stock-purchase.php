<?php
declare(strict_types=1);

require __DIR__ . '/../includes/action.php';
require __DIR__ . '/../includes/inventory.php';

require_post_action();
$id = post_record_id();
$quantity = post_positive_number('quantity');
$unitCost = post_positive_number('unit_cost', true);

if ($id === null || !valid_money_or_quantity($quantity)
    || !valid_money_or_quantity($unitCost, true)) {
    finish_action('Enter a valid purchase quantity and non-negative unit cost.', 'error');
}

try {
    if (!record_stock_purchase(database_connection(), $id, $quantity, $unitCost)) {
        finish_action('That stock item was not found.', 'error');
    }
    finish_action('Purchase recorded and stock quantity updated.');
} catch (PDOException $exception) {
    error_log('Coffee O Clock purchase record error: ' . $exception->getMessage());
    finish_action('Could not record this purchase. Check the database and try again.', 'error');
}
