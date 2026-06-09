"""
playmeter_bridge.py
====================
Sits between the Arduino (serial) and the Playmeter PHP/MySQL dashboard.

USAGE
-----
  python playmeter_bridge.py          # auto-detect port, machine_id=1
  python playmeter_bridge.py COM7 2   # explicit port, machine_id=2 (Linux: /dev/ttyUSB0)

REQUIREMENTS
------------
  pip install pyserial pymysql

CONFIGURATION
-------------
Edit the DB_* constants below to match your Playmeter database credentials.
"""

import serial
import serial.tools.list_ports
import sys
import time
import threading
import pymysql
import logging
from datetime import datetime

# ── Database config ────────────────────────────────────────────────────────────
DB_HOST     = "localhost"
DB_USER     = "root"
DB_PASS     = ""
DB_NAME     = "playmeter_db"

# ── Machine this Arduino is assigned to ───────────────────────────────────────
MACHINE_ID  = int(sys.argv[2]) if len(sys.argv) > 2 else 1

# ── Serial config ─────────────────────────────────────────────────────────────
SERIAL_PORT = sys.argv[1] if len(sys.argv) > 1 else None   # e.g. "COM7" or "/dev/ttyUSB0"
BAUD_RATE   = 9600
RECONNECT_DELAY = 5   # seconds between reconnect attempts

# ── Logging ───────────────────────────────────────────────────────────────────
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    handlers=[
        logging.FileHandler("playmeter_bridge.log"),
        logging.StreamHandler(sys.stdout),
    ]
)
log = logging.getLogger(__name__)

# ── State ─────────────────────────────────────────────────────────────────────
current_session_id   = None
arduino_state        = "IDLE"
ser                  = None
running              = True


# ════════════════════════════════════════════════════════════════════════════════
# Database helpers
# ════════════════════════════════════════════════════════════════════════════════

def get_db():
    return pymysql.connect(
        host=DB_HOST, user=DB_USER, password=DB_PASS, database=DB_NAME,
        cursorclass=pymysql.cursors.DictCursor, autocommit=True
    )


def update_machine_status(status: str):
    """Update the machine's status column so the dashboard reflects reality."""
    try:
        with get_db() as db:
            with db.cursor() as cur:
                cur.execute(
                    "UPDATE machines SET status=%s WHERE id=%s",
                    (status, MACHINE_ID)
                )
        log.info(f"Machine {MACHINE_ID} status → {status}")
    except Exception as e:
        log.error(f"DB update_machine_status error: {e}")


def log_session_start(boot_ts: int) -> int | None:
    """Insert a new play session row and return its ID."""
    try:
        with get_db() as db:
            with db.cursor() as cur:
                cur.execute(
                    """INSERT INTO plays (machine_id, start_time, status)
                       VALUES (%s, NOW(), 'playing')""",
                    (MACHINE_ID,)
                )
                return db.insert_id()
    except Exception as e:
        log.error(f"DB log_session_start error: {e}")
        return None


def log_session_end(session_id: int, total_seconds: int, amount: float):
    """Close a session with duration and cost."""
    try:
        with get_db() as db:
            with db.cursor() as cur:
                cur.execute(
                    """UPDATE plays
                       SET end_time=NOW(), duration_seconds=%s, amount=%s, status='done'
                       WHERE id=%s""",
                    (total_seconds, amount, session_id)
                )
        log.info(f"Session {session_id} ended: {total_seconds}s  Ksh {amount:.2f}")
    except Exception as e:
        log.error(f"DB log_session_end error: {e}")


def log_payment(session_id: int):
    """Mark the session as paid."""
    try:
        with get_db() as db:
            with db.cursor() as cur:
                cur.execute(
                    "UPDATE plays SET status='paid', paid_at=NOW() WHERE id=%s",
                    (session_id,)
                )
        log.info(f"Session {session_id} marked PAID")
    except Exception as e:
        log.error(f"DB log_payment error: {e}")


def log_alert(alert_type: str):
    """Record an alert event in the maintenance log or a dedicated table."""
    try:
        with get_db() as db:
            with db.cursor() as cur:
                cur.execute(
                    """INSERT INTO maintenance (machine_id, issue, reported_at, status)
                       VALUES (%s, %s, NOW(), 'open')""",
                    (MACHINE_ID, f"ALERT: {alert_type}")
                )
        log.warning(f"Alert logged: {alert_type}")
    except Exception as e:
        log.error(f"DB log_alert error: {e}")


def get_pending_commands():
    """Fetch queued commands for this Arduino unit from the dashboard."""
    try:
        with get_db() as db:
            with db.cursor() as cur:
                cur.execute(
                    """SELECT c.id, c.command, c.parameters
                       FROM arduino_commands c
                       JOIN arduino_units a ON c.arduino_unit_id = a.id
                       WHERE a.machine_id=%s AND c.status='pending'
                       ORDER BY c.created_at ASC LIMIT 5""",
                    (MACHINE_ID,)
                )
                rows = cur.fetchall()
            # Mark them as sent
            if rows:
                ids = tuple(r["id"] for r in rows)
                with db.cursor() as cur:
                    fmt = ",".join(["%s"] * len(ids))
                    cur.execute(f"UPDATE arduino_commands SET status='sent' WHERE id IN ({fmt})", ids)
        return rows
    except Exception as e:
        log.error(f"DB get_pending_commands error: {e}")
        return []


def update_heartbeat(state: str):
    """Keep the last_seen timestamp and state alive in arduino_units."""
    try:
        with get_db() as db:
            with db.cursor() as cur:
                cur.execute(
                    """UPDATE arduino_units
                       SET last_seen=NOW(), status=%s
                       WHERE machine_id=%s""",
                    (state.lower(), MACHINE_ID)
                )
    except Exception as e:
        log.error(f"DB update_heartbeat error: {e}")


# ════════════════════════════════════════════════════════════════════════════════
# Serial helpers
# ════════════════════════════════════════════════════════════════════════════════

def auto_detect_port() -> str | None:
    """Try to auto-detect the Arduino's COM port."""
    for p in serial.tools.list_ports.comports():
        desc = (p.description or "").lower()
        if "arduino" in desc or "ch340" in desc or "cp210" in desc or "usb serial" in desc:
            log.info(f"Auto-detected Arduino on {p.device}")
            return p.device
    return None


def send_to_arduino(message: str):
    global ser
    if ser and ser.is_open:
        try:
            ser.write((message.rstrip("\n") + "\n").encode())
            log.debug(f"→ Arduino: {message}")
        except Exception as e:
            log.error(f"Serial write error: {e}")


def send_lcd(line1: str, line2: str = ""):
    send_to_arduino(f"LCD:{line1[:16]}|{line2[:16]}")


# ════════════════════════════════════════════════════════════════════════════════
# Message handlers
# ════════════════════════════════════════════════════════════════════════════════

def handle_message(line: str):
    global current_session_id, arduino_state

    line = line.strip()
    if not line:
        return

    log.info(f"← Arduino: {line}")
    parts = line.split(",")
    event = parts[0]

    if event == "PS_POWER_ON":
        update_machine_status("booting")

    elif event == "SESSION_START":
        boot_ts = int(parts[1]) if len(parts) > 1 else 0
        current_session_id = log_session_start(boot_ts)
        update_machine_status("in_use")
        log.info(f"New session ID: {current_session_id}")

    elif event == "SESSION_END":
        total_secs = int(parts[1])   if len(parts) > 1 else 0
        amount     = float(parts[2]) if len(parts) > 2 else 0.0
        if current_session_id:
            log_session_end(current_session_id, total_secs, amount)
        update_machine_status("awaiting_payment")

    elif event == "PAYMENT_DONE":
        if current_session_id:
            log_payment(current_session_id)
            current_session_id = None
        update_machine_status("active")

    elif event in ("ALERT_NO_START", "ALERT_NO_PAYMENT", "ALERT_PERSON_LEFT"):
        log_alert(event)

    elif event == "ALERT_CLEARED":
        update_machine_status("active")

    elif event == "HEARTBEAT":
        arduino_state = parts[1] if len(parts) > 1 else "UNKNOWN"
        update_heartbeat(arduino_state)


# ════════════════════════════════════════════════════════════════════════════════
# Background: poll DB for dashboard-sent commands every 3 s
# ════════════════════════════════════════════════════════════════════════════════

def command_poller():
    while running:
        time.sleep(3)
        cmds = get_pending_commands()
        for cmd in cmds:
            c, p = cmd["command"], cmd["parameters"] or ""
            if c == "POWER_ON":
                send_to_arduino("LCD:Powering ON   |Via dashboard...|")
            elif c == "POWER_OFF":
                send_to_arduino("RESET")
            elif c == "RESET_SESSION":
                send_to_arduino("RESET")
            elif c == "UPDATE_RATE":
                send_to_arduino(f"RATE:{p}")
            elif c == "GET_STATUS":
                log.info(f"Current state: {arduino_state}")
            elif c.startswith("LCD:"):
                send_to_arduino(c)


# ════════════════════════════════════════════════════════════════════════════════
# Main loop
# ════════════════════════════════════════════════════════════════════════════════

def main():
    global ser, running

    port = SERIAL_PORT or auto_detect_port()
    if not port:
        log.error("No Arduino port found. Pass port as first argument, e.g.: python playmeter_bridge.py COM7")
        sys.exit(1)

    # Start background command poller
    t = threading.Thread(target=command_poller, daemon=True)
    t.start()

    while running:
        try:
            log.info(f"Connecting to {port} @ {BAUD_RATE} baud...")
            ser = serial.Serial(port, BAUD_RATE, timeout=1)
            time.sleep(2)   # wait for Arduino to reset after DTR toggle
            log.info("Connected. Listening...")

            while running:
                line = ser.readline().decode("utf-8", errors="ignore")
                if line:
                    handle_message(line)

        except serial.SerialException as e:
            log.error(f"Serial error: {e}. Reconnecting in {RECONNECT_DELAY}s...")
            if ser:
                try:
                    ser.close()
                except:
                    pass
            time.sleep(RECONNECT_DELAY)

        except KeyboardInterrupt:
            log.info("Stopping bridge...")
            running = False


if __name__ == "__main__":
    main()
