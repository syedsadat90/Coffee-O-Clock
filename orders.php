<?php
declare(strict_types=1);

require_once __DIR__ . '/coupons.php';

function order_status_labels(): array
{
    return [
        'pending' => 'Order received',
        'confirmed' => 'Confirmed',
        'preparing' => 'Preparing',
        'out_for_delivery' => 'Out for delivery',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
    ];
}

function next_order_statuses(string $status): array
{
    $transitions = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['preparing', 'cancelled'],
        'preparing' => ['out_for_delivery', 'cancelled'],
        'out_for_delivery' => ['delivered'],
        'delivered' => [],
        'cancelled' => [],
    ];
    return $transitions[$status] ?? [];
}

function customer_delivery_estimate(array $order, ?int $currentTime = null): ?array
{
    $status = (string) ($order['status'] ?? '');
    if ($status === 'cancelled') {
        return null;
    }

    if ($status === 'delivered') {
        foreach (array_reverse($order['history'] ?? []) as $event) {
            if (($event['status'] ?? '') === 'delivered') {
                $deliveredAt = strtotime((string) ($event['changed_at'] ?? ''));
                if ($deliveredAt !== false) {
                    return [
                        'title' => 'Delivered',
                        'window' => date('M j, Y · g:i a', $deliveredAt),
                        'note' => 'Your order was delivered at this time.',
                    ];
                }
            }
        }
        return null;
    }

    $remainingMinutes = [
        'pending' => [30, 45],
        'confirmed' => [25, 35],
        'preparing' => [15, 30],
        'out_for_delivery' => [10, 20],
    ][$status] ?? null;
    if ($remainingMinutes === null) {
        return null;
    }

    $estimateAnchor = $currentTime ?? time();
    $placedAt = strtotime((string) ($order['created_at'] ?? ''));
    if ($placedAt !== false) {
        $estimateAnchor = max($estimateAnchor, $placedAt);
    }
    foreach ($order['history'] ?? [] as $event) {
        $statusChangedAt = strtotime((string) ($event['changed_at'] ?? ''));
        if ($statusChangedAt !== false) {
            $estimateAnchor = max($estimateAnchor, $statusChangedAt);
        }
    }

    $from = $estimateAnchor + $remainingMinutes[0] * 60;
    $to = $estimateAnchor + $remainingMinutes[1] * 60;
    $sameDay = date('Y-m-d', $from) === date('Y-m-d', $to);
    $sameYear = date('Y', $from) === date('Y', $to);
    $fromFormat = $sameDay ? 'g:i a' : ($sameYear ? 'g:i a M j' : 'g:i a M j, Y');
    $toFormat = $sameDay ? 'g:i a M j' : ($sameYear ? 'g:i a M j' : 'g:i a M j, Y');

    return [
        'title' => $status === 'out_for_delivery'
            ? 'Approximate arrival'
            : 'Approximate delivery',
        'window' => date($fromFormat, $from) . ' – ' . date($toFormat, $to),
        'note' => 'About ' . $remainingMinutes[0] . '–' . $remainingMinutes[1]
            . ' minutes based on the current order status and latest update. Times may vary; refresh tracking for updates.',
    ];
}

function normalize_customer_phone(string $phone): string
{
    $phone = preg_replace('/[\s().-]/', '', trim($phone)) ?? '';

    if (preg_match('/^01[0-9]{9}$/', $phone) === 1) {
        return '+880' . substr($phone, 1);
    }

    if (preg_match('/^8801[0-9]{9}$/', $phone) === 1) {
        return '+' . $phone;
    }

    return $phone;
}

function valid_customer_phone(string $phone): bool
{
    return preg_match('/^\+?[0-9]{7,15}$/', normalize_customer_phone($phone)) === 1;
}

function valid_order_quantities(mixed $raw): ?array
{
    if (!is_array($raw) || count($raw) > 100) {
        return null;
    }

    $quantities = [];
    foreach ($raw as $rawId => $rawQuantity) {
        if ((!is_string($rawId) && !is_int($rawId))
            || !is_string($rawQuantity)
            || !preg_match('/^[1-9][0-9]*$/', (string) $rawId)
            || !preg_match('/^(?:0|[1-9][0-9]*)$/', $rawQuantity)) {
            return null;
        }

        $quantity = (int) $rawQuantity;
        if ($quantity > 20) {
            return null;
        }
        if ($quantity > 0) {
            $quantities[(int) $rawId] = $quantity;
        }
    }

    return $quantities === [] ? null : $quantities;
}

function create_customer_order(
    PDO $database,
    string $customerName,
    string $phone,
    string $address,
    string $note,
    array $quantities,
    string $couponCode = '',
    ?int $customerId = null,
    int $redeemPoints = 0
): array {
    if ($quantities === []) {
        throw new InvalidArgumentException('Choose at least one menu item.');
    }
    if ($redeemPoints < 0 || $redeemPoints % 100 !== 0) {
        throw new InvalidArgumentException('Redeem loyalty points in multiples of 100.');
    }

    try {
        $database->beginTransaction();
        $ids = array_keys($quantities);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $menuQuery = $database->prepare(
            "SELECT id, name, price FROM menu_items
             WHERE is_available = 1 AND id IN ({$placeholders})
             FOR UPDATE"
        );
        $menuQuery->execute($ids);
        $availableItems = $menuQuery->fetchAll();

        if (count($availableItems) !== count($quantities)) {
            throw new DomainException(
                'One or more selected items are no longer available. Review the menu and try again.'
            );
        }

        $subtotalCents = 0;
        $lines = [];
        foreach ($availableItems as $item) {
            $id = (int) $item['id'];
            $quantity = $quantities[$id];
            $unitCents = (int) round((float) $item['price'] * 100);
            $lineCents = $unitCents * $quantity;
            $subtotalCents += $lineCents;
            $lines[] = [
                'id' => $id,
                'name' => (string) $item['name'],
                'quantity' => $quantity,
                'unit_cents' => $unitCents,
                'line_cents' => $lineCents,
            ];
        }

        if ($subtotalCents <= 0 || $subtotalCents > 9999999999) {
            throw new DomainException('The order total is outside the accepted range.');
        }

        $coupon = null;
        $couponDiscountCents = 0;
        $normalizedCouponCode = strtoupper(trim($couponCode));
        if ($normalizedCouponCode !== '') {
            $couponQuery = $database->prepare(
                'SELECT id, code, discount_type, discount_value, minimum_order,
                        usage_limit, usage_count, starts_at, ends_at, is_active
                 FROM coupons WHERE code = :code LIMIT 1 FOR UPDATE'
            );
            $couponQuery->execute(['code' => $normalizedCouponCode]);
            $coupon = $couponQuery->fetch();
            if (!$coupon) {
                throw new DomainException('That coupon code is not valid.');
            }
            $couponDiscountCents = calculate_coupon_discount($coupon, $subtotalCents);
            if ($couponDiscountCents === null) {
                throw new DomainException('That coupon is expired, used up, or does not meet its minimum order.');
            }
        }

        $customer = null;
        $loyaltyDiscountCents = 0;
        if ($customerId !== null) {
            $customerQuery = $database->prepare(
                'SELECT id, phone, points FROM customer_accounts WHERE id = :id LIMIT 1 FOR UPDATE'
            );
            $customerQuery->execute(['id' => $customerId]);
            $customer = $customerQuery->fetch();
            if (!$customer || (string) $customer['phone'] !== normalize_customer_phone($phone)) {
                throw new DomainException('Sign in with the customer account that matches this phone number.');
            }
        }
        if ($redeemPoints > 0) {
            if (!$customer || $redeemPoints > (int) $customer['points']) {
                throw new DomainException('You do not have enough loyalty points for that redemption.');
            }
            $discountableCents = $subtotalCents - $couponDiscountCents;
            $loyaltyDiscountCents = $redeemPoints * 10;
            if ($loyaltyDiscountCents > (int) floor($discountableCents * 0.2)) {
                throw new DomainException('Loyalty points can cover up to 20% of the discounted order subtotal.');
            }
        }
        $totalCents = $subtotalCents - $couponDiscountCents - $loyaltyDiscountCents;

        $recipeQuery = $database->prepare(
            "SELECT menu_item_id, inventory_item_id, quantity_required
             FROM menu_recipes WHERE menu_item_id IN ({$placeholders})
             ORDER BY inventory_item_id, menu_item_id"
        );
        $recipeQuery->execute($ids);
        $stockRequirements = [];
        foreach ($recipeQuery->fetchAll() as $recipe) {
            $inventoryId = (int) $recipe['inventory_item_id'];
            $menuItemId = (int) $recipe['menu_item_id'];
            $needed = (float) $recipe['quantity_required'] * $quantities[$menuItemId];
            $stockRequirements[$inventoryId] = ($stockRequirements[$inventoryId] ?? 0) + $needed;
        }
        $lockedStock = [];
        foreach ($stockRequirements as $inventoryId => $needed) {
            $stockQuery = $database->prepare(
                'SELECT name, quantity FROM inventory_items WHERE id = :id FOR UPDATE'
            );
            $stockQuery->execute(['id' => $inventoryId]);
            $stockItem = $stockQuery->fetch();
            if (!$stockItem || (float) $stockItem['quantity'] + 0.000001 < $needed) {
                throw new DomainException(
                    'Not enough stock for ' . ($stockItem['name'] ?? 'a menu item')
                    . '. Please adjust your order or contact the cafe.'
                );
            }
            $lockedStock[$inventoryId] = [
                'quantity' => $needed,
            ];
        }

        $code = 'CO-' . strtoupper(bin2hex(random_bytes(6)));
        $order = $database->prepare(
            'INSERT INTO customer_orders
                (order_code, customer_name, customer_phone, delivery_address,
                 customer_note, total, payment_method, status)
             VALUES
                (:code, :name, :phone, :address, :note, :total, :payment, :status)'
        );
        $order->execute([
            'code' => $code,
            'name' => $customerName,
            'phone' => normalize_customer_phone($phone),
            'address' => $address,
            'note' => $note === '' ? null : $note,
            'total' => number_format($totalCents / 100, 2, '.', ''),
            'payment' => 'cash_on_delivery',
            'status' => 'pending',
        ]);
        $orderId = (int) $database->lastInsertId();

        if ($coupon !== null) {
            $redemption = $database->prepare(
                'INSERT INTO order_coupon_redemptions
                    (order_id, coupon_id, coupon_code, discount_amount)
                 VALUES (:order_id, :coupon_id, :coupon_code, :discount)'
            );
            $redemption->execute([
                'order_id' => $orderId,
                'coupon_id' => $coupon['id'],
                'coupon_code' => $coupon['code'],
                'discount' => number_format($couponDiscountCents / 100, 2, '.', ''),
            ]);
            $database->prepare(
                'UPDATE coupons SET usage_count = usage_count + 1 WHERE id = :id'
            )->execute(['id' => $coupon['id']]);
        }

        if ($redeemPoints > 0 && $customer !== null) {
            $database->prepare(
                'UPDATE customer_accounts SET points = points - :points WHERE id = :id'
            )->execute(['points' => $redeemPoints, 'id' => $customerId]);
            $database->prepare(
                'INSERT INTO order_loyalty_redemptions
                    (order_id, customer_id, points_redeemed, discount_amount)
                 VALUES (:order_id, :customer_id, :points, :discount)'
            )->execute([
                'order_id' => $orderId,
                'customer_id' => $customerId,
                'points' => $redeemPoints,
                'discount' => number_format($loyaltyDiscountCents / 100, 2, '.', ''),
            ]);
            $database->prepare(
                "INSERT INTO loyalty_transactions
                    (customer_id, order_id, points_change, reason)
                 VALUES (:customer_id, :order_id, :points, 'redeemed')"
            )->execute([
                'customer_id' => $customerId,
                'order_id' => $orderId,
                'points' => -$redeemPoints,
            ]);
        }

        if ($lockedStock !== []) {
            $stockUpdate = $database->prepare(
                'UPDATE inventory_items SET quantity = quantity - :quantity WHERE id = :id'
            );
            $movement = $database->prepare(
                "INSERT INTO inventory_movements
                    (inventory_item_id, order_id, movement_type, quantity_change)
                 VALUES (:inventory_id, :order_id, 'order_use', :quantity)"
            );
            foreach ($lockedStock as $inventoryId => $stock) {
                $stockUpdate->execute(['quantity' => $stock['quantity'], 'id' => $inventoryId]);
                $movement->execute([
                    'inventory_id' => $inventoryId,
                    'order_id' => $orderId,
                    'quantity' => -$stock['quantity'],
                ]);
            }
        }

        $insertLine = $database->prepare(
            'INSERT INTO customer_order_items
                (order_id, menu_item_id, item_name, quantity, unit_price, line_total)
             VALUES (:order_id, :menu_item_id, :item_name, :quantity, :unit_price, :line_total)'
        );
        foreach ($lines as $line) {
            $insertLine->execute([
                'order_id' => $orderId,
                'menu_item_id' => $line['id'],
                'item_name' => $line['name'],
                'quantity' => $line['quantity'],
                'unit_price' => number_format($line['unit_cents'] / 100, 2, '.', ''),
                'line_total' => number_format($line['line_cents'] / 100, 2, '.', ''),
            ]);
        }

        $history = $database->prepare(
            'INSERT INTO customer_order_status_history (order_id, status)
             VALUES (:order_id, :status)'
        );
        $history->execute(['order_id' => $orderId, 'status' => 'pending']);
        $database->commit();

        return [
            'id' => $orderId,
            'code' => $code,
            'total' => number_format($totalCents / 100, 2, '.', ''),
            'phone' => normalize_customer_phone($phone),
        ];
    } catch (Throwable $exception) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
        throw $exception;
    }
}

function find_customer_order(PDO $database, string $code, string $phone): ?array
{
    $statement = $database->prepare(
        'SELECT id, order_code, customer_name, customer_phone, delivery_address,
                customer_note, total, payment_method, status, created_at, updated_at,
                (total + COALESCE(coupon.discount_amount, 0)
                    + COALESCE(loyalty.discount_amount, 0)) AS subtotal,
                coupon.coupon_code, COALESCE(coupon.discount_amount, 0) AS coupon_discount,
                COALESCE(loyalty.discount_amount, 0) AS loyalty_discount
         FROM customer_orders
         LEFT JOIN order_coupon_redemptions coupon ON coupon.order_id = customer_orders.id
         LEFT JOIN order_loyalty_redemptions loyalty ON loyalty.order_id = customer_orders.id
         WHERE order_code = :code AND customer_phone = :phone
         LIMIT 1'
    );
    $statement->execute([
        'code' => strtoupper(trim($code)),
        'phone' => normalize_customer_phone($phone),
    ]);
    $order = $statement->fetch();
    if (!$order) {
        return null;
    }

    $items = $database->prepare(
        'SELECT item_name, quantity, unit_price, line_total
         FROM customer_order_items WHERE order_id = :order_id ORDER BY id'
    );
    $items->execute(['order_id' => $order['id']]);
    $order['items'] = $items->fetchAll();

    $history = $database->prepare(
        'SELECT status, changed_at FROM customer_order_status_history
         WHERE order_id = :order_id ORDER BY id'
    );
    $history->execute(['order_id' => $order['id']]);
    $order['history'] = $history->fetchAll();
    return $order;
}

function list_recent_customer_orders(PDO $database, int $limit = 50): array
{
    $limit = max(1, min($limit, 100));
    $orders = $database->query(
        'SELECT id, order_code, customer_name, customer_phone, total,
                status, created_at
         FROM customer_orders ORDER BY created_at DESC LIMIT ' . $limit
    )->fetchAll();

    $items = $database->prepare(
        'SELECT item_name, quantity FROM customer_order_items
         WHERE order_id = :order_id ORDER BY id'
    );
    foreach ($orders as &$order) {
        $items->execute(['order_id' => $order['id']]);
        $order['items'] = $items->fetchAll();
    }
    unset($order);
    return $orders;
}

function change_customer_order_status(
    PDO $database,
    int $orderId,
    string $newStatus,
    int $staffUserId
): bool {
    if (!array_key_exists($newStatus, order_status_labels())) {
        return false;
    }

    try {
        $database->beginTransaction();
        $query = $database->prepare(
            'SELECT status, customer_phone, total
             FROM customer_orders WHERE id = :id FOR UPDATE'
        );
        $query->execute(['id' => $orderId]);
        $order = $query->fetch();
        if (!$order
            || !in_array($newStatus, next_order_statuses((string) $order['status']), true)) {
            $database->rollBack();
            return false;
        }

        $update = $database->prepare(
            'UPDATE customer_orders SET status = :status WHERE id = :id'
        );
        $update->execute(['status' => $newStatus, 'id' => $orderId]);
        $history = $database->prepare(
            'INSERT INTO customer_order_status_history
                (order_id, status, changed_by_user_id)
             VALUES (:order_id, :status, :staff_id)'
        );
        $history->execute([
            'order_id' => $orderId,
            'status' => $newStatus,
            'staff_id' => $staffUserId,
        ]);

        if ($newStatus === 'delivered') {
            $customerQuery = $database->prepare(
                'SELECT id FROM customer_accounts WHERE phone = :phone LIMIT 1 FOR UPDATE'
            );
            $customerQuery->execute(['phone' => $order['customer_phone']]);
            $customerId = $customerQuery->fetchColumn();
            $earnedPoints = (int) floor((float) $order['total'] / 100);
            if ($customerId && $earnedPoints > 0) {
                $database->prepare(
                    'UPDATE customer_accounts SET points = points + :points WHERE id = :id'
                )->execute(['points' => $earnedPoints, 'id' => $customerId]);
                $database->prepare(
                    "INSERT INTO loyalty_transactions
                        (customer_id, order_id, points_change, reason)
                     VALUES (:customer_id, :order_id, :points, 'earned')"
                )->execute([
                    'customer_id' => $customerId,
                    'order_id' => $orderId,
                    'points' => $earnedPoints,
                ]);
            }
        }

        if ($newStatus === 'cancelled') {
            $stockMovements = $database->prepare(
                "SELECT inventory_item_id, -quantity_change AS quantity
                 FROM inventory_movements
                 WHERE order_id = :order_id AND movement_type = 'order_use'"
            );
            $stockMovements->execute(['order_id' => $orderId]);
            $restoreStock = $database->prepare(
                'UPDATE inventory_items SET quantity = quantity + :quantity WHERE id = :id'
            );
            $returnMovement = $database->prepare(
                "INSERT INTO inventory_movements
                    (inventory_item_id, order_id, movement_type, quantity_change)
                 VALUES (:inventory_id, :order_id, 'order_return', :quantity)"
            );
            foreach ($stockMovements->fetchAll() as $movement) {
                $restoreStock->execute([
                    'quantity' => $movement['quantity'],
                    'id' => $movement['inventory_item_id'],
                ]);
                $returnMovement->execute([
                    'inventory_id' => $movement['inventory_item_id'],
                    'order_id' => $orderId,
                    'quantity' => $movement['quantity'],
                ]);
            }

            $redemption = $database->prepare(
                'SELECT customer_id, points_redeemed
                 FROM order_loyalty_redemptions WHERE order_id = :order_id'
            );
            $redemption->execute(['order_id' => $orderId]);
            $redeemed = $redemption->fetch();
            if ($redeemed) {
                $database->prepare(
                    'UPDATE customer_accounts SET points = points + :points WHERE id = :id'
                )->execute([
                    'points' => $redeemed['points_redeemed'],
                    'id' => $redeemed['customer_id'],
                ]);
                $database->prepare(
                    "INSERT INTO loyalty_transactions
                        (customer_id, order_id, points_change, reason)
                     VALUES (:customer_id, :order_id, :points, 'refunded')"
                )->execute([
                    'customer_id' => $redeemed['customer_id'],
                    'order_id' => $orderId,
                    'points' => $redeemed['points_redeemed'],
                ]);
            }
        }
        $database->commit();
        return true;
    } catch (Throwable $exception) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
        throw $exception;
    }
}
