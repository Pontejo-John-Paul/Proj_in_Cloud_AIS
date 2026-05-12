<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Forgot Password — TrikScan</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --bg: #0a0f1c; --surface: #111827; --surface2: #1a2235;
      --accent: #f59e0b; --accent2: #10b981; --accent3: #3b82f6;
      --text: #f1f5f9; --muted: #64748b; --border: rgba(255,255,255,0.08);
      --danger: #ef4444;
    }
    html, body { font-family: 'DM Sans', sans-serif; background: transparent; color: var(--text); }

    /* ── MODAL CARD ── */
    .modal-card {
      background: var(--surface); border: 1px solid var(--border); border-radius: 20px;
      padding: 36px 32px 30px; width: 100%; max-width: 420px; margin: 0 auto;
      position: relative; box-shadow: 0 32px 80px rgba(0,0,0,0.6), 0 0 0 1px rgba(255,255,255,0.04);
    }
    .back-btn {
      position: absolute; top: 14px; left: 14px; width: 30px; height: 30px; border-radius: 50%;
      background: var(--surface2); border: 1px solid var(--border); color: var(--muted);
      font-size: 1rem; cursor: pointer; display: grid; place-items: center;
      transition: background 0.2s, color 0.2s; text-decoration: none;
    }
    .back-btn:hover { background: rgba(255,255,255,0.08); color: var(--text); }
    .close-btn {
      position: absolute; top: 14px; right: 14px; width: 30px; height: 30px; border-radius: 50%;
      background: var(--surface2); border: 1px solid var(--border); color: var(--muted);
      font-size: 1rem; cursor: pointer; display: grid; place-items: center;
      transition: background 0.2s, color 0.2s;
    }
    .close-btn:hover { background: rgba(255,255,255,0.08); color: var(--text); }

    /* ── STEPS ── */
    .step { display: none; }
    .step.active { display: block; }

    .step-indicator {
      display: flex; gap: 6px; margin-bottom: 20px;
    }
    .step-dot {
      height: 3px; border-radius: 2px; transition: all 0.3s;
      background: var(--border);
    }
    .step-dot.active { background: var(--accent3); }
    .step-dot.done   { background: var(--accent2); }
    .step-dot { flex: 1; }

    /* ── HEADER ── */
    .modal-header { margin-bottom: 24px; }
    .step-badge {
      display: inline-flex; align-items: center; gap: 6px;
      background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.25);
      border-radius: 100px; padding: 3px 12px; font-size: 0.7rem; font-weight: 600;
      color: var(--accent3); letter-spacing: 0.08em; text-transform: uppercase;
      font-family: 'JetBrains Mono', monospace; margin-bottom: 10px;
    }
    .modal-header h2 { font-family: 'Bebas Neue', sans-serif; font-size: 2rem; letter-spacing: 0.03em; line-height: 1; }
    .modal-header p  { margin-top: 5px; font-size: 0.85rem; color: var(--muted); font-weight: 300; }

    /* ── TOAST ── */
    .toast-container { position: fixed; top: 16px; right: 16px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; pointer-events: none; }
    .toast {
      display: flex; align-items: flex-start; gap: 10px; padding: 12px 16px;
      border-radius: 10px; font-size: 0.83rem; line-height: 1.5; min-width: 260px; max-width: 340px;
      pointer-events: all; box-shadow: 0 8px 32px rgba(0,0,0,0.4);
      animation: toastIn 0.35s cubic-bezier(0.34,1.56,0.64,1) forwards;
      border: 1px solid transparent; position: relative;
    }
    .toast.hiding { animation: toastOut 0.3s ease forwards; }
    .toast-success { background: rgba(16,185,129,0.12); border-color: rgba(16,185,129,0.3); color: #6ee7b7; }
    .toast-error   { background: rgba(239,68,68,0.12);  border-color: rgba(239,68,68,0.3);  color: #fca5a5; }
    .toast-warning { background: rgba(245,158,11,0.12); border-color: rgba(245,158,11,0.3); color: #fcd34d; }
    .toast-icon    { font-size: 1rem; flex-shrink: 0; margin-top: 1px; }
    .toast-body    { flex: 1; }
    .toast-title   { font-weight: 700; font-size: 0.82rem; margin-bottom: 2px; }
    .toast-msg     { font-size: 0.8rem; opacity: 0.85; }
    .toast-close   { background: none; border: none; cursor: pointer; color: inherit; opacity: 0.6; font-size: 1rem; padding: 0; flex-shrink: 0; }
    .toast-close:hover { opacity: 1; }
    .toast-progress { position: absolute; bottom: 0; left: 0; height: 2px; border-radius: 0 0 10px 10px; animation: progress 4s linear forwards; }
    .toast-success .toast-progress { background: var(--accent2); }
    .toast-error   .toast-progress { background: var(--danger); }
    .toast-warning .toast-progress { background: var(--accent); }
    @keyframes toastIn  { from{opacity:0;transform:translateX(40px)} to{opacity:1;transform:translateX(0)} }
    @keyframes toastOut { from{opacity:1;transform:translateX(0)}    to{opacity:0;transform:translateX(40px)} }
    @keyframes progress { from{width:100%} to{width:0} }
    @keyframes pulse    { 0%,100%{opacity:1} 50%{opacity:0.4} }

    /* ── FORM ── */
    .form-group { margin-bottom: 16px; }
    .form-group label { display: block; font-size: 0.8rem; font-weight: 500; color: var(--muted); margin-bottom: 7px; }
    .input-wrap { position: relative; }
    .input-wrap svg.input-icon {
      position: absolute; left: 11px; top: 50%; transform: translateY(-50%);
      width: 15px; height: 15px; stroke: var(--muted); fill: none; stroke-width: 1.8;
      pointer-events: none; transition: stroke 0.2s;
    }
    .form-group input {
      width: 100%; background: var(--surface2); border: 1px solid var(--border);
      border-radius: 8px; padding: 11px 40px 11px 36px; color: var(--text);
      font-family: 'DM Sans', sans-serif; font-size: 0.875rem;
      transition: border-color 0.2s, box-shadow 0.2s; outline: none;
    }
    .form-group input::placeholder { color: var(--muted); opacity: 0.6; }
    .form-group input:focus { border-color: rgba(59,130,246,0.5); box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
    .input-wrap:focus-within svg.input-icon { stroke: var(--accent3); }
    .form-group input.error   { border-color: rgba(239,68,68,0.5); }
    .form-group input.success { border-color: rgba(16,185,129,0.4); }
    .err-msg { font-size: 0.72rem; color: var(--danger); margin-top: 4px; display: none; }
    .err-msg.show { display: block; }

    .pw-toggle { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; padding: 0; display: grid; place-items: center; }
    .pw-toggle svg { width: 15px; height: 15px; stroke: var(--muted); fill: none; stroke-width: 1.8; transition: stroke 0.2s; }
    .pw-toggle:hover svg { stroke: var(--text); }

    /* ── OTP INPUT GROUP ── */
    .otp-group {
      display: flex; gap: 8px; justify-content: space-between; margin-bottom: 6px;
    }
    .otp-digit {
      width: 100%; max-width: 52px; height: 56px;
      background: var(--surface2); border: 1px solid var(--border);
      border-radius: 10px; color: var(--text);
      font-family: 'JetBrains Mono', monospace; font-size: 1.4rem; font-weight: 600;
      text-align: center; outline: none; transition: border-color 0.2s, box-shadow 0.2s;
    }
    .otp-digit:focus { border-color: rgba(59,130,246,0.5); box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
    .otp-digit.error { border-color: rgba(239,68,68,0.5); }
    .otp-digit.filled { border-color: rgba(59,130,246,0.3); }

    /* ── TIMER / RESEND ── */
    .otp-meta { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
    .otp-timer { font-size: 0.75rem; font-family: 'JetBrains Mono', monospace; color: var(--muted); }
    .otp-timer.urgent { color: var(--danger); }
    .resend-btn {
      font-size: 0.78rem; color: var(--accent); font-weight: 600;
      background: none; border: none; cursor: pointer; padding: 0;
      transition: opacity 0.2s;
    }
    .resend-btn:hover { opacity: 0.75; }
    .resend-btn:disabled { opacity: 0.4; cursor: not-allowed; }

    /* ── RESEND ATTEMPTS ── */
    .resend-info { font-size: 0.72rem; color: var(--muted); text-align: center; margin-bottom: 16px; }

    /* ── SUBMIT ── */
    .btn-submit {
      width: 100%; background: var(--accent3); color: #fff; border: none; border-radius: 8px;
      padding: 12px; font-family: 'DM Sans', sans-serif; font-size: 0.92rem; font-weight: 700;
      cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;
      transition: opacity 0.2s, transform 0.2s, box-shadow 0.2s;
      box-shadow: 0 0 24px rgba(59,130,246,0.25); position: relative; overflow: hidden;
    }
    .btn-submit:hover  { opacity: 0.9; transform: translateY(-1px); box-shadow: 0 0 36px rgba(59,130,246,0.4); }
    .btn-submit:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
    .btn-submit svg    { width: 15px; height: 15px; stroke: #fff; fill: none; stroke-width: 2.5; }
    .btn-submit.loading { pointer-events: none; }
    .btn-submit.loading::after {
      content: ''; position: absolute; inset: 0;
      background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.15) 50%, transparent 100%);
      animation: shimmer 1.2s infinite;
    }
    @keyframes shimmer { from{transform:translateX(-100%)} to{transform:translateX(100%)} }
    .spinner { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.6s linear infinite; display: none; }
    .btn-submit.loading .spinner    { display: block; }
    .btn-submit.loading .btn-label  { visibility: hidden; }
    @keyframes spin { to{transform:rotate(360deg)} }

    /* ── SUCCESS STATE ── */
    .success-icon {
      width: 64px; height: 64px; border-radius: 50%; background: rgba(16,185,129,0.1);
      border: 2px solid rgba(16,185,129,0.3); display: grid; place-items: center; margin: 0 auto 20px;
    }
    .success-icon svg { width: 28px; height: 28px; stroke: var(--accent2); fill: none; stroke-width: 2.5; }

    /* ── PASSWORD STRENGTH ── */
    .strength-bar { display: flex; gap: 4px; margin-top: 8px; }
    .strength-seg { flex: 1; height: 3px; border-radius: 2px; background: var(--border); transition: background 0.3s; }
    .strength-label { font-size: 0.7rem; color: var(--muted); margin-top: 4px; text-align: right; }

    .back-to-login {
      text-align: center; margin-top: 18px;
      font-size: 0.8rem; color: var(--muted);
    }
    .back-to-login a { color: var(--accent3); text-decoration: none; font-weight: 600; }
    .back-to-login a:hover { text-decoration: underline; }
  </style>
</head>
<body>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<div class="modal-card">
  <button class="close-btn" onclick="parent.closeLoginModal()" title="Close">&#x2715;</button>

  <!-- STEP DOTS -->
  <div class="step-indicator">
    <div class="step-dot active"  id="dot1"></div>
    <div class="step-dot"         id="dot2"></div>
    <div class="step-dot"         id="dot3"></div>
  </div>

  <!-- ══════════════════════════════════════════ -->
  <!--  STEP 1 — Enter Email                      -->
  <!-- ══════════════════════════════════════════ -->
  <div class="step active" id="step1">
    <div class="modal-header">
      <div class="step-badge">Step 1 of 3</div>
      <h2>Forgot Password</h2>
      <p>Enter your registered email and we'll send you a 6-digit OTP.</p>
    </div>

    <div class="form-group">
      <label for="emailInput">Email Address</label>
      <div class="input-wrap">
        <svg class="input-icon" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        <input type="email" id="emailInput" placeholder="you@example.com" autocomplete="email"/>
      </div>
      <div class="err-msg" id="emailErr">Please enter a valid email address.</div>
    </div>

    <button class="btn-submit" id="sendOtpBtn" onclick="sendOtp()">
      <div class="btn-label" style="display:flex;align-items:center;gap:8px;">
        <svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        Send OTP
      </div>
      <div class="spinner"></div>
    </button>

    <div class="back-to-login">
      Remembered your password? <a href="#" onclick="parent.closeLoginModal(); return false;">Back to Login</a>
    </div>
  </div>

  <!-- ══════════════════════════════════════════ -->
  <!--  STEP 2 — Enter OTP                        -->
  <!-- ══════════════════════════════════════════ -->
  <div class="step" id="step2">
    <div class="modal-header">
      <div class="step-badge">Step 2 of 3</div>
      <h2>Enter OTP</h2>
      <p id="otpSentDesc">Enter the 6-digit code sent to your email.</p>
    </div>

    <div class="otp-group" id="otpGroup">
      <input class="otp-digit" type="text" inputmode="numeric" maxlength="1" id="d0"/>
      <input class="otp-digit" type="text" inputmode="numeric" maxlength="1" id="d1"/>
      <input class="otp-digit" type="text" inputmode="numeric" maxlength="1" id="d2"/>
      <input class="otp-digit" type="text" inputmode="numeric" maxlength="1" id="d3"/>
      <input class="otp-digit" type="text" inputmode="numeric" maxlength="1" id="d4"/>
      <input class="otp-digit" type="text" inputmode="numeric" maxlength="1" id="d5"/>
    </div>

    <div class="otp-meta">
      <span class="otp-timer" id="otpTimer">Expires in 05:00</span>
      <button class="resend-btn" id="resendBtn" onclick="resendOtp()" disabled>Resend OTP</button>
    </div>
    <div class="resend-info" id="resendInfo">You have <strong id="resendLeft">2</strong> resend attempt(s) remaining.</div>

    <button class="btn-submit" id="verifyOtpBtn" onclick="verifyOtp()">
      <div class="btn-label" style="display:flex;align-items:center;gap:8px;">
        <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        Verify OTP
      </div>
      <div class="spinner"></div>
    </button>
  </div>

  <!-- ══════════════════════════════════════════ -->
  <!--  STEP 3 — Set New Password                 -->
  <!-- ══════════════════════════════════════════ -->
  <div class="step" id="step3">
    <div class="modal-header">
      <div class="step-badge">Step 3 of 3</div>
      <h2>New Password</h2>
      <p>OTP verified! Create a strong new password for your account.</p>
    </div>

    <div class="form-group">
      <label for="newPass">New Password</label>
      <div class="input-wrap">
        <svg class="input-icon" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        <input type="password" id="newPass" placeholder="Min. 8 chars, upper, number, symbol" oninput="updateStrength()"/>
        <button type="button" class="pw-toggle" onclick="togglePw('newPass','eyeNew')">
          <svg id="eyeNew" viewBox="0 0 24 24" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
      </div>
      <div class="strength-bar">
        <div class="strength-seg" id="s1"></div>
        <div class="strength-seg" id="s2"></div>
        <div class="strength-seg" id="s3"></div>
        <div class="strength-seg" id="s4"></div>
      </div>
      <div class="strength-label" id="strengthLabel"></div>
      <div class="err-msg" id="newPassErr"></div>
    </div>

    <div class="form-group">
      <label for="confirmPass">Confirm Password</label>
      <div class="input-wrap">
        <svg class="input-icon" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        <input type="password" id="confirmPass" placeholder="Repeat your new password"/>
        <button type="button" class="pw-toggle" onclick="togglePw('confirmPass','eyeConfirm')">
          <svg id="eyeConfirm" viewBox="0 0 24 24" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
      </div>
      <div class="err-msg" id="confirmPassErr">Passwords do not match.</div>
    </div>

    <button class="btn-submit" id="resetPassBtn" onclick="resetPassword()">
      <div class="btn-label" style="display:flex;align-items:center;gap:8px;">
        <svg viewBox="0 0 24 24"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
        Reset Password
      </div>
      <div class="spinner"></div>
    </button>
  </div>

  <!-- ══════════════════════════════════════════ -->
  <!--  STEP 4 — Success                          -->
  <!-- ══════════════════════════════════════════ -->
  <div class="step" id="step4">
    <div style="text-align:center; padding: 12px 0 8px;">
      <div class="success-icon">
        <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
      </div>
      <h2 style="font-family:'Bebas Neue',sans-serif;font-size:2rem;letter-spacing:0.03em;">Password Reset!</h2>
      <p style="margin-top:8px;font-size:0.85rem;color:var(--muted);">Your password has been updated successfully. You can now log in with your new credentials.</p>
      <button class="btn-submit" style="margin-top:24px;" onclick="parent.closeLoginModal()">
        <div class="btn-label" style="display:flex;align-items:center;gap:8px;">
          <svg viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
          Back to Login
        </div>
      </button>
    </div>
  </div>

</div><!-- .modal-card -->

<script>
  // ── STATE ──
  let currentStep    = 1;
  let userEmail      = '';
  let resetToken     = '';
  let timerInterval  = null;
  let resendLeft     = 2;   // server tells us remaining; default for initial send

  // Absolute URL derived from the actual script location so fetch works
  // correctly whether this page is loaded directly or inside an iframe.
  const baseURL = '<?php
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host  = $_SERVER['HTTP_HOST'];
    $dir   = rtrim(str_replace(basename(__FILE__), '', $_SERVER['PHP_SELF']), '/');
    echo $proto . '://' . $host . $dir . '/';
  ?>';

  // ── UTILITIES ──
  function showToast(type, title, msg) {
    const icons = { success: '✅', error: '❌', warning: '⚠️' };
    const container = document.getElementById('toastContainer');
    const t = document.createElement('div');
    t.className = `toast toast-${type}`;
    t.innerHTML = `
      <span class="toast-icon">${icons[type] || 'ℹ️'}</span>
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

  function setLoading(btnId, on) {
    const btn = document.getElementById(btnId);
    if (on) { btn.classList.add('loading'); btn.disabled = true; }
    else     { btn.classList.remove('loading'); btn.disabled = false; }
  }

  function goToStep(n) {
    document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
    document.getElementById('step' + n).classList.add('active');
    // Update dots (only 3 dots for 4 steps; step 4 is success)
    for (let i = 1; i <= 3; i++) {
      const dot = document.getElementById('dot' + i);
      if (i < Math.min(n, 4))       dot.className = 'step-dot done';
      else if (i === Math.min(n, 3)) dot.className = 'step-dot active';
      else                           dot.className = 'step-dot';
    }
    currentStep = n;
  }

  // ── OTP TIMER ──
  function startTimer(seconds) {
    clearInterval(timerInterval);
    const timerEl  = document.getElementById('otpTimer');
    const resendBtn = document.getElementById('resendBtn');
    let remaining   = seconds;

    resendBtn.disabled = true;

    timerInterval = setInterval(() => {
      remaining--;
      const m = String(Math.floor(remaining / 60)).padStart(2, '0');
      const s = String(remaining % 60).padStart(2, '0');
      timerEl.textContent = `Expires in ${m}:${s}`;
      timerEl.className = remaining <= 30 ? 'otp-timer urgent' : 'otp-timer';

      if (remaining <= 0) {
        clearInterval(timerInterval);
        timerEl.textContent = 'OTP expired';
        timerEl.className   = 'otp-timer urgent';
        if (resendLeft > 0) { resendBtn.disabled = false; }
      }
    }, 1000);
  }

  // ── OTP INPUT NAVIGATION ──
  document.addEventListener('DOMContentLoaded', () => {
    for (let i = 0; i < 6; i++) {
      const el = document.getElementById('d' + i);
      el.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(-1);
        this.classList.toggle('filled', this.value !== '');
        if (this.value && i < 5) document.getElementById('d' + (i + 1)).focus();
      });
      el.addEventListener('keydown', function (e) {
        if (e.key === 'Backspace' && !this.value && i > 0)
          document.getElementById('d' + (i - 1)).focus();
      });
      el.addEventListener('paste', function (e) {
        e.preventDefault();
        const text = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
        [...text].forEach((ch, idx) => {
          const d = document.getElementById('d' + idx);
          if (d) { d.value = ch; d.classList.add('filled'); }
        });
        const next = document.getElementById('d' + Math.min(text.length, 5));
        if (next) next.focus();
      });
    }
  });

  function getOtpValue() {
    return Array.from({length: 6}, (_, i) => document.getElementById('d' + i).value).join('');
  }
  function clearOtp() {
    for (let i = 0; i < 6; i++) {
      const d = document.getElementById('d' + i);
      d.value = ''; d.className = 'otp-digit';
    }
  }

  // ── STEP 1: SEND OTP ──
  async function sendOtp() {
    const emailEl  = document.getElementById('emailInput');
    const errEl    = document.getElementById('emailErr');
    const email    = emailEl.value.trim();
    const emailRx  = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (!email || !emailRx.test(email)) {
      emailEl.classList.add('error'); errEl.classList.add('show'); return;
    }
    emailEl.classList.remove('error'); errEl.classList.remove('show');

    setLoading('sendOtpBtn', true);
    try {
      const fd = new FormData();
      fd.append('action', 'send_otp');
      fd.append('email', email);
      const res  = await fetch(baseURL + 'process_forgot_password.php', { method: 'POST', body: fd });
      const data = await res.json();

      if (data.success) {
        userEmail  = email;
        resendLeft = data.resend_left ?? 2;
        document.getElementById('otpSentDesc').textContent =
          `Enter the 6-digit code sent to ${email}.`;
        document.getElementById('resendLeft').textContent = resendLeft;
        document.getElementById('resendInfo').style.display = resendLeft > 0 ? '' : 'none';
        goToStep(2);
        startTimer(data.expires_in ?? 300);
        showToast('success', 'OTP Sent!', data.message);
      } else {
        showToast('error', 'Failed', data.message);
        if (res.status === 429) {
          // Blocked — show wait time
          const wait = data.wait_seconds ?? 300;
          document.getElementById('sendOtpBtn').disabled = true;
          setTimeout(() => document.getElementById('sendOtpBtn').disabled = false, wait * 1000);
        }
      }
    } catch (_) {
      showToast('error', 'Network Error', 'Cannot reach server. Please try again.');
    } finally {
      setLoading('sendOtpBtn', false);
    }
  }

  // ── STEP 2: RESEND OTP ──
  async function resendOtp() {
    if (resendLeft <= 0) {
      showToast('warning', 'Limit Reached', 'No more resend attempts in this window.');
      return;
    }

    const resendBtn = document.getElementById('resendBtn');
    resendBtn.disabled = true;

    try {
      const fd = new FormData();
      fd.append('action', 'send_otp');
      fd.append('email', userEmail);
      const res  = await fetch(baseURL + 'process_forgot_password.php', { method: 'POST', body: fd });
      const data = await res.json();

      if (data.success) {
        resendLeft = data.resend_left ?? (resendLeft - 1);
        clearOtp();
        startTimer(data.expires_in ?? 300);
        document.getElementById('resendLeft').textContent = resendLeft;
        if (resendLeft <= 0) {
          document.getElementById('resendInfo').innerHTML = '<strong>No more resend attempts.</strong> Wait 5 minutes.';
        }
        showToast('success', 'OTP Resent!', data.message);
      } else {
        showToast('error', 'Resend Failed', data.message);
        if (resendLeft > 0) resendBtn.disabled = false;
      }
    } catch (_) {
      showToast('error', 'Network Error', 'Cannot reach server. Please try again.');
      if (resendLeft > 0) resendBtn.disabled = false;
    }
  }

  // ── STEP 2: VERIFY OTP ──
  async function verifyOtp() {
    const otp = getOtpValue();
    if (otp.length !== 6) {
      document.querySelectorAll('.otp-digit').forEach(d => d.classList.add('error'));
      showToast('warning', 'Incomplete OTP', 'Please enter all 6 digits.');
      return;
    }

    setLoading('verifyOtpBtn', true);
    try {
      const fd = new FormData();
      fd.append('action', 'verify_otp');
      fd.append('email', userEmail);
      fd.append('otp', otp);
      const res  = await fetch(baseURL + 'process_forgot_password.php', { method: 'POST', body: fd });
      const data = await res.json();

      if (data.success) {
        resetToken = data.reset_token;
        clearInterval(timerInterval);
        goToStep(3);
        showToast('success', 'OTP Verified!', data.message);
      } else {
        document.querySelectorAll('.otp-digit').forEach(d => d.classList.add('error'));
        showToast('error', 'Invalid OTP', data.message);
      }
    } catch (_) {
      showToast('error', 'Network Error', 'Cannot reach server. Please try again.');
    } finally {
      setLoading('verifyOtpBtn', false);
    }
  }

  // ── STEP 3: RESET PASSWORD ──
  async function resetPassword() {
    const pw  = document.getElementById('newPass').value;
    const cpw = document.getElementById('confirmPass').value;
    let valid = true;

    document.getElementById('newPassErr').classList.remove('show');
    document.getElementById('confirmPassErr').classList.remove('show');
    document.getElementById('newPass').classList.remove('error');
    document.getElementById('confirmPass').classList.remove('error');

    const strength = checkStrength(pw);
    if (strength < 2) {
      document.getElementById('newPass').classList.add('error');
      document.getElementById('newPassErr').textContent = 'Password is too weak. Use uppercase, number, and symbol.';
      document.getElementById('newPassErr').classList.add('show');
      valid = false;
    }
    if (pw !== cpw) {
      document.getElementById('confirmPass').classList.add('error');
      document.getElementById('confirmPassErr').classList.add('show');
      valid = false;
    }
    if (!valid) return;

    setLoading('resetPassBtn', true);
    try {
      const fd = new FormData();
      fd.append('action', 'reset_pass');
      fd.append('reset_token', resetToken);
      fd.append('new_password', pw);
      fd.append('confirm_password', cpw);
      const res  = await fetch(baseURL + 'process_forgot_password.php', { method: 'POST', body: fd });
      const data = await res.json();

      if (data.success) {
        goToStep(4);
        showToast('success', 'Success!', data.message);
      } else {
        showToast('error', 'Reset Failed', data.message);
      }
    } catch (_) {
      showToast('error', 'Network Error', 'Cannot reach server. Please try again.');
    } finally {
      setLoading('resetPassBtn', false);
    }
  }

  // ── PASSWORD STRENGTH ──
  function checkStrength(pw) {
    let score = 0;
    if (pw.length >= 8)          score++;
    if (/[A-Z]/.test(pw))        score++;
    if (/[0-9]/.test(pw))        score++;
    if (/[\W_]/.test(pw))        score++;
    return score;
  }

  function updateStrength() {
    const pw    = document.getElementById('newPass').value;
    const score = checkStrength(pw);
    const colors = ['#ef4444', '#f59e0b', '#3b82f6', '#10b981'];
    const labels = ['Too weak', 'Fair', 'Good', 'Strong'];
    for (let i = 1; i <= 4; i++) {
      document.getElementById('s' + i).style.background = i <= score ? colors[score - 1] : 'var(--border)';
    }
    document.getElementById('strengthLabel').textContent = pw ? labels[score - 1] || '' : '';
    document.getElementById('strengthLabel').style.color  = pw ? colors[score - 1] : 'var(--muted)';
  }

  // ── PASSWORD TOGGLE ──
  function togglePw(inputId, iconId) {
    const input  = document.getElementById(inputId);
    const isText = input.type === 'text';
    input.type   = isText ? 'password' : 'text';
    document.getElementById(iconId).innerHTML = isText
      ? '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>'
      : '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
  }

  // ── EMAIL FIELD CLEAR ERROR ON INPUT ──
  document.getElementById('emailInput').addEventListener('input', function () {
    this.classList.remove('error');
    document.getElementById('emailErr').classList.remove('show');
  });
</script>
</body>
</html>