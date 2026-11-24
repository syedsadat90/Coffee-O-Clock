<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/form-validation.php';
require __DIR__ . '/../includes/orders.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Method not allowed.');
}

if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('The order form expired. Return to the menu and try again.');
}

$customerName = post_text('customer_name', 100);
$phone = post_text('phone', 20);
$address = post_text('address', 500);
$note = $_POST['note'] ?? '';
$couponCode = $_POST['coupon_code'] ?? '';
$redeemPointsInput = $_POST['redeem_points'] ?? '0';
$quantities = valid_order_quantities($_POST['quantities'] ?? null);
$textLength = static fn (string $value): int =>
    function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);

if ($customerName === null || $textLength($customerName) < 2
    || $phone === null || !valid_customer_phone($phone)
    || $address === null || $textLength($address) < 8
    || !is_string($note) || $textLength($note) > 500
    || !is_string($couponCode) || $textLength($couponCode) > 30
    || (!is_string($redeemPointsInput) && !is_int($redeemPointsInput))
    || preg_match('/^(?:0|[1-9][0-9]*)$/', (string) $redeemPointsInput) !== 1
    || (int) $redeemPointsInput > 1000000
    || $quantities === null) {
    $_SESSION['shop_notice'] = 'Check your name, phone, delivery address, and item quantities, then try again.';
    $_SESSION['shop_notice_type'] = 'error';
    header('Location: ../shop.php');
    exit;
}

try {
    $created = create_customer_order(
        database_connection(),
        $customerName,
        $phone,
        $address,
        trim($note),
        $quantities,
        $couponCode,
        isset($_SESSION['customer_id']) ? (int) $_SESSION['customer_id'] : null,
        (int) $redeemPointsInput
    );
    $_SESSION['order_confirmation'] = [
        'code' => $created['code'],
        'phone' => $created['phone'],
    ];
    header('Location: ../track-order.php');
    exit;
} catch (DomainException|InvalidArgumentException $exception) {
    $_SESSION['shop_notice'] = $exception->getMessage();
    $_SESSION['shop_notice_type'] = 'error';
    header('Location: ../shop.php');
    exit;
} catch (Throwable $exception) {
    error_log('Coffee O Clock place order error: ' . $exception->getMessage());
    $_SESSION['shop_notice'] = 'We could not place the order just now. Please try again.';
    $_SESSION['shop_notice_type'] = 'error';
    header('Location: ../shop.php');
    exit;
}
