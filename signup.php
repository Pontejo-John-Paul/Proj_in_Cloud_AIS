<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Sign Up — TrikScan</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --bg: #0a0f1c; --surface: #111827; --surface2: #1a2235;
      --accent: #f59e0b; --accent2: #10b981; --text: #f1f5f9;
      --muted: #64748b; --border: rgba(255,255,255,0.08); --danger: #ef4444;
    }
    html, body { font-family: 'DM Sans', sans-serif; background: transparent; color: var(--text); }

    .modal-card {
      background: var(--surface); border: 1px solid var(--border);
      border-radius: 20px; padding: 32px 32px 28px; width: 100%;
      max-width: 460px; margin: 0 auto; position: relative;
      box-shadow: 0 32px 80px rgba(0,0,0,0.6), 0 0 0 1px rgba(255,255,255,0.04);
    }
    .close-btn {
      position: absolute; top: 14px; right: 14px; width: 30px; height: 30px;
      border-radius: 50%; background: var(--surface2); border: 1px solid var(--border);
      color: var(--muted); font-size: 1rem; cursor: pointer;
      display: grid; place-items: center; transition: background 0.2s, color 0.2s;
    }
    .close-btn:hover { background: rgba(255,255,255,0.08); color: var(--text); }

    .modal-header { margin-bottom: 20px; }
    .admin-badge {
      display: inline-flex; align-items: center; gap: 6px;
      background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.25);
      border-radius: 100px; padding: 3px 12px; font-size: 0.7rem; font-weight: 600;
      color: var(--accent); letter-spacing: 0.08em; text-transform: uppercase;
      font-family: 'JetBrains Mono', monospace; margin-bottom: 10px;
    }
    .admin-badge::before { content:''; width:6px; height:6px; background:var(--accent); border-radius:50%; }
    .modal-header h2 { font-family:'Bebas Neue',sans-serif; font-size:1.9rem; letter-spacing:0.03em; line-height:1; }
    .modal-header p { margin-top:5px; font-size:0.85rem; color:var(--muted); font-weight:300; }

    /* TOAST */
    .toast-container { position:fixed; top:16px; right:16px; z-index:9999; display:flex; flex-direction:column; gap:10px; pointer-events:none; }
    .toast {
      display:flex; align-items:flex-start; gap:10px; padding:12px 16px;
      border-radius:10px; font-size:0.83rem; line-height:1.5; min-width:260px; max-width:340px;
      pointer-events:all; box-shadow:0 8px 32px rgba(0,0,0,0.4);
      animation: toastIn 0.35s cubic-bezier(0.34,1.56,0.64,1) forwards;
      border: 1px solid transparent;
    }
    .toast.hiding { animation: toastOut 0.3s ease forwards; }
    .toast-success { background:rgba(16,185,129,0.12); border-color:rgba(16,185,129,0.3); color:#6ee7b7; }
    .toast-error   { background:rgba(239,68,68,0.12);  border-color:rgba(239,68,68,0.3);  color:#fca5a5; }
    .toast-warning { background:rgba(245,158,11,0.12); border-color:rgba(245,158,11,0.3); color:#fcd34d; }
    .toast-icon { font-size:1rem; flex-shrink:0; margin-top:1px; }
    .toast-body { flex:1; }
    .toast-title { font-weight:700; font-size:0.82rem; margin-bottom:2px; }
    .toast-msg   { font-size:0.8rem; opacity:0.85; }
    .toast-close { background:none; border:none; cursor:pointer; color:inherit; opacity:0.6; font-size:1rem; padding:0; flex-shrink:0; transition:opacity 0.2s; }
    .toast-close:hover { opacity:1; }
    .toast-progress { position:absolute; bottom:0; left:0; height:2px; border-radius:0 0 10px 10px; animation: progress 4s linear forwards; }
    .toast-success .toast-progress { background:var(--accent2); }
    .toast-error   .toast-progress { background:var(--danger); }
    .toast-warning .toast-progress { background:var(--accent); }
    @keyframes toastIn  { from{opacity:0;transform:translateX(40px)} to{opacity:1;transform:translateX(0)} }
    @keyframes toastOut { from{opacity:1;transform:translateX(0)}    to{opacity:0;transform:translateX(40px)} }
    @keyframes progress { from{width:100%} to{width:0} }

    .form-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .form-group { margin-bottom:13px; }
    .form-group label { display:block; font-size:0.78rem; font-weight:500; color:var(--muted); margin-bottom:6px; letter-spacing:0.02em; }
    .label-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; }
    .label-row label { margin-bottom:0; }
    .auto-badge { font-size:0.68rem; background:rgba(16,185,129,0.1); border:1px solid rgba(16,185,129,0.25); color:var(--accent2); padding:2px 8px; border-radius:20px; font-family:'JetBrains Mono',monospace; }

    .input-wrap { position:relative; }
    .input-wrap svg { position:absolute; left:11px; top:50%; transform:translateY(-50%); width:15px; height:15px; stroke:var(--muted); fill:none; stroke-width:1.8; pointer-events:none; }
    .form-group input, .form-group select {
      width:100%; background:var(--surface2); border:1px solid var(--border);
      border-radius:8px; padding:10px 11px 10px 36px; color:var(--text);
      font-family:'DM Sans',sans-serif; font-size:0.85rem;
      transition:border-color 0.2s, box-shadow 0.2s; outline:none; appearance:none;
    }
    .form-group input[readonly] { color:var(--accent2); cursor:default; font-family:'JetBrains Mono',monospace; font-size:0.8rem; }
    .form-group input::placeholder { color:var(--muted); opacity:0.6; }
    .form-group input:focus:not([readonly]), .form-group select:focus { border-color:rgba(245,158,11,0.4); box-shadow:0 0 0 3px rgba(245,158,11,0.08); }
    .form-group input.error { border-color:rgba(239,68,68,0.5); }
    .err-msg { font-size:0.72rem; color:var(--danger); margin-top:4px; display:none; }
    .err-msg.show { display:block; }

    .pw-toggle { position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; padding:0; display:grid; place-items:center; }
    .pw-toggle svg { position:static; transform:none; stroke:var(--muted); transition:stroke 0.2s; width:15px; height:15px; }
    .pw-toggle:hover svg { stroke:var(--text); }

    .pw-strength { margin-top:5px; display:flex; gap:3px; }
    .pw-bar { flex:1; height:3px; border-radius:100px; background:var(--surface2); transition:background 0.3s; }
    .pw-bar.weak   { background:var(--danger); }
    .pw-bar.fair   { background:var(--accent); }
    .pw-bar.strong { background:var(--accent2); }

    .form-divider { display:flex; align-items:center; gap:10px; margin:4px 0 14px; color:var(--muted); font-size:0.72rem; }
    .form-divider::before, .form-divider::after { content:''; flex:1; height:1px; background:var(--border); }

    .btn-submit {
      width:100%; background:var(--accent); color:#0a0f1c; border:none; border-radius:8px;
      padding:12px; font-family:'DM Sans',sans-serif; font-size:0.92rem; font-weight:700;
      cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px;
      transition:opacity 0.2s, transform 0.2s, box-shadow 0.2s;
      box-shadow:0 0 24px rgba(245,158,11,0.2); margin-top:4px;
    }
    .btn-submit:hover { opacity:0.9; transform:translateY(-1px); box-shadow:0 0 36px rgba(245,158,11,0.35); }
    .btn-submit:disabled { opacity:0.6; cursor:not-allowed; transform:none; }
    .btn-submit svg { width:15px; height:15px; stroke:#0a0f1c; fill:none; stroke-width:2.5; }
    .spinner { width:16px; height:16px; border:2px solid rgba(10,15,28,0.3); border-top-color:#0a0f1c; border-radius:50%; animation:spin 0.6s linear infinite; display:none; }
    .btn-submit.loading .spinner { display:block; }
    .btn-submit.loading .btn-label { display:none; }

    .login-link { text-align:center; margin-top:14px; font-size:0.82rem; color:var(--muted); }
    .login-link a { color:var(--accent); font-weight:600; text-decoration:none; transition:opacity 0.2s; }
    .login-link a:hover { opacity:0.8; }

    .success-state { display:none; text-align:center; padding:20px 0 8px; }
    .success-icon { width:60px; height:60px; background:rgba(16,185,129,0.1); border:1px solid rgba(16,185,129,0.25); border-radius:50%; display:grid; place-items:center; margin:0 auto 16px; }
    .success-icon svg { width:26px; height:26px; stroke:var(--accent2); fill:none; stroke-width:2; }
    .success-state h3 { font-family:'Bebas Neue',sans-serif; font-size:1.5rem; letter-spacing:0.04em; margin-bottom:6px; }
    .success-state p { font-size:0.85rem; color:var(--muted); line-height:1.6; }

    @keyframes spin { to { transform:rotate(360deg); } }
  </style>
</head>
<body>
<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<div class="modal-card">
  <button class="close-btn" onclick="parent.closeSignupModal()" title="Close">&#x2715;</button>

  <div class="modal-header" id="signupHeader">
    <div class="admin-badge">Admin Access Only</div>
    <h2>Create Admin Account</h2>
    <p>Register a new administrator for TrikScan.</p>
  </div>

  <form id="signupForm" novalidate>
    <div class="form-row">
      <div class="form-group">
        <label for="firstName">First Name</label>
        <div class="input-wrap">
          <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <input type="text" id="firstName" name="first_name" placeholder="Juan" autocomplete="given-name"/>
        </div>
        <div class="err-msg" id="firstNameErr">First name is required.</div>
      </div>
      <div class="form-group">
        <label for="lastName">Last Name</label>
        <div class="input-wrap">
          <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <input type="text" id="lastName" name="last_name" placeholder="dela Cruz" autocomplete="family-name"/>
        </div>
        <div class="err-msg" id="lastNameErr">Last name is required.</div>
      </div>
    </div>

    <!-- Auto-generated Username -->
    <div class="form-group">
      <div class="label-row">
        <label for="username">Username</label>
        <span class="auto-badge">⚡ Auto-generated</span>
      </div>
      <div class="input-wrap">
        <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        <input type="text" id="username" name="username" readonly placeholder="Fill in your name above..."/>
      </div>
      <div class="err-msg" id="usernameErr">Username could not be generated.</div>
    </div>

    <div class="form-group">
      <label for="email">Email Address</label>
      <div class="input-wrap">
        <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        <input type="email" id="email" name="email" placeholder="admin@trikscan.com" autocomplete="email"/>
      </div>
      <div class="err-msg" id="emailErr">Enter a valid email address.</div>
    </div>

    <div class="form-group">
      <label for="role">Admin Role</label>
      <div class="input-wrap">
        <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        <select id="role" name="role">
          <option value="">Select a role...</option>
          <option value="super_admin">Super Admin</option>
          <option value="admin">Admin</option>
          <option value="supervisor">Supervisor</option>
        </select>
      </div>
      <div class="err-msg" id="roleErr">Please select a role.</div>
    </div>

    <div class="form-divider">Account Security</div>

    <div class="form-group">
      <label for="password">Password</label>
      <div class="input-wrap">
        <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        <input type="password" id="password" name="password" placeholder="Create a strong password" autocomplete="new-password"/>
        <button type="button" class="pw-toggle" onclick="togglePw('password',this)">
          <svg viewBox="0 0 24 24" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
      </div>
      <div class="pw-strength">
        <div class="pw-bar" id="bar1"></div><div class="pw-bar" id="bar2"></div>
        <div class="pw-bar" id="bar3"></div><div class="pw-bar" id="bar4"></div>
      </div>
      <div class="err-msg" id="passwordErr">Password must be at least 8 characters.</div>
    </div>

    <div class="form-group">
      <label for="confirmPassword">Confirm Password</label>
      <div class="input-wrap">
        <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        <input type="password" id="confirmPassword" name="confirm_password" placeholder="Re-enter your password" autocomplete="new-password"/>
        <button type="button" class="pw-toggle" onclick="togglePw('confirmPassword',this)">
          <svg viewBox="0 0 24 24" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
      </div>
      <div class="err-msg" id="confirmPasswordErr">Passwords do not match.</div>
    </div>

    <button type="submit" class="btn-submit" id="submitBtn">
      <div class="btn-label" style="display:flex;align-items:center;gap:8px;">
        <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
        Create Admin Account
      </div>
      <div class="spinner"></div>
    </button>
  </form>

  <div class="login-link" id="loginLinkRow">
    Already have an account? <a href="#" onclick="parent.openLoginModal(); return false;">Log in here</a>
  </div>

  <div class="success-state" id="successState">
    <div class="success-icon">
      <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
    </div>
    <h3>Account Created!</h3>
    <p>Your admin account has been registered.<br>A confirmation email has been sent.</p>
  </div>
</div>

<script>
  // ── Auto-generate username from first + last name
  function generateUsername(first, last) {
    const clean = s => s.trim().toLowerCase().replace(/[^a-z0-9]/g, '');
    const f = clean(first), l = clean(last);
    if (!f && !l) return '';
    const base = (f ? f.charAt(0) : '') + (l ? l : f);
    return base.length >= 4 ? base : base + Math.floor(100 + Math.random() * 900);
  }

  let usernameGenerated = '';
  function refreshUsername() {
    const f = document.getElementById('firstName').value;
    const l = document.getElementById('lastName').value;
    const gen = generateUsername(f, l);
    usernameGenerated = gen;
    const el = document.getElementById('username');
    el.value = gen;
    el.placeholder = gen ? '' : 'Fill in your name above...';
  }
  document.getElementById('firstName').addEventListener('input', refreshUsername);
  document.getElementById('lastName').addEventListener('input',  refreshUsername);

  // ── Password visibility toggle
  function togglePw(id, btn) {
    const input = document.getElementById(id);
    const isText = input.type === 'text';
    input.type = isText ? 'password' : 'text';
    btn.querySelector('svg').innerHTML = isText
      ? '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>'
      : '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
  }

  // ── Password strength
  document.getElementById('password').addEventListener('input', function() {
    const bars = ['bar1','bar2','bar3','bar4'].map(id => document.getElementById(id));
    bars.forEach(b => b.className = 'pw-bar');
    if (!this.value) return;
    let s = 0;
    if (this.value.length >= 8)       s++;
    if (/[A-Z]/.test(this.value))     s++;
    if (/[0-9]/.test(this.value))     s++;
    if (/[^A-Za-z0-9]/.test(this.value)) s++;
    const cls = s <= 2 ? 'weak' : s === 3 ? 'fair' : 'strong';
    for (let i = 0; i < s; i++) bars[i].classList.add(cls);
  });

  // ── Toast system
  function showToast(type, title, msg) {
    const icons = { success:'✅', error:'❌', warning:'⚠️' };
    const container = document.getElementById('toastContainer');
    const t = document.createElement('div');
    t.className = `toast toast-${type}`;
    t.style.position = 'relative';
    t.innerHTML = `
      <span class="toast-icon">${icons[type]||'ℹ️'}</span>
      <div class="toast-body">
        <div class="toast-title">${title}</div>
        <div class="toast-msg">${msg}</div>
      </div>
      <button class="toast-close" onclick="dismissToast(this.parentElement)">✕</button>
      <div class="toast-progress"></div>`;
    container.appendChild(t);
    setTimeout(() => dismissToast(t), 4500);
  }
  function dismissToast(el) {
    if (!el || el.classList.contains('hiding')) return;
    el.classList.add('hiding');
    setTimeout(() => el.remove(), 320);
  }

  // ── Form submit
  document.getElementById('signupForm').addEventListener('submit', function(e) {
    e.preventDefault();
    let valid = true;

    const fields = [
      { id:'firstName',       errId:'firstNameErr',       check: v => v.trim().length > 0,    msg:'First name is required.' },
      { id:'lastName',        errId:'lastNameErr',        check: v => v.trim().length > 0,    msg:'Last name is required.' },
      { id:'username',        errId:'usernameErr',        check: v => v.trim().length >= 4,   msg:'Username too short.' },
      { id:'email',           errId:'emailErr',           check: v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v), msg:'Invalid email address.' },
      { id:'role',            errId:'roleErr',            check: v => v !== '',               msg:'Please select a role.' },
      { id:'password',        errId:'passwordErr',        check: v => v.length >= 8,          msg:'Password must be at least 8 characters.' },
      { id:'confirmPassword', errId:'confirmPasswordErr', check: v => v === document.getElementById('password').value, msg:'Passwords do not match.' },
    ];

    fields.forEach(f => {
      const input = document.getElementById(f.id);
      const err   = document.getElementById(f.errId);
      if (!f.check(input.value)) {
        input.classList.add('error');
        err.classList.add('show');
        valid = false;
      } else {
        input.classList.remove('error');
        err.classList.remove('show');
      }
    });

    if (!valid) {
      showToast('warning', 'Check your inputs', 'Please fill in all required fields correctly.');
      return;
    }

    const btn = document.getElementById('submitBtn');
    btn.classList.add('loading'); btn.disabled = true;

    const baseURL = window.location.href.substring(0, window.location.href.lastIndexOf('/') + 1);
    fetch(baseURL + 'process_signup.php', { method:'POST', body: new FormData(this) })
      .then(res => res.json())
      .then(data => {
        btn.classList.remove('loading'); btn.disabled = false;
        if (data.success) {
          showToast('success', 'Account Created!', data.message);
          setTimeout(() => {
            document.getElementById('signupForm').style.display = 'none';
            document.getElementById('signupHeader').style.display = 'none';
            document.getElementById('loginLinkRow').style.display = 'none';
            document.getElementById('successState').style.display = 'block';
          }, 800);
        } else {
          showToast('error', 'Registration Failed', data.message || 'Something went wrong. Please try again.');
        }
      })
      .catch(() => {
        btn.classList.remove('loading'); btn.disabled = false;
        showToast('error', 'Network Error', 'Cannot connect to server. Please check your connection.');
      });
  });

  // Clear error on input/change
  document.querySelectorAll('input:not([readonly]), select').forEach(el => {
    ['input','change'].forEach(evt => {
      el.addEventListener(evt, function() {
        this.classList.remove('error');
        const errEl = document.getElementById(this.id + 'Err');
        if (errEl) errEl.classList.remove('show');
      });
    });
  });
</script>
</body>
</html>