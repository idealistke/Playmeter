<?php
// index.php
session_start();
require_once 'config/database.php';

// Check login
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get today's date
$today = date('Y-m-d');

// Fetch statistics
$stats = [];

// Total machines
$query = "SELECT COUNT(*) as total FROM machines WHERE status = 'active'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_machines'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Today's plays
$query = "SELECT COUNT(*) as total FROM plays WHERE DATE(play_date) = :today";
$stmt = $db->prepare($query);
$stmt->bindParam(':today', $today);
$stmt->execute();
$stats['today_plays'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Today's revenue
$query = "SELECT COALESCE(SUM(amount_paid), 0) as total FROM plays WHERE DATE(play_date) = :today";
$stmt = $db->prepare($query);
$stmt->bindParam(':today', $today);
$stmt->execute();
$stats['today_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total revenue all time
$query = "SELECT COALESCE(SUM(amount_paid), 0) as total FROM plays";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Machines in maintenance
$query = "SELECT COUNT(*) as total FROM machines WHERE status = 'maintenance'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['maintenance_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get active sessions count
$query = "SELECT COUNT(*) as total FROM sessions WHERE end_time IS NULL";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['active_sessions'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Recent plays
$query = "SELECT p.*, m.name as machine_name 
          FROM plays p 
          LEFT JOIN machines m ON p.machine_id = m.id 
          ORDER BY p.play_date DESC 
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_plays = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent sessions
$query = "SELECT s.*, m.name as machine_name, c.full_name as customer_name
          FROM sessions s
          LEFT JOIN machines m ON s.machine_id = m.id
          LEFT JOIN customers c ON s.customer_id = c.id
          WHERE s.end_time IS NOT NULL
          ORDER BY s.end_time DESC
          LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - PlayMeter Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #2c3e50 0%, #1a252f 100%);
            color: white;
            position: fixed;
            width: 260px;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        .sidebar a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            padding: 15px 20px;
            display: block;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }
        .sidebar a:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: #3498db;
        }
        .sidebar a.active {
            background: rgba(52, 152, 219, 0.2);
            color: white;
            border-left-color: #3498db;
        }
        .sidebar i {
            margin-right: 10px;
            width: 20px;
        }
        .content {
            margin-left: 260px;
            padding: 20px;
        }
        .navbar-top {
            background: white;
            padding: 15px 25px;
            margin-bottom: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s, box-shadow 0.3s;
            border: none;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .stat-icon {
            font-size: 2.5rem;
            color: #3498db;
        }
        .table-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body>
    <!-- Sidebar - UPDATED with all navigation items -->
    <div class="sidebar">
        <div style="padding: 25px 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1);">
            <i class="bi bi-joystick" style="font-size: 3rem; color: #3498db;"></i>
            <h5 class="mt-2 mb-0">PlayMeter Pro</h5>
            <p style="font-size: 12px; color: rgba(255,255,255,0.6); margin-top: 5px;">
                <i class="bi bi-person-circle"></i> <?php echo $_SESSION['username']; ?>
            </p>
        </div>
        
        <nav style="margin-top: 20px;">
            <a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a href="machines.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'machines.php' ? 'active' : ''; ?>">
                <i class="bi bi-controller"></i> Machines
            </a>
            <a href="plays.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'plays.php' ? 'active' : ''; ?>">
                <i class="bi bi-play-circle"></i> Plays
            </a>
            <a href="customers.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'customers.php' ? 'active' : ''; ?>">
                <i class="bi bi-people"></i> Customers
            </a>
            <a href="session_monitor.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'session_monitor.php' ? 'active' : ''; ?>">
                <i class="bi bi-tv"></i> Live Monitor
            </a>
            <a href="reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                <i class="bi bi-graph-up"></i> Reports
            </a>
            <a href="maintenance.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'maintenance.php' ? 'active' : ''; ?>">
                <i class="bi bi-tools"></i> Maintenance
            </a>
            <a href="arduino_settings.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'arduino_settings.php' ? 'active' : ''; ?>">
                <i class="bi bi-microchip"></i> Arduino
            </a>
            <a href="profile.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                <i class="bi bi-person-circle"></i> Profile
            </a>
            <a href="logout.php" style="border-top: 1px solid rgba(255,255,255,0.1); margin-top: 20px; position: absolute; bottom: 0; width: 100%;">
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
                <span class="badge bg-primary me-2"><?php echo date('l, F j, Y'); ?></span>
                <span class="badge bg-success"><?php echo $stats['active_sessions']; ?> Active Sessions</span>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Total Machines</h6>
                            <h2 class="mb-0"><?php echo $stats['total_machines']; ?></h2>
                        </div>
                        <div class="stat-icon">
                            <i class="bi bi-controller"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Today's Plays</h6>
                            <h2 class="mb-0"><?php echo $stats['today_plays']; ?></h2>
                        </div>
                        <div class="stat-icon">
                            <i class="bi bi-play-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Today's Revenue</h6>
                            <h2 class="mb-0 text-success">KSh <?php echo number_format($stats['today_revenue'], 2); ?></h2>
                        </div>
                        <div class="stat-icon">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Maintenance</h6>
                            <h2 class="mb-0 text-warning"><?php echo $stats['maintenance_count']; ?></h2>
                        </div>
                        <div class="stat-icon">
                            <i class="bi bi-tools"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="table-container">
                    <h5 class="mb-3">Quick Actions</h5>
                    <a href="add_play.php" class="btn btn-primary me-2">
                        <i class="bi bi-plus-circle"></i> Record Play
                    </a>
                    <a href="add_machine.php" class="btn btn-success me-2">
                        <i class="bi bi-plus-circle"></i> Add Machine
                    </a>
                    <a href="add_customer.php" class="btn btn-info text-white me-2">
                        <i class="bi bi-person-plus"></i> Add Customer
                    </a>
                    <a href="session_monitor.php" class="btn btn-warning">
                        <i class="bi bi-tv"></i> Live Monitor
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Data -->
        <div class="row">
            <div class="col-md-6">
                <div class="table-container">
                    <h5 class="mb-3">Recent Plays</h5>
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Machine</th>
                                <th>Player</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recent_plays as $play): ?>
                            <tr>
                                <td><?php echo date('H:i', strtotime($play['play_date'])); ?></td>
                                <td><?php echo htmlspecialchars($play['machine_name']); ?></td>
                                <td><?php echo htmlspecialchars($play['player_name']); ?></td>
                                <td>KSh <?php echo number_format($play['amount_paid'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-md-6">
                <div class="table-container">
                    <h5 class="mb-3">Recent Sessions</h5>
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Machine</th>
                                <th>Customer</th>
                                <th>Duration</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recent_sessions as $session): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($session['machine_name']); ?></td>
                                <td><?php echo htmlspecialchars($session['customer_name'] ?? 'Guest'); ?></td>
                                <td><?php echo number_format($session['duration_minutes'] ?? 0, 1); ?> min</td>
                                <td>KSh <?php echo number_format($session['total_cost'] ?? 0, 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>