<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/form-validation.php';
require __DIR__ . '/../includes/orders.php';
require __DIR__ . '/../includes/reservations.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_is_valid($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('The form expired. Return to reservations and try again.');
}
$name = post_text('name', 100);
$phone = post_text('phone', 20);
$date = $_POST['date'] ?? null;
$time = $_POST['time'] ?? null;
$guests = filter_var($_POST['guest_count'] ?? null, FILTER_VALIDATE_INT);
$note = $_POST['note'] ?? '';
$allowedTimes = [];
for ($hour = 8; $hour <= 20; $hour++) {
    $allowedTimes[] = sprintf('%02d:00', $hour);
}
$validDate = is_string($date)
    && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1
    && DateTimeImmutable::createFromFormat('!Y-m-d', $date)?->format('Y-m-d') === $date
    && $date >= date('Y-m-d');
if ($name === null || strlen($name) < 2 || $phone === null
    || !valid_customer_phone($phone) || !$validDate || !is_string($time)
    || !in_array($time, $allowedTimes, true) || $guests === false
    || $guests < 1 || $guests > 12 || !is_string($note) || strlen($note) > 500) {
    $_SESSION['reservation_notice'] = 'Check your name, phone, date, time, and guest count.';
    $_SESSION['reservation_notice_type'] = 'error';
    header('Location: ../reserve.php');
    exit;
}
try {
    create_table_reservation(
        database_connection(),
        $name,
        normalize_customer_phone($phone),
        $guests,
        $date,
        $time . ':00',
        trim($note)
    );
    $_SESSION['reservation_notice'] = 'Reservation requested. The cafe will confirm your time.';
    $_SESSION['reservation_notice_type'] = 'success';
} catch (DomainException $exception) {
    $_SESSION['reservation_notice'] = $exception->getMessage();
    $_SESSION['reservation_notice_type'] = 'error';
} catch (PDOException $exception) {
    error_log('Coffee O Clock reservation error: ' . $exception->getMessage());
    $_SESSION['reservation_notice'] = 'Reservations are unavailable. Import database/update-4.sql and try again.';
    $_SESSION['reservation_notice_type'] = 'error';
}
header('Location: ../reserve.php');
exit;
