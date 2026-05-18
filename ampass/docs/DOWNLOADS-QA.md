# AMPass Downloads Center — QA Checklist

## Page Access

- [ ] `/downloads` loads without authentication
- [ ] `/downloads` shows all product cards
- [ ] Warning banner about audit is visible
- [ ] Back to AMPass link works

## File Downloads

- [ ] Active release file downloads successfully
- [ ] Download increments counter in database
- [ ] Inactive file returns 404
- [ ] Non-existent file ID returns 404
- [ ] Direct access to `app_storage/releases/` returns 403

## Admin Upload (when implemented)

- [ ] Admin can access `/admin/releases` page
- [ ] Admin can upload .exe file as Windows EXE
- [ ] Admin can upload .msi file as Windows MSI
- [ ] Admin can upload .zip file as Chrome Extension
- [ ] Admin can upload .zip file as Edge Extension
- [ ] Admin can upload .xpi file as Firefox Extension
- [ ] Both Chrome and Edge extension uploads appear on /downloads if active
- [ ] Upload of .php file is rejected
- [ ] Upload of .js file is rejected
- [ ] Upload of .html file is rejected
- [ ] Upload of .svg file is rejected
- [ ] Upload of .sh/.bat/.ps1 file is rejected
- [ ] SHA-256 checksum is generated after upload
- [ ] File size is calculated after upload
- [ ] Stored filename is random (not original)
- [ ] CSRF token required on upload form
- [ ] Non-admin cannot access /admin/releases
- [ ] Admin can enable/disable release
- [ ] Admin can delete release (removes file + DB row)
- [ ] Download count increments on public download
- [ ] Inactive file returns 404 on public download
- [ ] Direct access to `app_storage/releases/` returns 403

## Admin Management

- [ ] Admin can enable/disable downloads page
- [ ] Admin can mark release as active/inactive
- [ ] Admin can delete a release
- [ ] Audit log records upload/delete/download actions

## Extension Instructions

- [ ] Chrome/Edge install instructions are clear
- [ ] Developer mode steps are listed
- [ ] Extension origin setup is mentioned

## PWA Instructions

- [ ] PWA install steps are listed
- [ ] Link to web vault works

## Security

- [ ] No PHP/JS/HTML files can be uploaded as releases
- [ ] Stored filenames are randomized
- [ ] .htaccess blocks direct file access
- [ ] Download controller validates file exists before streaming
- [ ] Release delete cannot unlink files outside app_storage/releases
- [ ] Product type/file extension mismatch is rejected
- [ ] All authenticated /api/extension/* endpoints reject HTTP except localhost
- [ ] Copy download URL button works in admin
- [ ] Inactive release cannot be downloaded publicly
- [ ] Public /downloads page shows uploaded active releases

## ⚠️ Not Production Ready

AMPass download center requires professional security audit before distribution.
