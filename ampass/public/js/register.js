/**
 * AMPass - Registration JavaScript
 * SECURITY: Sets up client-side encryption during registration.
 * Generates vault key, encrypts it with derived key from master password,
 * and sends only the encrypted key + salt + IV to the server.
 */

(function() {
    'use strict';

    const form = document.getElementById('registerForm');
    const passwordInput = document.getElementById('password');
    const strengthBar = document.querySelector('#strengthBar .strength-fill');
    const strengthText = document.getElementById('strengthText');

    // Password strength indicator
    if (passwordInput && strengthBar) {
        passwordInput.addEventListener('input', () => {
            const score = AMPassCrypto.calculateStrength(passwordInput.value);
            const info = AMPassCrypto.getStrengthLabel(score);
            strengthBar.style.width = score + '%';
            strengthBar.className = 'strength-fill ' + info.class;
            if (strengthText) strengthText.textContent = info.label;
        });
    }

    // Handle form submission
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const btn = document.getElementById('registerBtn');
            const password = passwordInput.value;
            const confirmPassword = document.getElementById('confirm_password').value;

            // Client-side validation
            if (password !== confirmPassword) {
                alert('Passwords do not match.');
                return;
            }

            if (password.length < 12) {
                alert('Password must be at least 12 characters.');
                return;
            }

            // Disable button
            btn.disabled = true;
            btn.innerHTML = '<span>Setting up encryption...</span>';

            try {
                // Generate encryption keys
                const cryptoData = await AMPassCrypto.setupNewUser(password);

                // Set hidden fields (only encrypted data, never plaintext password)
                document.getElementById('encryptionSalt').value = cryptoData.encryption_salt;
                document.getElementById('encryptedVaultKey').value = cryptoData.encrypted_vault_key;
                document.getElementById('vaultKeyIv').value = cryptoData.vault_key_iv;

                // Submit the form (password field is the standard form password input)
                form.submit();

            } catch (error) {
                console.error('Encryption setup failed:', error);
                alert('Failed to set up encryption. Please ensure your browser supports Web Crypto API and try again.');
                btn.disabled = false;
                btn.innerHTML = '<span>Create Account & Set Up Vault</span>';
            }
        });
    }
})();
