<?php
declare(strict_types=1);

function post_text(string $field, int $maxLength): ?string
{
    $value = $_POST[$field] ?? null;
    if (!is_string($value)) {
        return null;
    }
    $value = trim($value);
    $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    if ($value === '' || $length > $maxLength) {
        return null;
    }
    return $value;
}

function post_positive_number(string $field, bool $allowZero = false): ?string
{
    $value = $_POST[$field] ?? null;
    if (!is_string($value) && !is_numeric($value)) {
        return null;
    }
    $normalized = trim((string) $value);
    if (!is_numeric($normalized)) {
        return null;
    }
    $number = (float) $normalized;
    if (!is_finite($number) || $number > 99999999.99
        || ($allowZero ? $number < 0 : $number <= 0)) {
        return null;
    }
    if (strlen($normalized) > 12 || preg_match('/^\d+(?:\.\d{1,2})?$/', $normalized) !== 1) {
        return null;
    }
    return number_format($number, 2, '.', '');
}

function post_record_id(string $field = 'id'): ?int
{
    $value = $_POST[$field] ?? null;
    if (!is_string($value) && !is_int($value)) {
        return null;
    }
    $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $validated === false ? null : $validated;
}
