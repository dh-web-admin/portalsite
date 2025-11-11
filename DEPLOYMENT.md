# Deployment Checklist - Developer Role Update

## Changes Made

### 1. Database Changes (REQUIRES MANUAL MIGRATION)

- Added 'developer' to the role ENUM in users table
- **Action Required:** Run migration after deploying

### 2. Code Changes (Automatic)

- ✅ Fixed header layout with professional button arrangement
- ✅ Added "Developer Preview Dashboard" title for developer role
- ✅ Fixed role editing functionality in user list
- ✅ Fixed JavaScript errors (btnPass, unsaved-guard)
- ✅ Fixed clone functionality in project checklist
- ✅ Fixed update_user.php API to handle role-only updates
- ✅ Disabled unsaved-guard on user list page only

## Deployment Steps

### Step 1: Git Commit & Push

```bash
git add .
git commit -m "Add developer role support and fix user management issues"
git push origin main
```

### Step 2: Wait for Railway Deployment

- Railway will automatically deploy your changes
- Wait for deployment to complete

### Step 3: Run Database Migration

1. Login to your production site as admin
2. Navigate to: `https://your-production-url.com/PortalSite/migrations/add_developer_role.php`
3. Verify the migration was successful
4. **IMPORTANT:** Delete the `/migrations/` folder after successful migration

### Step 4: Verify Everything Works

- [ ] Login as admin
- [ ] Go to User List
- [ ] Edit a user's role and change it to "developer"
- [ ] Verify the role persists after refresh
- [ ] Login as that developer user
- [ ] Verify you see "Developer Preview Dashboard"
- [ ] Test clone functionality in project checklist
- [ ] Verify header layout looks professional

## Files Changed

### Modified Files:

- `admin/user_list.php` - Fixed role editing, removed debug logging
- `api/update_user.php` - Fixed name parameter handling
- `assets/css/admin-layout.css` - Professional header layout
- `assets/js/unsaved-guard.js` - Added type checking for matches()
- `partials/portalheader.php` - Added Developer Preview Dashboard title
- `pages/project_checklist/index.php` - Fixed clone button syntax error

### New Files:

- `migrations/add_developer_role.php` - Production migration script
- `debug/check_roles.php` - Debug tool (optional, can delete)
- `debug/fix_role.php` - Debug tool (optional, can delete)
- `debug/add_developer_role.php` - Debug tool (optional, can delete)

## Post-Deployment Cleanup

### Required:

- Delete `/migrations/` folder after running migration

### Optional (for security):

- Delete `/debug/` folder (only needed for troubleshooting)

## Rollback Plan (if needed)

If something goes wrong:

1. **Revert code:**

   ```bash
   git revert HEAD
   git push origin main
   ```

2. **Revert database:** (only if migration was run)
   ```sql
   ALTER TABLE users MODIFY COLUMN role ENUM('admin','projectmanager','estimator','accounting','superintendent','foreman','mechanic','operator','laborer') NOT NULL;
   ```

## Notes

- All existing functionality preserved
- No breaking changes
- Developer role is purely additive
- Unsaved-guard disabled only on user list page (still works everywhere else)

## Support

If you encounter any issues:

1. Check Railway logs
2. Check browser console for JavaScript errors
3. Verify migration was run successfully
4. Check that user has 'developer' role in database
