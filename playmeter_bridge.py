"""
playmeter_bridge.py

Requirements:
    pip install pyserial pymysql

Usage:
    python playmeter_bridge.py
    python playmeter_bridge.py COM7 1
"""

import serial
import serial.tools.list_ports
import pymysql
import threading
import logging
import uuid
import sys
import time

# ============================================================================
# DATABASE CONFIG
# ============================================================================

DB_HOST = "localhost"
DB_PORT = 3306
DB_USER = "root"
DB_PASS = "@Unknown_bot.06"
DB_NAME = "playmeter_db"

# ============================================================================
# MACHINE CONFIG
# ============================================================================

MACHINE_ID = int(sys.argv[2]) if len(sys.argv) > 2 else 1

# ============================================================================
# SERIAL CONFIG
# ============================================================================

SERIAL_PORT = sys.argv[1] if len(sys.argv) > 1 else None
BAUD_RATE = 9600
RECONNECT_DELAY = 5

# ============================================================================
# LOGGING
# ============================================================================

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s"
)

log = logging.getLogger(__name__)

# ============================================================================
# GLOBALS
# ============================================================================

current_session_id = None
arduino_state = "IDLE"
running = True
ser = None

# ============================================================================
# DATABASE
# ============================================================================

def get_db():
    return pymysql.connect(
        host=DB_HOST,
        port=DB_PORT,
        user=DB_USER,
        password=DB_PASS,
        database=DB_NAME,
        cursorclass=pymysql.cursors.DictCursor,
        autocommit=True
    )

# ============================================================================
# MACHINE STATUS
# ============================================================================

def update_machine_status(status):

    if status not in ["active", "maintenance", "inactive"]:
        status = "active"

    try:
        with get_db() as db:
            with db.cursor() as cur:
                cur.execute(
                    """
                    UPDATE machines
                    SET status=%s
                    WHERE id=%s
                    """,
                    (status, MACHINE_ID)
                )
    except Exception as e:
        log.error(f"update_machine_status: {e}")

# ============================================================================
# SESSION START
# ============================================================================

def log_session_start():

    try:

        with get_db() as db:
            with db.cursor() as cur:

                cur.execute(
                    """
                    SELECT id
                    FROM arduino_units
                    WHERE machine_id=%s
                    LIMIT 1
                    """,
                    (MACHINE_ID,)
                )

                row = cur.fetchone()

                arduino_unit_id = None
                if row:
                    arduino_unit_id = row["id"]

                session_code = "S-" + uuid.uuid4().hex[:10].upper()

                cur.execute(
                    """
                    INSERT INTO sessions
                    (
                        session_code,
                        machine_id,
                        arduino_unit_id,
                        start_time,
                        payment_status,
                        amount_paid
                    )
                    VALUES
                    (
                        %s,
                        %s,
                        %s,
                        NOW(),
                        'pending',
                        0
                    )
                    """,
                    (
                        session_code,
                        MACHINE_ID,
                        arduino_unit_id
                    )
                )

                session_id = cur.lastrowid

                cur.execute(
                    """
                    UPDATE machines
                    SET current_session_id=%s
                    WHERE id=%s
                    """,
                    (
                        session_id,
                        MACHINE_ID
                    )
                )

                log.info(f"Session started: {session_id}")

                return session_id

    except Exception as e:
        log.error(f"log_session_start: {e}")
        return None

# ============================================================================
# SESSION END
# ============================================================================

def log_session_end(session_id, total_seconds, amount):

    try:

        duration_minutes = round(total_seconds / 60.0, 2)

        with get_db() as db:
            with db.cursor() as cur:

                cur.execute(
                    """
                    UPDATE sessions
                    SET
                        end_time=NOW(),
                        duration_minutes=%s,
                        total_cost=%s
                    WHERE id=%s
                    """,
                    (
                        duration_minutes,
                        amount,
                        session_id
                    )
                )

                cur.execute(
                    """
                    UPDATE machines
                    SET
                        total_plays = total_plays + 1,
                        total_revenue = total_revenue + %s,
                        current_session_id = NULL
                    WHERE id=%s
                    """,
                    (
                        amount,
                        MACHINE_ID
                    )
                )

        log.info(f"Session ended: {session_id}")

    except Exception as e:
        log.error(f"log_session_end: {e}")

# ============================================================================
# PAYMENT
# ============================================================================

def log_payment(session_id):

    try:

        with get_db() as db:
            with db.cursor() as cur:

                cur.execute(
                    """
                    UPDATE sessions
                    SET payment_status='paid'
                    WHERE id=%s
                    """,
                    (session_id,)
                )

        log.info(f"Session paid: {session_id}")

    except Exception as e:
        log.error(f"log_payment: {e}")

# ============================================================================
# ALERTS
# ============================================================================

def log_alert(alert_type):

    try:

        with get_db() as db:
            with db.cursor() as cur:

                cur.execute(
                    """
                    INSERT INTO maintenance
                    (
                        machine_id,
                        issue_description,
                        resolved
                    )
                    VALUES
                    (
                        %s,
                        %s,
                        0
                    )
                    """,
                    (
                        MACHINE_ID,
                        alert_type
                    )
                )

        log.warning(alert_type)

    except Exception as e:
        log.error(f"log_alert: {e}")

# ============================================================================
# HEARTBEAT
# ============================================================================

def update_heartbeat(state):

    try:

        status = "active"

        with get_db() as db:
            with db.cursor() as cur:

                cur.execute(
                    """
                    UPDATE arduino_units
                    SET
                        last_seen = NOW(),
                        status = %s
                    WHERE machine_id=%s
                    """,
                    (
                        status,
                        MACHINE_ID
                    )
                )

    except Exception as e:
        log.error(f"update_heartbeat: {e}")

# ============================================================================
# COMMANDS
# ============================================================================

def get_pending_commands():

    try:

        with get_db() as db:
            with db.cursor() as cur:

                cur.execute(
                    """
                    SELECT
                        c.id,
                        c.command,
                        c.parameters
                    FROM arduino_commands c
                    JOIN arduino_units a
                        ON c.arduino_unit_id = a.id
                    WHERE
                        a.machine_id=%s
                        AND c.status='pending'
                    ORDER BY c.created_at ASC
                    LIMIT 5
                    """,
                    (MACHINE_ID,)
                )

                rows = cur.fetchall()

                if rows:

                    ids = [r["id"] for r in rows]

                    placeholders = ",".join(["%s"] * len(ids))

                    cur.execute(
                        f"""
                        UPDATE arduino_commands
                        SET status='sent'
                        WHERE id IN ({placeholders})
                        """,
                        ids
                    )

                return rows

    except Exception as e:
        log.error(f"get_pending_commands: {e}")
        return []

# ============================================================================
# SERIAL
# ============================================================================

def auto_detect_port():

    for p in serial.tools.list_ports.comports():

        desc = (p.description or "").lower()

        if (
            "arduino" in desc or
            "ch340" in desc or
            "cp210" in desc or
            "usb serial" in desc
        ):
            return p.device

    return None

def send_to_arduino(msg):

    global ser

    try:
        if ser and ser.is_open:
            ser.write((msg + "\n").encode())
            log.info(f"TX: {msg}")
    except Exception as e:
        log.error(e)

# ============================================================================
# HANDLE ARDUINO EVENTS
# ============================================================================

def handle_message(line):

    global current_session_id
    global arduino_state

    line = line.strip()

    if not line:
        return

    log.info(f"RX: {line}")

    parts = line.split(",")

    event = parts[0]

    if event == "PS_POWER_ON":

        update_machine_status("active")

    elif event == "SESSION_START":

        current_session_id = log_session_start()

        update_machine_status("active")

    elif event == "SESSION_END":

        total_seconds = int(parts[1]) if len(parts) > 1 else 0
        amount = float(parts[2]) if len(parts) > 2 else 0

        if current_session_id:
            log_session_end(
                current_session_id,
                total_seconds,
                amount
            )

    elif event == "PAYMENT_DONE":

        if current_session_id:

            log_payment(current_session_id)

            current_session_id = None

        update_machine_status("active")

    elif event.startswith("ALERT"):

        log_alert(event)

        update_machine_status("maintenance")

    elif event == "HEARTBEAT":

        arduino_state = parts[1] if len(parts) > 1 else "UNKNOWN"

        update_heartbeat(arduino_state)

# ============================================================================
# COMMAND POLLER
# ============================================================================

def command_poller():

    while running:

        cmds = get_pending_commands()

        for cmd in cmds:

            command = cmd["command"]
            params = cmd["parameters"] or ""

            if params:
                send_to_arduino(f"{command}:{params}")
            else:
                send_to_arduino(command)

        time.sleep(3)

# ============================================================================
# MAIN
# ============================================================================

def main():

    global ser

    port = SERIAL_PORT or auto_detect_port()

    if not port:

        log.error("Arduino port not found")

        return

    threading.Thread(
        target=command_poller,
        daemon=True
    ).start()

    while running:

        try:

            log.info(f"Connecting {port}")

            ser = serial.Serial(
                port,
                BAUD_RATE,
                timeout=1
            )

            time.sleep(2)

            log.info("Connected")

            while True:

                line = ser.readline().decode(
                    "utf-8",
                    errors="ignore"
                )

                if line:
                    handle_message(line)

        except serial.SerialException as e:

            log.error(e)

            time.sleep(RECONNECT_DELAY)

# ============================================================================
# ENTRY
# ============================================================================

if __name__ == "__main__":
    main()