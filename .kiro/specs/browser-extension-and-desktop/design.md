# Design: AMPass Browser Extension & Desktop App

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                        AMPass Server (PHP)                        │
│  ┌──────────────┐  ┌──────────────────┐  ┌──────────────────┐  │
│  │ Web Session  │  │ Extension Token  │  │  Rate Limiter    │  │
│  │ Auth (CSRF)  │  │ Auth (Bearer)    │  │  + Audit Log     │  │
│  └──────┬───────┘  └────────┬─────────┘  └──────────────────┘  │
│         │                    │                                    │
│  ┌──────┴────────────────────┴──────────────────────────────┐   │
│  │              Shared Models / Database Layer                │   │
│  │  (vault_items, users, user_security, api_tokens, etc.)    │   │
│  └───────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
         │                              │
         │ HTTPS                        │ HTTPS
         ▼                              ▼
┌─────────────────┐          ┌─────────────────────────┐
│   Web Vault     │          │   Browser Extension      │
│   (Browser)     │          │   (Manifest V3)          │
│                 │          │                           │
│  crypto.js      │          │  ┌─────────────────────┐ │
│  (Web Crypto)   │          │  │ Service Worker      │ │
│                 │          │  │ (background.ts)     │ │
│                 │          │  │ - API calls         │ │
│                 │          │  │ - Token mgmt        │ │
│                 │          │  │ - Crypto ops        │ │
│                 │          │  └─────────┬───────────┘ │
│                 │          │            │             │
│                 │          │  ┌─────────┴───────────┐ │
│                 │          │  │ Content Scripts     │ │
│                 │          │  │ - Form detection    │ │
│                 │          │  │ - Autofill inject   │ │
│                 │          │  │ - Autosave detect   │ │
│                 │          │  └─────────────────────┘ │
│                 │          │                           │
│                 │          │  ┌─────────────────────┐ │
│                 │          │  │ Popup UI            │ │
│                 │          │  │ - Vault list        │ │
│                 │          │  │ - Search            │ │
│                 │          │  │ - Generator         │ │
│                 │          │  │ - Lock/Unlock       │ │
│                 │          │  └─────────────────────┘ │
│                 │          └─────────────────────────┘
└─────────────────┘                     │
                                        │ Native Messaging (optional)
                                        ▼
                              ┌─────────────────────┐
                              │  Tauri Desktop App   │
                              │  (optional)          │
                              │  - System tray       │
                              │  - OS keychain       │
                              │  - Global shortcuts  │
                              └─────────────────────┘
```

## Extension Token Authentication

The browser extension cannot use PHP session cookies (different origin, Manifest V3 restrictions). Instead, it uses bearer token authentication:

1. User enters AMPass server URL + credentials in extension options
2. Extension calls `POST /api/ext/login` with username + password
3. Server validates credentials, generates a random 256-bit token, stores `hash(token)` in `api_tokens` table
4. Server returns the raw token + derivation params to the extension
5. Extension stores token in `chrome.storage.session` (cleared on browser close)
6. All subsequent API calls include `Authorization: Bearer <token>` header
7. Server validates by hashing the received token and comparing to stored hash

### Token Security
- Tokens are hashed (SHA-256) before storage — a database leak doesn't expose tokens
- Tokens have configurable expiry (default 30 days)
- Tokens can be revoked from the web vault settings
- Each token is tied to a device name for identification
- Rate limiting applies per-token

## Extension Crypto Flow

The extension reuses the same crypto primitives as the web vault (`crypto.js` logic ported to TypeScript):

1. **Login:** Extension receives `encryption_salt`, `encrypted_vault_key`, `vault_key_iv`, `key_iterations` from server
2. **Unlock:** User enters master password → PBKDF2 derives wrapping key → decrypts vault key → stores in `chrome.storage.session`
3. **Fetch items:** Extension calls `/api/ext/vault/list` → receives encrypted blobs → decrypts locally
4. **Autofill:** Extension decrypts the matched item → injects plaintext into form fields → clears from memory after injection
5. **Autosave:** Extension captures form data → encrypts with vault key → sends ciphertext to server

## Content Script Design

The content script runs on all pages and:
1. Scans for login forms (`<input type="password">` + nearby text/email inputs)
2. Sends the page URL to the service worker for matching
3. If matches found, shows a small AMPass icon on the password field
4. On user click, requests decrypted credentials from service worker
5. Fills the form fields
6. Listens for form submissions to detect autosave opportunities

### Security Boundaries
- Content scripts NEVER hold the vault key
- Content scripts NEVER decrypt items — they request plaintext from the service worker only for the specific item being filled
- The service worker holds the vault key in memory and performs all crypto
- Plaintext credentials exist in content script memory only during the fill operation

## Database Changes

New table: `api_tokens`
```sql
CREATE TABLE api_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash VARCHAR(64) NOT NULL COMMENT 'SHA-256 of the bearer token',
    device_name VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) NULL,
    last_used_at DATETIME NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token_hash (token_hash),
    INDEX idx_token_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

## File Structure (New)

```
extension/                       # Browser extension (TypeScript)
├── manifest.json                # Manifest V3
├── package.json                 # Dev dependencies (TypeScript, Vite)
├── tsconfig.json
├── vite.config.ts
├── src/
│   ├── background/
│   │   └── service-worker.ts    # API calls, crypto, token management
│   ├── content/
│   │   ├── detector.ts          # Form detection logic
│   │   ├── autofill.ts          # Credential injection
│   │   └── autosave.ts          # Form submission capture
│   ├── popup/
│   │   ├── popup.html
│   │   ├── popup.ts
│   │   └── popup.css
│   ├── options/
│   │   ├── options.html
│   │   └── options.ts
│   ├── lib/
│   │   ├── crypto.ts            # Ported from crypto.js
│   │   ├── api.ts               # Server communication
│   │   └── types.ts             # TypeScript interfaces
│   └── assets/
│       └── icons/
├── dist/                        # Built extension (gitignored)
└── README.md

desktop/                         # Tauri desktop app (optional)
├── src-tauri/
│   ├── Cargo.toml
│   ├── src/main.rs
│   └── tauri.conf.json
├── src/                         # Frontend (reuses web vault or custom)
├── package.json
└── README.md

ampass/                          # Existing PHP app (modified)
├── app/controllers/api/
│   └── ExtApiController.php     # NEW: Extension API endpoints
├── app/models/
│   └── ApiToken.php             # NEW: Token model
├── database/
│   └── migrations/
│       └── 001_api_tokens.sql   # NEW: Migration for api_tokens table
└── ...
```

## API Design: Extension Endpoints

All extension endpoints use `Authorization: Bearer <token>` header instead of CSRF + session.

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/ext/login` | Authenticate, get token + derivation params |
| POST | `/api/ext/logout` | Revoke current token |
| POST | `/api/ext/unlock` | Verify master password (server-side check) |
| GET | `/api/ext/vault/list` | List all vault items (encrypted) |
| GET | `/api/ext/vault/match` | Find items by url_hash |
| POST | `/api/ext/vault/save` | Create/update item |
| POST | `/api/ext/vault/used` | Mark item as recently used |
| GET | `/api/ext/vault/stats` | Get vault stats |
| GET | `/api/ext/tokens` | List active tokens for current user |
| POST | `/api/ext/tokens/revoke` | Revoke a specific token |
