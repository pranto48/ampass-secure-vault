/**
 * AMPass - Password Generator JavaScript
 * All generation uses crypto.getRandomValues() for cryptographic randomness.
 */

(function() {
    'use strict';

    const lengthSlider = document.getElementById('passwordLength');
    const lengthValue = document.getElementById('lengthValue');
    const output = document.getElementById('generatedPassword');
    const strengthBar = document.querySelector('#genStrengthBar .strength-fill');
    const strengthText = document.getElementById('genStrengthText');
    const historyContainer = document.getElementById('passwordHistory');

    let history = [];

    // Length slider
    if (lengthSlider && lengthValue) {
        lengthSlider.addEventListener('input', () => {
            lengthValue.textContent = lengthSlider.value;
        });
    }

    // Generate button
    const genBtn = document.getElementById('generateBtn');
    if (genBtn) {
        genBtn.addEventListener('click', generate);
    }

    // Regenerate button
    const regenBtn = document.getElementById('regenerateBtn');
    if (regenBtn) {
        regenBtn.addEventListener('click', generate);
    }

    // Copy button
    const copyBtn = document.getElementById('copyGeneratedBtn');
    if (copyBtn) {
        copyBtn.addEventListener('click', () => {
            const pw = output.value;
            if (pw && pw !== 'Click Generate') {
                AMPassClipboard.copy(pw, 'Password');
            }
        });
    }

    // Passphrase button
    const passphraseBtn = document.getElementById('generatePassphraseBtn');
    if (passphraseBtn) {
        passphraseBtn.addEventListener('click', generatePassphrase);
    }

    // Save as vault item
    const saveBtn = document.getElementById('saveAsVaultItemBtn');
    if (saveBtn) {
        saveBtn.addEventListener('click', () => {
            const pw = output.value;
            if (pw && pw !== 'Click Generate') {
                // Redirect to add form with password pre-filled (via sessionStorage)
                sessionStorage.setItem('ampass_generated_password', pw);
                window.location.href = window.AMPass.baseUrl + '/vault/add';
            }
        });
    }

    // Clear history
    const clearBtn = document.getElementById('clearHistoryBtn');
    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            history = [];
            renderHistory();
        });
    }

    function generate() {
        const options = {
            length: parseInt(lengthSlider?.value || 16),
            uppercase: document.getElementById('optUppercase')?.checked,
            lowercase: document.getElementById('optLowercase')?.checked,
            numbers: document.getElementById('optNumbers')?.checked,
            symbols: document.getElementById('optSymbols')?.checked,
            noAmbiguous: document.getElementById('optNoAmbiguous')?.checked
        };

        const password = AMPassCrypto.generatePassword(options);
        output.value = password;
        updateStrength(password);
        addToHistory(password);
    }

    function generatePassphrase() {
        const options = {
            words: parseInt(document.getElementById('passphraseWords')?.value || 4),
            separator: document.getElementById('passphraseSeparator')?.value || '-',
            capitalize: document.getElementById('passphraseCapitalize')?.checked,
            addNumber: document.getElementById('passphraseNumbers')?.checked
        };

        const passphrase = AMPassCrypto.generatePassphrase(options);
        output.value = passphrase;
        updateStrength(passphrase);
        addToHistory(passphrase);
    }

    function updateStrength(password) {
        if (!strengthBar) return;
        const score = AMPassCrypto.calculateStrength(password);
        const info = AMPassCrypto.getStrengthLabel(score);
        strengthBar.style.width = score + '%';
        strengthBar.className = 'strength-fill ' + info.class;
        if (strengthText) strengthText.textContent = `${info.label} (${score}/100)`;
    }

    function addToHistory(password) {
        history.unshift({ password, time: new Date().toLocaleTimeString() });
        if (history.length > 10) history.pop();
        renderHistory();
    }

    function renderHistory() {
        if (!historyContainer) return;
        if (history.length === 0) {
            historyContainer.innerHTML = '<p class="text-muted">Generated passwords appear here (session only, not saved)</p>';
            return;
        }

        historyContainer.innerHTML = history.map(item => `
            <div class="history-item">
                <code class="history-password">${escapeHtml(item.password)}</code>
                <span class="history-time">${item.time}</span>
                <button class="btn btn-sm btn-ghost history-copy" data-pw="${escapeAttr(item.password)}">Copy</button>
            </div>
        `).join('');

        // Bind copy buttons
        historyContainer.querySelectorAll('.history-copy').forEach(btn => {
            btn.addEventListener('click', () => {
                AMPassClipboard.copy(btn.getAttribute('data-pw'), 'Password');
            });
        });
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function escapeAttr(str) {
        return str.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    // Generate one on page load
    generate();

})();
