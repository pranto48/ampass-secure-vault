# AMPass Desktop App — Release Package

## Contents

The release package is built by Tauri:

```bash
cd clients/desktop-tauri
cargo tauri build
```

Output files:
```
src-tauri/target/release/bundle/
├── msi/AMPass_1.0.0_x64.msi       # Windows Installer
└── nsis/AMPass_1.0.0_x64-setup.exe # NSIS Installer
```

## Source Structure

```
clients/desktop-tauri/
├── src-tauri/
│   ├── Cargo.toml
│   ├── tauri.conf.json
│   ├── build.rs
│   ├── icons/
│   └── src/
│       ├── main.rs
│       ├── lib.rs
│       ├── keychain.rs
│       ├── storage.rs
│       ├── tray.rs
│       ├── lock.rs
│       ├── backup.rs
│       └── native_messaging.rs
├── src/
│   ├── index.html
│   ├── css/app.css
│   └── js/
│       ├── app.js
│       ├── crypto.js
│       └── api-client.js
├── README.md
├── SPEC.md
└── .gitignore
```

## Before Distribution

1. Replace placeholder icons with actual PNG/ICO files
2. Code sign the installer (requires Windows code signing certificate)
3. Test on clean Windows 10/11 machine
4. Verify auto-lock works after idle
5. Verify wipe local data removes all traces

## Excluded from release

- `src-tauri/target/` (build artifacts — gitignored)
- No secrets, tokens, or credentials
- No `.git/` directory
- No test databases
