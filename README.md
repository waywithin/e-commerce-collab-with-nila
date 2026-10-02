# Magal Creator Multi-Vendor Marketplace

A complete, functional multi-vendor service marketplace platform built with **PHP 8+**, **MySQL**, **Vanilla JavaScript**, **CSS3**, and **HTML5**. Created to empower women entrepreneurs, boutique owners, and service providers for the **Women Entrepreneurs Award / Event Showcase**.

---

## ðŸŒŸ Key Architecture & Features

### 1. Multi-Vendor Platform Architecture
- **Three Distinct Roles**:
  - **Platform Manager**: Complete platform governance, category management, provider approval, 10% platform commission monitoring, and payout settlements.
  - **Service Provider (Women Entrepreneurs)**: Private studio dashboard, business profile management, service/product CRUD, file uploads, booking management, and settlement tracking.
  - **Customer / Client**: Discovery, category search, public provider profiles, live countdown offers, secure checkout, and booking history.

### 2. Live Dynamic Limited-Time Offers & Countdowns
- Providers can configure optional limited-time promotional offers with start and end `DATETIME` inputs.
- Real-time JavaScript countdown ticker displays remaining time (`Ends in: 03:42:18`).
- **Authoritative Server-Time Validation**: Pricing is validated by MySQL server timestamps during checkout. Expired offers automatically revert to standard pricing.

### 3. Payment Gateway & 10% Commission Engine
- **Decoupled Gateway Adapter Pattern**:
  - `MockGateway`: Ready-to-demo local simulator for presentations, awards showcases, and offline environments.
  - `CashfreeGateway`: Production Cashfree v3 REST API adapter.
- **Zero-Trust Verification**: Orders are only confirmed after verified gateway signatures.
- **Automated Commission Calculations**:
  $$\text{Gross Amount} \longrightarrow 10\% \text{ Platform Commission} + 90\% \text{ Net Provider Payable}$$
  Recorded immutably in the `commissions` table.
- **Strict Prohibition**: No personal UPI QR code uploads; all payments flow through the secured marketplace checkout.

---

## Local Setup

### Step 1: Start Apache and MySQL in XAMPP
1. Open **XAMPP Control Panel**.
2. Click **Start** next to **Apache**.
3. Click **Start** next to **MySQL**.

### Step 2: Import the Database in phpMyAdmin
1. Open your browser and navigate to: `http://localhost/phpmyadmin`
2. Open the **Import** tab, choose this workspace's `schema.sql`, and click **Import**.
3. The script creates `women_marketplace_db` and seeds sample data.

**Warning:** `schema.sql` drops and recreates the application's tables. Import it only into a fresh local/demo database; never import it over data you need to keep.

### Step 3: Run the Website
From PowerShell, start the PHP development server from the project folder (adjust the XAMPP path if installed elsewhere):
```powershell
Set-Location "C:\Users\MY PC\e-commerce3"
& "C:\xampp\php\php.exe" -S 127.0.0.1:8000 -t .
```
Then visit `http://127.0.0.1:8000`. Apache must not already be using that port. PHP needs PDO MySQL enabled; file uploads need Fileinfo. Cashfree also needs cURL.

The database defaults in `config/config.php` use `127.0.0.1:3306`, database `women_marketplace_db`, user `root`, and a blank password. If your local MySQL differs, set `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, and `DB_PORT` in the environment before starting PHP.

---

## Demo Login Credentials

| Role | Email | Password | Business / Function |
| :--- | :--- | :--- | :--- |
| **Platform Manager** | `manager@magalcreator.local` | `password123` | Platform Director & Administrator |
| **Service Provider** | `ayesha.henna@magalcreator.local` | `password123` | Ayesha Henna Artistry Studio |
| **Service Provider** | `meera.couture@magalcreator.local` | `password123` | Meera Couture & Embroidery |
| **Service Provider** | `sunita.bakes@magalcreator.local` | `password123` | Sunita Sweet Retreat Bakery |
| **Customer** | `customer@magalcreator.local` | `password123` | Client Booking Account |

*(The login page at `login.php` includes 1-click Quick Fill demo buttons for frictionless presentation to awards juries).*

## Deployment Notes

- Use a PHP 8+ host with PDO MySQL, HTTPS, and a MySQL database. Set `APP_ENV=production` and the `DB_*` variables in the host's secret/environment settings.
- Keep `ACTIVE_PAYMENT_GATEWAY` as `mock` only for local demonstrations. For real payments, configure `CASHFREE_APP_ID`, `CASHFREE_SECRET_KEY`, and `CASHFREE_ENV` as environment secrets, configure Cashfree webhook/return URLs for the production HTTPS domain, and set the platform's active gateway to `cashfree` in Manager Settings.
- Do not deploy the seeded demo accounts, demo banking details, or the mock payment simulator as production data. Provision real manager credentials and review the payment webhook before accepting live payments.
- Make `uploads/` writable by PHP while keeping script execution disabled there. Configure backups and database migrations; do not use the destructive demo `schema.sql` to update production.

---

## ðŸ“ Project Directory Map

- `config/` - `config.php`, `database.php` (PDO Singleton)
- `includes/` - `auth.php`, `security.php`, `uploader.php`, `helpers.php`, `header.php`, `footer.php`
- `includes/payment/` - `PaymentGatewayInterface.php`, `MockGateway.php`, `CashfreeGateway.php`, `PaymentService.php`
- `assets/` - CSS3 (`style.css`, `components.css`, `dashboard.css`), JS (`main.js`, `countdown.js`), Images & SVG placeholders
- `uploads/` - Secured upload storage (`.htaccess` disabled execution) for products and providers
- `provider/` - Women Entrepreneur studio portal (`products.php`, `product-add.php`, `orders.php`, `settlements.php`, `profile.php`)
- `manager/` - Platform Manager command center (`providers.php`, `categories.php`, `products.php`, `orders.php`, `commissions.php`, `settings.php`)
- `user/` - Customer booking history (`my-orders.php`)
- Public Pages: `index.php`, `browse.php`, `provider-profile.php`, `product-details.php`, `checkout.php`, `checkout-simulate.php`, `payment-success.php`, `login.php`, `register.php`, `register-provider.php`

