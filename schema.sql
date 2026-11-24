CREATE DATABASE IF NOT EXISTS coffee_oclock
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE coffee_oclock;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(80) NOT NULL,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY users_email_unique (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS menu_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(60) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY menu_items_available_idx (is_available)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inventory_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    unit VARCHAR(30) NOT NULL,
    quantity DECIMAL(10, 2) NOT NULL DEFAULT 0,
    reorder_level DECIMAL(10, 2) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY inventory_quantity_idx (quantity, reorder_level)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inventory_purchases (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    inventory_item_id BIGINT UNSIGNED NOT NULL,
    quantity_added DECIMAL(10, 2) NOT NULL,
    unit_cost DECIMAL(10, 2) NOT NULL DEFAULT 0,
    purchased_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY purchases_item_date_idx (inventory_item_id, purchased_at),
    CONSTRAINT purchases_inventory_item_fk
        FOREIGN KEY (inventory_item_id) REFERENCES inventory_items(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customer_orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_code VARCHAR(15) NOT NULL,
    customer_name VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(20) NOT NULL,
    delivery_address VARCHAR(500) NOT NULL,
    customer_note VARCHAR(500) NULL,
    total DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(30) NOT NULL DEFAULT 'cash_on_delivery',
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY customer_orders_code_unique (order_code),
    KEY customer_orders_created_idx (created_at),
    KEY customer_orders_status_created_idx (status, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customer_order_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    menu_item_id BIGINT UNSIGNED NULL,
    item_name VARCHAR(100) NOT NULL,
    quantity SMALLINT UNSIGNED NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    line_total DECIMAL(10, 2) NOT NULL,
    PRIMARY KEY (id),
    KEY customer_order_items_order_idx (order_id),
    CONSTRAINT order_items_order_fk
        FOREIGN KEY (order_id) REFERENCES customer_orders(id)
        ON DELETE CASCADE,
    CONSTRAINT order_items_menu_fk
        FOREIGN KEY (menu_item_id) REFERENCES menu_items(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customer_order_status_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(30) NOT NULL,
    changed_by_user_id BIGINT UNSIGNED NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY order_status_history_order_idx (order_id, changed_at),
    CONSTRAINT order_status_history_order_fk
        FOREIGN KEY (order_id) REFERENCES customer_orders(id)
        ON DELETE CASCADE,
    CONSTRAINT order_status_history_user_fk
        FOREIGN KEY (changed_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- Example cafe menu prices and opening stock for demonstration.
-- Existing menu and inventory records are left unchanged on re-import.
INSERT INTO menu_items (name, category, price)
SELECT sample.name, sample.category, sample.price
FROM (
    SELECT 'Espresso' AS name, 'Coffee' AS category, 120.00 AS price
    UNION ALL SELECT 'Americano', 'Coffee', 160.00
    UNION ALL SELECT 'Cappuccino', 'Coffee', 180.00
    UNION ALL SELECT 'Mocha', 'Coffee', 230.00
    UNION ALL SELECT 'Iced Latte', 'Cold drinks', 220.00
    UNION ALL SELECT 'Cold Coffee', 'Cold drinks', 220.00
    UNION ALL SELECT 'Milk Tea', 'Tea', 80.00
    UNION ALL SELECT 'Lemon Tea', 'Tea', 100.00
    UNION ALL SELECT 'Green Tea', 'Tea', 110.00
    UNION ALL SELECT 'Chocolate Brownie', 'Bakery', 150.00
    UNION ALL SELECT 'Chocolate Cake Slice', 'Bakery', 180.00
    UNION ALL SELECT 'Butter Croissant', 'Bakery', 140.00
    UNION ALL SELECT 'Chicken Sandwich', 'Snacks', 220.00
    UNION ALL SELECT 'Garlic Bread', 'Snacks', 130.00
    UNION ALL SELECT 'Strawberry Milkshake', 'Cold drinks', 230.00
    UNION ALL SELECT 'Chicken Wrap', 'Snacks', 250.00
) AS sample
WHERE NOT EXISTS (
    SELECT 1 FROM menu_items existing WHERE existing.name = sample.name
);

INSERT INTO inventory_items (name, unit, quantity, reorder_level)
SELECT sample.name, sample.unit, sample.quantity, sample.reorder_level
FROM (
    SELECT 'Coffee beans' AS name, 'kg' AS unit, 5.00 AS quantity, 2.00 AS reorder_level
    UNION ALL SELECT 'Milk', 'L', 10.00, 3.00
    UNION ALL SELECT 'Sugar', 'kg', 8.00, 2.00
    UNION ALL SELECT 'Tea leaves', 'kg', 3.00, 1.00
    UNION ALL SELECT 'Cocoa powder', 'kg', 2.00, 0.50
    UNION ALL SELECT 'Flour', 'kg', 8.00, 2.00
    UNION ALL SELECT 'Butter', 'kg', 3.00, 1.00
    UNION ALL SELECT 'Eggs', 'pieces', 60.00, 12.00
    UNION ALL SELECT 'Bread', 'loaves', 12.00, 4.00
    UNION ALL SELECT 'Chocolate syrup', 'L', 2.00, 0.50
    UNION ALL SELECT 'Takeaway cups', 'pieces', 100.00, 25.00
) AS sample
WHERE NOT EXISTS (
    SELECT 1 FROM inventory_items existing WHERE existing.name = sample.name
);
