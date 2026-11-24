<?php
declare(strict_types=1);

require __DIR__ . '/../includes/action.php';
require __DIR__ . '/../includes/inventory.php';

require_post_action();
$id = post_record_id();
$name = post_text('name', 100);
$unit = post_text('unit', 30);
$quantity = post_positive_number('quantity', true);
$reorderLevel = post_positive_number('reorder_level', true);

if ($id === null || $name === null || $unit === null
    || !valid_money_or_quantity($quantity, true)
    || !valid_money_or_quantity($reorderLevel, true)) {
    finish_action('Enter a valid stock name, unit, and non-negative quantities.', 'error');
}

try {
    if (!update_inventory_item(
        database_connection(),
        $id,
        $name,
        $unit,
        $quantity,
        $reorderLevel
    )) {
        finish_action('That stock item was not found.', 'error');
    }
    finish_action('Stock details and current level updated.');
} catch (PDOException $exception) {
    error_log('Coffee O Clock inventory update error: ' . $exception->getMessage());
    finish_action('Could not update the stock item. Try again.', 'error');
}
