<?php
// add_customer.php
session_start();
require_once 'config/database.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();
$message = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = $_POST['full_name'];
    $phone_number = $_POST['phone_number'];
    $email = $_POST['email'];
    $initial_balance = $_POST['initial_balance'] ?: 0;
    
    // Generate unique customer code
    $customer_code = 'CUST_' . time() . '_' . rand(100, 999);
    
    // Generate QR code data
    $qr_data = "PLAYMETER:{$customer_code}";
    
    $query = "INSERT INTO customers (customer_code, full_name, phone_number, email, balance, qr_code) 
              VALUES (:code, :name, :phone, :email, :balance, :qr)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':code', $customer_code);
    $stmt->bindParam(':name', $full_name);
    $stmt->bindParam(':phone', $phone_number);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':balance', $initial_balance);
    $stmt->bindParam(':qr', $qr_data);
    
    if($stmt->execute()) {
        $message = '<div class="alert alert-success">Customer added successfully! Customer Code: ' . $customer_code . '</div>';
    } else {
        $error = '<div class="alert alert-danger">Error adding customer.</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Customer - PlayMeter</title>
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
        .form-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            max-width: 600px;
            margin: 0 auto;
        }
        .navbar-top {
            background: white;
            padding: 15px 25px;
            margin-bottom: 25px;
            border-radius: 12px;
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
        <div class="navbar-top">
            <h4 class="mb-0">Add New Customer</h4>
        </div>
        
        <?php echo $message; ?>
        <?php echo $error; ?>
        
        <div class="form-container">
            <form method="POST" onsubmit="return validateForm()">
                <div class="mb-3">
                    <label for="full_name" class="form-label">Full Name *</label>
                    <input type="text" class="form-control" id="full_name" name="full_name" required>
                </div>
                
                <div class="mb-3">
                    <label for="phone_number" class="form-label">Phone Number</label>
                    <input type="tel" class="form-control" id="phone_number" name="phone_number" 
                           placeholder="e.g., 0712345678">
                </div>
                
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email">
                </div>
                
                <div class="mb-3">
                    <label for="initial_balance" class="form-label">Initial Balance (KSh)</label>
                    <input type="number" step="0.01" class="form-control" id="initial_balance" 
                           name="initial_balance" value="0">
                    <small class="text-muted">Optional: Add prepaid balance</small>
                </div>
                
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> 
                    A unique QR code will be generated for this customer after creation.
                </div>
                
                <button type="submit" class="btn btn-primary">Add Customer</button>
                <a href="customers.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>

    <script>
        function validateForm() {
            let name = document.getElementById('full_name').value.trim();
            if(name === '') {
                alert('Please enter customer name');
                return false;
            }
            return true;
        }
    </script>
</body>
</html>