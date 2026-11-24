<?php
declare(strict_types=1);

function normalize_email(mixed $value): string
{
    return is_string($value) ? strtolower(trim($value)) : '';
}

function valid_login_input(mixed $email, mixed $password): bool
{
    if (!is_string($email) || !is_string($password)) {
        return false;
    }

    $normalizedEmail = normalize_email($email);
    return strlen($normalizedEmail) <= 254
        && filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL) !== false
        && strlen($password) >= 1
        && strlen($password) <= 128;
}
