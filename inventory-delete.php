<?php
declare(strict_types=1);

require __DIR__ . '/../includes/action.php';
require __DIR__ . '/../includes/inventory.php';

require_post_action();
$id = post_record_id();
if ($id === null) {
    finish_action('Choose a valid stock item.', 'error');
}

try {
    if (!delete_inventory_item(database_connection(), $id)) {
        finish_action('That stock item was not found.', 'error');
    }
    finish_action('Stock record and its purchase history removed.');
} catch (PDOException $exception) {
    error_log('Coffee O Clock inventory delete error: ' . $exception->getMessage());
    finish_action('Could not remove the stock item. Try again.', 'error');
}
