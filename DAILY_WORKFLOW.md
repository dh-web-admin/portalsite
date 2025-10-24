# Daily Workflow Guide - PortalSite Development

## Simple 3-Step Process

Every time you work on your project, follow these three steps:

### Step 1: Work Normally ✏️

- Open VS Code
- Edit your files (PHP, CSS, HTML, etc.)
- Test in browser: http://localhost/PortalSite
- Make sure everything works

### Step 2: Save Locally 💾

- Save your files (Ctrl + S)

### Step 3: Backup to GitHub ☁️

```bash
# Open Terminal in VS Code (Ctrl + `)
# Then run these 3 commands:

git add .
git commit -m "Brief description of what you changed"
git push
```

**That's it!** Your code is now backed up on GitHub.

---

## 📍 Important: Where to Work

**Always work here:**

```
C:\xampp\htdocs\PortalSite
```

**Never:**

- ❌ Don't create a new folder
- ❌ Don't move your files
- ❌ Don't edit on GitHub website

---

## 🔄 Complete Daily Workflow Example

### Morning - Starting Work

1. Open VS Code
2. Make sure you're in: `C:\xampp\htdocs\PortalSite`
3. Start XAMPP (Apache + MySQL)
4. Open browser: http://localhost/PortalSite

### During the Day - Making Changes

**Example: You want to change dashboard colors**

1. Open `assets/css/dashboard.css` in VS Code
2. Change the colors:
   ```css
   .tile {
     background: #4a90e2; /* Changed from #3173d479 */
   }
   ```
3. Save file (Ctrl + S)
4. Refresh browser to see changes
5. Looks good? Continue to Step 3...

### End of Day (or After Each Feature) - Save to GitHub

Open Terminal in VS Code (Ctrl + `) and type:

```bash
# See what files you changed
git status

# Add all changes
git add .

# Save with description
git commit -m "Updated dashboard tile colors to blue"

# Upload to GitHub
git push
```

**Done!** ✅ Your work is saved to GitHub.

---

## 📝 Real Examples

### Example 1: Fixed a Bug

```bash
# You fixed the login redirect issue in auth/login_register.php

git add auth/login_register.php
git commit -m "Fix login redirect for non-admin users"
git push
```

### Example 2: Added New Feature

```bash
# You created admin/reports.php and assets/css/reports.css

git add .
git commit -m "Add reports page with user statistics"
git push
```

### Example 3: Multiple Small Changes

```bash
# You updated several CSS files and fixed typos

git add .
git commit -m "Update styling and fix typos across admin pages"
git push
```

### Example 4: End of Day Save

```bash
# You worked on various improvements throughout the day

git add .
git commit -m "Daily work: improved navigation, updated colors, fixed bugs"
git push
```

---

## 🎓 Understanding the 3 Git Commands

### Command 1: `git add .`

**What it does:** Prepares all your changed files to be saved

- The `.` means "everything I changed"
- You can also add specific files: `git add dashboard.php`

### Command 2: `git commit -m "message"`

**What it does:** Saves your changes locally with a description

- The message should describe what you did
- Good: "Add email validation to user registration"
- Bad: "updates" or "changes"

### Command 3: `git push`

**What it does:** Uploads your saved changes to GitHub

- Makes your work available in the cloud
- Creates a backup
- Others can now see your changes (if they have access)

---

## 🔍 Checking Your Status

**Before committing, always check what changed:**

```bash
# See which files you modified
git status

# See the actual changes you made
git diff

# See your recent commits
git log --oneline -5
```

---

## When Should You Commit & Push?

### Good Times:

- ✅ After completing a feature
- ✅ After fixing a bug
- ✅ Before trying something experimental
- ✅ End of your work session
- ✅ When you have working code you don't want to lose

### How Often?

- **Minimum**: Once per day (at end of work)
- **Recommended**: After each completed task
- **Maximum**: No limit! Commit as often as you want

### Don't Wait!

- ❌ Don't wait a week before committing
- ❌ Don't commit only when "everything is perfect"
- ✅ Commit working code frequently

---

## Common Situations & Solutions

### "I changed files but haven't pushed yet"

```bash
# That's fine! Just push when ready
git status      # See what you changed
git add .
git commit -m "Description of changes"
git push
```

### "I want to see what I changed before committing"

```bash
git status      # List changed files
git diff        # Show exact changes
```

### "I made a mistake and want to undo"

```bash
# Undo changes to a specific file (before commit)
git checkout -- filename.php

# Undo ALL local changes (before commit)
git reset --hard

# Undo last commit (after commit but before push)
git reset --soft HEAD~1

# Undo after pushing (creates new commit that undoes)
git revert HEAD
git push
```

### "Did I forget to push something?"

```bash
git status
# If it says "Your branch is ahead of 'origin/main'" - you need to push
git push
```

### "I'm not sure if my code is on GitHub"

```bash
git status
# Should say: "Your branch is up to date with 'origin/main'"
# Or check: https://github.com/dh-web-admin/portalsite
```

---

## 🚫 Files That Won't Be Pushed (Protected)

These files are automatically excluded (in .gitignore):

- ❌ `config/config.php` - Contains database password
- ❌ `*.bak` - Backup files
- ❌ `*.log` - Log files
- ❌ `*_backup.php` - Old backup files

**This is good!** Sensitive files stay on your computer only.

---

## 📋 Quick Reference Card

**Print this or keep it handy:**

```bash
# ========================================
# DAILY GIT COMMANDS
# ========================================

# 1. Check what changed
git status

# 2. Add all changes
git add .

# 3. Save with message
git commit -m "What I did today"

# 4. Upload to GitHub
git push

# ========================================
# BONUS COMMANDS
# ========================================

# See recent commits
git log --oneline -5

# See exact changes
git diff

# Undo local changes
git checkout -- filename.php

# Pull latest (if working from multiple computers)
git pull
```

---

## 💡 Pro Tips

### Tip 1: Write Good Commit Messages

```bash
# Good messages explain WHAT and WHY
✅ git commit -m "Add password reset button to user list"
✅ git commit -m "Fix email validation regex in registration"
✅ git commit -m "Update dashboard colors to match brand guidelines"

# Bad messages are vague
❌ git commit -m "updates"
❌ git commit -m "fix"
❌ git commit -m "changes"
```

### Tip 2: Commit Often

Better to have many small commits than one giant commit:

```bash
# Throughout the day:
git commit -m "Add email field validation"
git commit -m "Update error messages"
git commit -m "Fix spacing in registration form"
git commit -m "Add success notification"

# Push at the end
git push
```

### Tip 3: Test Before You Push

- Always test your code locally first
- Make sure http://localhost/PortalSite still works
- Then commit and push

### Tip 4: Pull Before You Start (If Using Multiple Computers)

```bash
# If you worked on another computer yesterday
git pull

# Now you have the latest code
# Start working...
```

---

## 🎯 Weekly Workflow Summary

### Monday Morning

```bash
# Start XAMPP
# Open VS Code
cd C:\xampp\htdocs\PortalSite
git pull  # Get any updates (if any)
# Start coding...
```

### During the Week

```bash
# After each feature or bug fix:
git add .
git commit -m "Description"
git push
```

### Friday Evening

```bash
# Make sure everything is pushed
git status
# If you see uncommitted changes:
git add .
git commit -m "Week's work completed"
git push
```

---

## 📊 Visual Workflow

```
┌──────────────────────────────────────────────┐
│  C:\xampp\htdocs\PortalSite                  │
│  (Your Local Files - Work Here!)             │
└──────────────┬───────────────────────────────┘
               │
               │ Edit, Save, Test
               │
               ▼
┌──────────────────────────────────────────────┐
│  git add .                                   │
│  git commit -m "message"                     │
│  (Saved locally on your computer)            │
└──────────────┬───────────────────────────────┘
               │
               │ git push
               │
               ▼
┌──────────────────────────────────────────────┐
│  GitHub.com/dh-web-admin/portalsite          │
│  (Backup in the cloud)                       │
└──────────────────────────────────────────────┘
```

---

## ✅ Checklist Before Closing VS Code

Before ending your work session:

- [ ] All changes saved (Ctrl + S)
- [ ] Tested in browser
- [ ] Run `git status` - see what changed
- [ ] Run `git add .` - stage changes
- [ ] Run `git commit -m "message"` - save locally
- [ ] Run `git push` - upload to GitHub
- [ ] Verify: `git status` says "up to date"

---

## 🎓 Remember

1. **Your work stays in:** `C:\xampp\htdocs\PortalSite`
2. **You edit files** in VS Code
3. **You test on:** http://localhost/PortalSite
4. **You backup with:** `git add`, `git commit`, `git push`
5. **GitHub is your** backup, not your workspace

---

## 🚀 You're Ready!

That's everything you need to know!

**Core workflow:**

1. Edit files
2. Test locally
3. `git add .` → `git commit -m "message"` → `git push`

Keep this file handy and refer to it whenever you need a reminder!

---

**Questions?** Check:

- This file: `DAILY_WORKFLOW.md`
- Setup guide: `GITHUB_SETUP.md`
- PHP structure: `PHP_ORGANIZATION.md`
- CSS structure: `CSS_ORGANIZATION.md`

**Last Updated:** October 24, 2025
