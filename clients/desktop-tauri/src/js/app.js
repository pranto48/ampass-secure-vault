/**
 * AMPass Desktop — Main Application
 * SECURITY: Vault key in memory only. Cleared on lock/quit.
 */
(function() {
  'use strict';

  // ===== Tauri Availability Check =====
  if (!window.__TAURI__ || !window.__TAURI__.core) {
    document.addEventListener('DOMContentLoaded', () => {
      document.getElementById('app').innerHTML = `
        <div class="auth-screen" style="display:flex;">
          <div class="auth-card">
            <h2 class="auth-title">AMPass Desktop</h2>
            <p class="auth-sub">This application requires the Tauri desktop runtime.</p>
            <p style="font-size:12px;color:#64748b;margin-top:12px;">To run in development:<br><code style="background:#f1f5f9;padding:2px 6px;border-radius:4px;">cargo tauri dev</code></p>
          </div>
        </div>`;
    });
    return; // Stop execution — not in Tauri
  }

  const { invoke } = window.__TAURI__.core;
  const { listen } = window.__TAURI__.event;

  let vaultKeyHex = null;
  let vaultItems = [];
  let derivationParams = null;
  let searchKey = null; // Derived from vault key for title_hash/url_hash
  let allDecrypted = [];

  // ===== Views =====
  function showAuth(id) {
    ['viewWelcome','viewLogin','viewUnlock','viewMain'].forEach(v => document.getElementById(v).style.display = 'none');
    document.getElementById(id).style.display = id === 'viewMain' ? 'flex' : 'flex';
  }

  function showPage(name) {
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    const page = document.getElementById('page' + name.charAt(0).toUpperCase() + name.slice(1));
    if (page) page.classList.add('active');
    document.querySelectorAll('.nav-link').forEach(l => l.classList.toggle('active', l.dataset.page === name));
  }

  // ===== Init =====
  async function init() {
    // Check if Tauri is available (won't be in browser preview)
    if (!window.__TAURI__) {
      document.getElementById('app').innerHTML = '<div class="auth-screen"><div class="auth-card"><h2>AMPass Desktop</h2><p class="auth-sub">This app requires the Tauri desktop runtime.<br>Please launch via <code>cargo tauri dev</code>.</p></div></div>';
      return;
    }
    try {
      const state = await invoke('get_app_state');
      if (!state.configured) { showAuth('viewWelcome'); return; }
      Api.setServerUrl(state.server_url);
      if (!state.authenticated) { showAuth('viewLogin'); return; }
      Api.token = (await invoke('get_auth_token')) || '';

      // If we have a token but no derivationParams, fetch them from server
      if (!derivationParams && Api.token) {
        try {
          const paramResult = await Api.request('derivationParams');
          if (paramResult.success && paramResult.params) {
            derivationParams = paramResult.params;
          }
        } catch (e) {
          // Token may be expired — redirect to login
          showAuth('viewLogin');
          return;
        }
      }

      if (state.locked) { showAuth('viewUnlock'); return; }
      showAuth('viewMain');
      await loadVault();
    } catch (e) { showAuth('viewWelcome'); }
  }

  // ===== Connect =====
  document.getElementById('btnConnect').addEventListener('click', async () => {
    const url = document.getElementById('welcomeUrl').value.trim();
    if (!url) return;
    const serverUrl = Api.normalizeServerUrl(url);
    await invoke('set_server_url', { url: serverUrl });
    Api.setServerUrl(serverUrl);
    showAuth('viewLogin');
  });

  // ===== Login =====
  document.getElementById('btnLogin').addEventListener('click', async () => {
    const user = document.getElementById('loginUser').value.trim();
    const pass = document.getElementById('loginPass').value;
    if (!user || !pass) return;
    document.getElementById('loginErr').textContent = '';
    try {
      const result = await Api.login(user, pass, 'AMPass Desktop on Windows');
      Api.token = result.token;
      await invoke('store_auth_token', { token: result.token });
      derivationParams = result.derivation_params;
      document.getElementById('loginPass').value = '';
      showAuth('viewUnlock');
    } catch (e) {
      document.getElementById('loginErr').textContent = e.message;
      document.getElementById('loginPass').value = '';
    }
  });
  document.getElementById('loginPass').addEventListener('keydown', e => { if (e.key === 'Enter') document.getElementById('btnLogin').click(); });

  // ===== Unlock =====
  document.getElementById('btnUnlock').addEventListener('click', async () => {
    const pass = document.getElementById('unlockPass').value;
    if (!pass) return;
    document.getElementById('unlockErr').textContent = '';
    try {
      if (!derivationParams) {
        Api.token = (await invoke('get_auth_token')) || '';
        throw new Error('Session expired. Please login again.');
      }
      // Check if vault needs initialization
      if (derivationParams.needs_setup || derivationParams.key_iterations === 0 || derivationParams.encrypted_vault_key === 'VAULT_NOT_INITIALIZED') {
        await initializeVault(pass);
      } else {
        vaultKeyHex = await Crypto.unlockVault(pass, derivationParams);
      }
      await invoke('unlock_vault', { vaultKeyHex });
      // Derive search key from vault key for HMAC hashing
      searchKey = await Crypto.deriveSearchKey(vaultKeyHex);
      document.getElementById('unlockPass').value = '';
      showAuth('viewMain');
      await loadVault();
    } catch (e) {
      document.getElementById('unlockErr').textContent = e.message || 'Invalid master password';
      document.getElementById('unlockPass').value = '';
    }
  });
  document.getElementById('unlockPass').addEventListener('keydown', e => { if (e.key === 'Enter') document.getElementById('btnUnlock').click(); });

  async function initializeVault(masterPassword) {
    const vaultKeyRaw = Crypto.bufToHex(crypto.getRandomValues(new Uint8Array(32)));
    const salt = Crypto.bufToHex(crypto.getRandomValues(new Uint8Array(32)));
    const iterations = 100000;
    const wrappingKey = await Crypto.deriveKey(masterPassword, salt, iterations);
    const encrypted = await Crypto.encrypt(vaultKeyRaw, wrappingKey);
    await Api.request('vault/init-key', { body: { encryption_salt: salt, encrypted_vault_key: encrypted.ciphertext, vault_key_iv: encrypted.iv, key_iterations: iterations } });
    derivationParams = { encryption_salt: salt, encrypted_vault_key: encrypted.ciphertext, vault_key_iv: encrypted.iv, key_iterations: iterations, needs_setup: false };
    vaultKeyHex = vaultKeyRaw;
  }

  // ===== Load Vault =====
  async function loadVault() {
    try {
      const result = await Api.listVault();
      vaultItems = result.items || [];
      await invoke('save_vault_cache', { encryptedItemsJson: JSON.stringify(vaultItems) });
    } catch (e) {
      const cached = await invoke('load_vault_cache');
      if (cached) { vaultItems = JSON.parse(cached); toast('Offline — cached data'); }
    }
    await decryptAll();
    renderQuickAccess();
    renderWebAccounts();
    renderIdentities();
    renderMemos();
    updateSyncTime();
    await invoke('record_activity');
  }

  async function decryptAll() {
    allDecrypted = [];
    for (const item of vaultItems) {
      try {
        const dec = await Crypto.decryptItem(item.encrypted_data, item.encryption_iv, vaultKeyHex);
        allDecrypted.push({ ...dec, _id: item.id, _type: item.item_type, _fav: item.is_favorite, _weak: item.is_weak, _used: item.last_used_at });
      } catch { allDecrypted.push({ title: '[Decrypt Error]', _id: item.id, _type: item.item_type }); }
    }
  }

  // ===== Render Functions =====
  function renderQuickAccess() {
    document.getElementById('statTotal').textContent = allDecrypted.length;
    document.getElementById('statFavorites').textContent = allDecrypted.filter(i => i._fav).length;
    document.getElementById('statWeak').textContent = allDecrypted.filter(i => i._weak).length;
    const score = allDecrypted.length > 0 ? Math.max(0, 100 - Math.round(allDecrypted.filter(i => i._weak).length / allDecrypted.length * 100)) : '—';
    document.getElementById('statScore').textContent = score + (typeof score === 'number' ? '%' : '');
    document.getElementById('secScore').textContent = score + (typeof score === 'number' ? '%' : '');
    document.getElementById('secWeak').textContent = allDecrypted.filter(i => i._weak).length;
    document.getElementById('secReused').textContent = '0';

    const recent = [...allDecrypted].filter(i => i._used).sort((a, b) => (b._used || '').localeCompare(a._used || '')).slice(0, 5);
    document.getElementById('recentList').innerHTML = recent.length ? recent.map(i => itemRow(i)).join('') : '<p class="empty-hint">No recently used items</p>';
    const favs = allDecrypted.filter(i => i._fav).slice(0, 5);
    document.getElementById('favoritesList').innerHTML = favs.length ? favs.map(i => itemRow(i)).join('') : '<p class="empty-hint">No favorites yet</p>';
  }

  function renderWebAccounts() {
    const items = allDecrypted.filter(i => i._type === 'login');
    document.getElementById('webAccountsList').innerHTML = items.length ? items.map(i => itemRow(i)).join('') : '<p class="empty-hint">No web accounts</p>';
  }

  function renderIdentities() {
    const items = allDecrypted.filter(i => i._type === 'identity');
    document.getElementById('identitiesList').innerHTML = items.length ? items.map(i => itemRow(i)).join('') : '<p class="empty-hint">No identities</p>';
  }

  function renderMemos() {
    const items = allDecrypted.filter(i => i._type === 'secure_note');
    document.getElementById('memosList').innerHTML = items.length ? items.map(i => itemRow(i)).join('') : '<p class="empty-hint">No secure memos</p>';
  }

  function itemRow(item) {
    const icon = item._type === 'login' ? '🌐' : item._type === 'identity' ? '👤' : item._type === 'secure_note' ? '📝' : '📦';
    return `<div class="item-row" data-id="${item._id}">
      <div class="item-icon">${icon}</div>
      <div class="item-info"><div class="item-title">${esc(item.title || 'Untitled')}</div><div class="item-sub">${esc(item.username || item.email || item.url || '')}</div></div>
      <div class="item-actions"><button class="btn-ghost-sm" data-copy-user="${item._id}" title="Copy username">👤</button><button class="btn-ghost-sm" data-copy-pass="${item._id}" title="Copy password">📋</button></div>
    </div>`;
  }

  // ===== Item Actions =====
  document.addEventListener('click', async (e) => {
    const copyUser = e.target.closest('[data-copy-user]');
    if (copyUser) { e.stopPropagation(); await copyField(parseInt(copyUser.dataset.copyUser), 'username'); return; }
    const copyPass = e.target.closest('[data-copy-pass]');
    if (copyPass) { e.stopPropagation(); await copyField(parseInt(copyPass.dataset.copyPass), 'password'); return; }
    const row = e.target.closest('.item-row');
    if (row) { showItemDetail(parseInt(row.dataset.id)); return; }
    const addBtn = e.target.closest('[data-add]');
    if (addBtn) { showAddModal(addBtn.dataset.add); return; }
  });

  async function copyField(id, field) {
    const item = allDecrypted.find(i => i._id === id);
    if (!item || !item[field]) { toast('Nothing to copy'); return; }
    await navigator.clipboard.writeText(item[field]);
    toast(field === 'password' ? 'Password copied (clears in 30s)' : 'Copied!');
    if (field === 'password') setTimeout(async () => { try { const c = await navigator.clipboard.readText(); if (c === item[field]) await navigator.clipboard.writeText(''); } catch {} }, 30000);
  }

  function showItemDetail(id) {
    const item = allDecrypted.find(i => i._id === id);
    if (!item) return;
    document.getElementById('modalTitle').textContent = item.title || 'Item Details';
    let html = '';
    if (item.url) html += `<div class="field-label">URL</div><div class="field-input" style="margin-bottom:8px;">${esc(item.url)}</div>`;
    if (item.username) html += `<div class="field-label">Username</div><div class="field-input" style="margin-bottom:8px;">${esc(item.username)}</div>`;
    if (item.password) html += `<div class="field-label">Password</div><div class="field-input" style="margin-bottom:8px;">••••••••</div>`;
    if (item.notes) html += `<div class="field-label">Notes</div><div class="field-input" style="margin-bottom:8px;white-space:pre-wrap;">${esc(item.notes)}</div>`;
    document.getElementById('modalBody').innerHTML = html || '<p class="empty-hint">No details</p>';
    document.getElementById('modalFooter').innerHTML = `<button class="btn-ghost-sm" onclick="document.getElementById('itemModal').style.display='none'">Close</button>`;
    document.getElementById('itemModal').style.display = 'flex';
  }

  function showAddModal(type) {
    document.getElementById('modalTitle').textContent = type === 'login' ? 'Add Web Account' : type === 'identity' ? 'Add Identity' : 'Add Secure Memo';
    let html = '<div class="auth-form">';
    html += '<label class="field-label">Title</label><input type="text" id="addTitle" class="field-input">';
    if (type === 'login') {
      html += '<label class="field-label">URL</label><input type="url" id="addUrl" class="field-input">';
      html += '<label class="field-label">Username</label><input type="text" id="addUser" class="field-input">';
      html += '<label class="field-label">Password</label><input type="password" id="addPass" class="field-input">';
    }
    html += '<label class="field-label">Notes</label><textarea id="addNotes" class="field-input" rows="3"></textarea>';
    html += '</div>';
    document.getElementById('modalBody').innerHTML = html;
    document.getElementById('modalFooter').innerHTML = `<button class="btn-ghost-sm" onclick="document.getElementById('itemModal').style.display='none'">Cancel</button><button class="btn-primary" style="width:auto;margin:0;padding:8px 16px;" id="btnSaveNew">Save</button>`;
    document.getElementById('itemModal').style.display = 'flex';
    document.getElementById('btnSaveNew').addEventListener('click', () => saveNewItem(type));
  }

  async function saveNewItem(type) {
    const data = { title: document.getElementById('addTitle')?.value || '', notes: document.getElementById('addNotes')?.value || '' };
    if (type === 'login') { data.url = document.getElementById('addUrl')?.value || ''; data.username = document.getElementById('addUser')?.value || ''; data.password = document.getElementById('addPass')?.value || ''; }
    if (!data.title) { toast('Title is required'); return; }
    try {
      const encrypted = await Crypto.encryptItem(data, vaultKeyHex);
      const urlHash = data.url ? await Crypto.computeSearchHash(data.url, searchKey) : null;
      const titleHash = await Crypto.computeSearchHash(data.title, searchKey);
      await Api.saveItem({ item_type: type, encrypted_data: encrypted.ciphertext, encryption_iv: encrypted.iv, url_hash: urlHash, title_hash: titleHash, password_strength: Crypto.strength(data.password || ''), is_weak: Crypto.strength(data.password || '') < 40 ? 1 : 0 });
      document.getElementById('itemModal').style.display = 'none';
      toast('Item saved!');
      await loadVault();
    } catch (e) { toast('Save failed: ' + e.message); }
  }

  // ===== Navigation =====
  document.getElementById('sidebarNav').addEventListener('click', (e) => {
    const link = e.target.closest('.nav-link');
    if (link) { e.preventDefault(); showPage(link.dataset.page); }
  });

  // ===== Search =====
  document.getElementById('searchInput').addEventListener('input', (e) => {
    const q = e.target.value.toLowerCase().trim();
    const filtered = q ? allDecrypted.filter(i => (i.title||'').toLowerCase().includes(q) || (i.username||'').toLowerCase().includes(q) || (i.url||'').toLowerCase().includes(q)) : allDecrypted.filter(i => i._type === 'login');
    document.getElementById('webAccountsList').innerHTML = filtered.length ? filtered.map(i => itemRow(i)).join('') : '<p class="empty-hint">No results</p>';
    showPage('webAccounts');
  });

  // ===== Lock =====
  document.getElementById('btnLockVault').addEventListener('click', async () => { vaultKeyHex = null; searchKey = null; allDecrypted = []; await invoke('lock_vault'); showAuth('viewUnlock'); });

  // ===== Sync =====
  document.getElementById('btnSyncNow').addEventListener('click', loadVault);
  function updateSyncTime() { document.getElementById('syncTime').textContent = new Date().toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'}); }

  // ===== Generator =====
  function genPw() {
    const pw = Crypto.generatePassword({ length: parseInt(document.getElementById('genLen').value), uppercase: document.getElementById('genUpper').checked, lowercase: document.getElementById('genLower').checked, numbers: document.getElementById('genNums').checked, symbols: document.getElementById('genSyms').checked });
    document.getElementById('genPw').value = pw;
    const s = Crypto.strength(pw);
    const fill = document.getElementById('genStrFill');
    fill.style.width = s + '%';
    fill.style.background = s >= 80 ? '#16a34a' : s >= 60 ? '#84cc16' : s >= 40 ? '#d97706' : '#dc2626';
  }
  document.getElementById('btnRegenerate').addEventListener('click', genPw);
  document.getElementById('genLen').addEventListener('input', (e) => { document.getElementById('genLenVal').textContent = e.target.value; genPw(); });
  document.getElementById('btnCopyGen').addEventListener('click', async () => { await navigator.clipboard.writeText(document.getElementById('genPw').value); toast('Copied!'); });

  // ===== Settings =====
  document.getElementById('btnLogout').addEventListener('click', async () => { try { await Api.logout(); } catch {} await invoke('logout'); vaultKeyHex = null; searchKey = null; allDecrypted = []; derivationParams = null; Api.token = ''; showAuth('viewLogin'); });
  document.getElementById('btnWipeCache').addEventListener('click', async () => { if (!confirm('Wipe all local data?')) return; await invoke('wipe_local_data'); vaultKeyHex = null; searchKey = null; allDecrypted = []; derivationParams = null; showAuth('viewWelcome'); });
  document.getElementById('btnExportBackup').addEventListener('click', async () => { const data = JSON.stringify({ version: '1.0', exported_at: new Date().toISOString(), items: vaultItems }); await invoke('pick_save_location', { data }); toast('Backup exported'); });

  // ===== Tauri Events =====
  listen('tray-lock', async () => { vaultKeyHex = null; searchKey = null; allDecrypted = []; await invoke('lock_vault'); showAuth('viewUnlock'); });
  listen('auto-locked', () => { vaultKeyHex = null; searchKey = null; allDecrypted = []; showAuth('viewUnlock'); });

  // ===== Helpers =====
  function esc(s) { if (!s) return ''; const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
  function toast(msg) { const t = document.getElementById('toast'); t.textContent = msg; t.style.display = 'block'; setTimeout(() => t.style.display = 'none', 3000); }

  // ===== Modal close =====
  document.getElementById('modalClose').addEventListener('click', () => document.getElementById('itemModal').style.display = 'none');

  // ===== Background sync =====
  setInterval(async () => { if (vaultKeyHex) { await invoke('record_activity'); await loadVault(); } }, 300000);

  // ===== Start =====
  init();
  setTimeout(genPw, 200);
})();
