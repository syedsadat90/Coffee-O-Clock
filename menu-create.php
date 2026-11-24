<?php
declare(strict_types=1);

require __DIR__ . '/../includes/action.php';
require __DIR__ . '/../includes/menu.php';

require_post_action();
$name = post_text('name', 100);
$category = post_text('category', 60);
$price = post_positive_number('price');

if ($name === null || $category === null || !valid_money_or_quantity($price)) {
    finish_action('Enter an item name, category, and a valid price greater than zero.', 'error');
}

try {
    create_menu_item(database_connection(), $name, $category, $price);
    finish_action('Menu item added.');
} catch (PDOException $exception) {
    error_log('Coffee O Clock menu create error: ' . $exception->getMessage());
    finish_action('Could not save the menu item. Check the database and try again.', 'error');
}
