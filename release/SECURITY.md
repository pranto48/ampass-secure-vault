# AMPass — Security Overview

## ⚠️ CRITICAL WARNING

**AMPass has NOT been professionally audited by a security firm.**

**Do NOT store real production credentials until:**
1. A qualified security firm has audited the cryptographic implementation
2. Penetration testing has been conducted against the deployed instance
3. The browser extension has been reviewed for Chrome Web Store requirements
4. The desktop app's local storage encryption has been formally verified

**Use at your own risk. The authors accept no liability for data loss or security breaches.**

---

## Encryption

| Component | Algorithm | Key Size | Purpose |
|-----------|-----------|----------|---------|
| Vault items | AES-256-GCM | 256-bit | Encrypt all credential fields |
| Key derivation | PBKDF2-SHA256 | 100k iterations | Derive wrapping key from master password |
| Password hashing | Argon2id (or bcrypt) | 64MB/4 rounds | Server-side login/master password verification |
| Local cache (desktop) | AES-256-GCM | 256-bit | Encrypt offline vault cache |
| API tokens | SHA-256 | 256-bit | Hash tokens before database storage |
| URL matching | HMAC-SHA256 | 256-bit | Server-side search without seeing plaintext |

## Zero-Knowledge Design

The server **never** stores or sees:
- Plaintext passwords, card numbers, notes, or any vault field
- The vault key
- The master password (only its Argon2id hash)
- Raw API tokens (only SHA-256 hashes)

The server **only** stores:
- AES-256-GCM ciphertext + IVs
- PBKDF2 salts and iteration counts
- HMAC hashes for search
- Argon2id password hashes
- Metadata flags (favorite, weak, reused)

## Security Features by Component

### PHP Web App
- CSRF protection on all state-changing operations
- Rate limiting on login, registration, unlock, password change
- Session fingerprinting (user-agent binding)
- Secure cookies (HttpOnly, Secure, SameSite=Strict)
- Content Security Policy headers
- Prepared statements (PDO) for all queries
- Input validation and output escaping
- .htaccess blocks access to config/, app/, database/, install/
- Installer triple-lock (constant + file + .htaccess)
- HTTPS enforcement with clear warnings

### Browser Extension
- Vault key only in service worker (never in content scripts)
- Autofill requires user action (never auto-fills on page load)
- HTTP autofill blocked by default
- Domain matching prevents cross-site filling
- No eval(), no remote scripts, no CDN
- CSP enforced in manifest
- Sender verification on all messages
- Clipboard auto-clear (30 seconds)
- Auto-lock on inactivity

### Desktop App
- Vault key in memory only (cleared on lock)
- Local cache encrypted with device key from OS keychain
- Auto-lock on idle and system sleep
- Master password never stored
- Wipe Local Data removes all traces

### Native Messaging
- Optional (disabled by default)
- Strict extension ID allowlist
- Message type allowlist (10 types)
- Vault must be unlocked for sensitive operations
- Timeout on all requests
- Never returns vault key or master password

## Known Architectural Limitations

1. Master password IS sent to server (over HTTPS) for Argon2id verification. True zero-knowledge would require SRP/OPAQUE.
2. Client-side encryption relies on JavaScript integrity. A compromised server could serve malicious JS.
3. Browser extension content scripts run in page context (Chrome isolated world provides protection).
4. Session-based vault unlock means a compromised session grants vault access.
5. Desktop app's local cache could be targeted by malware with disk + keychain access.
6. No hardware key (FIDO2/WebAuthn) support.

## Reporting Vulnerabilities

If you discover a security vulnerability:
1. Do NOT open a public issue
2. Contact the maintainer privately
3. Allow reasonable time for a fix before disclosure
