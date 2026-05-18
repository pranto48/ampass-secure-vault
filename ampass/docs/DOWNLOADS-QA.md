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

- [ ] Admin can access release management page
- [ ] Upload generates SHA-256 checksum
- [ ] Upload detects file size
- [ ] Upload stores file with random filename
- [ ] Only .exe, .msi, .zip, .xpi extensions allowed
- [ ] CSRF token required on upload form
- [ ] Non-admin cannot upload

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

## ⚠️ Not Production Ready

AMPass download center requires professional security audit before distribution.
