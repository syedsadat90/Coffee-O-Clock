<?php
declare(strict_types=1);

require __DIR__ . '/../includes/action.php';
require __DIR__ . '/../includes/menu.php';

require_post_action();
$id = post_record_id();
$available = $_POST['available'] ?? null;

if ($id === null || !in_array($available, ['0', '1'], true)) {
    finish_action('Choose a valid menu item and availability status.', 'error');
}

try {
    if (!change_menu_availability(database_connection(), $id, $available === '1')) {
        finish_action('That menu item was not found.', 'error');
    }
    finish_action($available === '1' ? 'Menu item is available.' : 'Menu item paused.');
} catch (PDOException $exception) {
    error_log('Coffee O Clock menu status error: ' . $exception->getMessage());
    finish_action('Could not update the menu item. Try again.', 'error');
}
