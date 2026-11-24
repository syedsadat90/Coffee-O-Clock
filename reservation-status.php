<?php
declare(strict_types=1);

require __DIR__ . '/../includes/action.php';
require __DIR__ . '/../includes/reservations.php';

require_post_action();
$id = post_record_id();
$status = post_text('status', 20);
if ($id === null || $status === null) {
    finish_action('Choose a valid reservation status.', 'error');
}
try {
    if (!change_reservation_status(database_connection(), $id, $status)) {
        finish_action('That reservation could not be updated. Refresh and try again.', 'error');
    }
    finish_action('Reservation updated.');
} catch (PDOException $exception) {
    error_log('Coffee O Clock reservation status error: ' . $exception->getMessage());
    finish_action('Could not update the reservation. Try again.', 'error');
}
