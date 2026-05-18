# Publish AMPass Downloads

## Overview

AMPass includes a web download center at `/downloads` where users can download the desktop app and browser extension.

## Admin Setup

1. Run migration `database/migrations/002_release_downloads.sql` (auto-runs on fresh install)
2. Ensure `app_storage/releases/` directory exists and is writable
3. Login as admin → Admin panel

## Upload a Release

1. Build the release file (see BUILD-DESKTOP-WINDOWS.md or BUILD-BROWSER-EXTENSION.md)
2. Go to Admin → Release Downloads (or manually insert into `release_downloads` table)
3. Upload the file
4. The system auto-generates:
   - SHA-256 checksum
   - File size
   - Random stored filename
5. Set version, release notes
6. Mark as active

## File Storage

Release files are stored in `app_storage/releases/` with:
- Random filenames (prevents guessing)
- `.htaccess` blocking direct access
- Files served through PHP controller with download count tracking

## Download URL Format

Public download: `https://yourdomain.com/ampass/downloads/file/{id}`

## Security

- Only admins can upload/delete releases
- Only active files can be downloaded
- Direct file access is blocked by .htaccess
- Allowed extensions: .exe, .msi, .zip, .xpi
- CSRF protection on all admin actions
- Audit logging on upload/download/delete

## cPanel Notes

- Ensure `app_storage/` directory has 755 permissions
- Ensure `app_storage/releases/` has 755 permissions
- The `.htaccess` inside blocks direct access
- PHP streams files through the controller
