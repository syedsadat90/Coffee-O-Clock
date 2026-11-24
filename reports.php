<?php
declare(strict_types=1);

function sales_report(PDO $database, int $days = 7): array
{
    $days = max(1, min($days, 90));
    $summary = $database->prepare(
        "SELECT COUNT(*) AS order_count,
                COALESCE(SUM(o.total), 0) AS revenue,
                COALESCE(SUM(c.discount_amount), 0) AS discounts
         FROM customer_orders o
         LEFT JOIN order_coupon_redemptions c ON c.order_id = o.id
         WHERE o.status = 'delivered'
           AND o.created_at >= CURRENT_DATE - INTERVAL :days DAY"
    );
    $summary->bindValue('days', $days, PDO::PARAM_INT);
    $summary->execute();

    $daily = $database->prepare(
        "SELECT DATE(created_at) AS sale_date,
                COUNT(*) AS order_count, COALESCE(SUM(total), 0) AS revenue
         FROM customer_orders
         WHERE status = 'delivered'
           AND created_at >= CURRENT_DATE - INTERVAL :days DAY
         GROUP BY DATE(created_at) ORDER BY sale_date DESC"
    );
    $daily->bindValue('days', $days, PDO::PARAM_INT);
    $daily->execute();
    return [
        'summary' => $summary->fetch(),
        'daily' => $daily->fetchAll(),
    ];
}
