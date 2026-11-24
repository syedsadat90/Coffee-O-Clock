<?php
declare(strict_types=1);

require __DIR__ . '/../includes/action.php';
require __DIR__ . '/../includes/form-validation.php';

require_post_action();
$menuId = post_record_id('menu_item_id');
$inventoryId = post_record_id('inventory_item_id');
$quantity = $_POST['quantity_required'] ?? null;
if ($menuId === null || $inventoryId === null || !is_string($quantity)
    || !preg_match('/^(?:0|[1-9][0-9]{0,6})(?:\.[0-9]{1,2})?$/', $quantity)
    || (float) $quantity <= 0 || (float) $quantity > 9999999) {
    finish_action('Choose a menu item, stock item, and positive quantity.', 'error');
}
try {
    $statement = database_connection()->prepare(
        'INSERT INTO menu_recipes (menu_item_id, inventory_item_id, quantity_required)
         VALUES (:menu_id, :inventory_id, :quantity)
         ON DUPLICATE KEY UPDATE quantity_required = VALUES(quantity_required)'
    );
    $statement->execute([
        'menu_id' => $menuId,
        'inventory_id' => $inventoryId,
        'quantity' => $quantity,
    ]);
    finish_action('Recipe ingredient saved.');
} catch (PDOException $exception) {
    error_log('Coffee O Clock recipe save error: ' . $exception->getMessage());
    finish_action('Could not save this recipe. Check the selected menu and stock items.', 'error');
}
