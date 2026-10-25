-- Run once in phpMyAdmin (database: merseans).
-- Adds the column that stores each service's reference photo file name.
-- (MariaDB / XAMPP: safe to run even if the column already exists.)
ALTER TABLE services ADD COLUMN IF NOT EXISTS image VARCHAR(100) DEFAULT NULL;
