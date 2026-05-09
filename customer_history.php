<?php
// customer_history.php
session_start();
require_once 'config/database.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$customer_id = isset($_GET['id']) ? $_GET['id'] : 0;

// Get customer details
$query = "SELECT * FROM customers WHERE id = $customer_id";
$result = $db->query($query);
$customer = $result->fetch(PDO::FETCH_ASSOC);

if(!$customer) {
    header("Location: customers.php");
    exit();
}

// Get customer's sessions
$sessions = $db->query("
    SELECT s.*, m.name as machine_name
    FROM sessions s
    LEFT JOIN machines m ON s.machine_id = m.id
    WHERE s.customer_id = $customer_id
    ORDER BY s.start_time DESC
");

// Get customer's plays
$plays = $db->query("
    SELECT p.*, m.name as machine_name
    FROM plays p
    LEFT JOIN machines m ON p.machine_id = m.id
    WHERE p.customer_id = $customer_id
    ORDER BY p.play_date DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer History - PlayMeter</title>
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
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .navbar-top {
            background: white;
            padding: 15px 25px;
            margin-bottom: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .nav-tabs {
            margin-bottom: 20px;
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
            <h4 class="mb-0">Customer History: <?php echo htmlspecialchars($customer['full_name']); ?></h4>
            <a href="customers.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>

        <!-- Customer Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <h6>Total Visits</h6>
                    <h3><?php echo $customer['total_visits']; ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <h6>Total Spent</h6>
                    <h3 class="text-success">KSh <?php echo number_format($customer['total_spent'], 2); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <h6>Current Balance</h6>
                    <h3 class="<?php echo $customer['balance'] > 0 ? 'text-warning' : 'text-success'; ?>">
                        KSh <?php echo number_format($customer['balance'], 2); ?>
                    </h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <h6>Customer Since</h6>
                    <h6><?php echo date('d M Y', strtotime($customer['created_at'])); ?></h6>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs" id="historyTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="sessions-tab" data-bs-toggle="tab" data-bs-target="#sessions" type="button" role="tab">Sessions</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="plays-tab" data-bs-toggle="tab" data-bs-target="#plays" type="button" role="tab">Plays</button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="historyTabContent">
            <!-- Sessions Tab -->
            <div class="tab-pane fade show active" id="sessions" role="tabpanel">
                <div class="stats-card">
                    <h5 class="mb-3">Session History</h5>
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Machine</th>
                                <th>Duration</th>
                                <th>Cost</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($session = $sessions->fetch(PDO::FETCH_ASSOC)): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($session['start_time'])); ?></td>
                                <td><?php echo $session['machine_name']; ?></td>
                                <td><?php echo $session['duration_minutes'] ? number_format($session['duration_minutes'], 1) . ' min' : 'Active'; ?></td>
                                <td>KSh <?php echo number_format($session['total_cost'] ?: 0, 2); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $session['payment_status'] == 'paid' ? 'success' : 
                                            ($session['payment_status'] == 'pending' ? 'warning' : 'secondary'); 
                                    ?>">
                                        <?php echo ucfirst($session['payment_status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Plays Tab -->
            <div class="tab-pane fade" id="plays" role="tabpanel">
                <div class="stats-card">
                    <h5 class="mb-3">Play History</h5>
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Machine</th>
                                <th>Player</th>
                                <th>Plays</th>
                                <th>Amount</th>
                                <th>Payment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($play = $plays->fetch(PDO::FETCH_ASSOC)): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($play['play_date'])); ?></td>
                                <td><?php echo $play['machine_name']; ?></td>
                                <td><?php echo $play['player_name']; ?></td>
                                <td><?php echo $play['plays_count']; ?></td>
                                <td>KSh <?php echo number_format($play['amount_paid'], 2); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $play['payment_method'] == 'cash' ? 'success' : 
                                            ($play['payment_method'] == 'card' ? 'info' : 'warning'); 
                                    ?>">
                                        <?php echo ucfirst($play['payment_method']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>