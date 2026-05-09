<?php
// session_monitor.php - WITH 6 SESSIONS VISIBLE & NO NEGATIVES
session_start();
require_once 'config/database.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Handle Stop All Sessions action
$message = '';
if(isset($_POST['stop_all_sessions'])) {
    // Get all active sessions
    $query = "SELECT * FROM sessions WHERE end_time IS NULL";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $active_sessions_to_stop = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stopped_count = 0;
    $total_revenue = 0;
    
    foreach($active_sessions_to_stop as $session) {
        $session_id = $session['id'];
        $start_time = strtotime($session['start_time']);
        $now = time();
        $elapsed_minutes = max(0, ($now - $start_time) / 60); // Ensure no negative
        $rate = $session['rate_per_minute'] ?? 2.00;
        
        // Get customer tier for discount
        if($session['customer_id']) {
            $cust_query = "SELECT total_visits FROM customers WHERE id = " . $session['customer_id'];
            $cust_stmt = $db->prepare($cust_query);
            $cust_stmt->execute();
            $customer = $cust_stmt->fetch(PDO::FETCH_ASSOC);
            $visits = $customer['total_visits'] ?? 0;
            
            // Apply discount based on visits
            if ($visits >= 50) $discount = 0.20; // Platinum - 20% off
            elseif ($visits >= 30) $discount = 0.15; // Gold - 15% off
            elseif ($visits >= 15) $discount = 0.10; // Silver - 10% off
            elseif ($visits >= 5) $discount = 0.05; // Bronze - 5% off
            else $discount = 0;
            
            $total_cost = ($elapsed_minutes * $rate) * (1 - $discount);
        } else {
            $total_cost = $elapsed_minutes * $rate;
        }
        
        // Update session
        $update = "UPDATE sessions SET 
                   end_time = NOW(), 
                   duration_minutes = $elapsed_minutes, 
                   total_cost = $total_cost,
                   payment_status = 'paid'
                   WHERE id = $session_id";
        $db->exec($update);
        
        // Update customer stats if customer exists
        if($session['customer_id']) {
            $update_cust = "UPDATE customers SET 
                           total_visits = total_visits + 1,
                           total_spent = total_spent + $total_cost
                           WHERE id = " . $session['customer_id'];
            $db->exec($update_cust);
        }
        
        // Update machine status
        $update_machine = "UPDATE machines SET current_session_id = NULL WHERE id = " . $session['machine_id'];
        $db->exec($update_machine);
        
        $stopped_count++;
        $total_revenue += $total_cost;
    }
    
    $message = '<div class="alert alert-success">
                <i class="bi bi-check-circle-fill me-2"></i>
                Successfully ended ' . $stopped_count . ' active sessions. Total revenue: KSh ' . number_format($total_revenue, 2) . '
                </div>';
}

// Get active sessions with customer names and visit counts
$query = "SELECT s.*, 
          m.name as machine_name,
          c.full_name as customer_name,
          c.customer_code,
          c.total_visits,
          c.total_spent
          FROM sessions s 
          LEFT JOIN machines m ON s.machine_id = m.id 
          LEFT JOIN customers c ON s.customer_id = c.id
          WHERE s.end_time IS NULL 
          ORDER BY s.start_time DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$active_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count active sessions
$total_active = count($active_sessions);

// Calculate potential revenue if all sessions ended now (with NO negatives)
$potential_revenue = 0;
$now = time();
foreach($active_sessions as $session) {
    $start = strtotime($session['start_time']);
    $elapsed = max(0, ($now - $start) / 60); // Ensure no negative
    $rate = $session['rate_per_minute'] ?? 2.00;
    $potential_revenue += $elapsed * $rate;
}

// Function to determine customer tier based on visits
function getCustomerTier($visits) {
    if ($visits >= 50) return 'platinum';
    if ($visits >= 30) return 'gold';
    if ($visits >= 15) return 'silver';
    if ($visits >= 5) return 'bronze';
    return 'regular';
}

// Function to get token badge details
function getTokenBadge($tier) {
    switch($tier) {
        case 'platinum':
            return [
                'icon' => 'bi-gem',
                'color' => '#8e44ad',
                'bg' => '#f3e5f5',
                'text' => 'PLATINUM',
                'discount' => '20'
            ];
        case 'gold':
            return [
                'icon' => 'bi-award',
                'color' => '#f39c12',
                'bg' => '#fff3e0',
                'text' => 'GOLD',
                'discount' => '15'
            ];
        case 'silver':
            return [
                'icon' => 'bi-star',
                'color' => '#7f8c8d',
                'bg' => '#f5f5f5',
                'text' => 'SILVER',
                'discount' => '10'
            ];
        case 'bronze':
            return [
                'icon' => 'bi-star-half',
                'color' => '#d35400',
                'bg' => '#ffecdb',
                'text' => 'BRONZE',
                'discount' => '5'
            ];
        default:
            return [
                'icon' => 'bi-person',
                'color' => '#95a5a6',
                'bg' => '#f8f9fa',
                'text' => 'REGULAR',
                'discount' => '0'
            ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Session Monitor - PlayMeter</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
            min-height: 100vh;
            display: flex;
        }
        
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #2c3e50 0%, #1a252f 100%);
            color: white;
            position: fixed;
            width: 260px;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            overflow-y: auto;
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
            width: calc(100% - 260px);
            min-height: 100vh;
            overflow-y: auto;
        }
        
        .header-card {
            background: white;
            border-radius: 15px;
            padding: 20px 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .stats-container {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .stats-badge {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 10px 20px;
            border-radius: 50px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .revenue-badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 10px 20px;
            border-radius: 50px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .token-summary {
            background: #fff3e0;
            color: #f39c12;
            padding: 10px 20px;
            border-radius: 50px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .stop-all-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 50px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: background 0.2s;
            font-size: 1em;
        }
        
        .stop-all-btn:hover {
            background: #c82333;
        }
        
        .stop-all-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
        
        /* Grid showing 6 sessions before scrolling (3x2 layout) */
        .sessions-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr); /* 3 columns */
            gap: 15px;
            margin-top: 20px;
        }
        
        /* Smaller session cards */
        .session-card {
            background: white;
            border-radius: 12px;
            padding: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border-left: 4px solid #28a745;
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            flex-direction: column;
            position: relative;
            height: fit-content;
            font-size: 0.9em;
        }
        
        .session-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        /* Token Badge - Smaller */
        .token-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 3px 8px;
            border-radius: 15px;
            font-size: 0.65em;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 3px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            z-index: 1;
        }
        
        .machine-name {
            font-size: 1.1em;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding-right: 70px;
        }
        
        .customer-info {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 6px;
            margin: 5px 0;
            border-left: 3px solid #3498db;
        }
        
        .customer-stats {
            display: flex;
            gap: 6px;
            margin-top: 3px;
            font-size: 0.7em;
            flex-wrap: wrap;
        }
        
        .visit-count, .spent-amount {
            padding: 2px 6px;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            gap: 2px;
        }
        
        .customer-name {
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.95em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .customer-code {
            font-size: 0.7em;
            color: #7f8c8d;
            font-family: monospace;
        }
        
        .time-info {
            display: flex;
            justify-content: space-between;
            background: #f8f9fa;
            border-radius: 5px;
            padding: 4px 6px;
            margin: 5px 0;
            font-size: 0.8em;
        }
        
        .rate-badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 2px 6px;
            border-radius: 15px;
            font-size: 0.7em;
            font-weight: 600;
        }
        
        .timer-display {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 6px;
            padding: 8px;
            text-align: center;
            margin: 5px 0;
        }
        
        .timer {
            font-size: 1.6em;
            font-weight: bold;
            color: white;
            font-family: monospace;
            line-height: 1;
        }
        
        .cost-display {
            background: linear-gradient(135deg, #43a047 0%, #2e7d32 100%);
            border-radius: 6px;
            padding: 8px;
            text-align: center;
            margin: 5px 0;
            position: relative;
        }
        
        .cost {
            font-size: 1.4em;
            font-weight: bold;
            color: white;
            font-family: monospace;
            line-height: 1;
        }
        
        .discount-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            background: #ffd700;
            color: #2c3e50;
            border-radius: 15px;
            padding: 2px 6px;
            font-size: 0.6em;
            font-weight: bold;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .timer-label, .cost-label {
            color: rgba(255,255,255,0.9);
            font-size: 0.65em;
            margin-top: 2px;
        }
        
        .end-btn {
            width: 100%;
            padding: 6px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            margin-top: 5px;
            font-size: 0.8em;
            cursor: pointer;
        }
        
        .end-btn:hover {
            background: #c82333;
        }
        
        .guest-badge {
            background: #9e9e9e;
            color: white;
            padding: 2px 5px;
            border-radius: 4px;
            font-size: 0.65em;
        }
        
        .no-sessions {
            grid-column: 1 / -1;
            background: white;
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            color: #999;
            border: 2px dashed #ccc;
        }
        
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            max-width: 500px;
            text-align: center;
        }
        
        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
        }
        
        /* Scroll indicator for more sessions */
        .more-sessions-indicator {
            grid-column: 1 / -1;
            text-align: center;
            padding: 15px;
            color: #7f8c8d;
            background: rgba(255,255,255,0.5);
            border-radius: 10px;
            margin-top: 10px;
        }
        
        @media (max-width: 1200px) {
            .sessions-grid {
                grid-template-columns: repeat(2, 1fr); /* 2 columns on medium screens */
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                min-height: auto;
            }
            
            .content {
                margin-left: 0;
                width: 100%;
            }
            
            .sessions-grid {
                grid-template-columns: 1fr; /* 1 column on mobile */
            }
        }
    </style>
</head>
<body>
    <!-- Confirmation Modal -->
    <div class="modal-overlay" id="confirmModal">
        <div class="modal-content">
            <i class="bi bi-exclamation-triangle-fill" style="font-size: 3em; color: #dc3545;"></i>
            <h4 class="mt-3">Stop All Active Sessions?</h4>
            <p class="text-muted">This will end all <?php echo $total_active; ?> active sessions and calculate final bills.</p>
            <p><strong>Estimated Total: KSh <?php echo number_format(max(0, $potential_revenue), 2); ?></strong></p>
            <div class="modal-buttons">
                <form method="POST" id="stopAllForm">
                    <button type="submit" name="stop_all_sessions" class="btn btn-danger">Yes, Stop All</button>
                </form>
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="sidebar">
        <div style="padding: 20px 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1);">
            <i class="bi bi-joystick" style="font-size: 2.5rem; color: #3498db;"></i>
            <h5 class="mt-2 mb-0">PlayMeter Pro</h5>
            <p style="font-size: 11px; color: rgba(255,255,255,0.6); margin-top: 5px;">
                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
            </p>
        </div>
        
        <nav style="margin-top: 15px;">
            <a href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="machines.php"><i class="bi bi-controller"></i> Machines</a>
            <a href="plays.php"><i class="bi bi-play-circle"></i> Plays</a>
            <a href="customers.php"><i class="bi bi-people"></i> Customers</a>
            <a href="session_monitor.php" class="active"><i class="bi bi-tv"></i> Live Monitor</a>
            <a href="reports.php"><i class="bi bi-graph-up"></i> Reports</a>
            <a href="maintenance.php"><i class="bi bi-tools"></i> Maintenance</a>
            <a href="arduino_settings.php"><i class="bi bi-microchip"></i> Arduino</a>
            <a href="profile.php"><i class="bi bi-person-circle"></i> Profile</a>
            <a href="logout.php" style="border-top: 1px solid rgba(255,255,255,0.1); margin-top: 20px; position: absolute; bottom: 0; width: 100%;">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="content">
        <!-- Success Message -->
        <?php if($message): ?>
            <?php echo $message; ?>
        <?php endif; ?>

        <!-- Header with stats and Stop All button -->
        <div class="header-card">
            <div>
                <h3 class="mb-2"><i class="bi bi-tv me-2"></i>Live Session Monitor</h3>
                <p class="text-muted mb-0">Real-time tracking of all active gaming sessions</p>
            </div>
            <div class="stats-container">
                <span class="stats-badge">
                    <i class="bi bi-play-circle-fill"></i>
                    <?php echo $total_active; ?> Active
                </span>
                <span class="revenue-badge">
                    <i class="bi bi-cash-stack"></i>
                    Est. KSh <?php echo number_format(max(0, $potential_revenue), 2); ?>
                </span>
                <?php 
                // Count token holders
                $token_holders = 0;
                foreach($active_sessions as $session) {
                    if($session['customer_name'] && $session['total_visits'] >= 5) {
                        $token_holders++;
                    }
                }
                if($token_holders > 0): 
                ?>
                <span class="token-summary">
                    <i class="bi bi-award"></i>
                    <?php echo $token_holders; ?> Token
                </span>
                <?php endif; ?>
                <?php if($total_active > 0): ?>
                <button class="stop-all-btn" onclick="openModal()">
                    <i class="bi bi-stop-circle-fill"></i>
                    Stop All
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sessions Grid - Shows ALL active sessions, 6 visible before scrolling -->
        <?php if(count($active_sessions) > 0): ?>
            <div class="sessions-grid">
                <?php foreach($active_sessions as $session): 
                    $start_time = strtotime($session['start_time']);
                    $rate = $session['rate_per_minute'] ?? 2.00;
                    $customer_name = $session['customer_name'] ?? 'Guest';
                    $customer_code = $session['customer_code'] ?? '';
                    $visits = $session['total_visits'] ?? 0;
                    $spent = $session['total_spent'] ?? 0;
                    
                    // Determine customer tier
                    $tier = getCustomerTier($visits);
                    $badge = getTokenBadge($tier);
                ?>
                <div class="session-card" id="session-<?php echo $session['id']; ?>">
                    <!-- Token Badge for Frequent Customers -->
                    <?php if($customer_name != 'Guest' && $tier != 'regular'): ?>
                    <div class="token-badge" style="background: <?php echo $badge['bg']; ?>; color: <?php echo $badge['color']; ?>;">
                        <i class="bi <?php echo $badge['icon']; ?>"></i>
                        <?php echo $badge['text']; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Machine Name -->
                    <div class="machine-name">
                        <i class="bi bi-controller me-1" style="color: #3498db;"></i>
                        <?php echo htmlspecialchars($session['machine_name']); ?>
                    </div>
                    
                    <!-- Customer Info -->
                    <div class="customer-info">
                        <div class="d-flex justify-content-between align-items-start">
                            <div style="flex: 1;">
                                <div class="customer-name">
                                    <i class="bi bi-person-circle me-1"></i>
                                    <?php echo htmlspecialchars($customer_name); ?>
                                    <?php if($customer_name == 'Guest'): ?>
                                        <span class="guest-badge">GUEST</span>
                                    <?php endif; ?>
                                </div>
                                <?php if($customer_code): ?>
                                    <div class="customer-code">
                                        <?php echo $customer_code; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Customer Stats for Frequent Visitors -->
                                <?php if($visits > 0): ?>
                                <div class="customer-stats">
                                    <span class="visit-count">
                                        <i class="bi bi-clock-history"></i> <?php echo $visits; ?>
                                    </span>
                                    <span class="spent-amount">
                                        <i class="bi bi-cash-stack"></i> KSh <?php echo number_format($spent, 0); ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <span class="rate-badge">
                                KSh <?php echo number_format($rate, 2); ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Start Time -->
                    <div class="time-info">
                        <span>
                            <i class="bi bi-clock me-1"></i>
                            <?php echo date('H:i', $start_time); ?>
                        </span>
                        <span>
                            <?php echo date('d/m', $start_time); ?>
                        </span>
                    </div>
                    
                    <!-- Timer Display -->
                    <div class="timer-display">
                        <div class="timer" id="timer-<?php echo $session['id']; ?>">00:00</div>
                        <div class="timer-label">Elapsed</div>
                    </div>
                    
                    <!-- Cost Display with Discount for Token Holders -->
                    <div class="cost-display">
                        <?php if($badge['discount'] > 0): ?>
                        <div class="discount-badge" title="<?php echo $badge['discount']; ?>% off">
                            -<?php echo $badge['discount']; ?>%
                        </div>
                        <?php endif; ?>
                        <div class="cost" id="cost-<?php echo $session['id']; ?>">KSh 0.00</div>
                        <div class="cost-label">Cost</div>
                    </div>
                    
                    <!-- End Session Button -->
                    <button class="end-btn" onclick="endSession(<?php echo $session['id']; ?>)">
                        <i class="bi bi-stop-circle me-1"></i>End
                    </button>
                </div>

                <script>
                    (function() {
                        const sessionId = <?php echo $session['id']; ?>;
                        const rate = <?php echo $rate; ?>;
                        const phpTimestamp = <?php echo $start_time; ?>;
                        const discount = <?php echo $badge['discount']; ?>;
                        
                        function updateTimer() {
                            const now = Math.floor(Date.now() / 1000);
                            const elapsedSeconds = Math.max(0, now - phpTimestamp);
                            
                            // Calculate time
                            const hours = Math.floor(elapsedSeconds / 3600);
                            const minutes = Math.floor((elapsedSeconds % 3600) / 60);
                            const seconds = elapsedSeconds % 60;
                            
                            // Format time
                            let timeStr;
                            if (hours > 0) {
                                timeStr = hours.toString().padStart(2, '0') + ':' + 
                                         minutes.toString().padStart(2, '0') + ':' + 
                                         seconds.toString().padStart(2, '0');
                            } else {
                                timeStr = minutes.toString().padStart(2, '0') + ':' + 
                                         seconds.toString().padStart(2, '0');
                            }
                            
                            // Update timer
                            const timerElement = document.getElementById('timer-' + sessionId);
                            if (timerElement) {
                                timerElement.textContent = timeStr;
                            }
                            
                            // Calculate base cost
                            let cost = (elapsedSeconds / 60) * rate;
                            
                            // Apply discount for token holders
                            if (discount > 0) {
                                cost = cost * (1 - (discount / 100));
                            }
                            
                            // Update cost
                            const costElement = document.getElementById('cost-' + sessionId);
                            if (costElement) {
                                costElement.textContent = 'KSh ' + cost.toFixed(2);
                            }
                        }
                        
                        updateTimer();
                        setInterval(updateTimer, 1000);
                    })();
                </script>
                <?php endforeach; ?>
            </div>
            
            <!-- Show indicator if more sessions below -->
            <?php if($total_active > 6): ?>
            <div class="more-sessions-indicator">
                <i class="bi bi-arrow-down-circle me-2"></i>
                Scroll down for more sessions (<?php echo $total_active - 6; ?> more)
                <i class="bi bi-arrow-down-circle ms-2"></i>
            </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="no-sessions">
                <i class="bi bi-tv"></i>
                <h4>No Active Sessions</h4>
                <p class="text-muted">There are currently no active gaming sessions.</p>
                <p class="text-muted">Start a session by scanning a QR code at an Arduino unit.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function openModal() {
            document.getElementById('confirmModal').style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('confirmModal').style.display = 'none';
        }
        
        function endSession(sessionId) {
            if(confirm('End this session?')) {
                location.reload();
            }
        }
        
        // Close modal if clicked outside
        window.onclick = function(event) {
            const modal = document.getElementById('confirmModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>