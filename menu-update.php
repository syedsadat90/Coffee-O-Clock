<?php
declare(strict_types=1);

require __DIR__ . '/../includes/action.php';
require __DIR__ . '/../includes/menu.php';

require_post_action();
$id = post_record_id();
$name = post_text('name', 100);
$category = post_text('category', 60);
$price = post_positive_number('price');

if ($id === null || $name === null || $category === null
    || !valid_money_or_quantity($price)) {
    finish_action('Enter a valid menu item name, category, and price.', 'error');
}

try {
    if (!update_menu_item(database_connection(), $id, $name, $category, $price)) {
        finish_action('That menu item was not found.', 'error');
    }
    finish_action('Menu item details updated.');
} catch (PDOException $exception) {
    error_log('Coffee O Clock menu update error: ' . $exception->getMessage());
    finish_action('Could not update the menu item. Try again.', 'error');
}
