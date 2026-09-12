# Campus Eats - Complete Documentation

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [System Requirements](#2-system-requirements)
3. [Installation Instructions](#3-installation-instructions)
4. [First Administrator Account](#4-first-administrator-account)
5. [Project Structure](#5-project-structure)
6. [Technology Stack](#6-technology-stack)
7. [User Roles and Responsibilities](#7-user-roles-and-responsibilities)
8. [User Interface Guidelines](#8-user-interface-guidelines)
9. [Security Overview](#9-security-overview)
10. [Database Structure](#10-database-structure)
11. [API Endpoints](#11-api-endpoints)
12. [Troubleshooting](#12-troubleshooting)
13. [Development Process](#13-development-process)
14. [Contributing](#14-contributing)
15. [Code Style Guidelines](#15-code-style-guidelines)
16. [Commit Message Format](#16-commit-message-format)
17. [Testing Requirements](#17-testing-requirements)
18. [Documentation Standards](#18-documentation-standards)
19. [Disclaimer](#19-disclaimer)

---

## 1. Project Overview

Campus Eats is a web-based food ordering and pickup management system for a
single higher education campus. Students browse vendor menus, place orders,
and track status in real time. Vendors manage menus and process orders
through a dedicated portal. Administrators oversee users, vendors, and
system reporting.

### 1.1 The Problem It Solves

Traditional campus food ordering involves physical queues at vendor stalls,
long wait times during peak hours, miscommunication of orders, no digital
tracking, and manual payment handling. There is no integrated system that
centralizes these operations.

### 1.2 The Proposed Solution

Campus Eats provides a single platform where students browse menus, place
orders, and track their status in real time. Vendors gain a digital
storefront to manage menus and process orders efficiently. Administrators
have a central dashboard for overseeing users, vendors, and the entire
system. The result is reduced congestion, improved operational efficiency,
and structured data for reporting.

### 1.3 Live Demo

An interactive demonstration of the intended behavior is available at:

https://campus-eats-platform.lovable.app/

The demonstration shows the core flows of the application. It is not a
substitute for running the PHP application locally. If PHP, MySQL, or a
local web server cannot be installed on the current machine, the
demonstration is the recommended way to review the interface.

### 1.4 Key Features

- User registration with role-based access control.
- Vendor menu browsing and search.
- Shopping cart management.
- Order placement with pickup time selection.
- Real-time order status tracking.
- Order history with receipts.
- Vendor menu and order management.
- Admin user and vendor management.
- Transaction monitoring and reporting.
- Feedback forum for complaints and compliments.

---

## 2. System Requirements

### 2.1 Server Requirements

- Web Server: Apache 2.4 or Nginx 1.18 or equivalent.
- PHP Version: PHP 7.3.12 or higher. PHP 8.3 is recommended.
- Database: MySQL 8.0.18 or higher.
- PHP Extensions: PDO, pdo_mysql, MySQLi, JSON, Session, cURL, OpenSSL.

### 2.2 Client Requirements

- Modern browsers including Google Chrome, Mozilla Firefox, Apple Safari,
  and Microsoft Edge.
- Minimum screen width of 320px for mobile devices.
- Internet connection for loading Font Awesome icons.

### 2.3 PHP Configuration Notes

The application calls external HTTPS endpoints. On Windows installations
of PHP, the CA bundle used for TLS verification is not configured by
default. Set both of the following directives in `php.ini`:

    curl.cainfo = "C:\path\to\cacert.pem"
    openssl.cafile = "C:\path\to\cacert.pem"

The current CA bundle is available from the curl project at
https://curl.se/ca/cacert.pem. Save the file as `cacert.pem`, set both
directives to its path, and restart the web server. Without this
configuration, the application reports the following error:

    cURL error 60 (CURLE_SSL_CACERT): SSL certificate problem:
    unable to get local issuer certificate

---

## 3. Installation Instructions

The instructions below describe a local installation on Windows using
WampServer. Equivalent steps apply on Linux and macOS with a LAMP or
MAMP stack.

### 3.1 Install WampServer

1. Visit the official WampServer website.
2. Download the version appropriate for the Windows system in use.
3. Run the installer and follow the on-screen instructions.
4. Accept the default settings.
5. Launch WampServer after installation completes.
6. Confirm the WampServer tray icon turns green.

### 3.2 Obtain the Repository

Clone the repository with Git:

    git clone https://github.com/HChristopherNaoyuki/campus-eats-web.git
    cd campus-eats-web

Alternatively, download the ZIP archive from GitHub and extract the
contents to a folder named `campus-eats-web`.

### 3.3 Place the Project Under the Web Root

1. Open File Explorer.
2. Navigate to the WampServer web root, normally `C:\wamp64\www\`.
3. Copy the entire `campus-eats-web` folder into that directory.
4. Confirm the resulting path is `C:\wamp64\www\campus-eats-web\`.

### 3.4 Start WampServer Services

1. Click the WampServer tray icon.
2. Choose Start All Services.
3. Wait for the icon to turn green. Apache and MySQL are now running.

### 3.5 Access phpMyAdmin

1. Open a browser.
2. Navigate to `http://localhost/phpmyadmin/`.
3. Log in with the MySQL user account. The default is `root` with an
   empty password on a fresh WampServer installation.

### 3.6 Create the Database

1. In phpMyAdmin, click New in the left sidebar.
2. Enter `campus_eats` as the database name.
3. Choose `utf8mb4_unicode_ci` as the collation.
4. Click Create.

### 3.7 Import the Schema

1. Select the `campus_eats` database in the left sidebar.
2. Click the Import tab.
3. Click Choose File and browse to:
   `C:\wamp64\www\campus-eats-web\Solution\sql\install.sql`
4. Click Go at the bottom of the page.
5. Wait for the import to report success.

The import creates all required tables. It does not insert any user,
administrator, demo, or sample account. All accounts are created through
the registration page. See [Section 4](#4-first-administrator-account).

The `install.sql` file must be saved as UTF-8 without a byte order mark.
If a text editor rewrites the leading hyphens of a comment line, the
schema installation will fail with a syntax error. See
[Section 12.5](#125-installation-completes-but-the-users-table-is-missing).

### 3.8 Configure Database Connection

1. Open the file
   `C:\wamp64\www\campus-eats-web\Solution\config\constants.php`
   in a text editor.
2. Confirm the following values match the local database:

        define('DB_HOST', 'localhost');
        define('DB_NAME', 'campus_eats');
        define('DB_USER', 'root');
        define('DB_PASS', '');
        define('DB_CHARSET', 'utf8mb4');

3. Save the file.

### 3.9 Configure URL Settings

1. In the same file, locate the `ROOT_URL` definition.
2. Set it to match the local installation path:

        define('ROOT_URL', '/campus-eats-web');

3. Save the file.

### 3.10 Set Directory Permissions

1. Navigate to `C:\wamp64\www\campus-eats-web\`.
2. Right-click the `Issues` folder and open Properties.
3. Confirm the folder is writable by the web server user.
4. Repeat for the `Solution/sql/` folder if manual SQL edits are
   anticipated.

### 3.11 Access the Application

1. Open a browser.
2. Navigate to `http://localhost/campus-eats-web/`.
3. The Campus Eats landing page appears.

### 3.12 Register the First Account

1. Click Sign In, then click Create account.
2. The registration page detects that the users table is empty.
3. The Role dropdown offers Student, Standard, Vendor, and Admin.
4. Register the first account with the Admin role.
5. Record the 16-character User ID shown on the confirmation screen.

After the first account exists, the Admin role is no longer offered and
is rejected server-side if submitted. See
[Section 4](#4-first-administrator-account).

### 3.13 Register Subsequent Accounts

Register additional accounts through the same registration page. The
available roles for subsequent registrations are Student, Standard, and
Vendor. Vendor registrations require administrative approval before the
vendor may log in.

### 3.14 Verify Installation

1. Log in with the first account.
2. Confirm the administrator dashboard appears.
3. Confirm the dashboard statistics render.
4. Navigate through the sections to confirm all links resolve.

---

## 4. First Administrator Account

The application does not ship with a default administrator account. No
account is created during installation. The first account created through
the registration page may be assigned the Admin role. After that first
account exists, the Admin role is neither offered nor accepted by the
registration page.

This behavior ensures that:

- No default username or password exists anywhere in the code-base.
- No demo account is inserted during installation.
- Administrative access is established by the operator on first use.

If the administrator password is lost, it can be reset through the
Recover account page using the email address and the 16-character User
ID recorded at registration. Alternatively, the password hash may be
updated directly in the database by a person with database access.

---

## 5. Project Structure

```
campus-eats-web/
├── .htaccess
├── _diagnose.php
├── about.php
├── faq.php
├── firebase.rules.json
├── help.php
├── index.php
├── privacy.php
├── terms.php
├── Documentation/
│   └── requirements/
│       └── campus-eats-process-document.pdf
├── Issues/
│   └── error_log.txt
└── Solution/
    ├── api/
    │   ├── add_menu_item.php
    │   ├── delete_menu_item.php
    │   ├── firebase_config.php
    │   ├── get_cart.php
    │   ├── get_csrf_token.php
    │   ├── get_menu_item.php
    │   ├── get_menu_items.php
    │   ├── get_order_details.php
    │   ├── get_order_status.php
    │   ├── get_orders.php
    │   ├── get_vendors.php
    │   ├── process_payment.php
    │   ├── update_cart.php
    │   ├── update_menu_item.php
    │   └── vendor_respond_order.php
    ├── assets/
    │   ├── css/
    │   │   ├── admin.css
    │   │   ├── apple.css
    │   │   ├── cart.css
    │   │   ├── dashboard-common.css
    │   │   ├── dashboard.css
    │   │   ├── footer-fix.css
    │   │   ├── layout-fix.css
    │   │   ├── modules.css
    │   │   ├── public.css
    │   │   ├── sidebar.css
    │   │   ├── student.css
    │   │   ├── style.css
    │   │   └── vendor.css
    │   ├── js/
    │   │   ├── admin.js
    │   │   ├── auth.js
    │   │   ├── cart-page.js
    │   │   ├── cart.js
    │   │   ├── checkout.js
    │   │   ├── dashboard-common.js
    │   │   ├── firebase.js
    │   │   ├── feedback-firebase.js
    │   │   ├── main.js
    │   │   ├── payment-modal.js
    │   │   ├── student.js
    │   │   └── vendor.js
    │   └── images/
    │       └── logo.png
    ├── config/
    │   ├── constants.php
    │   ├── database.php
    │   ├── demo_accounts.php
    │   └── error_logging.php
    ├── includes/
    │   ├── admin_sidebar.php
    │   ├── api_service.php
    │   ├── auth.php
    │   ├── dashboard_header.php
    │   ├── firebase_config.php
    │   ├── footer.php
    │   ├── i18n.php
    │   ├── oauth_google.php
    │   ├── password_validation.php
    │   ├── public_header.php
    │   ├── session.php
    │   ├── student_sidebar.php
    │   ├── user_id.php
    │   ├── vendor_functions.php
    │   └── vendor_sidebar.php
    ├── lang/
    │   ├── af.php
    │   └── en.php
    ├── modules/
    │   ├── admin/
    │   │   ├── dashboard.php
    │   │   ├── manage_users.php
    │   │   ├── manage_vendors.php
    │   │   ├── monitor_transactions.php
    │   │   ├── order_management.php
    │   │   ├── reports.php
    │   │   ├── security_log.php
    │   │   └── view_feedback.php
    │   ├── auth/
    │   │   ├── _diag_register.php
    │   │   ├── forgot_password.php
    │   │   ├── login.php
    │   │   ├── logout.php
    │   │   └── register.php
    │   ├── student/
    │   │   ├── cart.php
    │   │   ├── checkout.php
    │   │   ├── dashboard.php
    │   │   ├── menu_browse.php
    │   │   ├── order_history.php
    │   │   ├── order_tracking.php
    │   │   └── submit_feedback.php
    │   └── vendor/
    │       ├── dashboard.php
    │       ├── menu.php
    │       ├── orders.php
    │       ├── reports.php
    │       ├── respond_order.php
    │       └── update_status.php
    ├── sql/
    │   ├── add_indexes.sql
    │   ├── add_transaction_id.sql
    │   ├── fix_orders_table.sql
    │   ├── fix_payments_table.sql
    │   ├── install.sql
    │   ├── install_login_attempts_table.sql
    │   ├── login_attempts_table.sql
    │   ├── password_reset_attempts.sql
    │   ├── seed.php
    │   └── update_account_type_enum.sql
    └── data/
        └── user.txt
```

---

## 6. Technology Stack

### 6.1 Backend

- PHP 7.3.12 or higher. PHP 8.3 is recommended.
- PDO with prepared statements for all database access.

### 6.2 Database

- MySQL 8.0.18 or higher.
- Schema normalised to Third Normal Form.

### 6.3 Frontend

- HTML5 with semantic and accessible markup.
- CSS3 in external files. No inline style blocks.
- JavaScript ES6.

### 6.4 Icons

- Font Awesome 6.4.0.

### 6.5 Styling and Scripting Philosophy

All CSS is in external files under `Solution/assets/css/`. All JavaScript
is in external files under `Solution/assets/js/`. No inline styles and no
inline scripts are permitted except where a Content Security Policy nonce
is present.

---

## 7. User Roles and Responsibilities

The system is designed around four user roles, each with specific
permissions and interfaces.

### 7.1 Student

Students are the core customers of the platform.

Primary responsibilities: browse menus, place orders, and provide
feedback.

Primary functions:

- Register and log in to the system.
- Browse and search campus vendors and their menus.
- Add items to a shopping cart.
- Place orders and select a preferred pickup time.
- Track the real-time status of orders.
- View a complete history of past orders.
- Submit complaints or compliments through the feedback form.
- Receive a 2.5 percent student discount on purchases.

### 7.2 Standard

Standard users are customers who do not receive the student discount.

Primary responsibilities: browse menus, place orders, and provide
feedback.

Primary functions:

- Register and log in to the system.
- Browse and search campus vendors and their menus.
- Add items to a shopping cart.
- Place orders and select a preferred pickup time.
- Track the real-time status of orders.
- View a complete history of past orders.
- Submit complaints or compliments through the feedback form.
- No discount is applied to purchases.

### 7.3 Vendor

Vendors are campus food stalls or cafeterias that fulfil orders.

Primary responsibilities: manage the menu, process incoming orders, and
review sales reports.

Primary functions:

- Register and log in through the vendor portal.
- Manage the menu by adding, editing, or removing items.
- Toggle the availability of specific menu items.
- Accept or reject incoming customer orders.
- Update order status through the workflow.
- View sales reports and performance summaries.

### 7.4 Administrator

Administrators are system managers responsible for oversight and
configuration.

Primary responsibilities: manage users, vendors, and platform reporting.

Primary functions:

- Manage all user accounts.
- Approve or reject new vendor accounts.
- Monitor all financial transactions.
- Oversee all orders in the system.
- View and manage feedback.
- Generate and review system-wide reports.

---

## 8. User Interface Guidelines

All interfaces follow a minimalist design philosophy with a consistent
orange and gray colour scheme. The aim is a professional, clean, and
usable experience on desktop and mobile.

### 8.1 Student and Standard Interface

- Vendor Listing Dashboard: The main landing page displays all available
  vendors that are approved and open.
- Menu Browsing Screen: Shows a specific vendor's menu grouped by
  category. Each item displays its name, price, and description, with an
  Add to Cart button.
- Shopping Cart Interface: Displays selected items, allows quantity
  updates, and calculates subtotals, fees, and tax. A prominent Checkout
  button completes the flow.
- Order Tracking Page: Shows the real-time status of an order through a
  visual progress bar and a summary of the order.
- Order History Page: Lists past orders with receipts and a reorder
  option for completed orders.

### 8.2 Vendor Interface

- Menu Management Dashboard: Provides a central place for the vendor to
  add, edit, or delete menu items. A toggle marks items as available or
  unavailable.
- Order Management Panel: Displays incoming orders grouped by status.
  The vendor accepts, rejects, or updates each order.
- Shop Status: A button on the vendor dashboard opens or closes the shop
  for business.

### 8.3 Administrator Interface

- User Management Dashboard: Create, approve, suspend, or delete user
  accounts and change roles.
- Vendor Approval and Monitoring Panel: List of vendors with their
  status. The administrator approves or rejects applications and
  manages shop status.
- Reporting Dashboard: System-wide statistics including total users,
  orders, and revenue, plus recent orders and pending approvals.

---

## 9. Security Overview

Security is treated as a core component of every feature.

### 9.1 Authentication and Authorisation

- Password hashing with bcrypt at cost factor 12.
- Role-based access control enforced on every protected page.
- Session management with HttpOnly cookies, SameSite=Lax, and a custom
  session name.
- Rate limiting of five failed login attempts within fifteen minutes.

### 9.2 Data Protection and Integrity

- SQL injection prevention through PDO prepared statements on all
  database queries.
- Cross-site scripting prevention through `htmlspecialchars` with
  `ENT_QUOTES`. The `escapeOutput()` helper is the canonical wrapper.
- Cross-site request forgery prevention through tokens on all
  state-changing forms.
- Password hashes are never exposed to browser code.

### 9.3 Security Headers

- Content Security Policy restricting script sources.
- X-Frame-Options set to DENY.
- X-Content-Type-Options set to nosniff.
- Referrer-Policy set to strict-origin-when-cross-origin.
- Strict-Transport-Security enabled when the request is over HTTPS.

### 9.4 Operational Notes

- No default or demo credentials exist in the codebase.
- The first administrator account is created by the operator through
  the registration page.
- `curl.cainfo` and `openssl.cafile` must be configured for outbound
  HTTPS requests to be verified.

---

## 10. Database Structure

The database is normalised to Third Normal Form. All tables use InnoDB
and the `utf8mb4_unicode_ci` collation.

### 10.1 Core Entities

1. `users` stores all user accounts. Fields include `user_id`,
   `unique_id` for the 16-character recovery identifier, `full_name`,
   `username`, `email`, `password_hash`, `account_type`, `is_active`,
   and `is_verified`.
2. `vendors` stores vendor profile information linked to a user account.
   Fields include `vendor_name`, `description`, `is_open`, and
   `is_approved`.
3. `menu_items` stores menu items offered by each vendor. Fields include
   `item_name`, `price`, `quantity_available`, `category`, and
   `is_available`.
4. `orders` stores order records. Fields include `order_number`,
   `transaction_id`, `subtotal`, `service_fee`, `student_discount`,
   `tax`, `rounding_adjustment`, `total_amount`, `order_status`,
   `pickup_time`, and `special_requests`.
5. `order_items` stores the individual items within an order. It is
   linked to `orders` and `menu_items`.
6. `payments` stores payment records linked to a specific order.
   Fields include `payment_method`, `payment_status`,
   `transaction_reference`, and `amount`.
7. `complaints_compliments` stores user feedback. Fields include
   `entry_type`, `subject`, `message`, and `is_resolved`.
8. `login_attempts` stores failed login attempts by IP address and
   username for rate limiting.
9. `password_reset_attempts` stores password reset attempts by IP
   address and email for rate limiting.
10. `user_sessions` stores active session mappings for authenticated
    users.

### 10.2 Key Relationships

- A `users` record can be linked to one `vendors` record.
- A `vendors` record has many `menu_items`.
- A `users` record can place many `orders`.
- An `orders` record belongs to one `vendors` and one `users`.
- An `orders` record has many `order_items`.
- An `orders` record has one `payments` record.

---

## 11. API Endpoints

The system uses a set of API endpoints in `Solution/api/` for dynamic
data exchange between the frontend and the backend.

### 11.1 Cart Management

- `get_cart.php` returns the current user's cart contents.
- `update_cart.php` handles adding, removing, updating quantities, and
  clearing items.

### 11.2 Menu Management

- `get_menu_items.php` returns menu items for a specific vendor.
- `get_menu_item.php` returns the details of a single menu item.
- `add_menu_item.php` adds a new item to a vendor's menu.
- `update_menu_item.php` updates an existing menu item.
- `delete_menu_item.php` deletes an item that has never been ordered.

### 11.3 Order Processing

- `get_orders.php` returns the current user's order history.
- `get_order_details.php` returns the full details of a specific order.
- `get_order_status.php` returns the current status of an order.
- `process_payment.php` handles order placement and payment. It is not
  invoked by the current checkout page. See
  [Section 13.4](#134-known-unused-code).
- `vendor_respond_order.php` allows a vendor to update an order status.

### 11.4 Vendor and User Management

- `get_vendors.php` returns the list of approved and active vendors.
- `firebase_config.php` returns the Firebase client configuration.

---

## 12. Troubleshooting

### 12.1 Cannot Log In After Registration

Symptom: a user has registered but cannot log in.

Cause: the account is not verified, or the account is suspended, or the
credentials do not match.

Resolution: confirm the account exists in the `users` table with
`is_verified = 1` and `is_active = 1`. The first account registered
through the registration page is verified automatically. Subsequent
accounts may require administrative approval, depending on how the
application is configured.

### 12.2 Vendor Cannot Accept Orders

Symptom: a vendor is logged in but cannot accept orders.

Cause: one or more of the vendor flags is not set.

Resolution: confirm all of the following:

1. The vendor account has `users.is_active = 1`.
2. The vendor account has `users.is_verified = 1`.
3. The vendor profile has `vendors.is_approved = 1`.
4. The vendor profile has `vendors.is_open = 1`.

### 12.3 Missing Menu Items on Student Browsing Page

Symptom: a student cannot see a vendor's menu items.

Cause: one or more of the item flags is not set.

Resolution: confirm:

1. The vendor is `is_approved = 1`.
2. The vendor is `is_open = 1`.
3. The menu item is `is_available = 1`.
4. The menu item has `quantity_available` greater than zero.

### 12.4 Error 500 on API Requests

Symptom: AJAX requests to endpoints such as `process_payment.php` return
HTTP 500.

Cause: a fatal PHP error, a database connection failure, or a syntax
error in a modified file.

Resolution: read the last 30 lines of
`C:\wamp64\www\campus-eats-web\Issues\error_log.txt` and the last 30
lines of `C:\wamp64\logs\apache_error.log`. The first file names the
PHP file and line where the failure occurred.

### 12.5 Installation Completes but the Users Table Is Missing

Symptom: the log shows `Installation completed but the users table is
still missing from database 'campus_eats'.`

Cause: the SQL splitter in `database.php` produced a piece of SQL that
MySQL rejected, or the `install.sql` file is not what the splitter
expects.

Resolution:

1. Confirm `install.sql` begins with two hyphens followed by a space, not
   one hyphen followed by a space.
2. Confirm `install.sql` is saved as UTF-8 without a byte order mark.
3. Confirm `database.php` contains a method named `splitSqlStatements`.
   If it does not, replace `database.php` with the version that includes
   the comment-aware and string-aware splitter.

### 12.6 cURL Error 60 When Loading the Landing Page

Symptom: the landing page shows `Unable to load restaurant data. Please
try again later.`, and the log shows `cURL error 60 (CURLE_SSL_CACERT)`.

Cause: the PHP CA bundle is not configured on this machine.

Resolution:

1. Download the current CA bundle from https://curl.se/ca/cacert.pem.
2. Save it to a path outside the web root, for example
   `C:\wamp64\bin\php\php8.3.28\extras\ssl\cacert.pem`.
3. Set `curl.cainfo` and `openssl.cafile` in `php.ini` to that path.
4. Restart WampServer.

---

## 13. Development Process

### 13.1 Version Control and Documentation

- Version Control: the source code is managed using Git. Each file
  header contains a version history.
- Documentation: this file is the central source of truth for the
  system architecture and operation.
- Process Document: development is aligned with the requirements in
  `Documentation/requirements/campus-eats-process-document.pdf`.

### 13.2 Quality Assurance and Testing

- Error Logging: all system errors and significant events are written
  to `campus-eats-web/Issues/error_log.txt`.
- Code Reviews: changes are reviewed for adherence to the coding
  standards and the security requirements in
  [Section 15](#15-code-style-guidelines) and
  [Section 9](#9-security-overview).
- Security Audits: the system is analysed for common vulnerabilities,
  with fixes documented in each file's version history.

### 13.3 Key Development Principles

- Security First: security is a core component of every feature.
- User-Centric Design: interfaces follow a minimalist philosophy for
  clarity and ease of use.
- Maintainability: code is written to be clean, well-commented, and
  modular.

### 13.4 Known Unused Code

The following items are present in the source but are not part of any
active request path. They are listed here so contributors do not
mistake them for entry points.

- `Solution/assets/js/checkout.js` is not included by any page. The
  active checkout flow is the server-rendered form in
  `Solution/modules/student/checkout.php`.
- `Solution/includes/session.php` is a deprecated compatibility wrapper
  that includes `auth.php`. New code should include `auth.php` directly.
- The `admin_claims` node and the `users` node in
  `firebase.rules.json` are not written to by any code in the
  application. Only the `feedback` node is used.

---

## 14. Contributing

### 14.1 How to Contribute

1. Fork the repository.
2. Clone the fork.
3. Create a feature branch.
4. Make changes following the guidelines below.
5. Test locally.
6. Push the branch and open a pull request.

### 14.2 Feature Branch Naming

- Feature: `feature/your-feature-name`
- Bug Fix: `bugfix/issue-number-description`
- Documentation: `docs/your-doc-update`

### 14.3 Pull Request Requirements

- The description must state the problem, the approach, and how the
  change was tested.
- The pull request must be focused on a single change.
- All automated checks must pass.
- At least one maintainer must approve the change.
- All review comments must be resolved before merging.

### 14.4 No New Classes or Files

Do not introduce new classes or files unless the change explicitly
requires them. Extend the existing structure. Reuse and refinement of
the current structure are preferred over replacement.

---

## 15. Code Style Guidelines

### 15.1 PHP

- Brace style: Allman. Opening braces go on a new line.

      function exampleFunction()
      {
          // Code here
      }

- Indentation: four spaces. Do not use tabs.
- Class names: PascalCase, for example `UserManagement`.
- Methods and variables: camelCase, for example `getUserById`.
- Constants: UPPER_SNAKE_CASE, for example `ORDER_STATUS_PENDING`.
- Every function has a docblock with a purpose, parameters, and return
  value.

### 15.2 JavaScript

- Indentation: two spaces. Do not use tabs.
- Every statement terminates with a semicolon.
- Use `const` for values that do not change and `let` for values that
  do. Do not use `var`.
- Variable and function names: camelCase.
- Bind events with `addEventListener`, not with inline handlers.

### 15.3 CSS

- All CSS is in external files. No inline style attributes.
- Class names use kebab-case, for example `.admin-sidebar`.
- Colours and spacing use CSS custom properties.
- Keep specificity low and predictable.

### 15.4 HTML

- Use semantic elements such as `<header>`, `<nav>`, `<main>`,
  `<section>`, and `<footer>`.
- Include `alt` attributes on images and `aria` attributes on
  interactive elements.
- Validate forms on both the client and the server.

---

## 16. Commit Message Format

```
    Header line: a single line that explains the commit in the imperative

    The body of the commit message is a short text that explains what
    changed and why. Wrap the body at approximately 74 characters. Use
    the imperative mood in the header and the past tense is acceptable
    in the body if a narrative form reads more naturally.

    Explain the problem and the reasoning behind the chosen solution.
    Reviewers can read the patch, but the reasoning behind the patch is
    not visible in the diff.

    Reported-by: whoever reported the issue
    Signed-off-by: Your Name
```

---

## 17. Testing Requirements

### 17.1 Local Testing

Set up the system locally using the instructions in
[Section 3](#3-installation-instructions). Test every change in a local
environment before submitting a pull request.

### 17.2 Functional Testing

Confirm these flows work as expected:

- User registration and login.
- Vendor menu management.
- Order placement and tracking.
- Administrator user and vendor management.

### 17.3 Security Testing

Confirm the following controls are effective:

- SQL injection attempts are blocked by prepared statements.
- CSRF tokens are validated on every state-changing form.
- XSS attempts are escaped by `escapeOutput()`.
- Rate limiting blocks repeated failed login attempts.

### 17.4 Cross-Browser Testing

Test in the latest versions of Google Chrome, Mozilla Firefox, Apple
Safari, and Microsoft Edge.

### 17.5 Mobile Testing

Test at these screen widths:

- Mobile: 320px to 480px.
- Tablet: 768px to 1024px.
- Desktop: 1025px and above.

---

## 18. Documentation Standards

### 18.1 Code Documentation

- File headers: every PHP file has a header comment describing the
  purpose and any corrections.
- Function docblocks: every function has a docblock with a purpose,
  parameters, and return value.
- Inline comments: add a comment wherever the logic is non-obvious.

### 18.2 Visual Media

All screenshots and images are stored in `Solution/assets/images/`.
Images are never embedded directly in documentation files. Reference
them by relative path.

### 18.3 Compliance with the Process Document

Development treats the process document as an integrated whole. Every
design decision is validated against
`Documentation/requirements/campus-eats-process-document.pdf`.

### 18.4 Updating This Document

When a change affects the documented behavior, update this file in the
same pull request as the change.

---

## 19. Disclaimer

```
UNDER NO CIRCUMSTANCES SHOULD IMAGES OR EMOJIS BE INCLUDED DIRECTLY IN
THIS FILE. ALL VISUAL MEDIA, INCLUDING SCREENSHOTS AND IMAGES OF THE
APPLICATION, MUST BE STORED IN A DEDICATED FOLDER WITHIN THE PROJECT
DIRECTORY. THIS FOLDER SHOULD BE CLEARLY STRUCTURED AND NAMED
ACCORDINGLY TO INDICATE THAT IT CONTAINS ALL VISUAL CONTENT RELATED TO
THE APPLICATION, FOR EXAMPLE A FOLDER NAMED IMAGES, SCREENSHOTS, OR
MEDIA. THE AUTHOR IS NOT LIABLE OR RESPONSIBLE FOR ANY MALFUNCTIONS,
DEFECTS, OR ISSUES THAT MAY OCCUR AS A RESULT OF COPYING, MODIFYING, OR
USING THIS SOFTWARE. IF ANY PROBLEMS OR ERRORS ARE ENCOUNTERED, PLEASE
DO NOT ATTEMPT TO FIX THEM SILENTLY OR OUTSIDE THE PROJECT. INSTEAD,
SUBMIT A PULL REQUEST OR OPEN AN ISSUE ON THE CORRESPONDING GITHUB
REPOSITORY SO THAT IT CAN BE ADDRESSED APPROPRIATELY BY THE MAINTAINERS
OR CONTRIBUTORS.
```

---

*END OF DOCUMENT*

---