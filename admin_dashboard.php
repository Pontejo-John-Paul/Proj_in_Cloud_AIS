<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: homepage.php');
    exit;
}
$adminName     = htmlspecialchars($_SESSION['admin_name']     ?? 'Administrator');
$adminUsername = htmlspecialchars($_SESSION['admin_username'] ?? 'admin');
$adminRole     = $_SESSION['admin_role'] ?? 'admin';
$adminEmail    = htmlspecialchars($_SESSION['admin_email']    ?? '');
$loginTime     = date('F d, Y \a\t h:i A', $_SESSION['login_time'] ?? time());

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

/* ── DB Connection ── */
$db = new mysqli('localhost', 'root', '', 'trikscan_db');
if ($db->connect_error) {
    die('Database connection failed: ' . $db->connect_error);
}

/* ── Create drivers table if not exists ── */
$db->query("
    CREATE TABLE IF NOT EXISTS drivers_registered_tb (
        id INT AUTO_INCREMENT PRIMARY KEY,
        driver_id VARCHAR(20) UNIQUE NOT NULL,
        firstname VARCHAR(100) NOT NULL,
        middlename VARCHAR(100),
        lastname VARCHAR(100) NOT NULL,
        contact_number VARCHAR(20),
        email VARCHAR(150),
        age INT,
        sex ENUM('Male','Female','Other'),
        date_joined DATE,
        picture VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

/* ── Handle AJAX: Add Driver ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    header('Content-Type: application/json');

    if ($_POST['action'] === 'add_driver') {
        $firstname  = $db->real_escape_string(trim($_POST['firstname']  ?? ''));
        $middlename = $db->real_escape_string(trim($_POST['middlename'] ?? ''));
        $lastname   = $db->real_escape_string(trim($_POST['lastname']   ?? ''));
        $contact    = $db->real_escape_string(trim($_POST['contact']    ?? ''));
        $email      = $db->real_escape_string(trim($_POST['email']      ?? ''));
        $age        = (int)($_POST['age']  ?? 0);
        $sex        = $db->real_escape_string($_POST['sex'] ?? '');
        $date_joined= $db->real_escape_string($_POST['date_joined'] ?? date('Y-m-d'));

        if (!$firstname || !$lastname) {
            echo json_encode(['success'=>false,'message'=>'Firstname and Lastname are required.']);
            exit;
        }

        /* Generate unique Driver ID: DRV-YYYYMMDD-XXXX */
        $prefix    = 'DRV-' . date('Ymd') . '-';
        $res       = $db->query("SELECT driver_id FROM drivers_registered_tb WHERE driver_id LIKE '{$prefix}%' ORDER BY id DESC LIMIT 1");
        $lastRow   = $res->fetch_assoc();
        $seq       = $lastRow ? (int)substr($lastRow['driver_id'], -4) + 1 : 1;
        $driver_id = $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);

        /* Handle picture upload */
        $picture = '';
        if (!empty($_FILES['picture']['name'])) {
            $upload_dir = 'uploads/drivers/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $ext      = strtolower(pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION));
            $allowed  = ['jpg','jpeg','png','gif','webp'];
            if (!in_array($ext, $allowed)) {
                echo json_encode(['success'=>false,'message'=>'Invalid image format. Use JPG, PNG, GIF, or WEBP.']);
                exit;
            }
            $filename = $driver_id . '.' . $ext;
            if (move_uploaded_file($_FILES['picture']['tmp_name'], $upload_dir . $filename)) {
                $picture = $upload_dir . $filename;
            }
        }

        $sql = "INSERT INTO drivers_registered_tb
                (driver_id, firstname, middlename, lastname, contact_number, email, age, sex, date_joined, picture)
                VALUES ('$driver_id','$firstname','$middlename','$lastname','$contact','$email',$age,'$sex','$date_joined','$picture')";

        if ($db->query($sql)) {
            $id = $db->insert_id;
            echo json_encode([
                'success'   => true,
                'message'   => 'Driver registered successfully.',
                'driver_id' => $driver_id,
                'db_id'     => $id,
                'firstname' => htmlspecialchars($firstname),
                'middlename'=> htmlspecialchars($middlename),
                'lastname'  => htmlspecialchars($lastname),
                'age'       => $age,
                'sex'       => $sex,
                'picture'   => $picture,
            ]);
        } else {
            echo json_encode(['success'=>false,'message'=>'Database error: '.$db->error]);
        }
        exit;
    }

    if ($_POST['action'] === 'filter_attendance') {
        $filterType = $db->real_escape_string($_POST['filter_type'] ?? 'date');
        $filterVal  = $db->real_escape_string($_POST['filter_value'] ?? date('Y-m-d'));
        $lateAfterQ = $db->query("SELECT late_after FROM attendance_schedule ORDER BY id DESC LIMIT 1")->fetch_assoc();
        $lateAfterT = $lateAfterQ ? $lateAfterQ['late_after'] : '09:00:00';

        switch ($filterType) {
            case 'date':  $whereDate = "a.date = '$filterVal'"; break;
            case 'month': $whereDate = "DATE_FORMAT(a.date,'%Y-%m') = '$filterVal'"; break;
            case 'year':  $whereDate = "YEAR(a.date) = '$filterVal'"; break;
            case 'day':   $whereDate = "DAYOFWEEK(a.date) = '$filterVal'"; break;
            default:      $whereDate = "a.date = '$filterVal'";
        }

        $rows = [];
        $res = $db->query("
            SELECT a.*, d.firstname, d.middlename, d.lastname, d.driver_id AS drv_id
            FROM attendance a
            JOIN drivers_registered_tb d ON d.id = a.driver_id
            WHERE $whereDate
            ORDER BY a.date DESC, a.time_in ASC
        ");
        while ($r = $res->fetch_assoc()) {
            $isLate = $r['time_in'] && (strtotime($r['time_in']) > strtotime($lateAfterT));
            if ($r['time_in'] && $r['time_out']) {
                $status = 'Inactive'; $badge = 'status-inactive';
            } elseif ($r['time_in'] && !$r['time_out']) {
                $status = $isLate ? 'Late' : 'Present';
                $badge  = $isLate ? 'status-late' : 'status-present';
            } else {
                $status = 'Absent'; $badge = 'status-absent';
            }
            $rows[] = [
                'drv_id'   => $r['drv_id'],
                'name'     => trim($r['firstname'].' '.($r['middlename'] ? $r['middlename'][0].'. ' : '').$r['lastname']),
                'date'     => $r['date'],
                'time_in'  => $r['time_in']  ? date('h:i A', strtotime($r['time_in']))  : '—',
                'time_out' => $r['time_out'] ? date('h:i A', strtotime($r['time_out'])) : '—',
                'status'   => $status,
                'badge'    => $badge,
            ];
        }

        if ($filterType === 'date') {
            $absentRes = $db->query("
                SELECT d.firstname, d.middlename, d.lastname, d.driver_id AS drv_id
                FROM drivers_registered_tb d
                LEFT JOIN attendance a ON a.driver_id = d.id AND $whereDate
                WHERE a.id IS NULL
                ORDER BY d.firstname ASC
            ");
            while ($dr = $absentRes->fetch_assoc()) {
                $rows[] = [
                    'drv_id'   => $dr['drv_id'],
                    'name'     => trim($dr['firstname'].' '.($dr['middlename'] ? $dr['middlename'][0].'. ' : '').$dr['lastname']),
                    'date'     => $filterVal,
                    'time_in'  => '—',
                    'time_out' => '—',
                    'status'   => 'Absent',
                    'badge'    => 'status-absent',
                ];
            }
        }

        echo json_encode(['success' => true, 'rows' => $rows]);
        exit;
    }

    if ($_POST['action'] === 'delete_driver') {
        $id = (int)($_POST['id'] ?? 0);
        /* Remove picture file if exists */
        $res = $db->query("SELECT picture FROM drivers_registered_tb WHERE id=$id");
        if ($row = $res->fetch_assoc()) {
            if ($row['picture'] && file_exists($row['picture'])) unlink($row['picture']);
        }
        if ($db->query("DELETE FROM drivers_registered_tb WHERE id=$id")) {
            echo json_encode(['success'=>true,'message'=>'Driver deleted.']);
        } else {
            echo json_encode(['success'=>false,'message'=>$db->error]);
        }
        exit;
    }
}

/* ── Fetch all drivers for initial load ── */
$driversResult = $db->query("SELECT * FROM drivers_registered_tb ORDER BY created_at DESC");
$drivers = [];
while ($row = $driversResult->fetch_assoc()) $drivers[] = $row;
$totalDrivers = count($drivers);

/* ── Fetch attendance schedule (late_after threshold) ── */
$schedRow = $db->query("SELECT late_after FROM attendance_schedule ORDER BY id DESC LIMIT 1")->fetch_assoc();
$lateAfter = $schedRow ? $schedRow['late_after'] : '09:00:00'; // fallback

/* ── Today's attendance stats ── */
$today = date('Y-m-d');

// Present Today: has time_in today (active OR inactive — both mean they showed up)
$presentResult = $db->query("
    SELECT a.*, d.firstname, d.middlename, d.lastname, d.driver_id AS drv_id
    FROM attendance a
    JOIN drivers_registered_tb d ON d.id = a.driver_id
    WHERE a.date = '$today' AND a.time_in IS NOT NULL
");
$presentDrivers = [];
while ($row = $presentResult->fetch_assoc()) $presentDrivers[] = $row;
$presentCount = count($presentDrivers);

// Absent Today: drivers with NO attendance record today, OR record exists but time_in is NULL
$absentResult = $db->query("
    SELECT d.id, d.firstname, d.middlename, d.lastname, d.driver_id AS drv_id
    FROM drivers_registered_tb d
    LEFT JOIN attendance a ON a.driver_id = d.id AND a.date = '$today'
    WHERE a.id IS NULL OR a.time_in IS NULL
");
$absentDrivers = [];
while ($row = $absentResult->fetch_assoc()) $absentDrivers[] = $row;
$absentCount = count($absentDrivers);

// Late Arrivals: time_in > late_after from schedule, and still active (no time_out yet)
$lateResult = $db->query("
    SELECT a.*, d.firstname, d.middlename, d.lastname, d.driver_id AS drv_id
    FROM attendance a
    JOIN drivers_registered_tb d ON d.id = a.driver_id
    WHERE a.date = '$today' AND a.time_in IS NOT NULL AND a.time_out IS NULL
      AND TIME(a.time_in) > '$lateAfter'
");
$lateDrivers = [];
while ($row = $lateResult->fetch_assoc()) $lateDrivers[] = $row;
$lateCount = count($lateDrivers);

// Build late driver IDs set for tagging in the attendance log
$lateDriverIds = array_column($lateDrivers, 'driver_id');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard — TrikScan Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet"/>
  <!-- QR Code Library -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
  <style>
    *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
    :root {
      --bg:#0a0f1c; --surface:#111827; --surface2:#1a2235; --surface3:#202c42;
      --accent:#f59e0b; --accent2:#10b981; --accent3:#3b82f6;
      --text:#f1f5f9; --muted:#64748b; --border:rgba(255,255,255,0.07);
      --sidebar-w: 260px;
    }
    html { scroll-behavior:smooth; }
    body { font-family:'DM Sans',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; display:flex; overflow-x:hidden; }

    body::before { content:''; position:fixed; inset:0; background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E"); pointer-events:none; z-index:9999; opacity:0.4; }

    /* ── SIDEBAR ── */
    .sidebar { width:var(--sidebar-w); background:var(--surface); border-right:1px solid var(--border); display:flex; flex-direction:column; position:fixed; inset:0 auto 0 0; z-index:100; transition:transform 0.3s ease; }
    .sidebar-logo { padding:24px 20px 20px; display:flex; align-items:center; gap:10px; border-bottom:1px solid var(--border); }
    .sidebar-logo-icon { width:34px; height:34px; background:var(--accent); border-radius:8px; display:grid; place-items:center; flex-shrink:0; }
    .sidebar-logo-icon svg { width:18px; height:18px; }
    .sidebar-logo-text { font-family:'Bebas Neue',sans-serif; font-size:1.5rem; letter-spacing:0.05em; }
    .sidebar-logo-text span { color:var(--accent); }
    .sidebar-user { margin:16px; padding:14px; background:var(--surface2); border-radius:12px; border:1px solid var(--border); }
    .sidebar-user-name { font-weight:600; font-size:0.9rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .sidebar-user-role { display:inline-block; margin-top:5px; font-size:0.68rem; font-weight:700; padding:2px 10px; border-radius:20px; font-family:'JetBrains Mono',monospace; letter-spacing:0.05em; background:rgba(255,255,255,0.05); border:1px solid; }
    .sidebar-user-email { font-size:0.75rem; color:var(--muted); margin-top:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .sidebar-nav { flex:1; padding:8px 12px; overflow-y:auto; }
    .nav-section-label { font-size:0.68rem; font-weight:700; color:var(--muted); letter-spacing:0.1em; text-transform:uppercase; padding:12px 8px 6px; font-family:'JetBrains Mono',monospace; }
    .nav-item { display:flex; align-items:center; gap:12px; padding:10px 12px; border-radius:8px; color:var(--muted); text-decoration:none; font-size:0.875rem; font-weight:500; transition:background 0.2s, color 0.2s; cursor:pointer; border:none; background:none; width:100%; text-align:left; }
    .nav-item svg { width:18px; height:18px; stroke:currentColor; fill:none; stroke-width:1.8; flex-shrink:0; }
    .nav-item:hover { background:var(--surface2); color:var(--text); }
    .nav-item.active { background:rgba(245,158,11,0.1); color:var(--accent); }
    .nav-item.active svg { stroke:var(--accent); }
    .nav-badge { margin-left:auto; background:var(--accent); color:#0a0f1c; font-size:0.68rem; font-weight:700; padding:2px 7px; border-radius:20px; font-family:'JetBrains Mono',monospace; }
    .sidebar-footer { padding:14px 12px; border-top:1px solid var(--border); }
    .logout-btn { display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:8px; color:var(--muted); font-size:0.875rem; font-weight:500; cursor:pointer; border:none; background:none; width:100%; transition:background 0.2s, color 0.2s; }
    .logout-btn svg { width:18px; height:18px; stroke:currentColor; fill:none; stroke-width:1.8; }
    .logout-btn:hover { background:rgba(239,68,68,0.08); color:#fca5a5; }

    /* ── MAIN ── */
    .main { margin-left:var(--sidebar-w); flex:1; display:flex; flex-direction:column; min-height:100vh; }
    .topbar { background:rgba(10,15,28,0.8); backdrop-filter:blur(16px); border-bottom:1px solid var(--border); padding:16px 28px; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:50; }
    .topbar-left h1 { font-family:'Bebas Neue',sans-serif; font-size:1.5rem; letter-spacing:0.04em; line-height:1; }
    .topbar-left p  { font-size:0.78rem; color:var(--muted); margin-top:2px; font-family:'JetBrains Mono',monospace; }
    .topbar-right { display:flex; align-items:center; gap:12px; }
    .topbar-time { font-family:'JetBrains Mono',monospace; font-size:0.8rem; color:var(--muted); }
    .topbar-avatar { width:36px; height:36px; border-radius:50%; background:var(--accent); display:grid; place-items:center; font-family:'Bebas Neue',sans-serif; font-size:1rem; color:#0a0f1c; font-weight:900; }

    /* ── PAGE SECTIONS ── */
    .page-section { display:none; padding:28px; flex:1; animation:fadeUp 0.4s ease; }
    .page-section.active { display:block; }
    @keyframes fadeUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }

    /* Welcome banner */
    .welcome-banner { background:linear-gradient(135deg, var(--surface2) 0%, var(--surface) 100%); border:1px solid var(--border); border-radius:16px; padding:28px 32px; margin-bottom:24px; position:relative; overflow:hidden; display:flex; justify-content:space-between; align-items:center; gap:20px; }
    .welcome-banner::after { content:''; position:absolute; top:-60px; right:-60px; width:240px; height:240px; background:radial-gradient(circle, rgba(245,158,11,0.1) 0%, transparent 65%); pointer-events:none; }
    .welcome-banner h2 { font-family:'Bebas Neue',sans-serif; font-size:1.8rem; letter-spacing:0.03em; line-height:1; }
    .welcome-banner h2 span { color:var(--accent); }
    .welcome-banner p { color:var(--muted); font-size:0.875rem; margin-top:6px; line-height:1.5; }
    .welcome-meta { font-size:0.75rem; color:var(--muted); margin-top:10px; font-family:'JetBrains Mono',monospace; }
    .banner-qr { opacity:0.12; flex-shrink:0; }
    .banner-qr svg { width:80px; height:80px; }

    /* Stats */
    .stats-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px; }
    .stat-card { background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:20px 22px; transition:border-color 0.3s, transform 0.2s; }
    .stat-card:hover { border-color:rgba(255,255,255,0.15); transform:translateY(-2px); }
    .stat-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px; }
    .stat-icon { width:38px; height:38px; border-radius:10px; display:grid; place-items:center; }
    .stat-icon svg { width:20px; height:20px; fill:none; stroke-width:1.8; }
    .stat-trend { font-size:0.72rem; font-weight:600; padding:3px 8px; border-radius:20px; font-family:'JetBrains Mono',monospace; }
    .trend-up   { background:rgba(16,185,129,0.1);  color:var(--accent2); }
    .trend-down { background:rgba(239,68,68,0.1);   color:#fca5a5; }
    .trend-neu  { background:rgba(100,116,139,0.1); color:var(--muted); }
    .stat-value { font-family:'Bebas Neue',sans-serif; font-size:2.4rem; letter-spacing:0.02em; line-height:1; }
    .stat-label { font-size:0.78rem; color:var(--muted); margin-top:4px; }
    .v-amber { color:var(--accent); }
    .v-green  { color:var(--accent2); }
    .v-blue   { color:var(--accent3); }
    .v-red    { color:#ef4444; }

    /* Two col */
    .two-col { display:grid; grid-template-columns:1fr 360px; gap:20px; margin-bottom:24px; }
    .chart-card { background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:22px; }
    .card-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
    .card-title { font-size:0.9rem; font-weight:600; }
    .card-sub   { font-size:0.75rem; color:var(--muted); margin-top:2px; }
    .card-badge { font-size:0.72rem; background:var(--surface2); border:1px solid var(--border); color:var(--muted); padding:4px 10px; border-radius:6px; font-family:'JetBrains Mono',monospace; }
    .bar-chart-wrap { display:flex; align-items:flex-end; gap:10px; height:120px; }
    .bar-col { flex:1; display:flex; flex-direction:column; align-items:center; gap:6px; height:100%; }
    .bar-col .bar-fill { width:100%; border-radius:4px 4px 0 0; transition:height 0.5s ease; min-height:4px; }
    .bar-col .bar-day  { font-size:0.68rem; color:var(--muted); font-family:'JetBrains Mono',monospace; }
    .bar-col .bar-pct  { font-size:0.68rem; color:var(--muted); }
    .activity-card { background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:22px; }
    .activity-item { display:flex; align-items:flex-start; gap:12px; padding:10px 0; border-bottom:1px solid var(--border); }
    .activity-item:last-child { border-bottom:none; }
    .activity-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; margin-top:5px; }
    .activity-body { flex:1; }
    .activity-title { font-size:0.82rem; font-weight:500; }
    .activity-time  { font-size:0.72rem; color:var(--muted); margin-top:2px; font-family:'JetBrains Mono',monospace; }

    /* Table */
    .table-card { background:var(--surface); border:1px solid var(--border); border-radius:14px; overflow:hidden; margin-bottom:24px; }
    .table-topbar { padding:18px 22px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; }
    .table-scroll { overflow-x:auto; }
    table { width:100%; border-collapse:collapse; }
    thead th { padding:12px 18px; text-align:left; font-size:0.72rem; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:0.08em; font-family:'JetBrains Mono',monospace; border-bottom:1px solid var(--border); white-space:nowrap; }
    tbody td { padding:13px 18px; font-size:0.85rem; border-bottom:1px solid rgba(255,255,255,0.03); }
    tbody tr:last-child td { border-bottom:none; }
    tbody tr:hover td { background:var(--surface2); }
    .status-badge { display:inline-block; font-size:0.72rem; font-weight:700; padding:3px 10px; border-radius:20px; font-family:'JetBrains Mono',monospace; }
    .status-present { background:rgba(16,185,129,0.1);  color:var(--accent2); border:1px solid rgba(16,185,129,0.2); }
    .status-late    { background:rgba(245,158,11,0.1);  color:var(--accent);  border:1px solid rgba(245,158,11,0.2); }
    .status-absent  { background:rgba(239,68,68,0.1);   color:#fca5a5;        border:1px solid rgba(239,68,68,0.2); }
    .status-inactive{ background:rgba(100,116,139,0.1); color:var(--muted);   border:1px solid rgba(100,116,139,0.2); }

    /* ── ATTENDANCE FILTER ── */
    .att-filter-tabs { display:flex; gap:4px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:3px; }
    .att-tab { background:none; border:none; color:var(--muted); font-size:0.75rem; font-weight:600; padding:5px 12px; border-radius:6px; cursor:pointer; font-family:'JetBrains Mono',monospace; letter-spacing:0.04em; transition:background 0.2s, color 0.2s; }
    .att-tab:hover { color:var(--text); }
    .att-tab.active { background:var(--accent); color:#0a0f1c; }
    .att-filter-input { background:var(--surface2); border:1px solid var(--border); border-radius:8px; color:var(--text); font-family:'JetBrains Mono',monospace; font-size:0.78rem; padding:6px 10px; outline:none; cursor:pointer; transition:border-color 0.2s; }
    .att-filter-input:focus { border-color:var(--accent); }
    .att-filter-input option { background:var(--surface2); }
    .att-reset-btn { display:inline-flex; align-items:center; gap:4px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; color:var(--muted); font-size:0.75rem; font-weight:600; padding:6px 12px; cursor:pointer; font-family:'JetBrains Mono',monospace; transition:background 0.2s, color 0.2s, border-color 0.2s; }
    .att-reset-btn:hover { background:var(--surface3); color:var(--text); border-color:rgba(255,255,255,0.15); }
    .att-empty-state { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:48px 24px; gap:14px; color:var(--muted); }
    .att-empty-state svg { width:40px; height:40px; stroke:var(--muted); opacity:0.5; }
    .att-empty-state p { font-size:0.9rem; font-weight:500; }
    .att-empty-state span { font-size:0.78rem; font-family:'JetBrains Mono',monospace; opacity:0.6; }
    .att-loading { text-align:center; padding:32px; color:var(--muted); font-size:0.85rem; font-family:'JetBrains Mono',monospace; }

    /* Quick actions */
    .quick-actions { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:24px; }
    .qa-btn { display:inline-flex; align-items:center; gap:8px; padding:10px 20px; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer; border:1px solid var(--border); background:var(--surface); color:var(--text); transition:background 0.2s, border-color 0.2s, transform 0.2s; text-decoration:none; }
    .qa-btn svg { width:16px; height:16px; fill:none; stroke:currentColor; stroke-width:1.8; }
    .qa-btn:hover { background:var(--surface2); border-color:rgba(255,255,255,0.15); transform:translateY(-1px); }
    .qa-btn.primary { background:var(--accent); border-color:var(--accent); color:#0a0f1c; }
    .qa-btn.primary:hover { opacity:0.9; }
    .qa-btn.danger { background:rgba(239,68,68,0.1); border-color:rgba(239,68,68,0.3); color:#fca5a5; }
    .qa-btn.danger:hover { background:rgba(239,68,68,0.2); }

    /* ════════════════════════════════
       DRIVER MANAGEMENT SECTION
    ════════════════════════════════ */
    .dm-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
    .dm-header-left h2 { font-family:'Bebas Neue',sans-serif; font-size:1.9rem; letter-spacing:0.04em; }
    .dm-header-left p  { color:var(--muted); font-size:0.82rem; margin-top:3px; }
    .dm-search { display:flex; align-items:center; gap:10px; }
    .dm-search input { background:var(--surface); border:1px solid var(--border); color:var(--text); padding:9px 14px 9px 38px; border-radius:8px; font-size:0.85rem; font-family:'DM Sans',sans-serif; outline:none; width:240px; transition:border-color 0.2s; }
    .dm-search input:focus { border-color:var(--accent); }
    .dm-search-wrap { position:relative; }
    .dm-search-wrap svg { position:absolute; left:10px; top:50%; transform:translateY(-50%); width:16px; height:16px; stroke:var(--muted); fill:none; stroke-width:1.8; pointer-events:none; }

    /* Driver Table */
    .driver-table-wrap { background:var(--surface); border:1px solid var(--border); border-radius:14px; overflow:hidden; margin-bottom:24px; }
    .driver-table-topbar { padding:18px 22px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; }
    .driver-table tbody tr { cursor:pointer; }
    .driver-avatar { width:34px; height:34px; border-radius:50%; object-fit:cover; background:var(--surface3); display:flex; align-items:center; justify-content:center; font-family:'Bebas Neue',sans-serif; font-size:0.95rem; color:var(--accent); border:1px solid var(--border); overflow:hidden; flex-shrink:0; }
    .driver-avatar img { width:100%; height:100%; object-fit:cover; }
    .driver-name-cell { display:flex; align-items:center; gap:10px; }
    .driver-id-badge { font-family:'JetBrains Mono',monospace; font-size:0.72rem; background:rgba(245,158,11,0.08); border:1px solid rgba(245,158,11,0.2); color:var(--accent); padding:2px 8px; border-radius:4px; }
    .del-btn { display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; border-radius:6px; border:1px solid rgba(239,68,68,0.25); background:rgba(239,68,68,0.06); color:#fca5a5; cursor:pointer; transition:all 0.2s; }
    .del-btn:hover { background:rgba(239,68,68,0.18); border-color:rgba(239,68,68,0.5); }
    .del-btn svg { width:14px; height:14px; stroke:currentColor; fill:none; stroke-width:1.8; }
    .id-card-btn { display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; border-radius:6px; border:1px solid rgba(59,130,246,0.25); background:rgba(59,130,246,0.06); color:#93c5fd; cursor:pointer; transition:all 0.2s; margin-right:4px; }
    .id-card-btn:hover { background:rgba(59,130,246,0.18); border-color:rgba(59,130,246,0.5); }
    .id-card-btn svg { width:14px; height:14px; stroke:currentColor; fill:none; stroke-width:1.8; }
    .empty-state { text-align:center; padding:60px 20px; color:var(--muted); }
    .empty-state svg { width:48px; height:48px; stroke:var(--muted); fill:none; stroke-width:1.2; margin:0 auto 16px; display:block; opacity:0.4; }
    .empty-state p { font-size:0.88rem; }

    /* ── ADD DRIVER MODAL ── */
    .modal-overlay { display:none; position:fixed; inset:0; z-index:2000; background:rgba(5,8,18,0.85); backdrop-filter:blur(8px); align-items:center; justify-content:center; padding:20px; }
    .modal-overlay.open { display:flex; animation:fadeIn 0.2s ease; }
    .modal-box { background:var(--surface); border:1px solid var(--border); border-radius:18px; width:100%; max-width:620px; max-height:90vh; overflow-y:auto; box-shadow:0 40px 100px rgba(0,0,0,0.7); }
    .modal-header { padding:24px 28px 0; display:flex; justify-content:space-between; align-items:flex-start; }
    .modal-header h3 { font-family:'Bebas Neue',sans-serif; font-size:1.6rem; letter-spacing:0.04em; }
    .modal-header p  { font-size:0.8rem; color:var(--muted); margin-top:2px; }
    .modal-close { background:none; border:none; cursor:pointer; color:var(--muted); font-size:1.2rem; padding:4px; transition:color 0.2s; }
    .modal-close:hover { color:var(--text); }
    .modal-body { padding:24px 28px; }
    .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .form-grid.full { grid-template-columns:1fr; }
    .form-group { display:flex; flex-direction:column; gap:6px; }
    .form-group label { font-size:0.75rem; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:0.07em; font-family:'JetBrains Mono',monospace; }
    .form-group input, .form-group select { background:var(--surface2); border:1px solid var(--border); color:var(--text); padding:10px 14px; border-radius:8px; font-size:0.875rem; font-family:'DM Sans',sans-serif; outline:none; transition:border-color 0.2s; }
    .form-group input:focus, .form-group select:focus { border-color:var(--accent); }
    .form-group input::placeholder { color:var(--muted); }
    .form-group select option { background:var(--surface2); }
    .form-optional { font-size:0.68rem; color:var(--muted); margin-left:4px; font-style:italic; }

    /* Picture upload */
    .upload-area { border:2px dashed var(--border); border-radius:10px; padding:20px; text-align:center; cursor:pointer; transition:border-color 0.2s, background 0.2s; position:relative; }
    .upload-area:hover, .upload-area.drag { border-color:var(--accent); background:rgba(245,158,11,0.04); }
    .upload-area svg { width:28px; height:28px; stroke:var(--muted); fill:none; margin:0 auto 8px; display:block; }
    .upload-area p { font-size:0.82rem; color:var(--muted); }
    .upload-area input[type=file] { position:absolute; inset:0; opacity:0; cursor:pointer; }
    .upload-preview { width:80px; height:80px; border-radius:50%; object-fit:cover; margin:0 auto 8px; display:block; border:2px solid var(--accent); }

    .modal-footer { padding:0 28px 24px; display:flex; gap:10px; justify-content:flex-end; }
    .btn { padding:10px 24px; border-radius:8px; font-size:0.88rem; font-weight:600; cursor:pointer; border:1px solid var(--border); font-family:'DM Sans',sans-serif; transition:all 0.2s; }
    .btn-cancel { background:var(--surface2); color:var(--text); }
    .btn-cancel:hover { background:var(--surface3); }
    .btn-primary { background:var(--accent); color:#0a0f1c; border-color:var(--accent); }
    .btn-primary:hover { opacity:0.9; }
    .btn-primary:disabled { opacity:0.5; cursor:not-allowed; }

    /* ── ID CARD MODAL ── */
    .id-modal-overlay { display:none; position:fixed; inset:0; z-index:3000; background:rgba(5,8,18,0.9); backdrop-filter:blur(10px); align-items:center; justify-content:center; padding:20px; }
    .id-modal-overlay.open { display:flex; animation:fadeIn 0.2s ease; }
    .id-modal-box { background:var(--surface); border:1px solid var(--border); border-radius:18px; padding:30px; max-width:520px; width:100%; box-shadow:0 40px 100px rgba(0,0,0,0.7); text-align:center; }
    .id-modal-box h3 { font-family:'Bebas Neue',sans-serif; font-size:1.4rem; letter-spacing:0.04em; margin-bottom:20px; }

    /* School ID Card */
    .id-card {
      width:320px; margin:0 auto 20px;
      background: linear-gradient(160deg, #1a2235 0%, #0d1525 100%);
      border: 2px solid var(--accent);
      border-radius:16px; overflow:hidden;
      box-shadow: 0 8px 32px rgba(0,0,0,0.6), 0 0 0 1px rgba(245,158,11,0.1);
      font-family:'DM Sans',sans-serif;
    }
    .id-card-head {
      background: linear-gradient(135deg, var(--accent) 0%, #d97706 100%);
      padding:12px 16px; display:flex; align-items:center; gap:10px;
    }
    .id-card-head-logo { width:28px; height:28px; background:#0a0f1c; border-radius:6px; display:grid; place-items:center; flex-shrink:0; }
    .id-card-head-logo svg { width:16px; height:16px; }
    .id-card-head-text { flex:1; text-align:left; }
    .id-card-head-text .org { font-family:'Bebas Neue',sans-serif; font-size:1.1rem; letter-spacing:0.06em; color:#0a0f1c; line-height:1; }
    .id-card-head-text .sub { font-size:0.62rem; color:rgba(10,15,28,0.7); font-weight:600; letter-spacing:0.05em; text-transform:uppercase; }

    .id-card-body { padding:20px 20px 16px; }
    .id-card-photo-wrap { width:80px; height:80px; border-radius:50%; border:3px solid var(--accent); overflow:hidden; margin:0 auto 14px; background:var(--surface3); display:flex; align-items:center; justify-content:center; }
    .id-card-photo-wrap img { width:100%; height:100%; object-fit:cover; }
    .id-card-photo-initial { font-family:'Bebas Neue',sans-serif; font-size:2rem; color:var(--accent); }
    .id-card-fullname { font-family:'Bebas Neue',sans-serif; font-size:1.15rem; letter-spacing:0.04em; color:var(--text); line-height:1.1; }
    .id-card-type { font-size:0.65rem; color:var(--accent); font-weight:700; text-transform:uppercase; letter-spacing:0.12em; margin-top:3px; font-family:'JetBrains Mono',monospace; }

    .id-card-info { display:grid; grid-template-columns:1fr 1fr; gap:6px; margin:14px 0 16px; text-align:left; }
    .id-info-item label { font-size:0.6rem; color:var(--muted); text-transform:uppercase; letter-spacing:0.1em; font-family:'JetBrains Mono',monospace; }
    .id-info-item span  { font-size:0.78rem; font-weight:600; color:var(--text); display:block; }

    .id-card-divider { height:1px; background:var(--border); margin:0 -0px; }

    .id-card-footer { display:flex; align-items:center; justify-content:space-between; padding:14px 0 0; gap:12px; }
    .id-card-id-block { text-align:left; }
    .id-card-id-block label { font-size:0.6rem; color:var(--muted); text-transform:uppercase; letter-spacing:0.1em; font-family:'JetBrains Mono',monospace; }
    .id-card-id-block span { font-family:'JetBrains Mono',monospace; font-size:0.75rem; color:var(--accent); font-weight:700; display:block; margin-top:2px; }

    .id-qr-wrap { width:72px; height:72px; background:#fff; border-radius:8px; padding:4px; flex-shrink:0; }
    .id-qr-wrap canvas { width:100% !important; height:100% !important; }

    .id-modal-actions { display:flex; gap:10px; justify-content:center; }

    /* Delete confirm */
    .confirm-overlay { display:none; position:fixed; inset:0; z-index:4000; background:rgba(5,8,18,0.8); backdrop-filter:blur(6px); align-items:center; justify-content:center; }
    .confirm-overlay.open { display:flex; animation:fadeIn 0.2s ease; }
    .confirm-box { background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:32px; max-width:360px; width:90%; text-align:center; box-shadow:0 32px 80px rgba(0,0,0,0.6); }
    .confirm-icon { width:52px; height:52px; background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.2); border-radius:50%; display:grid; place-items:center; margin:0 auto 16px; font-size:1.4rem; }
    .confirm-box h3 { font-family:'Bebas Neue',sans-serif; font-size:1.5rem; letter-spacing:0.04em; margin-bottom:8px; }
    .confirm-box p  { font-size:0.85rem; color:var(--muted); margin-bottom:24px; line-height:1.5; }
    .confirm-btns { display:flex; gap:10px; justify-content:center; }
    .confirm-btn { padding:10px 24px; border-radius:8px; font-size:0.88rem; font-weight:600; cursor:pointer; border:1px solid var(--border); font-family:'DM Sans',sans-serif; transition:all 0.2s; }
    .confirm-btn.cancel { background:var(--surface2); color:var(--text); }
    .confirm-btn.cancel:hover { background:var(--surface3); }
    .confirm-btn.logout { background:#ef4444; color:#fff; border-color:#ef4444; }
    .confirm-btn.logout:hover { background:#dc2626; }
    .confirm-btn.del-confirm { background:#ef4444; color:#fff; border-color:#ef4444; }
    .confirm-btn.del-confirm:hover { background:#dc2626; }

    /* Toast */
    .toast-container { position:fixed; top:16px; right:16px; z-index:9999; display:flex; flex-direction:column; gap:10px; pointer-events:none; }
    .toast { display:flex; align-items:flex-start; gap:10px; padding:12px 16px; border-radius:10px; font-size:0.83rem; line-height:1.5; min-width:260px; max-width:340px; pointer-events:all; box-shadow:0 8px 32px rgba(0,0,0,0.4); animation:toastIn 0.35s cubic-bezier(0.34,1.56,0.64,1) forwards; border:1px solid transparent; position:relative; }
    .toast.hiding { animation:toastOut 0.3s ease forwards; }
    .toast-success { background:rgba(16,185,129,0.12); border-color:rgba(16,185,129,0.3); color:#6ee7b7; }
    .toast-error   { background:rgba(239,68,68,0.12);  border-color:rgba(239,68,68,0.3);  color:#fca5a5; }
    .toast-info    { background:rgba(59,130,246,0.12); border-color:rgba(59,130,246,0.3); color:#93c5fd; }
    .toast-icon { font-size:1rem; flex-shrink:0; margin-top:1px; }
    .toast-body { flex:1; }
    .toast-title { font-weight:700; font-size:0.82rem; margin-bottom:2px; }
    .toast-msg   { font-size:0.8rem; opacity:0.85; }
    .toast-close { background:none; border:none; cursor:pointer; color:inherit; opacity:0.6; font-size:1rem; padding:0; flex-shrink:0; }
    .toast-progress { position:absolute; bottom:0; left:0; height:2px; border-radius:0 0 10px 10px; animation:progress 4s linear forwards; }
    .toast-success .toast-progress { background:var(--accent2); }
    .toast-error   .toast-progress { background:#ef4444; }
    .toast-info    .toast-progress { background:var(--accent3); }
    @keyframes toastIn  { from{opacity:0;transform:translateX(40px)} to{opacity:1;transform:translateX(0)} }
    @keyframes toastOut { from{opacity:1;transform:translateX(0)}    to{opacity:0;transform:translateX(40px)} }
    @keyframes progress { from{width:100%} to{width:0} }
    @keyframes fadeIn   { from{opacity:0} to{opacity:1} }

    /* Spinner */
    .spinner { display:inline-block; width:16px; height:16px; border:2px solid rgba(10,15,28,0.3); border-top-color:#0a0f1c; border-radius:50%; animation:spin 0.6s linear infinite; }
    @keyframes spin { to{ transform:rotate(360deg); } }

    @media (max-width:1100px) { .stats-grid { grid-template-columns:repeat(2,1fr); } .two-col { grid-template-columns:1fr; } }
    @media (max-width:768px)  { .sidebar { transform:translateX(-100%); } .main { margin-left:0; } .stats-grid { grid-template-columns:1fr 1fr; } .form-grid { grid-template-columns:1fr; } }
  </style>
</head>
<body>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<!-- Logout Confirm Modal -->
<div class="confirm-overlay" id="confirmOverlay">
  <div class="confirm-box">
    <div class="confirm-icon">🚪</div>
    <h3>Confirm Logout</h3>
    <p>Are you sure you want to sign out of TrikScan Admin?</p>
    <div class="confirm-btns">
      <button class="confirm-btn cancel" onclick="closeModal('confirmOverlay')">Cancel</button>
      <button class="confirm-btn logout" onclick="doLogout()">Yes, Log Out</button>
    </div>
  </div>
</div>

<!-- Delete Driver Confirm -->
<div class="confirm-overlay" id="deleteConfirmOverlay">
  <div class="confirm-box">
    <div class="confirm-icon">🗑️</div>
    <h3>Delete Driver</h3>
    <p id="deleteConfirmText">Are you sure you want to delete this driver? This action cannot be undone.</p>
    <div class="confirm-btns">
      <button class="confirm-btn cancel" onclick="closeModal('deleteConfirmOverlay')">Cancel</button>
      <button class="confirm-btn del-confirm" id="deleteConfirmBtn">Delete</button>
    </div>
  </div>
</div>

<!-- Add Driver Modal -->
<div class="modal-overlay" id="addDriverModal">
  <div class="modal-box">
    <div class="modal-header">
      <div>
        <h3>Register New Driver</h3>
        <p>Fill in the driver's information below</p>
      </div>
      <button class="modal-close" onclick="closeModal('addDriverModal')">✕</button>
    </div>
    <div class="modal-body">
      <div class="form-grid" style="margin-bottom:14px;">
        <div class="form-group">
          <label>Firstname <span style="color:#ef4444">*</span></label>
          <input type="text" id="drvFirstname" placeholder="Juan" required/>
        </div>
        <div class="form-group">
          <label>Middlename <span class="form-optional">(optional)</span></label>
          <input type="text" id="drvMiddlename" placeholder="Dela"/>
        </div>
        <div class="form-group">
          <label>Lastname <span style="color:#ef4444">*</span></label>
          <input type="text" id="drvLastname" placeholder="Cruz" required/>
        </div>
        <div class="form-group">
          <label>Contact Number</label>
          <input type="text" id="drvContact" placeholder="09XXXXXXXXX"/>
        </div>
        <div class="form-group">
          <label>Email Address</label>
          <input type="email" id="drvEmail" placeholder="juan@example.com"/>
        </div>
        <div class="form-group">
          <label>Age</label>
          <input type="number" id="drvAge" placeholder="30" min="18" max="99"/>
        </div>
        <div class="form-group">
          <label>Sex</label>
          <select id="drvSex">
            <option value="">— Select —</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
            <option value="Other">Other</option>
          </select>
        </div>
        <div class="form-group">
          <label>Date Joined</label>
          <input type="date" id="drvDateJoined"/>
        </div>
      </div>

      <div class="form-group" style="margin-bottom:0;">
        <label>Profile Picture <span class="form-optional">(optional)</span></label>
        <div class="upload-area" id="uploadArea">
          <img class="upload-preview" id="uploadPreview" src="" style="display:none;"/>
          <svg id="uploadIcon" viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          <p id="uploadText">Click or drag to upload photo</p>
          <input type="file" id="drvPicture" accept="image/*" onchange="previewPicture(this)"/>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-cancel" onclick="closeModal('addDriverModal')">Cancel</button>
      <button class="btn btn-primary" id="addDriverBtn" onclick="submitAddDriver()">
        <span id="addDriverBtnText">Register Driver</span>
      </button>
    </div>
  </div>
</div>

<!-- ID Card Modal -->
<div class="id-modal-overlay" id="idCardModal">
  <div class="id-modal-box">
    <h3>🪪 Driver ID Card</h3>
    <div class="id-card" id="idCardEl">
      <div class="id-card-head">
        <div class="id-card-head-logo">
          <svg viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round">
            <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
            <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="15" y="15" width="2" height="2"/>
            <rect x="19" y="15" width="2" height="2"/><rect x="15" y="19" width="2" height="2"/><rect x="19" y="19" width="2" height="2"/>
          </svg>
        </div>
        <div class="id-card-head-text">
          <div class="org">TrikScan</div>
          <div class="sub">Official Driver Identification Card</div>
        </div>
      </div>
      <div class="id-card-body">
        <div class="id-card-photo-wrap" id="idCardPhoto">
          <span class="id-card-photo-initial" id="idCardInitial">?</span>
        </div>
        <div class="id-card-fullname" id="idCardName">FULL NAME</div>
        <div class="id-card-type">Registered Driver</div>
        <div class="id-card-info">
          <div class="id-info-item">
            <label>Age</label>
            <span id="idCardAge">—</span>
          </div>
          <div class="id-info-item">
            <label>Sex</label>
            <span id="idCardSex">—</span>
          </div>
          <div class="id-info-item">
            <label>Date Joined</label>
            <span id="idCardDate">—</span>
          </div>
          <div class="id-info-item">
            <label>Status</label>
            <span style="color:var(--accent2)">Active</span>
          </div>
        </div>
        <div class="id-card-divider"></div>
        <div class="id-card-footer">
          <div class="id-card-id-block">
            <label>Driver ID</label>
            <span id="idCardDriverId">DRV-XXXXXXXX-0000</span>
          </div>
          <div class="id-qr-wrap" id="idQrCode"></div>
        </div>
      </div>
    </div>
    <div class="id-modal-actions">
      <button class="btn btn-cancel" onclick="closeModal('idCardModal')">Close</button>
      <button class="btn btn-primary" onclick="printIdCard()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:15px;height:15px;display:inline;vertical-align:middle;margin-right:6px;"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        Print / Save
      </button>
    </div>
  </div>
</div>

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
    <button class="nav-item active" id="nav-dashboard" onclick="switchSection('dashboard')">
      <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
      Dashboard
    </button>
    <button class="nav-item" id="nav-drivers" onclick="switchSection('drivers')">
      <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      Driver Management
    </button>
    <div class="nav-section-label">Operations</div>
    <a class="nav-item" href="attendance.php">
      <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><polyline points="9 16 11 18 15 14"/></svg>
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

<!-- MAIN CONTENT -->
<div class="main">
  <!-- Topbar -->
  <div class="topbar">
    <div class="topbar-left">
      <h1 id="topbarTitle">Admin Dashboard</h1>
      <p id="currentDate">Loading date...</p>
    </div>
    <div class="topbar-right">
      <span class="topbar-time" id="liveTime">--:--:--</span>
      <div class="topbar-avatar"><?= strtoupper(substr($adminName, 0, 1)) ?></div>
    </div>
  </div>

  <!-- ═══ SECTION: DASHBOARD ═══ -->
  <div class="page-section active" id="section-dashboard">
    <!-- Welcome Banner -->
    <div class="welcome-banner">
      <div>
        <h2>Welcome back, <span><?= explode(' ', $adminName)[0] ?></span>!</h2>
        <p>Here's what's happening with your fleet today.</p>
        <div class="welcome-meta">Logged in as <?= $adminUsername ?> &nbsp;·&nbsp; <?= $roleLabel ?> &nbsp;·&nbsp; Since <?= $loginTime ?></div>
      </div>
      <div class="banner-qr">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
          <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
          <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="15" y="15" width="2" height="2"/>
          <rect x="19" y="15" width="2" height="2"/><rect x="15" y="19" width="2" height="2"/><rect x="19" y="19" width="2" height="2"/>
        </svg>
      </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-icon" style="background:rgba(16,185,129,0.1);"><svg viewBox="0 0 24 24" stroke="var(--accent2)"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
          <span class="stat-trend trend-up">Today</span>
        </div>
        <div class="stat-value v-green"><?= $presentCount ?></div>
        <div class="stat-label">Present Today</div>
      </div>
      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-icon" style="background:rgba(239,68,68,0.1);"><svg viewBox="0 0 24 24" stroke="#ef4444"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="17" y1="8" x2="23" y2="14"/><line x1="23" y1="8" x2="17" y2="14"/></svg></div>
          <span class="stat-trend trend-down">Today</span>
        </div>
        <div class="stat-value v-red"><?= $absentCount ?></div>
        <div class="stat-label">Absent Today</div>
      </div>
      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-icon" style="background:rgba(245,158,11,0.1);"><svg viewBox="0 0 24 24" stroke="var(--accent)"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
          <span class="stat-trend trend-neu">Late &gt; <?= date('h:i A', strtotime($lateAfter)) ?></span>
        </div>
        <div class="stat-value v-amber"><?= $lateCount ?></div>
        <div class="stat-label">Late Arrivals</div>
      </div>
      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-icon" style="background:rgba(59,130,246,0.1);"><svg viewBox="0 0 24 24" stroke="var(--accent3)"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
          <span class="stat-trend trend-up" id="statTotalDrivers">▲ +<?= $totalDrivers ?></span>
        </div>
        <div class="stat-value v-blue" id="statDriverValue"><?= $totalDrivers ?></div>
        <div class="stat-label">Total Drivers</div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
      <button class="qa-btn primary" onclick="switchSection('drivers'); setTimeout(()=>openModal('addDriverModal'),300)">
        <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
        Add Driver
      </button>
      <button class="qa-btn" onclick="switchSection('drivers')">
        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/></svg>
        View Drivers
      </button>
      <button class="qa-btn" onclick="showToast('info','Coming Soon','Export module is under development.')">
        <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Export Report
      </button>
      <button class="qa-btn" onclick="showToast('success','Refreshed','Dashboard data has been refreshed.')">
        <svg viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
        Refresh Data
      </button>
    </div>

    <!-- Chart + Activity -->
    <div class="two-col">
      <div class="chart-card">
        <div class="card-header">
          <div><div class="card-title">Weekly Attendance</div><div class="card-sub">Mon – Sun attendance rate overview</div></div>
          <span class="card-badge">This Week</span>
        </div>
        <div class="bar-chart-wrap" id="barChart"></div>
      </div>
      <div class="activity-card">
        <div class="card-header">
          <div><div class="card-title">Recent Activity</div><div class="card-sub">Latest scan events</div></div>
        </div>
        <div class="activity-item"><div class="activity-dot" style="background:var(--accent2)"></div><div class="activity-body"><div class="activity-title">Juan D. Cruz — Route 7 scanned in</div><div class="activity-time">05:48 AM · Present</div></div></div>
        <div class="activity-item"><div class="activity-dot" style="background:var(--accent)"></div><div class="activity-body"><div class="activity-title">Maria Santos — Route 3 scanned in</div><div class="activity-time">06:12 AM · Late</div></div></div>
        <div class="activity-item"><div class="activity-dot" style="background:#ef4444"></div><div class="activity-body"><div class="activity-title">Pedro Reyes — Route 11 absent</div><div class="activity-time">No scan recorded</div></div></div>
        <div class="activity-item"><div class="activity-dot" style="background:var(--accent2)"></div><div class="activity-body"><div class="activity-title">Ana Ramos — Route 2 scanned in</div><div class="activity-time">05:55 AM · Present</div></div></div>
        <div class="activity-item"><div class="activity-dot" style="background:var(--accent2)"></div><div class="activity-body"><div class="activity-title">Carlo Mendoza — Route 5 scanned in</div><div class="activity-time">05:40 AM · Present</div></div></div>
      </div>
    </div>

    <!-- Attendance Table -->
    <div class="table-card">
      <div class="table-topbar" style="flex-wrap:wrap;gap:14px;">
        <div>
          <div class="card-title">Attendance Log</div>
          <div class="card-sub" id="attLogSub"><?= date('F d, Y') ?></div>
        </div>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
          <div class="att-filter-tabs">
            <button class="att-tab active" data-type="date"  onclick="setFilterType('date')">Date</button>
            <button class="att-tab"        data-type="month" onclick="setFilterType('month')">Month</button>
            <button class="att-tab"        data-type="year"  onclick="setFilterType('year')">Year</button>
            <button class="att-tab"        data-type="day"   onclick="setFilterType('day')">Day</button>
          </div>
          <div id="attFilterInput">
            <input type="date"  id="attDatePicker"  value="<?= date('Y-m-d') ?>" class="att-filter-input" onchange="applyAttFilter()" style="display:block;"/>
            <input type="month" id="attMonthPicker" value="<?= date('Y-m') ?>"   class="att-filter-input" onchange="applyAttFilter()" style="display:none;"/>
            <select id="attYearPicker" class="att-filter-input" onchange="applyAttFilter()" style="display:none;">
              <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                <option value="<?= $y ?>" <?= $y == date('Y') ? 'selected' : '' ?>><?= $y ?></option>
              <?php endfor; ?>
            </select>
            <select id="attDayPicker" class="att-filter-input" onchange="applyAttFilter()" style="display:none;">
              <option value="1">Sunday</option>
              <option value="2">Monday</option>
              <option value="3">Tuesday</option>
              <option value="4">Wednesday</option>
              <option value="5">Thursday</option>
              <option value="6">Friday</option>
              <option value="7">Saturday</option>
            </select>
          </div>
          <button class="att-reset-btn" onclick="resetAttFilter()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px;vertical-align:middle;margin-right:4px;"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
            Today
          </button>
          <span class="card-badge" id="attLiveBadge">Live</span>
        </div>
      </div>
      <div class="table-scroll">
        <table>
          <thead><tr><th>#</th><th>Driver ID</th><th>Driver Name</th><th id="attDateHeader" style="display:none;">Date</th><th>Time In</th><th>Time Out</th><th>Status</th></tr></thead>
          <tbody id="attLogTbody">
          <?php
          /* Build combined log: Present (with late detection) + Absent */
          $logRows = [];
          $rowNum = 1;

          // 1. All drivers that have an attendance record today
          $allTodayResult = $db->query("
              SELECT a.*, d.firstname, d.middlename, d.lastname, d.driver_id AS drv_id
              FROM attendance a
              JOIN drivers_registered_tb d ON d.id = a.driver_id
              WHERE a.date = '$today'
              ORDER BY a.time_in ASC
          ");
          $todayAttendanceIds = [];
          while ($aRow = $allTodayResult->fetch_assoc()) {
              $todayAttendanceIds[] = $aRow['driver_id'];
              $isLate = $aRow['time_in'] && (strtotime($aRow['time_in']) > strtotime($lateAfter));
              if ($aRow['time_in'] && $aRow['time_out']) {
                  // Has both time_in and time_out → done for the day (Inactive)
                  $statusLabel = 'Inactive';
                  $badgeClass  = 'status-inactive';
              } elseif ($aRow['time_in'] && !$aRow['time_out']) {
                  // Has time_in but no time_out yet → still on duty
                  $statusLabel = $isLate ? 'Late' : 'Present';
                  $badgeClass  = $isLate ? 'status-late' : 'status-present';
              } else {
                  // No time_in → Absent
                  $statusLabel = 'Absent';
                  $badgeClass  = 'status-absent';
              }
              $fullName = htmlspecialchars(trim($aRow['firstname'] . ' ' . ($aRow['middlename'] ? $aRow['middlename'][0].'. ' : '') . $aRow['lastname']));
              $timeIn  = $aRow['time_in']  ? date('h:i A', strtotime($aRow['time_in']))  : '—';
              $timeOut = $aRow['time_out'] ? date('h:i A', strtotime($aRow['time_out'])) : '—';
              echo "<tr>
                <td>{$rowNum}</td>
                <td><span class='driver-id-badge' style='font-size:0.72rem;'>" . htmlspecialchars($aRow['drv_id']) . "</span></td>
                <td>{$fullName}</td>
                <td style=\"font-family:'JetBrains Mono',monospace;font-size:0.8rem;\">{$timeIn}</td>
                <td style=\"font-family:'JetBrains Mono',monospace;font-size:0.8rem;\">{$timeOut}</td>
                <td><span class='status-badge {$badgeClass}'>{$statusLabel}</span></td>
              </tr>";
              $rowNum++;
          }

          // 2. Drivers with NO attendance record today → Absent
          $absentTodayResult = $db->query("
              SELECT d.id, d.firstname, d.middlename, d.lastname, d.driver_id AS drv_id
              FROM drivers_registered_tb d
              LEFT JOIN attendance a ON a.driver_id = d.id AND a.date = '$today'
              WHERE a.id IS NULL
              ORDER BY d.firstname ASC
          ");
          while ($dRow = $absentTodayResult->fetch_assoc()) {
              $fullName = htmlspecialchars(trim($dRow['firstname'] . ' ' . ($dRow['middlename'] ? $dRow['middlename'][0].'. ' : '') . $dRow['lastname']));
              echo "<tr>
                <td>{$rowNum}</td>
                <td><span class='driver-id-badge' style='font-size:0.72rem;'>" . htmlspecialchars($dRow['drv_id']) . "</span></td>
                <td>{$fullName}</td>
                <td style=\"font-family:'JetBrains Mono',monospace;font-size:0.8rem;\">—</td>
                <td style=\"font-family:'JetBrains Mono',monospace;font-size:0.8rem;\">—</td>
                <td><span class='status-badge status-absent'>Absent</span></td>
              </tr>";
              $rowNum++;
          }

          if ($rowNum === 1): ?>
            <tr><td colspan="6" style="text-align:center;padding:28px;color:var(--muted);font-size:0.85rem;">No drivers registered yet.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div><!-- end dashboard -->

  <!-- ═══ SECTION: DRIVER MANAGEMENT ═══ -->
  <div class="page-section" id="section-drivers">

    <div class="dm-header">
      <div class="dm-header-left">
        <h2>Driver Management</h2>
        <p>Manage all registered tricycle drivers in the system</p>
      </div>
      <div class="dm-search">
        <div class="dm-search-wrap">
          <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" id="driverSearch" placeholder="Search drivers..." oninput="filterDrivers(this.value)"/>
        </div>
        <button class="qa-btn primary" onclick="openModal('addDriverModal')">
          <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
          Add Driver
        </button>
      </div>
    </div>

    <div class="driver-table-wrap">
      <div class="driver-table-topbar">
        <div>
          <div class="card-title">Registered Drivers</div>
          <div class="card-sub"><span id="driverCountLabel"><?= $totalDrivers ?></span> driver(s) found</div>
        </div>
        <span class="card-badge" id="driverTableBadge"><?= $totalDrivers ?> Total</span>
      </div>
      <div class="table-scroll">
        <table class="driver-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Driver</th>
              <th>Driver ID</th>
              <th>Contact</th>
              <th>Age</th>
              <th>Sex</th>
              <th>Date Joined</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="driverTableBody">
            <?php if (empty($drivers)): ?>
            <tr id="emptyRow">
              <td colspan="8">
                <div class="empty-state">
                  <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                  <p>No drivers registered yet. Click <strong>Add Driver</strong> to get started.</p>
                </div>
              </td>
            </tr>
            <?php else: ?>
              <?php foreach ($drivers as $i => $d): ?>
              <tr data-id="<?= $d['id'] ?>" data-name="<?= strtolower($d['firstname'].' '.$d['middlename'].' '.$d['lastname']) ?>">
                <td><?= $i + 1 ?></td>
                <td>
                  <div class="driver-name-cell">
                    <div class="driver-avatar">
                      <?php if ($d['picture'] && file_exists($d['picture'])): ?>
                        <img src="<?= htmlspecialchars($d['picture']) ?>" alt="photo"/>
                      <?php else: ?>
                        <?= strtoupper(substr($d['firstname'],0,1).substr($d['lastname'],0,1)) ?>
                      <?php endif; ?>
                    </div>
                    <div>
                      <div style="font-weight:600;"><?= htmlspecialchars($d['firstname'].' '.($d['middlename'] ? $d['middlename'][0].'. ' : '').$d['lastname']) ?></div>
                      <div style="font-size:0.75rem;color:var(--muted);"><?= htmlspecialchars($d['email'] ?: '—') ?></div>
                    </div>
                  </div>
                </td>
                <td><span class="driver-id-badge"><?= htmlspecialchars($d['driver_id']) ?></span></td>
                <td style="font-family:'JetBrains Mono',monospace;font-size:0.8rem;"><?= htmlspecialchars($d['contact_number'] ?: '—') ?></td>
                <td><?= $d['age'] ?: '—' ?></td>
                <td><?= htmlspecialchars($d['sex'] ?: '—') ?></td>
                <td style="font-family:'JetBrains Mono',monospace;font-size:0.8rem;"><?= $d['date_joined'] ? date('M d, Y', strtotime($d['date_joined'])) : '—' ?></td>
                <td>
                  <button class="id-card-btn" title="View ID Card"
                    onclick="showIdCard(<?= htmlspecialchars(json_encode([
                      'id'          => $d['id'],
                      'driver_id'   => $d['driver_id'],
                      'firstname'   => $d['firstname'],
                      'middlename'  => $d['middlename'],
                      'lastname'    => $d['lastname'],
                      'age'         => $d['age'],
                      'sex'         => $d['sex'],
                      'date_joined' => $d['date_joined'],
                      'picture'     => $d['picture'],
                    ]), ENT_QUOTES) ?>)">
                    <svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                  </button>
                  <button class="del-btn" title="Delete Driver" onclick="confirmDelete(<?= $d['id'] ?>, '<?= addslashes($d['firstname'].' '.$d['lastname']) ?>')">
                    <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div><!-- end drivers -->

</div><!-- end main -->

<script>
// ─── In-memory drivers array (from PHP) ───
let driversData = <?= json_encode($drivers) ?>;

// ─── Section switching ───
function switchSection(name) {
  document.querySelectorAll('.page-section').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  document.getElementById('section-' + name).classList.add('active');
  document.getElementById('nav-' + name).classList.add('active');
  const titles = { dashboard: 'Admin Dashboard', drivers: 'Driver Management' };
  document.getElementById('topbarTitle').textContent = titles[name] || 'Dashboard';
}

// ─── Modal helpers ───
function openModal(id)  { document.getElementById(id).classList.add('open');    document.body.style.overflow='hidden'; }
function closeModal(id) { document.getElementById(id).classList.remove('open'); document.body.style.overflow='';       }

// Close modal on overlay click
document.querySelectorAll('.modal-overlay, .id-modal-overlay').forEach(el => {
  el.addEventListener('click', e => { if (e.target === el) closeModal(el.id); });
});

// ─── Live clock ───
function updateClock() {
  const now = new Date();
  document.getElementById('liveTime').textContent = now.toLocaleTimeString('en-PH', {hour12:true});
  document.getElementById('currentDate').textContent = now.toLocaleDateString('en-PH', {weekday:'long',year:'numeric',month:'long',day:'numeric'});
}
updateClock(); setInterval(updateClock, 1000);

// ─── Bar chart ───
const weekData = [
  {day:'MON',pct:88,color:'var(--accent2)'},{day:'TUE',pct:95,color:'var(--accent2)'},
  {day:'WED',pct:74,color:'var(--accent)' },{day:'THU',pct:98,color:'var(--accent2)'},
  {day:'FRI',pct:91,color:'var(--accent2)'},{day:'SAT',pct:62,color:'var(--accent3)'},
  {day:'SUN',pct:45,color:'var(--muted)'  },
];
const chartEl = document.getElementById('barChart');
weekData.forEach(d => {
  const col = document.createElement('div'); col.className = 'bar-col';
  col.innerHTML = `<div style="flex:1;display:flex;align-items:flex-end;width:100%;"><div class="bar-fill" style="background:linear-gradient(to top,${d.color},${d.color}66);height:${d.pct}%;"></div></div><div class="bar-pct">${d.pct}%</div><div class="bar-day">${d.day}</div>`;
  chartEl.appendChild(col);
});

// ─── Toast ───
function showToast(type, title, msg) {
  const icons = {success:'✅',error:'❌',warning:'⚠️',info:'ℹ️'};
  const container = document.getElementById('toastContainer');
  const t = document.createElement('div'); t.className = `toast toast-${type}`;
  t.innerHTML = `<span class="toast-icon">${icons[type]||'ℹ️'}</span><div class="toast-body"><div class="toast-title">${title}</div><div class="toast-msg">${msg}</div></div><button class="toast-close" onclick="dismissToast(this.parentElement)">✕</button><div class="toast-progress"></div>`;
  container.appendChild(t);
  setTimeout(() => dismissToast(t), 4500);
}
function dismissToast(el) {
  if (!el || el.classList.contains('hiding')) return;
  el.classList.add('hiding'); setTimeout(() => el.remove(), 320);
}

// ─── Picture preview ───
function previewPicture(input) {
  const file = input.files[0]; if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    document.getElementById('uploadPreview').src = e.target.result;
    document.getElementById('uploadPreview').style.display = 'block';
    document.getElementById('uploadIcon').style.display = 'none';
    document.getElementById('uploadText').textContent = file.name;
  };
  reader.readAsDataURL(file);
}

// ─── Add Driver ───
function submitAddDriver() {
  const fn = document.getElementById('drvFirstname').value.trim();
  const ln = document.getElementById('drvLastname').value.trim();
  if (!fn || !ln) { showToast('error','Validation Error','Firstname and Lastname are required.'); return; }

  const btn  = document.getElementById('addDriverBtn');
  const btnT = document.getElementById('addDriverBtnText');
  btn.disabled = true;
  btnT.innerHTML = '<span class="spinner"></span> Registering...';

  const fd = new FormData();
  fd.append('action',       'add_driver');
  fd.append('firstname',    fn);
  fd.append('middlename',   document.getElementById('drvMiddlename').value.trim());
  fd.append('lastname',     ln);
  fd.append('contact',      document.getElementById('drvContact').value.trim());
  fd.append('email',        document.getElementById('drvEmail').value.trim());
  fd.append('age',          document.getElementById('drvAge').value);
  fd.append('sex',          document.getElementById('drvSex').value);
  fd.append('date_joined',  document.getElementById('drvDateJoined').value);
  const pic = document.getElementById('drvPicture').files[0];
  if (pic) fd.append('picture', pic);

  fetch(window.location.href, {method:'POST', body:fd})
    .then(r => r.json())
    .then(data => {
      btn.disabled = false; btnT.textContent = 'Register Driver';
      if (data.success) {
        closeModal('addDriverModal');
        resetAddForm();
        showToast('success','Driver Registered!', `${data.firstname} ${data.lastname} (${data.driver_id}) added.`);
        addDriverRow(data);
        updateDriverCount(driversData.length + 1);
        driversData.unshift(data); // prepend
        // Auto-show ID card
        setTimeout(() => showIdCard({
          driver_id: data.driver_id,
          firstname: data.firstname, middlename: data.middlename, lastname: data.lastname,
          age: data.age, sex: data.sex, date_joined: document.getElementById('drvDateJoined').value,
          picture: data.picture
        }), 400);
      } else {
        showToast('error','Error', data.message || 'Could not register driver.');
      }
    })
    .catch(() => {
      btn.disabled = false; btnT.textContent = 'Register Driver';
      showToast('error','Network Error','Could not connect. Check your server.');
    });
}

function resetAddForm() {
  ['drvFirstname','drvMiddlename','drvLastname','drvContact','drvEmail','drvAge'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('drvSex').value = '';
  document.getElementById('drvDateJoined').value = '';
  document.getElementById('drvPicture').value = '';
  document.getElementById('uploadPreview').style.display = 'none';
  document.getElementById('uploadIcon').style.display = 'block';
  document.getElementById('uploadText').textContent = 'Click or drag to upload photo';
}

function addDriverRow(data) {
  const tbody = document.getElementById('driverTableBody');
  // Remove empty state row if present
  const emptyRow = document.getElementById('emptyRow');
  if (emptyRow) emptyRow.remove();

  const mn  = data.middlename ? data.middlename[0]+'. ' : '';
  const fullDisplay = `${data.firstname} ${mn}${data.lastname}`;
  const initials = (data.firstname[0]||'') + (data.lastname[0]||'');
  const rowCount = tbody.querySelectorAll('tr').length + 1;

  const tr = document.createElement('tr');
  tr.dataset.id   = data.db_id || '';
  tr.dataset.name = (data.firstname+' '+data.middlename+' '+data.lastname).toLowerCase();
  tr.innerHTML = `
    <td>${rowCount}</td>
    <td>
      <div class="driver-name-cell">
        <div class="driver-avatar">
          ${data.picture ? `<img src="${data.picture}" alt="photo"/>` : initials.toUpperCase()}
        </div>
        <div>
          <div style="font-weight:600;">${fullDisplay}</div>
          <div style="font-size:0.75rem;color:var(--muted);">${data.email||'—'}</div>
        </div>
      </div>
    </td>
    <td><span class="driver-id-badge">${data.driver_id}</span></td>
    <td style="font-family:'JetBrains Mono',monospace;font-size:0.8rem;">${data.contact||'—'}</td>
    <td>${data.age||'—'}</td>
    <td>${data.sex||'—'}</td>
    <td style="font-family:'JetBrains Mono',monospace;font-size:0.8rem;">${data.date_joined ? formatDate(data.date_joined) : '—'}</td>
    <td>
      <button class="id-card-btn" title="View ID Card" onclick='showIdCard(${JSON.stringify({driver_id:data.driver_id,firstname:data.firstname,middlename:data.middlename,lastname:data.lastname,age:data.age,sex:data.sex,date_joined:data.date_joined,picture:data.picture})})'>
        <svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
      </button>
      <button class="del-btn" title="Delete Driver" onclick="confirmDelete(${data.db_id}, '${data.firstname} ${data.lastname}')">
        <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
      </button>
    </td>`;
  tbody.insertBefore(tr, tbody.firstChild);
}

function formatDate(str) {
  if (!str) return '—';
  const d = new Date(str); if (isNaN(d)) return str;
  return d.toLocaleDateString('en-US', {month:'short', day:'2-digit', year:'numeric'});
}

function updateDriverCount(n) {
  document.getElementById('navDriverCount').textContent   = n;
  document.getElementById('driverCountLabel').textContent = n;
  document.getElementById('driverTableBadge').textContent = n + ' Total';
  document.getElementById('statDriverValue').textContent  = n;
}

// ─── Delete Driver ───
let pendingDeleteId = null;
function confirmDelete(id, name) {
  pendingDeleteId = id;
  document.getElementById('deleteConfirmText').textContent = `Are you sure you want to delete "${name}"? This cannot be undone.`;
  document.getElementById('deleteConfirmBtn').onclick = () => doDeleteDriver(id);
  openModal('deleteConfirmOverlay');
}

function doDeleteDriver(id) {
  const fd = new FormData();
  fd.append('action', 'delete_driver');
  fd.append('id', id);
  fetch(window.location.href, {method:'POST', body:fd})
    .then(r => r.json())
    .then(data => {
      closeModal('deleteConfirmOverlay');
      if (data.success) {
        const row = document.querySelector(`tr[data-id="${id}"]`);
        if (row) { row.style.transition='opacity 0.3s'; row.style.opacity='0'; setTimeout(()=>row.remove(), 320); }
        driversData = driversData.filter(d => d.id != id);
        updateDriverCount(driversData.length);
        if (driversData.length === 0) {
          document.getElementById('driverTableBody').innerHTML = `<tr id="emptyRow"><td colspan="8"><div class="empty-state"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/></svg><p>No drivers registered yet.</p></div></td></tr>`;
        }
        showToast('success','Deleted','Driver has been removed.');
      } else {
        showToast('error','Error', data.message);
      }
    });
}

// ─── Search / Filter ───
function filterDrivers(query) {
  const q = query.toLowerCase().trim();
  const rows = document.querySelectorAll('#driverTableBody tr[data-name]');
  let visible = 0;
  rows.forEach(row => {
    const match = !q || row.dataset.name.includes(q) || row.textContent.toLowerCase().includes(q);
    row.style.display = match ? '' : 'none';
    if (match) visible++;
  });
  document.getElementById('driverCountLabel').textContent = visible;
  document.getElementById('driverTableBadge').textContent = visible + ' Found';
}

// ─── ID Card ───
let currentQR = null;
function showIdCard(driver) {
  const fn = driver.firstname || '';
  const mn = driver.middlename ? driver.middlename + ' ' : '';
  const ln = driver.lastname   || '';
  const fullName = `${fn} ${mn}${ln}`.trim().toUpperCase();
  const initials = ((fn[0]||'')+(ln[0]||'')).toUpperCase();

  document.getElementById('idCardName').textContent    = fullName;
  document.getElementById('idCardAge').textContent     = driver.age  || '—';
  document.getElementById('idCardSex').textContent     = driver.sex  || '—';
  document.getElementById('idCardDate').textContent    = formatDate(driver.date_joined) || '—';
  document.getElementById('idCardDriverId').textContent = driver.driver_id;
  document.getElementById('idCardInitial').textContent = initials;

  // Photo
  const photoWrap = document.getElementById('idCardPhoto');
  if (driver.picture) {
    photoWrap.innerHTML = `<img src="${driver.picture}" alt="Driver Photo"/>`;
  } else {
    photoWrap.innerHTML = `<span class="id-card-photo-initial">${initials}</span>`;
  }

  // QR Code
  const qrWrap = document.getElementById('idQrCode');
  qrWrap.innerHTML = '';
  new QRCode(qrWrap, {
    text: driver.driver_id,
    width: 64, height: 64,
    colorDark: '#000000', colorLight: '#ffffff',
    correctLevel: QRCode.CorrectLevel.H
  });

  openModal('idCardModal');
}

function printIdCard() {
  const card = document.getElementById('idCardEl').outerHTML;
  const win = window.open('','_blank','width=420,height=620');
  win.document.write(`<!DOCTYPE html><html><head><title>Driver ID</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet"/>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{background:#f0f0f0;display:flex;align-items:center;justify-content:center;min-height:100vh;font-family:'DM Sans',sans-serif;}
    :root{--bg:#0a0f1c;--surface:#111827;--surface2:#1a2235;--surface3:#202c42;--accent:#f59e0b;--accent2:#10b981;--accent3:#3b82f6;--text:#f1f5f9;--muted:#64748b;--border:rgba(255,255,255,0.07);}
    .id-card{width:320px;background:linear-gradient(160deg,#1a2235 0%,#0d1525 100%);border:2px solid var(--accent);border-radius:16px;overflow:hidden;box-shadow:0 8px 32px rgba(0,0,0,0.6);}
    .id-card-head{background:linear-gradient(135deg,var(--accent) 0%,#d97706 100%);padding:12px 16px;display:flex;align-items:center;gap:10px;}
    .id-card-head-logo{width:28px;height:28px;background:#0a0f1c;border-radius:6px;display:grid;place-items:center;}
    .id-card-head-logo svg{width:16px;height:16px;}
    .id-card-head-text .org{font-family:'Bebas Neue',sans-serif;font-size:1.1rem;letter-spacing:0.06em;color:#0a0f1c;line-height:1;}
    .id-card-head-text .sub{font-size:0.62rem;color:rgba(10,15,28,0.7);font-weight:600;letter-spacing:0.05em;text-transform:uppercase;}
    .id-card-body{padding:20px 20px 16px;}
    .id-card-photo-wrap{width:80px;height:80px;border-radius:50%;border:3px solid var(--accent);overflow:hidden;margin:0 auto 14px;background:#202c42;display:flex;align-items:center;justify-content:center;}
    .id-card-photo-wrap img{width:100%;height:100%;object-fit:cover;}
    .id-card-photo-initial{font-family:'Bebas Neue',sans-serif;font-size:2rem;color:var(--accent);}
    .id-card-fullname{font-family:'Bebas Neue',sans-serif;font-size:1.15rem;letter-spacing:0.04em;color:#f1f5f9;line-height:1.1;text-align:center;}
    .id-card-type{font-size:0.65rem;color:var(--accent);font-weight:700;text-transform:uppercase;letter-spacing:0.12em;margin-top:3px;font-family:'JetBrains Mono',monospace;text-align:center;}
    .id-card-info{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin:14px 0 16px;text-align:left;}
    .id-info-item label{font-size:0.6rem;color:#64748b;text-transform:uppercase;letter-spacing:0.1em;font-family:'JetBrains Mono',monospace;}
    .id-info-item span{font-size:0.78rem;font-weight:600;color:#f1f5f9;display:block;}
    .id-card-divider{height:1px;background:rgba(255,255,255,0.07);}
    .id-card-footer{display:flex;align-items:center;justify-content:space-between;padding:14px 0 0;gap:12px;}
    .id-card-id-block label{font-size:0.6rem;color:#64748b;text-transform:uppercase;letter-spacing:0.1em;font-family:'JetBrains Mono',monospace;}
    .id-card-id-block span{font-family:'JetBrains Mono',monospace;font-size:0.75rem;color:var(--accent);font-weight:700;display:block;margin-top:2px;}
    .id-qr-wrap{width:72px;height:72px;background:#fff;border-radius:8px;padding:4px;}
    .id-qr-wrap canvas{width:100%!important;height:100%!important;}
  </style></head><body>${card}</body></html>`);
  win.document.close();
  setTimeout(() => { win.print(); }, 800);
}

// ─── Logout ───
function doLogout() {
  fetch('logout.php').then(() => { window.location.href = 'homepage.php'; }).catch(() => { window.location.href = 'homepage.php'; });
}

// ─── Attendance Log Filter ───
let currentFilterType = 'date';

function setFilterType(type) {
  currentFilterType = type;
  // Update tab active state
  document.querySelectorAll('.att-tab').forEach(t => {
    t.classList.toggle('active', t.dataset.type === type);
  });
  // Show/hide correct input
  document.getElementById('attDatePicker').style.display  = type === 'date'  ? 'block' : 'none';
  document.getElementById('attMonthPicker').style.display = type === 'month' ? 'block' : 'none';
  document.getElementById('attYearPicker').style.display  = type === 'year'  ? 'block' : 'none';
  document.getElementById('attDayPicker').style.display   = type === 'day'   ? 'block' : 'none';
  applyAttFilter();
}

function getFilterValue() {
  switch(currentFilterType) {
    case 'date':  return document.getElementById('attDatePicker').value;
    case 'month': return document.getElementById('attMonthPicker').value;
    case 'year':  return document.getElementById('attYearPicker').value;
    case 'day':   return document.getElementById('attDayPicker').value;
  }
}

function applyAttFilter() {
  const val = getFilterValue();
  if (!val) return;
  const tbody = document.getElementById('attLogTbody');
  tbody.innerHTML = `<tr><td colspan="6"><div class="att-loading">Loading...</div></td></tr>`;

  // Update subtitle
  const sub = document.getElementById('attLogSub');
  const badge = document.getElementById('attLiveBadge');
  const days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
  if (currentFilterType === 'date') {
    const d = new Date(val + 'T00:00:00');
    sub.textContent = d.toLocaleDateString('en-US', {month:'long', day:'numeric', year:'numeric'});
    badge.textContent = val === new Date().toISOString().slice(0,10) ? 'Live' : 'Filtered';
  } else if (currentFilterType === 'month') {
    const [y,m] = val.split('-');
    sub.textContent = new Date(y, m-1).toLocaleDateString('en-US', {month:'long', year:'numeric'});
    badge.textContent = 'Filtered';
  } else if (currentFilterType === 'year') {
    sub.textContent = 'Year ' + val;
    badge.textContent = 'Filtered';
  } else if (currentFilterType === 'day') {
    const dayNames = {1:'Sunday',2:'Monday',3:'Tuesday',4:'Wednesday',5:'Thursday',6:'Friday',7:'Saturday'};
    sub.textContent = 'Every ' + (dayNames[val] || '');
    badge.textContent = 'Filtered';
  }

  const fd = new FormData();
  fd.append('action', 'filter_attendance');
  fd.append('filter_type', currentFilterType);
  fd.append('filter_value', val);

  fetch(window.location.href, {method:'POST', body:fd})
    .then(r => r.json())
    .then(data => {
      if (!data.success) { tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:24px;color:#fca5a5;">Error loading data.</td></tr>`; return; }
      if (!data.rows || data.rows.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6">
          <div class="att-empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <p>Walang resulta</p>
            <span>No attendance records found for the selected filter.</span>
          </div>
        </td></tr>`;
        return;
      }
      let html = '';
      data.rows.forEach((row, i) => {
        const dateCell = currentFilterType !== 'date'
          ? `<td style="font-family:'JetBrains Mono',monospace;font-size:0.78rem;color:var(--muted);">${row.date}</td>` : '';
        const dateTh = currentFilterType !== 'date' ? 1 : 0;
        html += `<tr>
          <td>${i+1}</td>
          <td><span class="driver-id-badge" style="font-size:0.72rem;">${esc(row.drv_id)}</span></td>
          <td>${esc(row.name)}</td>
          ${dateCell}
          <td style="font-family:'JetBrains Mono',monospace;font-size:0.8rem;">${row.time_in}</td>
          <td style="font-family:'JetBrains Mono',monospace;font-size:0.8rem;">${row.time_out}</td>
          <td><span class="status-badge ${row.badge}">${row.status}</span></td>
        </tr>`;
      });
      // Add date column header if not date filter
      if (currentFilterType !== 'date') {
        document.getElementById('attDateHeader').style.display = '';
      } else {
        document.getElementById('attDateHeader').style.display = 'none';
      }
      tbody.innerHTML = html;
    })
    .catch(() => {
      tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:24px;color:#fca5a5;">Network error.</td></tr>`;
    });
}

function resetAttFilter() {
  currentFilterType = 'date';
  document.querySelectorAll('.att-tab').forEach(t => t.classList.toggle('active', t.dataset.type === 'date'));
  document.getElementById('attDatePicker').style.display  = 'block';
  document.getElementById('attMonthPicker').style.display = 'none';
  document.getElementById('attYearPicker').style.display  = 'none';
  document.getElementById('attDayPicker').style.display   = 'none';
  document.getElementById('attDatePicker').value = new Date().toISOString().slice(0,10);
  document.getElementById('attDateHeader').style.display = 'none';
  applyAttFilter();
}

function esc(str) {
  const d = document.createElement('div'); d.textContent = str; return d.innerHTML;
}

// ─── Set today as default date joined ───
document.getElementById('drvDateJoined').valueAsDate = new Date();

// ─── Init attendance filter on load ───
window.addEventListener('load', () => {
  // Render today's data via AJAX so the filter JS controls the table from the start
  applyAttFilter();
});

// ─── Auto-switch section from URL hash (e.g. admin_dashboard.php#drivers) ───
window.addEventListener('load', () => {
  const hash = window.location.hash.replace('#', '');
  if (hash && document.getElementById('section-' + hash)) {
    switchSection(hash);
  }
});

// ─── Welcome toast (show once per login session only) ───
window.addEventListener('load', () => {
  if (!sessionStorage.getItem('welcome_shown')) {
    sessionStorage.setItem('welcome_shown', '1');
    setTimeout(() => showToast('success','Login Successful','Welcome back, <?= addslashes(explode(' ', $adminName)[0]) ?>! Dashboard loaded.'), 500);
  }
});
</script>
</body>
</html>