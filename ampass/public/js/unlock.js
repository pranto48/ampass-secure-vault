/**
 * AMPass - Vault Unlock JavaScript
 * SECURITY: Derives encryption key from master password client-side,
 * decrypts the vault key, and stores it in session memory.
 * Handles first-time vault initialization when key_iterations = 0.
 */

(function() {
    'use strict';

    const form = document.getElementById('unlockForm');

    if (form && window.AMPass && window.AMPass.derivationParams) {
        const params = window.AMPass.derivationParams;

        form.addEventListener('submit', async (e) => {
            const masterPassword = document.getElementById('master_password').value;
            if (!masterPassword) return;

            // Check if vault needs first-time initialization
            const needsSetup = params.needs_setup || params.key_iterations === 0 ||
                               params.encrypted_vault_key === 'VAULT_NOT_INITIALIZED';

            if (needsSetup) {
                // Prevent form submission — we need to initialize the vault first
                e.preventDefault();

                try {
                    await initializeVault(masterPassword);
                    // After initialization, submit the form for server-side verification
                    form.submit();
                } catch (error) {
                    console.warn('Vault initialization failed:', error.message);
                    alert('Vault setup failed: ' + error.message + '\nPlease try again.');
                }
            } else {
                // Normal unlock — derive key client-side, let form submit for server verification
                try {
                    await AMPassCrypto.unlockVault(masterPassword, params);
                } catch (error) {
                    console.warn('Client-side key derivation failed:', error.message);
                    // Still let the form submit — server will verify the password
                }
            }
        });
    }

    /**
     * First-time vault initialization.
     * Generates a vault key in the browser, encrypts it with the master password,
     * and sends the encrypted key to the server.
     * SECURITY: The raw vault key never leaves the browser.
     */
    async function initializeVault(masterPassword) {
        // Generate random 256-bit vault key
        const vaultKeyRaw = AMPassCrypto.bufferToHex(crypto.getRandomValues(new Uint8Array(32)));

        // Generate salt
        const salt = AMPassCrypto.generateSalt();
        const iterations = 100000;

        // Derive wrapping key from master password
        const wrappingKey = await AMPassCrypto.deriveKey(masterPassword, salt, iterations);

        // Encrypt the vault key
        const encrypted = await AMPassCrypto.encrypt(vaultKeyRaw, wrappingKey);

        // Send encrypted vault key to server
        const response = await fetch(window.AMPass.baseUrl + '/api/auth/initVaultKey', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.AMPass.csrfToken || ''
            },
            body: JSON.stringify({
                encryption_salt: salt,
                encrypted_vault_key: encrypted.ciphertext,
                vault_key_iv: encrypted.iv,
                key_iterations: iterations
            })
        });

        const result = await response.json();
        if (!response.ok || !result.success) {
            throw new Error(result.error || 'Failed to save vault key');
        }

        // Now unlock with the new params
        const newParams = {
            encryption_salt: salt,
            encrypted_vault_key: encrypted.ciphertext,
            vault_key_iv: encrypted.iv,
            key_iterations: iterations
        };

        await AMPassCrypto.unlockVault(masterPassword, newParams);

        // Update the page params so subsequent unlocks work
        window.AMPass.derivationParams = newParams;
    }
})();
