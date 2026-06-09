<!-- includes/sidebar.php -->
<div class="sidebar">
    <div style="padding:25px 20px;text-align:center;border-bottom:1px solid rgba(255,255,255,.1)">
        <i class="bi bi-joystick" style="font-size:2.8rem;color:#3b82f6"></i>
        <h5 class="mt-2 mb-0">PlayMeter Pro</h5>
        <p style="font-size:13px;color:rgba(255,255,255,.6);margin-top:5px">
            <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['username'] ?? 'admin') ?>
        </p>
    </div>
    <nav style="margin-top:20px">
        <a href="index.php" class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
        <a href="machines.php" class="<?= basename($_SERVER['PHP_SELF']) == 'machines.php' ? 'active' : '' ?>">
            <i class="bi bi-controller"></i> Machines
        </a>
        <a href="plays.php" class="<?= basename($_SERVER['PHP_SELF']) == 'plays.php' ? 'active' : '' ?>">
            <i class="bi bi-play-circle"></i> Plays
        </a>
        <a href="customers.php" class="<?= basename($_SERVER['PHP_SELF']) == 'customers.php' ? 'active' : '' ?>">
            <i class="bi bi-people"></i> Customers
        </a>
        <a href="session_monitor.php" class="<?= basename($_SERVER['PHP_SELF']) == 'session_monitor.php' ? 'active' : '' ?>">
            <i class="bi bi-tv"></i> Live Monitor
        </a>
        <a href="reports.php" class="<?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>">
            <i class="bi bi-graph-up"></i> Reports
        </a>
        <a href="maintenance.php" class="<?= basename($_SERVER['PHP_SELF']) == 'maintenance.php' ? 'active' : '' ?>">
            <i class="bi bi-tools"></i> Maintenance
        </a>
        <a href="arduino_settings.php" class="<?= basename($_SERVER['PHP_SELF']) == 'arduino_settings.php' ? 'active' : '' ?>">
            <i class="bi bi-microchip"></i> Arduino
        </a>
        <a href="add_play.php" class="<?= basename($_SERVER['PHP_SELF']) == 'add_play.php' ? 'active' : '' ?>">
            <i class="bi bi-plus-circle"></i> New Play
        </a>
        
        <a href="logout.php" style="border-top:1px solid rgba(255,255,255,.1);margin-top:30px;position:absolute;bottom:0;width:100%">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </nav>
</div>