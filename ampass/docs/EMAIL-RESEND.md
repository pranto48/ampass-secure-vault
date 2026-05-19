# AMPass — Email Integration (Resend API)

## Overview

AMPass uses the [Resend](https://resend.com) email API for sending security notifications, OTP codes, and password reset emails. No Composer dependencies required — uses cURL directly.

## Setup

1. Create a free account at [resend.com](https://resend.com)
2. Add and verify your sending domain
3. Create an API key at resend.com/api-keys
4. In AMPass: Admin → Email Settings
5. Enter your API key, from email, and from name
6. Enable desired email notifications
7. Click "Send Test Email" to verify

## Email Types

| Type | Description |
|------|-------------|
| Security alerts | Login from new device, password changed, device revoked |
| Password reset | Reset link with 30-minute expiry |
| Email 2FA/OTP | 6-digit code for login verification |
| New device alerts | Browser extension or desktop app connected |
| Backup alerts | Backup created, downloaded, or restored |

## Security

- API key encrypted at rest using APP_SECRET
- Only masked key shown in admin UI after saving
- OTP codes stored as hashes only
- No passwords or vault data included in emails
- Email logs store hashed recipient (not plaintext email)
- Rate limited sending

## Troubleshooting

### "Email not configured"
- Ensure API key is saved in Admin → Email Settings
- Ensure from email is set and domain is verified in Resend

### "Invalid Resend API key"
- Re-enter the API key (it may have been rotated)
- Check resend.com dashboard for key status

### "Rate limited by Resend"
- Resend has sending limits on free tier
- Wait and retry, or upgrade your Resend plan

### Test email not received
- Check spam/junk folder
- Verify domain DNS records in Resend dashboard
- Check Admin → Email Settings → Send Test Email for error details

## ⚠️ Not Production Ready

AMPass email system requires professional security audit before real credential storage.
