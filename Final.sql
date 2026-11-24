CREATE DATABASE IF NOT EXISTS coffee_oclock
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE coffee_oclock;

CREATE TABLE IF NOT EXISTS customer_accounts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    points INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY customer_accounts_phone_unique (phone)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS coupons (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(30) NOT NULL,
    discount_type VARCHAR(10) NOT NULL,
    discount_value DECIMAL(10, 2) NOT NULL,
    minimum_order DECIMAL(10, 2) NOT NULL DEFAULT 0,
    usage_limit INT UNSIGNED NULL,
    usage_count INT UNSIGNED NOT NULL DEFAULT 0,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY coupons_code_unique (code),
    KEY coupons_active_window_idx (is_active, starts_at, ends_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_coupon_redemptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    coupon_id BIGINT UNSIGNED NOT NULL,
    coupon_code VARCHAR(30) NOT NULL,
    discount_amount DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY order_coupon_order_unique (order_id),
    KEY order_coupon_coupon_idx (coupon_id),
    CONSTRAINT order_coupon_order_fk
        FOREIGN KEY (order_id) REFERENCES customer_orders(id)
        ON DELETE CASCADE,
    CONSTRAINT order_coupon_coupon_fk
        FOREIGN KEY (coupon_id) REFERENCES coupons(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS loyalty_transactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NULL,
    points_change INT NOT NULL,
    reason VARCHAR(20) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY loyalty_order_reason_unique (customer_id, order_id, reason),
    KEY loyalty_customer_date_idx (customer_id, created_at),
    CONSTRAINT loyalty_customer_fk
        FOREIGN KEY (customer_id) REFERENCES customer_accounts(id)
        ON DELETE CASCADE,
    CONSTRAINT loyalty_order_fk
        FOREIGN KEY (order_id) REFERENCES customer_orders(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_loyalty_redemptions (
    order_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    points_redeemed INT UNSIGNED NOT NULL,
    discount_amount DECIMAL(10, 2) NOT NULL,
    PRIMARY KEY (order_id),
    KEY order_loyalty_customer_idx (customer_id),
    CONSTRAINT order_loyalty_order_fk
        FOREIGN KEY (order_id) REFERENCES customer_orders(id)
        ON DELETE CASCADE,
    CONSTRAINT order_loyalty_customer_fk
        FOREIGN KEY (customer_id) REFERENCES customer_accounts(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS table_reservations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_name VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(20) NOT NULL,
    guest_count TINYINT UNSIGNED NOT NULL,
    reservation_date DATE NOT NULL,
    reservation_time TIME NOT NULL,
    customer_note VARCHAR(500) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'requested',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY reservation_slot_idx (reservation_date, reservation_time, status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS menu_recipes (
    menu_item_id BIGINT UNSIGNED NOT NULL,
    inventory_item_id BIGINT UNSIGNED NOT NULL,
    quantity_required DECIMAL(10, 2) NOT NULL,
    PRIMARY KEY (menu_item_id, inventory_item_id),
    KEY recipe_inventory_idx (inventory_item_id),
    CONSTRAINT recipe_menu_item_fk
        FOREIGN KEY (menu_item_id) REFERENCES menu_items(id)
        ON DELETE CASCADE,
    CONSTRAINT recipe_inventory_item_fk
        FOREIGN KEY (inventory_item_id) REFERENCES inventory_items(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inventory_movements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    inventory_item_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NULL,
    movement_type VARCHAR(20) NOT NULL,
    quantity_change DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY inventory_order_movement_unique (order_id, inventory_item_id, movement_type),
    KEY inventory_movement_date_idx (inventory_item_id, created_at),
    CONSTRAINT movement_inventory_item_fk
        FOREIGN KEY (inventory_item_id) REFERENCES inventory_items(id)
        ON DELETE CASCADE,
    CONSTRAINT movement_order_fk
        FOREIGN KEY (order_id) REFERENCES customer_orders(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO coupons
    (code, discount_type, discount_value, minimum_order, usage_limit, is_active)
SELECT 'WELCOME10', 'percent', 10.00, 200.00, NULL, 1
WHERE NOT EXISTS (
    SELECT 1 FROM coupons WHERE code = 'WELCOME10'
);
