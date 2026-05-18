# AMPass Changelog

## v1.0.0 (2026-05-18)

### PHP Web App
- Full password vault with 9 item types (login, secure note, identity, payment card, Wi-Fi, SSH, software license, bank account, custom)
- Client-side AES-256-GCM encryption via Web Crypto API
- PBKDF2 key derivation (100k iterations)
- Argon2id/bcrypt server-side password hashing
- Install wizard with database setup and admin creation
- Login, registration, vault unlock flow
- Dashboard with security score, stats, favorites, recent items
- Vault CRUD with folder organization and tags
- Password generator (random + passphrase)
- Secure credential sharing between users
- Admin panel (users, settings, audit logs, extension management)
- Encrypted backup import/export
- PWA support (service worker, manifest, offline screen)
- Dark/light mode with responsive design
- CSRF protection, rate limiting, session fingerprinting
- Content Security Policy, HSTS, security headers
- Extension API with bearer token authentication
- Comprehensive audit logging

### Browser Extension (Chrome/Edge MV3)
- Popup UI with login, unlock, vault list, search, generator
- Content script form detection and autofill
- Autosave prompt for new/updated credentials
- Domain matching for credential suggestions
- Badge count for matching items
- Auto-lock on inactivity
- Clipboard auto-clear
- HTTP autofill protection
- Options page with full configuration
- Optional native messaging bridge support

### Desktop App (Tauri v2 — Optional)
- Native Windows app with system tray
- Vault dashboard with search
- Password generator
- Encrypted offline cache (AES-256-GCM + OS keychain)
- Auto-lock on idle/sleep
- Background sync (5-minute interval)
- Encrypted backup export/import via native file picker
- Wipe local data option

### Native Messaging (Optional)
- Chrome/Edge/Firefox host manifests
- Windows registry install/uninstall scripts
- Rust native messaging host module
- Extension-side client with fallback to server API
- Strict message type allowlist
- Vault lock state enforcement

### Security
- Zero-knowledge encryption (server never sees plaintext)
- No plaintext secrets stored on disk or in database
- Rate limiting on all auth endpoints
- CSRF on all state-changing operations
- Audit logging for all security-relevant actions
- HTTPS enforcement with clear warnings
- Professional audit warning in all documentation
