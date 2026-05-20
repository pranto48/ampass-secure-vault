# AMPass — Versioning System

## Version Format

AMPass uses GitHub commit count as the version number:

- **Display format**: `V1.{commit_count}` (e.g., V1.61)
- **Semver format**: `1.{commit_count}.0` (e.g., 1.61.0)
- **Major version**: Always 1 (until a breaking rewrite)

## How It Works

The version is derived from the total number of commits on the `main` branch of:
https://github.com/pranto48/ampass-secure-vault

Example: If the repository has 61 commits, the version is **V1.61**.

## Version Files

The sync script updates these files:

| File | Field | Example |
|------|-------|---------|
| `ampass/app/version.php` | AMPASS_VERSION_DISPLAY | V1.61 |
| `ampass/app/version.php` | AMPASS_VERSION_SEMVER | 1.61.0 |
| `clients/browser-extension/manifest.json` | version | 1.61.0 |
| `clients/browser-extension/manifest.json` | version_name | V1.61 |
| `clients/desktop-tauri/src-tauri/tauri.conf.json` | version | 1.61.0 |
| `clients/desktop-tauri/src-tauri/Cargo.toml` | version | 1.61.0 |
| `ampass/docs/version.json` | all fields | — |

## Syncing Versions

Run before building or releasing:

```bash
php scripts/sync-version.php
```

The script detects commit count via:
1. Local `.git` directory (if available): `git rev-list --count HEAD`
2. GitHub API (fallback): Parses Link header pagination

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `GITHUB_TOKEN` | (none) | Optional, for higher API rate limits |
| `AMPASS_REPO_OWNER` | pranto48 | GitHub repository owner |
| `AMPASS_REPO_NAME` | ampass-secure-vault | GitHub repository name |
| `AMPASS_BRANCH` | main | Branch to count commits from |

## Build Workflow

### Desktop App
```bash
php scripts/sync-version.php
cd clients/desktop-tauri
cargo tauri build
```

### Browser Extension
```bash
php scripts/sync-version.php
# Then reload unpacked extension in chrome://extensions
```

### Web App
```bash
php scripts/sync-version.php
# version.php is loaded automatically on next request
```

## Where Version is Displayed

- **Web**: Admin panel, updates page, footer
- **Desktop**: Settings/About page, window title
- **Extension**: Popup footer, options page
- **Downloads page**: Shows latest version for each product

## Updater Integration

The updater uses version info for comparison:
- **Release mode**: Compares semver tags (e.g., 1.61.0 vs 1.62.0)
- **Branch mode**: Compares commit SHA (different SHA = update available)
- **Display**: Shows "V1.61 → V1.62" in update notifications

## Security

- `version.php` contains NO secrets (only version numbers and commit SHA)
- `sync-version.php` is CLI-only (exits with 403 if accessed via web)
- GitHub token (if used) is read from environment variable only
- Version files are safe to commit to version control
