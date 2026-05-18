# AMPass Browser Extension — Release Package

## Contents

Copy the following from `clients/browser-extension/` to create the release package:

```
browser-extension/
├── manifest.json
├── README.md
├── SECURITY.md
├── src/
│   ├── background/service-worker.js
│   ├── content/
│   │   ├── form-detector.js
│   │   ├── autofill.js
│   │   └── save-detector.js
│   ├── popup/
│   │   ├── index.html
│   │   ├── popup.css
│   │   └── popup.js
│   ├── options/
│   │   ├── options.html
│   │   ├── options.css
│   │   └── options.js
│   └── shared/
│       ├── api-client.js
│       ├── crypto-client.js
│       ├── domain-utils.js
│       ├── native-client.js
│       ├── password-generator.js
│       ├── security.js
│       └── storage.js
├── assets/icons/
│   ├── icon-16.png    (replace placeholders with real PNGs)
│   ├── icon-32.png
│   ├── icon-48.png
│   └── icon-128.png
└── test-pages/
    └── login-test.html
```

## Before Distribution

1. Replace placeholder icon PNGs with actual icons
2. Update `manifest.json` description if needed
3. Remove `test-pages/` for Chrome Web Store submission
4. Create a privacy policy page (required by Chrome Web Store)

## Excluded from release

- No secrets, tokens, or credentials
- No `.git/` directory
- No development notes
