<?php
session_start();
date_default_timezone_set('Asia/Manila');
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: homepage.php');
    exit;
}

$adminName     = htmlspecialchars($_SESSION['admin_name']     ?? 'Administrator');
$adminUsername = htmlspecialchars($_SESSION['admin_username'] ?? 'admin');
$adminRole     = $_SESSION['admin_role'] ?? 'admin';
$adminEmail    = htmlspecialchars($_SESSION['admin_email']    ?? '');

$roleLabel = match($adminRole) {
    'super_admin' => 'Super Admin',
    'supervisor'  => 'Supervisor',
    default       => 'Admin',
};
$roleColor = match($adminRole) {
    'super_admin' => '#f59e0b',
    'supervisor'  => '#10b981',
    default       => '#3b82f6',
};

/* ── DB ── */
$db = new mysqli('localhost', 'root', '', 'trikscan_db');
if ($db->connect_error) die('Database connection failed: ' . $db->connect_error);
$db->set_charset('utf8mb4');

/* ── Tables ── */
$db->query("CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    driver_id INT NOT NULL,
    date DATE NOT NULL,
    time_in TIME DEFAULT NULL,
    time_out TIME DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'inactive',
    UNIQUE KEY unique_driver_date (driver_id, date),
    FOREIGN KEY (driver_id) REFERENCES drivers_registered_tb(id) ON DELETE CASCADE
)");

$db->query("CREATE TABLE IF NOT EXISTS attendance_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(100) NOT NULL,
    time_start TIME NOT NULL,
    time_end TIME NOT NULL,
    late_after TIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

/* ── Auto-seed today ── */
$today = date('Y-m-d');
$dr = $db->query("SELECT id FROM drivers_registered_tb");
while ($drv = $dr->fetch_assoc()) {
    $did = (int)$drv['id'];
    $db->query("INSERT IGNORE INTO attendance (driver_id, date, status) VALUES ($did, '$today', 'inactive')");
}

/* ════ AJAX ════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    /* Time In */
    if ($_POST['action'] === 'time_in') {
        $scanned = $db->real_escape_string(trim($_POST['qr_driver_id'] ?? ''));
        $att_id  = (int)($_POST['attendance_id'] ?? 0);
        $res = $db->query("SELECT id FROM drivers_registered_tb WHERE driver_id='$scanned'");
        if (!$res || $res->num_rows === 0) { echo json_encode(['success'=>false,'message'=>'QR not recognized.']); exit; }
        $drv_int = (int)$res->fetch_assoc()['id'];
        $ar = $db->query("SELECT * FROM attendance WHERE id=$att_id AND driver_id=$drv_int AND date='$today'");
        if (!$ar || $ar->num_rows === 0) { echo json_encode(['success'=>false,'message'=>'QR does not match this driver.']); exit; }
        $att = $ar->fetch_assoc();
        if ($att['time_in'] !== null) { echo json_encode(['success'=>false,'message'=>'Already timed in today.']); exit; }
        $now = date('H:i:s');
        $is_late = false;
        $sr = $db->query("SELECT * FROM attendance_schedule ORDER BY id DESC LIMIT 1");
        if ($sr && $sr->num_rows > 0) { $s = $sr->fetch_assoc(); $is_late = $now > $s['late_after']; }
        $db->query("UPDATE attendance SET time_in='$now', status='active' WHERE id=$att_id");
        echo json_encode(['success'=>true,'time_in'=>$now,'is_late'=>$is_late,'message'=>'Time In recorded.'.($is_late?' ⚠️ Late arrival.':'')]);
        exit;
    }

    /* Time Out */
    if ($_POST['action'] === 'time_out') {
        $scanned = $db->real_escape_string(trim($_POST['qr_driver_id'] ?? ''));
        $att_id  = (int)($_POST['attendance_id'] ?? 0);
        $res = $db->query("SELECT id FROM drivers_registered_tb WHERE driver_id='$scanned'");
        if (!$res || $res->num_rows === 0) { echo json_encode(['success'=>false,'message'=>'QR not recognized.']); exit; }
        $drv_int = (int)$res->fetch_assoc()['id'];
        $ar = $db->query("SELECT * FROM attendance WHERE id=$att_id AND driver_id=$drv_int AND date='$today'");
        if (!$ar || $ar->num_rows === 0) { echo json_encode(['success'=>false,'message'=>'QR does not match this driver.']); exit; }
        $att = $ar->fetch_assoc();
        if ($att['time_in'] === null)  { echo json_encode(['success'=>false,'message'=>'Has not timed in yet.']); exit; }
        if ($att['time_out'] !== null) { echo json_encode(['success'=>false,'message'=>'Already timed out today.']); exit; }
        $now = date('H:i:s');
        $db->query("UPDATE attendance SET time_out='$now', status='inactive' WHERE id=$att_id");
        echo json_encode(['success'=>true,'time_out'=>$now,'message'=>'Time Out recorded.']);
        exit;
    }

    /* Save Schedule */
    if ($_POST['action'] === 'save_schedule') {
        $label = $db->real_escape_string(trim($_POST['label'] ?? ''));
        $ts    = $db->real_escape_string(trim($_POST['time_start'] ?? ''));
        $te    = $db->real_escape_string(trim($_POST['time_end'] ?? ''));
        $la    = $db->real_escape_string(trim($_POST['late_after'] ?? ''));
        if (!$label||!$ts||!$te||!$la) { echo json_encode(['success'=>false,'message'=>'All fields required.']); exit; }
        $db->query("INSERT INTO attendance_schedule (label,time_start,time_end,late_after) VALUES ('$label','$ts','$te','$la')");
        $nid = $db->insert_id;
        $row = $db->query("SELECT * FROM attendance_schedule WHERE id=$nid")->fetch_assoc();
        echo json_encode(['success'=>true,'message'=>'Schedule saved.','schedule'=>$row]);
        exit;
    }

    /* Update Schedule */
    if ($_POST['action'] === 'update_schedule') {
        $id    = (int)($_POST['id'] ?? 0);
        $label = $db->real_escape_string(trim($_POST['label'] ?? ''));
        $ts    = $db->real_escape_string(trim($_POST['time_start'] ?? ''));
        $te    = $db->real_escape_string(trim($_POST['time_end'] ?? ''));
        $la    = $db->real_escape_string(trim($_POST['late_after'] ?? ''));
        if (!$id||!$label||!$ts||!$te||!$la) { echo json_encode(['success'=>false,'message'=>'All fields required.']); exit; }
        $db->query("UPDATE attendance_schedule SET label='$label',time_start='$ts',time_end='$te',late_after='$la' WHERE id=$id");
        echo json_encode(['success'=>true,'message'=>'Schedule updated.']);
        exit;
    }

    /* Delete Schedule */
    if ($_POST['action'] === 'delete_schedule') {
        $id = (int)($_POST['id'] ?? 0);
        $db->query("DELETE FROM attendance_schedule WHERE id=$id");
        echo json_encode(['success'=>true,'message'=>'Schedule deleted.']);
        exit;
    }

    echo json_encode(['success'=>false,'message'=>'Unknown action.']); exit;
}

/* ── Fetch data ── */
$rec_res = $db->query("
    SELECT a.id AS att_id, a.driver_id AS driver_int_id, a.date,
           a.time_in, a.time_out, a.status,
           d.driver_id AS driver_str_id, d.firstname, d.lastname, d.picture
    FROM attendance a
    JOIN drivers_registered_tb d ON d.id = a.driver_id
    WHERE a.date = '$today'
    ORDER BY d.firstname ASC, d.lastname ASC
");
$records = [];
while ($row = $rec_res->fetch_assoc()) $records[] = $row;

$sched_res = $db->query("SELECT * FROM attendance_schedule ORDER BY time_start ASC");
$schedules = [];
while ($row = $sched_res->fetch_assoc()) $schedules[] = $row;

$active_sched = null;
$asr = $db->query("SELECT * FROM attendance_schedule ORDER BY id DESC LIMIT 1");
if ($asr && $asr->num_rows > 0) $active_sched = $asr->fetch_assoc();

$total_active   = count(array_filter($records, fn($r) => $r['status'] === 'active'));
$total_inactive = count(array_filter($records, fn($r) => $r['status'] === 'inactive'));

function isLate($ti, $la) { return $ti && $la && $ti > $la; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Attendance — TrikScan Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet"/>
  <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{
      --bg:#0a0f1c;--surface:#111827;--surface2:#1a2235;--surface3:#202c42;
      --accent:#f59e0b;--accent2:#10b981;--accent3:#3b82f6;--danger:#ef4444;
      --text:#f1f5f9;--muted:#64748b;--border:rgba(255,255,255,0.07);
      --sidebar-w:260px;
    }
    html{scroll-behavior:smooth}
    body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;overflow-x:hidden}
    body::before{content:'';position:fixed;inset:0;background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E");pointer-events:none;z-index:9999;opacity:.4}

    /* SIDEBAR */
    .sidebar{width:var(--sidebar-w);background:var(--surface);border-right:1px solid var(--border);display:flex;flex-direction:column;position:fixed;inset:0 auto 0 0;z-index:100}
    .sidebar-logo{padding:24px 20px 20px;display:flex;align-items:center;gap:10px;border-bottom:1px solid var(--border)}
    .sidebar-logo-icon{width:34px;height:34px;background:var(--accent);border-radius:8px;display:grid;place-items:center;flex-shrink:0}
    .sidebar-logo-icon svg{width:18px;height:18px}
    .sidebar-logo-text{font-family:'Bebas Neue',sans-serif;font-size:1.5rem;letter-spacing:.05em}
    .sidebar-logo-text span{color:var(--accent)}
    .sidebar-user{margin:16px;padding:14px;background:var(--surface2);border-radius:12px;border:1px solid var(--border)}
    .sidebar-user-name{font-weight:600;font-size:.9rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .sidebar-user-role{display:inline-block;margin-top:5px;font-size:.68rem;font-weight:700;padding:2px 10px;border-radius:20px;font-family:'JetBrains Mono',monospace;letter-spacing:.05em;background:rgba(255,255,255,.05);border:1px solid}
    .sidebar-user-email{font-size:.75rem;color:var(--muted);margin-top:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .sidebar-nav{flex:1;padding:8px 12px;overflow-y:auto}
    .nav-section-label{font-size:.68rem;font-weight:700;color:var(--muted);letter-spacing:.1em;text-transform:uppercase;padding:12px 8px 6px;font-family:'JetBrains Mono',monospace}
    .nav-item{display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:8px;color:var(--muted);text-decoration:none;font-size:.875rem;font-weight:500;transition:background .2s,color .2s;cursor:pointer;border:none;background:none;width:100%;text-align:left}
    .nav-item svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:1.8;flex-shrink:0}
    .nav-item:hover{background:var(--surface2);color:var(--text)}
    .nav-item.active{background:rgba(245,158,11,.1);color:var(--accent)}
    .nav-item.active svg{stroke:var(--accent)}
    .sidebar-footer{padding:14px 12px;border-top:1px solid var(--border)}
    .logout-btn{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:8px;color:var(--muted);font-size:.875rem;font-weight:500;cursor:pointer;border:none;background:none;width:100%;transition:background .2s,color .2s}
    .logout-btn svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:1.8}
    .logout-btn:hover{background:rgba(239,68,68,.08);color:#fca5a5}

    /* MAIN */
    .main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh}
    .topbar{background:rgba(10,15,28,.8);backdrop-filter:blur(16px);border-bottom:1px solid var(--border);padding:16px 28px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50}
    .topbar-left h1{font-family:'Bebas Neue',sans-serif;font-size:1.5rem;letter-spacing:.04em;line-height:1}
    .topbar-left p{font-size:.78rem;color:var(--muted);margin-top:2px;font-family:'JetBrains Mono',monospace}
    .topbar-right{display:flex;align-items:center;gap:12px}
    .topbar-time{font-family:'JetBrains Mono',monospace;font-size:.8rem;color:var(--muted)}
    .topbar-avatar{width:36px;height:36px;border-radius:50%;background:var(--accent);display:grid;place-items:center;font-family:'Bebas Neue',sans-serif;font-size:1rem;color:#0a0f1c;font-weight:900}

    .page-content{padding:28px;flex:1;animation:fadeUp .4s ease}
    @keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}

    /* DATE BANNER */
    .date-banner{background:linear-gradient(135deg,var(--surface2),var(--surface));border:1px solid var(--border);border-radius:14px;padding:18px 24px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between}
    .date-banner h2{font-family:'Bebas Neue',sans-serif;font-size:1.5rem;letter-spacing:.03em}
    .date-banner h2 span{color:var(--accent)}
    .date-banner p{color:var(--muted);font-size:.8rem;margin-top:4px;font-family:'JetBrains Mono',monospace}
    .date-banner-icon{opacity:.1}
    .date-banner-icon svg{width:60px;height:60px}

    /* STATS */
    .stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px}
    .stat-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px 22px;transition:border-color .3s,transform .2s}
    .stat-card:hover{border-color:rgba(255,255,255,.15);transform:translateY(-2px)}
    .stat-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px}
    .stat-icon{width:38px;height:38px;border-radius:10px;display:grid;place-items:center}
    .stat-icon svg{width:20px;height:20px;fill:none;stroke-width:1.8}
    .stat-value{font-family:'Bebas Neue',sans-serif;font-size:2.4rem;letter-spacing:.02em;line-height:1}
    .stat-label{font-size:.78rem;color:var(--muted);margin-top:4px}
    .v-green{color:var(--accent2)}.v-blue{color:var(--accent3)}

    /* SECTION TITLE */
    .section-title{display:flex;align-items:center;gap:12px;margin:28px 0 16px}
    .section-title h3{font-family:'Bebas Neue',sans-serif;font-size:1.15rem;letter-spacing:.05em;white-space:nowrap}
    .section-title-line{flex:1;height:1px;background:var(--border)}

    /* SET TIME CARD */
    .set-time-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:24px;margin-bottom:8px}
    .card-header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px}
    .card-title-block{display:flex;align-items:flex-start;gap:10px}
    .card-title-block svg{width:20px;height:20px;fill:none;stroke:var(--accent);stroke-width:1.8;margin-top:2px;flex-shrink:0}
    .card-title-block h4{font-size:.95rem;font-weight:600}
    .card-title-block p{font-size:.75rem;color:var(--muted);margin-top:3px}
    .active-sched-badge{display:flex;align-items:center;gap:7px;background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.25);color:var(--accent2);padding:7px 14px;border-radius:20px;font-size:.75rem;font-weight:600;font-family:'JetBrains Mono',monospace;white-space:nowrap}
    .active-sched-badge .dot{width:7px;height:7px;border-radius:50%;background:var(--accent2);animation:pulse 1.5s infinite;flex-shrink:0}
    @keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}
    .no-sched-badge{display:flex;align-items:center;gap:7px;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);color:#fca5a5;padding:7px 14px;border-radius:20px;font-size:.75rem;font-weight:600;font-family:'JetBrains Mono',monospace}

    /* ADD FORM */
    .sched-form{background:var(--surface2);border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:20px}
    .sched-form-label{font-size:.72rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;font-family:'JetBrains Mono',monospace;margin-bottom:14px}
    .sched-form-grid{display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:12px;align-items:end}
    .form-field{display:flex;flex-direction:column;gap:6px}
    .form-field label{font-size:.7rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.07em;font-family:'JetBrains Mono',monospace}
    .form-field input[type=text]{background:var(--surface3);border:1px solid var(--border);color:var(--text);padding:9px 12px;border-radius:8px;font-size:.875rem;font-family:'DM Sans',sans-serif;outline:none;transition:border-color .2s}
    .form-field input[type=text]:focus{border-color:var(--accent)}
    .form-field input[type=text]::placeholder{color:var(--muted)}

    /* Time picker with AM/PM toggle */
    .time-pick{display:flex;align-items:stretch;background:var(--surface3);border:1px solid var(--border);border-radius:8px;overflow:hidden;transition:border-color .2s}
    .time-pick:focus-within{border-color:var(--accent)}
    .time-pick input[type=time]{background:transparent;border:none;color:var(--text);padding:9px 10px;font-size:.82rem;font-family:'JetBrains Mono',monospace;outline:none;flex:1;min-width:0}
    .time-pick input[type=time]::-webkit-calendar-picker-indicator{filter:invert(.5);cursor:pointer}
    .ampm-grp{display:flex;border-left:1px solid var(--border)}
    .ampm-grp button{padding:0 9px;background:transparent;border:none;border-left:1px solid var(--border);color:var(--muted);font-size:.68rem;font-weight:700;font-family:'JetBrains Mono',monospace;cursor:pointer;transition:background .15s,color .15s;line-height:1}
    .ampm-grp button:first-child{border-left:none}
    .ampm-grp button.sel-am{background:rgba(59,130,246,.18);color:var(--accent3)}
    .ampm-grp button.sel-pm{background:rgba(245,158,11,.18);color:var(--accent)}

    .btn-save-sched{padding:9px 20px;border-radius:8px;background:var(--accent);color:#0a0f1c;border:none;font-size:.875rem;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;transition:opacity .2s;white-space:nowrap;height:38px;align-self:flex-end}
    .btn-save-sched:hover{opacity:.88}

    /* SCHEDULE TABLE */
    .sched-table-wrap{overflow-x:auto}
    .sched-table{width:100%;border-collapse:collapse}
    .sched-table thead th{padding:10px 14px;text-align:left;font-size:.7rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;font-family:'JetBrains Mono',monospace;border-bottom:1px solid var(--border);background:var(--surface2)}
    .sched-table tbody tr{border-bottom:1px solid var(--border);transition:background .15s}
    .sched-table tbody tr:last-child{border-bottom:none}
    .sched-table tbody tr:hover{background:rgba(255,255,255,.02)}
    .sched-table td{padding:11px 14px;font-size:.875rem;vertical-align:middle}
    .sched-label{font-weight:600}
    .t-time{font-family:'JetBrains Mono',monospace;font-size:.82rem}
    .t-late{font-family:'JetBrains Mono',monospace;font-size:.82rem;color:var(--accent)}
    .active-badge{display:inline-flex;align-items:center;gap:4px;background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.2);color:var(--accent2);padding:2px 8px;border-radius:20px;font-size:.65rem;font-weight:700;font-family:'JetBrains Mono',monospace;margin-left:6px}
    .btn-edit{padding:5px 12px;border-radius:6px;background:rgba(59,130,246,.1);border:1px solid rgba(59,130,246,.25);color:var(--accent3);font-size:.78rem;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:background .2s;margin-right:6px}
    .btn-edit:hover{background:rgba(59,130,246,.2)}
    .btn-del{padding:5px 12px;border-radius:6px;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);color:#fca5a5;font-size:.78rem;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:background .2s}
    .btn-del:hover{background:rgba(239,68,68,.15)}
    .sched-empty{padding:32px;text-align:center;color:var(--muted);font-size:.85rem}

    /* ATTENDANCE TABLE */
    .table-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden}
    .table-card-header{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--border);gap:14px;flex-wrap:wrap}
    .table-card-title{font-size:.95rem;font-weight:600;display:flex;align-items:center;gap:8px}
    .tbl-badge{font-size:.72rem;background:var(--surface2);border:1px solid var(--border);color:var(--muted);padding:4px 10px;border-radius:6px;font-family:'JetBrains Mono',monospace}
    .tbl-search{padding:8px 14px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:.85rem;font-family:'DM Sans',sans-serif;outline:none;width:220px;transition:border-color .2s}
    .tbl-search:focus{border-color:var(--accent)}
    .tbl-search::placeholder{color:var(--muted)}
    table.att{width:100%;border-collapse:collapse}
    table.att thead th{padding:12px 16px;text-align:left;font-size:.7rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;font-family:'JetBrains Mono',monospace;border-bottom:1px solid var(--border);background:var(--surface2)}
    table.att tbody tr{border-bottom:1px solid var(--border);transition:background .15s}
    table.att tbody tr:last-child{border-bottom:none}
    table.att tbody tr:hover{background:rgba(255,255,255,.02)}
    table.att td{padding:13px 16px;font-size:.875rem;vertical-align:middle}

    .driver-cell{display:flex;align-items:center;gap:10px}
    .d-avatar{width:36px;height:36px;border-radius:50%;background:var(--surface3);border:1px solid var(--border);overflow:hidden;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .d-avatar img{width:100%;height:100%;object-fit:cover}
    .d-initial{font-family:'Bebas Neue',sans-serif;font-size:1rem;color:var(--accent)}
    .d-name{font-weight:600;font-size:.88rem}
    .d-id{font-family:'JetBrains Mono',monospace;font-size:.68rem;color:var(--muted);margin-top:2px}

    .status-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;font-size:.72rem;font-weight:700;font-family:'JetBrains Mono',monospace;text-transform:uppercase;letter-spacing:.04em}
    .s-active{background:rgba(16,185,129,.12);color:var(--accent2);border:1px solid rgba(16,185,129,.25)}
    .s-inactive{background:rgba(100,116,139,.12);color:var(--muted);border:1px solid rgba(100,116,139,.2)}
    .s-dot{width:6px;height:6px;border-radius:50%}
    .s-active .s-dot{background:var(--accent2)}
    .s-inactive .s-dot{background:var(--muted)}

    .late-tag{display:inline-flex;align-items:center;gap:4px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);color:#fca5a5;padding:3px 9px;border-radius:20px;font-size:.68rem;font-weight:700;font-family:'JetBrains Mono',monospace}
    .ontime-tag{display:inline-flex;align-items:center;gap:4px;background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.2);color:var(--accent2);padding:3px 9px;border-radius:20px;font-size:.68rem;font-weight:700;font-family:'JetBrains Mono',monospace}
    .no-sched-tag{color:var(--muted);font-family:'JetBrains Mono',monospace;font-size:.78rem}

    .tval{font-family:'JetBrains Mono',monospace;font-size:.82rem;color:var(--text)}
    .tempty{color:var(--muted);font-family:'JetBrains Mono',monospace;font-size:.82rem}

    .btn-ti{padding:7px 14px;border-radius:7px;font-size:.8rem;font-weight:600;cursor:pointer;border:1px solid var(--accent3);background:rgba(59,130,246,.1);color:var(--accent3);font-family:'DM Sans',sans-serif;transition:all .2s;display:flex;align-items:center;gap:6px}
    .btn-ti:hover{background:rgba(59,130,246,.2)}
    .btn-to{padding:7px 14px;border-radius:7px;font-size:.8rem;font-weight:600;cursor:pointer;border:1px solid var(--accent2);background:rgba(16,185,129,.1);color:var(--accent2);font-family:'DM Sans',sans-serif;transition:all .2s;display:flex;align-items:center;gap:6px}
    .btn-to:hover{background:rgba(16,185,129,.2)}
    .btn-done{padding:7px 14px;border-radius:7px;font-size:.8rem;font-weight:600;cursor:not-allowed;border:1px solid var(--border);background:var(--surface2);color:var(--muted);font-family:'DM Sans',sans-serif;display:flex;align-items:center;gap:6px}
    .act-btn svg{width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:2}

    .empty-state{padding:50px 20px;text-align:center}
    .empty-state svg{width:44px;height:44px;stroke:var(--muted);fill:none;stroke-width:1.2;margin:0 auto 10px;display:block}
    .empty-state p{color:var(--muted);font-size:.88rem}

    /* SCANNER */
    .scanner-overlay{display:none;position:fixed;inset:0;z-index:3000;background:rgba(5,8,18,.92);backdrop-filter:blur(12px);align-items:center;justify-content:center;padding:20px}
    .scanner-overlay.open{display:flex;animation:fadeIn .2s ease}
    @keyframes fadeIn{from{opacity:0}to{opacity:1}}
    .scanner-box{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:32px;max-width:480px;width:100%;box-shadow:0 40px 100px rgba(0,0,0,.7)}
    .scanner-box h3{font-family:'Bebas Neue',sans-serif;font-size:1.5rem;letter-spacing:.04em;margin-bottom:6px}
    .scanner-sub{font-size:.82rem;color:var(--muted);margin-bottom:22px}
    #qr-reader{border-radius:12px;overflow:hidden;background:#000;border:2px solid var(--border)}
    .scanner-status{margin-top:16px;padding:12px 16px;border-radius:10px;font-size:.85rem;font-weight:500;display:none}
    .scanner-status.success{background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.25);color:var(--accent2);display:block}
    .scanner-status.error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);color:#fca5a5;display:block}
    .scanner-status.info{background:rgba(59,130,246,.1);border:1px solid rgba(59,130,246,.2);color:var(--accent3);display:block}
    .scanner-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:20px}

    /* MODALS */
    .modal-overlay{display:none;position:fixed;inset:0;z-index:3500;background:rgba(5,8,18,.88);backdrop-filter:blur(10px);align-items:center;justify-content:center;padding:20px}
    .modal-overlay.open{display:flex;animation:fadeIn .2s ease}
    .modal-box{background:var(--surface);border:1px solid var(--border);border-radius:18px;padding:30px;max-width:500px;width:100%;box-shadow:0 40px 100px rgba(0,0,0,.7)}
    .modal-box h3{font-family:'Bebas Neue',sans-serif;font-size:1.3rem;letter-spacing:.04em;margin-bottom:20px}
    .mgrid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px}
    .mgrid .full{grid-column:1/-1}
    .modal-footer{display:flex;gap:10px;justify-content:flex-end}

    .btn{padding:10px 22px;border-radius:8px;font-size:.875rem;font-weight:600;cursor:pointer;border:1px solid var(--border);font-family:'DM Sans',sans-serif;transition:all .2s}
    .btn-cancel{background:var(--surface2);color:var(--text)}
    .btn-cancel:hover{background:var(--surface3)}
    .btn-primary{background:var(--accent);color:#0a0f1c;border-color:var(--accent)}
    .btn-primary:hover{opacity:.9}

    .confirm-overlay{display:none;position:fixed;inset:0;z-index:4000;background:rgba(5,8,18,.8);backdrop-filter:blur(6px);align-items:center;justify-content:center}
    .confirm-overlay.open{display:flex;animation:fadeIn .2s ease}
    .confirm-box{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:32px;max-width:360px;width:90%;text-align:center;box-shadow:0 32px 80px rgba(0,0,0,.6)}
    .confirm-icon{width:52px;height:52px;border-radius:50%;display:grid;place-items:center;margin:0 auto 16px;font-size:1.4rem}
    .confirm-box h3{font-family:'Bebas Neue',sans-serif;font-size:1.5rem;letter-spacing:.04em;margin-bottom:8px}
    .confirm-box p{font-size:.85rem;color:var(--muted);margin-bottom:24px;line-height:1.5}
    .confirm-btns{display:flex;gap:10px;justify-content:center}
    .confirm-btn{padding:10px 24px;border-radius:8px;font-size:.88rem;font-weight:600;cursor:pointer;border:1px solid var(--border);font-family:'DM Sans',sans-serif;transition:all .2s}
    .confirm-btn.cancel{background:var(--surface2);color:var(--text)}
    .confirm-btn.cancel:hover{background:var(--surface3)}
    .confirm-btn.danger{background:var(--danger);color:#fff;border-color:var(--danger)}
    .confirm-btn.danger:hover{background:#dc2626}

    .toast-wrap{position:fixed;bottom:24px;right:24px;display:flex;flex-direction:column;gap:10px;z-index:9998;pointer-events:none}
    .toast{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:14px 18px;display:flex;align-items:flex-start;gap:12px;min-width:280px;max-width:360px;box-shadow:0 8px 32px rgba(0,0,0,.4);animation:toastIn .3s ease;pointer-events:all}
    @keyframes toastIn{from{opacity:0;transform:translateX(20px)}to{opacity:1;transform:translateX(0)}}
    .toast-icon{width:20px;height:20px;border-radius:50%;display:grid;place-items:center;flex-shrink:0;margin-top:1px;font-size:.75rem}
    .toast.success .toast-icon{background:rgba(16,185,129,.15);color:var(--accent2)}
    .toast.error .toast-icon{background:rgba(239,68,68,.15);color:#fca5a5}
    .toast.info .toast-icon{background:rgba(59,130,246,.15);color:var(--accent3)}
    .toast-body{flex:1}
    .toast-title{font-size:.85rem;font-weight:600}
    .toast-msg{font-size:.78rem;color:var(--muted);margin-top:2px}
    .toast-close{background:none;border:none;color:var(--muted);cursor:pointer;font-size:1rem;line-height:1;align-self:flex-start}
  </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="sidebar-logo-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="#0a0f1c" stroke-width="2.5" stroke-linecap="round">
        <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
        <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="15" y="15" width="2" height="2"/>
        <rect x="19" y="15" width="2" height="2"/><rect x="15" y="19" width="2" height="2"/><rect x="19" y="19" width="2" height="2"/>
      </svg>
    </div>
    <span class="sidebar-logo-text">Trik<span>Scan</span></span>
  </div>
  <div class="sidebar-user">
    <div class="sidebar-user-name"><?= $adminName ?></div>
    <span class="sidebar-user-role" style="color:<?= $roleColor ?>;border-color:<?= $roleColor ?>33;"><?= $roleLabel ?></span>
    <div class="sidebar-user-email"><?= $adminEmail ?></div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section-label">Main</div>
    <a class="nav-item" href="admin_dashboard.php">
      <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
      Dashboard
    </a>
    <a class="nav-item" href="admin_dashboard.php#drivers">
      <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      Driver Management
    </a>
    <div class="nav-section-label">Operations</div>
    <a class="nav-item active" href="attendance.php">
      <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><polyline points="9 16 11 18 15 14"/></svg>
      Attendance
    </a>
    <div class="nav-section-label">Reports</div>
    <a class="nav-item" href="reports.php">
      <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg>
      Reports
    </a>
  </nav>
  <div class="sidebar-footer">
    <button class="logout-btn" onclick="openModal('confirmOverlay')">
      <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Log Out
    </button>
  </div>
</aside>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div class="topbar-left">
      <h1>Attendance</h1>
      <p id="currentDate">Loading...</p>
    </div>
    <div class="topbar-right">
      <span class="topbar-time" id="liveTime">--:--:--</span>
      <div class="topbar-avatar"><?= strtoupper(substr($adminName,0,1)) ?></div>
    </div>
  </div>

  <div class="page-content">

    <!-- Date Banner -->
    <div class="date-banner">
      <div>
        <h2>Today's Attendance — <span><?= date('F d, Y') ?></span></h2>
        <p>Records are automatically generated for all registered drivers each day.</p>
      </div>
      <div class="date-banner-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
          <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
          <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
          <polyline points="9 16 11 18 15 14"/>
        </svg>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-header"><div class="stat-icon" style="background:rgba(59,130,246,.1)"><svg viewBox="0 0 24 24" stroke="var(--accent3)"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div></div>
        <div class="stat-value v-blue"><?= count($records) ?></div>
        <div class="stat-label">Total Drivers</div>
      </div>
      <div class="stat-card">
        <div class="stat-header"><div class="stat-icon" style="background:rgba(16,185,129,.1)"><svg viewBox="0 0 24 24" stroke="var(--accent2)"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div></div>
        <div class="stat-value v-green" id="statActive"><?= $total_active ?></div>
        <div class="stat-label">Currently Active</div>
      </div>
      <div class="stat-card">
        <div class="stat-header"><div class="stat-icon" style="background:rgba(100,116,139,.1)"><svg viewBox="0 0 24 24" stroke="var(--muted)"><circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/></svg></div></div>
        <div class="stat-value" style="color:var(--muted)" id="statInactive"><?= $total_inactive ?></div>
        <div class="stat-label">Inactive / Not Yet In</div>
      </div>
    </div>

    <!-- ══ SET TIME SECTION ══ -->
    <div class="section-title">
      <h3> Set Time</h3>
      <div class="section-title-line"></div>
    </div>

    <div class="set-time-card">
      <div class="card-header">
        <div class="card-title-block">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          <div>
            <h4>Attendance Schedule</h4>
            <p>Define Time In windows and late thresholds. The latest entry is the active schedule.</p>
          </div>
        </div>
        <?php if ($active_sched): ?>
          <div class="active-sched-badge" id="activeBadge">
            <span class="dot"></span>
            Active: <?= htmlspecialchars($active_sched['label']) ?> &nbsp;|&nbsp;
            Late after <?= date('h:i A', strtotime($active_sched['late_after'])) ?>
          </div>
        <?php else: ?>
          <div class="no-sched-badge" id="activeBadge">⚠ No schedule set</div>
        <?php endif; ?>
      </div>

      <!-- Add Form -->
      <div class="sched-form">
        <div class="sched-form-label">+ Add New Schedule</div>
        <div class="sched-form-grid">
          <div class="form-field">
            <label>Schedule Name</label>
            <input type="text" id="newLabel" placeholder="e.g. Morning Shift"/>
          </div>
          <div class="form-field">
            <label>Time Start</label>
            <div class="time-pick">
              <input type="time" id="newStart" value="06:00" onchange="syncAMPM('newStart','ns')"/>
              <div class="ampm-grp">
                <button id="ns-am" class="sel-am" onclick="clickAMPM('newStart','ns','AM')">AM</button>
                <button id="ns-pm"               onclick="clickAMPM('newStart','ns','PM')">PM</button>
              </div>
            </div>
          </div>
          <div class="form-field">
            <label>Time End</label>
            <div class="time-pick">
              <input type="time" id="newEnd" value="09:00" onchange="syncAMPM('newEnd','ne')"/>
              <div class="ampm-grp">
                <button id="ne-am" class="sel-am" onclick="clickAMPM('newEnd','ne','AM')">AM</button>
                <button id="ne-pm"               onclick="clickAMPM('newEnd','ne','PM')">PM</button>
              </div>
            </div>
          </div>
          <div class="form-field">
            <label>Late After</label>
            <div class="time-pick">
              <input type="time" id="newLate" value="07:00" onchange="syncAMPM('newLate','nl')"/>
              <div class="ampm-grp">
                <button id="nl-am" class="sel-am" onclick="clickAMPM('newLate','nl','AM')">AM</button>
                <button id="nl-pm"               onclick="clickAMPM('newLate','nl','PM')">PM</button>
              </div>
            </div>
          </div>
          <div>
            <button class="btn-save-sched" onclick="addSchedule()">Save Schedule</button>
          </div>
        </div>
      </div>

      <!-- Schedule Table -->
      <div class="sched-table-wrap">
        <table class="sched-table">
          <thead>
            <tr>
              <th>Schedule Name</th>
              <th>Time Start</th>
              <th>Time End</th>
              <th>Late After</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="schedBody">
            <?php if (empty($schedules)): ?>
            <tr id="schedEmpty"><td colspan="5" class="sched-empty">No schedules yet. Add one above.</td></tr>
            <?php else: ?>
            <?php foreach ($schedules as $s):
              $isActive = $active_sched && $s['id'] === $active_sched['id'];
            ?>
            <tr id="sr-<?= $s['id'] ?>">
              <td>
                <span class="sched-label"><?= htmlspecialchars($s['label']) ?></span>
                <?php if ($isActive): ?><span class="active-badge">● Active</span><?php endif; ?>
              </td>
              <td><span class="t-time"><?= date('h:i A', strtotime($s['time_start'])) ?></span></td>
              <td><span class="t-time"><?= date('h:i A', strtotime($s['time_end'])) ?></span></td>
              <td><span class="t-late"><?= date('h:i A', strtotime($s['late_after'])) ?></span></td>
              <td>
                <button class="btn-edit" onclick="openEdit(<?= $s['id'] ?>,'<?= addslashes($s['label']) ?>','<?= $s['time_start'] ?>','<?= $s['time_end'] ?>','<?= $s['late_after'] ?>')">Edit</button>
                <button class="btn-del"  onclick="confirmDel(<?= $s['id'] ?>,'<?= addslashes($s['label']) ?>')">Delete</button>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ══ ATTENDANCE TABLE ══ -->
    <div class="section-title">
      <h3> Daily Attendance Sheet</h3>
      <div class="section-title-line"></div>
    </div>

    <div class="table-card">
      <div class="table-card-header">
        <div class="table-card-title">
          <svg viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="1.8" style="width:18px;height:18px;"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          <?= date('F d, Y') ?>
          <span class="tbl-badge" id="tableBadge"><?= count($records) ?> Drivers</span>
        </div>
        <input class="tbl-search" type="search" placeholder="Search driver..." oninput="filterTable(this.value)" autocomplete="off"/>
      </div>
      <div style="overflow-x:auto">
        <table class="att">
          <thead>
            <tr>
              <th>Driver</th>
              <th>Date</th>
              <th>Time In</th>
              <th>Time Out</th>
              <th>Status</th>
              <th>Punctuality</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody id="attendanceBody">
            <?php if (empty($records)): ?>
            <tr id="emptyRow"><td colspan="7"><div class="empty-state">
              <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
              <p>No drivers registered yet.</p>
            </div></td></tr>
            <?php else: ?>
            <?php foreach ($records as $r):
              $initials = strtoupper(($r['firstname'][0]??'').($r['lastname'][0]??''));
              $fullName = htmlspecialchars($r['firstname'].' '.$r['lastname']);
              $drvId    = htmlspecialchars($r['driver_str_id']);
              $tIn      = $r['time_in']  ? date('h:i A', strtotime($r['time_in']))  : null;
              $tOut     = $r['time_out'] ? date('h:i A', strtotime($r['time_out'])) : null;
              $attId    = (int)$r['att_id'];
              $hasPic   = !empty($r['picture']);
              $late     = $active_sched && $r['time_in'] ? isLate($r['time_in'], $active_sched['late_after']) : null;
            ?>
            <tr data-att-id="<?= $attId ?>" data-name="<?= strtolower($fullName) ?>" id="row-<?= $attId ?>">
              <td>
                <div class="driver-cell">
                  <div class="d-avatar">
                    <?php if ($hasPic): ?>
                      <img src="<?= htmlspecialchars($r['picture']) ?>" alt="<?= $fullName ?>"/>
                    <?php else: ?>
                      <span class="d-initial"><?= $initials ?></span>
                    <?php endif; ?>
                  </div>
                  <div>
                    <div class="d-name"><?= $fullName ?></div>
                    <div class="d-id"><?= $drvId ?></div>
                  </div>
                </div>
              </td>
              <td><span style="font-family:'JetBrains Mono',monospace;font-size:.8rem"><?= date('M d, Y', strtotime($r['date'])) ?></span></td>
              <td id="ti-<?= $attId ?>"><?= $tIn ? "<span class='tval'>$tIn</span>" : "<span class='tempty'>—</span>" ?></td>
              <td id="to-<?= $attId ?>"><?= $tOut ? "<span class='tval'>$tOut</span>" : "<span class='tempty'>—</span>" ?></td>
              <td id="st-<?= $attId ?>">
                <?php if ($r['status']==='active'): ?>
                  <span class="status-badge s-active"><span class="s-dot"></span>Active</span>
                <?php else: ?>
                  <span class="status-badge s-inactive"><span class="s-dot"></span>Inactive</span>
                <?php endif; ?>
              </td>
              <td id="pt-<?= $attId ?>">
                <?php if ($r['time_in'] && $active_sched): ?>
                  <?= $late ? '<span class="late-tag">⚠ Late</span>' : '<span class="ontime-tag">✓ On Time</span>' ?>
                <?php elseif ($r['time_in'] && !$active_sched): ?>
                  <span class="no-sched-tag">No schedule</span>
                <?php else: ?>
                  <span class="tempty">—</span>
                <?php endif; ?>
              </td>
              <td id="ac-<?= $attId ?>">
                <?php if (!$tIn): ?>
                  <button class="btn-ti act-btn" onclick="openScanner(<?= $attId ?>,'time_in','<?= addslashes($fullName) ?>')">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>Time In
                  </button>
                <?php elseif (!$tOut): ?>
                  <button class="btn-to act-btn" onclick="openScanner(<?= $attId ?>,'time_out','<?= addslashes($fullName) ?>')">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>Time Out
                  </button>
                <?php else: ?>
                  <button class="btn-done act-btn" disabled>
                    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>Done
                  </button>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div><!-- end page-content -->
</div><!-- end main -->

<!-- SCANNER MODAL -->
<div class="scanner-overlay" id="scannerOverlay">
  <div class="scanner-box">
    <h3 id="scanTitle">⚡ QR Scanner</h3>
    <p class="scanner-sub" id="scanSub">Point the camera at the driver's QR code.</p>
    <div id="qr-reader"></div>
    <div class="scanner-status" id="scanStatus"></div>
    <div class="scanner-actions"><button class="btn btn-cancel" onclick="closeScanner()">Cancel</button></div>
  </div>
</div>

<!-- EDIT SCHEDULE MODAL -->
<div class="modal-overlay" id="editModal">
  <div class="modal-box">
    <h3>✏️ Edit Schedule</h3>
    <input type="hidden" id="editId"/>
    <div class="mgrid">
      <div class="form-field full">
        <label>Schedule Name</label>
        <input type="text" id="editLabel" placeholder="e.g. Morning Shift"/>
      </div>
      <div class="form-field">
        <label>Time Start</label>
        <div class="time-pick">
          <input type="time" id="editStart" onchange="syncAMPM('editStart','es')"/>
          <div class="ampm-grp">
            <button id="es-am" onclick="clickAMPM('editStart','es','AM')">AM</button>
            <button id="es-pm" onclick="clickAMPM('editStart','es','PM')">PM</button>
          </div>
        </div>
      </div>
      <div class="form-field">
        <label>Time End</label>
        <div class="time-pick">
          <input type="time" id="editEnd" onchange="syncAMPM('editEnd','ee')"/>
          <div class="ampm-grp">
            <button id="ee-am" onclick="clickAMPM('editEnd','ee','AM')">AM</button>
            <button id="ee-pm" onclick="clickAMPM('editEnd','ee','PM')">PM</button>
          </div>
        </div>
      </div>
      <div class="form-field full">
        <label>Late After</label>
        <div class="time-pick">
          <input type="time" id="editLate" onchange="syncAMPM('editLate','el')"/>
          <div class="ampm-grp">
            <button id="el-am" onclick="clickAMPM('editLate','el','AM')">AM</button>
            <button id="el-pm" onclick="clickAMPM('editLate','el','PM')">PM</button>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-cancel" onclick="closeModal('editModal')">Cancel</button>
      <button class="btn btn-primary" onclick="saveEdit()">Save Changes</button>
    </div>
  </div>
</div>

<!-- DELETE SCHED CONFIRM -->
<div class="confirm-overlay" id="delSchedOverlay">
  <div class="confirm-box">
    <div class="confirm-icon" style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2)">🗑️</div>
    <h3>Delete Schedule?</h3>
    <p id="delSchedText">This cannot be undone.</p>
    <div class="confirm-btns">
      <button class="confirm-btn cancel" onclick="closeModal('delSchedOverlay')">Cancel</button>
      <button class="confirm-btn danger" id="delSchedBtn">Delete</button>
    </div>
  </div>
</div>

<!-- LOGOUT CONFIRM -->
<div class="confirm-overlay" id="confirmOverlay">
  <div class="confirm-box">
    <div class="confirm-icon" style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2)">🚪</div>
    <h3>Log Out?</h3>
    <p>You'll be returned to the login page.</p>
    <div class="confirm-btns">
      <button class="confirm-btn cancel" onclick="closeModal('confirmOverlay')">Cancel</button>
      <button class="confirm-btn danger" onclick="doLogout()">Log Out</button>
    </div>
  </div>
</div>

<div class="toast-wrap" id="toastWrap"></div>

<script>
/* ─ Clock ─ */
(function tick(){
  const n = new Date();
  document.getElementById('liveTime').textContent   = n.toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
  document.getElementById('currentDate').textContent = n.toLocaleDateString('en-PH',{weekday:'long',year:'numeric',month:'long',day:'numeric'});
  setTimeout(tick,1000);
})();

/* ─ Toast ─ */
function showToast(type,title,msg){
  const icons={success:'✓',error:'✕',info:'i'};
  const t=document.createElement('div');
  t.className=`toast ${type}`;
  t.innerHTML=`<div class="toast-icon">${icons[type]||'i'}</div><div class="toast-body"><div class="toast-title">${title}</div><div class="toast-msg">${msg}</div></div><button class="toast-close" onclick="this.closest('.toast').remove()">×</button>`;
  document.getElementById('toastWrap').appendChild(t);
  setTimeout(()=>{t.style.transition='opacity .4s';t.style.opacity='0';setTimeout(()=>t.remove(),400);},4000);
}

/* ─ Modals ─ */
function openModal(id){document.getElementById(id).classList.add('open')}
function closeModal(id){document.getElementById(id).classList.remove('open')}

/* ══════════════════════════════
   AM / PM TOGGLE
══════════════════════════════ */
function syncAMPM(inputId, prefix){
  const h = parseInt((document.getElementById(inputId).value||'00:00').split(':')[0]);
  const isAM = h < 12;
  document.getElementById(prefix+'-am').className = isAM ? 'sel-am' : '';
  document.getElementById(prefix+'-pm').className = isAM ? '' : 'sel-pm';
}

function clickAMPM(inputId, prefix, period){
  const inp = document.getElementById(inputId);
  let [h,m] = (inp.value||'00:00').split(':').map(Number);
  if(period==='AM' && h>=12) h -= 12;
  if(period==='PM' && h<12)  h += 12;
  inp.value = String(h).padStart(2,'0')+':'+String(m).padStart(2,'0');
  syncAMPM(inputId, prefix);
}

/* ══════════════════════════════
   SCHEDULE CRUD
══════════════════════════════ */
function addSchedule(){
  const label = document.getElementById('newLabel').value.trim();
  const start = document.getElementById('newStart').value;
  const end   = document.getElementById('newEnd').value;
  const late  = document.getElementById('newLate').value;
  if(!label||!start||!end||!late){showToast('error','Missing Fields','Fill in all fields.');return;}
  const fd=new FormData();
  fd.append('action','save_schedule');
  fd.append('label',label);
  fd.append('time_start',start+':00');
  fd.append('time_end',end+':00');
  fd.append('late_after',late+':00');
  fetch('attendance.php',{method:'POST',body:fd})
    .then(r=>r.json()).then(d=>{
      if(d.success){
        showToast('success','Saved',d.message);
        addSchedRow(d.schedule,true);
        document.getElementById('newLabel').value='';
        document.getElementById('newStart').value='06:00';
        document.getElementById('newEnd').value='09:00';
        document.getElementById('newLate').value='07:00';
        ['newStart,ns','newEnd,ne','newLate,nl'].forEach(x=>{const[a,b]=x.split(',');syncAMPM(a,b);});
        updateActiveBadge(d.schedule);
      }else showToast('error','Error',d.message);
    });
}

function addSchedRow(s,markActive){
  const empty=document.getElementById('schedEmpty');
  if(empty)empty.remove();
  if(markActive) document.querySelectorAll('.active-badge').forEach(b=>b.remove());
  const ab=markActive?`<span class="active-badge">● Active</span>`:'';
  const tr=document.createElement('tr');
  tr.id=`sr-${s.id}`;
  tr.innerHTML=`
    <td><span class="sched-label">${esc(s.label)}</span>${ab}</td>
    <td><span class="t-time">${fmt(s.time_start)}</span></td>
    <td><span class="t-time">${fmt(s.time_end)}</span></td>
    <td><span class="t-late">${fmt(s.late_after)}</span></td>
    <td>
      <button class="btn-edit" onclick="openEdit(${s.id},'${escJ(s.label)}','${s.time_start}','${s.time_end}','${s.late_after}')">Edit</button>
      <button class="btn-del"  onclick="confirmDel(${s.id},'${escJ(s.label)}')">Delete</button>
    </td>`;
  document.getElementById('schedBody').appendChild(tr);
}

function openEdit(id,label,start,end,late){
  document.getElementById('editId').value    = id;
  document.getElementById('editLabel').value = label;
  document.getElementById('editStart').value = start.substring(0,5);
  document.getElementById('editEnd').value   = end.substring(0,5);
  document.getElementById('editLate').value  = late.substring(0,5);
  syncAMPM('editStart','es'); syncAMPM('editEnd','ee'); syncAMPM('editLate','el');
  openModal('editModal');
}

function saveEdit(){
  const id    = document.getElementById('editId').value;
  const label = document.getElementById('editLabel').value.trim();
  const start = document.getElementById('editStart').value;
  const end   = document.getElementById('editEnd').value;
  const late  = document.getElementById('editLate').value;
  if(!label||!start||!end||!late){showToast('error','Missing Fields','Fill in all fields.');return;}
  const fd=new FormData();
  fd.append('action','update_schedule'); fd.append('id',id);
  fd.append('label',label); fd.append('time_start',start+':00');
  fd.append('time_end',end+':00'); fd.append('late_after',late+':00');
  fetch('attendance.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){
      showToast('success','Updated',d.message);
      closeModal('editModal');
      const row=document.getElementById(`sr-${id}`);
      if(row){
        const tds=row.querySelectorAll('td');
        const ab=row.querySelector('.active-badge')?`<span class="active-badge">● Active</span>`:'';
        tds[0].innerHTML=`<span class="sched-label">${esc(label)}</span>${ab}`;
        tds[1].innerHTML=`<span class="t-time">${fmt(start+':00')}</span>`;
        tds[2].innerHTML=`<span class="t-time">${fmt(end+':00')}</span>`;
        tds[3].innerHTML=`<span class="t-late">${fmt(late+':00')}</span>`;
        row.querySelector('.btn-edit').setAttribute('onclick',`openEdit(${id},'${escJ(label)}','${start+':00'}','${end+':00'}','${late+':00'}')`);
      }
      // If it has the active badge, update the header badge too
      if(document.querySelector(`#sr-${id} .active-badge`)){
        updateActiveBadge({id,label,late_after:late+':00'});
      }
    }else showToast('error','Error',d.message);
  });
}

function confirmDel(id,label){
  document.getElementById('delSchedText').textContent=`Delete schedule "${label}"? This cannot be undone.`;
  document.getElementById('delSchedBtn').onclick=()=>doDelSched(id);
  openModal('delSchedOverlay');
}
function doDelSched(id){
  const fd=new FormData(); fd.append('action','delete_schedule'); fd.append('id',id);
  fetch('attendance.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    closeModal('delSchedOverlay');
    if(d.success){
      showToast('success','Deleted',d.message);
      const row=document.getElementById(`sr-${id}`);
      const wasActive=row&&row.querySelector('.active-badge');
      if(row){row.style.opacity='0';row.style.transition='opacity .3s';setTimeout(()=>{row.remove();checkEmpty();},300);}
      if(wasActive) document.getElementById('activeBadge').outerHTML=`<div class="no-sched-badge" id="activeBadge">⚠ No schedule set</div>`;
    }else showToast('error','Error',d.message);
  });
}
function checkEmpty(){
  if(!document.querySelector('#schedBody tr[id^="sr-"]'))
    document.getElementById('schedBody').innerHTML=`<tr id="schedEmpty"><td colspan="5" class="sched-empty">No schedules yet. Add one above.</td></tr>`;
}

function updateActiveBadge(s){
  const b=document.getElementById('activeBadge');
  if(!b)return;
  b.className='active-sched-badge';
  b.innerHTML=`<span class="dot"></span> Active: ${esc(s.label)} &nbsp;|&nbsp; Late after ${fmt(s.late_after)}`;
}

/* ══════════════════════════════
   QR SCANNER
══════════════════════════════ */
let qr=null,scanAction=null,scanId=null,scanned=false;

function openScanner(attId,action,name){
  scanAction=action; scanId=attId; scanned=false;
  document.getElementById('scanTitle').innerHTML   = action==='time_in'?'⏱ Time In — QR Scan':'⏹ Time Out — QR Scan';
  document.getElementById('scanSub').innerHTML     = `Scanning for: <strong>${name}</strong>`;
  const s=document.getElementById('scanStatus');
  s.className='scanner-status info'; s.textContent='📷 Camera starting…';
  openModal('scannerOverlay');
  qr=new Html5Qrcode('qr-reader');
  qr.start({facingMode:'environment'},{fps:10,qrbox:{width:250,height:250}},onScan,()=>{})
    .then(()=>{s.textContent='✅ Camera ready — hold QR code steady.';})
    .catch(e=>{s.className='scanner-status error';s.textContent='❌ Camera error: '+e;});
}
function closeScanner(){
  closeModal('scannerOverlay');
  document.getElementById('scanStatus').className='scanner-status';
  if(qr){qr.stop().then(()=>{qr.clear();qr=null;document.getElementById('qr-reader').innerHTML='';}).catch(()=>{qr=null;document.getElementById('qr-reader').innerHTML='';});}
}
function onScan(txt){
  if(scanned)return; scanned=true;
  const s=document.getElementById('scanStatus');
  s.className='scanner-status info'; s.textContent='🔎 Validating QR…';
  if(qr)qr.pause();
  const fd=new FormData();
  fd.append('action',scanAction); fd.append('attendance_id',scanId); fd.append('qr_driver_id',txt.trim());
  fetch('attendance.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){
      s.className='scanner-status success'; s.textContent='✅ '+d.message;
      updateRow(scanId,scanAction,d);
      showToast('success',scanAction==='time_in'?'Time In Recorded':'Time Out Recorded',d.message);
      setTimeout(closeScanner,1500);
    }else{
      s.className='scanner-status error'; s.textContent='❌ '+d.message;
      showToast('error','Validation Failed',d.message);
      setTimeout(()=>{scanned=false;if(qr)qr.resume();s.className='scanner-status info';s.textContent='📷 Try again — hold QR code steady.';},2500);
    }
  }).catch(()=>{s.className='scanner-status error';s.textContent='❌ Network error.';scanned=false;if(qr)qr.resume();});
}

function updateRow(attId,action,data){
  if(action==='time_in'){
    const ti=document.getElementById('ti-'+attId);
    if(ti)ti.innerHTML=`<span class="tval">${f12(data.time_in)}</span>`;
    const st=document.getElementById('st-'+attId);
    if(st)st.innerHTML=`<span class="status-badge s-active"><span class="s-dot"></span>Active</span>`;
    const pt=document.getElementById('pt-'+attId);
    if(pt){
      if(data.is_late!==undefined)
        pt.innerHTML=data.is_late?`<span class="late-tag">⚠ Late</span>`:`<span class="ontime-tag">✓ On Time</span>`;
      else pt.innerHTML=`<span class="no-sched-tag">No schedule</span>`;
    }
    const ac=document.getElementById('ac-'+attId);
    if(ac){
      const nm=document.getElementById('row-'+attId)?.querySelector('.d-name')?.textContent||'';
      ac.innerHTML=`<button class="btn-to act-btn" onclick="openScanner(${attId},'time_out','${nm.replace(/'/g,"\\'")}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>Time Out</button>`;
    }
    upStats(1,-1);
  }else{
    const to=document.getElementById('to-'+attId);
    if(to)to.innerHTML=`<span class="tval">${f12(data.time_out)}</span>`;
    const st=document.getElementById('st-'+attId);
    if(st)st.innerHTML=`<span class="status-badge s-inactive"><span class="s-dot"></span>Inactive</span>`;
    const ac=document.getElementById('ac-'+attId);
    if(ac)ac.innerHTML=`<button class="btn-done act-btn" disabled><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>Done</button>`;
    upStats(-1,1);
  }
}
function upStats(a,b){
  const ae=document.getElementById('statActive');
  const be=document.getElementById('statInactive');
  if(ae)ae.textContent=Math.max(0,parseInt(ae.textContent)+a);
  if(be)be.textContent=Math.max(0,parseInt(be.textContent)+b);
}

/* ─ Helpers ─ */
function f12(t){if(!t)return'—';const[h,m]=t.split(':');const hh=parseInt(h);return`${hh%12||12}:${m} ${hh>=12?'PM':'AM'}`;}
function fmt(t){if(!t)return'—';const p=t.split(':');const hh=parseInt(p[0]);return`${hh%12||12}:${p[1]} ${hh>=12?'PM':'AM'}`;}
function esc(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function escJ(s){return String(s).replace(/\\/g,'\\\\').replace(/'/g,"\\'");}
function filterTable(q){
  q=q.toLowerCase().trim();
  const rows=document.querySelectorAll('#attendanceBody tr[data-name]');
  let v=0;
  rows.forEach(r=>{const m=!q||r.dataset.name.includes(q);r.style.display=m?'':'none';if(m)v++;});
  document.getElementById('tableBadge').textContent=v+' Drivers';
}
function doLogout(){fetch('logout.php').then(()=>{location.href='homepage.php';}).catch(()=>{location.href='homepage.php';});}

window.addEventListener('load',()=>{setTimeout(()=>showToast('info','Attendance Loaded','Records auto-generated for today.'),600);});
</script>
</body>
</html>