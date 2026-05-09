<?php
// reports.php
session_start();
require_once 'config/database.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get daily revenue for last 30 days
$daily_revenue = $db->query("
    SELECT DATE(play_date) as date, COUNT(*) as plays, SUM(amount_paid) as revenue
    FROM plays 
    WHERE play_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(play_date)
    ORDER BY date DESC
");

// Get top machines
$top_machines = $db->query("
    SELECT m.name, COUNT(p.id) as plays, SUM(p.amount_paid) as revenue
    FROM machines m
    LEFT JOIN plays p ON m.id = p.machine_id
    GROUP BY m.id, m.name
    ORDER BY revenue DESC
    LIMIT 10
");

// Get session statistics
$session_stats = $db->query("
    SELECT 
        COUNT(*) as total_sessions,
        AVG(duration_minutes) as avg_duration,
        SUM(total_cost) as total_revenue,
        AVG(total_cost) as avg_cost
    FROM sessions
    WHERE end_time IS NOT NULL
    AND start_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
")->fetch(PDO::FETCH_ASSOC);

// Get payment method distribution
$payment_methods = $db->query("
    SELECT payment_method, COUNT(*) as count, SUM(amount_paid) as total
    FROM plays
    GROUP BY payment_method
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - PlayMeter</title>
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
        .report-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .stat-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        .export-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
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
        <div class="navbar-top d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Reports & Analytics</h4>
            <div>
                <span class="badge bg-info">Last 30 Days</span>
            </div>
        </div>

        <!-- Summary Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-box">
                    <h6>Total Sessions</h6>
                    <h3><?php echo number_format($session_stats['total_sessions'] ?? 0); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box">
                    <h6>Avg Duration</h6>
                    <h3><?php echo number_format($session_stats['avg_duration'] ?? 0, 1); ?> min</h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box">
                    <h6>Total Revenue</h6>
                    <h3 class="text-success">KSh <?php echo number_format($session_stats['total_revenue'] ?? 0, 2); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box">
                    <h6>Avg Cost/Session</h6>
                    <h3>KSh <?php echo number_format($session_stats['avg_cost'] ?? 0, 2); ?></h3>
                </div>
            </div>
        </div>

        <!-- Daily Revenue -->
        <div class="report-card">
            <h5 class="mb-3">Daily Revenue (Last 30 Days)</h5>
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Plays</th>
                        <th>Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $daily_revenue->fetch(PDO::FETCH_ASSOC)): ?>
                    <tr>
                        <td><?php echo date('d M Y', strtotime($row['date'])); ?></td>
                        <td><?php echo $row['plays']; ?></td>
                        <td>KSh <?php echo number_format($row['revenue'] ?? 0, 2); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Top Machines and Payment Methods -->
        <div class="row">
            <div class="col-md-6">
                <div class="report-card">
                    <h5 class="mb-3">Top Performing Machines</h5>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Machine</th>
                                <th>Plays</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $top_machines->fetch(PDO::FETCH_ASSOC)): ?>
                            <tr>
                                <td><?php echo $row['name']; ?></td>
                                <td><?php echo $row['plays'] ?? 0; ?></td>
                                <td>KSh <?php echo number_format($row['revenue'] ?? 0, 2); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-md-6">
                <div class="report-card">
                    <h5 class="mb-3">Payment Methods</h5>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Method</th>
                                <th>Count</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $payment_methods->fetch(PDO::FETCH_ASSOC)): ?>
                            <tr>
                                <td><span class="badge bg-<?php 
                                    echo $row['payment_method'] == 'cash' ? 'success' : 
                                        ($row['payment_method'] == 'card' ? 'info' : 'warning'); 
                                ?>"><?php echo ucfirst($row['payment_method']); ?></span></td>
                                <td><?php echo $row['count']; ?></td>
                                <td>KSh <?php echo number_format($row['total'] ?? 0, 2); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Export Buttons -->
        <div class="export-btn">
            <div class="btn-group dropup">
                <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-download"></i> Export Data
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="export_data.php?type=sessions">Export Sessions</a></li>
                    <li><a class="dropdown-item" href="export_data.php?type=machines">Export Machines</a></li>
                    <li><a class="dropdown-item" href="export_data.php?type=customers">Export Customers</a></li>
                    <li><a class="dropdown-item" href="export_data.php?type=revenue">Export Revenue</a></li>
                </ul>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>