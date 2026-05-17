---
inclusion: fileMatch
fileMatchPattern: "extension/**"
---

# AMPass Browser Extension Development Guide

## Tech Stack
- TypeScript (strict mode)
- Vite for bundling
- Chrome Manifest V3
- Web Crypto API (same algorithms as web vault)

## Security Rules for Extension Code

1. **Vault key lives ONLY in the service worker** — never in content scripts or popup.
2. **Content scripts request decrypted data** from service worker via `chrome.runtime.sendMessage` — they never hold the vault key.
3. **Plaintext credentials in content scripts** exist only during the fill operation and must be cleared immediately after.
4. **API tokens stored in `chrome.storage.session`** — automatically cleared when browser closes.
5. **Server URL stored in `chrome.storage.local`** — persists across sessions.
6. **Never log plaintext passwords** or vault data to console in production builds.
7. **All API calls use HTTPS** — reject HTTP server URLs.

## Message Protocol (Service Worker ↔ Content/Popup)

```typescript
// Messages from popup/content to service worker
type Message =
  | { type: 'GET_STATUS' }
  | { type: 'LOGIN', payload: { serverUrl: string, username: string, password: string } }
  | { type: 'UNLOCK', payload: { masterPassword: string } }
  | { type: 'LOCK' }
  | { type: 'GET_MATCHES', payload: { url: string } }
  | { type: 'GET_ITEM', payload: { id: number } }
  | { type: 'DECRYPT_ITEM', payload: { id: number } }
  | { type: 'SAVE_ITEM', payload: { encryptedData: string, iv: string, ... } }
  | { type: 'SEARCH', payload: { query: string } }
  | { type: 'GENERATE_PASSWORD', payload: { options: PasswordOptions } }

// Responses
type Response =
  | { success: true, data: any }
  | { success: false, error: string }
```

## Build Commands

```bash
cd extension
npm install          # Install dev dependencies
npm run dev          # Watch mode (rebuilds on change)
npm run build        # Production build → dist/chrome/
npm run build:firefox # Firefox build → dist/firefox/
npm run test         # Run unit tests
npm run package      # Create .zip for store submission
```

## Loading Unpacked Extension (Development)

1. Run `npm run dev` in the `extension/` directory
2. Open `chrome://extensions/`
3. Enable "Developer mode"
4. Click "Load unpacked" → select `extension/dist/chrome/`
5. Extension reloads automatically on rebuild

## Crypto Implementation Notes

The extension's `crypto.ts` must produce identical output to the web vault's `crypto.js`:
- Same PBKDF2 parameters (SHA-256, configurable iterations)
- Same AES-GCM parameters (256-bit key, 96-bit IV)
- Same hex encoding for ciphertext and IVs
- Same HMAC-SHA256 for url_hash matching

Test by encrypting with the extension and decrypting with the web vault (and vice versa).
