<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/orders.php';
require __DIR__ . '/../includes/customers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_is_valid($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('The form expired. Return to the rewards page and try again.');
}
$phone = $_POST['phone'] ?? null;
$password = $_POST['password'] ?? null;
if (!is_string($phone) || !valid_customer_phone($phone) || !is_string($password)) {
    $_SESSION['customer_notice'] = 'Enter a valid phone number and password.';
    $_SESSION['customer_notice_type'] = 'error';
    header('Location: ../customer-account.php');
    exit;
}
try {
    $customer = find_customer_by_phone(database_connection(), normalize_customer_phone($phone));
} catch (PDOException $exception) {
    error_log('Coffee O Clock customer sign-in error: ' . $exception->getMessage());
    $_SESSION['customer_notice'] = 'Customer accounts are unavailable. Import database/update-4.sql and try again.';
    $_SESSION['customer_notice_type'] = 'error';
    header('Location: ../customer-account.php');
    exit;
}
if (!$customer || !password_verify($password, (string) $customer['password_hash'])) {
    $_SESSION['customer_notice'] = 'Phone number or password is incorrect.';
    $_SESSION['customer_notice_type'] = 'error';
    header('Location: ../customer-account.php');
    exit;
}
session_regenerate_id(true);
$_SESSION['customer_id'] = (int) $customer['id'];
$_SESSION['customer_name'] = $customer['full_name'];
$_SESSION['customer_phone'] = $customer['phone'];
header('Location: ../customer-account.php');
exit;
