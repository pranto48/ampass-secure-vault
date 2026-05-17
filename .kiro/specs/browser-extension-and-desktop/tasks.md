# Tasks: AMPass Browser Extension & Desktop App

## Phase 1: Stabilize & Document Current API

- [ ] 1. Document all existing API endpoints with request/response examples in `ampass/docs/api.md`
- [ ] 2. Add API versioning header support (`X-AMPass-Version`) to `App.php`
- [ ] 3. Add CORS headers support for extension origin in `Security.php` (configurable allowed origins)
- [ ] 4. Verify all existing API endpoints return consistent JSON error format `{error: string, code: int}`
- [ ] 5. Add `OPTIONS` preflight handler for CORS in the router

### Files created/changed:
- `ampass/docs/api.md` (NEW)
- `ampass/app/core/Security.php` (MODIFIED — add CORS method)
- `ampass/app/core/App.php` (MODIFIED — add OPTIONS handler, version header)

---

## Phase 2: Extension-Ready API Endpoints

- [ ] 6. Create `database/migrations/001_api_tokens.sql` with the `api_tokens` table schema
- [ ] 7. Create `app/models/ApiToken.php` model (create, validate, revoke, list, cleanup expired)
- [ ] 8. Create `app/controllers/api/ExtApiController.php` with token-based auth
- [ ] 9. Implement `POST /api/ext/login` — validate credentials, generate token, return derivation params
- [ ] 10. Implement `POST /api/ext/logout` — revoke current token
- [ ] 11. Implement `POST /api/ext/unlock` — verify master password via token auth
- [ ] 12. Implement `GET /api/ext/vault/list` — list encrypted vault items (token auth)
- [ ] 13. Implement `GET /api/ext/vault/match?url_hash=` — find items by URL hash
- [ ] 14. Implement `POST /api/ext/vault/save` — create/update item (token auth)
- [ ] 15. Implement `POST /api/ext/vault/used` — mark item as used
- [ ] 16. Implement `GET /api/ext/tokens` — list user's active tokens
- [ ] 17. Implement `POST /api/ext/tokens/revoke` — revoke a token by ID
- [ ] 18. Add rate limiting to all extension endpoints (per-token + per-IP)
- [ ] 19. Add audit logging for extension login, unlock, save, and token operations
- [ ] 20. Add token management section to user settings view (`app/views/settings/tokens.php`)
- [ ] 21. Update `SettingsController.php` to handle token listing and revocation from web UI

### Files created/changed:
- `ampass/database/migrations/001_api_tokens.sql` (NEW)
- `ampass/app/models/ApiToken.php` (NEW)
- `ampass/app/controllers/api/ExtApiController.php` (NEW)
- `ampass/app/core/App.php` (MODIFIED — route `/api/ext/*`)
- `ampass/app/views/settings/tokens.php` (NEW)
- `ampass/app/controllers/SettingsController.php` (MODIFIED)

---

## Phase 3: Chrome/Edge Manifest V3 Extension

- [ ] 22. Initialize `extension/` directory with `package.json`, `tsconfig.json`, `vite.config.ts`
- [ ] 23. Create `extension/manifest.json` (Manifest V3, permissions: activeTab, storage, alarms)
- [ ] 24. Create `extension/src/lib/crypto.ts` — port AMPassCrypto from `crypto.js` to TypeScript
- [ ] 25. Create `extension/src/lib/api.ts` — HTTP client with bearer token auth
- [ ] 26. Create `extension/src/lib/types.ts` — TypeScript interfaces for vault items, API responses
- [ ] 27. Create `extension/src/lib/storage.ts` — wrapper for chrome.storage.session/local
- [ ] 28. Create `extension/src/background/service-worker.ts` — main background script
  - Token management
  - Vault key storage (in-memory + session storage)
  - Message handling from popup/content scripts
  - Alarm for auto-lock timeout
  - Badge update with match count
- [ ] 29. Create `extension/src/popup/popup.html` + `popup.ts` + `popup.css`
  - Login/unlock state
  - Vault item list for current site
  - Search across all items
  - Quick copy username/password
  - Password generator
  - Lock button
- [ ] 30. Create `extension/src/options/options.html` + `options.ts`
  - Server URL configuration
  - Login to AMPass server
  - Auto-lock timeout setting
  - About/version info
- [ ] 31. Create extension icons (16, 32, 48, 128px) in `extension/src/assets/icons/`
- [ ] 32. Build and test extension locally (load unpacked in Chrome)

### Files created:
- `extension/` directory with full structure (see design.md)

---

## Phase 4: Autofill, Autosave, Generator

- [ ] 33. Create `extension/src/content/detector.ts` — detect login forms on pages
  - Find `<input type="password">` elements
  - Find associated username/email fields
  - Identify form boundaries
  - Handle SPA navigation (MutationObserver)
- [ ] 34. Create `extension/src/content/autofill.ts` — inject credentials into forms
  - Show AMPass icon overlay on detected fields
  - On click: request credentials from service worker
  - Fill fields with proper event dispatching (input, change, blur events)
  - Handle multiple matches (show picker dropdown)
- [ ] 35. Create `extension/src/content/autosave.ts` — detect form submissions
  - Listen for form submit events
  - Capture username + password values before submission
  - Send to service worker for encryption + save offer
  - Show notification bar for save/update confirmation
- [ ] 36. Add password generator to popup with same options as web vault
- [ ] 37. Add context menu integration ("Generate Password", "Open AMPass Vault")
- [ ] 38. Add keyboard shortcut support (Ctrl+Shift+L to autofill, Ctrl+Shift+G to generate)
- [ ] 39. Test autofill on common sites (Google, GitHub, Amazon, banking sites)

### Files created/changed:
- `extension/src/content/detector.ts` (NEW)
- `extension/src/content/autofill.ts` (NEW)
- `extension/src/content/autosave.ts` (NEW)
- `extension/src/content/content.css` (NEW — autofill icon styles)
- `extension/src/popup/` (MODIFIED — add generator tab)
- `extension/manifest.json` (MODIFIED — add content_scripts, commands)

---

## Phase 5: Firefox Support (Optional)

- [ ] 40. Create `extension/manifest.firefox.json` with Firefox-specific adjustments
- [ ] 41. Add WebExtension polyfill (`webextension-polyfill`) to handle `browser.*` vs `chrome.*`
- [ ] 42. Adjust service worker to use event pages if needed for Firefox compatibility
- [ ] 43. Test on Firefox Developer Edition
- [ ] 44. Add build script that outputs both Chrome and Firefox builds

### Files created/changed:
- `extension/manifest.firefox.json` (NEW)
- `extension/package.json` (MODIFIED — add polyfill dep)
- `extension/vite.config.ts` (MODIFIED — multi-target build)
- `extension/scripts/build.ts` (NEW — build script for both targets)

---

## Phase 6: Tauri Desktop App (Optional)

- [ ] 45. Initialize `desktop/` with `npm create tauri-app`
- [ ] 46. Configure `tauri.conf.json` — window settings, system tray, single instance
- [ ] 47. Create Tauri frontend that loads the AMPass web vault URL or a local UI
- [ ] 48. Add system tray with menu (Open Vault, Lock, Generate Password, Quit)
- [ ] 49. Add global keyboard shortcut (configurable) to open vault window
- [ ] 50. Implement OS keychain integration for vault key storage (Rust `keyring` crate)
- [ ] 51. Add auto-lock on system idle/sleep (Tauri event listeners)
- [ ] 52. Add auto-start on login option

### Files created:
- `desktop/` directory with Tauri project structure

---

## Phase 7: Native Messaging Bridge (Optional)

- [ ] 53. Add native messaging host manifest for Chrome/Firefox in desktop app
- [ ] 54. Implement native messaging protocol in Tauri (stdin/stdout JSON messages)
- [ ] 55. Extension detects if desktop app is available via native messaging ping
- [ ] 56. If desktop app available: extension requests vault key from desktop (avoids re-entering master password)
- [ ] 57. If desktop app unavailable: extension falls back to standalone mode (current behavior)
- [ ] 58. Add connection status indicator in extension popup

### Files created/changed:
- `desktop/src-tauri/src/native_messaging.rs` (NEW)
- `desktop/native-messaging-host.json` (NEW — Chrome manifest)
- `extension/src/lib/native.ts` (NEW — native messaging client)
- `extension/src/background/service-worker.ts` (MODIFIED — native messaging integration)

---

## Phase 8: Tests, Documentation, Packaging

- [ ] 59. Write unit tests for `extension/src/lib/crypto.ts` (verify encrypt/decrypt roundtrip)
- [ ] 60. Write unit tests for `extension/src/lib/api.ts` (mock HTTP responses)
- [ ] 61. Write integration tests for `ExtApiController.php` (token auth flow)
- [ ] 62. Write E2E test for autofill on a test page
- [ ] 63. Update `ampass/README.md` with extension setup instructions
- [ ] 64. Create `extension/README.md` with build/install/development instructions
- [ ] 65. Create `desktop/README.md` with build instructions
- [ ] 66. Add Chrome Web Store listing assets (screenshots, description, privacy policy)
- [ ] 67. Create release packaging script (zip for Chrome, xpi for Firefox)
- [ ] 68. Add `.github/workflows/` CI for extension build + PHP lint (optional)

### Files created:
- `extension/tests/` (NEW)
- `extension/README.md` (NEW)
- `desktop/README.md` (NEW)
- `ampass/docs/api.md` (UPDATED)
- `extension/scripts/package.ts` (NEW — release packaging)
