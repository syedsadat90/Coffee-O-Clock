<?php
declare(strict_types=1);

function reservation_slot_available(
    PDO $database,
    string $date,
    string $time,
    int $guestCount,
    int $capacity = 24
): bool {
    $statement = $database->prepare(
        "SELECT COALESCE(SUM(guest_count), 0)
         FROM table_reservations
         WHERE reservation_date = :date AND reservation_time = :time
           AND status IN ('requested', 'confirmed')"
    );
    $statement->execute(['date' => $date, 'time' => $time]);
    return (int) $statement->fetchColumn() + $guestCount <= $capacity;
}

function create_table_reservation(
    PDO $database,
    string $name,
    string $phone,
    int $guestCount,
    string $date,
    string $time,
    string $note
): int {
    $lockName = 'coffee-reservation-' . sha1($date . $time);
    $lock = $database->prepare('SELECT GET_LOCK(:lock_name, 5)');
    $lock->execute(['lock_name' => $lockName]);
    if ((int) $lock->fetchColumn() !== 1) {
        throw new RuntimeException('Reservation capacity is busy. Please try again.');
    }

    try {
        if (!reservation_slot_available($database, $date, $time, $guestCount)) {
            throw new DomainException('That time is full. Please choose another hour.');
        }
        $statement = $database->prepare(
            'INSERT INTO table_reservations
                (customer_name, customer_phone, guest_count, reservation_date,
                 reservation_time, customer_note)
             VALUES (:name, :phone, :guests, :date, :time, :note)'
        );
        $statement->execute([
            'name' => $name,
            'phone' => $phone,
            'guests' => $guestCount,
            'date' => $date,
            'time' => $time,
            'note' => $note === '' ? null : $note,
        ]);
        return (int) $database->lastInsertId();
    } finally {
        $database->prepare('SELECT RELEASE_LOCK(:lock_name)')
            ->execute(['lock_name' => $lockName]);
    }
}

function list_reservations(PDO $database, int $limit = 50): array
{
    $limit = max(1, min($limit, 100));
    return $database->query(
        'SELECT id, customer_name, customer_phone, guest_count,
                reservation_date, reservation_time, customer_note, status
         FROM table_reservations
         WHERE reservation_date >= CURRENT_DATE
         ORDER BY reservation_date, reservation_time LIMIT ' . $limit
    )->fetchAll();
}

function change_reservation_status(PDO $database, int $id, string $status): bool
{
    if (!in_array($status, ['confirmed', 'completed', 'cancelled'], true)) {
        return false;
    }
    $statement = $database->prepare(
        "UPDATE table_reservations SET status = :status
         WHERE id = :id AND status IN ('requested', 'confirmed')"
    );
    $statement->execute(['status' => $status, 'id' => $id]);
    return $statement->rowCount() > 0;
}
