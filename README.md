# PortalSite - Employee Portal & User Management System

A comprehensive web-based employee portal built with PHP and MySQL, featuring role-based access control and complete user management functionality.

## 🚀 Features

- **User Authentication**: Secure login system with password hashing
- **Role-Based Access Control**: Multiple user roles (Admin, Project Manager, Estimator, etc.)
- **User Management**:
  - Add new users
  - Edit user details
  - Remove users
  - Reset user passwords
  - Search and filter users
- **Admin Dashboard**: Intuitive tile-based navigation
- **Responsive Design**: Modern CSS with gradients and animations
- **Security**: Prepared statements, password validation, session management

## 🛠️ Technologies Used

- **Backend**: PHP 8.x
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript
- **Server**: Apache (XAMPP)

## 📋 Requirements

- PHP 8.0 or higher
- MySQL 5.7 or higher
- Apache web server
- Modern web browser

## 🔧 Installation

### 1. Clone the repository

```bash
git clone https://github.com/dh-web-admin/portalsite.git
cd portalsite
```

### 2. Set up the database

```sql
CREATE DATABASE dhdatabase;
USE dhdatabase;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create default admin user (password: Admin123!)
INSERT INTO users (name, email, password, role)
VALUES ('Admin', 'admin@example.com', '$2y$10$example_hash_here', 'admin');
```

### 3. Configure database connection

```bash
# Copy the example config file
cp config/config.example.php config/config.php

# Edit config.php with your database credentials
```

Update `config/config.php`:

```php
$host = 'localhost';
$user = 'your_db_username';
$password = 'your_db_password';
$database = 'dhdatabase';
```

### 4. Set up web server

**XAMPP**

```bash
# Place project in: C:\xampp\htdocs\PortalSite\
# Access via: http://localhost/PortalSite/
```

## 📁 Project Structure

```
PortalSite/
├── admin/                  # Admin-only pages
│   ├── dashboard.php       # Main dashboard
│   ├── user_list.php       # View/manage users
│   ├── register_new.php    # Add new users
│   ├── edit_user.php       # Edit user details
│   └── remove_user.php     # Delete users
├── auth/                   # Authentication
│   ├── login.php           # Login page
│   ├── login_register.php  # Login handler
│   └── forgot_password.php # Password recovery
├── api/                    # AJAX endpoints
│   ├── update_user.php
│   └── update_user_password.php
├── config/                 # Configuration
│   ├── config.php          # Database config (not in repo)
│   └── config.example.php  # Config template
├── partials/               # Reusable components
│   ├── portalheader.php
│   └── admin_sidebar.php
├── assets/                 # Static files
│   ├── css/               # Stylesheets
│   └── images/            # Images and icons
└── index.php              # Entry point
```

## 🔐 Security Features

- ✅ Password hashing with `password_hash()`
- ✅ Prepared statements (SQL injection protection)
- ✅ Session-based authentication
- ✅ Role-based access control
- ✅ Password strength validation
- ✅ XSS protection with `htmlspecialchars()`

### Password Requirements

- Minimum 8 characters
- At least 1 number
- At least 1 uppercase letter
- At least 1 special character

## 👥 User Roles

- **Admin**: Full system access
- **Project Manager**: (Future implementation)
- **Estimator**: (Future implementation)
- **Accounting**: (Future implementation)
- **Superintendent**: (Future implementation)
- **Foreman**: (Future implementation)
- **Mechanic**: (Future implementation)
- **Operator**: (Future implementation)
- **Laborer**: (Future implementation)

## 🎨 CSS Organization

The project uses modular CSS files for better maintainability:

- `base.css` - Global styles and utilities
- `admin-layout.css` - Admin page layout
- `dashboard.css` - Dashboard tiles
- `user-list.css` - User list table and modals
- `register-user.css` - User registration form
- `edit-user.css` - User edit form
- `remove-user.css` - User removal page
- `login.css` - Login page
- `forgot-password.css` - Password recovery page

See [CSS_ORGANIZATION.md](CSS_ORGANIZATION.md) for details.

## 📖 Documentation

- [PHP Organization Guide](PHP_ORGANIZATION.md)
- [CSS Organization Guide](CSS_ORGANIZATION.md)

## ⚠️ Important Notes

- Never commit `config/config.php` to version control
- Always use environment-specific configuration
- Keep your database credentials secure
- Regularly backup your database

---

**Last Updated**: October 24, 2025
**Version**: 1.0.0
