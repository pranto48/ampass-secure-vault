# AMPass — Browser-to-Desktop Bridge

## Security Warning

AMPass browser-to-desktop unlock and native messaging require professional security audit before real credential storage.

## Overview

The AMPass browser extension can communicate with the AMPass Desktop app via Chrome Native Messaging. This enables:

- Opening the desktop unlock window from a website's password field
- Checking desktop vault lock status
- Password generation via desktop app
- Future: direct autofill coordination

## How It Works

1. User clicks AMPass field icon on a website login page
2. If vault is locked, extension shows "Open AMPass" button
3. Clicking "Open AMPass" sends a native message to the desktop app
4. Desktop app shows/focuses its unlock window
5. User enters master password in desktop app
6. User clicks field icon again to autofill

## Architecture

```
Browser Extension ←→ Native Messaging Host ←→ AMPass Desktop App
     (Chrome)         (stdin/stdout JSON)        (Tauri window)
```

The native messaging host is the AMPass Desktop executable itself, running in native-messaging mode. It communicates with the running GUI instance via a signal file in the AMPass data directory.

## Signal File IPC

When the browser requests `open_unlock_window`:
1. Native messaging host writes `unlock_signal.json` to AMPass data directory
2. Running Tauri app polls this file every 2 seconds
3. When detected, Tauri shows/focuses the main window and emits `show-unlock-from-browser` event
4. Frontend shows the unlock screen

## Setup

### Automatic (via Desktop App)
- Install AMPass Desktop
- Enable "Browser Extension Bridge" in Desktop Settings
- The installer registers the native messaging host manifest

### Manual Setup (Windows)

1. Create native messaging host manifest at:
   `%LOCALAPPDATA%\AMPass\com.ampass.desktop.json`

```json
{
  "name": "com.ampass.desktop",
  "description": "AMPass Desktop Native Messaging Host",
  "path": "C:\\Path\\To\\AMPass.exe",
  "type": "stdio",
  "allowed_origins": [
    "chrome-extension://YOUR_EXTENSION_ID/"
  ]
}
```

2. Add Windows Registry key:
   `HKCU\Software\Google\Chrome\NativeMessagingHosts\com.ampass.desktop`
   Value: path to the manifest JSON file

3. For Edge, use:
   `HKCU\Software\Microsoft\Edge\NativeMessagingHosts\com.ampass.desktop`

### Enable in Extension
- Open extension options/settings
- Enable "Use Desktop Bridge"

## Message Types

| Type | Direction | Description |
|------|-----------|-------------|
| ping | ext→desktop | Check if host is alive |
| get_status | ext→desktop | Get vault lock status + version |
| open_unlock_window | ext→desktop | Show/focus desktop unlock UI |
| focus_main_window | ext→desktop | Focus desktop window |
| lock | ext→desktop | Lock the vault |
| unlock_request | ext→desktop | Check if vault is unlocked |
| generate_password | ext→desktop | Generate password via OS random |
| audit_event | ext→desktop | Log an audit event |

## Security

- No plaintext passwords sent through native messaging
- No master password sent through native messaging
- No vault key sent through native messaging
- Signal file contains only action/reason/page_host — never secrets
- Desktop app must be unlocked by user entering master password in the GUI
- Native messaging host validates message types against strict allowlist
- Maximum message size enforced (1MB)

## Idle Lock Behavior

- Browser extension: locks after 30 minutes idle (default)
- Desktop app: locks after 30 minutes idle (default)
- Lock only clears vault key from memory
- Trusted browser token and derivation params are preserved
- Trusted PC token and derivation params are preserved
- Next interaction asks only master password

## Troubleshooting

### "AMPass Desktop bridge not available"
- Ensure AMPass Desktop is installed
- Enable "Use Desktop Bridge" in extension settings
- Check that native messaging host manifest is registered
- Restart browser after installing

### Desktop doesn't open when clicking "Open AMPass"
- Ensure AMPass Desktop is running (check system tray)
- The signal file polling runs every 2 seconds — wait briefly
- Check AMPass data directory for `unlock_signal.json`

### Extension shows "Click the extension icon to unlock"
- Desktop bridge is not available or disabled
- Use the extension popup to unlock instead
