# AMPass — Browser Extension Installation Guide

## Requirements

- Google Chrome 110+ or Microsoft Edge 110+
- AMPass PHP server installed and running
- Extension API enabled (Admin → Browser Extensions)

## Load Unpacked (Development)

1. Open `chrome://extensions/` (or `edge://extensions/`)
2. Enable **Developer mode** (toggle top-right)
3. Click **Load unpacked**
4. Select the `clients/browser-extension/` folder
5. Note your extension ID (shown under the extension name)

## Configure

1. Click the AMPass extension icon in the toolbar
2. Enter your AMPass server URL (e.g., `https://yourdomain.com/ampass`)
3. Sign in with your AMPass username and password
4. Enter your master password to unlock the vault

## Server-Side Setup

1. Import `ampass/database/migrations/001_extension_tables.sql`
2. Admin Panel → Browser Extensions:
   - Enable Extension API: ✓
   - Allowed Origins: `chrome-extension://YOUR_EXTENSION_ID/`
   - Token Lifetime: 30 days
   - Max Devices: 10

## Features

- Autofill login forms (click AMPass icon on password field)
- Autosave new credentials (prompt after form submission)
- Update changed passwords (prompt after password change)
- Password generator (popup → Generate button)
- Vault search (popup search bar)
- Copy username/password (popup item actions)
- Auto-lock after inactivity (configurable)

## Settings

Extension icon → Settings (gear icon) → Options page:
- Server URL
- Device name
- Autofill behavior (click/ask/never)
- Autosave behavior (ask/never)
- Clipboard clear timer
- Lock timeout
- Desktop app bridge (optional)
- Theme

## Icons

The extension ships with placeholder icons. Replace files in `assets/icons/` with actual PNG images (16, 32, 48, 128px) before distribution.

## Security Notes

- Autofill is blocked on HTTP pages by default (except localhost)
- The extension never stores plaintext passwords
- Vault key is cleared when browser closes
- All encryption/decryption happens locally in the extension
- See `SECURITY.md` in the extension folder for full audit checklist
