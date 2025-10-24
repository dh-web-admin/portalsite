# GitHub Setup Guide for PortalSite

## Quick Reference Commands

### Initial Setup (One-Time)

```bash
# Configure Git with your info
git config --global user.name "Your Name"
git config --global user.email "your.email@example.com"

# Initialize repository
git init

# Add all files
git add .

# First commit
git commit -m "Initial commit: Employee portal with user management"

# Connect to GitHub (replace with your repo URL)
git remote add origin https://github.com/YOUR-USERNAME/PortalSite.git

# Rename branch to main
git branch -M main

# Push to GitHub
git push -u origin main
```

### Daily Workflow

```bash
# Check status of files
git status

# Add changed files
git add .

# Commit with message
git commit -m "Description of what you changed"

# Push to GitHub
git push

# Pull latest changes
git pull
```

### Useful Commands

```bash
# See commit history
git log

# See what changed
git diff

# Undo local changes (before commit)
git checkout -- filename.php

# Undo last commit (keep changes)
git reset --soft HEAD~1

# Create new branch
git checkout -b feature-name

# Switch branches
git checkout main

# Delete a branch
git branch -d feature-name
```

### If You Make a Mistake

```bash
# Revert last commit (creates new commit)
git revert HEAD

# Go back to previous commit (destructive)
git reset --hard HEAD~1

# Discard all local changes
git reset --hard origin/main
```

## GitHub Authentication

### Option 1: Personal Access Token (Recommended)

1. Go to GitHub.com → Settings → Developer Settings
2. Personal Access Tokens → Tokens (classic)
3. Generate New Token
4. Select scopes: `repo` (full control)
5. Copy token and save it securely
6. Use token as password when pushing

### Option 2: GitHub CLI (Easier)

```bash
# Install GitHub CLI
winget install GitHub.cli

# Authenticate
gh auth login

# Follow prompts to authenticate via browser
```

### Option 3: SSH Keys

```bash
# Generate SSH key
ssh-keygen -t ed25519 -C "your.email@example.com"

# Copy public key
cat ~/.ssh/id_ed25519.pub

# Add to GitHub: Settings → SSH and GPG keys → New SSH key
```

## Common Issues & Solutions

### Issue: "git is not recognized"

**Solution**: Restart terminal/VS Code after installing Git

### Issue: Authentication failed

**Solution**: Use Personal Access Token instead of password

### Issue: "fatal: not a git repository"

**Solution**: Make sure you ran `git init` first

### Issue: Conflicts when pulling

**Solution**:

```bash
git stash          # Save your changes
git pull           # Get latest
git stash pop      # Reapply your changes
```

### Issue: Pushed sensitive data (config.php)

**Solution**:

```bash
# Remove from Git history
git filter-branch --force --index-filter \
  "git rm --cached --ignore-unmatch config/config.php" \
  --prune-empty --tag-name-filter cat -- --all

# Force push
git push origin --force --all
```

## Best Practices

### Commit Messages

- ✅ Good: "Add password reset functionality to user list"
- ✅ Good: "Fix login redirect bug for non-admin users"
- ❌ Bad: "updates"
- ❌ Bad: "fixed stuff"

### When to Commit

- After completing a feature
- After fixing a bug
- Before trying something experimental
- At the end of the day
- Before switching to different work

### What to Commit

- ✅ Source code (.php, .css, .js, .html)
- ✅ Documentation (.md files)
- ✅ Configuration templates (.example files)
- ❌ Actual config files with passwords
- ❌ Database files
- ❌ Log files
- ❌ Temporary files

## Branches Strategy

### Main Branch

- Production-ready code
- Always stable
- Protected from direct commits

### Development Workflow

```bash
# Start new feature
git checkout -b feature/user-notifications

# Work on feature, make commits
git add .
git commit -m "Add notification system"

# When done, merge to main
git checkout main
git merge feature/user-notifications

# Push to GitHub
git push

# Delete feature branch
git branch -d feature/user-notifications
```

## Deployment with Git

### Pull to Server

```bash
# On your production server
cd /var/www/html/PortalSite
git pull origin main

# Or set up auto-deployment webhook
```

### Rollback Production

```bash
# If something breaks in production
git revert HEAD
git push
# Server pulls the revert
```

## .gitignore Patterns

Already configured in `.gitignore`:

- `config/config.php` - Database credentials
- `*.bak` - Backup files
- `*.log` - Log files
- `node_modules/` - Dependencies
- `.env` - Environment variables

## Repository Settings (On GitHub)

### Recommended Settings:

1. **Branch Protection**:

   - Settings → Branches → Add rule
   - Branch name: `main`
   - ✅ Require pull request reviews

2. **Security**:

   - Settings → Security
   - ✅ Enable Dependabot alerts
   - ✅ Enable secret scanning

3. **Issues**:
   - Enable for bug tracking and features

## Next Steps After Setup

1. ✅ Code is on GitHub
2. Set up branch protection rules
3. Enable GitHub Actions (CI/CD)
4. Configure deployment webhooks
5. Set up staging environment
6. Document deployment process

---

**Need Help?**

- GitHub Docs: https://docs.github.com
- Git Docs: https://git-scm.com/doc
