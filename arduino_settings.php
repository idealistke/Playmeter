<?php
// arduino_settings.php
session_start();
require_once 'config/database.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Handle form submissions
$message = '';
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['add_arduino'])) {
        $unit_id = $_POST['unit_id'];
        $machine_id = $_POST['machine_id'] ?: 'NULL';
        $firmware = $_POST['firmware_version'];
        
        $query = "INSERT INTO arduino_units (unit_id, machine_id, firmware_version, status, last_seen) 
                  VALUES ('$unit_id', $machine_id, '$firmware', 'active', NOW())";
        
        if($db->exec($query)) {
            $message = '<div class="alert alert-success">Arduino unit added successfully!</div>';
        } else {
            $message = '<div class="alert alert-danger">Error adding Arduino unit.</div>';
        }
    }
    
    if(isset($_POST['send_command'])) {
        $arduino_id = $_POST['arduino_id'];
        $command = $_POST['command'];
        $parameters = $_POST['parameters'];
        
        $query = "INSERT INTO arduino_commands (arduino_unit_id, command, parameters, status) 
                  VALUES ($arduino_id, '$command', '$parameters', 'pending')";
        
        if($db->exec($query)) {
            $message = '<div class="alert alert-success">Command sent to Arduino!</div>';
        }
    }
}

// Get all Arduino units
$arduinos = $db->query("
    SELECT a.*, m.name as machine_name 
    FROM arduino_units a 
    LEFT JOIN machines m ON a.machine_id = m.id 
    ORDER BY a.last_seen DESC
");

// Get all machines for dropdown
$machines = $db->query("SELECT id, name FROM machines WHERE status = 'active'");

// Get pending commands
$pending_commands = $db->query("
    SELECT c.*, a.unit_id 
    FROM arduino_commands c
    JOIN arduino_units a ON c.arduino_unit_id = a.id
    WHERE c.status = 'pending'
    ORDER BY c.created_at ASC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arduino Settings - PlayMeter</title>
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
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.9em;
        }
        .online {
            background: #d4edda;
            color: #155724;
        }
        .offline {
            background: #f8d7da;
            color: #721c24;
        }
        .arduino-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 5px solid #3498db;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: none;
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
        <div class="navbar-top">
            <h4 class="mb-0">Arduino Management</h4>
        </div>
        
        <?php echo $message; ?>

        <!-- Add Arduino Form -->
        <div class="card">
            <h5 class="mb-3">Register New Arduino Unit</h5>
            <form method="POST" class="row g-3">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="unit_id" placeholder="Unit ID (e.g., ARDUINO_001)" required>
                </div>
                <div class="col-md-3">
                    <select class="form-control" name="machine_id">
                        <option value="">Assign to Machine (Optional)</option>
                        <?php while($machine = $machines->fetch(PDO::FETCH_ASSOC)): ?>
                        <option value="<?php echo $machine['id']; ?>"><?php echo $machine['name']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="text" class="form-control" name="firmware_version" placeholder="Firmware Version" value="1.0.0">
                </div>
                <div class="col-md-2">
                    <button type="submit" name="add_arduino" class="btn btn-primary w-100">Add Unit</button>
                </div>
            </form>
        </div>

        <!-- Arduino Units List -->
        <h5 class="mb-3">Connected Arduino Units</h5>
        <div class="row">
            <?php while($arduino = $arduinos->fetch(PDO::FETCH_ASSOC)): 
                $is_online = $arduino['last_seen'] && (time() - strtotime($arduino['last_seen'])) < 300; // 5 minutes
            ?>
            <div class="col-md-6">
                <div class="arduino-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5><?php echo $arduino['unit_id']; ?></h5>
                            <p class="mb-1">
                                <strong>Machine:</strong> <?php echo $arduino['machine_name'] ?: 'Not assigned'; ?><br>
                                <strong>Firmware:</strong> <?php echo $arduino['firmware_version']; ?><br>
                                <strong>Last Seen:</strong> <?php echo $arduino['last_seen'] ? date('d/m/Y H:i:s', strtotime($arduino['last_seen'])) : 'Never'; ?>
                            </p>
                        </div>
                        <div>
                            <span class="status-badge <?php echo $is_online ? 'online' : 'offline'; ?>">
                                <?php echo $is_online ? 'Online' : 'Offline'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Command Form -->
                    <form method="POST" class="mt-3">
                        <input type="hidden" name="arduino_id" value="<?php echo $arduino['id']; ?>">
                        <div class="input-group">
                            <select class="form-control" name="command" required>
                                <option value="">Send Command...</option>
                                <option value="RESTART">Restart Arduino</option>
                                <option value="POWER_ON">Power On Console</option>
                                <option value="POWER_OFF">Power Off Console</option>
                                <option value="RESET_SESSION">Reset Session</option>
                                <option value="GET_STATUS">Get Status</option>
                                <option value="UPDATE_RATE">Update Rate</option>
                            </select>
                            <input type="text" class="form-control" name="parameters" placeholder="Parameters (optional)">
                            <button type="submit" name="send_command" class="btn btn-outline-primary">Send</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endwhile; ?>
        </div>

        <!-- Pending Commands -->
        <?php if($pending_commands->rowCount() > 0): ?>
        <div class="card mt-4">
            <h5 class="mb-3">Pending Commands</h5>
            <table class="table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Arduino</th>
                        <th>Command</th>
                        <th>Parameters</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($cmd = $pending_commands->fetch(PDO::FETCH_ASSOC)): ?>
                    <tr>
                        <td><?php echo date('H:i:s', strtotime($cmd['created_at'])); ?></td>
                        <td><?php echo $cmd['unit_id']; ?></td>
                        <td><code><?php echo $cmd['command']; ?></code></td>
                        <td><?php echo $cmd['parameters']; ?></td>
                        <td><span class="badge bg-warning">Pending</span></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>