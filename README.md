# QARTIV — Smart Commerce Platform (Final Project)

> **Modern, Full-Stack PHP & MySQL E-Commerce Application with Dynamic Product Management, Real Product Photos, Cart & Checkout, Wishlist, Order Invoicing, and Admin Dashboard.**

---

## 📌 Project Overview
**Qartiv** is a responsive, feature-rich e-commerce web platform engineered using native PHP, MySQL, modern semantic HTML5, and bespoke CSS styling. It provides an intuitive storefront for customers and an administrative management suite for store owners.

---

## 🚀 Key Features

### 🛍️ Customer Experience
- **Interactive Storefront (`customer/products.php`)**: Live search, category filtering, multi-tier price slider/filter, and real-time sorting (newest, price ascending/descending, rating).
- **Product Details (`customer/product_details.php`)**: High-resolution product images, discount tags, inventory counter, customer reviews and rating breakdown, quantity selector, and instant add-to-cart/wishlist.
- **Dynamic Shopping Cart (`customer/cart.php`)**: Live item updates, real-time quantity modifiers, coupon code redemption (`WELCOME10`, `QARTIV500`, `FESTIVE20`), subtotal, discounts, and tax calculation.
- **Secure Checkout & Order Placement (`customer/checkout.php`)**: Multi-address management, Cash on Delivery (COD) & online payment simulations, order confirmation, and automated order generation.
- **Customer Dashboard & Invoicing (`customer/dashboard.php`, `customer/orders.php`, `customer/invoice.php`)**: Order tracking, complete printable invoice generation, wishlist management, and profile settings.

### 🛡️ Administrative Portal
- **Dashboard (`admin/dashboard.php`)**: Live revenue analytics, order status statistics, top-selling items, and quick management links.
- **Product Management (`admin/products.php`)**: Full CRUD (Create, Read, Update, Delete) for products with secure file upload handling (JPG, PNG, WEBP with MIME inspection), stock levels, pricing, and discount rules.
- **Category Management (`admin/categories.php`)**: Category creation, status controls, and banner image associations.
- **Coupon Manager (`admin/coupons.php`)**: Promotional discount codes (percentage & fixed discount thresholds, expiry dates).
- **Order & Customer Tracking (`admin/orders.php`, `admin/customers.php`)**: Status updates (Placed → Confirmed → Shipped → Delivered → Cancelled).

---

## 📸 Product Catalog & Image Assets
The project comes pre-seeded with 8 real, high-resolution product photos and 4 category banners located in `uploads/products/`:

| Product Name | Category | Base Price | Discount | Final Price | Stock | Image File |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **Wireless Headphones** | Electronics | ₹2,499.00 | 15% | ₹2,124.15 | 14 | `prod_headphones.jpg` |
| **Smart Watch** | Electronics | ₹3,499.00 | 10% | ₹3,149.10 | 12 | `prod_smartwatch.jpg` |
| **Classic Sneakers** | Fashion | ₹1,999.00 | 20% | ₹1,599.20 | 25 | `prod_sneakers.jpg` |
| **Premium Backpack** | Fashion | ₹1,599.00 | 12% | ₹1,407.12 | 18 | `prod_backpack.jpg` |
| **Minimal Table Lamp** | Home & Living | ₹1,299.00 | 8% | ₹1,195.08 | 7 | `prod_table_lamp.jpg` |
| **Aroma Diffuser** | Home & Living | ₹999.00 | 10% | ₹899.10 | 9 | `prod_diffuser.jpg` |
| **Daily Face Care Kit** | Beauty | ₹899.00 | 15% | ₹764.15 | 18 | `prod_skincare.jpg` |
| **Perfume Collection** | Beauty | ₹1,799.00 | 18% | ₹1,475.18 | 14 | `prod_perfume.jpg` |

### Category Banners:
- **Electronics**: `cat_electronics.jpg`
- **Fashion**: `cat_fashion.jpg`
- **Home & Living**: `cat_home.jpg`
- **Beauty**: `cat_beauty.jpg`

---

## 🔑 Login Credentials

| Role | Email | Password | Access URL |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin@qartiv.com` | *(Registered admin password)* | `http://localhost/Qartiv/auth/login.php` |
| **Customer** | `maddulamanasa485@gmail.com` | *(Registered user password)* | `http://localhost/Qartiv/auth/login.php` |

*(You can also click **Register** to create a fresh customer account with any email and password at any time).*

---

## ⚙️ Installation & Setup (WAMP / XAMPP)

1. **Copy Project Folder**:
   - Place the `Qartiv` folder in `C:\wamp64\www\Qartiv` (for WAMP) or `C:\xampp\htdocs\Qartiv` (for XAMPP).
2. **Start Services**:
   - Start Apache and MySQL in your WAMP or XAMPP Control Panel.
3. **Import Database**:
   - Open phpMyAdmin (`http://localhost/phpmyadmin`).
   - Create a new database named `qartiv` (collation `utf8mb4_unicode_ci`).
   - Click **Import** and select the file `qartiv_database.sql` from the project root.
   - Click **Go** to complete importing all 10 tables and pre-loaded records.
4. **Database Configuration**:
   - Check `config/db.php`:
     ```php
     $host = "localhost";
     $user = "root";
     $password = ""; // Default is empty for WAMP/XAMPP
     $database = "qartiv";
     ```
5. **Launch Application**:
   - Visit: `http://localhost/Qartiv/index.php` or `http://localhost/Qartiv/customer/products.php`

---

## 📁 File Structure
```
Qartiv/
├── admin/                     # Administrative management suite
│   ├── categories.php         # Category CRUD & banner settings
│   ├── coupons.php            # Promotional coupon codes
│   ├── customers.php          # User list and customer accounts
│   ├── dashboard.php          # Analytics dashboard
│   ├── orders.php             # Order lifecycle management
│   └── products.php           # Product catalog CRUD with photo uploads
├── assets/
│   ├── css/style.css          # Core stylesheet with responsive design
│   ├── images/                # Brand SVGs and UI banners
│   └── js/                    # UI script enhancements
├── auth/
│   ├── login.php              # Secure login with role redirection
│   ├── logout.php             # Session destruction
│   └── register.php           # Account creation
├── config/
│   ├── db.php                 # MySQLi database connection
│   └── helpers.php            # Image upload validator & URL resolver
├── customer/
│   ├── account.php            # Profile & password management
│   ├── cart.php               # Shopping cart & discount calculator
│   ├── checkout.php           # Address selection & order placement
│   ├── dashboard.php          # Customer portal hub
│   ├── invoice.php            # Printable PDF/HTML order invoice
│   ├── order_details.php      # Detailed items list of an order
│   ├── order_success.php      # Order receipt & confirmation
│   ├── orders.php             # Customer order history
│   ├── product_details.php    # Single product view with reviews & gallery
│   ├── products.php           # Main product catalog with filters & search
│   └── wishlist.php           # Saved customer wishlist
├── uploads/
│   ├── invoices/              # Generated invoice archives
│   └── products/              # Stored high-res product photos & category banners
├── index.php                  # Landing page & featured collections
├── qartiv_database.sql        # Complete SQL database dump (tables + seed data)
└── README.md                  # Project documentation
```
