---
inclusion: auto
---

# AMPass Security Rules (All Code)

These rules apply to ALL code in this project — PHP backend, JavaScript web vault, browser extension, and desktop app.

## Absolute Rules (Never Violate)

1. **NEVER store plaintext vault secrets in the database.** This includes passwords, card numbers, CVVs, secure notes, SSH keys, Wi-Fi passwords, and any user-entered sensitive field.

2. **NEVER send decrypted vault item fields to the PHP server.** The server receives and stores ONLY: `encrypted_data` (ciphertext hex), `encryption_iv` (nonce hex), `title_hash` (HMAC), `url_hash` (HMAC), and metadata flags.

3. **NEVER log plaintext secrets** to server logs, browser console (in production), or audit logs.

4. **ALL encryption/decryption happens client-side** — in the browser (Web Crypto API), in the extension (service worker), or in the desktop app. The PHP server is a dumb encrypted storage layer.

5. **Master password verification** uses Argon2id hash comparison on the server. The master password is transmitted over HTTPS but is NEVER stored — only its hash.

6. **Key derivation** (PBKDF2, 100k iterations, SHA-256) happens exclusively client-side to produce the wrapping key that decrypts the vault key.

## API Security

- All state-changing endpoints require authentication (session or bearer token)
- Web vault uses CSRF tokens on POST/PUT/DELETE
- Extension API uses bearer tokens (no CSRF needed — tokens are proof of intent)
- Rate limiting on: login (5/15min), registration (3/hour), unlock (5/5min), token creation (10/hour)
- All inputs validated and sanitized before database operations
- All database queries use prepared statements (PDO)

## Session/Token Security

- PHP sessions: HttpOnly, Secure, SameSite=Strict cookies
- Session fingerprinting (user-agent hash binding)
- Vault auto-lock after configurable timeout (default 5 minutes)
- API tokens: SHA-256 hashed before storage, revocable, expirable
- Extension vault key: stored in `chrome.storage.session` (cleared on browser close)

## What the Server Stores

| Data | Storage Format |
|------|---------------|
| Login password | Argon2id hash |
| Master password | Argon2id hash (separate from login) |
| Vault key | Encrypted with user's derived key (AES-GCM ciphertext) |
| Vault items | AES-256-GCM ciphertext + IV |
| Item titles | HMAC-SHA256 hash (for server-side search) |
| Item URLs | HMAC-SHA256 hash (for URL matching) |
| API tokens | SHA-256 hash of raw token |
| Shared item keys | Encrypted with recipient's key |

## What the Server NEVER Stores

- Plaintext passwords, card numbers, notes, or any vault field content
- Raw vault key
- Raw API tokens
- Master password in any form other than Argon2id hash
- Decrypted anything
