# Remember Me Feature - Railway Database Migration

## Important: Run this on Railway Production Database

The remember me feature requires adding new columns to the users table.

### Option 1: Run via Railway CLI

```bash
railway run php database/migrate_remember_token.php
```

### Option 2: Run SQL directly on Railway MySQL

Connect to your Railway MySQL database and run:

```sql
ALTER TABLE users
ADD COLUMN remember_token VARCHAR(64) NULL DEFAULT NULL,
ADD COLUMN remember_token_expires DATETIME NULL DEFAULT NULL,
ADD INDEX idx_remember_token (remember_token);
```

### Option 3: Access via Railway Dashboard

1. Go to Railway Dashboard
2. Select your MySQL service
3. Go to "Data" tab
4. Run the SQL from Option 2

---

**Note:** The migration has already been run on the local XAMPP database.
You must run it on Railway for the production site to work with Remember Me.
