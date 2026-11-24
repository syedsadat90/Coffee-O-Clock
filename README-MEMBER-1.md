# Coffee O'Clock — Member 1
## Customer Experience, Accounts, Reservations, and Order Tracking

Member 1 delivers the customer-facing experience for the Coffee O'Clock cafe
management project. It includes online ordering screens, customer accounts and
rewards, table reservations, and order tracking.

> **This is a contribution package, not a standalone application.** It depends
> on the shared files and services supplied by Member 2. Integrate both
> packages into the same project before running the application.

## Features

- Browse, search, and filter available menu items.
- Submit delivery orders and view order status and tracking details.
- Register a customer account, sign in, and review order and rewards history.
- Earn and redeem loyalty points during eligible orders.
- Request a table reservation and view booking confirmation status.
- Use customer-side JavaScript interactions and responsive page styling.

## Files owned by Member 1

- Customer pages: `shop.php`, `customer-account.php`, `reserve.php`, and
  `track-order.php`.
- Customer and reservation endpoints in `actions/`.
- Customer and reservation data-access helpers in `includes/`.
- Customer stylesheet module: `assets/css/style-member-1.css`.
- Customer-facing requirements, order-flow documentation, and feature
  integration tests.
- Customer and reservation database migration:
  `database/update-4.sql`.

Member 2 owns the shared order-processing and coupon services, staff
workspace, base database schema, and common CSS entry point. Keep these
ownership boundaries when combining work.

## Integration requirements

1. Start with the project's repository root and apply Member 2's files.
2. Add Member 1's files without replacing files owned by Member 2.
3. Keep the existing `assets/images/` directory and Member 2's
   `assets/css/style.css` entry point. That entry point must load both
   `style-member-1.css` and `style-member-2.css`.
4. Confirm the shared `includes/orders.php`, `includes/coupons.php`, database
   configuration, bootstrap, and validation helpers from Member 2 are present.
5. Apply the database scripts in the order shown below.

## Requirements

- PHP 8.1 or later with PDO MySQL enabled.
- MySQL or a compatible MariaDB server.
- The complete, integrated project (Member 1 and Member 2 files).

## Database setup

Start the database server, then import these scripts into the same database,
in order:

1. Member 2's `database/schema.sql` creates the shared `coffee_oclock` database
   and base application tables.
2. This package's `database/update-4.sql` adds the customer, loyalty, and
   reservation tables.

Do not import the update migration before the base schema: it relies on the
shared customer-order tables. Back up any database containing real data before
applying schema changes.

## Run locally

From the integrated project root, configure the database connection using the
`COFFEE_DB_HOST`, `COFFEE_DB_NAME`, `COFFEE_DB_USER`, and
`COFFEE_DB_PASSWORD` environment variables if the local MySQL defaults do not
apply. The defaults are `127.0.0.1`, `coffee_oclock`, `root`, and a blank
password.

With XAMPP installed at `C:\xampp`, start the PHP development server:

```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8003
```

Open `http://127.0.0.1:8003/shop.php` for the customer menu,
`http://127.0.0.1:8003/customer-account.php` for rewards,
`http://127.0.0.1:8003/reserve.php` for table bookings, and
`http://127.0.0.1:8003/track-order.php` for order tracking. Keep the terminal
open while the site is in use; press **Ctrl+C** to stop the server.

## Verification

Run the feature integration test from the integrated project root after both
database scripts have been applied:

```powershell
C:\xampp\php\php.exe tests\feature-integration-test.php
```

The test writes temporary records to the configured database and removes them
when it finishes. Use a development database, not production data.


