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
    } else if (msg.type === 'AUTOFILL_IDENTITY') {
      performIdentityAutofill(msg.payload);
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
   * Scan page and perform identity autofill
   */
  function performIdentityAutofill(payload) {
    const inputs = Array.from(document.querySelectorAll('input:not([type="password"]):not([type="hidden"]):not([type="submit"]):not([type="button"]), select'))
      .filter(el => {
        const style = window.getComputedStyle(el);
        const isVisible = style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0' && el.offsetWidth > 0 && el.offsetHeight > 0;
        return isVisible && el.type !== 'hidden';
      });

    const classified = {};
    inputs.forEach(input => {
      const fieldType = window.__ampassClassifyField ? window.__ampassClassifyField(input) : null;
      if (fieldType && !classified[fieldType]) {
        classified[fieldType] = input;
      }
    });

    const mockFormData = {
      type: 'identity',
      fields: classified
    };

    window.__ampassAutofillIdentity(payload, mockFormData);
  }

  /**
   * Exposed identity autofill function
   */
  window.__ampassAutofillIdentity = function(identityData, identityFormData) {
    if (!identityData || !identityFormData || !identityFormData.fields) return false;

    const fields = identityFormData.fields;

    let firstName = identityData.first_name || '';
    let lastName = identityData.last_name || '';
    let fullName = identityData.full_name || '';

    if (!fullName && firstName) {
      fullName = firstName + (lastName ? ' ' + lastName : '');
    }
    if (!firstName && fullName) {
      const parts = fullName.trim().split(/\s+/);
      firstName = parts[0] || '';
      lastName = parts.slice(1).join(' ') || '';
    }

    const mapping = {
      first_name: firstName,
      last_name: lastName,
      full_name: fullName,
      email: identityData.email || '',
      phone: identityData.phone || '',
      company: identityData.company || '',
      address_line1: identityData.address_line1 || '',
      address_line2: identityData.address_line2 || '',
      city: identityData.city || '',
      state: identityData.state || '',
      postcode: identityData.postcode || '',
      country: identityData.country || '',
      date_of_birth: identityData.date_of_birth || ''
    };

    for (const [fieldType, fieldElement] of Object.entries(fields)) {
      const val = mapping[fieldType];
      if (val && fieldElement) {
        fillField(fieldElement, val);
      }
    }

    return true;
  };

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

    if (field.tagName === 'SELECT') {
      const options = Array.from(field.options);
      const valLower = String(value).toLowerCase().trim();
      
      let matchedOption = options.find(opt => 
        opt.value.toLowerCase().trim() === valLower || 
        opt.text.toLowerCase().trim() === valLower
      );
      
      if (!matchedOption) {
        matchedOption = options.find(opt => 
          opt.text.toLowerCase().includes(valLower) || 
          valLower.includes(opt.text.toLowerCase())
        );
      }
      
      if (matchedOption) {
        field.value = matchedOption.value;
      } else {
        field.value = value;
      }
      
      field.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
      field.dispatchEvent(new Event('blur', { bubbles: true }));
      return;
    }

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
