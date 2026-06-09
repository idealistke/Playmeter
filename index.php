<?php
// index.php — Dashboard (FIFA 26 · single machine · live schema)
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db       = $database->getConnection();
$today    = date('Y-m-d');

$stats = [];

// FIFA 26 machine row (id = 1)
$row = $db->query("SELECT status, is_online, total_plays, total_revenue FROM machines WHERE id = 1")
          ->fetch(PDO::FETCH_ASSOC);
$stats['machine_status'] = $row['status']       ?? 'unknown';
$stats['machine_online'] = $row['is_online']    ?? 0;
$stats['total_plays']    = $row['total_plays']  ?? 0;
$stats['total_revenue']  = $row['total_revenue'] ?? 0;

// Today's paid plays
$stmt = $db->prepare("SELECT COUNT(*) as total FROM plays WHERE DATE(play_date) = :today AND payment_status = 'paid'");
$stmt->execute([':today' => $today]);
$stats['today_plays'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Today's revenue (paid only)
$stmt = $db->prepare("SELECT COALESCE(SUM(amount_paid),0) as total FROM plays WHERE DATE(play_date) = :today AND payment_status = 'paid'");
$stmt->execute([':today' => $today]);
$stats['today_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Pending payments today
$stmt = $db->prepare("SELECT COUNT(*) as total FROM plays WHERE DATE(play_date) = :today AND payment_status = 'pending'");
$stmt->execute([':today' => $today]);
$stats['pending_payments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Currently playing — uses plays.status (migration col)
$stmt = $db->query("SELECT COUNT(*) as total FROM plays WHERE status = 'playing'");
$stats['currently_playing'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Arduino state — uses arduino_units.state (migration col)
$row2 = $db->query("SELECT state, last_seen FROM arduino_units WHERE unit_id = 'ARDUINO_001' LIMIT 1")
           ->fetch(PDO::FETCH_ASSOC);
$stats['arduino_state']     = $row2['state']    ?? 'unknown';
$stats['arduino_last_seen'] = $row2['last_seen'] ?? null;

// Active sessions
$stmt = $db->query("SELECT COUNT(*) as total FROM sessions WHERE end_time IS NULL");
$stats['active_sessions'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Recent plays — columns that actually exist in your DB
$recent_plays = $db->query("
    SELECT
        p.id, p.player_name, p.phone_number, p.plays_count,
        p.amount_paid, p.amount, p.payment_method, p.payment_status,
        p.status, p.duration_seconds, p.paid_at, p.play_date,
        p.mpesa_transaction_code,
        c.full_name AS customer_name
    FROM plays p
    LEFT JOIN customers c ON p.customer_id = c.id
    ORDER BY p.play_date DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — PlayMeter Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body { background:#f4f6f9; font-family:'Segoe UI',sans-serif; }
        .sidebar {
            min-height:100vh;
            background:linear-gradient(135deg,#2c3e50 0%,#1a252f 100%);
            color:white; position:fixed; width:260px;
            box-shadow:2px 0 10px rgba(0,0,0,.1);
        }
        .sidebar a {
            color:rgba(255,255,255,.8); text-decoration:none;
            padding:15px 20px; display:block; transition:all .3s;
            border-left:4px solid transparent;
        }
        .sidebar a:hover  { background:rgba(255,255,255,.1); color:#fff; border-left-color:#3498db; }
        .sidebar a.active { background:rgba(52,152,219,.2);  color:#fff; border-left-color:#3498db; }
        .sidebar i { margin-right:10px; width:20px; }
        .content { margin-left:260px; padding:20px; }
        .navbar-top {
            background:#fff; padding:15px 25px; margin-bottom:25px;
            border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,.05);
        }
        .stat-card {
            background:#fff; border-radius:12px; padding:25px; margin-bottom:20px;
            box-shadow:0 2px 10px rgba(0,0,0,.05);
            transition:transform .3s,box-shadow .3s;
        }
        .stat-card:hover { transform:translateY(-5px); box-shadow:0 5px 20px rgba(0,0,0,.1); }
        .stat-icon { font-size:2.5rem; color:#3498db; }
        .table-container { background:#fff; border-radius:12px; padding:20px; box-shadow:0 2px 10px rgba(0,0,0,.05); }
        .machine-hero {
            background:linear-gradient(135deg,#1a9e3f,#0a5e25);
            color:#fff; border-radius:12px; padding:22px 28px;
            display:flex; align-items:center; justify-content:space-between;
            margin-bottom:25px; box-shadow:0 4px 15px rgba(10,94,37,.3);
        }
        .badge-online  { background:rgba(255,255,255,.25) !important; }
        .badge-offline { background:rgba(255,60,60,.4)    !important; }
        .badge-paid      { background-color:#27ae60; }
        .badge-pending   { background-color:#f39c12; }
        .badge-failed    { background-color:#e74c3c; }
        .badge-cancelled { background-color:#7f8c8d; }
        .badge-playing   { background-color:#3498db; }
        .badge-ended     { background-color:#95a5a6; }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div style="padding:25px 20px;text-align:center;border-bottom:1px solid rgba(255,255,255,.1)">
        <i class="bi bi-joystick" style="font-size:3rem;color:#3498db"></i>
        <h5 class="mt-2 mb-0">PlayMeter Pro</h5>
        <p style="font-size:12px;color:rgba(255,255,255,.6);margin-top:5px">
            <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['username']) ?>
        </p>
    </div>
    <nav style="margin-top:20px">
        <a href="index.php"            class="active"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="machines.php"><i class="bi bi-controller"></i> Machine</a>
        <a href="plays.php"><i class="bi bi-play-circle"></i> Plays</a>
        <a href="customers.php"><i class="bi bi-people"></i> Customers</a>
        <a href="session_monitor.php"><i class="bi bi-tv"></i> Live Monitor</a>
        <a href="reports.php"><i class="bi bi-graph-up"></i> Reports</a>
        <a href="maintenance.php"><i class="bi bi-tools"></i> Maintenance</a>
        <a href="arduino_settings.php"><i class="bi bi-microchip"></i> Arduino</a>
        <a href="profile.php"><i class="bi bi-person-circle"></i> Profile</a>
        <a href="logout.php" style="border-top:1px solid rgba(255,255,255,.1);margin-top:20px;position:absolute;bottom:0;width:100%">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </nav>
</div>

<!-- Main Content -->
<div class="content">

    <!-- Top Bar -->
    <div class="navbar-top d-flex justify-content-between align-items-center">
        <h4 class="mb-0">Dashboard</h4>
        <div>
            <span class="badge bg-primary me-2"><?= date('l, F j, Y') ?></span>
            <span class="badge bg-success"><?= $stats['active_sessions'] ?> Active Session(s)</span>
            <?php if ($stats['pending_payments'] > 0): ?>
            <span class="badge bg-warning ms-1"><?= $stats['pending_payments'] ?> Pending Payment(s)</span>
            <?php endif; ?>
            <?php if ($stats['currently_playing'] > 0): ?>
            <span class="badge bg-info ms-1"><i class="bi bi-controller"></i> <?= $stats['currently_playing'] ?> Playing Now</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- FIFA 26 Machine Hero -->
    <div class="machine-hero">
        <div>
            <div style="font-size:1.6rem;font-weight:700">
                <i class="bi bi-controller me-2"></i>FIFA 26
            </div>
            <div style="opacity:.85;font-size:.9rem">
                Arduino: ARDUINO_001 &nbsp;|&nbsp; KSh 50.00 per play
            </div>
        </div>
        <div class="text-end">
            <?php if ($stats['machine_online']): ?>
                <span class="badge badge-online fs-6">
                    <i class="bi bi-circle-fill me-1" style="font-size:.6rem"></i>Online
                </span>
            <?php else: ?>
                <span class="badge badge-offline fs-6">
                    <i class="bi bi-circle-fill me-1" style="font-size:.6rem"></i>Offline
                </span>
            <?php endif; ?>
            <div class="mt-2">
                <span class="badge bg-light text-dark">Machine: <?= ucfirst($stats['machine_status']) ?></span>
                <span class="badge bg-light text-dark ms-1">Arduino: <?= ucfirst($stats['arduino_state']) ?></span>
            </div>
            <?php if ($stats['arduino_last_seen']): ?>
            <div style="font-size:.75rem;opacity:.7;margin-top:4px">
                Last heartbeat: <?= date('H:i:s', strtotime($stats['arduino_last_seen'])) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="row">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Today's Plays</h6>
                        <h2 class="mb-0"><?= $stats['today_plays'] ?></h2>
                    </div>
                    <div class="stat-icon"><i class="bi bi-play-circle"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Today's Revenue</h6>
                        <h2 class="mb-0 text-success">KSh <?= number_format($stats['today_revenue'], 2) ?></h2>
                    </div>
                    <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Total Revenue</h6>
                        <h2 class="mb-0 text-primary">KSh <?= number_format($stats['total_revenue'], 2) ?></h2>
                    </div>
                    <div class="stat-icon"><i class="bi bi-graph-up-arrow"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">All-Time Plays</h6>
                        <h2 class="mb-0"><?= $stats['total_plays'] ?></h2>
                    </div>
                    <div class="stat-icon"><i class="bi bi-joystick"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="table-container">
                <h5 class="mb-3">Quick Actions</h5>
                <a href="add_play.php" class="btn btn-success me-2">
                    <i class="bi bi-plus-circle"></i> Start New Play
                </a>
                <a href="add_customer.php" class="btn btn-info text-white me-2">
                    <i class="bi bi-person-plus"></i> Add Customer
                </a>
                <a href="session_monitor.php" class="btn btn-warning me-2">
                    <i class="bi bi-tv"></i> Live Monitor
                </a>
                <a href="reports.php" class="btn btn-outline-primary">
                    <i class="bi bi-graph-up"></i> Reports
                </a>
            </div>
        </div>
    </div>

    <!-- Recent Plays -->
    <div class="row">
        <div class="col-12">
            <div class="table-container">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Recent Plays — FIFA 26</h5>
                    <a href="plays.php" class="btn btn-sm btn-outline-secondary">View All</a>
                </div>
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Time</th>
                            <th>Player</th>
                            <th>Phone</th>
                            <th>Plays</th>
                            <th>Duration</th>
                            <th>Amount</th>
                            <th>M-Pesa Code</th>
                            <th>Payment</th>
                            <th>Play Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_plays as $play): ?>
                        <tr>
                            <td><?= date('H:i', strtotime($play['play_date'])) ?></td>
                            <td>
                                <?= htmlspecialchars($play['player_name'] ?? '—') ?>
                                <?php if ($play['customer_name']): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($play['customer_name']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($play['phone_number'] ?? '—') ?></td>
                            <td><?= $play['plays_count'] ?></td>
                            <td>
                                <?php if ($play['duration_seconds'] > 0):
                                    $m = floor($play['duration_seconds'] / 60);
                                    $s = $play['duration_seconds'] % 60;
                                    echo $m > 0 ? "{$m}m {$s}s" : "{$s}s";
                                else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>KSh <?= number_format($play['amount_paid'] ?? 0, 2) ?></td>
                            <td>
                                <?php if (!empty($play['mpesa_transaction_code'])): ?>
                                    <code><?= htmlspecialchars($play['mpesa_transaction_code']) ?></code>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $pMap = [
                                    'paid'      => ['badge-paid',      'Paid'],
                                    'pending'   => ['badge-pending',   'Pending'],
                                    'failed'    => ['badge-failed',    'Failed'],
                                    'cancelled' => ['badge-cancelled', 'Cancelled'],
                                ];
                                $p = $pMap[$play['payment_status']] ?? ['bg-secondary', ucfirst($play['payment_status'] ?? '?')];
                                ?>
                                <span class="badge <?= $p[0] ?>"><?= $p[1] ?></span>
                            </td>
                            <td>
                                <?php
                                $sMap = [
                                    'playing' => ['badge-playing', 'Playing'],
                                    'ended'   => ['badge-ended',   'Ended'],
                                ];
                                $st = $sMap[$play['status']] ?? ['bg-secondary', ucfirst($play['status'] ?? '?')];
                                ?>
                                <span class="badge <?= $st[0] ?>"><?= $st[1] ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recent_plays)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">No plays recorded yet.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>