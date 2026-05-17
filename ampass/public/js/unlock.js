/**
 * AMPass - Vault Unlock JavaScript
 * SECURITY: Derives encryption key from master password client-side,
 * decrypts the vault key, and stores it in session memory.
 * The master password is sent to server only for verification (hashed server-side).
 */

(function() {
    'use strict';

    const form = document.getElementById('unlockForm');

    if (form && window.AMPass && window.AMPass.derivationParams) {
        form.addEventListener('submit', async (e) => {
            // Don't prevent default - let the form submit to server for verification
            // But also derive the key client-side for vault decryption

            const masterPassword = document.getElementById('master_password').value;
            if (!masterPassword) return;

            try {
                // Derive key and unlock vault client-side
                await AMPassCrypto.unlockVault(masterPassword, window.AMPass.derivationParams);
                // Form will submit normally to server for server-side verification
            } catch (error) {
                console.warn('Client-side key derivation failed:', error.message);
                // Still let the form submit - server will verify the password
                // If server says OK but client failed, user will need to re-enter on next page
            }
        });
    }
})();
