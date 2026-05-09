<?php
// machines.php
session_start();
require_once 'config/database.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get all machines with their Arduino units
$query = "SELECT m.*, a.unit_id as arduino_unit, 
          (SELECT COUNT(*) FROM sessions WHERE machine_id = m.id AND end_time IS NULL) as active_sessions
          FROM machines m
          LEFT JOIN arduino_units a ON m.arduino_unit_id = a.id
          ORDER BY m.name";
$machines = $db->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Machines - PlayMeter</title>
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
        .machine-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s;
            border-left: 5px solid #3498db;
        }
        .machine-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
        }
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        .status-maintenance {
            background: #fff3cd;
            color: #856404;
        }
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
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
            <h4 class="mb-0">Machines Management</h4>
            <div>
                <a href="add_machine.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Add Machine
                </a>
            </div>
        </div>

        <!-- Machines Grid -->
        <div class="row">
            <?php while($machine = $machines->fetch(PDO::FETCH_ASSOC)): ?>
            <div class="col-md-4">
                <div class="machine-card">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h5 class="mb-0"><?php echo htmlspecialchars($machine['name']); ?></h5>
                        <span class="status-badge status-<?php echo $machine['status']; ?>">
                            <?php echo ucfirst($machine['status']); ?>
                        </span>
                    </div>
                    
                    <p class="text-muted mb-2">
                        <i class="bi bi-tag"></i> <?php echo $machine['machine_type']; ?><br>
                        <i class="bi bi-cpu"></i> Arduino: <?php echo $machine['arduino_unit'] ?? 'Not assigned'; ?>
                    </p>
                    
                    <div class="row text-center mb-3">
                        <div class="col-4">
                            <small>Price/Play</small>
                            <h6 class="text-primary">KSh <?php echo $machine['price_per_play']; ?></h6>
                        </div>
                        <div class="col-4">
                            <small>Total Plays</small>
                            <h6><?php echo $machine['total_plays']; ?></h6>
                        </div>
                        <div class="col-4">
                            <small>Revenue</small>
                            <h6 class="text-success">KSh <?php echo number_format($machine['total_revenue'], 2); ?></h6>
                        </div>
                    </div>
                    
                    <?php if($machine['active_sessions'] > 0): ?>
                    <div class="alert alert-warning py-1 px-2 mb-2">
                        <small><i class="bi bi-exclamation-triangle"></i> <?php echo $machine['active_sessions']; ?> active session(s)</small>
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-between">
                        <a href="edit_machine.php?id=<?php echo $machine['id']; ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                        <a href="session_monitor.php?machine=<?php echo $machine['id']; ?>" class="btn btn-sm btn-outline-success">
                            <i class="bi bi-play-circle"></i> Monitor
                        </a>
                        <a href="maintenance.php?machine=<?php echo $machine['id']; ?>" class="btn btn-sm btn-outline-warning">
                            <i class="bi bi-tools"></i> Maintain
                        </a>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</body>
</html>