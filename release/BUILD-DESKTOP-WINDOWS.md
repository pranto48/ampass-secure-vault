# Build AMPass Desktop App for Windows

## Prerequisites

- [Rust](https://rustup.rs/) stable toolchain
- [Node.js](https://nodejs.org/) 18+ (for Tauri CLI)
- Windows 10/11 with Visual Studio Build Tools

## Build Steps

```bash
cd clients/desktop-tauri

# Install Tauri CLI (first time only)
cargo install tauri-cli --version "^2"

# Development mode (hot reload)
cargo tauri dev

# Production build
cargo tauri build
```

## Output Files

After `cargo tauri build`:

```
src-tauri/target/release/bundle/
├── nsis/
│   └── AMPass_1.0.0_x64-setup.exe    ← NSIS installer
└── msi/
    └── AMPass_1.0.0_x64.msi          ← MSI installer
```

## Upload to AMPass Downloads

1. Login to AMPass web app as admin
2. Go to Admin → Release Downloads
3. Upload the `.exe` file as "Windows EXE"
4. Upload the `.msi` file as "Windows MSI"
5. Set version number and release notes
6. Enable the release

## Icons

Replace placeholder icons in `src-tauri/icons/` with real PNG/ICO files before building for distribution.

## Code Signing

For production distribution, sign the installer with a Windows code signing certificate. Set environment variables before building:

```
TAURI_SIGNING_PRIVATE_KEY=...
TAURI_SIGNING_PRIVATE_KEY_PASSWORD=...
```
