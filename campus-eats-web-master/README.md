# Campus Eats

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [System Requirements](#2-system-requirements)
3. [Installation Instructions](#3-installation-instructions)
4. [Demo Users](#4-demo-users)
5. [Project Structure](#5-project-structure)
6. [Technology Stack](#6-technology-stack)
7. [User Roles](#7-user-roles)
8. [Security Overview](#8-security-overview)
9. [Documentation](#9-documentation)
10. [Contributing](#10-contributing)
11. [Disclaimer](#11-disclaimer)

---

## 1. Project Overview

Campus Eats is a web-based food ordering and pickup management system
for a single higher education campus. Students browse vendor menus,
place orders, and track status in real time. Vendors manage menus and
process orders through a dedicated portal. Administrators oversee users,
vendors, and system reporting.

### Live Demo

You can view the absolute demo of this application at:

https://joyful-eats-platform.lovable.app

This link is the absolute demo of what is being built in PHP, MySQL,
JavaScript, CSS, and HTML. That website has all of the basic things of
what is being built. If you are unable to install PHP, MySQL, or any
servers locally due to storage or your system does not meet the minimum
requirement to run, it would be advised to view the website by copying
the link and pasting it on any browser of your choice.

### Key Features

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

### Server Requirements

- **Web Server:** Apache 2.4 or Nginx 1.18 or equivalent.
- **PHP Version:** PHP 7.3.12 or higher.
- **Database:** MySQL 8.0.18 or higher.
- **PHP Extensions:** PDO, MySQLi, JSON, and Session extensions.

### Client Requirements

- Modern browsers including Google Chrome, Mozilla Firefox, Apple
  Safari, and Microsoft Edge.
- Minimum screen width of 320px for mobile devices.
- Internet connection for loading Font Awesome icons.

---

## 3. Installation Instructions

If you can run it locally and have the storage space to download it,
here is a user-friendly guide to install it, how to clone the repo,
place it under www, and view it.

### Step 1: Download and Install WampServer

1. Visit the official WampServer website.
2. Download the appropriate version for your Windows system.
3. Run the installer and follow the on-screen instructions.
4. Accept the default settings during installation.
5. Launch WampServer after installation completes.
6. Ensure the WampServer icon turns green in your system tray.

### Step 2: Clone or Download the Repository

Clone the repository using Git or download the ZIP file.

```bash
git clone https://github.com/HChristopherNaoyuki/campus-eats-web.git
cd campus-eats-web
```

If you are not using Git, download the ZIP file from GitHub and extract
the contents to a folder named `campus-eats-web` on your desktop.

### Step 3: Copy the Folder to the WWW Directory

1. Open File Explorer on your Windows system.
2. Navigate to the WampServer installation directory.
3. The default location is `C:\wamp64\www\`.
4. Copy the entire `campus-eats-web` folder from your desktop.
5. Paste it into the `C:\wamp64\www\` directory.
6. Verify that the path is `C:\wamp64\www\campus-eats-web\`.

### Step 4: Start WampServer Services

1. Click the WampServer icon in your system tray.
2. Select "Start All Services" from the menu.
3. Wait for the icon to turn green.
4. This indicates Apache and MySQL are running correctly.

### Step 5: Access phpMyAdmin

1. Open your web browser.
2. Type `http://localhost/phpmyadmin/` in the address bar.
3. Press Enter to access phpMyAdmin.
4. Log in with username `root` and leave the password field empty.

### Step 6: Create the Database

1. Click on "New" in the left sidebar of phpMyAdmin.
2. Enter `campus_eats` as the database name.
3. Select `utf8mb4_unicode_ci` as the collation.
4. Click the "Create" button.

### Step 7: Import the Database Schema

1. Click on the `campus_eats` database in the left sidebar.
2. Click the "Import" tab at the top of the page.
3. Click "Choose File" and browse to the install.sql file.
4. The file is located at `C:\wamp64\www\campus-eats-web\Solution\sql\install.sql`.
5. Select the file and click the "Go" button at the bottom.
6. Wait for the import to complete successfully.

### Step 8: Seed Demo Data

To populate the database with demo users and sample menu items:

1. Open a command prompt or terminal.
2. Navigate to the project directory:
   ```
   cd C:\wamp64\www\campus-eats-web
   ```
3. Run the seeding script:
   ```
   php Solution/sql/seed.php
   ```
4. Wait for the script to complete execution.
5. You should see messages confirming the seed process.

### Step 9: Configure Database Connection

1. Navigate to the configuration folder:
   `C:\wamp64\www\campus-eats-web\Solution\config\`
2. Open the file named `constants.php` in a text editor.
3. Ensure these values are set correctly:

   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'campus_eats');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('DB_CHARSET', 'utf8mb4');
   ```

4. Save and close the file.

### Step 10: Configure URL Settings

1. Still in the `constants.php` file, locate the `ROOT_URL` definition.
2. Set it to match your server configuration:

   ```php
   define('ROOT_URL', '/campus-eats-web');
   ```

3. Save and close the file.

### Step 11: Set File Permissions

1. Navigate to `C:\wamp64\www\campus-eats-web\`.
2. Right-click the `Issues` folder and select Properties.
3. Ensure the folder has write permissions.
4. Do the same for the `Solution/sql/` folder.

### Step 12: Access the Application

1. Open your web browser.
2. Type `http://localhost/campus-eats-web/` in the address bar.
3. Press Enter to access the application.
4. You should see the Campus Eats landing page.
5. Click the "Open app" button to proceed to login.

### Step 13: Log in to the System

The first time the system is accessed, the default administrator
account is created automatically. Log in with these credentials:

- **Username:** `admin`
- **Password:** `Admin@123`

### Step 14: Verify Installation

1. After logging in, you should see the administrator dashboard.
2. Check that the dashboard displays statistics correctly.
3. Navigate to different sections to ensure functionality.
4. Verify that all links and buttons work as expected.

### Step 15: Change Default Password

1. Click on the user menu in the top right corner.
2. Select "Change Password" if available.
3. If not available, update the password directly in the database.
4. Use a strong password for production environments.

### Common Issues and Solutions

**WampServer icon is orange:**
- Check if another service is using port 80.
- Ensure Apache and MySQL services are running.

**Database connection error:**
- Verify your database credentials in `constants.php`.
- Ensure MySQL is running in WampServer.

**Page not found (404):**
- Verify the `ROOT_URL` configuration.
- Ensure the folder path matches your installation.

---

## 4. Demo Users

These accounts are available after running the seeding script.

### Administrator Accounts

| Full Name | Username | Email | Password |
|-----------|----------|-------|----------|
| John Doe | johndoe | john.doe@example.com | AdminPass123! |
| Sarah Wilson | sarahw | sarah.wilson@example.com | AdminPass987% |

### Vendor Accounts

| Full Name | Username | Email | Password |
|-----------|----------|-------|----------|
| Jane Smith | janesmith | jane.smith@example.com | VendorPass456$ |
| Michael Brown | mikeb | mike.brown@example.com | VendorPass789# |

### Standard Accounts

| Full Name | Username | Email | Password |
|-----------|----------|-------|----------|
| Emily Davis | emilyd | emily.davis@example.com | StandardPass321@ |
| Robert Taylor | robertt | robert.taylor@example.com | StandardPass654# |

### Student Accounts

| Full Name | Username | Email | Password |
|-----------|----------|-------|----------|
| David Lee | davidl | david.lee@example.com | StudentPass234$ |
| Maria Garcia | mariag | maria.garcia@example.com | StudentPass567% |

### Important Security Notice

These accounts are for development and testing only. Change all
default passwords before production deployment.

---

## 5. Project Structure

```
campus-eats-web/
├── .htaccess
├── about.php
├── faq.php
├── help.php
├── index.php
├── privacy.php
├── terms.php
├── .github/
│   └── workflows/
│       ├── ci.yml
│       └── main.yml
├── Documentation/
│   ├── requirements/
│   │   └── campus-eats-process-document.pdf
│   └── reports/
│       ├── documentation.md
│       ├── disclaimer.md
│       ├── contributing.md
│       └── project_plan.md
├── Issues/
│   └── error_log.txt
└── Solution/
    ├── api/
    │   ├── add_menu_item.php
    │   ├── delete_menu_item.php
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
    │   ├── footer.php
    │   ├── header.php
    │   ├── password_validation.php
    │   ├── public_header.php
    │   ├── session.php
    │   ├── student_sidebar.php
    │   ├── user_id.php
    │   └── vendor_sidebar.php
    ├── modules/
    │   ├── admin/
    │   │   ├── dashboard.php
    │   │   ├── manage_users.php
    │   │   ├── manage_vendors.php
    │   │   ├── monitor_transactions.php
    │   │   ├── order_management.php
    │   │   ├── reports.php
    │   │   └── view_feedback.php
    │   ├── auth/
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
    │   ├── add_transaction_id.sql
    │   ├── fix_orders_table.sql
    │   ├── fix_payments_table.sql
    │   ├── install.sql
    │   ├── install_login_attempts_table.sql
    │   ├── login_attempts_table.sql
    │   ├── seed.php
    │   └── update_account_type_enum.sql
    └── data/
        └── users.txt
```

For a complete folder breakdown, see `Documentation/reports/documentation.md`.

---

## 6. Technology Stack

### Backend

- **PHP 7.3.12:** Server-side logic and API endpoints.
- **PDO:** Secure database connections with prepared statements.

### Database

- **MySQL 8.0.18:** Relational database.
- **Normalized to Third Normal Form (3NF).**

### Frontend

- **HTML5:** Semantic and accessible markup.
- **CSS3:** Minimalist styling with external files only.
- **JavaScript ES6:** Client-side interactivity.

### Icons

- **Font Awesome 6.4.0:** Consistent icon set.

---

## 7. User Roles

### Student

**Functions:**

- Register and log in.
- Browse and search vendor menus.
- Add items to cart.
- Place orders with pickup time.
- Track order status.
- View order history.
- Submit feedback.
- Receive 2.5% student discount.

### Standard

**Functions:**

- Register and log in.
- Browse and search vendor menus.
- Add items to cart.
- Place orders with pickup time.
- Track order status.
- View order history.
- Submit feedback.
- No discount applied.

### Vendor

**Functions:**

- Register and log in.
- Manage menu items (add, edit, delete).
- Toggle item availability.
- Accept or reject orders.
- Update order status.
- View sales reports.

### Administrator

**Functions:**

- Manage user accounts.
- Approve or reject vendors.
- Monitor transactions.
- Oversee all orders.
- View and manage feedback.
- Generate system reports.

---

## 8. Security Overview

### Authentication and Authorization

- **Password Hashing:** bcrypt with cost factor 12.
- **Role-Based Access Control:** Strict permissions per role.
- **Session Management:** HttpOnly, SameSite=Lax, custom session name.
- **Rate Limiting:** 5 failed login attempts within 15 minutes.

### Data Protection

- **SQL Injection Prevention:** PDO prepared statements.
- **XSS Prevention:** `htmlspecialchars()` with ENT_QUOTES.
- **CSRF Protection:** Tokens for all state-changing forms.

### Security Headers

- **Content Security Policy (CSP):** Restricts script sources.
- **X-Frame-Options: DENY:** Prevents clickjacking.
- **X-Content-Type-Options: nosniff:** Prevents MIME-sniffing.
- **Referrer-Policy: strict-origin-when-cross-origin.**

---

## 9. Documentation

For detailed information, refer to the following files in the
`Documentation/reports/` directory.

| Document | Description |
|----------|-------------|
| `documentation.md` | System architecture, API, database schema. |
| `contributing.md` | Coding standards, pull request process. |
| `project_plan.md` | Timeline, milestones, deliverables. |
| `disclaimer.md` | Legal disclaimer information. |

The original project requirements are in
`Documentation/requirements/campus-eats-process-document.md`.

---

## 10. Contributing

### Getting Started

1. Fork the repository.
2. Clone your fork.
3. Create a feature branch.
4. Make changes following coding standards.
5. Write tests for new functionality.
6. Submit a pull request.

### Code Style

- **PHP:** PSR-12 with Allman brace style.
- **JavaScript:** ESLint with Airbnb style guide.
- **CSS:** BEM naming with kebab-case.
- **HTML:** Semantic elements with accessibility.

### Security Requirements

- Use PDO prepared statements for all database queries.
- Escape all HTML output using `escapeOutput()`.
- Include CSRF tokens in all state-changing forms.
- Use bcrypt for password hashing.

For complete guidelines, see `Documentation/reports/contributing.md`.

---

## 11. Disclaimer

UNDER NO CIRCUMSTANCES SHOULD IMAGES OR EMOJIS BE INCLUDED DIRECTLY IN
THE README FILE. ALL VISUAL MEDIA, INCLUDING SCREENSHOTS AND IMAGES OF
THE APPLICATION, MUST BE STORED IN A DEDICATED FOLDER WITHIN THE
PROJECT DIRECTORY. THIS FOLDER SHOULD BE CLEARLY STRUCTURED AND NAMED
ACCORDINGLY TO INDICATE THAT IT CONTAINS ALL VISUAL CONTENT RELATED TO
THE APPLICATION (FOR EXAMPLE, A FOLDER NAMED IMAGES, SCREENSHOTS, OR
MEDIA). I AM NOT LIABLE OR RESPONSIBLE FOR ANY MALFUNCTIONS, DEFECTS,
OR ISSUES THAT MAY OCCUR AS A RESULT OF COPYING, MODIFYING, OR USING
THIS SOFTWARE. IF YOU ENCOUNTER ANY PROBLEMS OR ERRORS, PLEASE DO NOT
ATTEMPT TO FIX THEM SILENTLY OR OUTSIDE THE PROJECT. INSTEAD, KINDLY
SUBMIT A PULL REQUEST OR OPEN AN ISSUE ON THE CORRESPONDING GITHUB
REPOSITORY, SO THAT IT CAN BE ADDRESSED APPROPRIATELY BY THE
MAINTAINERS OR CONTRIBUTORS.

---

*END OF DOCUMENT*

---