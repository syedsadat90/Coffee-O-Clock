<?php
declare(strict_types=1);

require __DIR__ . '/../includes/action.php';
require __DIR__ . '/../includes/menu.php';

require_post_action();
$id = post_record_id();
if ($id === null) {
    finish_action('Choose a valid menu item.', 'error');
}

try {
    if (!delete_menu_item(database_connection(), $id)) {
        finish_action('That menu item was not found.', 'error');
    }
    finish_action('Menu item removed.');
} catch (PDOException $exception) {
    error_log('Coffee O Clock menu delete error: ' . $exception->getMessage());
    finish_action('Could not remove the menu item. Try again.', 'error');
}
