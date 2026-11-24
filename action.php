<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/form-validation.php';

function require_post_action(): void
{
    require_signed_in();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        http_response_code(405);
        exit('Method not allowed.');
    }
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('The form expired. Return to the dashboard and try again.');
    }
}

function finish_action(string $message, string $type = 'success'): never
{
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header('Location: ../dashboard.php');
    exit;
}

function valid_money_or_quantity(?string $value, bool $allowZero = false): bool
{
    if ($value === null) {
        return false;
    }
    $number = (float) $value;
    return is_finite($number) && ($allowZero ? $number >= 0 : $number > 0);
}
