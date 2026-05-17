<?php
/**
 * AMPass - Password Generator View
 * All generation happens client-side for security.
 */
?>

<div class="page-header">
    <h1 class="page-title">Password Generator</h1>
    <p class="page-subtitle">Generate strong, unique passwords</p>
</div>

<div class="generator-container">
    <div class="card">
        <div class="card-body">
            <!-- Generated Password Display -->
            <div class="generated-password-display">
                <input type="text" id="generatedPassword" class="generated-password-input" readonly value="Click Generate">
                <button class="btn btn-icon" id="copyGeneratedBtn" title="Copy to clipboard">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                </button>
                <button class="btn btn-icon" id="regenerateBtn" title="Regenerate">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/></svg>
                </button>
            </div>

            <!-- Strength Meter -->
            <div class="generator-strength">
                <div class="password-strength-bar" id="genStrengthBar">
                    <div class="strength-fill"></div>
                </div>
                <span class="strength-text" id="genStrengthText">-</span>
            </div>

            <!-- Length Slider -->
            <div class="form-group">
                <div class="slider-header">
                    <label class="form-label">Length</label>
                    <span class="slider-value" id="lengthValue">16</span>
                </div>
                <input type="range" id="passwordLength" class="form-range" min="8" max="64" value="16">
            </div>

            <!-- Options -->
            <div class="generator-options">
                <label class="checkbox-label">
                    <input type="checkbox" id="optUppercase" checked>
                    <span>Uppercase (A-Z)</span>
                </label>
                <label class="checkbox-label">
                    <input type="checkbox" id="optLowercase" checked>
                    <span>Lowercase (a-z)</span>
                </label>
                <label class="checkbox-label">
                    <input type="checkbox" id="optNumbers" checked>
                    <span>Numbers (0-9)</span>
                </label>
                <label class="checkbox-label">
                    <input type="checkbox" id="optSymbols" checked>
                    <span>Symbols (!@#$%...)</span>
                </label>
                <label class="checkbox-label">
                    <input type="checkbox" id="optNoAmbiguous">
                    <span>Avoid ambiguous (0O, 1lI)</span>
                </label>
            </div>

            <div class="form-divider"></div>

            <!-- Passphrase Generator -->
            <div class="form-group">
                <label class="form-label">Or generate a passphrase:</label>
                <div class="passphrase-options">
                    <div class="form-group">
                        <label class="form-label-sm">Words</label>
                        <input type="number" id="passphraseWords" class="form-input form-input-sm" value="4" min="3" max="8">
                    </div>
                    <div class="form-group">
                        <label class="form-label-sm">Separator</label>
                        <input type="text" id="passphraseSeparator" class="form-input form-input-sm" value="-" maxlength="3">
                    </div>
                    <label class="checkbox-label">
                        <input type="checkbox" id="passphraseCapitalize" checked>
                        <span>Capitalize</span>
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" id="passphraseNumbers">
                        <span>Add number</span>
                    </label>
                </div>
                <button class="btn btn-secondary btn-full" id="generatePassphraseBtn">Generate Passphrase</button>
            </div>

            <div class="form-divider"></div>

            <!-- Actions -->
            <div class="generator-actions">
                <button class="btn btn-primary btn-full" id="generateBtn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4m0 12v4M4.93 4.93l2.83 2.83m8.48 8.48l2.83 2.83M2 12h4m12 0h4"/></svg>
                    Generate Password
                </button>
                <button class="btn btn-secondary btn-full" id="saveAsVaultItemBtn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/></svg>
                    Save as Vault Item
                </button>
            </div>
        </div>
    </div>

    <!-- Password History (session only, never persisted) -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Generated History</h2>
            <button class="btn btn-sm btn-ghost" id="clearHistoryBtn">Clear</button>
        </div>
        <div class="card-body">
            <div class="password-history" id="passwordHistory">
                <p class="text-muted">Generated passwords appear here (session only, not saved)</p>
            </div>
        </div>
    </div>
</div>
