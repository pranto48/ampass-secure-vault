# AMPass — OneDrive Backup Setup Guide

## Security Warning

AMPass backup, OneDrive upload, remote backup, and restore features require professional security audit before real credential storage.

## Overview

AMPass can upload encrypted `.ampass-backup` files to Microsoft OneDrive. Only encrypted files are uploaded — OneDrive never receives plaintext database dumps or vault data.

## Prerequisites

- A Microsoft account (personal or organizational)
- Access to Azure Portal (free with any Microsoft account)
- AMPass server accessible via HTTPS (or localhost for development)

## Step 1 — Create Azure App Registration

1. Open [Azure App Registrations](https://portal.azure.com/#blade/Microsoft_AAD_RegisteredApps/ApplicationsListBlade)
2. Click **New registration**
3. Fill in:
   - Name: `AMPass Backup`
   - Supported account types: **Accounts in any organizational directory and personal Microsoft accounts**
   - Redirect URI type: **Web**
   - Redirect URI: `YOUR_APP_URL/admin/backup-destinations/onedrive-callback`
     - Example: `http://localhost/ampass/admin/backup-destinations/onedrive-callback`
     - Example: `https://yourdomain.com/ampass/admin/backup-destinations/onedrive-callback`
4. Click **Register**
5. Copy the **Application (client) ID** — this is your Client ID

## Step 2 — Add API Permissions

1. In your Azure App, go to **API permissions**
2. Click **Add a permission**
3. Select **Microsoft Graph**
4. Select **Delegated permissions**
5. Add:
   - `Files.ReadWrite`
   - `offline_access`
6. Click **Add permissions**

## Step 3 — Create Client Secret

1. Go to **Certificates & secrets**
2. Click **New client secret**
3. Description: `AMPass Backup`
4. Expiry: Choose appropriate duration (recommended: 24 months)
5. Click **Add**
6. **Copy the Value immediately** — it won't be shown again

## Step 4 — Configure in AMPass

1. Go to **Admin → Backup Destinations**
2. In the "OneDrive Backup Setup" section:
   - Enter your **Client ID**
   - Enter your **Client Secret**
   - Set folder name (default: `AMPass Backups`)
3. Click **Save OneDrive Settings**

## Step 5 — Connect Microsoft Account

1. After saving, find your OneDrive destination in the table
2. Click **Connect** button
3. You'll be redirected to Microsoft login
4. Sign in and authorize AMPass to access your files
5. You'll be redirected back to AMPass
6. Status should show **Connected**

## Usage

1. Create an encrypted backup in **Admin → Backups**
2. Go to **Admin → Backup Destinations**
3. Select the backup and OneDrive destination
4. Click **Upload to Remote**

## Troubleshooting

### "OneDrive is not configured yet"
- Enter Client ID and Client Secret in the edit form
- Save settings before trying to connect

### "Invalid redirect URI"
- The redirect URI in Azure must exactly match what AMPass shows
- Check for trailing slashes, http vs https, correct path

### "Token exchange failed"
- Client Secret may have expired — create a new one in Azure
- Verify Client ID matches the Azure app

### "Reconnect OneDrive"
- Refresh token expired (usually after 90 days of inactivity)
- Click "Reconnect" to re-authorize

### "Wrong Microsoft account"
- Sign out of Microsoft in your browser first
- Then click Connect to choose the correct account

## Security Notes

- Refresh token is stored encrypted (AES-256-GCM with APP_SECRET)
- Tokens are never logged
- Only encrypted `.ampass-backup` files are uploaded
- OneDrive never receives plaintext vault data
- OAuth state token prevents CSRF on callback
- Client Secret is encrypted at rest

## AMPass Desktop App — Server URL

The AMPass Desktop app can connect to any AMPass server. On first launch, enter your server URL:

- Local XAMPP: `http://localhost/ampass` or `http://localhost/ampass-secure-vault/ampass`
- cPanel: `https://yourdomain.com/ampass`
- Custom path: Enter the full URL to your AMPass installation

The desktop app uses the same Extension API as the browser extension. The server URL is saved locally and the auth token is stored in the OS keychain.
