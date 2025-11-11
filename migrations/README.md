# Database Migrations

## Add Supplier Coordinates (Required for Maps Feature)

### Option 1: Run via Railway CLI

1. Install Railway CLI if you haven't:

```bash
npm i -g @railway/cli
```

2. Login to Railway:

```bash
railway login
```

3. Link to your project:

```bash
railway link
```

4. Run the migration:

```bash
railway run php migrations/add_supplier_coordinates.php
```

### Option 2: Run SQL Directly in Railway Database

1. Go to your Railway dashboard
2. Click on your MySQL database service
3. Go to the "Data" tab or "Connect" tab
4. Open the database console or use the connection string
5. Run these SQL commands:

```sql
ALTER TABLE suppliers ADD COLUMN latitude DECIMAL(10, 8) NULL AFTER state;
ALTER TABLE suppliers ADD COLUMN longitude DECIMAL(11, 8) NULL AFTER latitude;
```

### Option 3: Use Railway MySQL Client

1. Get your database connection details from Railway dashboard
2. Connect using MySQL client:

```bash
mysql -h [host] -u [user] -p[password] [database]
```

3. Run the SQL commands above

---

## After Adding Columns

### Batch Geocode All Suppliers (Optional)

You can either:

- Let the automatic background geocoding handle it (5 suppliers per page load)
- Or run the batch script: `railway run php migrations/geocode_suppliers.php`

The background geocoding will gradually geocode all suppliers as users visit the maps page.
