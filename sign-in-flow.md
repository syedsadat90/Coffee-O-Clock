# Sign-in and sign-out flow

## Sign in

1. The browser requests `index.php`.
2. The page starts a PHP session and includes a request-protection token in
   the sign-in form.
3. The staff member submits their email and password to `actions/login.php`.
4. The server validates the request and looks up the normalized email using a
   prepared MySQL statement.
5. PHP verifies the entered password against the stored password hash.
6. On success, the server regenerates the session ID and stores the signed-in
   account details in the session.
7. The browser is redirected to the signed-in workspace at `dashboard.php`.

## Sign out

1. The signed-in page submits a protected POST request to
   `actions/logout.php`.
2. The server checks the request token.
3. The server clears the session data, expires the session cookie, and
   destroys the session.
4. The browser is redirected back to `index.php`.

## Main files involved

- `index.php` — sign-in form.
- `actions/login.php` — validates credentials and starts the session.
- `dashboard.php` — checks for a signed-in session and displays the account.
- `actions/logout.php` — ends the session.
- `includes/bootstrap.php` — session setup and shared response headers.
- `includes/security.php` — request-token helpers.
- `config/database.php` — MySQL connection.

The dashboard's menu and inventory forms use their own protected POST
endpoints under `actions/`; see `menu-inventory-requirements.md` for those
features.
