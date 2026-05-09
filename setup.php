<?php
// setup.php - Improved version that checks for existing data
$host = "localhost";
$user = "root";
$pass = "@Unknown_bot.06";

try {
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS playmeter_db");
    $pdo->exec("USE playmeter_db");
    
    // Create tables if they don't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS machines (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            machine_type VARCHAR(50),
            status ENUM('active', 'maintenance', 'inactive') DEFAULT 'active',
            price_per_play DECIMAL(10,2) DEFAULT 1.00,
            total_plays INT DEFAULT 0,
            total_revenue DECIMAL(10,2) DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS plays (
            id INT PRIMARY KEY AUTO_INCREMENT,
            machine_id INT,
            player_name VARCHAR(100),
            plays_count INT DEFAULT 1,
            amount_paid DECIMAL(10,2),
            payment_method ENUM('cash', 'card', 'token') DEFAULT 'cash',
            play_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(100),
            role ENUM('admin', 'operator') DEFAULT 'operator',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Check if users table is empty before inserting
    $checkUsers = $pdo->query("SELECT COUNT(*) as count FROM users");
    $userCount = $checkUsers->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($userCount == 0) {
        // Insert sample users only if table is empty
        $pdo->exec("
            INSERT INTO users (username, password, email, role) VALUES
            ('admin', 'admin123', 'admin@playmeter.com', 'admin'),
            ('operator', 'operator123', 'operator@playmeter.com', 'operator')
        ");
        echo "Users created successfully.<br>";
    } else {
        echo "Users already exist, skipping insertion.<br>";
    }
    
    // Check if machines table is empty before inserting
    $checkMachines = $pdo->query("SELECT COUNT(*) as count FROM machines");
    $machineCount = $checkMachines->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($machineCount == 0) {
        // Insert sample machines only if table is empty
        $pdo->exec("
            INSERT INTO machines (name, machine_type, price_per_play) VALUES
            ('Street Fighter VI', 'Arcade', 2.50),
            ('Pinball Wizard', 'Pinball', 1.50),
            ('Air Hockey Pro', 'Sports', 2.00)
        ");
        echo "Sample machines created successfully.<br>";
    } else {
        echo "Machines already exist, skipping insertion.<br>";
    }
    
    echo "<br><strong>Database setup complete!</strong><br>";
    echo "<a href='login.php'>Go to Login Page</a>";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>