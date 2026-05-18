# AMPass Desktop App — QA Checklist

## Build

- [ ] `cargo tauri dev` starts without errors
- [ ] `cargo tauri build` produces .exe and .msi in bundle/
- [ ] App launches on Windows 10/11
- [ ] Opening index.html in browser (without Tauri) shows friendly message, does not crash

## Connection

- [ ] Welcome screen shows server URL input
- [ ] Connecting to invalid URL shows error
- [ ] Connecting to valid localhost AMPass works
- [ ] Connecting to HTTPS AMPass works

## Authentication

- [ ] Login with valid credentials → proceeds to unlock
- [ ] Login with wrong password → shows error
- [ ] Login rate limited after 5 attempts

## Vault Unlock

- [ ] First-time vault initialization works (needs_setup=true detected)
- [ ] Vault init sends encrypted key to /api/extension/vault/init-key
- [ ] Normal unlock with master password works
- [ ] Wrong master password shows error
- [ ] Vault key stored in memory only (not on disk)
- [ ] searchKey derived from vault key after unlock
- [ ] searchKey cleared on lock/logout/wipe

## Vault Display

- [ ] Quick Access shows stats (total, favorites, weak, score)
- [ ] Web Accounts lists login items
- [ ] Identities lists identity items
- [ ] Secure Memos lists secure notes
- [ ] Items decrypt and show title/username
- [ ] Search filters items instantly

## Item Operations

- [ ] Add new web account → saves encrypted to server
- [ ] Item appears in web vault after sync
- [ ] Copy username works
- [ ] Copy password works
- [ ] Clipboard clears after 30 seconds
- [ ] Item detail modal shows fields

## Password Generator

- [ ] Generates password on page load
- [ ] Length slider works
- [ ] Checkboxes toggle character sets
- [ ] Copy button works
- [ ] Strength bar updates

## Sync

- [ ] Sync button fetches latest from server
- [ ] Sync time updates
- [ ] Background sync runs every 5 minutes
- [ ] Offline mode shows cached data with warning

## Lock/Logout

- [ ] Lock button clears vault key from memory
- [ ] Lock shows unlock screen
- [ ] Auto-lock after inactivity (15 min default)
- [ ] Tray "Lock Vault" works
- [ ] Logout clears token and shows login
- [ ] Wipe Local Cache removes all local data

## Security

- [ ] No plaintext passwords in %APPDATA%/ampass/
- [ ] Bearer token in Windows Credential Manager
- [ ] cache.enc is encrypted (not readable as JSON)
- [ ] Closing app clears vault key
- [ ] config.json only contains server URL (non-secret)
- [ ] All authenticated extension API endpoints reject HTTP (except localhost)
- [ ] searchKey is null after lock/logout/wipe
- [ ] derivationParams cleared on logout

## ⚠️ Not Production Ready

AMPass desktop app requires professional security audit before real credential storage.
