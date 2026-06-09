-- playmeter_arduino_migration.sql
-- Run this against your existing playmeter_db to add columns the bridge uses.
-- Safe to run repeatedly (uses IF NOT EXISTS / IGNORE).

-- 1. Add duration_seconds, amount, status, paid_at to plays table (if not already there)
ALTER TABLE plays
  ADD COLUMN IF NOT EXISTS duration_seconds INT DEFAULT 0,
  ADD COLUMN IF NOT EXISTS amount           DECIMAL(10,2) DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS status           VARCHAR(30)   DEFAULT 'playing',
  ADD COLUMN IF NOT EXISTS paid_at          DATETIME      NULL;

-- 2. Add state column to arduino_units so last heartbeat state is stored
ALTER TABLE arduino_units
  ADD COLUMN IF NOT EXISTS state VARCHAR(30) DEFAULT 'idle';

-- 3. Add machine status column for real-time dashboard
ALTER TABLE machines
  ADD COLUMN IF NOT EXISTS status VARCHAR(30) DEFAULT 'active';

-- 4. Create maintenance table if it doesn't exist
CREATE TABLE IF NOT EXISTS maintenance (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  machine_id  INT         NOT NULL,
  issue       TEXT,
  reported_at DATETIME    DEFAULT NOW(),
  resolved_at DATETIME    NULL,
  status      VARCHAR(20) DEFAULT 'open',
  FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE CASCADE
);

-- 5. Register this Arduino unit (adjust unit_id and machine_id as needed)
INSERT IGNORE INTO arduino_units (unit_id, machine_id, firmware_version, status, last_seen)
VALUES ('ARDUINO_001', 1, '1.0.0', 'active', NOW());
