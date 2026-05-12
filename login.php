<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Login — TrikScan</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --bg: #0a0f1c; --surface: #111827; --surface2: #1a2235;
      --accent: #f59e0b; --accent2: #10b981; --accent3: #3b82f6;
      --text: #f1f5f9; --muted: #64748b; --border: rgba(255,255,255,0.08); --danger: #ef4444;
    }
    html, body { font-family:'DM Sans',sans-serif; background:transparent; color:var(--text); }

    /* ── MODAL CARD ── */
    .modal-card {
      background: var(--surface); border:1px solid var(--border); border-radius:20px;
      padding:36px 32px 30px; width:100%; max-width:420px; margin:0 auto;
      position:relative; box-shadow:0 32px 80px rgba(0,0,0,0.6), 0 0 0 1px rgba(255,255,255,0.04);
    }
    .close-btn {
      position:absolute; top:14px; right:14px; width:30px; height:30px; border-radius:50%;
      background:var(--surface2); border:1px solid var(--border); color:var(--muted);
      font-size:1rem; cursor:pointer; display:grid; place-items:center;
      transition:background 0.2s, color 0.2s;
    }
    .close-btn:hover { background:rgba(255,255,255,0.08); color:var(--text); }

    /* ── HEADER ── */
    .modal-header { margin-bottom:24px; }
    .login-badge {
      display:inline-flex; align-items:center; gap:6px;
      background:rgba(59,130,246,0.1); border:1px solid rgba(59,130,246,0.25);
      border-radius:100px; padding:3px 12px; font-size:0.7rem; font-weight:600;
      color:var(--accent3); letter-spacing:0.08em; text-transform:uppercase;
      font-family:'JetBrains Mono',monospace; margin-bottom:10px;
    }
    .login-badge::before { content:''; width:6px; height:6px; background:var(--accent3); border-radius:50%; animation:pulse 1.5s infinite; }
    .modal-header h2 { font-family:'Bebas Neue',sans-serif; font-size:2rem; letter-spacing:0.03em; line-height:1; }
    .modal-header p  { margin-top:5px; font-size:0.85rem; color:var(--muted); font-weight:300; }

    /* ── TOAST ── */
    .toast-container { position:fixed; top:16px; right:16px; z-index:9999; display:flex; flex-direction:column; gap:10px; pointer-events:none; }
    .toast {
      display:flex; align-items:flex-start; gap:10px; padding:12px 16px;
      border-radius:10px; font-size:0.83rem; line-height:1.5; min-width:260px; max-width:340px;
      pointer-events:all; box-shadow:0 8px 32px rgba(0,0,0,0.4);
      animation:toastIn 0.35s cubic-bezier(0.34,1.56,0.64,1) forwards;
      border:1px solid transparent; position:relative;
    }
    .toast.hiding { animation:toastOut 0.3s ease forwards; }
    .toast-success { background:rgba(16,185,129,0.12); border-color:rgba(16,185,129,0.3); color:#6ee7b7; }
    .toast-error   { background:rgba(239,68,68,0.12);  border-color:rgba(239,68,68,0.3);  color:#fca5a5; }
    .toast-warning { background:rgba(245,158,11,0.12); border-color:rgba(245,158,11,0.3); color:#fcd34d; }
    .toast-icon { font-size:1rem; flex-shrink:0; margin-top:1px; }
    .toast-body { flex:1; }
    .toast-title { font-weight:700; font-size:0.82rem; margin-bottom:2px; }
    .toast-msg   { font-size:0.8rem; opacity:0.85; }
    .toast-close { background:none; border:none; cursor:pointer; color:inherit; opacity:0.6; font-size:1rem; padding:0; flex-shrink:0; }
    .toast-close:hover { opacity:1; }
    .toast-progress { position:absolute; bottom:0; left:0; height:2px; border-radius:0 0 10px 10px; animation:progress 4s linear forwards; }
    .toast-success .toast-progress { background:var(--accent2); }
    .toast-error   .toast-progress { background:var(--danger); }
    .toast-warning .toast-progress { background:var(--accent); }
    @keyframes toastIn  { from{opacity:0;transform:translateX(40px)} to{opacity:1;transform:translateX(0)} }
    @keyframes toastOut { from{opacity:1;transform:translateX(0)}    to{opacity:0;transform:translateX(40px)} }
    @keyframes progress { from{width:100%} to{width:0} }
    @keyframes pulse    { 0%,100%{opacity:1} 50%{opacity:0.4} }

    /* ── FORM ── */
    .form-group { margin-bottom:16px; }
    .form-group label { display:block; font-size:0.8rem; font-weight:500; color:var(--muted); margin-bottom:7px; }
    .input-wrap { position:relative; }
    .input-wrap svg.input-icon { position:absolute; left:11px; top:50%; transform:translateY(-50%); width:15px; height:15px; stroke:var(--muted); fill:none; stroke-width:1.8; pointer-events:none; transition:stroke 0.2s; }
    .form-group input {
      width:100%; background:var(--surface2); border:1px solid var(--border);
      border-radius:8px; padding:11px 40px 11px 36px; color:var(--text);
      font-family:'DM Sans',sans-serif; font-size:0.875rem;
      transition:border-color 0.2s, box-shadow 0.2s; outline:none;
    }
    .form-group input::placeholder { color:var(--muted); opacity:0.6; }
    .form-group input:focus { border-color:rgba(59,130,246,0.5); box-shadow:0 0 0 3px rgba(59,130,246,0.1); }
    .form-group input:focus + svg.input-icon,
    .input-wrap:focus-within svg.input-icon { stroke:var(--accent3); }
    .form-group input.error { border-color:rgba(239,68,68,0.5); }
    .form-group input.success { border-color:rgba(16,185,129,0.4); }
    .err-msg { font-size:0.72rem; color:var(--danger); margin-top:4px; display:none; }
    .err-msg.show { display:block; }

    .pw-toggle { position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; padding:0; display:grid; place-items:center; }
    .pw-toggle svg { width:15px; height:15px; stroke:var(--muted); fill:none; stroke-width:1.8; transition:stroke 0.2s; }
    .pw-toggle:hover svg { stroke:var(--text); }

    .forgot-row { display:flex; justify-content:flex-end; margin-top:-8px; margin-bottom:16px; }
    .forgot-link { font-size:0.78rem; color:var(--accent); text-decoration:none; font-weight:600; transition:opacity 0.2s; }
    .forgot-link:hover { opacity:0.75; }

    /* ── SUBMIT ── */
    .btn-submit {
      width:100%; background:var(--accent3); color:#fff; border:none; border-radius:8px;
      padding:12px; font-family:'DM Sans',sans-serif; font-size:0.92rem; font-weight:700;
      cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px;
      transition:opacity 0.2s, transform 0.2s, box-shadow 0.2s;
      box-shadow:0 0 24px rgba(59,130,246,0.25); position:relative; overflow:hidden;
    }
    .btn-submit:hover { opacity:0.9; transform:translateY(-1px); box-shadow:0 0 36px rgba(59,130,246,0.4); }
    .btn-submit:disabled { opacity:0.6; cursor:not-allowed; transform:none; }
    .btn-submit svg { width:15px; height:15px; stroke:#fff; fill:none; stroke-width:2.5; }

    /* Loading shimmer on button */
    .btn-submit.loading { pointer-events:none; }
    .btn-submit.loading::after {
      content:''; position:absolute; inset:0;
      background:linear-gradient(90deg,transparent 0%,rgba(255,255,255,0.15) 50%,transparent 100%);
      animation:shimmer 1.2s infinite;
    }
    @keyframes shimmer { from{transform:translateX(-100%)} to{transform:translateX(100%)} }

    .spinner { width:16px; height:16px; border:2px solid rgba(255,255,255,0.3); border-top-color:#fff; border-radius:50%; animation:spin 0.6s linear infinite; display:none; }
    .btn-submit.loading .spinner { display:block; }
    .btn-submit.loading .btn-label { visibility:hidden; }
    @keyframes spin { to{transform:rotate(360deg)} }

    /* ── LOADING OVERLAY ── */
    .page-loader {
      position:fixed; inset:0; background:rgba(10,15,28,0.92); backdrop-filter:blur(8px);
      z-index:10000; display:none; flex-direction:column; align-items:center; justify-content:center; gap:20px;
    }
    .page-loader.active { display:flex; }
    .loader-ring {
      width:56px; height:56px; border-radius:50%; position:relative;
      border:3px solid var(--surface2);
    }
    .loader-ring::before {
      content:''; position:absolute; inset:-3px; border-radius:50%;
      border:3px solid transparent; border-top-color:var(--accent3);
      animation:spin 0.9s linear infinite;
    }
    .loader-ring::after {
      content:''; position:absolute; inset:6px; border-radius:50%;
      border:2px solid transparent; border-top-color:var(--accent);
      animation:spin 0.6s linear infinite reverse;
    }
    .loader-logo { font-family:'Bebas Neue',sans-serif; font-size:1.4rem; letter-spacing:0.1em; color:var(--text); }
    .loader-logo span { color:var(--accent); }
    .loader-msg { font-size:0.82rem; color:var(--muted); font-family:'JetBrains Mono',monospace; }
    .loader-dots::after { content:''; animation:dots 1.5s infinite steps(4); }
    @keyframes dots { 0%{content:''} 25%{content:'.'} 50%{content:'..'} 75%{content:'...'} 100%{content:''} }

    /* ── DIVIDER ── */
    .or-divider { display:flex; align-items:center; gap:12px; margin:16px 0; color:var(--muted); font-size:0.75rem; }
    .or-divider::before,.or-divider::after { content:''; flex:1; height:1px; background:var(--border); }

    /* ── SIGNUP LINK ── */
    .signup-link { text-align:center; font-size:0.82rem; color:var(--muted); }
    .signup-link a { color:var(--accent); font-weight:600; text-decoration:none; transition:opacity 0.2s; }
    .signup-link a:hover { opacity:0.8; }
  </style>
</head>
<body>

<!-- Page Loader -->
<div class="page-loader" id="pageLoader">
  <div class="loader-ring"></div>
  <div class="loader-logo">Trik<span>Scan</span></div>
  <div class="loader-msg">Verifying credentials<span class="loader-dots"></span></div>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<div class="modal-card">
  <button class="close-btn" onclick="parent.closeLoginModal()" title="Close">&#x2715;</button>

  <div class="modal-header">
    <div class="login-badge">Admin Portal</div>
    <h2>Welcome Back</h2>
    <p>Sign in to access the TrikScan dashboard.</p>
  </div>

  <form id="loginForm" novalidate>
    <div class="form-group">
      <label for="identifier">Username or Email</label>
      <div class="input-wrap">
        <svg class="input-icon" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        <input type="text" id="identifier" name="identifier" placeholder="Enter username or email" autocomplete="username"/>
      </div>
      <div class="err-msg" id="identifierErr">Please enter your username or email.</div>
    </div>

    <div class="form-group">
      <label for="password">Password</label>
      <div class="input-wrap">
        <svg class="input-icon" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        <input type="password" id="password" name="password" placeholder="Enter your password" autocomplete="current-password"/>
        <button type="button" class="pw-toggle" onclick="togglePw()">
          <svg id="eyeIcon" viewBox="0 0 24 24" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
      </div>
      <div class="err-msg" id="passwordErr">Please enter your password.</div>
    </div>

    <div class="forgot-row">
      <a href="#" class="forgot-link" onclick="openForgotPassword(); return false;">Forgot password?</a>
    </div>

    <button type="submit" class="btn-submit" id="submitBtn">
      <div class="btn-label" style="display:flex;align-items:center;gap:8px;">
        <svg viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
        Log In
      </div>
      <div class="spinner"></div>
    </button>
  </form>

  <div class="or-divider">OR</div>
  <div class="signup-link">
    No account yet? <a href="#" onclick="parent.openSignupFromLogin(); return false;">Sign up here</a>
  </div>
</div>

<script>
  function togglePw() {
    const input = document.getElementById('password');
    const isText = input.type === 'text';
    input.type = isText ? 'password' : 'text';
    document.getElementById('eyeIcon').innerHTML = isText
      ? '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>'
      : '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
  }

  function showToast(type, title, msg) {
    const icons = { success:'✅', error:'❌', warning:'⚠️' };
    const container = document.getElementById('toastContainer');
    const t = document.createElement('div');
    t.className = `toast toast-${type}`;
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

  document.getElementById('loginForm').addEventListener('submit', function(e) {
    e.preventDefault();
    let valid = true;

    const fields = [
      { id:'identifier', errId:'identifierErr', check: v => v.trim().length > 0 },
      { id:'password',   errId:'passwordErr',   check: v => v.length > 0 },
    ];
    fields.forEach(f => {
      const input = document.getElementById(f.id);
      const err   = document.getElementById(f.errId);
      if (!f.check(input.value)) {
        input.classList.add('error'); err.classList.add('show'); valid = false;
      } else {
        input.classList.remove('error'); err.classList.remove('show');
      }
    });

    if (!valid) {
      showToast('warning', 'Missing Fields', 'Please fill in all required fields.');
      return;
    }

    const btn = document.getElementById('submitBtn');
    btn.classList.add('loading'); btn.disabled = true;

    const baseURL = window.location.href.substring(0, window.location.href.lastIndexOf('/') + 1);
    fetch(baseURL + 'process_login.php', { method:'POST', body: new FormData(this) })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          showToast('success', 'Login Successful!', 'Redirecting to dashboard...');
          document.getElementById('pageLoader').classList.add('active');
          setTimeout(() => {
            // Redirect the parent page, not just the iframe
            parent.window.location.href = baseURL + 'admin_dashboard.php';
          }, 1200);
        } else {
          btn.classList.remove('loading'); btn.disabled = false;
          showToast('error', 'Login Failed', data.message || 'Invalid credentials. Please try again.');
          document.getElementById('identifier').classList.add('error');
          document.getElementById('password').classList.add('error');
        }
      })
      .catch(() => {
        btn.classList.remove('loading'); btn.disabled = false;
        showToast('error', 'Network Error', 'Cannot connect to server. Please try again.');
      });
  });

  // Open forgot password modal via parent
  function openForgotPassword() {
    if (typeof parent.openForgotPasswordModal === 'function') {
      parent.openForgotPasswordModal();
    } else {
      window.location.href = 'forgot_password.php';
    }
  }

  // Clear error on input
  document.querySelectorAll('input').forEach(el => {
    el.addEventListener('input', function() {
      this.classList.remove('error');
      const errEl = document.getElementById(this.id + 'Err');
      if (errEl) errEl.classList.remove('show');
    });
  });
</script>
</body>
</html>