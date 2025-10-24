# Railway.app Deployment Guide - PortalSite

Complete guide to deploy your PortalSite to Railway.app

---

## 🎯 What is Railway?

Railway.app is a modern cloud platform that:

- ✅ Deploys directly from GitHub
- ✅ Provides free MySQL database
- ✅ Auto-deploys on every git push
- ✅ Free $5 credit monthly (enough for small projects)
- ✅ Easy setup, no server management needed

---

## 📋 Prerequisites

- ✅ GitHub account with your code (you have this!)
- ✅ Railway account (we'll create)
- ✅ Your database SQL dump (to import data)

---

## 🚀 Step-by-Step Deployment

### Step 1: Create Railway Account

1. Go to **https://railway.app**
2. Click **"Start a New Project"**
3. Click **"Login with GitHub"**
4. Authorize Railway to access your GitHub repositories
5. You'll be redirected to Railway dashboard

**Cost:** Free tier includes $5/month credit (resets monthly)

---

### Step 2: Create New Project from GitHub

1. On Railway dashboard, click **"New Project"**
2. Select **"Deploy from GitHub repo"**
3. Choose: **`dh-web-admin/portalsite`**
4. Railway will:
   - Clone your repository
   - Detect it's a PHP project
   - Start building automatically

**Wait 2-3 minutes for initial deployment**

---

### Step 3: Add MySQL Database

Your portal needs a database:

1. In your project, click **"+ New"** button
2. Select **"Database"**
3. Choose **"Add MySQL"**
4. Railway creates a MySQL instance automatically

**Database is now ready!**

---

### Step 4: Get Database Credentials

1. Click on your **MySQL** service (the database icon)
2. Go to **"Connect"** tab
3. You'll see credentials like:

```
MYSQL_HOST: mysql.railway.internal
MYSQL_PORT: 3306
MYSQL_USER: root
MYSQL_PASSWORD: [auto-generated]
MYSQL_DATABASE: railway
```

**Copy these values - you'll need them!**

---

### Step 5: Configure Environment Variables

Tell your PHP app how to connect to the database:

1. Click on your **portalsite service** (the PHP app)
2. Go to **"Variables"** tab
3. Click **"+ New Variable"**
4. Add these one by one:

```
Variable Name: DB_HOST
Value: mysql.railway.internal

Variable Name: DB_USER
Value: root

Variable Name: DB_PASSWORD
Value: [paste the MySQL password from Step 4]

Variable Name: DB_NAME
Value: railway

Variable Name: DB_PORT
Value: 3306

Variable Name: RAILWAY_ENVIRONMENT
Value: production
```

**Click "Add" after each variable**

---

### Step 6: Update Your config.php

**Option A: Use the Railway-Compatible Config (Recommended)**

Replace your `config/config.php` with the new version:

```bash
# In VS Code terminal
cp config/config.railway.php config/config.php
```

This new config:

- Uses environment variables on Railway (production)
- Uses localhost on your computer (development)
- Works in both environments automatically

**Option B: Manually Update config.php**

Edit `config/config.php` to use environment variables:

```php
<?php
$isProduction = getenv('RAILWAY_ENVIRONMENT') !== false;

if ($isProduction) {
    // Railway production
    $host = getenv('DB_HOST');
    $user = getenv('DB_USER');
    $password = getenv('DB_PASSWORD');
    $database = getenv('DB_NAME');
} else {
    // Local development
    $host = 'localhost';
    $user = 'root';
    $password = '';
    $database = 'dhdatabase';
}

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>
```

---

### Step 7: Push Updated Config to GitHub

```bash
# Stage the changes
git add config/config.php

# Commit
git commit -m "Update config for Railway deployment"

# Push to GitHub
git push
```

**Railway will automatically redeploy!**

---

### Step 8: Import Your Database

You need to import your users table and data:

**Method 1: Using Railway CLI (Recommended)**

1. Install Railway CLI:

   ```bash
   # Windows (PowerShell as Admin)
   iwr https://railway.app/install.ps1 | iex
   ```

2. Login to Railway:

   ```bash
   railway login
   ```

3. Link to your project:

   ```bash
   railway link
   # Select your portalsite project
   ```

4. Connect to MySQL:

   ```bash
   railway connect mysql
   ```

5. Import your database:

   ```sql
   CREATE TABLE IF NOT EXISTS users (
       id INT AUTO_INCREMENT PRIMARY KEY,
       name VARCHAR(255) NOT NULL,
       email VARCHAR(255) UNIQUE NOT NULL,
       password VARCHAR(255) NOT NULL,
       role VARCHAR(50) NOT NULL,
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
   );
   ```

6. Create admin user:
   ```sql
   INSERT INTO users (name, email, password, role)
   VALUES ('Admin', 'admin@darkhorse.com', '$2y$10$YourHashedPasswordHere', 'admin');
   ```

**Method 2: Using Railway Dashboard**

1. Click on **MySQL service**
2. Go to **"Data"** tab
3. Click **"Query"**
4. Paste and run your SQL commands

**Method 3: Export from Local, Import to Railway**

1. Export from local XAMPP:

   ```bash
   # In XAMPP, export dhdatabase as SQL file
   mysqldump -u root dhdatabase > portalsite_backup.sql
   ```

2. Use Railway CLI to import:
   ```bash
   railway connect mysql < portalsite_backup.sql
   ```

---

### Step 9: Generate Public URL

1. Click on your **portalsite service**
2. Go to **"Settings"** tab
3. Scroll to **"Networking"** section
4. Click **"Generate Domain"**
5. Railway will generate a URL like:
   ```
   https://portalsite-production-xxxx.up.railway.app
   ```

**This is your live site URL!**

---

### Step 10: Test Your Deployment

1. Visit your Railway URL
2. You should see your login page
3. Try logging in with your admin credentials
4. Test all features:
   - User list
   - Add user
   - Edit user
   - Remove user
   - Dashboard navigation

---

## 🔄 Auto-Deploy Setup

Railway automatically deploys when you push to GitHub!

**Your workflow now:**

```bash
# Make changes locally
# Edit files in VS Code

# Test locally
# http://localhost/PortalSite

# Push to GitHub
git add .
git commit -m "Added new feature"
git push

# Railway automatically deploys! 🎉
# Wait 1-2 minutes, then check your Railway URL
```

---

## ⚙️ Railway Configuration File (Optional)

Create `railway.json` in your project root for custom settings:

```json
{
  "$schema": "https://railway.app/railway.schema.json",
  "build": {
    "builder": "NIXPACKS"
  },
  "deploy": {
    "startCommand": "php -S 0.0.0.0:$PORT -t .",
    "healthcheckPath": "/",
    "healthcheckTimeout": 100,
    "restartPolicyType": "ON_FAILURE",
    "restartPolicyMaxRetries": 10
  }
}
```

---

## 📊 Monitoring Your App

### Check Deployment Status

1. Go to your Railway dashboard
2. Click on **portalsite service**
3. Go to **"Deployments"** tab
4. See all deployments, logs, and status

### View Logs

1. Click on **portalsite service**
2. Go to **"Logs"** tab
3. See real-time application logs
4. Useful for debugging errors

### Monitor Usage

1. Go to **"Usage"** tab
2. See:
   - CPU usage
   - Memory usage
   - Network traffic
   - Monthly cost

---

## 💰 Cost Estimate

**Railway Free Tier:**

- $5 free credit per month
- Typical small PHP app: ~$3-4/month
- MySQL database: ~$1-2/month

**For PortalSite (estimated):**

- Small team (< 50 users): **FREE** (within $5 credit)
- Medium team (50-200 users): ~$8-12/month
- Large team (200+ users): ~$15-25/month

**First month is essentially free with trial credit!**

---

## 🔒 Security Best Practices

### 1. Update Session Settings

Add to your config.php:

```php
// Production session security
if ($isProduction) {
    ini_set('session.cookie_secure', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Strict');
}
```

### 2. Hide Errors in Production

```php
if ($isProduction) {
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    error_reporting(E_ALL);
}
```

### 3. Use HTTPS

Railway automatically provides HTTPS for your domain!

### 4. Regular Backups

1. Click on **MySQL service**
2. Go to **"Backups"** tab
3. Railway creates automatic backups
4. You can restore anytime

---

## 🐛 Troubleshooting

### Issue: "Connection failed"

**Solution:**

- Check environment variables are set correctly
- Verify DB_HOST is `mysql.railway.internal`
- Check MySQL service is running

### Issue: "Page not found"

**Solution:**

- Check deployment logs for errors
- Verify all files pushed to GitHub
- Check `railway.json` start command

### Issue: "Session not working"

**Solution:**

- Add session path in config:
  ```php
  session_save_path('/tmp');
  ```

### Issue: "Images not loading"

**Solution:**

- Check file paths use relative paths
- Verify images pushed to GitHub
- Check assets folder permissions

### Issue: "Database empty"

**Solution:**

- Import database using Railway CLI
- Or use Query tab in MySQL service
- Check connection to correct database

---

## 🔄 CI/CD Pipeline

Railway provides automatic CI/CD:

```
Local Development
       ↓
    git push
       ↓
    GitHub
       ↓
Railway Auto-Detects Push
       ↓
  Builds Project
       ↓
  Runs Tests (if configured)
       ↓
    Deploys
       ↓
 Live on Railway! 🎉
```

**Time:** Usually 1-3 minutes from push to live!

---

## 📱 Custom Domain (Optional)

### Add Your Own Domain

1. Click on **portalsite service**
2. Go to **"Settings"** → **"Networking"**
3. Click **"Custom Domain"**
4. Add your domain: `portal.yourcompany.com`
5. Update your DNS:
   ```
   Type: CNAME
   Name: portal
   Value: [Railway provides this]
   ```

**Railway handles SSL automatically!**

---

## 🔧 Railway CLI Commands

```bash
# Install Railway CLI
iwr https://railway.app/install.ps1 | iex

# Login
railway login

# Link project
railway link

# View logs
railway logs

# Connect to database
railway connect mysql

# Open dashboard
railway open

# Deploy manually
railway up

# Run command on Railway
railway run php artisan migrate

# View environment variables
railway variables
```

---

## 📊 Comparison: Railway vs Traditional Hosting

| Feature                 | Railway      | Traditional cPanel |
| ----------------------- | ------------ | ------------------ |
| Setup Time              | 5 minutes    | 1-2 hours          |
| Auto-deploy from GitHub | ✅ Yes       | ❌ Manual FTP      |
| Free SSL                | ✅ Automatic | 💰 Extra cost      |
| Database Included       | ✅ Yes       | ✅ Yes             |
| Scalability             | ✅ Easy      | ❌ Manual          |
| Free Tier               | ✅ $5/month  | ❌ $5-10/month     |
| Backups                 | ✅ Automatic | ⚠️ Manual          |
| Deployment              | 🚀 git push  | 😓 FTP upload      |

---

## ✅ Deployment Checklist

Before going live:

- [ ] Code pushed to GitHub
- [ ] Railway project created
- [ ] MySQL database added
- [ ] Environment variables configured
- [ ] config.php updated for production
- [ ] Database imported with users table
- [ ] Admin user created
- [ ] Domain generated
- [ ] Site tested (login, user management)
- [ ] SSL working (automatic)
- [ ] Logs checked for errors

---

## 🎓 Summary

**To Deploy:**

1. Create Railway account with GitHub
2. New Project → Deploy from GitHub
3. Add MySQL database
4. Set environment variables
5. Update config.php
6. Push to GitHub
7. Import database
8. Generate domain
9. Test your site!

**To Update:**

```bash
git add .
git commit -m "updates"
git push
# Railway auto-deploys in 1-2 minutes!
```

---

## 📚 Resources

- Railway Docs: https://docs.railway.app
- Railway Discord: https://discord.gg/railway
- Railway Status: https://status.railway.app
- Pricing: https://railway.app/pricing

---

**Ready to deploy? Let me know when you've created your Railway account and I'll help you through each step!**

**Last Updated:** October 24, 2025
