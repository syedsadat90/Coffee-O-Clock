<?php
declare(strict_types=1);

require __DIR__ . '/../includes/action.php';

require_post_action();
$menuId = post_record_id('menu_item_id');
$inventoryId = post_record_id('inventory_item_id');
if ($menuId === null || $inventoryId === null) {
    finish_action('Choose a valid recipe ingredient.', 'error');
}
try {
    $statement = database_connection()->prepare(
        'DELETE FROM menu_recipes
         WHERE menu_item_id = :menu_id AND inventory_item_id = :inventory_id'
    );
    $statement->execute(['menu_id' => $menuId, 'inventory_id' => $inventoryId]);
    finish_action($statement->rowCount() > 0 ? 'Recipe ingredient removed.' : 'Recipe ingredient not found.',
        $statement->rowCount() > 0 ? 'success' : 'error');
} catch (PDOException $exception) {
    error_log('Coffee O Clock recipe delete error: ' . $exception->getMessage());
    finish_action('Could not remove the recipe ingredient. Try again.', 'error');
}
