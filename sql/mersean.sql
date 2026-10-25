-- Oak & Blade barbershop database
-- Import this in phpMyAdmin (Import tab) or run it in the SQL tab.

CREATE DATABASE IF NOT EXISTS mersean;
USE mersean;

-- Remove old versions of these tables (the old restaurant tables had different columns).
-- NOTE: the accounts table is kept so existing logins are not deleted.
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS services;
DROP TABLE IF EXISTS barbers;
DROP TABLE IF EXISTS password_resets;
SET FOREIGN_KEY_CHECKS = 1;

-- Accounts (customers, staff, admin) --
CREATE TABLE IF NOT EXISTS accounts (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    firstname  VARCHAR(50)  NOT NULL,
    lastname   VARCHAR(50)  NOT NULL,
    email      VARCHAR(100) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    role       ENUM('customer','staff','admin') NOT NULL DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Password reset tokens (used by forgotPassword / resetPassword) --
CREATE TABLE IF NOT EXISTS password_resets (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(100) NOT NULL,
    token      VARCHAR(100) NOT NULL,
    expires_at DATETIME NOT NULL,
    used       TINYINT(1) NOT NULL DEFAULT 0
);

-- Services --
CREATE TABLE IF NOT EXISTS services (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    description TEXT,
    price       DECIMAL(8,2) NOT NULL,
    duration    INT NOT NULL,                 -- minutes
    category    ENUM('haircut','beard','package') NOT NULL,
    icon        VARCHAR(20)  DEFAULT 'scissors',
    image       VARCHAR(100) DEFAULT NULL,    -- file name inside assets/img/services/
    popular     TINYINT(1) NOT NULL DEFAULT 0
);

-- Barbers --
CREATE TABLE IF NOT EXISTS barbers (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    name      VARCHAR(100) NOT NULL,
    specialty VARCHAR(150),
    photo     VARCHAR(50)                     -- file name without .jpg (marco, alex, sam)
);

-- Appointments --
CREATE TABLE IF NOT EXISTS appointments (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    user_id          INT NOT NULL,
    service_id       INT NOT NULL,
    barber_id        INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time VARCHAR(10) NOT NULL,    -- like "9:00 AM" (same as getTimeSlots())
    contact          VARCHAR(50) NOT NULL,
    notes            VARCHAR(255) DEFAULT NULL,
    status           ENUM('Pending','Confirmed','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)    REFERENCES accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id),
    FOREIGN KEY (barber_id)  REFERENCES barbers(id),
    -- one barber cannot have two active bookings at the same date and time
    UNIQUE KEY one_barber_one_time (barber_id, appointment_date, appointment_time)
);

-- Starter data --
INSERT INTO services (name, description, price, duration, category, icon, popular) VALUES
('Classic haircut',  'A timeless cut tailored to you. Includes a wash and a styled finish.',          30, 30, 'haircut', 'scissors', 1),
('Skin fade',        'A seamless fade with crisp edges and a clean, modern finish.',                  35, 45, 'haircut', 'spark',    0),
('Beard trim',       'Shape, line up, and tame your beard. Finished with nourishing oil.',            20, 20, 'beard',   'user',     0),
('Cut & beard',      'The full refresh. A tailored haircut paired with a precise beard trim.',        45, 60, 'package', 'check',    1),
('Hot towel shave',  'A traditional straight-razor shave with a warm towel and aftercare.',           25, 30, 'beard',   'drop',     0),
('Kids'' haircut',   'A fresh look for our younger neighbors. For children 12 and under.',            25, 30, 'haircut', 'smile',    0);

INSERT INTO barbers (name, specialty, photo) VALUES
('Marco Rossi',  'Classic cuts & precision fades', 'marco'),
('Alex Johnson', 'Fades & beard styling',          'alex'),
('Sam Lee',      'Modern & textured cuts',         'sam');
