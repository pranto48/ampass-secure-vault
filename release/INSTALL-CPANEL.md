# AMPass — cPanel Installation Guide

## Requirements

- cPanel shared hosting with PHP 8.0+
- MySQL 5.7+ or MariaDB 10.3+
- Apache with mod_rewrite enabled
- SSL/TLS certificate (Let's Encrypt free SSL via cPanel)

## Steps

1. **Enable HTTPS** — In cPanel → SSL/TLS → Install Let's Encrypt certificate

2. **Create database** — In cPanel → MySQL Databases:
   - Create database: `ampass_db`
   - Create user: `ampass_user` with a strong password
   - Add user to database with ALL PRIVILEGES

3. **Upload files** — Via cPanel File Manager or FTP:
   - Upload the `ampass/` folder to `public_html/ampass/`
   - Or to `public_html/` if AMPass is the only site

4. **Set permissions**:
   - `config/` directory: 755
   - After install, `config/config.php`: 640

5. **Run installer** — Visit `https://yourdomain.com/ampass/install/`

6. **Database setup** (Step 1):
   - Database Host: `localhost`
   - Database Name: `ampass_db`
   - Database Username: `ampass_user`
   - Database Password: *(your DB password)*
   - Site Name: `AMPass`
   - Site URL: `https://yourdomain.com/ampass`

7. **Admin account** (Step 2):
   - Fill in admin details
   - Password: 12+ chars with mixed case, numbers, symbols

8. **Post-install security** (CRITICAL):
   - **DELETE** the `/install/` directory via File Manager
   - Uncomment HTTPS redirect in `.htaccess` (lines 7-8)
   - Uncomment HSTS header in `.htaccess`
   - Set `config/config.php` permissions to 640
   - Verify `https://yourdomain.com/ampass/install/` returns 403
   - Verify `https://yourdomain.com/ampass/config/` returns 403

9. **Extension API** (Optional):
   - Import `database/migrations/001_extension_tables.sql` via phpMyAdmin
   - Admin Panel → Browser Extensions → Enable API
   - Add your extension's origin to allowed origins

## Troubleshooting

- **500 error**: Check `.htaccess` is uploaded and mod_rewrite is enabled
- **Blank page**: Check PHP version is 8.0+ in cPanel → PHP Version
- **DB connection failed**: Verify username has access to the database
- **Installer locked**: Delete `config/.install_lock` to re-run (not recommended)
