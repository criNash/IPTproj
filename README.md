# Budget Supreme

A web-based personal budget management system that helps users track their income, expenses, and financial goals with a dedicated admin panel for user management.

## Project Description

Budget Supreme is a web application designed to help individuals take control of their personal finances. Users can register, log in, manage multiple wallet/bank accounts, record transactions, set spending budgets per category, and visualize their financial data through interactive graphs. An admin panel allows administrators to monitor registered clients and platform statistics.


## Features

### Client Side
- User Registration & Login — Secure sign-up with username, email, and contact info
- Forgot Password / Account Recovery — Security question + recovery PIN system
- Dashboard — Overview of financial status
- Multiple Accounts — Create and manage multiple wallet/bank accounts with custom icons and balances
- Transaction Management — Log income and expense transactions by category, account, date, and note; filter by month
- Custom Categories — Create personalized income/expense categories with emoji icons
- Budget Planner — Set spending limits per category with daily, weekly, monthly, or yearly periods
- Financial Graphs — Visual charts for income vs. expense trends
- Notifications — Smart alerts for budget warnings and spending updates
- Export Transactions — Download transaction history as a report
- Import Transactions — Bulk upload transactions via file
- Profile & Account Settings — Update personal info and change password

### Admin Side
- Admin Login — Separate secured admin portal
- Dashboard — View total admins, total clients, and new registrations today
- Client List — Browse, view, edit, and delete client accounts
- Statistics — Platform-wide usage stats and graphs
- Admin Profile Settings — Manage admin account and password
- Create Admin — Add new admin users


## Technologies Used

- Frontend: HTML, CSS, JavaScript
- Backend: PHP
- Database: MySQL
- Fonts: Google Fonts
- Hosting: ByetHost free web hosting
- Database Tool: phpMyAdmin


## Setup and Installation

### Prerequisites
- A web server with PHP support (XAMPP or ByetHost)
- MySQL database
- A browser

### Option A — Local Setup (XAMPP)

1. Download or clone this repository and place the FinalProject folder inside your server root:
   - XAMPP: C:/xampp/htdocs/FinalProject
   - WAMP: C:/wamp64/www/FinalProject

2. Open phpMyAdmin at http://localhost/phpmyadmin, create a new database (e.g. budget_supreme), then import the file: client/finals.sql

3. Open both admin/dbconnect.php and client/dbconnect.php and update the credentials:
   $conn = mysqli_connect("localhost", "root", "", "budget_supreme");

4. Access the app:
   - Client portal: http://localhost/FinalProject/client/index.php
   - Admin portal: http://localhost/FinalProject/admin/index.php

### Option B — ByetHost Deployment

1. Sign up at https://byet.host and create a free hosting account.

2. Upload the contents of FinalProject/ into your htdocs folder via File Manager.

3. In the ByetHost control panel, go to MySQL Databases and create a new database. Take note of the host, database name, username, and password.

4. Open phpMyAdmin from the ByetHost panel, select your database, and import client/finals.sql.

5. Update both admin/dbconnect.php and client/dbconnect.php with your ByetHost database credentials:
   $conn = mysqli_connect("sql_host", "db_username", "db_password", "db_name");

6. Access your live site:
   - Client portal: http://your-domain.byethost.com/client/index.php
   - Admin portal: http://your-domain.byethost.com/admin/index.php

