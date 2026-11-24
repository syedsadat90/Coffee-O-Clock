# Coffee O'Clock — Member 2
## Staff Workspace, Menu, Inventory, and Shared Services

Member 2 delivers the staff-facing workspace and shared application foundation
for the Coffee O'Clock cafe management project. It includes staff
authentication, menu and inventory management, stock purchases, coupon
administration, and the shared services used by customer ordering.

> **This is a contribution package, not the complete final application.**
> Customer pages, customer accounts, reservations, and their database migration
> are supplied by Member 1. Integrate both packages for the complete project.

## Features

- Staff sign-in and sign-out.
- Create, edit, pause, enable, and delete menu items.
- Track inventory levels and reorder thresholds.
- Record stock purchases and update on-hand quantities.
- Manage coupon codes and review the staff dashboard and sales summary.
- Provide shared request, security, validation, database, and order-processing
  services for the integrated application.

## Files owned by Member 2

- Staff pages: `index.php` and `dashboard.php`.
- Staff authentication, menu, inventory, stock-purchase, coupon, and recipe
  endpoints in `actions/`.
- Shared configuration and services in `config/` and `includes/`, including
  `includes/orders.php` and `includes/coupons.php`.
- Base database schema: `database/schema.sql`.
- Staff/dashboard stylesheet and shared entry point:
  `assets/css/style-member-2.css` and `assets/css/style.css`.
- Staff setup script, validation tests, and project documentation.

Member 1 owns the customer-facing pages, customer/reservation endpoints,
`assets/css/style-member-1.css`, and the customer/order/reservation migration.
Keep these ownership boundaries when combining work.

## Integration requirements

1. Use the repository root as the destination and add Member 2's files.
2. Add Member 1's files without replacing files owned by Member 1.
3. Keep Member 2's `assets/css/style.css` entry point and verify that it imports
   both `style-member-1.css` and `style-member-2.css`.
4. Keep Member 1's `assets/images/` directory and `assets/js/shop.js`.
5. Confirm the Member 1 database migration is present before setting up the
   complete application's database.

## Requirements

- PHP 8.1 or later with PDO MySQL enabled.
- MySQL or a compatible MariaDB server.
- The complete, integrated project for customer-facing functionality.

## Database setup

Start the database server and import the SQL files into the same database, in
this order:

1. This package's `database/schema.sql` creates the `coffee_oclock` database
   and base tables for staff, menu, inventory, purchases, and customer orders.
2. Member 1's `database/update-4.sql` adds customer accounts, loyalty,
   reservations, and related customer features.

The Member 1 migration depends on tables created by the base schema. Back up
any database containing real data before applying schema changes.

The default connection is host `127.0.0.1`, database `coffee_oclock`, user
`root`, and a blank password. To use different local credentials, set
`COFFEE_DB_HOST`, `COFFEE_DB_NAME`, `COFFEE_DB_USER`, and
`COFFEE_DB_PASSWORD` in the terminal before running PHP commands.

## Create a staff account

From the integrated project root, run:

```powershell
C:\xampp\php\php.exe scripts\create-user.php
```

Follow the prompts to set the staff member's name, email, and password. The
account is stored in the database with a password hash.

## Run locally

With XAMPP installed at `C:\xampp` and MySQL running, start the PHP development
server from the integrated project root:

```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8003
```

Open `http://127.0.0.1:8003/` for staff sign-in. Customer ordering and other
Member 1 features are available after both packages have been integrated.
Keep the terminal open while the site is in use; press **Ctrl+C** to stop the
server.

## Verification

Run the Member 2 validation tests from the project root:

```powershell
C:\xampp\php\php.exe tests\validation-test.php
```

After integration, lint the PHP files with:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object {
    C:\xampp\php\php.exe -l $_.FullName
}
```

