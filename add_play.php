<?php
// add_play.php
session_start();
require_once 'config/database.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();
$message = '';

// Get selected machine if any
$selected_machine = isset($_GET['machine_id']) ? $_GET['machine_id'] : '';

// Get all machines for dropdown
$machines = $db->query("SELECT id, name, price_per_play FROM machines WHERE status = 'active' ORDER BY name");

// Get customers for dropdown
$customers = $db->query("SELECT id, full_name, customer_code FROM customers ORDER BY full_name");

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $machine_id = $_POST['machine_id'];
    $player_name = $_POST['player_name'];
    $customer_id = $_POST['customer_id'] ?: 'NULL';
    $plays_count = $_POST['plays_count'];
    $amount_paid = $_POST['amount_paid'];
    $payment_method = $_POST['payment_method'];
    
    // Insert play
    $query = "INSERT INTO plays (machine_id, player_name, customer_id, plays_count, amount_paid, payment_method) 
              VALUES ($machine_id, '$player_name', $customer_id, $plays_count, $amount_paid, '$payment_method')";
    
    if($db->exec($query)) {
        // Update machine stats
        $update = "UPDATE machines SET total_plays = total_plays + $plays_count, 
                   total_revenue = total_revenue + $amount_paid WHERE id = $machine_id";
        $db->exec($update);
        
        $message = '<div class="alert alert-success">Play recorded successfully!</div>';
    } else {
        $message = '<div class="alert alert-danger">Error recording play!</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Play - PlayMeter</title>
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
            <h4 class="mb-0">Record New Play</h4>
        </div>
        
        <?php echo $message; ?>
        
        <div class="form-container">
            <form method="POST" id="playForm">
                <div class="mb-3">
                    <label for="machine_id" class="form-label">Select Machine</label>
                    <select class="form-control" id="machine_id" name="machine_id" required>
                        <option value="">Choose machine...</option>
                        <?php while($machine = $machines->fetch(PDO::FETCH_ASSOC)): ?>
                        <option value="<?php echo $machine['id']; ?>" 
                                data-price="<?php echo $machine['price_per_play']; ?>"
                                <?php echo ($selected_machine == $machine['id']) ? 'selected' : ''; ?>>
                            <?php echo $machine['name']; ?> - KSh <?php echo $machine['price_per_play']; ?>/play
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="customer_id" class="form-label">Select Customer (Optional)</label>
                    <select class="form-control" id="customer_id" name="customer_id">
                        <option value="">Walk-in Customer</option>
                        <?php while($customer = $customers->fetch(PDO::FETCH_ASSOC)): ?>
                        <option value="<?php echo $customer['id']; ?>">
                            <?php echo $customer['full_name']; ?> (<?php echo $customer['customer_code']; ?>)
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="player_name" class="form-label">Player Name</label>
                    <input type="text" class="form-control" id="player_name" name="player_name" required>
                </div>
                
                <div class="mb-3">
                    <label for="plays_count" class="form-label">Number of Plays</label>
                    <input type="number" class="form-control" id="plays_count" name="plays_count" min="1" value="1" required>
                </div>
                
                <div class="mb-3">
                    <label for="amount_paid" class="form-label">Amount Paid (KSh)</label>
                    <input type="number" step="0.01" class="form-control" id="amount_paid" name="amount_paid" required>
                </div>
                
                <div class="mb-3">
                    <label for="payment_method" class="form-label">Payment Method</label>
                    <select class="form-control" id="payment_method" name="payment_method" required>
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                        <option value="token">Token</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">Record Play</button>
                <a href="plays.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>

    <script>
        // Auto-calculate amount based on machine and plays
        document.getElementById('machine_id').addEventListener('change', calculateAmount);
        document.getElementById('plays_count').addEventListener('input', calculateAmount);
        
        function calculateAmount() {
            var machineSelect = document.getElementById('machine_id');
            var playsCount = document.getElementById('plays_count').value;
            
            if(machineSelect.selectedIndex > 0) {
                var selectedOption = machineSelect.options[machineSelect.selectedIndex];
                var price = parseFloat(selectedOption.dataset.price);
                var amount = price * playsCount;
                document.getElementById('amount_paid').value = amount.toFixed(2);
            }
        }
        
        window.onload = function() {
            calculateAmount();
        };
    </script>
</body>
</html>