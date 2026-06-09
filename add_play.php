<?php
// add_play.php — FIFA 26 only · M-Pesa STK Push · matched to live schema
session_start();
require_once 'config/database.php';
require_once 'mpesa_payment.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db       = $database->getConnection();

define('FIFA26_MACHINE_ID',     1);
define('FIFA26_PRICE_PER_PLAY', 50.00);

$message           = '';
$checkoutRequestId = '';
$playId            = 0;
$stkSent           = false;

// ── STEP 1: Submit player info → trigger STK Push ─────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'start_payment') {

    $playerName = trim($_POST['player_name']);
    $phone      = trim($_POST['phone_number']);
    $playsCount = max(1, (int) $_POST['plays_count']);
    $amountDue  = FIFA26_PRICE_PER_PLAY * $playsCount;
    $customerId = !empty($_POST['customer_id']) ? (int) $_POST['customer_id'] : null;

    // INSERT — uses only columns confirmed to exist in live DB
    // plays cols: machine_id, customer_id, player_name, phone_number, plays_count,
    //             amount_paid, amount, payment_method, payment_status,
    //             status, duration_seconds, paid_at
    $stmt = $db->prepare("
        INSERT INTO plays
            (machine_id, customer_id, player_name, phone_number, plays_count,
             amount_paid, amount, payment_method, payment_status,
             status, duration_seconds, paid_at)
        VALUES
            (:mid, :cid, :name, :phone, :cnt,
             :amt, :amt2, 'mpesa', 'pending',
             'playing', 0, NULL)
    ");
    $stmt->execute([
        ':mid'   => FIFA26_MACHINE_ID,
        ':cid'   => $customerId,
        ':name'  => $playerName,
        ':phone' => $phone,
        ':cnt'   => $playsCount,
        ':amt'   => $amountDue,
        ':amt2'  => $amountDue,
    ]);
    $playId  = $db->lastInsertId();
    $playRef = 'FIFA26-' . $playId;

    // Send M-Pesa STK Push
    $mpesa  = new MpesaPayment();
    $result = $mpesa->stkPush($phone, $amountDue, $playRef);

    if ($result['success']) {
        $checkoutRequestId = $result['checkout_request_id'];
        $stkSent = true;

        // Save checkout request ID — column confirmed to exist after migration
        $db->prepare("UPDATE plays SET mpesa_checkout_request_id = ? WHERE id = ?")
           ->execute([$checkoutRequestId, $playId]);

        $message = '
        <div class="alert alert-info d-flex align-items-center gap-3">
            <i class="bi bi-phone fs-3"></i>
            <div>
                <strong>M-Pesa Prompt Sent!</strong><br>
                Ask <strong>' . htmlspecialchars($phone) . '</strong> to check their phone and enter PIN.
            </div>
        </div>';
    } else {
        $db->prepare("UPDATE plays SET payment_status = 'failed', status = 'ended' WHERE id = ?")
           ->execute([$playId]);
        $message = '<div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i>M-Pesa error: '
                 . htmlspecialchars($result['message']) . '</div>';
        $playId = 0;
    }
}

// ── STEP 2 (AJAX): Poll payment status ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'check_status') {
    header('Content-Type: application/json');

    $cri = trim($_POST['checkout_request_id']);
    $pid = (int) $_POST['play_id'];

    // Check DB first — callback may have already fired
    $stmt = $db->prepare("SELECT payment_status, mpesa_transaction_code FROM plays WHERE id = ?");
    $stmt->execute([$pid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && $row['payment_status'] === 'paid') {
        // Update machine totals
        $db->prepare("
            UPDATE machines
            SET total_plays   = total_plays   + (SELECT plays_count FROM plays WHERE id = ?),
                total_revenue = total_revenue + (SELECT amount_paid  FROM plays WHERE id = ?)
            WHERE id = ?
        ")->execute([$pid, $pid, FIFA26_MACHINE_ID]);

        echo json_encode(['status' => 'paid', 'txn' => $row['mpesa_transaction_code']]);
        exit;
    }

    if ($row && in_array($row['payment_status'], ['failed', 'cancelled'])) {
        echo json_encode(['status' => 'failed', 'message' => 'Payment failed or cancelled']);
        exit;
    }

    // Callback hasn't fired yet — query Daraja directly
    $mpesa  = new MpesaPayment();
    $result = $mpesa->queryStatus($cri);

    if ($result['paid']) {
        $db->prepare("
            UPDATE plays
            SET payment_status = 'paid',
                status         = 'ended',
                paid_at        = NOW(),
                amount         = amount_paid
            WHERE id = ?
        ")->execute([$pid]);

        $db->prepare("
            UPDATE machines
            SET total_plays   = total_plays   + (SELECT plays_count FROM plays WHERE id = ?),
                total_revenue = total_revenue + (SELECT amount_paid  FROM plays WHERE id = ?)
            WHERE id = ?
        ")->execute([$pid, $pid, FIFA26_MACHINE_ID]);

        echo json_encode(['status' => 'paid']);

    } elseif (str_contains($result['message'], 'cancel') || str_contains($result['message'], 'timeout')) {
        $db->prepare("UPDATE plays SET payment_status = 'failed', status = 'ended' WHERE id = ?")
           ->execute([$pid]);
        echo json_encode(['status' => 'failed', 'message' => $result['message']]);

    } else {
        echo json_encode(['status' => 'pending', 'message' => $result['message']]);
    }
    exit;
}

// Load customers for dropdown
$customers = $db->query("SELECT id, full_name, customer_code, phone_number FROM customers ORDER BY full_name")
                ->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Play — FIFA 26 | PlayMeter</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body { background:#f4f6f9; font-family:'Segoe UI',sans-serif; }
        .sidebar {
            min-height:100vh;
            background:linear-gradient(135deg,#2c3e50,#1a252f);
            position:fixed; width:260px;
            box-shadow:2px 0 10px rgba(0,0,0,.1);
        }
        .sidebar a {
            color:rgba(255,255,255,.8); text-decoration:none;
            padding:15px 20px; display:block; transition:all .3s;
            border-left:4px solid transparent;
        }
        .sidebar a:hover  { background:rgba(255,255,255,.1); color:#fff; border-left-color:#3498db; }
        .sidebar a.active { background:rgba(52,152,219,.2);  color:#fff; border-left-color:#3498db; }
        .sidebar i { margin-right:10px; width:20px; }
        .content { margin-left:260px; padding:20px; }
        .navbar-top {
            background:#fff; padding:15px 25px; margin-bottom:25px;
            border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,.05);
        }
        .form-container {
            background:#fff; border-radius:12px; padding:30px;
            box-shadow:0 2px 10px rgba(0,0,0,.05); max-width:620px; margin:0 auto;
        }
        .game-badge {
            background:linear-gradient(135deg,#1a9e3f,#0a5e25);
            color:#fff; border-radius:10px; padding:16px 20px;
            display:flex; align-items:center; gap:14px; margin-bottom:24px;
        }
        .game-badge i { font-size:2.2rem; }
        .payment-status-box {
            border-radius:10px; padding:20px; text-align:center;
            border:2px dashed #3498db; margin-top:20px;
        }
        .spinner-phone { font-size:2.5rem; animation:pulse 1s infinite; }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.3} }
        .status-paid   { border-color:#27ae60 !important; background:#eafaf1; }
        .status-failed { border-color:#e74c3c !important; background:#fdf1f0; }
    </style>
</head>
<body>

<div class="sidebar">
    <div style="padding:25px 20px;text-align:center;border-bottom:1px solid rgba(255,255,255,.1)">
        <i class="bi bi-joystick" style="font-size:3rem;color:#3498db"></i>
        <h5 class="mt-2 mb-0" style="color:#fff">PlayMeter Pro</h5>
        <p style="font-size:12px;color:rgba(255,255,255,.6);margin-top:5px">
            <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['username']) ?>
        </p>
    </div>
    <nav style="margin-top:20px">
        <a href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="machines.php"><i class="bi bi-controller"></i> Machine</a>
        <a href="plays.php" class="active"><i class="bi bi-play-circle"></i> Plays</a>
        <a href="customers.php"><i class="bi bi-people"></i> Customers</a>
        <a href="session_monitor.php"><i class="bi bi-tv"></i> Live Monitor</a>
        <a href="reports.php"><i class="bi bi-graph-up"></i> Reports</a>
        <a href="maintenance.php"><i class="bi bi-tools"></i> Maintenance</a>
        <a href="arduino_settings.php"><i class="bi bi-microchip"></i> Arduino</a>
        <a href="profile.php"><i class="bi bi-person-circle"></i> Profile</a>
        <a href="logout.php" style="border-top:1px solid rgba(255,255,255,.1);margin-top:20px;position:absolute;bottom:0;width:100%">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </nav>
</div>

<div class="content">
    <div class="navbar-top d-flex justify-content-between align-items-center">
        <h4 class="mb-0"><i class="bi bi-plus-circle me-2"></i>New Play</h4>
        <a href="plays.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Plays
        </a>
    </div>

    <?= $message ?>

    <div class="form-container">

        <!-- FIFA 26 badge — hardcoded, no dropdown needed -->
        <div class="game-badge">
            <i class="bi bi-controller"></i>
            <div>
                <div style="font-size:1.2rem;font-weight:700">FIFA 26</div>
                <div style="font-size:.85rem;opacity:.85">
                    Arduino: ARDUINO_001 &nbsp;|&nbsp; KSh <?= number_format(FIFA26_PRICE_PER_PLAY, 2) ?> per play
                </div>
            </div>
        </div>

        <?php if (!$stkSent): ?>
        <!-- ── Player info form ── -->
        <form method="POST" id="playForm">
            <input type="hidden" name="action" value="start_payment">

            <div class="mb-3">
                <label class="form-label fw-semibold">Player Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="player_name" required
                       placeholder="e.g. John Doe">
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">M-Pesa Phone Number <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-phone"></i></span>
                    <input type="tel" class="form-control" name="phone_number" id="phoneInput" required
                           placeholder="07XXXXXXXX"
                           pattern="^(07|01|2547|2541)\d{8}$"
                           title="Enter a valid Safaricom number e.g. 0712345678">
                </div>
                <div class="form-text">Customer will receive M-Pesa prompt on this number.</div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Number of Plays <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="playsCount" name="plays_count"
                       min="1" max="20" value="1" required>
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold">Amount Due (KSh)</label>
                <input type="text" class="form-control bg-light fw-bold text-success fs-5"
                       id="amountDisplay" readonly>
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold">
                    Linked Customer <span class="text-muted fw-normal">(optional)</span>
                </label>
                <select class="form-select" name="customer_id" id="customerSelect">
                    <option value="">— Walk-in / Guest —</option>
                    <?php foreach ($customers as $c): ?>
                    <option value="<?= $c['id'] ?>"
                            data-phone="<?= htmlspecialchars($c['phone_number'] ?? '') ?>">
                        <?= htmlspecialchars($c['full_name']) ?>
                        (<?= htmlspecialchars($c['customer_code']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Selecting a customer auto-fills their phone number.</div>
            </div>

            <button type="submit" class="btn btn-success btn-lg w-100">
                <i class="bi bi-phone me-2"></i>Send M-Pesa Payment Request
            </button>
        </form>

        <?php else: ?>
        <!-- ── Payment waiting screen ── -->
        <div class="payment-status-box" id="paymentStatusBox">
            <div class="spinner-phone" id="statusIcon">📱</div>
            <h5 class="mt-3" id="statusTitle">Waiting for Payment...</h5>
            <p class="text-muted mb-3" id="statusMsg">
                Ask the customer to check their phone and enter M-Pesa PIN.
            </p>
            <div class="progress mb-3" style="height:6px">
                <div class="progress-bar progress-bar-striped progress-bar-animated bg-info w-100"
                     id="progressBar"></div>
            </div>
            <button class="btn btn-outline-secondary btn-sm" onclick="cancelPayment()">
                <i class="bi bi-x"></i> Cancel
            </button>
        </div>

        <input type="hidden" id="hiddenCRI"    value="<?= htmlspecialchars($checkoutRequestId) ?>">
        <input type="hidden" id="hiddenPlayId" value="<?= $playId ?>">
        <?php endif; ?>

    </div><!-- /form-container -->
</div><!-- /content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Amount calculator ─────────────────────────────────────────
const PRICE      = <?= FIFA26_PRICE_PER_PLAY ?>;
const playsInput = document.getElementById('playsCount');
const amtDisplay = document.getElementById('amountDisplay');

function updateAmount() {
    const n = parseInt(playsInput?.value) || 1;
    if (amtDisplay) amtDisplay.value = 'KSh ' + (PRICE * n).toFixed(2);
}
playsInput?.addEventListener('input', updateAmount);
updateAmount();

// ── Auto-fill phone from customer select ─────────────────────
document.getElementById('customerSelect')?.addEventListener('change', function () {
    const phone = this.options[this.selectedIndex]?.dataset?.phone;
    if (phone) document.getElementById('phoneInput').value = phone;
});

// ── Payment status polling ────────────────────────────────────
const cri    = document.getElementById('hiddenCRI')?.value;
const playId = document.getElementById('hiddenPlayId')?.value;
let pollTimer, attempts = 0;
const MAX = 40; // ~2 min at 3s intervals

if (cri && playId) {
    pollTimer = setInterval(poll, 3000);
}

function poll() {
    if (++attempts > MAX) {
        clearInterval(pollTimer);
        setStatus('failed', 'Payment timed out', 'The request expired. Please try again.');
        return;
    }
    fetch('add_play.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=check_status&checkout_request_id=${encodeURIComponent(cri)}&play_id=${encodeURIComponent(playId)}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'paid') {
            clearInterval(pollTimer);
            setStatus('paid', '✅ Payment Confirmed!',
                `M-Pesa Code: <strong>${data.txn || 'N/A'}</strong><br>Game is ready to start!`);
        } else if (data.status === 'failed') {
            clearInterval(pollTimer);
            setStatus('failed', '❌ Payment Failed', data.message || 'Customer declined or timed out.');
        }
    })
    .catch(() => {});
}

function setStatus(type, title, body) {
    const box  = document.getElementById('paymentStatusBox');
    const icon = document.getElementById('statusIcon');
    const ttl  = document.getElementById('statusTitle');
    const msg  = document.getElementById('statusMsg');
    const bar  = document.getElementById('progressBar');

    ttl.textContent = title;
    msg.innerHTML   = body;
    bar.classList.remove('progress-bar-animated','progress-bar-striped','bg-info');
    document.querySelector('.btn-outline-secondary')?.remove();

    if (type === 'paid') {
        box.classList.add('status-paid');
        bar.classList.add('bg-success','w-100');
        icon.textContent = '🎮';
        icon.style.animation = 'none';
        setTimeout(() => {
            box.insertAdjacentHTML('beforeend',
                `<div class="mt-3">
                    <a href="add_play.php" class="btn btn-success me-2">
                        <i class="bi bi-plus-circle"></i> New Play
                    </a>
                    <a href="index.php" class="btn btn-outline-primary">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </div>`);
        }, 400);
    } else {
        box.classList.add('status-failed');
        bar.classList.add('bg-danger','w-100');
        icon.textContent = '⚠️';
        icon.style.animation = 'none';
        setTimeout(() => {
            box.insertAdjacentHTML('beforeend',
                `<div class="mt-3">
                    <a href="add_play.php" class="btn btn-warning">
                        <i class="bi bi-arrow-counterclockwise"></i> Try Again
                    </a>
                </div>`);
        }, 400);
    }
}

function cancelPayment() {
    clearInterval(pollTimer);
    window.location.href = 'add_play.php';
}
</script>
</body>
</html>