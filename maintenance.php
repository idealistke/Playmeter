<?php
// maintenance.php
session_start();
require_once 'config/database.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Handle form submission
$message = '';
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_maintenance'])) {
    $machine_id = $_POST['machine_id'];
    $issue = $_POST['issue'];
    
    $query = "INSERT INTO maintenance (machine_id, issue_description) VALUES ($machine_id, '$issue')";
    if($db->exec($query)) {
        $message = '<div class="alert alert-success">Maintenance issue reported successfully!</div>';
        
        // Update machine status
        $db->exec("UPDATE machines SET status = 'maintenance' WHERE id = $machine_id");
    } else {
        $message = '<div class="alert alert-danger">Error reporting issue!</div>';
    }
}

// Handle resolve
if(isset($_GET['resolve'])) {
    $id = $_GET['resolve'];
    $db->exec("UPDATE maintenance SET resolved = TRUE, resolved_date = NOW() WHERE id = $id");
    $message = '<div class="alert alert-success">Issue marked as resolved!</div>';
}

// Get all maintenance records
$query = "SELECT m.*, mac.name as machine_name 
          FROM maintenance m 
          LEFT JOIN machines mac ON m.machine_id = mac.id 
          ORDER BY m.reported_date DESC";
$maintenance = $db->query($query);

// Get machines for dropdown
$machines = $db->query("SELECT id, name FROM machines WHERE status = 'active' OR status = 'maintenance'");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance - PlayMeter</title>
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
        .card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: none;
        }
        .issue-pending {
            border-left: 5px solid #ffc107;
        }
        .issue-resolved {
            border-left: 5px solid #28a745;
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
            <h4 class="mb-0">Maintenance Management</h4>
        </div>

        <?php echo $message; ?>

        <!-- Add Maintenance Form -->
        <div class="card">
            <h5 class="mb-3">Report New Issue</h5>
            <form method="POST">
                <div class="row">
                    <div class="col-md-5">
                        <select name="machine_id" class="form-control" required>
                            <option value="">Select Machine</option>
                            <?php while($machine = $machines->fetch(PDO::FETCH_ASSOC)): ?>
                            <option value="<?php echo $machine['id']; ?>">
                                <?php echo htmlspecialchars($machine['name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <input type="text" name="issue" class="form-control" placeholder="Describe the issue" required>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" name="add_maintenance" class="btn btn-primary w-100">
                            <i class="bi bi-plus-circle"></i> Report
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Maintenance Records -->
        <div class="card">
            <h5 class="mb-3">Maintenance History</h5>
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Machine</th>
                        <th>Issue</th>
                        <th>Status</th>
                        <th>Resolved</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $maintenance->fetch(PDO::FETCH_ASSOC)): ?>
                    <tr class="<?php echo $row['resolved'] ? 'issue-resolved' : 'issue-pending'; ?>">
                        <td><?php echo date('d/m/Y H:i', strtotime($row['reported_date'])); ?></td>
                        <td><?php echo htmlspecialchars($row['machine_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['issue_description']); ?></td>
                        <td>
                            <?php if($row['resolved']): ?>
                                <span class="badge bg-success">Resolved</span>
                            <?php else: ?>
                                <span class="badge bg-warning">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo $row['resolved_date'] ? date('d/m/Y', strtotime($row['resolved_date'])) : '-'; ?>
                        </td>
                        <td>
                            <?php if(!$row['resolved']): ?>
                                <a href="?resolve=<?php echo $row['id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('Mark this issue as resolved?')">
                                    <i class="bi bi-check-circle"></i> Resolve
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>