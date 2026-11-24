<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/customers.php';
require __DIR__ . '/../includes/form-validation.php';
require __DIR__ . '/../includes/orders.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_is_valid($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('The form expired. Return to the rewards page and try again.');
}

$name = post_text('name', 100);
$phone = post_text('phone', 20);
$password = $_POST['password'] ?? null;
$length = static fn (string $value): int =>
    function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
if ($name === null || $length($name) < 2 || $phone === null
    || !valid_customer_phone($phone) || !is_string($password)
    || $length($password) < 8 || $length($password) > 200) {
    $_SESSION['customer_notice'] = 'Use a name, valid phone number, and password with at least 8 characters.';
    $_SESSION['customer_notice_type'] = 'error';
    header('Location: ../customer-account.php');
    exit;
}

try {
    $database = database_connection();
    $customerId = register_customer(
        $database,
        $name,
        normalize_customer_phone($phone),
        $password
    );
    session_regenerate_id(true);
    $_SESSION['customer_id'] = $customerId;
    $_SESSION['customer_name'] = $name;
    $_SESSION['customer_phone'] = normalize_customer_phone($phone);
    header('Location: ../customer-account.php');
    exit;
} catch (PDOException $exception) {
    if ($exception->getCode() === '23000') {
        $_SESSION['customer_notice'] = 'An account already uses that phone number. Sign in instead.';
    } else {
        error_log('Coffee O Clock customer registration error: ' . $exception->getMessage());
        $_SESSION['customer_notice'] = 'Could not create the account. Import database/update-4.sql and try again.';
    }
    $_SESSION['customer_notice_type'] = 'error';
    header('Location: ../customer-account.php');
    exit;
}
