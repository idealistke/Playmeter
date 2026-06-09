<?php
// machines.php — Single machine view (FIFA 26 only)
// Adding/editing machines is disabled until expansion phase.
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db       = $database->getConnection();

// Always load FIFA 26 (id=1) only
$stmt = $db->query("
    SELECT m.*,
           a.unit_id  AS arduino_unit,
           a.state    AS arduino_state,
           a.last_seen AS arduino_last_seen,
           a.status   AS arduino_status,
           (SELECT COUNT(*) FROM sessions WHERE machine_id = m.id AND end_time IS NULL) AS active_sessions
    FROM machines m
    LEFT JOIN arduino_units a ON m.arduino_unit_id = a.id
    WHERE m.id = 1
    LIMIT 1
");
$machine = $stmt->fetch(PDO::FETCH_ASSOC);

// Today's stats
$today = date('Y-m-d');
$todayStats = $db->prepare("
    SELECT COUNT(*) AS plays, COALESCE(SUM(amount_paid),0) AS revenue
    FROM plays
    WHERE machine_id = 1 AND DATE(play_date) = :today AND payment_status = 'paid'
");
$todayStats->execute([':today' => $today]);
$today = $todayStats->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Machine — PlayMeter Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body { background:#f4f6f9; font-family:'Segoe UI',sans-serif; }
        .sidebar {
            min-height:100vh;
            background:linear-gradient(135deg,#2c3e50,#1a252f);
            position:fixed; width:260px;
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
        .machine-hero {
            background:linear-gradient(135deg,#1a9e3f,#0a5e25);
            color:#fff; border-radius:16px; padding:30px;
            box-shadow:0 4px 20px rgba(10,94,37,.3); margin-bottom:25px;
        }
        .stat-card {
            background:#fff; border-radius:12px; padding:22px;
            box-shadow:0 2px 10px rgba(0,0,0,.05); margin-bottom:20px;
            transition:transform .3s;
        }
        .stat-card:hover { transform:translateY(-4px); box-shadow:0 5px 20px rgba(0,0,0,.1); }
        .stat-icon { font-size:2.2rem; color:#3498db; }
        .arduino-card {
            background:#fff; border-radius:12px; padding:22px;
            box-shadow:0 2px 10px rgba(0,0,0,.05); margin-bottom:20px;
            border-left:5px solid #3498db;
        }
        .expansion-note {
            background:#fff3cd; border:1px solid #ffc107;
            border-radius:10px; padding:16px 20px; margin-bottom:20px;
        }
        .dot { display:inline-block; width:10px; height:10px; border-radius:50%; margin-right:6px; }
        .dot-green  { background:#27ae60; }
        .dot-grey   { background:#95a5a6; }
        .dot-yellow { background:#f39c12; }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div style="padding:25px 20px;text-align:center;border-bottom:1px solid rgba(255,255,255,.1)">
        <i class="bi bi-joystick" style="font-size:3rem;color:#3498db"></i>
        <h5 class="mt-2 mb-0" style="color:#fff">PlayMeter Pro</h5>
        <p style="font-size:12px;color:rgba(255,255,255,.6);margin-top:5px">
            <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['username']) ?>
        </p>
    </div>
    <nav style="margin-top:20px">
        <a href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="machines.php" class="active"><i class="bi bi-controller"></i> Machine</a>
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

<!-- Content -->
<div class="content">

    <div class="navbar-top d-flex justify-content-between align-items-center">
        <h4 class="mb-0"><i class="bi bi-controller me-2"></i>Machine</h4>
        <span class="badge bg-secondary">Single Machine Mode</span>
    </div>

    <!-- Expansion note -->
    <div class="expansion-note">
        <i class="bi bi-info-circle-fill text-warning me-2"></i>
        <strong>Single Machine Mode:</strong> PlayMeter is currently configured for one machine — FIFA 26.
        Additional machines can be added during your expansion phase.
    </div>

    <!-- FIFA 26 Hero Card -->
    <div class="machine-hero">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <i class="bi bi-controller" style="font-size:3rem"></i>
                    <div>
                        <h2 class="mb-0 fw-bold">FIFA 26</h2>
                        <div style="opacity:.85">Console &nbsp;·&nbsp; Machine #1</div>
                    </div>
                </div>
                <div class="mt-3 d-flex gap-3 flex-wrap">
                    <span class="badge bg-light text-dark fs-6">
                        KSh <?= number_format($machine['price_per_play'], 2) ?> / play
                    </span>
                    <?php if ($machine['active_sessions'] > 0): ?>
                    <span class="badge bg-warning text-dark fs-6">
                        <i class="bi bi-circle-fill" style="font-size:.5rem"></i>
                        <?= $machine['active_sessions'] ?> Active Session
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <?php if ($machine['is_online']): ?>
                    <div style="font-size:1.1rem">
                        <span class="dot dot-green"></span>Online
                    </div>
                <?php else: ?>
                    <div style="font-size:1.1rem">
                        <span class="dot dot-grey"></span>Offline
                    </div>
                <?php endif; ?>
                <div style="opacity:.8;font-size:.9rem;margin-top:4px">
                    Status: <?= ucfirst($machine['status']) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Today's Plays</h6>
                        <h2 class="mb-0"><?= $today['plays'] ?></h2>
                    </div>
                    <div class="stat-icon"><i class="bi bi-play-circle"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Today's Revenue</h6>
                        <h2 class="mb-0 text-success">KSh <?= number_format($today['revenue'], 2) ?></h2>
                    </div>
                    <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">All-Time Plays</h6>
                        <h2 class="mb-0"><?= number_format($machine['total_plays']) ?></h2>
                    </div>
                    <div class="stat-icon"><i class="bi bi-joystick"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Total Revenue</h6>
                        <h2 class="mb-0 text-primary">KSh <?= number_format($machine['total_revenue'], 2) ?></h2>
                    </div>
                    <div class="stat-icon"><i class="bi bi-graph-up-arrow"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Arduino Unit Card -->
    <div class="arduino-card">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h5 class="mb-1"><i class="bi bi-microchip me-2 text-primary"></i>Arduino Unit</h5>
                <div class="text-muted mb-3">Hardware controller for FIFA 26</div>
            </div>
            <?php
            $aState = $machine['arduino_state'] ?? 'unknown';
            $dotClass = match($aState) {
                'idle'    => 'dot-green',
                'playing' => 'dot-yellow',
                default   => 'dot-grey',
            };
            ?>
            <span class="badge bg-light text-dark fs-6">
                <span class="dot <?= $dotClass ?>"></span>
                <?= ucfirst($aState) ?>
            </span>
        </div>
        <div class="row">
            <div class="col-md-3">
                <small class="text-muted d-block">Unit ID</small>
                <strong><?= htmlspecialchars($machine['arduino_unit'] ?? 'ARDUINO_001') ?></strong>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Status</small>
                <strong><?= ucfirst($machine['arduino_status'] ?? 'active') ?></strong>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">State</small>
                <strong><?= ucfirst($aState) ?></strong>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Last Heartbeat</small>
                <strong>
                    <?= $machine['arduino_last_seen']
                        ? date('d M H:i:s', strtotime($machine['arduino_last_seen']))
                        : '—' ?>
                </strong>
            </div>
        </div>
        <div class="mt-3">
            <a href="arduino_settings.php" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-gear"></i> Arduino Settings
            </a>
            <a href="maintenance.php" class="btn btn-sm btn-outline-warning ms-2">
                <i class="bi bi-tools"></i> Log Maintenance Issue
            </a>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="stat-card">
        <h5 class="mb-3">Quick Actions</h5>
        <a href="add_play.php" class="btn btn-success me-2">
            <i class="bi bi-plus-circle"></i> Start New Play
        </a>
        <a href="session_monitor.php" class="btn btn-warning me-2">
            <i class="bi bi-tv"></i> Live Monitor
        </a>
        <a href="plays.php" class="btn btn-outline-primary me-2">
            <i class="bi bi-list"></i> View All Plays
        </a>
        <a href="reports.php" class="btn btn-outline-secondary">
            <i class="bi bi-graph-up"></i> Reports
        </a>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>