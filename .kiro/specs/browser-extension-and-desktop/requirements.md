# Requirements: AMPass Browser Extension & Desktop App

## Overview

Add browser extension support to AMPass for true browser-wide autofill, then optionally add a Tauri-based desktop app. The PHP web vault remains the primary product and must not be broken.

## Functional Requirements

### FR-1: Extension-Ready API Layer
The PHP backend must expose token-based API endpoints that a browser extension can authenticate with independently of the web session (since extensions can't share PHP session cookies reliably).

- FR-1.1: Add API token authentication (long-lived bearer tokens stored per device)
- FR-1.2: Add `POST /api/ext/login` — Authenticate with username + password, return API token + derivation params
- FR-1.3: Add `POST /api/ext/unlock` — Verify master password via token auth, return vault unlock confirmation
- FR-1.4: Add `GET /api/ext/vault/list` — List vault items (token auth)
- FR-1.5: Add `GET /api/ext/vault/match?url=` — Find items matching a URL hash
- FR-1.6: Add `POST /api/ext/vault/save` — Save new/updated item (token auth)
- FR-1.7: Add `POST /api/ext/vault/used` — Mark item as used
- FR-1.8: Add rate limiting on all extension API endpoints
- FR-1.9: Add `api_tokens` database table (user_id, token_hash, device_name, created_at, last_used_at, expires_at, revoked)
- FR-1.10: Add token management UI in user settings (view devices, revoke tokens)

### FR-2: Chrome/Edge Manifest V3 Browser Extension
- FR-2.1: Popup UI showing vault items for current site (filtered by URL match)
- FR-2.2: Autofill username + password into login forms on any website
- FR-2.3: Detect new login form submissions and offer to save credentials (autosave)
- FR-2.4: Password generator accessible from popup and context menu
- FR-2.5: Vault search from popup
- FR-2.6: Lock/unlock vault from popup
- FR-2.7: Login to AMPass server from extension options page
- FR-2.8: All encryption/decryption happens in the extension (service worker or offscreen document), never on the server
- FR-2.9: Vault key stored in extension's session storage (cleared on browser close or lock timeout)
- FR-2.10: Extension icon badge shows number of matching credentials for current tab

### FR-3: Autofill Behavior
- FR-3.1: Detect login forms (username + password fields) on page load
- FR-3.2: Show inline autofill icon on detected fields
- FR-3.3: On icon click or keyboard shortcut, fill credentials from matched vault item
- FR-3.4: Support multiple matches (show picker if >1 credential for a domain)
- FR-3.5: Never inject credentials without user action (no auto-fill on page load)
- FR-3.6: Clear filled credentials from page memory after navigation

### FR-4: Autosave
- FR-4.1: Detect form submissions containing username + password
- FR-4.2: Show notification bar offering to save new credentials
- FR-4.3: If URL matches existing item, offer to update instead
- FR-4.4: Encrypt and save via extension API

### FR-5: Firefox Support (Optional)
- FR-5.1: Port Manifest V3 extension to Firefox (Manifest V3 with Firefox-specific APIs)
- FR-5.2: Use browser.* namespace with WebExtension polyfill
- FR-5.3: Same feature set as Chrome/Edge version

### FR-6: Tauri Desktop App (Optional)
- FR-6.1: Wrap the AMPass web vault in a Tauri window
- FR-6.2: Add system tray icon with quick actions (lock, generate password, open vault)
- FR-6.3: Add global keyboard shortcut to open vault or autofill
- FR-6.4: Store vault key in OS keychain (Windows Credential Manager / macOS Keychain)
- FR-6.5: Auto-lock on system idle/sleep
- FR-6.6: Optional native messaging bridge to communicate with browser extension

### FR-7: Native Messaging Bridge (Optional)
- FR-7.1: Desktop app registers as native messaging host
- FR-7.2: Extension can request vault data from desktop app (avoids re-entering master password)
- FR-7.3: Desktop app handles key storage securely in OS keychain
- FR-7.4: Fallback: extension works standalone without desktop app

## Non-Functional Requirements

- NFR-1: Extension must work without the desktop app installed
- NFR-2: PHP web app must continue working independently of extension/desktop
- NFR-3: No Node.js required for PHP production deployment
- NFR-4: Extension build uses TypeScript + bundler (Vite/webpack)
- NFR-5: Desktop app build uses Rust (Tauri) + TypeScript frontend
- NFR-6: All client-side crypto uses Web Crypto API (same as web vault)
- NFR-7: Extension must pass Chrome Web Store review requirements
- NFR-8: API tokens must be revocable and have configurable expiry
- NFR-9: Extension must handle server unreachable gracefully (show cached item count, lock vault)
