# AMPass — Backup & Restore Guide

## ⚠️ Security Warning

AMPass backup, email 2FA, browser extension, desktop app, and web vault require professional security audit before real credential storage.

## Creating a Backup

1. Login as admin
2. Go to **Admin → Backups**
3. Enter a strong backup password (8+ characters)
4. Select options:
   - Include release files (optional)
   - Include audit logs (optional)
5. Click **Create Backup**
6. Download the `.ampass-backup` file

### What's included

- All database tables (users, vault items, settings, etc.)
- Vault items remain encrypted (server never has plaintext)
- Optional: release files, audit logs

### Encryption

- Backup password → Argon2id key derivation (or PBKDF2 fallback)
- Package encrypted with XChaCha20-Poly1305 (or AES-256-GCM fallback)
- Each backup has unique random salt and nonce
- Backup password is never stored

## Downloading a Backup

1. Admin → Backups → click **Download**
2. Store the file securely offline
3. Remember the backup password

## Restoring a Backup

### On the same server

1. Admin → Backups → Upload `.ampass-backup` file
2. Enter backup password
3. Choose restore mode (verify only / restore)
4. Confirm with "RESTORE AMPASS"

### On a new XAMPP/cPanel server

1. Install AMPass fresh (run installer)
2. Login as admin
3. Admin → Backups → Upload backup file
4. Restore will overwrite the fresh database

## Backup Password Warning

**If you lose the backup password, the backup cannot be recovered.**

AMPass does not store the backup password anywhere. The encryption key is derived from the password using Argon2id with high memory/time cost.

## Troubleshooting

### Large file upload fails
- Increase `upload_max_filesize` and `post_max_size` in php.ini
- On cPanel: PHP Settings → upload_max_filesize

### Backup creation fails
- Ensure `app_storage/backups/` directory exists and is writable (755)
- Check PHP memory_limit is sufficient for large databases

### Restore fails
- Verify backup password is correct
- Ensure backup file is not corrupted (check SHA-256)
- Try "Verify only" mode first
