# AMPass Native Messaging

Enables communication between the AMPass browser extension and the AMPass desktop app via Chrome's Native Messaging protocol.

## ⚠️ Security Warning

**This native messaging implementation has NOT been professionally audited.**

Native messaging creates a local IPC channel between the browser extension and a native application. While Chrome restricts which extensions can connect (via `allowed_origins`), the security of this channel depends on:
- The integrity of the native host executable
- Proper extension ID configuration
- The desktop app's vault lock state
- OS-level process isolation

**Do NOT use for real credentials until professionally audited.**

---

## Overview

Native messaging is **optional**. The extension works standalone with the AMPass server API. When enabled:

1. Extension detects if the desktop app's native host is installed
2. If available, extension can query vault status, request password generation, and send audit events through the desktop app
3. If unavailable, extension falls back to direct server API communication
4. User must explicitly enable "Desktop App Bridge" in extension settings

---

## How to Install Native Host on Windows

### Prerequisites
- AMPass Desktop App installed (or built from source)
- Chrome/Edge with AMPass extension loaded
- Your extension ID (find at `chrome://extensions/` with Developer mode on)

### Steps

1. **Edit the manifest file** — Open `chrome-host-manifest.json` and replace `REPLACE_WITH_YOUR_EXTENSION_ID` with your actual extension ID:
   ```json
   "allowed_origins": [
     "chrome-extension://abcdefghijklmnopqrstuvwxyz123456/"
   ]
   ```

2. **Run the install script** as Administrator:
   ```cmd
   install-windows.bat
   ```

3. **Verify installation** — Check that the registry key exists:
   ```cmd
   reg query "HKCU\Software\Google\Chrome\NativeMessagingHosts\com.ampass.desktop"
   ```

4. **Enable in extension** — Go to AMPass extension options → Desktop App Bridge → Enable

5. **Test connection** — The options page will show connection status

### Manual Installation

If the batch script doesn't work:

1. Copy `ampass-native-host.exe` to `C:\Program Files\AMPass\`
2. Copy `chrome-host-manifest.json` to `C:\Program Files\AMPass\`
3. Edit the manifest to set correct path and extension ID
4. Add registry key:
   ```cmd
   reg add "HKCU\Software\Google\Chrome\NativeMessagingHosts\com.ampass.desktop" /ve /t REG_SZ /d "C:\Program Files\AMPass\chrome-host-manifest.json" /f
   ```

---

## How to Uninstall Native Host

Run as Administrator:
```cmd
uninstall-windows.bat
```

Or manually:
```cmd
reg delete "HKCU\Software\Google\Chrome\NativeMessagingHosts\com.ampass.desktop" /f
reg delete "HKCU\Software\Microsoft\Edge\NativeMessagingHosts\com.ampass.desktop" /f
del "C:\Program Files\AMPass\ampass-native-host.exe"
del "C:\Program Files\AMPass\chrome-host-manifest.json"
```

---

## How to Test Native Messaging

### Quick Test

1. Install the native host (steps above)
2. Open AMPass extension options page
3. Enable "Desktop App Bridge"
4. The status should show "✅ Connected to AMPass Desktop v1.0.0"

### Manual Test

1. Open Chrome DevTools on the extension's service worker (`chrome://extensions/` → AMPass → "Inspect views: service worker")
2. In the console:
   ```javascript
   const port = chrome.runtime.connectNative('com.ampass.desktop');
   port.onMessage.addListener(msg => console.log('Response:', msg));
   port.postMessage({ type: 'ping', request_id: '1' });
   ```
3. Expected response: `{ type: "pong", success: true, data: { version: "1.0.0", app: "AMPass Desktop" }, request_id: "1" }`

---

## Message Protocol

All messages are JSON objects with a `type` field and optional `payload`.

### Request Format
```json
{
  "type": "message_type",
  "payload": { ... },
  "request_id": "unique-id"
}
```

### Response Format
```json
{
  "type": "response_type",
  "success": true,
  "data": { ... },
  "request_id": "matching-id"
}
```

### Message Types

| Type | Direction | Description | Requires Unlock |
|------|-----------|-------------|-----------------|
| `ping` | ext → app | Test connection | No |
| `get_status` | ext → app | Get vault lock state | No |
| `unlock_request` | ext → app | Ask user to unlock in desktop app | No |
| `lock` | ext → app | Lock the vault | No |
| `search_by_domain` | ext → app | Find items for a domain | Yes |
| `get_item_for_autofill` | ext → app | Get specific item data | Yes |
| `save_detected_login` | ext → app | Save new credential | Yes |
| `update_detected_login` | ext → app | Update existing credential | Yes |
| `generate_password` | ext → app | Generate a random password | No |
| `audit_event` | ext → app | Log an action | No |

### Security Rules for Messages

- `search_by_domain`, `get_item_for_autofill`, `save_detected_login`, `update_detected_login` all return an error if the vault is locked
- `unlock_request` does NOT unlock the vault — it tells the user to unlock in the desktop app UI
- The desktop app never sends the vault key or master password through native messaging
- Password generation uses OS-level cryptographic random
- All unknown message types are rejected

---

## Troubleshooting

### "Desktop app not found or not running"
- Ensure the AMPass desktop app is installed and running
- Check that the native host executable exists at the path in the manifest
- Verify the registry key points to the correct manifest file

### "Host responded but with error"
- The native host is reachable but returned an error
- Check if the vault is locked (some operations require unlock)
- Check the desktop app logs for details

### Extension ID mismatch
- The manifest's `allowed_origins` must exactly match your extension ID
- Format: `chrome-extension://YOUR_ID_HERE/` (note the trailing slash)
- Reload the extension after changing the manifest

### Registry not found
- Run `install-windows.bat` as Administrator
- Or manually add the registry key (see Manual Installation above)

### Chrome says "Native messaging host not found"
- The manifest JSON must be valid (no trailing commas)
- The `path` in the manifest must point to an existing `.exe`
- The `name` must be `com.ampass.desktop` (matching what the extension requests)

### Firefox differences
- Firefox uses `allowed_extensions` instead of `allowed_origins`
- Firefox uses the extension's ID (e.g., `ampass@ampass.local`) not the chrome-extension:// URL
- Registry path: `HKCU\Software\Mozilla\NativeMessagingHosts\com.ampass.desktop`

---

## File Structure

```
clients/native-messaging/
├── chrome-host-manifest.json      # Chrome/Edge native messaging manifest
├── edge-host-manifest.json        # Edge-specific (same format as Chrome)
├── firefox-host-manifest.json     # Firefox native messaging manifest
├── install-windows.bat            # Windows registry install script
├── uninstall-windows.bat          # Windows registry uninstall script
└── README.md                      # This file
```

The native host executable (`ampass-native-host.exe`) is built as part of the Tauri desktop app. When the desktop app is launched with `--native-messaging` flag, it runs in native messaging mode (stdin/stdout JSON protocol).

---

## Architecture

```
┌─────────────────────────────────────────────────────┐
│ Browser Extension (service worker)                   │
│                                                       │
│  NativeClient.sendMessage({ type: 'ping' })          │
│       │                                               │
│       │ chrome.runtime.connectNative()                │
│       ▼                                               │
└───────┬───────────────────────────────────────────────┘
        │ stdin/stdout (JSON with 4-byte length prefix)
        ▼
┌───────────────────────────────────────────────────────┐
│ Native Host (ampass-native-host.exe)                   │
│                                                         │
│  Reads from stdin → validates → processes → writes stdout│
│                                                         │
│  SECURITY:                                              │
│  - Only allowed extension IDs can connect (manifest)    │
│  - Vault must be unlocked for sensitive operations      │
│  - Never returns plaintext if locked                    │
│  - Never logs secrets                                   │
│  - Timeout on all operations                            │
└─────────────────────────────────────────────────────────┘
```

---

## Security Model

| Concern | Mitigation |
|---------|------------|
| Unauthorized extension connects | `allowed_origins` in manifest restricts to specific extension IDs |
| Secrets leaked via native messaging | Vault must be unlocked; desktop app controls what data is returned |
| Plaintext in transit | Native messaging uses OS-level stdio pipes (local only, not network) |
| Malicious native host | User must explicitly install the host; Chrome verifies manifest path |
| Process memory inspection | Same risk as any desktop app; OS process isolation applies |
| Extension disabled but host remains | Host only responds when connected; no persistent background process |

---

## License

MIT License
