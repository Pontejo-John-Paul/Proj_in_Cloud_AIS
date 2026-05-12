<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TrikScan — QR Attendance System for Tricycle Drivers</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg: #0a0f1c;
      --surface: #111827;
      --surface2: #1a2235;
      --accent: #f59e0b;
      --accent2: #10b981;
      --accent3: #3b82f6;
      --text: #f1f5f9;
      --muted: #64748b;
      --border: rgba(255,255,255,0.07);
      --glow: rgba(245,158,11,0.15);
    }

    html { scroll-behavior: smooth; }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--bg);
      color: var(--text);
      overflow-x: hidden;
      cursor: default;
    }

    /* ── NOISE OVERLAY ── */
    body::before {
      content: '';
      position: fixed;
      inset: 0;
      background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.04'/%3E%3C/svg%3E");
      pointer-events: none;
      z-index: 9999;
      opacity: 0.4;
    }

    /* ── NAVBAR ── */
    nav {
      position: fixed;
      top: 0; left: 0; right: 0;
      z-index: 100;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 18px 5%;
      background: rgba(10,15,28,0.85);
      backdrop-filter: blur(16px);
      border-bottom: 1px solid var(--border);
    }

    .logo {
      display: flex; align-items: center; gap: 10px;
    }
    .logo-icon {
      width: 36px; height: 36px;
      background: var(--accent);
      border-radius: 8px;
      display: grid;
      place-items: center;
    }
    .logo-icon svg { width: 20px; height: 20px; }
    .logo-text {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 1.6rem;
      letter-spacing: 0.05em;
      color: var(--text);
    }
    .logo-text span { color: var(--accent); }

    .nav-links {
      display: flex; gap: 32px; list-style: none;
    }
    .nav-links a {
      color: var(--muted);
      text-decoration: none;
      font-size: 0.875rem;
      font-weight: 500;
      letter-spacing: 0.02em;
      transition: color 0.2s;
    }
    .nav-links a:hover { color: var(--text); }

    .nav-auth-btns { display: flex; align-items: center; gap: 10px; }

    .nav-login {
      color: var(--muted);
      font-size: 0.875rem;
      font-weight: 500;
      text-decoration: none;
      padding: 9px 16px;
      border-radius: 6px;
      border: 1px solid var(--border);
      transition: color 0.2s, border-color 0.2s;
    }
    .nav-login:hover { color: var(--text); border-color: rgba(255,255,255,0.2); }

    .nav-cta {
      background: var(--accent);
      color: #0a0f1c;
      padding: 9px 22px;
      border-radius: 6px;
      font-weight: 600;
      font-size: 0.875rem;
      text-decoration: none;
      border: none;
      cursor: pointer;
      font-family: 'DM Sans', sans-serif;
      transition: opacity 0.2s, transform 0.2s;
    }
    .nav-cta:hover { opacity: 0.88; transform: translateY(-1px); }

    /* ── SIGNUP MODAL OVERLAY ── */
    .signup-overlay {
      display: none;
      position: fixed;
      inset: 0;
      z-index: 1000;
      background: rgba(5,8,18,0.82);
      backdrop-filter: blur(6px);
      -webkit-backdrop-filter: blur(6px);
      align-items: center;
      justify-content: center;
      padding: 20px;
      animation: overlayIn 0.25s ease;
    }
    .signup-overlay.open { display: flex; }

    .signup-modal {
      width: 100%;
      max-width: 500px;
      border-radius: 20px;
      overflow: hidden;
      animation: modalIn 0.3s cubic-bezier(0.34,1.56,0.64,1);
      box-shadow: 0 40px 100px rgba(0,0,0,0.7);
    }

    .signup-modal iframe {
      width: 100%;
      height: 690px;
      max-height: 90vh;
      display: block;
      border: none;
      border-radius: 20px;
    }


    /* ── LOGIN MODAL OVERLAY ── */
    .login-overlay {
      display: none;
      position: fixed;
      inset: 0;
      z-index: 1000;
      background: rgba(5,8,18,0.82);
      backdrop-filter: blur(6px);
      -webkit-backdrop-filter: blur(6px);
      align-items: center;
      justify-content: center;
      padding: 20px;
      animation: overlayIn 0.25s ease;
    }
    .login-overlay.open { display: flex; }
    .login-modal {
      width: 100%;
      max-width: 440px;
      border-radius: 20px;
      overflow: hidden;
      animation: modalIn 0.3s cubic-bezier(0.34,1.56,0.64,1);
      box-shadow: 0 40px 100px rgba(0,0,0,0.7);
    }
    .login-modal iframe {
      width: 100%;
      height: 460px;
      max-height: 90vh;
      display: block;
      border: none;
      border-radius: 20px;
    }
    @keyframes overlayIn {
      from { opacity: 0; }
      to   { opacity: 1; }
    }
    @keyframes modalIn {
      from { opacity: 0; transform: scale(0.92) translateY(20px); }
      to   { opacity: 1; transform: scale(1) translateY(0); }
    }

    /* ── HERO ── */
    .hero {
      min-height: 100vh;
      display: grid;
      grid-template-columns: 1fr 1fr;
      align-items: center;
      gap: 60px;
      padding: 140px 5% 80px;
      position: relative;
      overflow: hidden;
    }

    /* radial glow bg */
    .hero::after {
      content: '';
      position: absolute;
      top: -100px; right: -100px;
      width: 700px; height: 700px;
      background: radial-gradient(circle, rgba(245,158,11,0.12) 0%, transparent 65%);
      pointer-events: none;
    }

    .hero-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: rgba(245,158,11,0.1);
      border: 1px solid rgba(245,158,11,0.25);
      border-radius: 100px;
      padding: 6px 14px;
      font-size: 0.78rem;
      font-weight: 500;
      color: var(--accent);
      letter-spacing: 0.05em;
      text-transform: uppercase;
      margin-bottom: 24px;
      animation: fadeUp 0.6s ease both;
    }
    .hero-badge::before {
      content: '';
      width: 6px; height: 6px;
      background: var(--accent);
      border-radius: 50%;
      animation: pulse 1.5s infinite;
    }

    .hero h1 {
      font-family: 'Bebas Neue', sans-serif;
      font-size: clamp(3rem, 6vw, 5.5rem);
      line-height: 0.95;
      letter-spacing: 0.01em;
      animation: fadeUp 0.6s 0.1s ease both;
    }
    .hero h1 .accent { color: var(--accent); }
    .hero h1 .line2 {
      display: block;
      -webkit-text-stroke: 1px rgba(255,255,255,0.3);
      color: transparent;
    }

    .hero-sub {
      margin-top: 20px;
      font-size: 1.05rem;
      color: var(--muted);
      line-height: 1.7;
      max-width: 480px;
      font-weight: 300;
      animation: fadeUp 0.6s 0.2s ease both;
    }

    .hero-actions {
      margin-top: 36px;
      display: flex;
      gap: 16px;
      flex-wrap: wrap;
      animation: fadeUp 0.6s 0.3s ease both;
    }

    .btn-primary {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: var(--accent);
      color: #0a0f1c;
      padding: 13px 28px;
      border-radius: 8px;
      font-weight: 700;
      font-size: 0.95rem;
      text-decoration: none;
      transition: transform 0.2s, box-shadow 0.2s;
      box-shadow: 0 0 30px rgba(245,158,11,0.25);
    }
    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 0 40px rgba(245,158,11,0.4);
    }

    .btn-secondary {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: transparent;
      color: var(--text);
      padding: 13px 28px;
      border-radius: 8px;
      font-weight: 500;
      font-size: 0.95rem;
      text-decoration: none;
      border: 1px solid var(--border);
      transition: border-color 0.2s, background 0.2s;
    }
    .btn-secondary:hover { border-color: rgba(255,255,255,0.25); background: rgba(255,255,255,0.04); }

    /* ── QR PHONE MOCKUP ── */
    .hero-visual {
      display: flex;
      justify-content: center;
      align-items: center;
      animation: fadeUp 0.7s 0.2s ease both;
      position: relative;
    }

    .phone-frame {
      width: 260px;
      background: var(--surface2);
      border-radius: 32px;
      border: 1px solid var(--border);
      padding: 20px 18px;
      box-shadow: 0 40px 80px rgba(0,0,0,0.5), 0 0 0 1px rgba(255,255,255,0.05);
      position: relative;
      z-index: 2;
    }
    .phone-notch {
      width: 80px; height: 12px;
      background: var(--bg);
      border-radius: 100px;
      margin: 0 auto 18px;
    }

    .qr-scan-area {
      background: white;
      border-radius: 16px;
      padding: 18px;
      position: relative;
      overflow: hidden;
    }

    /* QR code grid */
    .qr-grid {
      display: grid;
      grid-template-columns: repeat(11, 1fr);
      gap: 2px;
    }
    .qr-cell {
      aspect-ratio: 1;
      border-radius: 1px;
    }

    .scan-line {
      position: absolute;
      left: 0; right: 0;
      height: 2px;
      background: linear-gradient(90deg, transparent, var(--accent), transparent);
      box-shadow: 0 0 10px var(--accent);
      animation: scanLine 2.2s ease-in-out infinite;
      top: 10%;
    }

    /* corner markers */
    .qr-corner {
      position: absolute;
      width: 22px; height: 22px;
      border-color: var(--accent);
      border-style: solid;
    }
    .qr-corner.tl { top: 10px; left: 10px; border-width: 3px 0 0 3px; }
    .qr-corner.tr { top: 10px; right: 10px; border-width: 3px 3px 0 0; }
    .qr-corner.bl { bottom: 10px; left: 10px; border-width: 0 0 3px 3px; }
    .qr-corner.br { bottom: 10px; right: 10px; border-width: 0 3px 3px 0; }

    .phone-info {
      margin-top: 16px;
      text-align: center;
    }
    .phone-info p {
      font-size: 0.75rem;
      color: var(--muted);
      margin-bottom: 8px;
    }
    .phone-status {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: rgba(16,185,129,0.1);
      border: 1px solid rgba(16,185,129,0.25);
      border-radius: 100px;
      padding: 5px 12px;
      font-size: 0.75rem;
      color: var(--accent2);
      font-family: 'JetBrains Mono', monospace;
    }
    .phone-status::before {
      content: '';
      width: 6px; height: 6px;
      background: var(--accent2);
      border-radius: 50%;
      animation: pulse 1.5s infinite;
    }

    /* floating cards */
    .float-card {
      position: absolute;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 12px 16px;
      font-size: 0.78rem;
      box-shadow: 0 20px 40px rgba(0,0,0,0.3);
    }
    .float-card.left {
      left: -40px; top: 30%;
      animation: floatLeft 4s ease-in-out infinite;
    }
    .float-card.right {
      right: -30px; bottom: 25%;
      animation: floatRight 4s 1s ease-in-out infinite;
    }
    .float-card .card-label { color: var(--muted); font-size: 0.7rem; margin-bottom: 4px; }
    .float-card .card-value { color: var(--text); font-weight: 600; font-family: 'JetBrains Mono', monospace; }
    .float-card .card-value.green { color: var(--accent2); }

    /* ── STATS BAR ── */
    .stats-bar {
      background: var(--surface);
      border-top: 1px solid var(--border);
      border-bottom: 1px solid var(--border);
      padding: 28px 5%;
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 20px;
      text-align: center;
    }
    .stat-item h3 {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 2.2rem;
      letter-spacing: 0.02em;
      color: var(--accent);
    }
    .stat-item p {
      font-size: 0.82rem;
      color: var(--muted);
      margin-top: 2px;
    }

    /* ── SECTION COMMON ── */
    section { padding: 90px 5%; }
    .section-label {
      font-size: 0.75rem;
      color: var(--accent);
      letter-spacing: 0.12em;
      text-transform: uppercase;
      font-weight: 600;
      margin-bottom: 12px;
      font-family: 'JetBrains Mono', monospace;
    }
    .section-title {
      font-family: 'Bebas Neue', sans-serif;
      font-size: clamp(2rem, 4vw, 3.2rem);
      line-height: 1;
      letter-spacing: 0.02em;
      margin-bottom: 14px;
    }
    .section-sub {
      color: var(--muted);
      font-size: 1rem;
      line-height: 1.7;
      max-width: 520px;
      font-weight: 300;
    }

    /* ── HOW IT WORKS ── */
    .how-it-works { background: var(--surface); }
    .how-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 2px;
      margin-top: 50px;
    }
    .how-step {
      background: var(--bg);
      padding: 36px 30px;
      position: relative;
      transition: background 0.3s;
    }
    .how-step:hover { background: var(--surface2); }
    .step-num {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 4rem;
      color: rgba(245,158,11,0.12);
      line-height: 1;
      margin-bottom: 16px;
    }
    .step-icon {
      width: 44px; height: 44px;
      background: rgba(245,158,11,0.1);
      border-radius: 10px;
      display: grid; place-items: center;
      margin-bottom: 16px;
    }
    .step-icon svg { width: 22px; height: 22px; stroke: var(--accent); fill: none; stroke-width: 1.8; }
    .how-step h3 { font-size: 1.05rem; font-weight: 600; margin-bottom: 8px; }
    .how-step p { font-size: 0.875rem; color: var(--muted); line-height: 1.65; }

    /* ── FEATURES ── */
    .features-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 60px;
      align-items: center;
      margin-top: 50px;
    }
    .feature-list { display: flex; flex-direction: column; gap: 20px; }
    .feature-item {
      display: flex;
      gap: 16px;
      padding: 20px;
      border: 1px solid var(--border);
      border-radius: 12px;
      background: var(--surface);
      transition: border-color 0.3s, transform 0.3s;
    }
    .feature-item:hover { border-color: rgba(245,158,11,0.3); transform: translateX(4px); }
    .feature-icon {
      flex-shrink: 0;
      width: 40px; height: 40px;
      border-radius: 10px;
      display: grid; place-items: center;
    }
    .feature-icon svg { width: 20px; height: 20px; fill: none; stroke-width: 1.8; }
    .fi-amber { background: rgba(245,158,11,0.1); }
    .fi-amber svg { stroke: var(--accent); }
    .fi-green { background: rgba(16,185,129,0.1); }
    .fi-green svg { stroke: var(--accent2); }
    .fi-blue { background: rgba(59,130,246,0.1); }
    .fi-blue svg { stroke: var(--accent3); }
    .feature-item h4 { font-size: 0.95rem; font-weight: 600; margin-bottom: 4px; }
    .feature-item p { font-size: 0.82rem; color: var(--muted); line-height: 1.6; }

    /* Dashboard preview */
    .dashboard-preview {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 40px 80px rgba(0,0,0,0.4);
    }
    .dash-topbar {
      background: var(--surface2);
      padding: 12px 16px;
      display: flex;
      align-items: center;
      gap: 8px;
      border-bottom: 1px solid var(--border);
    }
    .dash-dot { width: 10px; height: 10px; border-radius: 50%; }
    .dash-dot.r { background: #ef4444; }
    .dash-dot.y { background: var(--accent); }
    .dash-dot.g { background: var(--accent2); }
    .dash-title { font-size: 0.78rem; color: var(--muted); margin-left: auto; font-family: 'JetBrains Mono', monospace; }

    .dash-body { padding: 16px; }
    .dash-cards {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 10px;
      margin-bottom: 14px;
    }
    .dash-card {
      background: var(--bg);
      border-radius: 10px;
      padding: 14px;
      border: 1px solid var(--border);
    }
    .dash-card .dc-label { font-size: 0.65rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 6px; }
    .dash-card .dc-value { font-family: 'Bebas Neue', sans-serif; font-size: 1.6rem; letter-spacing: 0.02em; }
    .dash-card .dc-sub { font-size: 0.65rem; color: var(--accent2); margin-top: 2px; }
    .dc-amber { color: var(--accent); }
    .dc-green { color: var(--accent2); }
    .dc-blue  { color: var(--accent3); }

    .dash-chart { background: var(--bg); border-radius: 10px; padding: 14px; border: 1px solid var(--border); }
    .dash-chart .dc-label { font-size: 0.65rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 12px; }
    .bar-chart { display: flex; align-items: flex-end; gap: 6px; height: 70px; }
    .bar {
      flex: 1;
      border-radius: 4px 4px 0 0;
      transition: opacity 0.2s;
      position: relative;
    }
    .bar:hover { opacity: 0.8; }
    .bar-label { font-size: 0.55rem; color: var(--muted); text-align: center; margin-top: 4px; font-family: 'JetBrains Mono', monospace; }

    .dash-table { margin-top: 14px; }
    .dt-header {
      display: grid;
      grid-template-columns: 2fr 1fr 1fr 1fr;
      gap: 8px;
      padding: 8px 12px;
      font-size: 0.6rem;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.08em;
      border-bottom: 1px solid var(--border);
    }
    .dt-row {
      display: grid;
      grid-template-columns: 2fr 1fr 1fr 1fr;
      gap: 8px;
      padding: 8px 12px;
      font-size: 0.7rem;
      border-bottom: 1px solid var(--border);
      align-items: center;
    }
    .dt-row:last-child { border-bottom: none; }
    .dt-badge {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 4px;
      font-size: 0.6rem;
      font-weight: 600;
    }
    .dt-badge.present { background: rgba(16,185,129,0.15); color: var(--accent2); }
    .dt-badge.absent  { background: rgba(239,68,68,0.15);  color: #ef4444; }
    .dt-badge.late    { background: rgba(245,158,11,0.15);  color: var(--accent); }

    /* ── ADMIN DASHBOARD SECTION ── */
    .admin-section { background: var(--surface); }
    .admin-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 20px;
      margin-top: 50px;
    }
    .admin-card {
      background: var(--bg);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 28px;
      transition: border-color 0.3s, transform 0.3s;
    }
    .admin-card:hover { border-color: rgba(245,158,11,0.3); transform: translateY(-3px); }
    .admin-card-icon {
      width: 48px; height: 48px;
      border-radius: 12px;
      display: grid; place-items: center;
      margin-bottom: 18px;
    }
    .admin-card-icon svg { width: 24px; height: 24px; fill: none; stroke-width: 1.8; }
    .admin-card h3 { font-size: 1rem; font-weight: 600; margin-bottom: 8px; }
    .admin-card p { font-size: 0.83rem; color: var(--muted); line-height: 1.6; }

    /* ── REPORTING ── */
    .report-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 30px;
      margin-top: 50px;
    }
    .report-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 28px;
      display: flex;
      gap: 18px;
      align-items: flex-start;
      transition: border-color 0.3s;
    }
    .report-card:hover { border-color: rgba(59,130,246,0.3); }
    .report-num {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 2.5rem;
      color: rgba(59,130,246,0.3);
      line-height: 1;
      flex-shrink: 0;
      width: 40px;
    }
    .report-card h3 { font-size: 0.95rem; font-weight: 600; margin-bottom: 6px; }
    .report-card p { font-size: 0.82rem; color: var(--muted); line-height: 1.6; }

    /* ── CTA ── */
    .cta-section {
      text-align: center;
      padding: 100px 5%;
      position: relative;
      overflow: hidden;
    }
    .cta-section::before {
      content: '';
      position: absolute;
      inset: 0;
      background: radial-gradient(ellipse at center, rgba(245,158,11,0.08) 0%, transparent 65%);
    }
    .cta-section h2 {
      font-family: 'Bebas Neue', sans-serif;
      font-size: clamp(2.5rem, 5vw, 4rem);
      line-height: 1;
      margin-bottom: 16px;
    }
    .cta-section p { color: var(--muted); font-size: 1rem; max-width: 480px; margin: 0 auto 36px; line-height: 1.7; }
    .cta-actions { display: flex; justify-content: center; gap: 16px; flex-wrap: wrap; }

    /* ── FOOTER ── */
    footer {
      background: var(--surface);
      border-top: 1px solid var(--border);
      padding: 40px 5%;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 20px;
    }
    footer p { font-size: 0.82rem; color: var(--muted); }
    .footer-links { display: flex; gap: 24px; }
    .footer-links a { font-size: 0.82rem; color: var(--muted); text-decoration: none; transition: color 0.2s; }
    .footer-links a:hover { color: var(--text); }

    /* ── ANIMATIONS ── */
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(24px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50%       { opacity: 0.4; }
    }
    @keyframes scanLine {
      0%   { top: 10%; opacity: 0; }
      10%  { opacity: 1; }
      90%  { opacity: 1; }
      100% { top: 90%; opacity: 0; }
    }
    @keyframes floatLeft {
      0%, 100% { transform: translateY(0) rotate(-2deg); }
      50%       { transform: translateY(-10px) rotate(-2deg); }
    }
    @keyframes floatRight {
      0%, 100% { transform: translateY(0) rotate(2deg); }
      50%       { transform: translateY(-10px) rotate(2deg); }
    }

    /* ── RESPONSIVE ── */
    @media (max-width: 900px) {
      .hero { grid-template-columns: 1fr; text-align: center; }
      .hero-sub { max-width: 100%; }
      .hero-actions { justify-content: center; }
      .hero-visual { order: -1; }
      .float-card { display: none; }
      .stats-bar { grid-template-columns: repeat(2,1fr); }
      .how-grid { grid-template-columns: 1fr; }
      .features-grid { grid-template-columns: 1fr; }
      .admin-grid { grid-template-columns: 1fr; }
      .report-grid { grid-template-columns: 1fr; }
      .nav-links { display: none; }
    }
  </style>
</head>
<body>

<!-- NAVBAR -->
<nav>
  <div class="logo">
    <div class="logo-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="#0a0f1c" stroke-width="2.5" stroke-linecap="round">
        <rect x="3" y="3" width="7" height="7" rx="1"/>
        <rect x="14" y="3" width="7" height="7" rx="1"/>
        <rect x="3" y="14" width="7" height="7" rx="1"/>
        <rect x="15" y="15" width="2" height="2"/>
        <rect x="19" y="15" width="2" height="2"/>
        <rect x="15" y="19" width="2" height="2"/>
        <rect x="19" y="19" width="2" height="2"/>
      </svg>
    </div>
    <span class="logo-text">Trik<span>Scan</span></span>
  </div>
  <ul class="nav-links">
    <li><a href="#how">How It Works</a></li>
    <li><a href="#features">Features</a></li>
    <li><a href="#dashboard">Dashboard</a></li>
    <li><a href="#reports">Reports</a></li>
  </ul>
  <div class="nav-auth-btns">
    <button onclick="openLoginModal()" class="nav-login">Log In</button>
    <button onclick="openSignupModal()" class="nav-cta">Sign Up</button>
  </div>
</nav>

<!-- ── SIGNUP MODAL OVERLAY ── -->
<div id="signupOverlay" class="signup-overlay" onclick="handleOverlayClick(event)">
  <div class="signup-modal">
    <iframe id="signupFrame" src="signup.php" frameborder="0" scrolling="auto" title="Admin Sign Up"></iframe>
  </div>
</div>


<!-- ── LOGIN MODAL OVERLAY ── -->
<div id="loginOverlay" class="login-overlay" onclick="handleLoginOverlayClick(event)">
  <div class="login-modal">
    <iframe id="loginFrame" src="login.php" frameborder="0" scrolling="auto" title="Admin Login"></iframe>
  </div>
</div>

<!-- HERO -->
<section class="hero">
  <div class="hero-content">
    <div class="hero-badge">Smart Attendance Technology</div>
    <h1>
      QR-Based<br>
      <span class="accent">Attendance</span><br>
      <span class="line2">Monitoring</span>
    </h1>
    <p class="hero-sub">A digital attendance system for tricycle drivers — scan, track, and manage daily presence with a powerful admin dashboard and real-time reporting tools.</p>
    <div class="hero-actions">
      <a href="#how" class="btn-primary">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg>
        See How It Works
      </a>
      <a href="#dashboard" class="btn-secondary">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
        View Dashboard
      </a>
    </div>
  </div>

  <!-- PHONE MOCKUP -->
  <div class="hero-visual">
    <div class="float-card left" style="transform: rotate(-2deg);">
      <div class="card-label">Today's Attendance</div>
      <div class="card-value green">142 / 160</div>
    </div>

    <div class="phone-frame">
      <div class="phone-notch"></div>
      <div class="qr-scan-area">
        <!-- SVG QR pattern -->
        <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" style="width:100%;display:block;">
          <!-- finder patterns -->
          <rect x="10" y="10" width="60" height="60" rx="4" fill="#1a1a1a"/>
          <rect x="18" y="18" width="44" height="44" rx="2" fill="white"/>
          <rect x="26" y="26" width="28" height="28" rx="1" fill="#1a1a1a"/>
          <rect x="130" y="10" width="60" height="60" rx="4" fill="#1a1a1a"/>
          <rect x="138" y="18" width="44" height="44" rx="2" fill="white"/>
          <rect x="146" y="26" width="28" height="28" rx="1" fill="#1a1a1a"/>
          <rect x="10" y="130" width="60" height="60" rx="4" fill="#1a1a1a"/>
          <rect x="18" y="138" width="44" height="44" rx="2" fill="white"/>
          <rect x="26" y="146" width="28" height="28" rx="1" fill="#1a1a1a"/>
          <!-- random modules -->
          <rect x="84" y="10" width="10" height="10" fill="#1a1a1a"/>
          <rect x="100" y="10" width="10" height="10" fill="#1a1a1a"/>
          <rect x="116" y="10" width="10" height="10" fill="#1a1a1a"/>
          <rect x="84" y="26" width="10" height="10" fill="#1a1a1a"/>
          <rect x="116" y="26" width="10" height="10" fill="#1a1a1a"/>
          <rect x="84" y="42" width="20" height="10" fill="#1a1a1a"/>
          <rect x="110" y="42" width="16" height="10" fill="#1a1a1a"/>
          <rect x="84" y="58" width="10" height="10" fill="#1a1a1a"/>
          <rect x="100" y="58" width="20" height="10" fill="#1a1a1a"/>
          <!-- middle rows -->
          <rect x="10" y="84" width="10" height="10" fill="#1a1a1a"/>
          <rect x="30" y="84" width="20" height="10" fill="#1a1a1a"/>
          <rect x="60" y="84" width="10" height="10" fill="#1a1a1a"/>
          <rect x="84" y="84" width="10" height="10" fill="#1a1a1a"/>
          <rect x="100" y="84" width="10" height="10" fill="#1a1a1a"/>
          <rect x="130" y="84" width="20" height="10" fill="#1a1a1a"/>
          <rect x="160" y="84" width="10" height="10" fill="#1a1a1a"/>
          <rect x="180" y="84" width="10" height="10" fill="#1a1a1a"/>
          <rect x="10" y="100" width="20" height="10" fill="#1a1a1a"/>
          <rect x="50" y="100" width="10" height="10" fill="#1a1a1a"/>
          <rect x="84" y="100" width="20" height="10" fill="#1a1a1a"/>
          <rect x="116" y="100" width="10" height="10" fill="#1a1a1a"/>
          <rect x="140" y="100" width="10" height="10" fill="#1a1a1a"/>
          <rect x="170" y="100" width="20" height="10" fill="#1a1a1a"/>
          <rect x="10" y="116" width="10" height="10" fill="#1a1a1a"/>
          <rect x="40" y="116" width="20" height="10" fill="#1a1a1a"/>
          <rect x="84" y="116" width="10" height="10" fill="#1a1a1a"/>
          <rect x="100" y="116" width="20" height="10" fill="#1a1a1a"/>
          <rect x="130" y="116" width="10" height="10" fill="#1a1a1a"/>
          <rect x="150" y="116" width="20" height="10" fill="#1a1a1a"/>
          <rect x="180" y="116" width="10" height="10" fill="#1a1a1a"/>
          <!-- bottom right data -->
          <rect x="84" y="130" width="20" height="10" fill="#1a1a1a"/>
          <rect x="116" y="130" width="14" height="10" fill="#1a1a1a"/>
          <rect x="84" y="146" width="10" height="10" fill="#1a1a1a"/>
          <rect x="100" y="146" width="16" height="10" fill="#1a1a1a"/>
          <rect x="130" y="146" width="10" height="10" fill="#1a1a1a"/>
          <rect x="150" y="146" width="20" height="10" fill="#1a1a1a"/>
          <rect x="84" y="162" width="22" height="10" fill="#1a1a1a"/>
          <rect x="116" y="162" width="10" height="10" fill="#1a1a1a"/>
          <rect x="140" y="162" width="10" height="10" fill="#1a1a1a"/>
          <rect x="160" y="162" width="20" height="10" fill="#1a1a1a"/>
          <rect x="84" y="178" width="10" height="10" fill="#1a1a1a"/>
          <rect x="100" y="178" width="20" height="10" fill="#1a1a1a"/>
          <rect x="130" y="178" width="20" height="10" fill="#1a1a1a"/>
          <rect x="160" y="178" width="10" height="10" fill="#1a1a1a"/>
          <rect x="180" y="178" width="10" height="10" fill="#1a1a1a"/>
          <!-- scan line -->
          <rect x="0" y="0" width="200" height="200" fill="none"/>
        </svg>
        <!-- animated scan line overlay -->
        <div class="scan-line"></div>
        <div class="qr-corner tl"></div>
        <div class="qr-corner tr"></div>
        <div class="qr-corner bl"></div>
        <div class="qr-corner br"></div>
      </div>
      <div class="phone-info">
        <p>Place QR code inside the frame</p>
        <div class="phone-status">● SCANNING ACTIVE</div>
      </div>
    </div>

    <div class="float-card right" style="transform: rotate(2deg);">
      <div class="card-label">Last Scan</div>
      <div class="card-value">Juan D. Cruz</div>
      <div class="card-label" style="margin-top:4px;">08:42 AM — Route 7</div>
    </div>
  </div>
</section>

<!-- STATS BAR -->
<div class="stats-bar">
  <div class="stat-item"><h3>160+</h3><p>Registered Drivers</p></div>
  <div class="stat-item"><h3>98.5%</h3><p>Scan Accuracy Rate</p></div>
  <div class="stat-item"><h3>&lt;1s</h3><p>QR Processing Time</p></div>
  <div class="stat-item"><h3>24/7</h3><p>Real-time Monitoring</p></div>
</div>

<!-- HOW IT WORKS -->
<section class="how-it-works" id="how">
  <div class="section-label">// Process</div>
  <div class="section-title">HOW IT WORKS</div>
  <p class="section-sub">A simple three-step flow ensures every driver's attendance is captured accurately and instantly.</p>
  <div class="how-grid">
    <div class="how-step">
      <div class="step-num">01</div>
      <div class="step-icon">
        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="15" y="15" width="2" height="2"/><rect x="19" y="15" width="2" height="2"/><rect x="15" y="19" width="2" height="2"/><rect x="19" y="19" width="2" height="2"/></svg>
      </div>
      <h3>Driver QR Assignment</h3>
      <p>Each tricycle driver is registered in the system and issued a unique ID for QRcode.</p>
    </div>
    <div class="how-step">
      <div class="step-num">02</div>
      <div class="step-icon">
        <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
      </div>
      <h3>Scan at Station</h3>
      <p>Drivers scan their QR code at the terminal for attendance. The system instantly logs the timestamp and attendance status.</p>
    </div>
    <div class="how-step">
      <div class="step-num">03</div>
      <div class="step-icon">
        <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      </div>
      <h3>Admin Monitoring</h3>
      <p>Administrators access the live dashboard to monitor attendance, add drivers, and generate reports.</p>
    </div>
  </div>
</section>

<!-- FEATURES -->
<section id="features">
  <div class="section-label">// Capabilities</div>
  <div class="section-title">KEY FEATURES</div>
  <div class="features-grid">
    <div class="feature-list">
      <div class="feature-item">
        <div class="feature-icon fi-amber">
          <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        </div>
        <div>
          <h4>Unique QR per Driver</h4>
          <p>Each driver gets an encrypted, unique QR code preventing spoofing and duplicate scans throughout the work day.</p>
        </div>
      </div>
      <div class="feature-item">
        <div class="feature-icon fi-green">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div>
          <h4>Real-Time Timestamps</h4>
          <p>Every scan is recorded with an exact date and time stamp, enabling accurate time-in and time-out tracking across all routes.</p>
        </div>
      </div>
      <div class="feature-item">
        <div class="feature-icon fi-amber">
          <svg viewBox="0 0 24 24"><path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/></svg>
        </div>
        <div>
          <h4>Absent & Late Alerts</h4>
          <p>Automatic flagging of absent or tardy drivers so administrators can take immediate action or follow up accordingly.</p>
        </div>
      </div>
    </div>

    <!-- DASHBOARD PREVIEW -->
    <div class="dashboard-preview">
      <div class="dash-topbar">
        <div class="dash-dot r"></div>
        <div class="dash-dot y"></div>
        <div class="dash-dot g"></div>
        <div class="dash-title">TrikScan Admin — Dashboard</div>
      </div>
      <div class="dash-body">
        <div class="dash-cards">
          <div class="dash-card">
            <div class="dc-label">Present</div>
            <div class="dc-value dc-green">142</div>
            <div class="dc-sub">▲ +3 from yesterday</div>
          </div>
          <div class="dash-card">
            <div class="dc-label">Absent</div>
            <div class="dc-value" style="color:#ef4444;">18</div>
            <div class="dc-sub" style="color:#ef4444;">▼ -1 from yesterday</div>
          </div>
          <div class="dash-card">
            <div class="dc-label">Late</div>
            <div class="dc-value dc-amber">7</div>
            <div class="dc-sub">After 6:00 AM cutoff</div>
          </div>
        </div>

        <div class="dash-chart">
          <div class="dc-label">Weekly Attendance Rate</div>
          <div class="bar-chart">
            <div>
              <div class="bar" style="height:55px; background: linear-gradient(to top, #10b981, rgba(16,185,129,0.4));"></div>
              <div class="bar-label">MON</div>
            </div>
            <div>
              <div class="bar" style="height:62px; background: linear-gradient(to top, #10b981, rgba(16,185,129,0.4));"></div>
              <div class="bar-label">TUE</div>
            </div>
            <div>
              <div class="bar" style="height:48px; background: linear-gradient(to top, #f59e0b, rgba(245,158,11,0.4));"></div>
              <div class="bar-label">WED</div>
            </div>
            <div>
              <div class="bar" style="height:65px; background: linear-gradient(to top, #10b981, rgba(16,185,129,0.4));"></div>
              <div class="bar-label">THU</div>
            </div>
            <div>
              <div class="bar" style="height:58px; background: linear-gradient(to top, #10b981, rgba(16,185,129,0.4));"></div>
              <div class="bar-label">FRI</div>
            </div>
            <div>
              <div class="bar" style="height:38px; background: linear-gradient(to top, #3b82f6, rgba(59,130,246,0.4));"></div>
              <div class="bar-label">SAT</div>
            </div>
          </div>
        </div>

        <div class="dash-table">
          <div class="dt-header">
            <span>Driver Name</span><span>Route</span><span>Time In</span><span>Status</span>
          </div>
          <div class="dt-row">
            <span>Juan D. Cruz</span><span>Route 7</span><span style="font-family:'JetBrains Mono',monospace;font-size:0.65rem;">05:48</span>
            <span><div class="dt-badge present">Present</div></span>
          </div>
          <div class="dt-row">
            <span>Maria Santos</span><span>Route 3</span><span style="font-family:'JetBrains Mono',monospace;font-size:0.65rem;">06:12</span>
            <span><div class="dt-badge late">Late</div></span>
          </div>
          <div class="dt-row">
            <span>Pedro Reyes</span><span>Route 11</span><span style="font-family:'JetBrains Mono',monospace;font-size:0.65rem;">—</span>
            <span><div class="dt-badge absent">Absent</div></span>
          </div>
          <div class="dt-row">
            <span>Ana Ramos</span><span>Route 2</span><span style="font-family:'JetBrains Mono',monospace;font-size:0.65rem;">05:55</span>
            <span><div class="dt-badge present">Present</div></span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ADMIN DASHBOARD -->
<section class="admin-section" id="dashboard">
  <div class="section-label">// Admin Tools</div>
  <div class="section-title">ADMIN DASHBOARD</div>
  <p class="section-sub">A comprehensive control panel for administrators to manage drivers, routes, and attendance data effortlessly.</p>
  <div class="admin-grid">
    <div class="admin-card">
      <div class="admin-card-icon" style="background:rgba(245,158,11,0.1);">
        <svg viewBox="0 0 24 24" stroke="var(--accent)"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      </div>
      <h3>Driver Management</h3>
      <p>Register new drivers, update profiles, and assign QR codes.</p>
    </div>
    <div class="admin-card">
      <div class="admin-card-icon" style="background:rgba(16,185,129,0.1);">
        <svg viewBox="0 0 24 24" stroke="var(--accent2)"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      </div>
      <h3>Live Attendance Feed</h3>
      <p>Monitor incoming scans in real-time with an auto-updating feed showing driver name, timestamp, and status.</p>
    </div>
    <div class="admin-card">
      <div class="admin-card-icon" style="background:rgba(59,130,246,0.1);">
        <svg viewBox="0 0 24 24" stroke="var(--accent3)"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      </div>
      <h3>Daily Scheduling</h3>
      <p>Set shift schedules, define attendance windows, configure late thresholds, and manage holiday or rest day calendars.</p>
    </div>
    <div class="admin-card">
      <div class="admin-card-icon" style="background:rgba(16,185,129,0.1);">
        <svg viewBox="0 0 24 24" stroke="var(--accent2)"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      </div>
      <h3>Driver Search & Filter</h3>
      <p>Quickly locate any driver by name or attendance status with advanced filtering and sorting capabilities.</p>
    </div>
    <div class="admin-card">
      <div class="admin-card-icon" style="background:rgba(59,130,246,0.1);">
        <svg viewBox="0 0 24 24" stroke="var(--accent3)"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      </div>
      <h3>Role-Based Access</h3>
      <p>Assign admin, supervisor, or viewer roles with different permission levels to keep data secure and access controlled.</p>
    </div>
  </div>
</section>

<!-- REPORTING -->
<section id="reports">
  <div class="section-label">// Data & Insights</div>
  <div class="section-title">REPORTING MODULE</div>
  <p class="section-sub">Turn attendance data into actionable insights with comprehensive reports designed for decision-makers.</p>
  <div class="report-grid">
    <div class="report-card">
      <div class="report-num">01</div>
      <div>
        <h3>Daily Attendance Summary</h3>
        <p>A complete day-end report listing all present, absent, and late drivers with exact scan times and route assignments.</p>
      </div>
    </div>
    <div class="report-card">
      <div class="report-num">03</div>
      <div>
        <h3>Individual Driver Records</h3>
        <p>Per-driver historical attendance logs with total present days, absence count, and punctuality score for the period.</p>
      </div>
    </div>
    <div class="report-card">
      <div class="report-num">05</div>
      <div>
        <h3>Export to PDF / Excel</h3>
        <p>Download ready-to-submit reports in PDF or Excel format for compliance, audits, or organizational records.</p>
      </div>
    </div>
    <div class="report-card">
      <div class="report-num">06</div>
      <div>
        <h3>Audit Trail Logs</h3>
        <p>Complete system activity logs tracking every scan, edit, and admin action for full transparency and accountability.</p>
      </div>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="cta-section" id="cta">
  <div class="section-label">// Get Started</div>
  <h2>READY TO MODERNIZE<br>YOUR TERMINAL?</h2>
  <p>Replace manual logbooks with a smart, reliable QR-based attendance system built for tricycle associations and transport terminals.</p>
</section>

<!-- FOOTER -->
<footer>
  <div class="logo">
    <div class="logo-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="#0a0f1c" stroke-width="2.5" stroke-linecap="round">
        <rect x="3" y="3" width="7" height="7" rx="1"/>
        <rect x="14" y="3" width="7" height="7" rx="1"/>
        <rect x="3" y="14" width="7" height="7" rx="1"/>
        <rect x="15" y="15" width="2" height="2"/>
        <rect x="19" y="15" width="2" height="2"/>
        <rect x="15" y="19" width="2" height="2"/>
        <rect x="19" y="19" width="2" height="2"/>
      </svg>
    </div>
    <span class="logo-text">Trik<span>Scan</span></span>
  </div>
  <p>© 2025 TrikScan. QR-Based Attendance Monitoring System for Tricycle Drivers.</p>
  <div class="footer-links">
    <a href="#">Privacy Policy</a>
    <a href="#">Terms of Use</a>
    <a href="#">Contact</a>
  </div>
</footer>


<script>
  // Open the signup modal
  function openSignupModal() {
    const overlay = document.getElementById('signupOverlay');
    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  // Close the signup modal — called by iframe's close button via parent.closeSignupModal()
  function closeSignupModal() {
    const overlay = document.getElementById('signupOverlay');
    overlay.classList.remove('open');
    document.body.style.overflow = '';
    // Reload iframe to reset form state
    document.getElementById('signupFrame').src = document.getElementById('signupFrame').src;
  }

  // Close when clicking the dark backdrop (not the modal itself)
  function handleOverlayClick(e) {
    if (e.target === document.getElementById('signupOverlay')) {
      closeSignupModal();
    }
  }


  // ── LOGIN MODAL
  function openLoginModal() {
    document.getElementById('loginOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
  }
  function closeLoginModal() {
    document.getElementById('loginOverlay').classList.remove('open');
    document.body.style.overflow = '';
    const f = document.getElementById('loginFrame');
    f.src = f.src;
  }
  function handleLoginOverlayClick(e) {
    if (e.target === document.getElementById('loginOverlay')) closeLoginModal();
  }
  function openSignupFromLogin() {
    closeLoginModal();
    setTimeout(openSignupModal, 150);
  }

  // ── ESCAPE closes both
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { closeSignupModal(); closeLoginModal(); }
  });
</script>

</body>
</html>