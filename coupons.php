<?php
declare(strict_types=1);

function normalize_coupon_code(string $code): string
{
    return strtoupper(trim($code));
}

function calculate_coupon_discount(
    array $coupon,
    int $subtotalCents,
    ?int $now = null
): ?int {
    $now ??= time();
    if ((int) ($coupon['is_active'] ?? 0) !== 1
        || ($coupon['usage_limit'] !== null
            && (int) $coupon['usage_count'] >= (int) $coupon['usage_limit'])
        || $subtotalCents < (int) round((float) $coupon['minimum_order'] * 100)) {
        return null;
    }

    $startsAt = !empty($coupon['starts_at']) ? strtotime((string) $coupon['starts_at']) : null;
    $endsAt = !empty($coupon['ends_at']) ? strtotime((string) $coupon['ends_at']) : null;
    if (($startsAt !== null && ($startsAt === false || $now < $startsAt))
        || ($endsAt !== null && ($endsAt === false || $now > $endsAt))) {
        return null;
    }

    $value = (float) $coupon['discount_value'];
    $discountCents = ($coupon['discount_type'] ?? '') === 'percent'
        ? (int) floor($subtotalCents * min(100, $value) / 100)
        : (int) round($value * 100);
    return max(0, min($subtotalCents, $discountCents));
}

function create_coupon(
    PDO $database,
    string $code,
    string $type,
    string $value,
    string $minimum,
    ?int $usageLimit
): int {
    $statement = $database->prepare(
        'INSERT INTO coupons
            (code, discount_type, discount_value, minimum_order, usage_limit)
         VALUES (:code, :type, :value, :minimum, :usage_limit)'
    );
    $statement->execute([
        'code' => normalize_coupon_code($code),
        'type' => $type,
        'value' => $value,
        'minimum' => $minimum,
        'usage_limit' => $usageLimit,
    ]);
    return (int) $database->lastInsertId();
}

function list_coupons(PDO $database): array
{
    return $database->query(
        'SELECT id, code, discount_type, discount_value, minimum_order,
                usage_limit, usage_count, starts_at, ends_at, is_active
         FROM coupons ORDER BY created_at DESC, id DESC'
    )->fetchAll();
}

function set_coupon_active(PDO $database, int $id, bool $active): bool
{
    $statement = $database->prepare(
        'UPDATE coupons SET is_active = :active WHERE id = :id'
    );
    $statement->execute(['active' => (int) $active, 'id' => $id]);
    return $statement->rowCount() > 0;
}
