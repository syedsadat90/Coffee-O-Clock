# Account and workspace requirements

## Functional requirements

- Staff can sign in with a registered email and password.
- Invalid sign-in details show a helpful error without exposing account data.
- Successful sign-in starts a PHP session and opens the cafe workspace.
- A visitor who is not signed in cannot access the workspace or its actions.
- Signed-in staff can sign out.
- Staff accounts are created locally by the project administrator; public
  self-registration is outside this update.
- Signed-in staff can manage menu records and inventory records.
- A stock purchase increases the item's current quantity and is recorded in
  purchase history.
- Inventory status is based on the saved quantity and reorder level.

## Quality and safety requirements

- Passwords are stored as hashes.
- Database reads and writes use prepared statements.
- Management forms require POST and include a session-bound request token.
- Submitted text and numeric values are validated on the server.
- Displayed user-provided text is escaped in HTML.
- Database exceptions are logged on the server and reported as a general
  message in the browser.
- The interface should remain usable on desktop and small mobile screens.

## Outside this update

- Sales entry and automatic ingredient usage.
- Customer visit counts.
- Daily expenses, profit calculations, and reports.
- Password reset, email verification, and staff role management.
