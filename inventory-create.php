<?php
declare(strict_types=1);

require __DIR__ . '/../includes/action.php';
require __DIR__ . '/../includes/inventory.php';

require_post_action();
$name = post_text('name', 100);
$unit = post_text('unit', 30);
$quantity = post_positive_number('quantity', true);
$reorderLevel = post_positive_number('reorder_level', true);

if ($name === null || $unit === null
    || !valid_money_or_quantity($quantity, true)
    || !valid_money_or_quantity($reorderLevel, true)) {
    finish_action('Enter a stock name, unit, and valid non-negative quantities.', 'error');
}

try {
    create_inventory_item(
        database_connection(),
        $name,
        $unit,
        $quantity,
        $reorderLevel
    );
    finish_action('Inventory item added.');
} catch (PDOException $exception) {
    error_log('Coffee O Clock inventory create error: ' . $exception->getMessage());
    finish_action('Could not save the stock item. Check the database and try again.', 'error');
}
