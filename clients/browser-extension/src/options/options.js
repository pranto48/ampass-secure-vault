/**
 * AMPass Extension - Options Page Logic
 */

(function() {
  'use strict';

  const els = {
    serverUrl: document.getElementById('serverUrl'),
    deviceName: document.getElementById('deviceName'),
    autofillBehavior: document.getElementById('autofillBehavior'),
    allowHttpAutofill: document.getElementById('allowHttpAutofill'),
    autosaveBehavior: document.getElementById('autosaveBehavior'),
    clipboardClear: document.getElementById('clipboardClear'),
    lockTimeout: document.getElementById('lockTimeout'),
    theme: document.getElementById('theme'),
    useDesktopBridge: document.getElementById('useDesktopBridge'),
    bridgeStatus: document.getElementById('bridgeStatus'),
    btnSave: document.getElementById('btnSave'),
    btnLogout: document.getElementById('btnLogout'),
    statusMsg: document.getElementById('statusMsg')
  };

  function showMsg(text, type = 'success') {
    els.statusMsg.textContent = text;
    els.statusMsg.className = 'status-msg ' + type;
    els.statusMsg.style.display = 'block';
    setTimeout(() => { els.statusMsg.style.display = 'none'; }, 4000);
  }

  async function loadSettings() {
    const settings = await Storage.getSettings();
    const serverUrl = await Storage.getServerUrl();

    els.serverUrl.value = serverUrl || settings.serverUrl || '';
    els.deviceName.value = settings.deviceName || 'AMPass Browser Extension';
    els.autofillBehavior.value = settings.autofillBehavior || 'click';
    els.allowHttpAutofill.checked = settings.allowHttpAutofill || false;
    els.autosaveBehavior.value = settings.autosaveBehavior || 'ask';
    els.clipboardClear.value = settings.clipboardClearSeconds || 30;
    els.lockTimeout.value = settings.lockTimeoutMinutes || 15;
    els.theme.value = settings.theme || 'system';
    els.useDesktopBridge.checked = settings.useDesktopBridge || false;

    // Test native messaging connection
    if (settings.useDesktopBridge) {
      testBridgeConnection();
    }
  }

  async function testBridgeConnection() {
    els.bridgeStatus.textContent = 'Status: Testing...';
    try {
      const port = chrome.runtime.connectNative('com.ampass.desktop');
      const testPromise = new Promise((resolve, reject) => {
        const timer = setTimeout(() => { port.disconnect(); reject(new Error('Timeout')); }, 3000);
        port.onMessage.addListener((msg) => { clearTimeout(timer); port.disconnect(); resolve(msg); });
        port.onDisconnect.addListener(() => { clearTimeout(timer); reject(new Error('Disconnected')); });
        port.postMessage({ type: 'ping', request_id: '1' });
      });
      const result = await testPromise;
      if (result && result.success) {
        els.bridgeStatus.textContent = 'Status: ✅ Connected to AMPass Desktop v' + (result.data?.version || '?');
        els.bridgeStatus.style.color = '#22c55e';
      } else {
        els.bridgeStatus.textContent = 'Status: ❌ Host responded but with error';
        els.bridgeStatus.style.color = '#ef4444';
      }
    } catch (e) {
      els.bridgeStatus.textContent = 'Status: ❌ Desktop app not found or not running';
      els.bridgeStatus.style.color = '#ef4444';
    }
  }

  els.btnSave.addEventListener('click', async () => {
    const serverUrl = els.serverUrl.value.trim();

    // Validate server URL
    if (serverUrl && !Security.isValidServerUrl(serverUrl)) {
      showMsg('Invalid server URL. Use https:// for production or http://localhost for development.', 'error');
      return;
    }

    if (serverUrl) {
      await Storage.setServerUrl(serverUrl);
    }

    const settings = {
      serverUrl: serverUrl,
      deviceName: els.deviceName.value.trim() || 'AMPass Browser Extension',
      autofillBehavior: els.autofillBehavior.value,
      allowHttpAutofill: els.allowHttpAutofill.checked,
      autosaveBehavior: els.autosaveBehavior.value,
      clipboardClearSeconds: Math.max(5, Math.min(300, parseInt(els.clipboardClear.value) || 30)),
      lockTimeoutMinutes: Math.max(1, Math.min(1440, parseInt(els.lockTimeout.value) || 15)),
      theme: els.theme.value,
      useDesktopBridge: els.useDesktopBridge.checked
    };

    await Storage.saveSettings(settings);
    showMsg('Settings saved successfully!', 'success');
  });

  els.btnLogout.addEventListener('click', async () => {
    if (!confirm('Disconnect from AMPass server? You will need to log in again.')) return;
    try {
      await chrome.runtime.sendMessage({ type: 'LOGOUT' });
    } catch (e) { /* ignore */ }
    await Storage.logout();
    await Storage.removeLocal('serverUrl');
    showMsg('Disconnected. You can close this page.', 'success');
    els.serverUrl.value = '';
  });

  loadSettings();
})();
