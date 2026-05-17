/**
 * AMPass - Client-Side Encryption Module
 * SECURITY: All vault encryption/decryption happens here using Web Crypto API.
 * The server NEVER sees plaintext vault data.
 * 
 * Encryption: AES-256-GCM (authenticated encryption)
 * Key Derivation: PBKDF2 with SHA-256
 * Zero-knowledge: Server only stores ciphertext + IV + salt
 */

const AMPassCrypto = (function() {
    'use strict';

    const ALGORITHM = 'AES-GCM';
    const KEY_LENGTH = 256;
    const IV_LENGTH = 12; // 96 bits for AES-GCM
    const SALT_LENGTH = 32;
    const DEFAULT_ITERATIONS = 100000;

    // Storage key for the derived vault key (session only - stored in memory)
    let _vaultKey = null;

    /**
     * Generate a random salt
     */
    function generateSalt(length = SALT_LENGTH) {
        const salt = new Uint8Array(length);
        crypto.getRandomValues(salt);
        return bufferToHex(salt);
    }

    /**
     * Generate a random IV/nonce
     */
    function generateIV() {
        const iv = new Uint8Array(IV_LENGTH);
        crypto.getRandomValues(iv);
        return bufferToHex(iv);
    }

    /**
     * Generate a random vault key (256-bit)
     */
    async function generateVaultKey() {
        const key = await crypto.subtle.generateKey(
            { name: ALGORITHM, length: KEY_LENGTH },
            true, // extractable
            ['encrypt', 'decrypt']
        );
        return key;
    }

    /**
     * Derive an encryption key from master password using PBKDF2
     * SECURITY: This is the key derivation function that turns the master password
     * into a cryptographic key for encrypting/decrypting the vault key.
     */
    async function deriveKey(password, salt, iterations = DEFAULT_ITERATIONS) {
        const encoder = new TextEncoder();
        const passwordBuffer = encoder.encode(password);
        const saltBuffer = hexToBuffer(salt);

        // Import password as key material
        const keyMaterial = await crypto.subtle.importKey(
            'raw',
            passwordBuffer,
            'PBKDF2',
            false,
            ['deriveKey']
        );

        // Derive the actual encryption key
        const derivedKey = await crypto.subtle.deriveKey(
            {
                name: 'PBKDF2',
                salt: saltBuffer,
                iterations: iterations,
                hash: 'SHA-256'
            },
            keyMaterial,
            { name: ALGORITHM, length: KEY_LENGTH },
            true,
            ['encrypt', 'decrypt']
        );

        return derivedKey;
    }

    /**
     * Encrypt data with AES-GCM
     * Returns: { ciphertext: hex, iv: hex }
     */
    async function encrypt(plaintext, key) {
        const encoder = new TextEncoder();
        const data = encoder.encode(plaintext);
        const iv = crypto.getRandomValues(new Uint8Array(IV_LENGTH));

        const ciphertext = await crypto.subtle.encrypt(
            { name: ALGORITHM, iv: iv },
            key,
            data
        );

        return {
            ciphertext: bufferToHex(new Uint8Array(ciphertext)),
            iv: bufferToHex(iv)
        };
    }

    /**
     * Decrypt data with AES-GCM
     */
    async function decrypt(ciphertextHex, ivHex, key) {
        const ciphertext = hexToBuffer(ciphertextHex);
        const iv = hexToBuffer(ivHex);

        const decrypted = await crypto.subtle.decrypt(
            { name: ALGORITHM, iv: iv },
            key,
            ciphertext
        );

        const decoder = new TextDecoder();
        return decoder.decode(decrypted);
    }

    /**
     * Export a CryptoKey to raw hex
     */
    async function exportKey(key) {
        const raw = await crypto.subtle.exportKey('raw', key);
        return bufferToHex(new Uint8Array(raw));
    }

    /**
     * Import a raw hex key
     */
    async function importKey(hexKey) {
        const keyBuffer = hexToBuffer(hexKey);
        return await crypto.subtle.importKey(
            'raw',
            keyBuffer,
            { name: ALGORITHM, length: KEY_LENGTH },
            true,
            ['encrypt', 'decrypt']
        );
    }

    /**
     * Setup encryption for a new user (registration)
     * 1. Generate a random vault key
     * 2. Derive a wrapping key from master password
     * 3. Encrypt the vault key with the wrapping key
     * 4. Return encrypted vault key + salt + IV (to store on server)
     */
    async function setupNewUser(masterPassword) {
        // Generate random vault key
        const vaultKey = await generateVaultKey();
        const vaultKeyRaw = await exportKey(vaultKey);

        // Generate salt for key derivation
        const salt = generateSalt();

        // Derive wrapping key from master password
        const wrappingKey = await deriveKey(masterPassword, salt, DEFAULT_ITERATIONS);

        // Encrypt the vault key with the wrapping key
        const encrypted = await encrypt(vaultKeyRaw, wrappingKey);

        return {
            encryption_salt: salt,
            encrypted_vault_key: encrypted.ciphertext,
            vault_key_iv: encrypted.iv,
            key_iterations: DEFAULT_ITERATIONS
        };
    }

    /**
     * Unlock vault - derive key and decrypt vault key
     * Called when user enters master password to unlock
     */
    async function unlockVault(masterPassword, params) {
        const { encryption_salt, encrypted_vault_key, vault_key_iv, key_iterations } = params;

        // Derive the wrapping key from master password
        const wrappingKey = await deriveKey(masterPassword, encryption_salt, key_iterations || DEFAULT_ITERATIONS);

        // Decrypt the vault key
        const vaultKeyHex = await decrypt(encrypted_vault_key, vault_key_iv, wrappingKey);

        // Import the vault key
        _vaultKey = await importKey(vaultKeyHex);

        // Store in sessionStorage (encrypted key reference only)
        sessionStorage.setItem('ampass_vault_key', vaultKeyHex);

        return true;
    }

    /**
     * Restore vault key from session storage (page reload)
     */
    async function restoreVaultKey() {
        const stored = sessionStorage.getItem('ampass_vault_key');
        if (stored) {
            _vaultKey = await importKey(stored);
            return true;
        }
        return false;
    }

    /**
     * Lock vault - clear key from memory
     */
    function lockVault() {
        _vaultKey = null;
        sessionStorage.removeItem('ampass_vault_key');
    }

    /**
     * Check if vault is unlocked (key available)
     */
    function isUnlocked() {
        return _vaultKey !== null;
    }

    /**
     * Encrypt a vault item (JSON object → encrypted string)
     */
    async function encryptVaultItem(itemData) {
        if (!_vaultKey) {
            // Try to restore from session
            const restored = await restoreVaultKey();
            if (!restored) throw new Error('Vault is locked');
        }

        const plaintext = JSON.stringify(itemData);
        return await encrypt(plaintext, _vaultKey);
    }

    /**
     * Decrypt a vault item (encrypted string → JSON object)
     */
    async function decryptVaultItem(ciphertextHex, ivHex) {
        if (!_vaultKey) {
            const restored = await restoreVaultKey();
            if (!restored) throw new Error('Vault is locked');
        }

        const plaintext = await decrypt(ciphertextHex, ivHex, _vaultKey);
        return JSON.parse(plaintext);
    }

    /**
     * Compute HMAC-SHA256 for server-side searchable hash
     * Used for title_hash and url_hash so server can match without seeing plaintext
     */
    async function computeHMAC(data, secret) {
        const encoder = new TextEncoder();
        const keyData = encoder.encode(secret || 'ampass-hmac-key');
        const message = encoder.encode(data.toLowerCase().trim());

        const key = await crypto.subtle.importKey(
            'raw', keyData, { name: 'HMAC', hash: 'SHA-256' }, false, ['sign']
        );

        const signature = await crypto.subtle.sign('HMAC', key, message);
        return bufferToHex(new Uint8Array(signature));
    }

    // ===== Utility Functions =====

    function bufferToHex(buffer) {
        return Array.from(buffer).map(b => b.toString(16).padStart(2, '0')).join('');
    }

    function hexToBuffer(hex) {
        const bytes = new Uint8Array(hex.length / 2);
        for (let i = 0; i < hex.length; i += 2) {
            bytes[i / 2] = parseInt(hex.substr(i, 2), 16);
        }
        return bytes;
    }

    // ===== Password Generator =====

    function generatePassword(options = {}) {
        const length = options.length || 16;
        const uppercase = options.uppercase !== false;
        const lowercase = options.lowercase !== false;
        const numbers = options.numbers !== false;
        const symbols = options.symbols !== false;
        const noAmbiguous = options.noAmbiguous || false;

        let chars = '';
        if (uppercase) chars += noAmbiguous ? 'ABCDEFGHJKMNPQRSTUVWXYZ' : 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        if (lowercase) chars += noAmbiguous ? 'abcdefghjkmnpqrstuvwxyz' : 'abcdefghijklmnopqrstuvwxyz';
        if (numbers) chars += noAmbiguous ? '23456789' : '0123456789';
        if (symbols) chars += '!@#$%^&*()_+-=[]{}|;:,.<>?';

        if (chars.length === 0) chars = 'abcdefghijklmnopqrstuvwxyz';

        const array = new Uint32Array(length);
        crypto.getRandomValues(array);

        let password = '';
        for (let i = 0; i < length; i++) {
            password += chars[array[i] % chars.length];
        }

        return password;
    }

    /**
     * Generate a passphrase from word list
     */
    function generatePassphrase(options = {}) {
        const wordCount = options.words || 4;
        const separator = options.separator || '-';
        const capitalize = options.capitalize !== false;
        const addNumber = options.addNumber || false;

        // Simple word list (in production, use a larger list like EFF diceware)
        const words = [
            'apple', 'brave', 'cloud', 'dance', 'eagle', 'flame', 'grape', 'heart',
            'ivory', 'jewel', 'karma', 'lemon', 'maple', 'noble', 'ocean', 'pearl',
            'quest', 'river', 'storm', 'tiger', 'unity', 'vivid', 'whale', 'xenon',
            'yacht', 'zebra', 'amber', 'blaze', 'coral', 'delta', 'ember', 'frost',
            'globe', 'haven', 'index', 'joker', 'knack', 'lunar', 'metro', 'nexus',
            'orbit', 'prism', 'quilt', 'radar', 'solar', 'torch', 'ultra', 'vault',
            'wired', 'pixel', 'cyber', 'crypt', 'shield', 'spark', 'swift', 'stone',
            'steel', 'blade', 'crown', 'drift', 'forge', 'ghost', 'haste', 'light'
        ];

        const array = new Uint32Array(wordCount);
        crypto.getRandomValues(array);

        let passphrase = [];
        for (let i = 0; i < wordCount; i++) {
            let word = words[array[i] % words.length];
            if (capitalize) word = word.charAt(0).toUpperCase() + word.slice(1);
            passphrase.push(word);
        }

        let result = passphrase.join(separator);
        if (addNumber) {
            const num = new Uint32Array(1);
            crypto.getRandomValues(num);
            result += separator + (num[0] % 100);
        }

        return result;
    }

    /**
     * Calculate password strength (0-100)
     */
    function calculateStrength(password) {
        if (!password) return 0;

        let score = 0;
        const length = password.length;

        // Length scoring
        if (length >= 8) score += 10;
        if (length >= 12) score += 15;
        if (length >= 16) score += 15;
        if (length >= 20) score += 10;

        // Character variety
        if (/[a-z]/.test(password)) score += 10;
        if (/[A-Z]/.test(password)) score += 10;
        if (/[0-9]/.test(password)) score += 10;
        if (/[^a-zA-Z0-9]/.test(password)) score += 15;

        // Bonus for mixing
        const types = [/[a-z]/, /[A-Z]/, /[0-9]/, /[^a-zA-Z0-9]/].filter(r => r.test(password)).length;
        score += types * 5;

        // Penalty for common patterns
        if (/^[a-z]+$/.test(password) || /^[0-9]+$/.test(password)) score -= 20;
        if (/(.)\1{2,}/.test(password)) score -= 10; // Repeated chars
        if (/^(123|abc|qwerty|password)/i.test(password)) score -= 30;

        return Math.max(0, Math.min(100, score));
    }

    /**
     * Get strength label
     */
    function getStrengthLabel(score) {
        if (score >= 80) return { label: 'Very Strong', class: 'strength-excellent' };
        if (score >= 60) return { label: 'Strong', class: 'strength-good' };
        if (score >= 40) return { label: 'Fair', class: 'strength-fair' };
        if (score >= 20) return { label: 'Weak', class: 'strength-weak' };
        return { label: 'Very Weak', class: 'strength-terrible' };
    }

    // Public API
    return {
        generateSalt,
        generateIV,
        deriveKey,
        encrypt,
        decrypt,
        setupNewUser,
        unlockVault,
        restoreVaultKey,
        lockVault,
        isUnlocked,
        encryptVaultItem,
        decryptVaultItem,
        computeHMAC,
        generatePassword,
        generatePassphrase,
        calculateStrength,
        getStrengthLabel,
        bufferToHex,
        hexToBuffer
    };
})();
