# AMPass — XAMPP Installation Guide

## Requirements

- XAMPP 8.0+ (Apache + MySQL)
- Windows 10/11 (or Linux/macOS with XAMPP)
- Web browser with JavaScript enabled

## Steps

1. **Start XAMPP** — Open XAMPP Control Panel, start Apache and MySQL.

2. **Copy files** — Copy the `ampass/` folder to `C:\xampp\htdocs\ampass`

3. **Open installer** — Navigate to `http://localhost/ampass/install/`

4. **Database setup** (Step 1):
   - Database Host: `localhost`
   - Database Name: `ampass_db`
   - Database Username: `root`
   - Database Password: *(leave empty)*
   - Site Name: `AMPass`
   - Site URL: *(leave empty for auto-detect)*
   - Click "Test Connection & Continue"

5. **Admin account** (Step 2):
   - Full Name: Your name
   - Email: Your email
   - Username: `admin`
   - Password: Minimum 12 characters with uppercase, lowercase, number, and symbol
   - Click "Install AMPass"

6. **Post-install security**:
   - Delete the `C:\xampp\htdocs\ampass\install\` directory
   - Verify `http://localhost/ampass/install/` returns 403

7. **Login** — Go to `http://localhost/ampass/login`

## Extension API Setup (Optional)

If using the browser extension:

1. Import `ampass/database/migrations/001_extension_tables.sql` via phpMyAdmin
2. Login as admin → Admin Panel → Browser Extensions → Enable API

## Notes

- XAMPP on localhost is acceptable for development/testing
- For any network-accessible deployment, use HTTPS
- The app shows a warning banner when accessed without HTTPS
