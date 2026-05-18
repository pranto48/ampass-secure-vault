# AMPass Browser Extension — Security Documentation

## ⚠️ CRITICAL WARNING

**This password manager and browser extension have NOT been professionally audited by a security firm.**

**Do NOT use this extension to store real production credentials until:**
1. A qualified security firm has audited the cryptographic implementation
2. Penetration testing has been conducted against the deployed backend
3. The extension has been reviewed for Chrome Web Store security requirements
4. The content script isolation model has been formally verified

**Use at your own risk. The authors accept no liability for data loss or security breaches.**

---

## Security Architecture

### Encryption Model
- **Algorithm**: AES-256-GCM (authenticated encryption)
- **Key Derivation**: PBKDF2 with SHA-256, 100,000 iterations
- **Vault Key**: Random 256-bit key, encrypted with user's derived key
- **All decryption happens locally** in the extension service worker
- **Server stores only**: ciphertext, IVs, salts, HMAC hashes, Argon2id password hashes

### Trust Boundaries

```
┌─────────────────────────────────────────────────────┐
│ TRUSTED: Extension Process (isolated from page JS)   │
│                                                       │
│  Service Worker: Holds vault key, performs crypto     │
│  Popup/Options: Receives decrypted data via messages │
│  Content Scripts: Isolated world, no vault key       │
└─────────────────────────────────────────────────────┘
         │ chrome.runtime messages (internal only)
         │
┌────────┴────────────────────────────────────────────┐
│ UNTRUSTED: Web Page Context                          │
│                                                       │
│  Page JavaScript cannot access extension variables   │
│  Content scripts run in isolated world               │
│  No window.postMessage used for secrets              │
└─────────────────────────────────────────────────────┘
```

---

## Security Audit Checklist

### ✅ Storage Security
- [x] **No plaintext vault passwords in chrome.storage** — Only the vault key hex is stored in `chrome.storage.session` (cleared on browser close). Vault items are stored as encrypted ciphertext.
- [x] **Master password never stored** — Used only transiently for PBKDF2 derivation, then discarded. The `unlockPassword` input is cleared immediately after use.
- [x] **Sensitive data in session storage only** — Auth token, vault key, and derivation params use `chrome.storage.session` which is automatically cleared when the browser closes.
- [x] **Settings in local storage are non-sensitive** — Only server URL, device name, UI preferences. No secrets.

### ✅ Memory Security
- [x] **Decrypted secrets kept only in memory** — Decrypted item data exists only as function return values and is not persisted.
- [x] **Autofill clears payload after use** — `payload.username = null; payload.password = null;` after filling fields.
- [x] **Popup clears password inputs** — Login and unlock password fields are cleared after submission.

### ✅ Autofill Security
- [x] **Autofill requires user action** — Default behavior is "click" (fill only after user clicks AMPass icon or popup button). Never auto-fills on page load.
- [x] **HTTP autofill blocked by default** — `autofill.js` checks protocol and blocks on HTTP unless explicitly allowed in settings or on localhost.
- [x] **Domain matching prevents phishing** — `getMatches()` compares base domains. Credentials for `google.com` won't fill on `g00gle.com`.
- [x] **Hidden fields not filled** — `form-detector.js` checks `isVisible()` and `isHidden()` before marking fields.
- [x] **Cross-origin iframes skipped** — Content scripts check `window.self !== window.top` and skip cross-origin frames.

### ✅ Content Script Isolation
- [x] **Content scripts don't expose secrets to page JS** — Run in Chrome's isolated world. Page JavaScript cannot access content script variables.
- [x] **No window.postMessage for secrets** — All communication uses `chrome.runtime.sendMessage` (extension-internal only).
- [x] **No global variables leaked** — Content scripts use IIFEs. The `Security` helper in save-detector is local, not on `window`.
- [x] **Sender verification** — Service worker verifies `sender.id === chrome.runtime.id` on all messages.

### ✅ Code Safety
- [x] **No eval()** — Not used anywhere in the extension.
- [x] **No new Function()** — Not used.
- [x] **No remote/CDN scripts** — All code is bundled locally.
- [x] **No unsafe innerHTML with untrusted data** — All user data passed through `Security.escapeHtml()` before insertion.
- [x] **CSP enforced** — Manifest declares `script-src 'self'; object-src 'none'`.

### ✅ Permissions
- [x] **Minimal permissions** — `storage`, `activeTab`, `scripting`, `clipboardWrite`, `alarms`. No `<all_urls>` host permission.
- [x] **Content scripts on `<all_urls>`** — Required for form detection on any site. Runs at `document_idle` and only in top frame (`all_frames: false`).
- [x] **No `tabs` permission** — Uses `activeTab` which only grants access to the current tab when user interacts with the extension.

### ✅ Network Security
- [x] **HTTPS required for production** — API client and popup show warnings for HTTP servers (except localhost).
- [x] **Bearer token auth** — Tokens are 64-char hex, SHA-256 hashed server-side. A database leak doesn't expose raw tokens.
- [x] **No sensitive data in URLs** — All sensitive data sent in request body, never query parameters.

### ✅ Clipboard Security
- [x] **Auto-clear timer** — Clipboard is cleared after configurable timeout (default 30 seconds).
- [x] **Only clears if unchanged** — Checks clipboard content before clearing to avoid wiping user's other copies.

### ✅ Logging
- [x] **No passwords in logs** — No `console.log` with sensitive data in production code.
- [x] **API errors sanitized** — Service worker catches errors and returns safe messages without internal details.

### ✅ Lock/Logout
- [x] **Auto-lock timeout** — Configurable alarm (default 15 minutes). Clears vault key from session storage.
- [x] **Manual lock** — Clears vault key and cached items immediately.
- [x] **Logout clears all session data** — `Storage.clearSession()` removes token, vault key, derivation params, cached items.
- [x] **Server-side token revocation** — Admin can revoke device from AMPass panel, immediately invalidating the extension's token.

### ✅ Autosave Security
- [x] **Always requires user confirmation** — Shows a prompt with "Save" / "Not now" buttons. Never saves silently.
- [x] **Skips autofilled forms** — Won't re-save credentials that were just autofilled by AMPass.
- [x] **Credentials cleared after save** — `data.password = null` after sending to service worker.

---

## Manual Security Test Checklist

Perform these tests before deploying the extension:

### Test 1: Wrong Domain Must Not Autofill
1. Add a credential for `example.com` in your vault
2. Navigate to `evil-example.com`
3. Open the AMPass popup
4. **Expected**: No matches shown for the current site. Autofill button not available.

### Test 2: HTTP Page Must Show Warning
1. Navigate to an HTTP page (not localhost)
2. Open the AMPass popup
3. **Expected**: Warning banner "Server is not using HTTPS" if server is HTTP.
4. Try to autofill on the HTTP page
5. **Expected**: Autofill blocked unless `allowHttpAutofill` is explicitly enabled in settings.

### Test 3: Locked Vault Must Not Fill
1. Lock the vault (click lock button in popup)
2. Navigate to a page with a login form
3. Open the popup
4. **Expected**: Unlock screen shown. No vault items accessible. Badge shows 0.

### Test 4: Revoked Extension Device Must Stop Working
1. Log in from the extension
2. Go to AMPass Admin → Browser Extensions → Revoke the device
3. Try to fetch vault items from the extension
4. **Expected**: API returns 401. Extension shows login screen.

### Test 5: Clipboard Clear Timer Must Run
1. Copy a password from the popup
2. Wait for the configured timeout (default 30 seconds)
3. Paste from clipboard
4. **Expected**: Clipboard is empty (or contains empty string).

### Test 6: Browser Close Must Not Leave Plaintext Secrets
1. Unlock the vault and use the extension
2. Close the browser completely
3. Reopen the browser and check `chrome.storage.session`
4. **Expected**: Session storage is empty. Vault key, token, and cached items are gone. User must re-authenticate.

### Test 7: Autosave Must Require Confirmation
1. Navigate to the test page (`test-pages/login-test.html`)
2. Fill in username and password manually
3. Submit the form
4. **Expected**: AMPass save prompt appears asking "Save login?" with Save/Not now buttons.
5. Click "Not now"
6. **Expected**: No credential saved. Prompt disappears.

### Test 8: Export/Import Remains Encrypted
1. Export vault from the web app
2. Open the exported JSON file
3. **Expected**: All `encrypted_data` fields contain hex ciphertext, not plaintext. No readable passwords, usernames, or notes.

---

## Known Limitations

1. **Service worker termination**: Chrome may terminate the service worker after ~5 minutes of inactivity. The vault key persists in `chrome.storage.session` and is restored, but in-memory cache is lost.

2. **Clipboard API limitations**: `navigator.clipboard.readText()` may fail if the popup loses focus. The clear timer uses a best-effort approach.

3. **Content script detection**: Form detection uses heuristics. Some non-standard login forms (custom web components, shadow DOM) may not be detected.

4. **No phishing database**: Domain matching is based on string comparison. The extension does not query a phishing database like Google Safe Browsing.

5. **Single-process trust**: If the browser process itself is compromised (malware with browser access), the vault key in session storage could be extracted.

6. **No hardware key support**: The extension does not support hardware security keys (FIDO2/WebAuthn) for vault unlock.

---

## Reporting Security Issues

If you discover a security vulnerability in this extension, please:
1. Do NOT open a public GitHub issue
2. Contact the maintainer privately
3. Allow reasonable time for a fix before disclosure

---

*Last updated: 2026-05-18*
