<?php
declare(strict_types=1);

function list_inventory_items(PDO $database): array
{
    return $database->query(
        'SELECT id, name, unit, quantity, reorder_level, updated_at
         FROM inventory_items
         ORDER BY name'
    )->fetchAll();
}

function create_inventory_item(
    PDO $database,
    string $name,
    string $unit,
    string $quantity,
    string $reorderLevel
): int {
    $statement = $database->prepare(
        'INSERT INTO inventory_items (name, unit, quantity, reorder_level)
         VALUES (:name, :unit, :quantity, :reorder_level)'
    );
    $statement->execute([
        'name' => $name,
        'unit' => $unit,
        'quantity' => $quantity,
        'reorder_level' => $reorderLevel,
    ]);
    return (int) $database->lastInsertId();
}

function update_inventory_item(
    PDO $database,
    int $id,
    string $name,
    string $unit,
    string $quantity,
    string $reorderLevel
): bool {
    $statement = $database->prepare(
        'UPDATE inventory_items
         SET name = :name, unit = :unit, quantity = :quantity,
             reorder_level = :reorder_level
         WHERE id = :id'
    );
    $statement->execute([
        'name' => $name,
        'unit' => $unit,
        'quantity' => $quantity,
        'reorder_level' => $reorderLevel,
        'id' => $id,
    ]);

    if ($statement->rowCount() > 0) {
        return true;
    }

    $exists = $database->prepare('SELECT 1 FROM inventory_items WHERE id = :id');
    $exists->execute(['id' => $id]);
    return (bool) $exists->fetchColumn();
}

function record_stock_purchase(
    PDO $database,
    int $itemId,
    string $quantity,
    string $unitCost
): bool {
    try {
        $database->beginTransaction();
        $lock = $database->prepare(
            'SELECT id FROM inventory_items WHERE id = :id FOR UPDATE'
        );
        $lock->execute(['id' => $itemId]);
        if (!$lock->fetch()) {
            $database->rollBack();
            return false;
        }

        $update = $database->prepare(
            'UPDATE inventory_items
             SET quantity = quantity + :quantity
             WHERE id = :id'
        );
        $update->execute(['quantity' => $quantity, 'id' => $itemId]);

        $purchase = $database->prepare(
            'INSERT INTO inventory_purchases
                (inventory_item_id, quantity_added, unit_cost)
             VALUES (:item_id, :quantity, :unit_cost)'
        );
        $purchase->execute([
            'item_id' => $itemId,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
        ]);
        $database->commit();
        return true;
    } catch (Throwable $exception) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
        throw $exception;
    }
}

function delete_inventory_item(PDO $database, int $id): bool
{
    $statement = $database->prepare('DELETE FROM inventory_items WHERE id = :id');
    $statement->execute(['id' => $id]);
    return $statement->rowCount() > 0;
}

function inventory_summary(array $items): array
{
    $lowStock = 0;
    $outOfStock = 0;
    foreach ($items as $item) {
        if ((float) $item['quantity'] <= 0) {
            $outOfStock++;
        } elseif ((float) $item['quantity'] <= (float) $item['reorder_level']) {
            $lowStock++;
        }
    }
    return [
        'total' => count($items),
        'low_stock' => $lowStock,
        'out_of_stock' => $outOfStock,
    ];
}
