# System Documentation

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Technology Stack](#2-technology-stack)
3. [Installation and Setup](#3-installation-and-setup)
4. [User Roles and Responsibilities](#4-user-roles-and-responsibilities)
5. [User Interface Guidelines](#5-user-interface-guidelines)
6. [Security Features](#6-security-features)
7. [Troubleshooting](#7-troubleshooting)
8. [The Database Structure](#8-the-database-structure)
9. [The API Endpoints](#9-the-api-endpoints)
10. [The Development Process](#10-the-development-process)

---

## 1. System Overview

Campus Eats is a web-based food ordering and pickup management system
designed for a single higher education campus. The platform acts as a
central digital hub, connecting students with campus food vendors to
streamline the entire ordering process.

### 1.1. The Problem it Solves

Traditional campus food ordering involves physical queues at vendor
stalls, leading to long wait times during peak hours, miscommunication
of orders, a lack of digital tracking, and inefficient manual payment
handling. There is no integrated system to centralize these operations.

### 1.2. The Proposed Solution

Campus Eats provides a comprehensive solution by allowing students to
browse menus, place orders, and track their status in real time.
Vendors gain a digital storefront to manage menus and process orders
efficiently. Administrators have a central dashboard for overseeing
users, vendors, and the entire system. This digitizes the process,
reduces congestion, improves operational efficiency for vendors, and
provides administrators with valuable data insights. The system is
designed to be a realistic, scalable, and technically achievable
solution for a semester-length project, demonstrating strong software
engineering principles.

---

## 2. Technology Stack

The Campus Eats system is built using a standard, reliable web
development stack.

### 2.1. Backend

**PHP 7.3.12** is used for all server-side logic, API endpoints, and
database interactions.

### 2.2. Database

**MySQL 8.0.18** serves as the relational database for persistent data
storage.

### 2.3. Frontend

The user interface is constructed with **HTML5**, styled with **CSS3**,
and enhanced with **JavaScript ES6** for interactivity.

### 2.4. Icons

**Font Awesome 6.4.0** provides a consistent set of icons across the
application.

### 2.5. Styling Philosophy

All styles are managed in external CSS files to ensure a clean
separation of concerns, maintainability, and adherence to best
practices.

### 2.6. Scripting Philosophy

All JavaScript is placed in external files to enhance security and
maintainability.

---

## 3. Installation and Setup

Follow these steps to get the Campus Eats system running on your local
development environment.

### 3.1. Database Setup

1.  Use a MySQL client (like phpMyAdmin or the command line) to create
    a new database for the system (e.g., `campus_eats`).

2.  Locate the SQL installation script:
    `campus-eats-web/Solution/sql/install.sql`.

3.  Execute this script against your newly created database. This will
    create all the necessary tables, including `users`, `vendors`,
    `menu_items`, `orders`, and others.

### 3.2. Database Configuration

Configure the database connection by editing the constants file at
`campus-eats-web/Solution/config/constants.php`. Ensure these values
are correct for your local environment.

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'campus_eats');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');
```

### 3.3. URL Configuration

The system uses URL constants to correctly link pages. Configure the
`ROOT_URL` constant in `campus-eats-web/Solution/config/constants.php`
to match your server's document root.

```php
// If your site is at: http://localhost/campus-eats-web/
define('ROOT_URL', '/campus-eats-web');

// If your site is at: http://localhost/
define('ROOT_URL', '');
```

### 3.4. Admin Account Creation

The first time the system is accessed, it will automatically create the
default administrator account. You can log in with the following
credentials. It is critical to change the password upon your first
login.

- **Username:** `admin`
- **Password:** `Admin@123`

### 3.5. Seeding Demo Data

For development and testing, a database seeding script is available at
`campus-eats-web/Solution/sql/seed.php`. This script will populate the
database with demo accounts and sample menu items.

**Demo User Accounts:**

The following users are created by the seed script for testing. Each
account includes a username, email, password, and role.

| Name           | Username   | Email                       | Password        | Role    |
|----------------|------------|-----------------------------|-----------------|---------|
| John Doe       | johndoe    | john.doe@example.com        | AdminPass123!   | admin   |
| Sarah Wilson   | sarahw     | sarah.wilson@example.com    | AdminPass987%   | admin   |
| Jane Smith     | janesmith  | jane.smith@example.com      | VendorPass456$  | vendor  |
| Michael Brown  | mikeb      | mike.brown@example.com      | VendorPass789#  | vendor  |
| Emily Davis    | emilyd     | emily.davis@example.com     | StandardPass321@| standard|
| Robert Taylor  | robertt    | robert.taylor@example.com   | StandardPass654#| standard|
| David Lee      | davidl     | david.lee@example.com       | StudentPass234$ | student |
| Maria Garcia   | mariag     | maria.garcia@example.com    | StudentPass567% | student |

---

## 4. User Roles and Responsibilities

The system is designed around four primary user roles, each with
specific permissions and interfaces.

### 4.1. Student

Students are the core customers of the platform.

**Key Responsibilities:** Browse menus, place orders, and provide
feedback.

**Primary Functions:**
- Register and log in to the system.
- Browse and search for campus vendors and their menus.
- Add items to a digital shopping cart.
- Place orders and select a preferred pickup time.
- Track the real-time status of their orders.
- View a complete history of their past orders.
- Submit complaints or compliments via the feedback forum.
- Receive a 2.5% discount on all purchases.

### 4.2. Standard

Standard users are customers who do not qualify for the student
discount.

**Key Responsibilities:** Browse menus, place orders, and provide
feedback.

**Primary Functions:**
- Register and log in to the system.
- Browse and search for campus vendors and their menus.
- Add items to a digital shopping cart.
- Place orders and select a preferred pickup time.
- Track the real-time status of their orders.
- View a complete history of their past orders.
- Submit complaints or compliments via the feedback forum.
- No discount is applied to purchases.

### 4.3. Vendor

Vendors are campus food stalls or cafeterias that fulfill orders.

**Key Responsibilities:** Manage their menu, process incoming orders,
and generate reports.

**Primary Functions:**
- Register and log in through a dedicated vendor portal.
- Manage their menu by adding, editing, or removing items.
- Toggle the availability of specific menu items.
- Accept or reject incoming customer orders.
- Update the order status (e.g., `preparing`, `ready for pickup`).
- View sales reports and analyze their performance.

### 4.4. Administrator

Administrators are system managers responsible for oversight and
configuration.

**Key Responsibilities:** Manage users, vendors, and the platform's
financial health.

**Primary Functions:**
- Manage all user accounts (students, vendors, and other admins).
- Approve or reject new vendor and student accounts.
- Monitor all financial transactions within the platform.
- Oversee all orders in the system.
- View and manage feedback (complaints and compliments).
- Generate and view comprehensive system-wide reports.

---

## 5. User Interface Guidelines

All interfaces in the Campus Eats system adhere to a minimalist design
philosophy, using a consistent orange and gray color scheme to ensure a
professional, clean, and usable experience.

### 5.1. Student and Standard Interface

- **Vendor Listing Dashboard:** The main landing page displays a list
  of all available (approved and open) vendors.

- **Menu Browsing Screen:** Allows users to view a specific vendor's
  menu, organized by categories. Each item shows its name, price,
  and description, with an "Add to Cart" button.

- **Shopping Cart Interface:** Displays all selected items, allows
  for quantity updates, calculates subtotals, fees, and tax, and
  provides a prominent "Checkout" button. Transaction IDs are
  generated and displayed.

- **Order Tracking Page:** Shows the real-time status of an order
  via a visual progress bar and a summary of the order details.

- **Order History Page:** Lists all past orders, allowing the user to
  view receipts and reorder completed orders.

### 5.2. Vendor Interface

- **Menu Management Dashboard:** Provides a central place for vendors
  to add, edit, or delete menu items. A simple toggle switch allows
  them to mark items as available or unavailable.

- **Order Management Panel:** Displays a list of all incoming orders,
  categorized by status. Vendors can accept, reject, or update the
  status of each order.

- **Shop Status:** A prominent button on the vendor dashboard allows
  them to easily open or close their shop for business.

### 5.3. Administrator Interface

- **User Management Dashboard:** Allows administrators to create,
  approve, suspend, or delete user accounts, as well as change user
  roles.

- **Vendor Approval and Monitoring Panel:** Displays a list of
  vendors and their status. Administrators can approve or reject
  vendor applications and manage their shop status.

- **Reporting Dashboard:** Provides a comprehensive overview of
  system-wide statistics, including total users, orders, and revenue,
  and a list of recent orders and pending approvals.

---

## 6. Security Features

The security of the Campus Eats system is a top priority, with multiple
layers of protection implemented at all levels.

### 6.1. Authentication and Authorization

- **Password Hashing:** All user passwords are securely hashed using
  the bcrypt algorithm with a cost factor of 12.

- **Role-Based Access Control (RBAC):** The system strictly enforces
  role-based permissions for Student, Standard, Vendor, and Admin
  roles.

- **Session Management:** Secure sessions are implemented with
  HttpOnly and SameSite=Lax attributes, and use a custom session name
  (`CAMPUS_EATS_SESSION`).

- **Rate Limiting:** Login and password reset attempts are limited to
  prevent brute-force attacks.

### 6.2. Data Protection and Integrity

- **SQL Injection Prevention:** All database queries are executed
  using PDO prepared statements.

- **Cross-Site Scripting (XSS) Prevention:** All output to the HTML
  is properly escaped using `htmlspecialchars()` with the
  `ENT_QUOTES` flag.

- **Cross-Site Request Forgery (CSRF) Protection:** All state-
  changing forms are protected with robust CSRF tokens.

### 6.3. Security Headers

The system includes several HTTP security headers to further protect
users and the server.

- **Content Security Policy (CSP):** Restricts the sources of
  scripts, styles, and images.

- **X-Frame-Options: DENY:** Prevents the site from being embedded in
  a frame, mitigating clickjacking attacks.

- **X-Content-Type-Options: nosniff:** Prevents the browser from
  MIME-sniffing a response.

- **Referrer-Policy: strict-origin-when-cross-origin:** Controls the
  amount of referrer information sent.

---

## 7. Troubleshooting

This section provides solutions to common issues that may arise during
development and usage.

### 7.1. Cannot log in after registration

**Problem:** A user has registered but cannot log in.

**Solution:** New accounts require administrator approval. The
administrator must either set the `is_verified` field to `1` for the
user in the database or approve the account through the admin control
panel (`manage_users.php`).

### 7.2. Vendor cannot accept orders

**Problem:** A vendor is logged in but cannot accept new orders.

**Solution:** Ensure all of the following conditions are met:
1.  The vendor's account is `is_verified = 1`.
2.  The vendor's profile is `is_approved = 1`.
3.  The vendor's shop status is `is_open = 1`.

### 7.3. Missing menu items on student browsing page

**Problem:** A student cannot see a vendor's menu items.

**Solution:** Verify the following for the vendor and their items:
1.  The vendor is `is_approved = 1`.
2.  The vendor's shop is `is_open = 1`.
3.  The individual menu item is `is_available = 1`.
4.  The menu item has a `quantity_available` greater than 0.

### 7.4. Error 500 on API requests

**Problem:** AJAX requests to API endpoints (e.g., `process_payment.php`)
return a 500 Internal Server Error.

**Solution:** Check the PHP error log for detailed error messages.
Common causes include:
- Database connection failures (incorrect credentials in
  `database.php`).
- Syntax errors in any PHP files that have been modified.

---

## 8. The Database Structure

The database is the backbone of the Campus Eats system. It is designed
to be normalized to the Third Normal Form (3NF) to ensure data
integrity and minimal redundancy.

### 8.1. Core Entities

1.  **`users`:** Stores all user accounts (Students, Standards,
    Vendors, and Administrators). Includes fields for `unique_id`
    (16-character identifier for password recovery), `password_hash`,
    and `account_type` (admin, vendor, student, standard).

2.  **`vendors`:** Stores vendor profile information linked to a user
    account. Contains fields for `vendor_name`, `is_open` (shop
    status), and `is_approved` (administrative approval).

3.  **`menu_items`:** Stores all items offered by vendors. Includes
    fields for `item_name`, `price`, `quantity_available` (for
    inventory tracking), `category`, and `is_available`.

4.  **`orders`:** Stores order records placed by users. Includes
    comprehensive financial fields: `subtotal`, `service_fee`,
    `student_discount`, `tax`, `rounding_adjustment`, `total_amount`,
    and the unique `transaction_id` (Format: `TDYYYYMMDDHHMMSS`).

5.  **`order_items`:** Stores the individual items within an order. It
    is linked to `orders` and `menu_items`.

6.  **`payments`:** Stores payment transactions linked to a specific
    order, tracking the `payment_method`, `payment_status`, and
    `transaction_reference`.

7.  **`complaints_compliments`:** Stores user feedback (complaints and
    compliments) for administrative review. Tracks the `entry_type`,
    subject, message, and `is_resolved` status.

8.  **`login_attempts`:** Used for security rate limiting, storing
    failed login attempts by IP address and username.

### 8.2. Key Relationships

- A `users` record can be linked to one `vendors` record.
- A `vendors` record has many `menu_items`.
- A `users` (student or standard) can place many `orders`.
- An `orders` record belongs to one `vendors` and one `users`.
- An `orders` record has many `order_items`.
- An `orders` record has one `payments` record.

---

## 9. The API Endpoints

The system uses a set of API endpoints (located in
`campus-eats-web/Solution/api/`) to handle dynamic data exchange
between the frontend and the backend.

### 9.1. Cart Management

- **`get_cart.php`**: Retrieves the current user's cart contents from
  the session.
- **`update_cart.php`**: Handles adding, removing, updating
  quantities, and clearing items in the cart.

### 9.2. Menu Management

- **`get_menu_items.php`**: Returns a list of menu items for a
  specific vendor.
- **`get_menu_item.php`**: Retrieves the details of a single menu
  item for editing.
- **`add_menu_item.php`**: Allows a vendor to add a new item to their
  menu.
- **`update_menu_item.php`**: Allows a vendor to update an existing
  menu item.
- **`delete_menu_item.php`**: Allows a vendor to delete an item (only
  if it has never been ordered).

### 9.3. Order Processing

- **`get_orders.php`**: Returns the current user's order history.
- **`get_order_details.php`**: Fetches the full details of a specific
  order, including all items, for the "reorder" function.
- **`get_order_status.php`**: Returns the current status of a
  specific order for real-time tracking.
- **`process_payment.php`**: Handles the complete order placement and
  payment process, including financial calculations, stock updates,
  and transaction ID generation.
- **`vendor_respond_order.php`**: Allows a vendor to update the
  status of an order (e.g., accept, start preparing).

### 9.4. Vendor and User Management

- **`get_vendors.php`**: Returns a list of all approved and active
  vendors.

---

## 10. The Development Process

The Campus Eats project follows a structured, iterative development
process.

### 10.1. Version Control and Documentation

- **Version Control:** The source code is managed using Git. Each
  file header contains a detailed version history, making it easy to
  track changes and their rationale.

- **Documentation:** This comprehensive documentation is the central
  source of truth for the system's architecture and operation. Each
  component is documented to ensure maintainability and knowledge
  transfer.

- **Process Document:** All development is strictly aligned with the
  requirements and specifications outlined in the
  `campus-eats-process-document.pdf`.

### 10.2. Quality Assurance and Testing

- **Error Logging:** All system errors and significant events are
  logged to the `campus-eats-web/Issues/error_log.txt` file for
  debugging and analysis.

- **Code Reviews:** Code changes are reviewed to ensure they adhere
  to the established coding standards and security best practices.

- **Security Audits:** The system has been analyzed for common
  vulnerabilities, with fixes and improvements documented in each
  file's version history.

### 10.3. Key Development Principles

- **Security First:** Security is not an afterthought but a core
  component of every feature, from user authentication and input
  validation to the use of prepared statements.

- **User-Centric Design:** All interfaces are built with the user in
  mind, adhering to a minimalist design philosophy for clarity and
  ease of use.

- **Maintainability:** Code is written to be clean, well-commented,
  and modular, allowing for easy updates and future development.

---

*END OF DOCUMENT*

---