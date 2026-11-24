<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/validation.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Method not allowed.');
}

if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('The form expired. Return to the sign-in page and try again.');
}

$email = normalize_email($_POST['email'] ?? null);
$password = $_POST['password'] ?? null;

if (!valid_login_input($email, $password)) {
    $_SESSION['login_error'] = 'Enter a valid email address and password.';
    header('Location: ../index.php');
    exit;
}

try {
    $statement = database_connection()->prepare(
        'SELECT id, full_name, email, password_hash
         FROM users
         WHERE email = :email
         LIMIT 1'
    );
    $statement->execute(['email' => $email]);
    $user = $statement->fetch();
} catch (PDOException $exception) {
    error_log('Coffee O Clock sign-in database error: ' . $exception->getMessage());
    http_response_code(503);
    exit('The sign-in service is unavailable. Check the database setup and try again.');
}

if (!$user || !password_verify($password, $user['password_hash'])) {
    $_SESSION['login_error'] = 'Email or password is incorrect.';
    header('Location: ../index.php');
    exit;
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['email'] = $user['email'];
unset($_SESSION['login_error']);

header('Location: ../dashboard.php');
exit;
