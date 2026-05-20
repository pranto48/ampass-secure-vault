# AMPass — Update System

## ⚠️ Security Warning

AMPass updater, remote backup, encrypted backup, email, 2FA, browser extension, desktop app, and web vault require professional security audit before real credential storage.

## Overview

AMPass can check GitHub for updates and apply them from the admin panel.

**Source:** https://github.com/pranto48/ampass-secure-vault

## Update Methods

### Method A: GitHub Releases (Default)
- Checks `https://api.github.com/repos/pranto48/ampass-secure-vault/releases/latest`
- Compares release tag version with installed version
- Downloads release ZIP

### Method B: GitHub Branch ZIP
- Checks latest commit SHA on `main` branch
- Compares with installed commit SHA
- Downloads branch archive ZIP

### Method C: Git CLI (Optional)
- Requires shell_exec and .git directory
- Not available on most cPanel shared hosting
- Use Method A or B instead

## How to Update

1. Login as admin
2. Go to **Admin → Updates**
3. Click **Check for Updates**
4. If update available, review release notes
5. Enter backup password (pre-update backup is created automatically)
6. Type `UPDATE AMPASS` to confirm
7. Click **Update Now**

## What Happens During Update

1. Encrypted backup created automatically
2. Maintenance mode enabled (users see "updating" page)
3. Update package downloaded from GitHub
4. Files extracted and applied (config/app_storage excluded)
5. Database migrations run
6. Maintenance mode disabled
7. Update history recorded

## What's Never Overwritten

- `config/config.php`
- `config/.install_lock`
- `app_storage/` (backups, releases, uploads)
- `.htaccess` (unless explicitly chosen)
- User-uploaded files

## Rollback

If update fails:
- Maintenance mode is disabled
- Update marked as "failed" in history
- Pre-update backup available for manual restore

## Troubleshooting

### "GitHub API rate limited"
- Add a GitHub personal access token in Admin → Updates → Settings
- Free tier allows 60 requests/hour without token, 5000 with token

### Update fails on cPanel
- Ensure `app_storage/temp/` is writable
- Check PHP memory_limit (128MB+ recommended)
- Check max_execution_time (120+ seconds recommended)

### Migrations fail
- Check Admin → Updates for error details
- Run migrations manually via phpMyAdmin if needed
- Failed migrations are not marked as applied (safe to retry)
