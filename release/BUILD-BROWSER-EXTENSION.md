# Build AMPass Browser Extension

## No Build Step Required

The extension uses plain JavaScript — no bundler needed.

## Package for Distribution

### Windows (PowerShell)

```powershell
cd clients/browser-extension

# Create ZIP excluding dev files
Compress-Archive -Path manifest.json, src, assets, README.md, SECURITY.md `
  -DestinationPath "AMPass-browser-extension-v1.0.0.zip" -Force
```

### Manual

1. Navigate to `clients/browser-extension/`
2. Select: `manifest.json`, `src/`, `assets/`, `README.md`, `SECURITY.md`
3. Create a ZIP file named `AMPass-browser-extension-v1.0.0.zip`
4. Exclude: `test-pages/`, any `.git` files, `node_modules/`

## Upload to AMPass Downloads

1. Login to AMPass web app as admin
2. Go to Admin → Release Downloads
3. Upload the ZIP as "Chrome Extension"
4. Set version and release notes
5. Enable the release

## Before Distribution

- Replace placeholder icon PNGs with real icons (16, 32, 48, 128px)
- Remove `test-pages/` from the ZIP
- Verify manifest.json has correct version number

## Chrome Web Store (Future)

For Chrome Web Store submission:
1. Create a developer account at https://chrome.google.com/webstore/devconsole
2. Upload the ZIP
3. Add screenshots, description, privacy policy
4. Submit for review
