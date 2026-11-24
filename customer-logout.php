<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_is_valid($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('The form expired. Return to the rewards page and try again.');
}
unset($_SESSION['customer_id'], $_SESSION['customer_name'], $_SESSION['customer_phone']);
header('Location: ../customer-account.php');
exit;
