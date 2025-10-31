# PHP File Organization - PortalSite

## Overview

The PortalSite project has been organized into a logical folder structure that separates concerns and improves maintainability. This document outlines the complete PHP file organization.

## Project Structure

```
PortalSite/
├── index.php                          # Front controller: redirects to login (serves health if matched)
├── session_init.php                   # Centralized session + remember-me auto-login
├── home.html                          # (Legacy file - consider removing)
│
├── admin/                             # Admin-only pages
│   ├── user_list.php                  # View users, edit roles, reset passwords
│   ├── register_new.php               # Add new users
│   ├── edit_user.php                  # Edit existing user details
│   └── remove_user.php                # Delete users
│
├── pages/                             # Main app pages (all roles land on dashboard)
│   ├── dashboard.php
│   ├── equipment.php | Bid_tracking.php | scheduling.php | engineering.php | ...
│   └── _template.php                  # Base scaffold for new pages
│
├── auth/                              # Authentication pages
│   ├── login.php                      # Login form
│   ├── login_register.php             # Login handler (issues 12h remember token)
│   ├── logout.php                     # Logout confirmation (clears token)
│   └── forgot_password.php            # Password recovery page (UI)
│
├── api/                               # API endpoints for AJAX calls
│   ├── update_user.php                # Update user details (name, role)
│   └── update_user_password.php       # Update user password (admin function)
│
├── config/                            # Configuration files
│   ├── config.php                     # Database connection settings
│   ├── config.example.php             # Example template
│   └── config.railway.php             # Railway-specific config
│
├── partials/                          # Reusable PHP components
│   ├── portalheader.php               # Header with logo (used on admin/pages)
│   ├── sidebar.php                    # Side navigation (role-aware)
│   ├── url.php                        # base_url helper for robust links/assets
│   └── permissions.php                # Role access helpers (can_access, etc.)
│
├── debug/                             # Admin-only diagnostics (guarded)
│   ├── pages_health.php | debug_session.php | debug_page_load.php | health.php
│
├── assets/                            # Static assets
│   ├── css/                           # Stylesheets (see assets/css/.CSS_ORGANIZATION.md)
│   │   ├── base.css | admin-layout.css | dashboard.css | login.css | ...
│   ├── js/
│   │   └── mobile-menu.js             # Hamburger/overlay for mobile sidebar
│   └── images/
│       ├── logo.svg | eportal.svg | maintenance.png
│
└── docs/
  ├── Readme-Portal-Organization.md  # This document
  └── RESPONSIVE_DESIGN.md           # Responsive approach details
```

## File Descriptions

### 📁 Root Level

#### `index.php`

- **Purpose**: Entry point for the application
- **Functionality**: Redirects all traffic to `/PortalSite/auth/login.php`
- **Access**: Public
- **Dependencies**: None

---

### 📁 admin/ (Admin-Only Pages)

All files in this directory require:

- Active session (`$_SESSION['email']`, `$_SESSION['name']`)
- Admin role verification
- Include: `../config/config.php`
- Include: `../partials/portalheader.php`
- Include: `../partials/sidebar.php`

#### `user_list.php`

- **Purpose**: View and manage all users
- **Features**:
  - Searchable user table
  - Edit user details (inline)
  - Reset user passwords (popup modal)
  - Delete users
  - Password show/hide toggles
- **AJAX Calls**:
  - `../api/update_user.php` (edit user)
  - `../api/update_user_password.php` (reset password)
- **CSS**: base.css, admin-layout.css, user-list.css
- **Security**: Password validation (8+ chars, 1 number, 1 uppercase, 1 special)

#### `register_new.php`

- **Purpose**: Add new users to the system
- **Features**:
  - User registration form (name, email, password, role)
  - Password validation with hints
  - Role selection dropdown
  - Success/error messages
- **Database**: Inserts into `users` table with hashed password
- **CSS**: base.css, admin-layout.css, register-user.css
- **Validation**:
  - Password: 8+ chars, 1 number, 1 uppercase, 1 special
  - Email uniqueness check

#### `edit_user.php`

- **Purpose**: Edit existing user information
- **URL Parameter**: `?id=<user_id>`
- **Features**:
  - Edit user name
  - Change user role
  - Email displayed but not editable
- **Database**: Updates `users` table
- **CSS**: base.css, admin-layout.css, edit-user.css
- **Back Link**: Returns to user_list.php

#### `remove_user.php`

- **Purpose**: Delete users from the system
- **Features**:
  - User selection dropdown
  - Confirmation required
  - Success/error messages
- **Database**: Deletes from `users` table
- **CSS**: base.css, admin-layout.css, remove-user.css
- **Security**: Prevents self-deletion

---

### 📁 pages/ (Application Pages)

All files in this directory require:

- Active session (`$_SESSION['email']`, `$_SESSION['name']`)
- Include: `../config/config.php`
- Include: `../partials/portalheader.php`
- Include: `../partials/sidebar.php`
- May include: `../partials/permissions.php` for per-page access guards

#### `dashboard.php`

- Purpose: Main landing page for all roles
- Features:
  - Role-aware tiles (shows only pages available to the user’s role)
  - Responsive grid (3 → 2 → 1 columns)
- CSS: base.css, admin-layout.css, dashboard.css
- Redirects: to `auth/login.php` if not authenticated

#### Other content pages (e.g., `equipment.php`, `forms.php`, ...)

- Purpose: Placeholder content with maintenance image for now
- Access: Enforced via `partials/permissions.php`
- CSS: base.css, admin-layout.css, dashboard.css
- Assets: Uses `base_url()` for robust image and asset linking

---

### 📁 auth/ (Authentication Pages)

#### `login.php`

- **Purpose**: User login interface
- **Features**:
  - Email/password input
  - Error message display
  - Link to contact admin for password reset
- **Form Action**: `login_register.php`
- **CSS**: base.css, login.css
- **Access**: Public
- **Session**: Displays error from `$_SESSION['login_error']`

#### `login_register.php`

- Purpose: Authentication handler
- Functionality:
  - Verifies email and password using `password_verify()`
  - Regenerates session ID on login
  - Sets session variables on success
  - Issues a secure HttpOnly `remember_token` cookie (12 hours)
  - Stores token + expiry in DB for auto-login
- Redirects:
  - Success (all roles): `../pages/dashboard.php`
  - Failure: Back to `login.php` with error message
- Database: Queries `users` table
- Security: Prepared statements, password hashing

#### `forgot_password.php`

- **Purpose**: Password recovery interface (UI only)
- **Features**:
  - Email input for password reset request
  - Back to login link
- **Status**: Frontend only - backend email functionality not implemented
- **CSS**: base.css, forgot-password.css
- **Future**: Needs email sending logic and token generation

#### `logout.php`

- Purpose: Explicit logout with confirmation page
- Functionality:
  - Clears `remember_token` from DB
  - Clears cookie, destroys session
  - Shows confirmation UI with links back to login/site
- CSS: base.css (page uses inline layout styles)

---

### 📁 api/ (AJAX Endpoints)

#### `update_user.php`

- **Purpose**: Handle user detail updates via AJAX
- **Method**: POST
- **Input**: JSON (`userId`, `name`, `role`)
- **Output**: JSON (`success`, `message`)
- **Features**:
  - Updates user name and/or role
  - Validates input
  - Returns JSON response
- **Security**:
  - Session check
  - Admin role verification
  - Prepared statements
- **Called by**: `user_list.php` (AJAX fetch)

#### `update_user_password.php`

- **Purpose**: Handle admin password reset via AJAX
- **Method**: POST
- **Input**: JSON (`userId`, `newPassword`)
- **Output**: JSON (`success`, `message`)
- **Features**:
  - Server-side password validation
  - Password hashing with `password_hash()`
  - Returns JSON response
- **Validation**: 8+ chars, 1 number, 1 uppercase, 1 special character
- **Security**:
  - Session check
  - Admin role verification
  - Prepared statements
- **Called by**: `user_list.php` (password reset popup)

---

### 📁 config/

#### `config.php`

- **Purpose**: Database configuration and connection
- **Contains**:
  - Database credentials (host, username, password, database name)
  - MySQLi connection object (`$conn`)
  - Connection error handling
- **Usage**: Included by all files needing database access
- **Security**: Should be in `.gitignore` (contains sensitive data)

---

### 📁 partials/ (Reusable Components)

#### `portalheader.php`

- **Purpose**: Standard header for admin pages
- **Features**:
  - Displays welcome message
  - Shows logged-in user name
  - Portal logo
- **Used by**: All admin pages
- **CSS Classes**: `.welcome-section`, `.welcome-left`, `.welcome-logo`
- **Assets**: `/PortalSite/assets/images/eportal.svg`

#### `sidebar.php`

- **Purpose**: Navigation sidebar for admin pages
- **Features**:
  - Navigation menu with sections
  - "Manage Users" group visible only to admins
  - Logout button pinned to bottom
- **Links**:
  - Dashboard: `../pages/dashboard.php`
  - Users (admin only): `../admin/user_list.php`, `../admin/register_new.php`, `../admin/remove_user.php`
  - Logout: `../auth/logout.php`
- **JavaScript**: Toggle functionality for expandable sections
- **CSS Classes**: `.side-nav`, `.nav-btn`, `.nav-group`, `.logout-btn`

#### `url.php`

- **Purpose**: Environment-aware URL builder
- **Function**: `base_url($path)` returns a path rooted at the site base (local or prod)
- **Usage**: Prefer for links and asset URLs to avoid broken paths

#### `permissions.php`

- **Purpose**: Role-based access control helpers
- **Functions**: `allowed_pages_for_role($role)`, `can_access($role, $page)`
- **Usage**: Import in each `pages/*.php` and guard as needed

---

### 📁 assets/

#### assets/css/

See `assets/css/.CSS_ORGANIZATION.md` for detailed CSS file structure and mapping.

#### assets/images/

- `logo.svg` - Company logo (used on login page)
- `eportal.svg` - Portal header logo (used on admin pages)

---

## File Dependencies

### Database-Dependent Files:

```
config/config.php
  ├── admin/dashboard.php
  ├── admin/user_list.php
  ├── admin/register_new.php
  ├── admin/edit_user.php
  ├── admin/remove_user.php
  ├── auth/login_register.php
  ├── api/update_user.php
  └── api/update_user_password.php
```

### Session-Dependent Files:

All files in `admin/` and `api/` directories require active sessions

### Include Dependencies:

```
admin/dashboard.php
  ├── includes: ../config/config.php
  ├── includes: ../partials/portalheader.php
  └── includes: ../partials/admin_sidebar.php

admin/user_list.php
  ├── includes: ../config/config.php
  ├── includes: ../partials/portalheader.php
  └── includes: ../partials/admin_sidebar.php

(Same pattern for all admin/*.php files)
```

---

## Authentication Flow

```
1. User visits: http://localhost/PortalSite/
   └── index.php redirects to auth/login.php

2. User enters credentials in login.php
   └── Form submits to auth/login_register.php

3. login_register.php validates credentials
  ├── On success → pages/dashboard.php (all roles)
  ├── Issues a 12-hour remember token (HttpOnly cookie) and stores it in DB
  └── On failure → back to login.php with error

4. Admin navigates via dashboard tiles or sidebar
   └── All admin pages verify session + admin role

5. User logs out
  └── auth/logout.php → clears DB token + cookie → destroys session → confirmation page
```

---

## User Management Flow

### View Users:

```
admin/dashboard.php → "User List" tile
  └── admin/user_list.php
      ├── Displays all users in table
      ├── Search functionality (client-side)
      └── Action buttons per user
```

### Edit User:

```
admin/user_list.php → "Edit" button
  └── AJAX POST to api/update_user.php
      └── Updates database
      └── Returns JSON response
      └── Updates UI without page reload
```

### Reset Password:

```
admin/user_list.php → "Reset Password" button
  └── Opens popup modal
      └── Admin enters new password
      └── AJAX POST to api/update_user_password.php
          └── Validates password
          └── Updates database with hash
          └── Returns JSON response
```

### Add User:

```
admin/dashboard.php → "Add User" tile
  └── admin/register_new.php
      └── Form submission (POST to self)
          └── Validates input
          └── Hashes password
          └── Inserts into database
          └── Shows success message
```

### Remove User:

```
admin/dashboard.php → "Remove User" tile
  └── admin/remove_user.php
      └── Select user from dropdown
      └── Form submission (POST to self)
          └── Deletes from database
          └── Shows confirmation
```

---

## Security Features

### Authentication:

- ✅ Session-based authentication
- ✅ Password hashing with `password_hash()` and `password_verify()`
- ✅ Role-based access control (admin verification)
- ✅ Session checks on all protected pages

### Database:

- ✅ Prepared statements (prevents SQL injection)
- ✅ Parameterized queries
- ✅ MySQLi with `bind_param()`

### Input Validation:

- ✅ Server-side password validation
- ✅ Email format validation
- ✅ Client-side validation (JavaScript)
- ✅ HTML special character escaping with `htmlspecialchars()`

### Password Requirements:

- Minimum 8 characters
- At least 1 number
- At least 1 uppercase letter
- At least 1 special character (!@#$%^&\*()\_+-=[]{}|;:,.<>?)

---

## URL and Include Conventions

### base_url helper (for links/assets)

Use `partials/url.php` and call `base_url('/path')` for robust URLs across environments.

```php
<link rel="stylesheet" href="<?php echo htmlspecialchars(base_url('/assets/css/base.css')); ?>">
<img src="<?php echo htmlspecialchars(base_url('/assets/images/maintenance.png')); ?>" alt="...">
```

### Relative includes with **DIR** (for PHP includes)

```php
// From admin/*.php or pages/*.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../partials/portalheader.php';
require_once __DIR__ . '/../partials/sidebar.php';
```

---

## Database Schema

### `users` Table:

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,  -- hashed with password_hash()
    role VARCHAR(50) NOT NULL,       -- admin, projectmanager, estimator, etc.
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Supported Roles:

- `admin` - Full access to all features
- `projectmanager` - (Future implementation)
- `estimator` - (Future implementation)
- `accounting` - (Future implementation)
- `superintendent` - (Future implementation)
- `foreman` - (Future implementation)
- `mechanic` - (Future implementation)
- `operator` - (Future implementation)
- `laborer` - (Future implementation)

---

## Maintenance Notes

### When Adding a New Admin Page:

1. Create file in `admin/` directory
2. Copy session/auth checks from existing admin file
3. Include: `../config/config.php`
4. Include: `../partials/portalheader.php`
5. Include: `../partials/admin_sidebar.php`
6. Create page-specific CSS in `assets/css/`
7. Link CSS: base.css, admin-layout.css, [page-specific].css
8. Add navigation link to `partials/admin_sidebar.php`
9. Add tile to `admin/dashboard.php` if needed

### When Adding a New API Endpoint:

1. Create file in `api/` directory
2. Verify session and admin role at top
3. Include: `../config/config.php`
4. Accept JSON input: `json_decode(file_get_contents('php://input'))`
5. Return JSON: `header('Content-Type: application/json')` + `echo json_encode()`
6. Use prepared statements for all database queries

### When Adding a New Role:

1. Add role to dropdown in `admin/register_new.php`
2. Add role to dropdown in `admin/edit_user.php`
3. Create role-specific dashboard in `dashboards/` folder
4. Update redirect logic in `auth/login_register.php`
5. Update role verification in protected pages

---

**Last Updated**: October 30, 2025
**Version**: 1.1
**Maintained by**: Samip Kafle / dh-web-admin
