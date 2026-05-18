# AMPass — Desktop App Installation Guide (Tauri)

## Requirements

- Windows 10/11 (64-bit)
- AMPass PHP server installed and running
- Extension API enabled on the server

## Install from Release

1. Download `AMPass_1.0.0_x64.msi` from releases
2. Run the installer
3. Launch AMPass from Start Menu or system tray

## Build from Source

### Prerequisites
- [Rust](https://rustup.rs/) stable toolchain
- [Node.js](https://nodejs.org/) 18+ (for Tauri CLI)
- Windows SDK (included with Visual Studio Build Tools)

### Build Steps
```bash
cd clients/desktop-tauri
cargo install tauri-cli --version "^2"

# Development (hot reload)
cargo tauri dev

# Production build
cargo tauri build
```

Output: `src-tauri/target/release/bundle/msi/AMPass_1.0.0_x64.msi`

## First Run

1. Launch AMPass desktop app
2. Enter your server URL (e.g., `https://yourdomain.com/ampass`)
3. Sign in with your AMPass credentials
4. Enter master password to unlock vault
5. Vault syncs and is cached locally (encrypted)

## Features

- Vault dashboard with search
- Password generator
- Copy username/password
- Encrypted offline cache
- System tray (lock/sync/quit)
- Auto-lock on idle (15 min default)
- Encrypted backup export/import
- Wipe local data option

## Data Storage

All local data in `%APPDATA%\ampass\`:
- `config.json` — Server URL, preferences (non-sensitive)
- `cache.enc` — Encrypted vault cache (AES-256-GCM with device key)

Credentials stored in Windows Credential Manager:
- Bearer token (for server auth)
- Device key (for cache encryption)

## Native Messaging (Optional)

To enable browser extension ↔ desktop app communication:
1. Run `clients/native-messaging/install-windows.bat` as Administrator
2. Edit manifest to add your extension ID
3. Enable "Desktop App Bridge" in extension settings

## Security

- Master password is never stored
- Vault key exists in memory only while unlocked
- Local cache is encrypted with device key from OS keychain
- Auto-lock on idle/sleep
- Wipe Local Data removes all local files and keychain entries
- See `SPEC.md` for full security model
