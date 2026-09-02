# City Civil Registration System (PHP + MySQL)

A complete, working v1 of a city civil registration system: citizens request
birth/death/marriage certificates online, pay a fee, and staff review and
issue certificates as PDFs.

## Setup with XAMPP

1. **Copy this folder** into your XAMPP `htdocs` directory, e.g.:
   ```
   C:\xampp\htdocs\php-civil-registration
   ```

2. **Start Apache and MySQL** from the XAMPP Control Panel.

3. **Set your Chapa key and admin login** — open `includes/config.php`:
   ```php
   define('CHAPA_SECRET_KEY', 'your_actual_chapa_test_secret_key');
   define('ADMIN_USERNAME', 'admin');
   ```
   The admin password is stored as a hash, not plaintext. Generate one and
   paste it in as `ADMIN_PASSWORD_HASH`:
   ```
   php includes/generate_password_hash.php "your-new-password"
   ```
   Copy the printed hash into `includes/config.php`, then **delete
   `includes/generate_password_hash.php`** — it shouldn't stay reachable
   on a live server.

4. **Visit the site**:
   - Public form: http://localhost/php-civil-registration/index.php
   - Status check: http://localhost/php-civil-registration/status.php
   - Admin panel: http://localhost/php-civil-registration/admin.php
     (you'll be asked to log in first)

   The database (`civil_registration`) and its `requests` table are created
   automatically the first time any page loads, using MySQL via `mysqli`,
   with default XAMPP credentials (`root`, no password).

## How it works

- `index.php` — public request form, links to the status page
- `submit_request.php` — saves the form to MySQL, then starts a Chapa payment
  (currently **bypassed for testing** — see note below)
- `verify_payment.php` — Chapa calls this after payment completes (needs a
  public URL to work — won't fire on `localhost`)
- `payment_success.php` — shows the citizen their Request ID after "payment"
- `status.php` — citizens check their request status and download their
  certificate, using their Request ID + National ID Number
- `login.php` / `login_check.php` / `logout.php` — admin authentication
- `admin.php` — staff view: list requests, approve/reject, mark paid,
  download PDF (requires login)
- `update_status.php` — handles approve/reject (blocks approval until paid)
- `mark_paid.php` — manually mark a request as paid
- `clear_test_data.php` — wipes all requests and generated PDFs, for use
  right before handing the site over for real (requires login)
- `includes/generate_certificate.php` — builds the certificate PDF with FPDF
- `includes/require_login.php` — include at the top of any page that should
  require admin login
- `includes/session.php` — starts sessions with hardened cookie settings;
  every page that touches `$_SESSION` includes this instead of calling
  `session_start()` directly
- `includes/csrf.php` — CSRF token helpers used by all admin forms
- `includes/generate_password_hash.php` — one-time CLI/browser helper to
  generate `ADMIN_PASSWORD_HASH`; delete after use
- `download.php` — gatekeeper for certificate PDFs. `certificates/` is
  blocked from direct web access (see `certificates/.htaccess`), so both
  citizens (via `status.php`) and staff (via `admin.php`) download through
  this script, which re-checks authorization first
- `certificates/` — where generated PDFs are saved (not directly
  web-accessible — see `download.php` above)

## Chapa payment — currently bypassed for testing

Your Chapa account isn't fully verified yet, so real payments can't be
initiated. In `submit_request.php`, this is bypassed with:

```php
$SKIP_CHAPA_FOR_TESTING = true;
```

**Once your Chapa account is verified:**
1. Open `submit_request.php`
2. Set `$SKIP_CHAPA_FOR_TESTING = false;`
3. Test a real submission — it should redirect to Chapa's checkout page

Also note: Chapa's payment callback (`verify_payment.php`) needs a **public
URL** to reach your server — it won't work on `localhost`. That's why the
**"Mark Paid" button** exists in the admin panel — use it for local testing,
and once deployed to the city's real server (with a real domain), update
`BASE_URL` in `includes/config.php` and the real Chapa callback will work
automatically. The manual button is also handy afterward as a staff override
for bank-transfer or in-person payments.

## New features

- **Digital signature & official seal** — `includes/generate_certificate.php`
  now stamps every approved certificate with a generated circular seal
  (`includes/seal.php`, cached to `assets/images/official_seal_generated.png`
  the first time it's needed) plus a registrar signature block
  (`REGISTRAR_NAME` / `REGISTRAR_TITLE` in `includes/config.php`) and a
  verification code. Anyone can confirm a certificate is genuine at
  `verify.php` using the Certificate ID + code printed on the PDF.
- **Multi-step request wizard** — `index.php` is now a 3-step form (Personal
  Info → Document Upload → Review & Pay) with client-side step navigation,
  per-step validation, and a live upload preview, all without page reloads.
  The final step still POSTs normally to `submit_request.php` since it has
  to redirect the browser to Chapa's external checkout page. An optional ID
  document upload is stored in `uploads/` (blocked from direct web access —
  view it via `view_document.php`, admin-only).
- **SMS queue & delivery logs** — every SMS the site sends (request
  received, payment confirmed, approved/rejected, renewal reminders) goes
  through `sendAndLogSMS()` in `includes/sms.php` and is recorded in the new
  `sms_logs` table. Review and retry failed sends at `admin_sms_logs.php`.
- **Live admin search** — the search/filter bar on `admin.php` now queries
  `admin_search.php` via `fetch()` on every keystroke (debounced) and swaps
  the table body in place; the original GET-based filtering still works as
  a fallback.
- **Certificate expiry & renewal reminders** — approved certificates get an
  `expiry_date` (`CERTIFICATE_VALIDITY_DAYS` in `includes/config.php`, 365
  days by default). Run `cron_expiry_reminders.php` from the command line
  (see the crontab example inside that file) to text applicants whose
  certificate is within `RENEWAL_REMINDER_WINDOW_DAYS` of expiring.
- **Client activity timeline** — `status.php` shows a 5-stage progress bar
  (Submitted → SMS Verified → Fee Paid → Under Review → Approved & Ready)
  driven by the request's actual `sms_verified`, `payment_status`, and
  `request_status` columns.

Related new files: `verify.php`, `view_document.php`, `admin_search.php`,
`admin_sms_logs.php`, `resend_sms.php`, `cron_expiry_reminders.php`,
`includes/seal.php`, `uploads/` (with its own `.htaccess`).

Before this goes live, also set `CERTIFICATE_VERIFICATION_SECRET` in
`includes/config.php` to a long random string — it's what makes
verification codes on `verify.php` unforgeable.

## Before handing this to the city for real use

- [ ] Change `ADMIN_USERNAME` and generate a real `ADMIN_PASSWORD_HASH` in
      `includes/config.php` — do not keep the defaults
- [ ] Delete `includes/generate_password_hash.php` once you've used it
- [ ] Set `$SKIP_CHAPA_FOR_TESTING = false;` once Chapa is verified
- [ ] Update `BASE_URL` in `includes/config.php` to the real domain once deployed
- [ ] Run `clear_test_data.php` once, right before real launch — then
      consider deleting the file itself, since it's a standing destructive
      route
- [ ] Add the city's logo and branding colors to the HTML/CSS
- [ ] Enable HTTPS on the real server (required for Chapa's live mode), then
      uncomment `'secure' => true` in `includes/session.php`
- [ ] Confirm `certificates/.htaccess` is actually being applied by your web
      server (Apache must have `AllowOverride All` for the certificates
      folder, or the `Require all denied` rule is silently ignored) — test
      by trying to open a certificate URL directly, it should be blocked
- [ ] Back up the MySQL database regularly once real citizen data is stored
