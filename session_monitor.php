<?php
// session_monitor.php - WITH 6 SESSIONS VISIBLE & NO NEGATIVES
session_start();
require_once 'config/database.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$message = '';

// ─── Handle individual End Session ───────────────────────────────────────────
if(isset($_POST['end_single_session']) && !empty($_POST['session_id'])) {
    $session_id = (int)$_POST['session_id'];

    $stmt = $db->prepare("SELECT * FROM sessions WHERE id = ? AND end_time IS NULL");
    $stmt->execute([$session_id]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if($session) {
        $start_time      = strtotime($session['start_time']);
        $now             = time();
        $elapsed_minutes = max(0, ($now - $start_time) / 60);
        $rate            = $session['rate_per_minute'] ?? 2.00;

        // Loyalty discount
        $discount = 0;
        if($session['customer_id']) {
            $cst = $db->prepare("SELECT total_visits FROM customers WHERE id = ?");
            $cst->execute([$session['customer_id']]);
            $cust   = $cst->fetch(PDO::FETCH_ASSOC);
            $visits = $cust['total_visits'] ?? 0;
            if      ($visits >= 50) $discount = 0.20;
            elseif  ($visits >= 30) $discount = 0.15;
            elseif  ($visits >= 15) $discount = 0.10;
            elseif  ($visits >= 5)  $discount = 0.05;
        }
        $total_cost = ($elapsed_minutes * $rate) * (1 - $discount);

        // End the session
        $upd = $db->prepare("UPDATE sessions SET
                end_time         = NOW(),
                duration_minutes = ?,
                total_cost       = ?,
                amount_paid      = ?,
                payment_status   = 'paid'
                WHERE id = ?");
        $upd->execute([round($elapsed_minutes,2), round($total_cost,2), round($total_cost,2), $session_id]);

        // Also write to plays table (new schema with session_id + duration_minutes)
        // Only insert if not already recorded for this session
        $check = $db->prepare("SELECT id FROM plays WHERE session_id = ?");
        $check->execute([$session_id]);
        if(!$check->fetch()) {
            $ins = $db->prepare("INSERT INTO plays
                (session_id, machine_id, customer_id, player_name,
                 plays_count, duration_minutes, amount_paid, payment_method)
                VALUES (?, ?, ?, ?, 1, ?, ?, 'cash')");
            $ins->execute([
                $session_id,
                $session['machine_id'],
                $session['customer_id'],
                'Session Player',
                round($elapsed_minutes,2),
                round($total_cost,2)
            ]);
        }

        // Update customer stats
        if($session['customer_id']) {
            $db->prepare("UPDATE customers SET
                    total_visits = total_visits + 1,
                    total_spent  = total_spent + ?
                    WHERE id = ?")
               ->execute([round($total_cost,2), $session['customer_id']]);
        }

        // Free the machine
        $db->prepare("UPDATE machines SET current_session_id = NULL WHERE id = ?")
           ->execute([$session['machine_id']]);

        $h   = floor($elapsed_minutes / 60);
        $m   = floor((int)$elapsed_minutes % 60);
        $s   = (int)(($elapsed_minutes - floor($elapsed_minutes)) * 60);
        $dur = ($h > 0 ? "{$h}h " : '') . "{$m}m {$s}s";
        $disc_str = ($discount > 0) ? ' (after ' . (int)($discount*100) . '% loyalty discount)' : '';

        $message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <strong>Session ended.</strong> Duration: <strong>' . $dur . '</strong> —
            Amount due: <strong>KSh ' . number_format($total_cost,2) . '</strong>' . $disc_str . '.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
    }
}

// ─── Handle Stop All Sessions ─────────────────────────────────────────────────
if(isset($_POST['stop_all_sessions'])) {
    $query = "SELECT * FROM sessions WHERE end_time IS NULL";
    $stmt  = $db->prepare($query);
    $stmt->execute();
    $active_sessions_to_stop = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stopped_count = 0;
    $total_revenue = 0;
    $now           = time();

    foreach($active_sessions_to_stop as $session) {
        $session_id      = $session['id'];
        $start_time      = strtotime($session['start_time']);
        $elapsed_minutes = max(0, ($now - $start_time) / 60);
        $rate            = $session['rate_per_minute'] ?? 2.00;

        $discount = 0;
        if($session['customer_id']) {
            $cst = $db->prepare("SELECT total_visits FROM customers WHERE id = ?");
            $cst->execute([$session['customer_id']]);
            $cust   = $cst->fetch(PDO::FETCH_ASSOC);
            $visits = $cust['total_visits'] ?? 0;
            if      ($visits >= 50) $discount = 0.20;
            elseif  ($visits >= 30) $discount = 0.15;
            elseif  ($visits >= 15) $discount = 0.10;
            elseif  ($visits >= 5)  $discount = 0.05;
        }
        $total_cost = ($elapsed_minutes * $rate) * (1 - $discount);

        $upd = $db->prepare("UPDATE sessions SET
                end_time         = NOW(),
                duration_minutes = ?,
                total_cost       = ?,
                amount_paid      = ?,
                payment_status   = 'paid'
                WHERE id = ?");
        $upd->execute([round($elapsed_minutes,2), round($total_cost,2), round($total_cost,2), $session_id]);

        // Write to plays table
        $check = $db->prepare("SELECT id FROM plays WHERE session_id = ?");
        $check->execute([$session_id]);
        if(!$check->fetch()) {
            $ins = $db->prepare("INSERT INTO plays
                (session_id, machine_id, customer_id, player_name,
                 plays_count, duration_minutes, amount_paid, payment_method)
                VALUES (?, ?, ?, ?, 1, ?, ?, 'cash')");
            $ins->execute([
                $session_id,
                $session['machine_id'],
                $session['customer_id'],
                'Session Player',
                round($elapsed_minutes,2),
                round($total_cost,2)
            ]);
        }

        if($session['customer_id']) {
            $db->prepare("UPDATE customers SET
                    total_visits = total_visits + 1,
                    total_spent  = total_spent + ?
                    WHERE id = ?")
               ->execute([round($total_cost,2), $session['customer_id']]);
        }

        $db->prepare("UPDATE machines SET current_session_id = NULL WHERE id = ?")
           ->execute([$session['machine_id']]);

        $stopped_count++;
        $total_revenue += $total_cost;
    }

    $message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>
        Successfully ended <strong>' . $stopped_count . '</strong> active sessions.
        Total revenue: <strong>KSh ' . number_format($total_revenue,2) . '</strong>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>';
}

// ─── Fetch active sessions ────────────────────────────────────────────────────
$query = "SELECT s.*,
          m.name as machine_name,
          c.full_name as customer_name,
          c.customer_code,
          c.total_visits,
          c.total_spent
          FROM sessions s
          LEFT JOIN machines m ON s.machine_id = m.id
          LEFT JOIN customers c ON s.customer_id = c.id
          WHERE s.end_time IS NULL
          ORDER BY s.start_time DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$active_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_active = count($active_sessions);

$potential_revenue = 0;
$now = time();
foreach($active_sessions as $session) {
    $start   = strtotime($session['start_time']);
    $elapsed = max(0, ($now - $start) / 60);
    $rate    = $session['rate_per_minute'] ?? 2.00;
    $potential_revenue += $elapsed * $rate;
}

function getCustomerTier($visits) {
    if ($visits >= 50) return 'platinum';
    if ($visits >= 30) return 'gold';
    if ($visits >= 15) return 'silver';
    if ($visits >= 5)  return 'bronze';
    return 'regular';
}

function getTokenBadge($tier) {
    switch($tier) {
        case 'platinum': return ['icon'=>'bi-gem',       'color'=>'#8e44ad','bg'=>'#f3e5f5','text'=>'PLATINUM','discount'=>'20'];
        case 'gold':     return ['icon'=>'bi-award',     'color'=>'#f39c12','bg'=>'#fff3e0','text'=>'GOLD',    'discount'=>'15'];
        case 'silver':   return ['icon'=>'bi-star',      'color'=>'#7f8c8d','bg'=>'#f5f5f5','text'=>'SILVER',  'discount'=>'10'];
        case 'bronze':   return ['icon'=>'bi-star-half', 'color'=>'#d35400','bg'=>'#ffecdb','text'=>'BRONZE',  'discount'=>'5'];
        default:         return ['icon'=>'bi-person',    'color'=>'#95a5a6','bg'=>'#f8f9fa','text'=>'REGULAR', 'discount'=>'0'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Session Monitor - PlayMeter</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            background:#f4f6f9;
            font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;
            overflow-x:hidden; min-height:100vh; display:flex;
        }
        .sidebar {
            min-height:100vh;
            background:linear-gradient(135deg,#2c3e50 0%,#1a252f 100%);
            color:white; position:fixed; width:260px;
            box-shadow:2px 0 10px rgba(0,0,0,0.1); overflow-y:auto;
        }
        .sidebar a {
            color:rgba(255,255,255,0.8); text-decoration:none;
            padding:15px 20px; display:block; transition:all 0.3s;
            border-left:4px solid transparent;
        }
        .sidebar a:hover  { background:rgba(255,255,255,0.1); color:white; border-left-color:#3498db; }
        .sidebar a.active { background:rgba(52,152,219,0.2);  color:white; border-left-color:#3498db; }
        .sidebar i { margin-right:10px; width:20px; }
        .content { margin-left:260px; padding:20px; width:calc(100% - 260px); min-height:100vh; overflow-y:auto; }
        .header-card {
            background:white; border-radius:15px; padding:20px 25px; margin-bottom:25px;
            box-shadow:0 2px 10px rgba(0,0,0,0.05);
            display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px;
        }
        .stats-container { display:flex; gap:15px; flex-wrap:wrap; }
        .stats-badge   { background:#e8f5e9; color:#2e7d32; padding:10px 20px; border-radius:50px; font-weight:600; display:inline-flex; align-items:center; gap:8px; }
        .revenue-badge { background:#e3f2fd; color:#1976d2; padding:10px 20px; border-radius:50px; font-weight:600; display:inline-flex; align-items:center; gap:8px; }
        .token-summary { background:#fff3e0; color:#f39c12; padding:10px 20px; border-radius:50px; font-weight:600; display:inline-flex; align-items:center; gap:8px; }
        .stop-all-btn {
            background:#dc3545; color:white; border:none; padding:12px 25px;
            border-radius:50px; font-weight:600; display:inline-flex; align-items:center; gap:8px;
            cursor:pointer; transition:background 0.2s; font-size:1em;
        }
        .stop-all-btn:hover    { background:#c82333; }
        .stop-all-btn:disabled { background:#6c757d; cursor:not-allowed; }
        .sessions-grid {
            display:grid; grid-template-columns:repeat(3,1fr); gap:15px; margin-top:20px;
        }
        .session-card {
            background:white; border-radius:12px; padding:12px;
            box-shadow:0 2px 8px rgba(0,0,0,0.05); border-left:4px solid #28a745;
            transition:transform 0.2s, box-shadow 0.2s;
            display:flex; flex-direction:column; position:relative; height:fit-content; font-size:0.9em;
        }
        .session-card:hover { transform:translateY(-2px); box-shadow:0 4px 12px rgba(0,0,0,0.1); }
        .token-badge {
            position:absolute; top:10px; right:10px; padding:3px 8px;
            border-radius:15px; font-size:0.65em; font-weight:600;
            display:flex; align-items:center; gap:3px;
            box-shadow:0 2px 5px rgba(0,0,0,0.1); z-index:1;
        }
        .machine-name {
            font-size:1.1em; font-weight:600; color:#2c3e50; margin-bottom:5px;
            white-space:nowrap; overflow:hidden; text-overflow:ellipsis; padding-right:70px;
        }
        .customer-info {
            background:#f8f9fa; border-radius:6px; padding:6px; margin:5px 0; border-left:3px solid #3498db;
        }
        .customer-stats { display:flex; gap:6px; margin-top:3px; font-size:0.7em; flex-wrap:wrap; }
        .visit-count, .spent-amount { padding:2px 6px; border-radius:4px; display:inline-flex; align-items:center; gap:2px; }
        .customer-name { font-weight:600; color:#2c3e50; font-size:0.95em; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .customer-code { font-size:0.7em; color:#7f8c8d; font-family:monospace; }
        .time-info { display:flex; justify-content:space-between; background:#f8f9fa; border-radius:5px; padding:4px 6px; margin:5px 0; font-size:0.8em; }
        .rate-badge { background:#e3f2fd; color:#1976d2; padding:2px 6px; border-radius:15px; font-size:0.7em; font-weight:600; }
        .timer-display {
            background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);
            border-radius:6px; padding:8px; text-align:center; margin:5px 0;
        }
        .timer { font-size:1.6em; font-weight:bold; color:white; font-family:monospace; line-height:1; }
        .cost-display {
            background:linear-gradient(135deg,#43a047 0%,#2e7d32 100%);
            border-radius:6px; padding:8px; text-align:center; margin:5px 0; position:relative;
        }
        .cost { font-size:1.4em; font-weight:bold; color:white; font-family:monospace; line-height:1; }
        .discount-badge {
            position:absolute; top:-6px; right:-6px; background:#ffd700; color:#2c3e50;
            border-radius:15px; padding:2px 6px; font-size:0.6em; font-weight:bold;
            box-shadow:0 2px 5px rgba(0,0,0,0.2);
        }
        .timer-label, .cost-label { color:rgba(255,255,255,0.9); font-size:0.65em; margin-top:2px; }
        .end-btn {
            width:100%; padding:6px; background:#dc3545; color:white; border:none;
            border-radius:5px; font-weight:600; margin-top:5px; font-size:0.8em; cursor:pointer;
        }
        .end-btn:hover { background:#c82333; }
        .guest-badge { background:#9e9e9e; color:white; padding:2px 5px; border-radius:4px; font-size:0.65em; }
        .no-sessions {
            grid-column:1/-1; background:white; border-radius:15px; padding:40px;
            text-align:center; color:#999; border:2px dashed #ccc;
        }

        /* ── Modals ─────────────────────────────────────── */
        .modal-overlay {
            display:none; position:fixed; top:0; left:0; width:100%; height:100%;
            background:rgba(0,0,0,0.55); z-index:1000; justify-content:center; align-items:center;
        }
        .modal-content {
            background:white; padding:30px; border-radius:15px;
            max-width:460px; width:90%; text-align:center;
        }
        .modal-buttons { display:flex; gap:10px; justify-content:center; margin-top:20px; }

        /* Payment receipt inside End-Session modal */
        .receipt-box {
            background:linear-gradient(135deg,#1a1a2e 0%,#16213e 100%);
            border-radius:10px; padding:16px; margin:14px 0; color:white; text-align:center;
        }
        .receipt-machine { font-size:0.85em; color:rgba(255,255,255,0.65); }
        .receipt-timer   { font-size:1.5em; font-weight:bold; font-family:monospace; color:#a29bfe; margin:4px 0; }
        .receipt-amount  { font-size:2em; font-weight:bold; font-family:monospace; color:#f9ca24; }
        .receipt-rate    { font-size:0.75em; color:rgba(255,255,255,0.5); }
        .receipt-discount{ font-size:0.8em; color:#55efc4; margin-top:4px; }
        .receipt-note    { font-size:0.72em; color:rgba(255,255,255,0.45); margin-top:8px; border-top:1px solid rgba(255,255,255,0.1); padding-top:6px; }

        .more-sessions-indicator {
            grid-column:1/-1; text-align:center; padding:15px; color:#7f8c8d;
            background:rgba(255,255,255,0.5); border-radius:10px; margin-top:10px;
        }
        @media(max-width:1200px){ .sessions-grid{ grid-template-columns:repeat(2,1fr); } }
        @media(max-width:768px) {
            .sidebar{ width:100%; position:relative; min-height:auto; }
            .content{ margin-left:0; width:100%; }
            .sessions-grid{ grid-template-columns:1fr; }
        }
    </style>
</head>
<body>

<!-- ── Stop-All Confirmation Modal ────────────────────────────────────────── -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal-content">
        <i class="bi bi-exclamation-triangle-fill" style="font-size:3em;color:#dc3545;"></i>
        <h4 class="mt-3">Stop All Active Sessions?</h4>
        <p class="text-muted">This will end all <strong><?php echo $total_active; ?></strong> active sessions and calculate final bills.</p>
        <p><strong>Estimated Total: KSh <span id="stopAllEstimate"><?php echo number_format(max(0,$potential_revenue),2); ?></span></strong></p>
        <div class="modal-buttons">
            <form method="POST" id="stopAllForm">
                <button type="submit" name="stop_all_sessions" class="btn btn-danger">
                    <i class="bi bi-stop-circle-fill me-1"></i>Yes, Stop All
                </button>
            </form>
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
        </div>
    </div>
</div>

<!-- ── Individual End-Session Modal ───────────────────────────────────────── -->
<div class="modal-overlay" id="endSessionModal">
    <div class="modal-content">
        <i class="bi bi-stop-circle-fill" style="font-size:2.5em;color:#dc3545;"></i>
        <h4 class="mt-2">End Session?</h4>

        <!-- Live payment receipt — updates every second -->
        <div class="receipt-box">
            <div class="receipt-machine" id="rcptMachine">—</div>
            <div class="receipt-machine" id="rcptPlayer" style="margin-top:2px;">—</div>
            <div class="receipt-timer"   id="rcptTimer">00:00</div>
            <div class="receipt-rate"    id="rcptRate">@ KSh 0.00/min</div>
            <div class="receipt-amount"  id="rcptAmount">KSh 0.00</div>
            <div class="receipt-discount" id="rcptDiscount"></div>
            <div class="receipt-note">Collect payment — then confirm to close session</div>
        </div>

        <div class="modal-buttons">
            <form method="POST" id="endSessionForm">
                <input type="hidden" name="end_single_session" value="1">
                <input type="hidden" name="session_id" id="endSessionId" value="">
                <button type="submit" class="btn btn-danger">
                    <i class="bi bi-cash-coin me-1"></i>Confirm & End
                </button>
            </form>
            <button type="button" class="btn btn-secondary" onclick="closeEndModal()">Cancel</button>
        </div>
    </div>
</div>

<!-- ── Sidebar ─────────────────────────────────────────────────────────────── -->
<div class="sidebar">
    <div style="padding:20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1);">
        <i class="bi bi-joystick" style="font-size:2.5rem;color:#3498db;"></i>
        <h5 class="mt-2 mb-0">PlayMeter Pro</h5>
        <p style="font-size:11px;color:rgba(255,255,255,0.6);margin-top:5px;">
            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
        </p>
    </div>
    <nav style="margin-top:15px;">
        <a href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="machines.php"><i class="bi bi-controller"></i> Machines</a>
        <a href="plays.php"><i class="bi bi-play-circle"></i> Plays</a>
        <a href="customers.php"><i class="bi bi-people"></i> Customers</a>
        <a href="session_monitor.php" class="active"><i class="bi bi-tv"></i> Live Monitor</a>
        <a href="reports.php"><i class="bi bi-graph-up"></i> Reports</a>
        <a href="maintenance.php"><i class="bi bi-tools"></i> Maintenance</a>
        <a href="arduino_settings.php"><i class="bi bi-microchip"></i> Arduino</a>
        <a href="profile.php"><i class="bi bi-person-circle"></i> Profile</a>
        <a href="logout.php" style="border-top:1px solid rgba(255,255,255,0.1);margin-top:20px;position:absolute;bottom:0;width:100%;">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </nav>
</div>

<!-- ── Main Content ────────────────────────────────────────────────────────── -->
<div class="content">

    <?php if($message): ?>
        <?php echo $message; ?>
    <?php endif; ?>

    <!-- Header -->
    <div class="header-card">
        <div>
            <h3 class="mb-2"><i class="bi bi-tv me-2"></i>Live Session Monitor</h3>
            <p class="text-muted mb-0">Real-time tracking of all active gaming sessions</p>
        </div>
        <div class="stats-container">
            <span class="stats-badge">
                <i class="bi bi-play-circle-fill"></i>
                <?php echo $total_active; ?> Active
            </span>
            <span class="revenue-badge">
                <i class="bi bi-cash-stack"></i>
                Est. KSh <span id="headerRevenue"><?php echo number_format(max(0,$potential_revenue),2); ?></span>
            </span>
            <?php
            $token_holders = 0;
            foreach($active_sessions as $s) {
                if($s['customer_name'] && ($s['total_visits']??0) >= 5) $token_holders++;
            }
            if($token_holders > 0): ?>
            <span class="token-summary">
                <i class="bi bi-award"></i><?php echo $token_holders; ?> Token
            </span>
            <?php endif; ?>
            <?php if($total_active > 0): ?>
            <button class="stop-all-btn" onclick="openModal()">
                <i class="bi bi-stop-circle-fill"></i> Stop All
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Session Cards -->
    <?php if(count($active_sessions) > 0): ?>
    <div class="sessions-grid">

        <?php foreach($active_sessions as $session):
            $start_time    = strtotime($session['start_time']);
            $rate          = $session['rate_per_minute'] ?? 2.00;
            $customer_name = $session['customer_name'] ?? 'Guest';
            $customer_code = $session['customer_code'] ?? '';
            $visits        = $session['total_visits'] ?? 0;
            $spent         = $session['total_spent'] ?? 0;
            $tier          = getCustomerTier($visits);
            $badge         = getTokenBadge($tier);
        ?>
        <div class="session-card" id="session-<?php echo $session['id']; ?>">

            <?php if($customer_name != 'Guest' && $tier != 'regular'): ?>
            <div class="token-badge" style="background:<?php echo $badge['bg']; ?>;color:<?php echo $badge['color']; ?>;">
                <i class="bi <?php echo $badge['icon']; ?>"></i> <?php echo $badge['text']; ?>
            </div>
            <?php endif; ?>

            <div class="machine-name">
                <i class="bi bi-controller me-1" style="color:#3498db;"></i>
                <?php echo htmlspecialchars($session['machine_name']); ?>
            </div>

            <div class="customer-info">
                <div class="d-flex justify-content-between align-items-start">
                    <div style="flex:1;">
                        <div class="customer-name">
                            <i class="bi bi-person-circle me-1"></i>
                            <?php echo htmlspecialchars($customer_name); ?>
                            <?php if($customer_name == 'Guest'): ?>
                                <span class="guest-badge">GUEST</span>
                            <?php endif; ?>
                        </div>
                        <?php if($customer_code): ?>
                        <div class="customer-code"><?php echo $customer_code; ?></div>
                        <?php endif; ?>
                        <?php if($visits > 0): ?>
                        <div class="customer-stats">
                            <span class="visit-count"><i class="bi bi-clock-history"></i> <?php echo $visits; ?></span>
                            <span class="spent-amount"><i class="bi bi-cash-stack"></i> KSh <?php echo number_format($spent,0); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <span class="rate-badge">KSh <?php echo number_format($rate,2); ?>/min</span>
                </div>
            </div>

            <div class="time-info">
                <span><i class="bi bi-clock me-1"></i><?php echo date('H:i', $start_time); ?></span>
                <span><?php echo date('d/m', $start_time); ?></span>
            </div>

            <div class="timer-display">
                <div class="timer" id="timer-<?php echo $session['id']; ?>">00:00</div>
                <div class="timer-label">Elapsed</div>
            </div>

            <div class="cost-display">
                <?php if($badge['discount'] > 0): ?>
                <div class="discount-badge" title="<?php echo $badge['discount']; ?>% off">
                    -<?php echo $badge['discount']; ?>%
                </div>
                <?php endif; ?>
                <div class="cost" id="cost-<?php echo $session['id']; ?>">KSh 0.00</div>
                <div class="cost-label">Cost</div>
            </div>

            <button class="end-btn" onclick="openEndModal(
                <?php echo $session['id']; ?>,
                '<?php echo addslashes($session['machine_name']); ?>',
                '<?php echo addslashes($customer_name); ?>',
                <?php echo $start_time; ?>,
                <?php echo $rate; ?>,
                <?php echo (int)$badge['discount']; ?>
            )">
                <i class="bi bi-stop-circle me-1"></i>End
            </button>
        </div>

        <!-- Per-card timer script — exactly your original pattern -->
        <script>
        (function() {
            const sessionId    = <?php echo $session['id']; ?>;
            const rate         = <?php echo $rate; ?>;
            const phpTimestamp = <?php echo $start_time; ?>;
            const discount     = <?php echo $badge['discount']; ?>;

            function updateTimer() {
                const now            = Math.floor(Date.now() / 1000);
                const elapsedSeconds = Math.max(0, now - phpTimestamp);

                const hours   = Math.floor(elapsedSeconds / 3600);
                const minutes = Math.floor((elapsedSeconds % 3600) / 60);
                const seconds = elapsedSeconds % 60;

                let timeStr;
                if (hours > 0) {
                    timeStr = hours.toString().padStart(2,'0') + ':' +
                              minutes.toString().padStart(2,'0') + ':' +
                              seconds.toString().padStart(2,'0');
                } else {
                    timeStr = minutes.toString().padStart(2,'0') + ':' +
                              seconds.toString().padStart(2,'0');
                }

                const timerEl = document.getElementById('timer-' + sessionId);
                if (timerEl) timerEl.textContent = timeStr;

                // Cost
                let cost = (elapsedSeconds / 60) * rate;
                if (discount > 0) cost = cost * (1 - (discount / 100));

                const costEl = document.getElementById('cost-' + sessionId);
                if (costEl) costEl.textContent = 'KSh ' + cost.toFixed(2);
            }

            updateTimer();
            setInterval(updateTimer, 1000);
        })();
        </script>

        <?php endforeach; ?>
    </div>

    <?php if($total_active > 6): ?>
    <div class="more-sessions-indicator">
        <i class="bi bi-arrow-down-circle me-2"></i>
        Scroll down for more sessions (<?php echo $total_active - 6; ?> more)
        <i class="bi bi-arrow-down-circle ms-2"></i>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="sessions-grid">
        <div class="no-sessions">
            <i class="bi bi-tv" style="font-size:3em;"></i>
            <h4>No Active Sessions</h4>
            <p class="text-muted">There are currently no active gaming sessions.</p>
            <p class="text-muted">Start a session by scanning a QR code at an Arduino unit.</p>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /.content -->

<script>
// ── Stop-All modal ────────────────────────────────────────────────────────────
function openModal()  { document.getElementById('confirmModal').style.display  = 'flex'; }
function closeModal() { document.getElementById('confirmModal').style.display  = 'none'; }

// ── End-Session modal ─────────────────────────────────────────────────────────
let _endTimerInterval = null;

function openEndModal(sessionId, machineName, playerName, phpTs, rate, discountPct) {
    // Stop any previous ticking inside this modal
    if (_endTimerInterval) clearInterval(_endTimerInterval);

    document.getElementById('endSessionId').value = sessionId;
    document.getElementById('rcptMachine').textContent = '🎮 ' + machineName;
    document.getElementById('rcptPlayer').textContent  = '👤 ' + playerName;
    document.getElementById('rcptRate').textContent    = '@ KSh ' + rate.toFixed(2) + '/min';
    document.getElementById('rcptDiscount').textContent =
        discountPct > 0 ? '🏷 ' + discountPct + '% loyalty discount applied' : '';

    function tick() {
        const now     = Math.floor(Date.now() / 1000);
        const elapsed = Math.max(0, now - phpTs);

        // Timer string
        const h = Math.floor(elapsed / 3600);
        const m = Math.floor((elapsed % 3600) / 60);
        const s = elapsed % 60;
        const timeStr = (h > 0 ? String(h).padStart(2,'0') + ':' : '')
                      + String(m).padStart(2,'0') + ':'
                      + String(s).padStart(2,'0');
        document.getElementById('rcptTimer').textContent = timeStr;

        // Cost
        let cost = (elapsed / 60) * rate;
        if (discountPct > 0) cost = cost * (1 - discountPct / 100);
        document.getElementById('rcptAmount').textContent = 'KSh ' + cost.toFixed(2);
    }

    tick();
    _endTimerInterval = setInterval(tick, 1000);

    document.getElementById('endSessionModal').style.display = 'flex';
}

function closeEndModal() {
    if (_endTimerInterval) clearInterval(_endTimerInterval);
    document.getElementById('endSessionModal').style.display = 'none';
}

// Close modals on outside click
window.onclick = function(event) {
    if (event.target == document.getElementById('confirmModal'))   closeModal();
    if (event.target == document.getElementById('endSessionModal')) closeEndModal();
};
</script>

</body>
</html>