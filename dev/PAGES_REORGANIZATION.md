# Pages Folder Reorganization - December 30, 2025

## Overview

The `pages/` directory has been reorganized from a flat structure to a folder-based structure, with each page having its own dedicated folder containing both the PHP file and page-specific CSS.

## What Changed

### Before (Old Structure)

```
pages/
├── Bid_tracking.php
├── dashboard.php
├── employee_information.php
├── engineering.php
├── equipments.php
├── forms.php
├── for_sale.php
├── manuals.php
├── maps.php
├── pictures.php
├── project_checklist.php
├── scheduling.php
├── videos.php
└── _template.php
```

### After (New Structure)

```
pages/
├── Bid_tracking/
│   ├── index.php
│   └── style.css
├── dashboard/
│   ├── index.php
│   └── style.css
├── employee_information/
│   ├── index.php
│   └── style.css
├── engineering/
│   ├── index.php
│   └── style.css
├── equipments/
│   ├── index.php
│   └── style.css
├── forms/
│   ├── index.php
│   └── style.css
├── for_sale/
│   ├── index.php
│   └── style.css
├── manuals/
│   ├── index.php
│   └── style.css
├── maps/
│   ├── index.php
│   └── style.css
├── pictures/
│   ├── index.php
│   └── style.css
├── project_checklist/
│   ├── index.php
│   └── style.css
├── scheduling/
│   ├── index.php
│   └── style.css
├── videos/
│   ├── index.php
│   └── style.css
└── _template.php
```

## Benefits

### 1. Better Organization

- Each page is self-contained in its own folder
- Easy to find all files related to a specific page
- Clear separation of concerns

### 2. Dedicated CSS Files

- Each page has its own `style.css` file
- No more CSS conflicts between pages
- Easier to maintain page-specific styles
- Global styles still inherited from parent CSS files

### 3. Cleaner URLs

- Old: `http://localhost/PortalSite/pages/dashboard.php`
- New: `http://localhost/PortalSite/pages/dashboard/`
- Cleaner, more professional URLs

### 4. Scalability

- Easy to add more files to a page (JS, images, etc.)
- Potential for page-specific assets in the future
- Better structure for growing application

## Path Changes

### In Page Files (index.php)

**Includes:**

- Old: `require_once __DIR__ . '/../config/config.php';`
- New: `require_once __DIR__ . '/../../config/config.php';`

- Old: `include __DIR__ . '/../partials/portalheader.php';`
- New: `include __DIR__ . '/../../partials/portalheader.php';`

**CSS Links:**

- Old: `<link rel="stylesheet" href="../assets/css/base.css" />`
- New: `<link rel="stylesheet" href="../../assets/css/base.css" />`
- Added: `<link rel="stylesheet" href="style.css" />`

**API Calls:**

- Old: `fetch('../api/update_project.php', ...)`
- New: `fetch('../../api/update_project.php', ...)`

**Redirects:**

- Old: `header('Location: ../auth/login.php');`
- New: `header('Location: ../../auth/login.php');`

### In Dashboard (dashboard/index.php)

**Links to other pages:**

- Old: `<a href="/pages/equipments.php">`
- New: `<a href="/pages/equipments/">`

### In Sidebar (partials/sidebar.php)

**Dashboard link:**

- Old: `href="<?php echo base_url('/pages/dashboard.php'); ?>"`
- New: `href="<?php echo base_url('/pages/dashboard/'); ?>"`

### In Other Files

All references to `pages/*.php` have been updated to `pages/*/` throughout:

- `admin/` files
- `auth/` files
- `debug/` files

## CSS Structure

Each page now has a local `style.css` file that:

1. Contains page-specific styles only
2. Is loaded AFTER global CSS files
3. Can override global styles if needed
4. Keeps page styling isolated

**Load order in each page:**

```html
<link rel="stylesheet" href="../../assets/css/base.css" />
<link rel="stylesheet" href="../../assets/css/admin-layout.css" />
<link rel="stylesheet" href="../../assets/css/dashboard.css" />
<link rel="stylesheet" href="../../assets/css/[page-specific].css" />
<!-- If exists -->
<link rel="stylesheet" href="style.css" />
<!-- Local styles -->
```

## Migration Checklist

✅ Created individual folders for each page
✅ Moved PHP files to folders as `index.php`
✅ Updated all `../` paths to `../../` in page files
✅ Updated dashboard links to point to folders
✅ Updated sidebar navigation
✅ Updated redirects in all files
✅ Updated auth file redirects
✅ Updated admin file links
✅ Updated debug file links
✅ Created `style.css` for each page
✅ Added style.css links to all index.php files
✅ Updated documentation

## Testing Checklist

After this reorganization, test:

- [ ] Dashboard loads correctly
- [ ] All dashboard tiles link to correct pages
- [ ] Sidebar navigation works
- [ ] Page redirects (when not logged in) work
- [ ] API calls from pages work
- [ ] CSS loads correctly on all pages
- [ ] Page-specific styles apply
- [ ] Login/logout flow works
- [ ] Admin functions work
- [ ] All assets (images, fonts) load

## Future Possibilities

With this new structure, we can now easily:

1. **Add page-specific JavaScript:**

   ```
   pages/project_checklist/
   ├── index.php
   ├── style.css
   └── script.js  ← New
   ```

2. **Add page-specific assets:**

   ```
   pages/project_checklist/
   ├── index.php
   ├── style.css
   ├── images/
   └── fonts/
   ```

3. **Add page documentation:**

   ```
   pages/project_checklist/
   ├── index.php
   ├── style.css
   └── README.md
   ```

4. **Add page configuration:**
   ```
   pages/project_checklist/
   ├── index.php
   ├── style.css
   └── config.json
   ```

## Breaking Changes

### For Developers

If you have bookmarks or hardcoded URLs:

- Update from `.php` to `/` (folder)
- Example: `pages/dashboard.php` → `pages/dashboard/`

### For Deployment

When deploying:

- All path changes are relative, so deployment should work seamlessly
- Make sure to upload entire `pages/` folder structure
- Verify all redirects work after deployment

## Rollback Plan

If needed, to revert:

1. Move all `index.php` files back to `pages/` root
2. Rename them to original names (e.g., `dashboard/index.php` → `dashboard.php`)
3. Update all `../../` paths back to `../`
4. Update dashboard links back to `.php`
5. Update sidebar and other references
6. Remove `style.css` files or move to global CSS

## Notes

- The `_template.php` file remains in the root of `pages/` as a reference template
- All pages maintain backward compatibility with session management
- No database changes were required
- No changes to authentication or authorization logic
- This is purely a structural/organizational change

---

**Date:** December 30, 2025
**Type:** Structural Reorganization
**Impact:** All page files
**Breaking:** URL structure changed (`.php` → `/`)
**Version:** 2.0
