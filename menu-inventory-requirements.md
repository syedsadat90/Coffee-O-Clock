# Menu and inventory requirements

## Menu

- A signed-in staff member can add a menu item with its name, category, and
  selling price.
- The workspace lists saved items and whether each is available.
- Staff can pause or re-enable an item and can remove a menu record.

## Inventory

- A signed-in staff member can add a stock item with a unit, starting
  quantity, and reorder level.
- Each item is marked out of stock when its quantity is zero or lower.
- A positive quantity at or below the reorder level is marked for reorder.
- A signed-in staff member can record a purchase quantity and unit cost.
  Recording a purchase adds the quantity and stores a purchase-history row in
  the same database transaction.
- Staff can remove an inventory record; its related purchase history is
  deleted with it.

## Limits

- Quantities and unit costs allow up to two decimal places.
- This update does not deduct stock when menu items are sold.
- Purchase history is stored but a purchase-history screen is not included.
