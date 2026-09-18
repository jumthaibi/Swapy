# Swapy

A PHP and MySQL item-swapping marketplace, built as a software engineering course project. Users list items they own and swap them with other users, with support staff and delivery roles built into the platform.

## Features

- **Customer** — browse items, chat with other users, manage a profile, and arrange swaps
- **Admin (AdminCM)** — manage the platform's content and users
- **Customer Support (CustSupport)** — handle support requests
- **Delivery** — manage delivery of swapped items
- In-app chat between users, including image sharing
- Profile pictures and item images stored and served through dedicated image handlers (`image.php`, `image_storage.php`)
- Role-based login and access control (`auth.php`)

## Screenshots

![Swapy screenshot](screenshot.png)

## Tech Stack

- PHP (MySQLi)
- MySQL / MariaDB
- HTML, CSS, JavaScript

## Running It Locally

This project needs a local PHP + MySQL server, such as **XAMPP**, **WAMP**, or **MAMP**.

1. **Install a local server stack** if you don't have one — [XAMPP](https://www.apachefriends.org/) is a simple option.
2. **Copy the project folder** into your server's web root:
   - XAMPP: `htdocs/Swapy`
3. **Create the database:**
   - Open phpMyAdmin (or the MySQL CLI) and create a database named `swapy_db`.
   - Import `database/swapy_db.sql` into it.
4. **Check the database credentials** in `db.php` — by default it expects:
   - Host: `localhost`, User: `root`, Password: *(empty)*
   - Update these if your local MySQL setup differs.
5. **Start Apache and MySQL** from your server stack's control panel.
6. **Open the site** in your browser:
   - `http://localhost/Swapy/index.php`
   - Role-specific areas are under `AdminCM/`, `CustSupport/`, and `delivery/`.

## Notes

- `database/portal accounts.pdf` documents the different login roles and their credentials — check it for test accounts.
- The `customer/uploads/` folder holds sample profile/item photos used to demonstrate the swap and chat features.
