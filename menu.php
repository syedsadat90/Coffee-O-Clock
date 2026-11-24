<?php
declare(strict_types=1);

function list_menu_items(PDO $database): array
{
    return $database->query(
        'SELECT id, name, category, price, is_available, created_at
         FROM menu_items
         ORDER BY category, name'
    )->fetchAll();
}

function create_menu_item(PDO $database, string $name, string $category, string $price): void
{
    $statement = $database->prepare(
        'INSERT INTO menu_items (name, category, price)
         VALUES (:name, :category, :price)'
    );
    $statement->execute([
        'name' => $name,
        'category' => $category,
        'price' => $price,
    ]);
}

function update_menu_item(
    PDO $database,
    int $id,
    string $name,
    string $category,
    string $price
): bool {
    $statement = $database->prepare(
        'UPDATE menu_items
         SET name = :name, category = :category, price = :price
         WHERE id = :id'
    );
    $statement->execute([
        'name' => $name,
        'category' => $category,
        'price' => $price,
        'id' => $id,
    ]);

    if ($statement->rowCount() > 0) {
        return true;
    }

    $exists = $database->prepare('SELECT 1 FROM menu_items WHERE id = :id');
    $exists->execute(['id' => $id]);
    return (bool) $exists->fetchColumn();
}

function change_menu_availability(PDO $database, int $id, bool $isAvailable): bool
{
    $statement = $database->prepare(
        'UPDATE menu_items SET is_available = :available WHERE id = :id'
    );
    $statement->execute(['available' => $isAvailable ? 1 : 0, 'id' => $id]);
    return $statement->rowCount() > 0;
}

function delete_menu_item(PDO $database, int $id): bool
{
    $statement = $database->prepare('DELETE FROM menu_items WHERE id = :id');
    $statement->execute(['id' => $id]);
    return $statement->rowCount() > 0;
}
