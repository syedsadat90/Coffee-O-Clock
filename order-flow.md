# Customer order and delivery-tracking flow

## Place a delivery order

1. A customer opens `shop.php`, which lists menu items marked available.
2. The customer selects quantities and provides their name, phone number,
   delivery address, and an optional delivery note.
3. The server validates the form and request token, re-reads the current
   available menu prices, calculates the total, and saves the order and
   item-price snapshots in a single transaction.
4. The order begins in the `pending` state with cash on delivery as its
   payment method.
5. The customer receives an order code and is sent to `track-order.php`.

## Track and update an order

- Customers look up an order by entering both its order code and the phone
  number used at checkout. These details are submitted in a POST request and
  are not put into the page URL.
- Bangladesh phone numbers can be entered in local `01...` or international
  `+8801...` format; separators such as spaces and hyphens are ignored.
- Signed-in staff see recent orders in `dashboard.php` and can move each
  order through allowed delivery statuses using the explicit action button
  for the next step.
- Each accepted status change is saved to the order's status history and is
  shown on the customer's tracking page.
- Customers can use **Refresh status** to look up the latest saved status
  without re-entering their order details.
- Tracking shows an approximate remaining-time range based on the latest
  status: 30–45 minutes when received, 25–35 when confirmed, 15–30 while
  preparing, and 10–20 while out for delivery. The clock-time window uses the
  later of the current time and the order's latest update, so estimates do not
  appear earlier than the order activity. Refresh tracking for an updated
  estimate. Delivered orders show the recorded delivery time.
- Order states move from received to confirmed, preparing, out for delivery,
  and delivered. Staff may cancel an order before it goes out for delivery.

## Limits

- Cash on delivery only; there is no payment-provider integration.
- Inventory is not automatically reduced because menu-to-ingredient recipes
  are not configured.
- The database stores customer contact and delivery details to support
  order processing and tracking.
