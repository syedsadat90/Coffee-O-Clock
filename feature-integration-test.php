<?php
declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/coupons.php';
require __DIR__ . '/../includes/orders.php';
require __DIR__ . '/../includes/customers.php';
require __DIR__ . '/../includes/reservations.php';
require __DIR__ . '/../includes/reports.php';

$database = database_connection();
$token = strtoupper(substr(bin2hex(random_bytes(6)), 0, 10));
$menuName = 'Test item ' . $token;
$inventoryName = 'Test stock ' . $token;
$phone = '+88017123' . str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
$couponCode = 'TEST' . $token;
$orderIds = [];
$temporaryUserId = null;
$testFailed = false;

try {
    $staffId = $database->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn();
    if (!$staffId) {
        $staff = $database->prepare(
            'INSERT INTO users (full_name, email, password_hash)
             VALUES (:name, :email, :password_hash)'
        );
        $staff->execute([
            'name' => 'Integration Test',
            'email' => 'integration-' . strtolower($token) . '@example.test',
            'password_hash' => password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT),
        ]);
        $temporaryUserId = (int) $database->lastInsertId();
        $staffId = $temporaryUserId;
    }

    $menu = $database->prepare(
        'INSERT INTO menu_items (name, category, price) VALUES (:name, :category, :price)'
    );
    $menu->execute(['name' => $menuName, 'category' => 'Test', 'price' => '150.00']);
    $menuId = (int) $database->lastInsertId();

    $stock = $database->prepare(
        'INSERT INTO inventory_items (name, unit, quantity, reorder_level)
         VALUES (:name, :unit, :quantity, :reorder)'
    );
    $stock->execute([
        'name' => $inventoryName,
        'unit' => 'kg',
        'quantity' => '1.00',
        'reorder' => '0.10',
    ]);
    $inventoryId = (int) $database->lastInsertId();
    $database->prepare(
        'INSERT INTO menu_recipes (menu_item_id, inventory_item_id, quantity_required)
         VALUES (:menu_id, :inventory_id, :quantity)'
    )->execute(['menu_id' => $menuId, 'inventory_id' => $inventoryId, 'quantity' => '0.10']);

    $customerId = register_customer($database, 'Integration Customer', $phone, 'TestPassword123!');
    $database->prepare('UPDATE customer_accounts SET points = 1000 WHERE id = :id')
        ->execute(['id' => $customerId]);
    create_coupon($database, $couponCode, 'percent', '10.00', '0.00', null);

    $firstOrder = create_customer_order(
        $database,
        'Integration Customer',
        $phone,
        '123 Test Street, Test City',
        '',
        [$menuId => 2],
        $couponCode,
        $customerId,
        100
    );
    $orderIds[] = $firstOrder['id'];
    if ((float) $firstOrder['total'] !== 260.0) {
        throw new RuntimeException('Coupon and loyalty discounts did not produce the expected total.');
    }
    $stockAfterOrder = (float) $database->query(
        'SELECT quantity FROM inventory_items WHERE id = ' . $inventoryId
    )->fetchColumn();
    if (abs($stockAfterOrder - 0.80) > 0.001) {
        throw new RuntimeException('Recipe stock was not deducted when the order was placed.');
    }

    foreach (['confirmed', 'preparing', 'cancelled'] as $status) {
        if (!change_customer_order_status($database, (int) $firstOrder['id'], $status, (int) $staffId)) {
            throw new RuntimeException('The cancellation status progression failed.');
        }
    }
    $stockAfterCancel = (float) $database->query(
        'SELECT quantity FROM inventory_items WHERE id = ' . $inventoryId
    )->fetchColumn();
    $pointsAfterCancel = (int) $database->query(
        'SELECT points FROM customer_accounts WHERE id = ' . $customerId
    )->fetchColumn();
    if (abs($stockAfterCancel - 1.0) > 0.001 || $pointsAfterCancel !== 1000) {
        throw new RuntimeException('Cancellation did not restore stock and redeemed points.');
    }

    $secondOrder = create_customer_order(
        $database,
        'Integration Customer',
        $phone,
        '123 Test Street, Test City',
        '',
        [$menuId => 1],
        '',
        $customerId
    );
    $orderIds[] = $secondOrder['id'];
    foreach (['confirmed', 'preparing', 'out_for_delivery', 'delivered'] as $status) {
        if (!change_customer_order_status($database, (int) $secondOrder['id'], $status, (int) $staffId)) {
            throw new RuntimeException('The delivery status progression failed.');
        }
    }
    $earnedPoints = (int) $database->query(
        'SELECT points FROM customer_accounts WHERE id = ' . $customerId
    )->fetchColumn();
    if ($earnedPoints !== 1001) {
        throw new RuntimeException('Delivered order did not award loyalty points.');
    }

    $reservationDate = date('Y-m-d', strtotime('+30 days'));
    create_table_reservation(
        $database,
        'Integration Customer',
        $phone,
        12,
        $reservationDate,
        '21:00:00',
        ''
    );
    create_table_reservation(
        $database,
        'Integration Customer',
        $phone,
        12,
        $reservationDate,
        '21:00:00',
        ''
    );
    $slotWasFull = false;
    try {
        create_table_reservation(
            $database,
            'Integration Customer',
            $phone,
            1,
            $reservationDate,
            '21:00:00',
            ''
        );
    } catch (DomainException) {
        $slotWasFull = true;
    }
    if (!$slotWasFull) {
        throw new RuntimeException('Reservation capacity allowed an overbooked slot.');
    }

    sales_report($database);
    echo "PASS - discounts, recipe stock, cancellation refunds, loyalty earning, reservation capacity, and sales report\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'FAIL - ' . $exception->getMessage() . "\n");
    $testFailed = true;
} finally {
    foreach ($orderIds as $orderId) {
        $database->prepare('DELETE FROM customer_orders WHERE id = :id')
            ->execute(['id' => $orderId]);
    }
    $database->prepare('DELETE FROM table_reservations WHERE customer_phone = :phone')
        ->execute(['phone' => $phone]);
    $database->prepare('DELETE FROM coupons WHERE code = :code')
        ->execute(['code' => $couponCode]);
    $database->prepare('DELETE FROM customer_accounts WHERE phone = :phone')
        ->execute(['phone' => $phone]);
    $database->prepare('DELETE FROM inventory_items WHERE name = :name')
        ->execute(['name' => $inventoryName]);
    $database->prepare('DELETE FROM menu_items WHERE name = :name')
        ->execute(['name' => $menuName]);
    if ($temporaryUserId !== null) {
        $database->prepare('DELETE FROM users WHERE id = :id')
            ->execute(['id' => $temporaryUserId]);
    }
}

exit($testFailed ? 1 : 0);
