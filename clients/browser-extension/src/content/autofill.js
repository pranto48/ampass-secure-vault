/**
 * AMPass Extension - Autofill (Content Script)
 * SECURITY: Never holds vault key. Receives plaintext credentials from
 * service worker only during fill operation, then clears them.
 * Never autofills without user action.
 */

(function() {
  'use strict';

  /**
   * Exposed autofill function for form-detector.js to call directly.
   * SECURITY: Credentials exist in memory only during this operation.
   * @param {object} payload - { username, password }
   * @param {object|null} preferredFormData - { passwordField, usernameField } or null for auto-detect
   * @returns {boolean} true if fill succeeded
   */
  window.__ampassAutofill = function(payload, preferredFormData = null) {
    if (!payload) return false;
    const { username, password } = payload;

    if (preferredFormData && preferredFormData.passwordField) {
      // Use the specific form fields provided
      if (preferredFormData.usernameField && username) {
        fillField(preferredFormData.usernameField, username);
      }
      if (preferredFormData.passwordField && password) {
        fillField(preferredFormData.passwordField, password);
        preferredFormData.passwordField.setAttribute('data-ampass-filled', 'true');
      }
    } else {
      // Fallback: auto-detect fields
      performAutofill(payload);
    }

    // Clear sensitive data
    payload.username = null;
    payload.password = null;
    return true;
  };

  // Listen for autofill commands from popup/service worker
  chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
    if (msg.type === 'AUTOFILL') {
      // SECURITY: Verify this is a safe context for autofill
      const url = window.location.href;
      const isLocalhost = /^https?:\/\/(localhost|127\.0\.0\.1|::1)/i.test(url);
      const isHttps = url.startsWith('https://');

      if (!isHttps && !isLocalhost) {
        // Check if user explicitly allowed HTTP autofill
        chrome.storage.local.get('settings', (result) => {
          const settings = result.settings || {};
          if (!settings.allowHttpAutofill) {
            sendResponse({ success: false, error: 'Autofill blocked on HTTP page' });
            return;
          }
          performAutofill(msg.payload);
          sendResponse({ success: true });
        });
        return true; // async response
      }

      performAutofill(msg.payload);
      sendResponse({ success: true });
    }
    return false;
  });

  /**
   * Fill credentials into detected form fields
   * SECURITY: Credentials exist in memory only during this operation.
   */
  function performAutofill(payload) {
    const { username, password } = payload;
    const forms = window.__ampassGetForms ? window.__ampassGetForms() : [];

    if (forms.length === 0) {
      // Try to find fields directly
      const pwField = document.querySelector('input[type="password"]:not([data-ampass-filled])');
      if (!pwField) return;

      const form = pwField.closest('form') || document.body;
      const usernameField = form.querySelector('input[type="email"], input[type="text"], input[autocomplete="username"]');

      if (usernameField && username) {
        fillField(usernameField, username);
      }
      if (pwField && password) {
        fillField(pwField, password);
        pwField.setAttribute('data-ampass-filled', 'true');
      }
    } else {
      // Use detected form data
      const formData = forms[0]; // Fill first detected form
      if (formData.usernameField && username) {
        fillField(formData.usernameField, username);
      }
      if (formData.passwordField && password) {
        fillField(formData.passwordField, password);
        formData.passwordField.setAttribute('data-ampass-filled', 'true');
      }
    }

    // Clear sensitive data from local scope
    // (JavaScript GC will handle the rest, but we null the references)
    payload.username = null;
    payload.password = null;
  }

  /**
   * Fill a field and trigger proper events so websites detect the change.
   * SECURITY: Never fills hidden inputs, csrf_token fields, or AMPass-internal fields.
   */
  function fillField(field, value) {
    // Never fill hidden fields or CSRF tokens
    if (!field || field.type === 'hidden') return;
    if (field.name === 'csrf_token' || field.name === '_token' || field.name === '_csrf') return;
    if (field.getAttribute('data-ampass-no-fill') === 'true') return;

    // Focus the field
    field.focus();

    // Set value using native setter (bypasses React/Vue controlled components)
    const nativeInputValueSetter = Object.getOwnPropertyDescriptor(
      window.HTMLInputElement.prototype, 'value'
    ).set;
    nativeInputValueSetter.call(field, value);

    // Dispatch events in the correct order
    field.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
    field.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
    field.dispatchEvent(new KeyboardEvent('keydown', { bubbles: true }));
    field.dispatchEvent(new KeyboardEvent('keyup', { bubbles: true }));
    field.dispatchEvent(new Event('blur', { bubbles: true }));
  }
})();
