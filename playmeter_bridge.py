"""
playmeter_bridge.py — With Daraja STK Push Payment
=====================================================
When a session ends, the bridge:
1. Looks up the customer's phone from sessions -> customers table
2. Fires an STK Push via Daraja sandbox
3. Arduino counts down 30 seconds for customer to enter M-Pesa PIN
4. Operator presses BTN_B to confirm → session marked paid
"""

import serial
import serial.tools.list_ports
import pymysql
import threading
import logging
import requests
import base64
import uuid
import sys
import time
import io
from datetime import datetime

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
# DARAJA CONFIG
# ============================================================================
DARAJA_BASE_URL = "https://sandbox.safaricom.co.ke"
DARAJA_CONSUMER_KEY = "e1pyUe0qftDTfE65OBlV73iq4y9d1QkPMS28pUON3EFJLFYM"
DARAJA_CONSUMER_SECRET = "NgLyiRMtpGrPxUzwwxqnHNPAA8y3vBaVvjQaAWQ0Wiy6f2nAxOwUIL4AlungStkI"
DARAJA_SHORTCODE = "174379"
DARAJA_PASSKEY = "bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919"
DARAJA_CALLBACK_URL = "https://yourdomain.com/mpesa/callback"

# ============================================================================
# LOGGING
# ============================================================================
_fmt = logging.Formatter("%(asctime)s [%(levelname)s] %(message)s")
_file_handler = logging.FileHandler("playmeter_bridge.log", encoding="utf-8")
_file_handler.setFormatter(_fmt)
_stream_handler = logging.StreamHandler(
    io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8", errors="replace")
)
_stream_handler.setFormatter(_fmt)
logging.basicConfig(level=logging.INFO, handlers=[_file_handler, _stream_handler])
log = logging.getLogger(__name__)

# ============================================================================
# GLOBALS
# ============================================================================
current_session_id = None
current_session_phone = None
current_amount_owed = 0.0
arduino_state = "IDLE"
running = True
ser = None

# ============================================================================
# DATABASE
# ============================================================================
def get_db():
    return pymysql.connect(
        host=DB_HOST, port=DB_PORT, user=DB_USER, password=DB_PASS,
        database=DB_NAME, cursorclass=pymysql.cursors.DictCursor, autocommit=True
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
                    "UPDATE machines SET status=%s WHERE id=%s",
                    (status, MACHINE_ID)
                )
    except Exception as e:
        log.error(f"update_machine_status: {e}")

# ============================================================================
# SESSION START — FIXED
# - NO game_name column (does not exist in sessions table)
# - Always links to customer with phone 0799718653
# - Machine name = FIFA 26 (run once: UPDATE machines SET name='FIFA 26' WHERE id=1)
# ============================================================================
def log_session_start():
    try:
        with get_db() as db:
            with db.cursor() as cur:

                # Get arduino unit for this machine
                cur.execute(
                    "SELECT id FROM arduino_units WHERE machine_id=%s LIMIT 1",
                    (MACHINE_ID,)
                )
                row = cur.fetchone()
                arduino_unit_id = row["id"] if row else None

                # Always link to customer 0799718653 (Scripter Cryptoking)
                cur.execute(
                    "SELECT id FROM customers WHERE phone_number='0799718653' LIMIT 1"
                )
                customer_row = cur.fetchone()
                customer_id = customer_row["id"] if customer_row else None

                if not customer_id:
                    log.error("Customer 0799718653 not found in customers table!")

                session_code = "S-" + uuid.uuid4().hex[:10].upper()

                # INSERT — no game_name column, that comes from machines.name
                cur.execute("""
                    INSERT INTO sessions
                        (session_code, machine_id, arduino_unit_id, customer_id,
                         start_time, payment_status, amount_paid)
                    VALUES
                        (%s, %s, %s, %s, NOW(), 'pending', 0)
                """, (session_code, MACHINE_ID, arduino_unit_id, customer_id))

                session_id = cur.lastrowid

                cur.execute(
                    "UPDATE machines SET current_session_id=%s WHERE id=%s",
                    (session_id, MACHINE_ID)
                )

                log.info(
                    f"Session started: id={session_id} code={session_code} "
                    f"customer_id={customer_id}"
                )
                return session_id

    except Exception as e:
        log.error(f"log_session_start: {e}")
        return None

# ============================================================================
# SESSION END + FETCH PHONE + FIRE STK PUSH
# ============================================================================
def log_session_end(session_id, total_seconds, amount):
    global current_session_phone, current_amount_owed
    try:
        duration_minutes = round(total_seconds / 60.0, 2)

        with get_db() as db:
            with db.cursor() as cur:
                cur.execute(
                    "UPDATE sessions SET end_time=NOW(), duration_minutes=%s, total_cost=%s WHERE id=%s",
                    (duration_minutes, amount, session_id)
                )
                cur.execute(
                    """UPDATE machines
                       SET total_plays = total_plays + 1,
                           total_revenue = total_revenue + %s,
                           current_session_id = NULL
                       WHERE id=%s""",
                    (amount, MACHINE_ID)
                )

                # Fetch linked customer phone
                cur.execute(
                    """SELECT c.phone_number, c.full_name
                       FROM sessions s
                       LEFT JOIN customers c ON s.customer_id = c.id
                       WHERE s.id=%s""",
                    (session_id,)
                )
                row = cur.fetchone()

                current_amount_owed = amount

                print()
                print("=" * 48)
                print(" SESSION ENDED")
                print("=" * 48)
                print(f" Session ID : {session_id}")
                print(f" Duration   : {total_seconds // 60}m {total_seconds % 60}s")
                print(f" Amount     : Ksh {amount:.2f}")

                if row and row["phone_number"]:
                    current_session_phone = row["phone_number"]
                    print(f" Customer   : {row['full_name']}")
                    print(f" Phone      : {current_session_phone}")
                    print(f" STK Push   : Sending to phone now...")
                    print("=" * 48)
                    print()
                    threading.Thread(
                        target=send_stk_push,
                        args=(current_session_phone, amount, session_id),
                        daemon=True
                    ).start()
                else:
                    current_session_phone = None
                    print(f" Customer   : Not linked to session")
                    print(f" STK Push   : Skipped (no phone). Press BTN_B to confirm manually.")
                    print("=" * 48)
                    print()

    except Exception as e:
        log.error(f"log_session_end: {e}")

# ============================================================================
# DARAJA STK PUSH
# ============================================================================
def get_daraja_token():
    try:
        credentials = base64.b64encode(
            f"{DARAJA_CONSUMER_KEY}:{DARAJA_CONSUMER_SECRET}".encode()
        ).decode()
        r = requests.get(
            f"{DARAJA_BASE_URL}/oauth/v1/generate?grant_type=client_credentials",
            headers={"Authorization": f"Basic {credentials}"},
            timeout=10
        )
        r.raise_for_status()
        token = r.json().get("access_token")
        log.info("Daraja token OK")
        return token
    except Exception as e:
        log.error(f"get_daraja_token: {e}")
        return None

def format_phone(phone: str) -> str:
    phone = phone.strip().replace(" ", "").replace("-", "")
    if phone.startswith("+"): phone = phone[1:]
    if phone.startswith("0"): phone = "254" + phone[1:]
    if phone.startswith("7") or phone.startswith("1"): phone = "254" + phone
    return phone

def send_stk_push(phone: str, amount: float, session_id: int):
    log.info(f"STK Push -> {phone} Ksh {amount:.0f}")
    token = get_daraja_token()
    if not token:
        log.error("No Daraja token. STK Push failed.")
        return

    timestamp = datetime.now().strftime("%Y%m%d%H%M%S")
    raw_pass = f"{DARAJA_SHORTCODE}{DARAJA_PASSKEY}{timestamp}"
    password = base64.b64encode(raw_pass.encode()).decode()

    phone_fmt = format_phone(phone)
    amount_int = max(1, int(round(amount)))

    payload = {
        "BusinessShortCode": DARAJA_SHORTCODE,
        "Password": password,
        "Timestamp": timestamp,
        "TransactionType": "CustomerPayBillOnline",
        "Amount": amount_int,
        "PartyA": phone_fmt,
        "PartyB": DARAJA_SHORTCODE,
        "PhoneNumber": phone_fmt,
        "CallBackURL": DARAJA_CALLBACK_URL,
        "AccountReference": f"Playmeter-{session_id}",
        "TransactionDesc": f"FIFA 26 Gaming Session"
    }

    try:
        r = requests.post(
            f"{DARAJA_BASE_URL}/mpesa/stkpush/v1/processrequest",
            json=payload,
            headers={
                "Authorization": f"Bearer {token}",
                "Content-Type": "application/json"
            },
            timeout=15
        )
        result = r.json()
        if result.get("ResponseCode") == "0":
            checkout_id = result.get("CheckoutRequestID", "")
            log.info(f"STK Push sent OK. CheckoutRequestID={checkout_id}")
            print(f"\n [M-PESA] Prompt sent to {phone_fmt}. Customer has ~25s to enter PIN.\n")
            save_checkout_id(session_id, checkout_id)
        else:
            log.error(f"STK Push failed: {result.get('errorMessage') or result}")
    except Exception as e:
        log.error(f"send_stk_push error: {e}")

def save_checkout_id(session_id: int, checkout_id: str):
    try:
        with get_db() as db:
            with db.cursor() as cur:
                cur.execute(
                    "UPDATE sessions SET qr_scanned=%s WHERE id=%s",
                    (checkout_id, session_id)
                )
    except Exception as e:
        log.error(f"save_checkout_id: {e}")

# ============================================================================
# PAYMENT CONFIRMED (BTN_B pressed after STK)
# ============================================================================
def log_payment(session_id):
    try:
        with get_db() as db:
            with db.cursor() as cur:
                cur.execute(
                    """SELECT s.customer_id, s.duration_minutes,
                              c.full_name
                       FROM sessions s
                       LEFT JOIN customers c ON s.customer_id = c.id
                       WHERE s.id=%s""",
                    (session_id,)
                )
                row = cur.fetchone()
                customer_id = row["customer_id"] if row else None
                player_name = row["full_name"] if row else None
                duration_minutes = row["duration_minutes"] if row else None  # <-- NEW

                cur.execute(
                    """INSERT INTO plays
                           (session_id, machine_id, customer_id, player_name,
                            plays_count, duration_minutes, amount_paid,
                            payment_method, play_date)
                       VALUES (%s, %s, %s, %s, 1, %s, %s, 'cash', NOW())""",
                    (session_id, MACHINE_ID, customer_id, player_name,
                     duration_minutes, current_amount_owed)   # <-- session_id + duration
                )
                plays_id = cur.lastrowid

                cur.execute(
                    "UPDATE sessions SET payment_status='paid', amount_paid=%s WHERE id=%s",
                    (current_amount_owed, session_id)
                )

                # Update customer stats
                if customer_id:
                    cur.execute(
                        """UPDATE customers SET
                               total_visits = total_visits + 1,
                               total_spent = total_spent + %s
                           WHERE id=%s""",
                        (current_amount_owed, customer_id)
                    )

                log.info(f"Session {session_id} PAID Ksh {current_amount_owed:.2f}")
                print(f"\n PAYMENT CONFIRMED | Session: {session_id} | Amount: Ksh {current_amount_owed:.2f} | Duration: {duration_minutes}min\n")

    except Exception as e:
        log.error(f"log_payment: {e}")

# ============================================================================
# ALERTS
# ============================================================================
def log_alert(alert_type):
    descriptions = {
        "ALERT_NO_START":   "Player detected but did not press START within timeout",
        "ALERT_NO_PAYMENT": "Session ended but payment not confirmed within timeout",
        "ALERT_PERSON_LEFT":"Player left the console mid-session without stopping",
    }
    desc = descriptions.get(alert_type, alert_type)
    try:
        with get_db() as db:
            with db.cursor() as cur:
                cur.execute(
                    "INSERT INTO maintenance (machine_id, issue_description, resolved) VALUES (%s, %s, 0)",
                    (MACHINE_ID, desc)
                )
        log.warning(f"Alert: {desc}")
    except Exception as e:
        log.error(f"log_alert: {e}")

# ============================================================================
# HEARTBEAT
# ============================================================================
def update_heartbeat(state):
    try:
        with get_db() as db:
            with db.cursor() as cur:
                cur.execute(
                    "UPDATE arduino_units SET last_seen=NOW(), status='active' WHERE machine_id=%s",
                    (MACHINE_ID,)
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
                    """SELECT c.id, c.command, c.parameters
                       FROM arduino_commands c
                       JOIN arduino_units a ON c.arduino_unit_id = a.id
                       WHERE a.machine_id=%s AND c.status='pending'
                       ORDER BY c.created_at ASC LIMIT 5""",
                    (MACHINE_ID,)
                )
                rows = cur.fetchall()
                if rows:
                    ids = [r["id"] for r in rows]
                    placeholders = ",".join(["%s"] * len(ids))
                    cur.execute(
                        f"UPDATE arduino_commands SET status='sent' WHERE id IN ({placeholders})",
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
        if any(k in desc for k in ["arduino", "ch340", "cp210", "usb serial"]):
            log.info(f"Auto-detected: {p.device}")
            return p.device
    return None

def send_to_arduino(msg):
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
    global current_session_id, arduino_state, current_session_phone, current_amount_owed
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
        amount = float(parts[2]) if len(parts) > 2 else 0.0
        if current_session_id:
            log_session_end(current_session_id, total_seconds, amount)

    elif event == "PAYMENT_DONE":
        if current_session_id:
            log_payment(current_session_id)
            current_session_id = None
            current_session_phone = None
            current_amount_owed = 0.0
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
        for cmd in get_pending_commands():
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
        log.error("Arduino not found. Usage: python playmeter_bridge.py COM14 1")
        return

    threading.Thread(target=command_poller, daemon=True).start()

    while running:
        try:
            log.info(f"Connecting to {port}...")
            ser = serial.Serial(port, BAUD_RATE, timeout=1)
            time.sleep(2)
            log.info("Connected. Listening...")
            while True:
                line = ser.readline().decode("utf-8", errors="ignore")
                if line:
                    handle_message(line)
        except serial.SerialException as e:
            log.error(f"Serial error: {e}")
            time.sleep(RECONNECT_DELAY)

if __name__ == "__main__":
    main()