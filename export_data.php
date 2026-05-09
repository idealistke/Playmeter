<?php
// export_data.php
session_start();
require_once 'config/database.php';
require_once 'includes/auth_check.php';

$database = new Database();
$db = $database->getConnection();

$type = isset($_GET['type']) ? $_GET['type'] : 'sessions';
$format = isset($_GET['format']) ? $_GET['format'] : 'csv';

// Set headers for download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="playmeter_' . $type . '_' . date('Y-m-d') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

switch($type) {
    case 'sessions':
        // Export all sessions
        fputcsv($output, ['Session Code', 'Machine', 'Customer', 'Start Time', 'End Time', 'Duration', 'Rate', 'Total Cost', 'Payment Status']);
        
        $query = "SELECT s.session_code, m.name as machine_name, c.full_name as customer_name,
                         s.start_time, s.end_time, s.duration_minutes, s.rate_per_minute, 
                         s.total_cost, s.payment_status
                  FROM sessions s
                  LEFT JOIN machines m ON s.machine_id = m.id
                  LEFT JOIN customers c ON s.customer_id = c.id
                  ORDER BY s.start_time DESC";
        $result = $db->query($query);
        
        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
        break;
        
    case 'machines':
        // Export machines with statistics
        fputcsv($output, ['Machine', 'Type', 'Status', 'Total Plays', 'Total Revenue', 'Last Active']);
        
        $query = "SELECT m.name, m.machine_type, m.status, 
                         COUNT(p.id) as total_plays, 
                         COALESCE(SUM(p.amount_paid), 0) as total_revenue,
                         MAX(p.play_date) as last_active
                  FROM machines m
                  LEFT JOIN plays p ON m.id = p.machine_id
                  GROUP BY m.id
                  ORDER BY total_revenue DESC";
        $result = $db->query($query);
        
        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
        break;
        
    case 'customers':
        // Export customers with statistics
        fputcsv($output, ['Customer Code', 'Name', 'Phone', 'Email', 'Total Visits', 'Total Spent', 'Balance', 'Registered']);
        
        $query = "SELECT customer_code, full_name, phone_number, email, 
                         total_visits, total_spent, balance, created_at
                  FROM customers
                  ORDER BY total_spent DESC";
        $result = $db->query($query);
        
        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
        break;
        
    case 'revenue':
        // Daily revenue report
        fputcsv($output, ['Date', 'Total Sessions', 'Total Revenue', 'Average per Session']);
        
        $query = "SELECT DATE(start_time) as date, 
                         COUNT(*) as sessions,
                         COALESCE(SUM(total_cost), 0) as revenue,
                         AVG(total_cost) as average
                  FROM sessions
                  WHERE payment_status = 'paid'
                  GROUP BY DATE(start_time)
                  ORDER BY date DESC";
        $result = $db->query($query);
        
        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
        break;
}

fclose($output);
exit();
?>