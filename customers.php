<?php
declare(strict_types=1);

function find_customer_by_phone(PDO $database, string $phone, bool $lock = false): ?array
{
    $query = 'SELECT id, full_name, phone, password_hash, points
              FROM customer_accounts WHERE phone = :phone LIMIT 1';
    if ($lock) {
        $query .= ' FOR UPDATE';
    }
    $statement = $database->prepare($query);
    $statement->execute(['phone' => $phone]);
    $customer = $statement->fetch();
    return $customer ?: null;
}

function customer_order_history(PDO $database, string $phone): array
{
    $statement = $database->prepare(
        'SELECT order_code, total, status, created_at
         FROM customer_orders WHERE customer_phone = :phone
         ORDER BY created_at DESC LIMIT 20'
    );
    $statement->execute(['phone' => $phone]);
    return $statement->fetchAll();
}

function customer_loyalty_history(PDO $database, int $customerId): array
{
    $statement = $database->prepare(
        'SELECT points_change, reason, created_at
         FROM loyalty_transactions
         WHERE customer_id = :customer_id
         ORDER BY created_at DESC, id DESC LIMIT 20'
    );
    $statement->execute(['customer_id' => $customerId]);
    return $statement->fetchAll();
}

function register_customer(PDO $database, string $name, string $phone, string $password): int
{
    $statement = $database->prepare(
        'INSERT INTO customer_accounts (full_name, phone, password_hash)
         VALUES (:name, :phone, :password_hash)'
    );
    $statement->execute([
        'name' => $name,
        'phone' => $phone,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);
    return (int) $database->lastInsertId();
}
