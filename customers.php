<?php
// customers.php
session_start();
require_once 'config/database.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get all customers
$query = "SELECT * FROM customers ORDER BY created_at DESC";
$customers = $db->query($query);

// Get customer statistics
$stats = $db->query("
    SELECT 
        COUNT(*) as total_customers,
        SUM(total_visits) as total_visits,
        SUM(total_spent) as total_spent,
        AVG(balance) as avg_balance
    FROM customers
")->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers - PlayMeter</title>
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
        .customer-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s;
            border-left: 5px solid #3498db;
        }
        .customer-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .qr-code {
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .qr-code img {
            max-width: 120px;
        }
        .stat-card {
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
        <div class="navbar-top d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Customer Management</h4>
            <div>
                <a href="add_customer.php" class="btn btn-primary">
                    <i class="bi bi-person-plus"></i> Add New Customer
                </a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <h6>Total Customers</h6>
                    <h3><?php echo $stats['total_customers'] ?? 0; ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <h6>Total Visits</h6>
                    <h3><?php echo $stats['total_visits'] ?? 0; ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <h6>Total Spent</h6>
                    <h3 class="text-success">KSh <?php echo number_format($stats['total_spent'] ?? 0, 2); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <h6>Avg Balance</h6>
                    <h3>KSh <?php echo number_format($stats['avg_balance'] ?? 0, 2); ?></h3>
                </div>
            </div>
        </div>

        <!-- Customers Grid -->
        <div class="row">
            <?php if($customers->rowCount() > 0): ?>
                <?php while($customer = $customers->fetch(PDO::FETCH_ASSOC)): ?>
                <div class="col-md-4">
                    <div class="customer-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <h5><?php echo htmlspecialchars($customer['full_name']); ?></h5>
                            <span class="badge bg-info">#<?php echo $customer['customer_code']; ?></span>
                        </div>
                        
                        <p class="text-muted mb-2">
                            <i class="bi bi-telephone"></i> <?php echo $customer['phone_number'] ?: 'No phone'; ?><br>
                            <i class="bi bi-envelope"></i> <?php echo $customer['email'] ?: 'No email'; ?>
                        </p>
                        
                        <div class="row text-center mb-3">
                            <div class="col-4">
                                <small>Visits</small>
                                <h6><?php echo $customer['total_visits']; ?></h6>
                            </div>
                            <div class="col-4">
                                <small>Balance</small>
                                <h6 class="<?php echo $customer['balance'] > 0 ? 'text-warning' : 'text-success'; ?>">
                                    KSh <?php echo number_format($customer['balance'], 2); ?>
                                </h6>
                            </div>
                            <div class="col-4">
                                <small>Spent</small>
                                <h6>KSh <?php echo number_format($customer['total_spent'], 2); ?></h6>
                            </div>
                        </div>
                        
                        <div class="qr-code mb-3">
                            <?php if($customer['qr_code']): ?>
                                <div class="bg-light p-2 rounded">
                                    <small class="text-muted">QR Data: <?php echo $customer['qr_code']; ?></small>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No QR code generated</p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="generate_qr.php?customer_id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-qr-code"></i> Generate QR
                            </a>
                            <a href="customer_history.php?id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-outline-success">
                                <i class="bi bi-clock-history"></i> History
                            </a>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        <i class="bi bi-info-circle"></i> No customers found. 
                        <a href="add_customer.php" class="alert-link">Add your first customer</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>