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

$today = date('Y-m-d');

/* ── Fetch late_after threshold ── */
$schedRow = $db->query("SELECT late_after FROM attendance_schedule ORDER BY id DESC LIMIT 1")->fetch_assoc();
$lateAfter = $schedRow ? $schedRow['late_after'] : '09:00:00';

/* ── AJAX: filter_report ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'filter_report') {
    header('Content-Type: application/json');

    $filterType  = $db->real_escape_string($_POST['filter_type']  ?? 'date');
    $filterVal   = $db->real_escape_string($_POST['filter_value'] ?? $today);
    $statusFilter= $db->real_escape_string($_POST['status_filter'] ?? 'all');

    $lateQ = $db->query("SELECT late_after FROM attendance_schedule ORDER BY id DESC LIMIT 1")->fetch_assoc();
    $lateT = $lateQ ? $lateQ['late_after'] : '09:00:00';

    switch ($filterType) {
        case 'date':  $whereDate = "a.date = '$filterVal'"; break;
        case 'month': $whereDate = "DATE_FORMAT(a.date,'%Y-%m') = '$filterVal'"; break;
        case 'year':  $whereDate = "YEAR(a.date) = '$filterVal'"; break;
        case 'day':   $whereDate = "DAYOFWEEK(a.date) = '$filterVal'"; break;
        default:      $whereDate = "a.date = '$filterVal'";
    }

    $rows = [];

    /* Drivers with attendance records */
    $res = $db->query("
        SELECT a.*, d.firstname, d.middlename, d.lastname, d.driver_id AS drv_id, d.contact_number
        FROM attendance a
        JOIN drivers_registered_tb d ON d.id = a.driver_id
        WHERE $whereDate
        ORDER BY a.date ASC, d.firstname ASC
    ");
    while ($r = $res->fetch_assoc()) {
        $isLate = $r['time_in'] && (strtotime($r['time_in']) > strtotime($lateT));
        if ($r['time_in'] && $r['time_out']) {
            $status = 'Inactive'; $badge = 'status-inactive';
        } elseif ($r['time_in'] && !$r['time_out']) {
            $status = $isLate ? 'Late' : 'Present';
            $badge  = $isLate ? 'status-late' : 'status-present';
        } else {
            $status = 'Absent'; $badge = 'status-absent';
        }
        if ($statusFilter !== 'all' && strtolower($status) !== strtolower($statusFilter)) continue;
        $rows[] = [
            'drv_id'   => $r['drv_id'],
            'name'     => trim($r['firstname'].' '.($r['middlename'] ? $r['middlename'][0].'. ' : '').$r['lastname']),
            'date'     => $r['date'],
            'time_in'  => $r['time_in']  ? date('h:i A', strtotime($r['time_in']))  : '—',
            'time_out' => $r['time_out'] ? date('h:i A', strtotime($r['time_out'])) : '—',
            'status'   => $status,
            'badge'    => $badge,
            'contact'  => $r['contact_number'] ?: '—',
        ];
    }

    /* For date filter: also show absent (no record at all) */
    if ($filterType === 'date' && ($statusFilter === 'all' || $statusFilter === 'Absent')) {
        $abRes = $db->query("
            SELECT d.firstname, d.middlename, d.lastname, d.driver_id AS drv_id, d.contact_number
            FROM drivers_registered_tb d
            LEFT JOIN attendance a ON a.driver_id = d.id AND $whereDate
            WHERE a.id IS NULL
            ORDER BY d.firstname ASC
        ");
        while ($dr = $abRes->fetch_assoc()) {
            $rows[] = [
                'drv_id'   => $dr['drv_id'],
                'name'     => trim($dr['firstname'].' '.($dr['middlename'] ? $dr['middlename'][0].'. ' : '').$dr['lastname']),
                'date'     => $filterVal,
                'time_in'  => '—',
                'time_out' => '—',
                'status'   => 'Absent',
                'badge'    => 'status-absent',
                'contact'  => $dr['contact_number'] ?: '—',
            ];
        }
    }

    /* Summary counts */
    $summary = ['Present'=>0,'Late'=>0,'Inactive'=>0,'Absent'=>0,'Total'=>count($rows)];
    foreach ($rows as $row) {
        if (isset($summary[$row['status']])) $summary[$row['status']]++;
    }

    echo json_encode(['success'=>true, 'rows'=>$rows, 'summary'=>$summary]);
    exit;
}

/* ── Initial load: today's data ── */
$initRows = [];
$res = $db->query("
    SELECT a.*, d.firstname, d.middlename, d.lastname, d.driver_id AS drv_id, d.contact_number
    FROM attendance a
    JOIN drivers_registered_tb d ON d.id = a.driver_id
    WHERE a.date = '$today'
    ORDER BY d.firstname ASC
");
while ($r = $res->fetch_assoc()) {
    $isLate = $r['time_in'] && (strtotime($r['time_in']) > strtotime($lateAfter));
    if ($r['time_in'] && $r['time_out'])      { $status='Inactive'; $badge='status-inactive'; }
    elseif ($r['time_in'] && !$r['time_out']) { $status=$isLate?'Late':'Present'; $badge=$isLate?'status-late':'status-present'; }
    else                                       { $status='Absent';  $badge='status-absent'; }
    $initRows[] = ['drv_id'=>$r['drv_id'],'name'=>trim($r['firstname'].' '.($r['middlename']?$r['middlename'][0].'. ':'').$r['lastname']),'date'=>$r['date'],'time_in'=>$r['time_in']?date('h:i A',strtotime($r['time_in'])):'—','time_out'=>$r['time_out']?date('h:i A',strtotime($r['time_out'])):'—','status'=>$status,'badge'=>$badge,'contact'=>$r['contact_number']?:'—'];
}
/* absent drivers today */
$abRes = $db->query("SELECT d.firstname,d.middlename,d.lastname,d.driver_id AS drv_id,d.contact_number FROM drivers_registered_tb d LEFT JOIN attendance a ON a.driver_id=d.id AND a.date='$today' WHERE a.id IS NULL ORDER BY d.firstname ASC");
while ($dr = $abRes->fetch_assoc()) {
    $initRows[] = ['drv_id'=>$dr['drv_id'],'name'=>trim($dr['firstname'].' '.($dr['middlename']?$dr['middlename'][0].'. ':'').$dr['lastname']),'date'=>$today,'time_in'=>'—','time_out'=>'—','status'=>'Absent','badge'=>'status-absent','contact'=>$dr['contact_number']?:'—'];
}

$totalDrivers = $db->query("SELECT COUNT(*) AS c FROM drivers_registered_tb")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reports — TrikScan Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet"/>
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

    /* ── SIDEBAR ── */
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
    .nav-badge{margin-left:auto;background:var(--accent);color:#0a0f1c;font-size:.68rem;font-weight:700;padding:2px 7px;border-radius:20px;font-family:'JetBrains Mono',monospace}
    .sidebar-footer{padding:14px 12px;border-top:1px solid var(--border)}
    .logout-btn{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:8px;color:var(--muted);font-size:.875rem;font-weight:500;cursor:pointer;border:none;background:none;width:100%;transition:background .2s,color .2s}
    .logout-btn svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:1.8}
    .logout-btn:hover{background:rgba(239,68,68,.08);color:#fca5a5}

    /* ── MAIN ── */
    .main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh}
    .topbar{background:rgba(10,15,28,.8);backdrop-filter:blur(16px);border-bottom:1px solid var(--border);padding:16px 28px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50}
    .topbar-left h1{font-family:'Bebas Neue',sans-serif;font-size:1.5rem;letter-spacing:.04em;line-height:1}
    .topbar-left p{font-size:.78rem;color:var(--muted);margin-top:2px;font-family:'JetBrains Mono',monospace}
    .topbar-right{display:flex;align-items:center;gap:12px}
    .topbar-time{font-family:'JetBrains Mono',monospace;font-size:.8rem;color:var(--muted)}
    .topbar-avatar{width:36px;height:36px;border-radius:50%;background:var(--accent);display:grid;place-items:center;font-family:'Bebas Neue',sans-serif;font-size:1rem;color:#0a0f1c;font-weight:900}

    .page-content{padding:28px;flex:1;animation:fadeUp .4s ease}
    @keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}

    /* ── SUMMARY CARDS ── */
    .summary-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:24px}
    .sum-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px 18px}
    .sum-label{font-size:.7rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;font-family:'JetBrains Mono',monospace;margin-bottom:6px}
    .sum-val{font-family:'Bebas Neue',sans-serif;font-size:2rem;letter-spacing:.02em;line-height:1}
    .v-green{color:var(--accent2)} .v-amber{color:var(--accent)} .v-red{color:#ef4444} .v-muted{color:var(--muted)} .v-blue{color:var(--accent3)}

    /* ── FILTER CARD ── */
    .filter-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px 22px;margin-bottom:20px}
    .filter-card-title{font-size:.88rem;font-weight:600;margin-bottom:16px;display:flex;align-items:center;gap:8px}
    .filter-card-title svg{width:16px;height:16px;stroke:var(--accent);fill:none;stroke-width:2}
    .filter-row{display:flex;align-items:flex-end;gap:14px;flex-wrap:wrap}
    .filter-group{display:flex;flex-direction:column;gap:6px}
    .filter-group label{font-size:.68rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;font-family:'JetBrains Mono',monospace}

    .filter-tabs{display:flex;gap:4px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:3px}
    .f-tab{background:none;border:none;color:var(--muted);font-size:.75rem;font-weight:600;padding:6px 14px;border-radius:6px;cursor:pointer;font-family:'JetBrains Mono',monospace;letter-spacing:.04em;transition:background .2s,color .2s}
    .f-tab:hover{color:var(--text)}
    .f-tab.active{background:var(--accent);color:#0a0f1c}

    .f-input{background:var(--surface2);border:1px solid var(--border);border-radius:8px;color:var(--text);font-family:'JetBrains Mono',monospace;font-size:.82rem;padding:8px 12px;outline:none;cursor:pointer;transition:border-color .2s;height:38px}
    .f-input:focus{border-color:var(--accent)}
    .f-input option{background:var(--surface2)}

    .f-select{background:var(--surface2);border:1px solid var(--border);border-radius:8px;color:var(--text);font-family:'JetBrains Mono',monospace;font-size:.82rem;padding:8px 12px;outline:none;cursor:pointer;transition:border-color .2s;height:38px}
    .f-select:focus{border-color:var(--accent)}
    .f-select option{background:var(--surface2)}

    .btn-apply{display:inline-flex;align-items:center;gap:8px;padding:8px 20px;border-radius:8px;background:var(--accent);border:none;color:#0a0f1c;font-size:.875rem;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;transition:opacity .2s;height:38px}
    .btn-apply:hover{opacity:.88}
    .btn-apply svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:2.2}
    .btn-reset{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;background:var(--surface2);border:1px solid var(--border);color:var(--muted);font-size:.82rem;font-weight:600;cursor:pointer;font-family:'JetBrains Mono',monospace;transition:all .2s;height:38px}
    .btn-reset:hover{color:var(--text);border-color:rgba(255,255,255,.15)}
    .btn-reset svg{width:13px;height:13px;fill:none;stroke:currentColor;stroke-width:2}
    .btn-print{display:inline-flex;align-items:center;gap:8px;padding:8px 20px;border-radius:8px;background:rgba(59,130,246,.1);border:1px solid rgba(59,130,246,.3);color:var(--accent3);font-size:.875rem;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .2s;height:38px}
    .btn-print:hover{background:rgba(59,130,246,.2)}
    .btn-print svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:1.8}

    /* ── REPORT TABLE ── */
    .table-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden}
    .table-header{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:12px}
    .table-title{font-size:.95rem;font-weight:600}
    .table-sub{font-size:.75rem;color:var(--muted);margin-top:2px;font-family:'JetBrains Mono',monospace}
    .tbl-badge{font-size:.72rem;background:var(--surface2);border:1px solid var(--border);color:var(--muted);padding:4px 10px;border-radius:6px;font-family:'JetBrains Mono',monospace}
    .tbl-search{padding:7px 12px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:.82rem;font-family:'DM Sans',sans-serif;outline:none;width:200px;transition:border-color .2s}
    .tbl-search:focus{border-color:var(--accent)}
    .tbl-search::placeholder{color:var(--muted)}

    table.rep{width:100%;border-collapse:collapse}
    table.rep thead th{padding:11px 16px;text-align:left;font-size:.68rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;font-family:'JetBrains Mono',monospace;border-bottom:1px solid var(--border);background:var(--surface2)}
    table.rep tbody tr{border-bottom:1px solid var(--border);transition:background .15s}
    table.rep tbody tr:last-child{border-bottom:none}
    table.rep tbody tr:hover{background:rgba(255,255,255,.02)}
    table.rep td{padding:12px 16px;font-size:.875rem;vertical-align:middle}
    .drv-id-badge{display:inline-block;font-family:'JetBrains Mono',monospace;font-size:.7rem;background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.2);color:var(--accent);padding:2px 8px;border-radius:6px}
    .tval{font-family:'JetBrains Mono',monospace;font-size:.82rem}
    .tempty{color:var(--muted);font-family:'JetBrains Mono',monospace;font-size:.82rem}

    .status-badge{display:inline-block;font-size:.68rem;font-weight:700;padding:3px 10px;border-radius:20px;font-family:'JetBrains Mono',monospace}
    .status-present{background:rgba(16,185,129,.1);color:var(--accent2);border:1px solid rgba(16,185,129,.2)}
    .status-late{background:rgba(245,158,11,.1);color:var(--accent);border:1px solid rgba(245,158,11,.2)}
    .status-absent{background:rgba(239,68,68,.1);color:#fca5a5;border:1px solid rgba(239,68,68,.2)}
    .status-inactive{background:rgba(100,116,139,.1);color:var(--muted);border:1px solid rgba(100,116,139,.2)}

    /* ── EMPTY STATE ── */
    .empty-state{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:60px 24px;gap:14px;color:var(--muted)}
    .empty-state svg{width:44px;height:44px;stroke:var(--muted);opacity:.45}
    .empty-state p{font-size:.95rem;font-weight:500}
    .empty-state span{font-size:.78rem;font-family:'JetBrains Mono',monospace;opacity:.6;text-align:center}

    .loading-state{display:flex;align-items:center;justify-content:center;padding:40px;gap:10px;color:var(--muted);font-family:'JetBrains Mono',monospace;font-size:.82rem}
    .spinner{width:18px;height:18px;border:2px solid var(--border);border-top-color:var(--accent);border-radius:50%;animation:spin .6s linear infinite;flex-shrink:0}
    @keyframes spin{to{transform:rotate(360deg)}}

    /* ── PRINT STYLES ── */
    @media print {
      body::before,body::after{display:none!important}
      .sidebar,.topbar,.filter-card,.btn-print,.btn-apply,.btn-reset,.no-print{display:none!important}
      .main{margin-left:0!important}
      .page-content{padding:0!important}
      .print-header{display:block!important}
      .summary-grid{grid-template-columns:repeat(5,1fr)!important}
      .table-card{border:1px solid #ddd!important;border-radius:0!important}
      table.rep thead th{background:#f3f4f6!important;color:#374151!important;border-bottom:2px solid #d1d5db!important}
      table.rep tbody tr{border-bottom:1px solid #e5e7eb!important}
      table.rep td,table.rep thead th{color:#111!important}
      .drv-id-badge{background:#fef3c7!important;color:#92400e!important;border-color:#fcd34d!important}
      .status-present{background:#d1fae5!important;color:#065f46!important;border-color:#6ee7b7!important}
      .status-late{background:#fef3c7!important;color:#92400e!important;border-color:#fcd34d!important}
      .status-absent{background:#fee2e2!important;color:#991b1b!important;border-color:#fca5a5!important}
      .status-inactive{background:#f3f4f6!important;color:#374151!important;border-color:#d1d5db!important}
      .sum-card{border:1px solid #d1d5db!important;background:#fff!important}
      .sum-label{color:#6b7280!important}
      .sum-val{color:#111!important}
      .tbl-search,.tbl-badge,.table-search-wrap{display:none!important}
    }
    .print-header{display:none;margin-bottom:18px;padding-bottom:14px;border-bottom:2px solid #d1d5db}
    .print-header h2{font-size:1.3rem;font-weight:700;color:#111;font-family:'Bebas Neue',sans-serif;letter-spacing:.04em}
    .print-header p{font-size:.82rem;color:#6b7280;margin-top:4px}
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
      <span class="nav-badge"><?= $totalDrivers ?></span>
    </a>
    <div class="nav-section-label">Operations</div>
    <a class="nav-item" href="attendance.php">
      <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><polyline points="9 16 11 18 15 14"/></svg>
      Attendance
    </a>
    <div class="nav-section-label">Reports</div>
    <a class="nav-item active" href="reports.php">
      <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg>
      Reports
    </a>
  </nav>

  <div class="sidebar-footer">
    <button class="logout-btn" onclick="doLogout()">
      <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Log Out
    </button>
  </div>
</aside>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div class="topbar-left">
      <h1>Attendance Reports</h1>
      <p id="currentDate">Loading...</p>
    </div>
    <div class="topbar-right">
      <span class="topbar-time" id="liveTime">--:--:--</span>
      <div class="topbar-avatar"><?= strtoupper(substr($adminName,0,1)) ?></div>
    </div>
  </div>

  <div class="page-content">

    <!-- Print Header (hidden on screen, shown on print) -->
    <div class="print-header">
      <h2>TrikScan — Attendance Report</h2>
      <p id="printSubtitle">Generated: <?= date('F d, Y \a\t h:i A') ?></p>
    </div>

    <!-- Summary Cards -->
    <div class="summary-grid">
      <div class="sum-card">
        <div class="sum-label">Total</div>
        <div class="sum-val v-blue" id="sumTotal">—</div>
      </div>
      <div class="sum-card">
        <div class="sum-label">Present</div>
        <div class="sum-val v-green" id="sumPresent">—</div>
      </div>
      <div class="sum-card">
        <div class="sum-label">Late</div>
        <div class="sum-val v-amber" id="sumLate">—</div>
      </div>
      <div class="sum-card">
        <div class="sum-label">Absent</div>
        <div class="sum-val v-red" id="sumAbsent">—</div>
      </div>
      <div class="sum-card">
        <div class="sum-label">Inactive</div>
        <div class="sum-val v-muted" id="sumInactive">—</div>
      </div>
    </div>

    <!-- Filter Card -->
    <div class="filter-card no-print">
      <div class="filter-card-title">
        <svg viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
        Filter Report
      </div>
      <div class="filter-row">

        <!-- Filter Type Tabs -->
        <div class="filter-group">
          <label>Filter By</label>
          <div class="filter-tabs">
            <button class="f-tab active" data-type="date"  onclick="setFType('date')">Date</button>
            <button class="f-tab"        data-type="month" onclick="setFType('month')">Month</button>
            <button class="f-tab"        data-type="year"  onclick="setFType('year')">Year</button>
            <button class="f-tab"        data-type="day"   onclick="setFType('day')">Day</button>
          </div>
        </div>

        <!-- Dynamic value input -->
        <div class="filter-group">
          <label id="fValueLabel">Date</label>
          <div id="fInputWrap">
            <input type="date"  id="fDate"  class="f-input" value="<?= $today ?>" onchange="applyFilter()"/>
            <input type="month" id="fMonth" class="f-input" value="<?= date('Y-m') ?>" onchange="applyFilter()" style="display:none"/>
            <select id="fYear" class="f-input" onchange="applyFilter()" style="display:none">
              <?php for($y=date('Y');$y>=2020;$y--): ?>
                <option value="<?=$y?>" <?=$y==date('Y')?'selected':''?>><?=$y?></option>
              <?php endfor; ?>
            </select>
            <select id="fDay" class="f-input" onchange="applyFilter()" style="display:none">
              <option value="1">Sunday</option>
              <option value="2">Monday</option>
              <option value="3">Tuesday</option>
              <option value="4">Wednesday</option>
              <option value="5">Thursday</option>
              <option value="6">Friday</option>
              <option value="7">Saturday</option>
            </select>
          </div>
        </div>

        <!-- Status filter -->
        <div class="filter-group">
          <label>Status</label>
          <select id="fStatus" class="f-select" onchange="applyFilter()">
            <option value="all">All Status</option>
            <option value="Present">Present</option>
            <option value="Late">Late</option>
            <option value="Absent">Absent</option>
            <option value="Inactive">Inactive</option>
          </select>
        </div>

        <!-- Actions -->
        <div class="filter-group">
          <label style="visibility:hidden">Actions</label>
          <div style="display:flex;gap:8px;align-items:center">
            <button class="btn-apply" onclick="applyFilter()">
              <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              Apply
            </button>
            <button class="btn-reset" onclick="resetFilter()">
              <svg viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
              Today
            </button>
            <button class="btn-print" onclick="printReport()">
              <svg viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
              Print / Save PDF
            </button>
          </div>
        </div>

      </div>
    </div>

    <!-- Report Table -->
    <div class="table-card">
      <div class="table-header">
        <div>
          <div class="table-title">Attendance Records</div>
          <div class="table-sub" id="reportSub">Today — <?= date('F d, Y') ?></div>
        </div>
        <div style="display:flex;align-items:center;gap:10px;" class="table-search-wrap">
          <input type="text" class="tbl-search" placeholder="Search driver..." oninput="searchTable(this.value)"/>
          <span class="tbl-badge" id="rowCountBadge">Loading...</span>
        </div>
      </div>
      <div style="overflow-x:auto">
        <table class="rep" id="reportTable">
          <thead>
            <tr>
              <th>#</th>
              <th>Driver ID</th>
              <th>Driver Name</th>
              <th class="date-col" style="display:none">Date</th>
              <th>Time In</th>
              <th>Time Out</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody id="reportTbody">
            <tr><td colspan="7"><div class="loading-state"><div class="spinner"></div>Loading data...</div></td></tr>
          </tbody>
        </table>
      </div>
    </div>

  </div><!-- end page-content -->
</div><!-- end main -->

<script>
// ── Live Clock ──
function updateClock(){
  const now = new Date();
  document.getElementById('liveTime').textContent = now.toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
  document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US',{weekday:'long',month:'long',day:'numeric',year:'numeric'});
}
setInterval(updateClock,1000); updateClock();

// ── Filter state ──
let currentFType = 'date';

function setFType(type) {
  currentFType = type;
  document.querySelectorAll('.f-tab').forEach(t=>t.classList.toggle('active',t.dataset.type===type));
  const labels = {date:'Date',month:'Month',year:'Year',day:'Day of Week'};
  document.getElementById('fValueLabel').textContent = labels[type];
  document.getElementById('fDate').style.display  = type==='date'  ? '' : 'none';
  document.getElementById('fMonth').style.display = type==='month' ? '' : 'none';
  document.getElementById('fYear').style.display  = type==='year'  ? '' : 'none';
  document.getElementById('fDay').style.display   = type==='day'   ? '' : 'none';
  applyFilter();
}

function getFValue() {
  switch(currentFType){
    case 'date':  return document.getElementById('fDate').value;
    case 'month': return document.getElementById('fMonth').value;
    case 'year':  return document.getElementById('fYear').value;
    case 'day':   return document.getElementById('fDay').value;
  }
}

function applyFilter() {
  const val    = getFValue();
  const status = document.getElementById('fStatus').value;
  if (!val) return;

  document.getElementById('reportTbody').innerHTML = `<tr><td colspan="7"><div class="loading-state"><div class="spinner"></div>Loading data...</div></td></tr>`;
  document.getElementById('rowCountBadge').textContent = '...';

  // Update subtitle
  const dayNames = {1:'Sunday',2:'Monday',3:'Tuesday',4:'Wednesday',5:'Thursday',6:'Friday',7:'Saturday'};
  let subText = '';
  if (currentFType==='date') {
    const d = new Date(val+'T00:00:00');
    subText = d.toLocaleDateString('en-US',{weekday:'long',month:'long',day:'numeric',year:'numeric'});
  } else if (currentFType==='month') {
    const [y,m]=val.split('-');
    subText = new Date(y,m-1).toLocaleDateString('en-US',{month:'long',year:'numeric'});
  } else if (currentFType==='year') {
    subText = 'Year '+val;
  } else {
    subText = 'Every '+dayNames[val];
  }
  if (status!=='all') subText += ' · '+status+' only';
  document.getElementById('reportSub').textContent = subText;
  document.getElementById('printSubtitle').textContent = 'Filter: '+subText+' — Generated: '+new Date().toLocaleString('en-US');

  // Show date column for non-date filters
  const showDateCol = currentFType !== 'date';
  document.querySelectorAll('.date-col').forEach(el=>el.style.display=showDateCol?'':'none');

  const fd = new FormData();
  fd.append('action','filter_report');
  fd.append('filter_type',currentFType);
  fd.append('filter_value',val);
  fd.append('status_filter',status);

  fetch(window.location.href,{method:'POST',body:fd})
    .then(r=>r.json())
    .then(data=>{
      if (!data.success) { showError(); return; }
      updateSummary(data.summary);
      renderRows(data.rows, showDateCol);
    })
    .catch(showError);
}

function updateSummary(s) {
  document.getElementById('sumTotal').textContent   = s?.Total   ?? 0;
  document.getElementById('sumPresent').textContent = s?.Present ?? 0;
  document.getElementById('sumLate').textContent    = s?.Late    ?? 0;
  document.getElementById('sumAbsent').textContent  = s?.Absent  ?? 0;
  document.getElementById('sumInactive').textContent= s?.Inactive?? 0;
}

function renderRows(rows, showDate) {
  const tbody = document.getElementById('reportTbody');
  document.getElementById('rowCountBadge').textContent = rows.length + ' record(s)';
  if (!rows.length) {
    tbody.innerHTML = `<tr><td colspan="7">
      <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <p>Walang resulta</p>
        <span>No attendance records found for the selected filter.</span>
      </div>
    </td></tr>`;
    return;
  }
  let html = '';
  rows.forEach((r,i)=>{
    const dateCell = showDate ? `<td class="date-col tval">${r.date}</td>` : '';
    html += `<tr data-name="${esc(r.name).toLowerCase()}">
      <td>${i+1}</td>
      <td><span class="drv-id-badge">${esc(r.drv_id)}</span></td>
      <td style="font-weight:500">${esc(r.name)}</td>
      ${dateCell}
      <td class="${r.time_in==='—'?'tempty':'tval'}">${r.time_in}</td>
      <td class="${r.time_out==='—'?'tempty':'tval'}">${r.time_out}</td>
      <td><span class="status-badge ${r.badge}">${r.status}</span></td>
    </tr>`;
  });
  tbody.innerHTML = html;
}

function showError() {
  document.getElementById('reportTbody').innerHTML = `<tr><td colspan="7" style="text-align:center;padding:28px;color:#fca5a5;font-family:'JetBrains Mono',monospace;font-size:.82rem;">Failed to load data. Please try again.</td></tr>`;
  document.getElementById('rowCountBadge').textContent = 'Error';
}

function resetFilter() {
  currentFType = 'date';
  document.querySelectorAll('.f-tab').forEach(t=>t.classList.toggle('active',t.dataset.type==='date'));
  document.getElementById('fValueLabel').textContent = 'Date';
  document.getElementById('fDate').style.display=''; document.getElementById('fDate').value=new Date().toISOString().slice(0,10);
  document.getElementById('fMonth').style.display='none';
  document.getElementById('fYear').style.display='none';
  document.getElementById('fDay').style.display='none';
  document.getElementById('fStatus').value='all';
  document.querySelectorAll('.date-col').forEach(el=>el.style.display='none');
  applyFilter();
}

function searchTable(q) {
  q = q.toLowerCase().trim();
  document.querySelectorAll('#reportTbody tr[data-name]').forEach(row=>{
    row.style.display = (!q || row.dataset.name.includes(q) || row.textContent.toLowerCase().includes(q)) ? '' : 'none';
  });
}

function printReport() { window.print(); }

function esc(str) { const d=document.createElement('div');d.textContent=str;return d.innerHTML; }

function doLogout() { fetch('logout.php').then(()=>location.href='homepage.php').catch(()=>location.href='homepage.php'); }

// ── Init ──
window.addEventListener('load', () => {
  // Render initial PHP data
  const initData = <?= json_encode($initRows) ?>;
  updateSummary({
    Total:   initData.length,
    Present: initData.filter(r=>r.status==='Present').length,
    Late:    initData.filter(r=>r.status==='Late').length,
    Absent:  initData.filter(r=>r.status==='Absent').length,
    Inactive:initData.filter(r=>r.status==='Inactive').length,
  });
  renderRows(initData, false);
  document.getElementById('reportSub').textContent = 'Today — <?= date('F d, Y') ?>';
  document.getElementById('rowCountBadge').textContent = initData.length + ' record(s)';
});
</script>
</body>
</html>