CREATE DATABASE IF NOT EXISTS parking_management;
USE parking_management;

DROP TABLE IF EXISTS admin_parking_lots;
DROP TABLE IF EXISTS overstay_alerts;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS vehicles;
DROP TABLE IF EXISTS event_reservations;
DROP TABLE IF EXISTS parking_slots;
DROP TABLE IF EXISTS parking_lots;
DROP TABLE IF EXISTS admins;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS password_resets;

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL
);

CREATE TABLE admins (
    user_id INT PRIMARY KEY,
    role VARCHAR(50),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE customers (
    user_id INT PRIMARY KEY,
    license_plate VARCHAR(20),
    subscription_type ENUM('Monthly','Daily','Hourly') DEFAULT 'Daily',
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE parking_lots (
    lot_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    location VARCHAR(255),
    total_slots INT NOT NULL
);

CREATE TABLE parking_slots (
    slot_id INT AUTO_INCREMENT PRIMARY KEY,
    lot_id INT NOT NULL,
    slot_number VARCHAR(10) NOT NULL,
    status ENUM('Available','Occupied','Reserved') NOT NULL DEFAULT 'Available',
    type ENUM('Compact','Large','Handicapped') NOT NULL,
    hourly_rate DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (lot_id) REFERENCES parking_lots(lot_id) ON DELETE CASCADE
);

CREATE TABLE vehicles (
    vehicle_id INT AUTO_INCREMENT PRIMARY KEY,
    license_plate VARCHAR(20) NOT NULL UNIQUE,
    vehicle_type ENUM('Sedan','SUV','Truck','Other') NOT NULL,
    owner_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES customers(user_id) ON DELETE CASCADE
);

CREATE TABLE bookings (
    booking_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    vehicle_id INT NOT NULL,
    slot_id INT NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    status ENUM('Active','Completed','Cancelled') NOT NULL DEFAULT 'Active',
    FOREIGN KEY (customer_id) REFERENCES customers(user_id) ON DELETE CASCADE,
    FOREIGN KEY (slot_id) REFERENCES parking_slots(slot_id) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id) ON DELETE CASCADE
);

CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('Card','Mobile Pay','Cash') NOT NULL,
    payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE
);

CREATE TABLE overstay_alerts (
    alert_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    booking_id INT NOT NULL,
    fine_amount DECIMAL(10,2) NOT NULL,
    status ENUM('Pending','Paid') DEFAULT 'Pending',
    FOREIGN KEY (customer_id) REFERENCES customers(user_id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE
);

CREATE TABLE event_reservations (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    organizer VARCHAR(100) NOT NULL,
    lot_id INT NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    reserved_slots INT NOT NULL,
    FOREIGN KEY (lot_id) REFERENCES parking_lots(lot_id) ON DELETE CASCADE
);

CREATE TABLE admin_parking_lots (
    admin_id INT NOT NULL,
    lot_id INT NOT NULL,
    PRIMARY KEY (admin_id, lot_id),
    FOREIGN KEY (admin_id) REFERENCES admins(user_id) ON DELETE CASCADE,
    FOREIGN KEY (lot_id) REFERENCES parking_lots(lot_id) ON DELETE CASCADE
);

CREATE TABLE password_resets (
    reset_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expiry DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

UPDATE parking_lots SET total_slots = 50 WHERE lot_id IN (1, 2);

INSERT INTO parking_slots (lot_id, slot_number, status, type, hourly_rate) VALUES
-- Compact spots (30)
(1, 'A1', 'Available', 'Compact', 2.50),
(1, 'A2', 'Available', 'Compact', 2.50),
(1, 'A3', 'Available', 'Compact', 2.50),
(1, 'A4', 'Available', 'Compact', 2.50),
(1, 'A5', 'Available', 'Compact', 2.50),
(1, 'A6', 'Available', 'Compact', 2.50),
(1, 'A7', 'Available', 'Compact', 2.50),
(1, 'A8', 'Available', 'Compact', 2.50),
(1, 'A9', 'Available', 'Compact', 2.50),
(1, 'A10', 'Available', 'Compact', 2.50),
(1, 'A11', 'Available', 'Compact', 2.50),
(1, 'A12', 'Available', 'Compact', 2.50),
(1, 'A13', 'Available', 'Compact', 2.50),
(1, 'A14', 'Available', 'Compact', 2.50),
(1, 'A15', 'Available', 'Compact', 2.50),
(1, 'A16', 'Available', 'Compact', 2.50),
(1, 'A17', 'Available', 'Compact', 2.50),
(1, 'A18', 'Available', 'Compact', 2.50),
(1, 'A19', 'Available', 'Compact', 2.50),
(1, 'A20', 'Available', 'Compact', 2.50),
(1, 'A21', 'Available', 'Compact', 2.50),
(1, 'A22', 'Available', 'Compact', 2.50),
(1, 'A23', 'Available', 'Compact', 2.50),
(1, 'A24', 'Available', 'Compact', 2.50),
(1, 'A25', 'Available', 'Compact', 2.50),
(1, 'A26', 'Available', 'Compact', 2.50),
(1, 'A27', 'Available', 'Compact', 2.50),
(1, 'A28', 'Available', 'Compact', 2.50),
(1, 'A29', 'Available', 'Compact', 2.50),
(1, 'A30', 'Available', 'Compact', 2.50),

-- Large spots (15)
(1, 'A31', 'Available', 'Large', 3.00),
(1, 'A32', 'Available', 'Large', 3.00),
(1, 'A33', 'Available', 'Large', 3.00),
(1, 'A34', 'Available', 'Large', 3.00),
(1, 'A35', 'Available', 'Large', 3.00),
(1, 'A36', 'Available', 'Large', 3.00),
(1, 'A37', 'Available', 'Large', 3.00),
(1, 'A38', 'Available', 'Large', 3.00),
(1, 'A39', 'Available', 'Large', 3.00),
(1, 'A40', 'Available', 'Large', 3.00),
(1, 'A41', 'Available', 'Large', 3.00),
(1, 'A42', 'Available', 'Large', 3.00),
(1, 'A43', 'Available', 'Large', 3.00),
(1, 'A44', 'Available', 'Large', 3.00),
(1, 'A45', 'Available', 'Large', 3.00),

-- Handicapped spots (5)
(1, 'A46', 'Available', 'Handicapped', 2.00),
(1, 'A47', 'Available', 'Handicapped', 2.00),
(1, 'A48', 'Available', 'Handicapped', 2.00),
(1, 'A49', 'Available', 'Handicapped', 2.00),
(1, 'A50', 'Available', 'Handicapped', 2.00);

-- Insert 50 spots for Lot B (Compact, Large, and Handicapped)
INSERT INTO parking_slots (lot_id, slot_number, status, type, hourly_rate) VALUES
-- Compact spots (30)
(2, 'B1', 'Available', 'Compact', 2.50),
(2, 'B2', 'Available', 'Compact', 2.50),
(2, 'B3', 'Available', 'Compact', 2.50),
(2, 'B4', 'Available', 'Compact', 2.50),
(2, 'B5', 'Available', 'Compact', 2.50),
(2, 'B6', 'Available', 'Compact', 2.50),
(2, 'B7', 'Available', 'Compact', 2.50),
(2, 'B8', 'Available', 'Compact', 2.50),
(2, 'B9', 'Available', 'Compact', 2.50),
(2, 'B10', 'Available', 'Compact', 2.50),
(2, 'B11', 'Available', 'Compact', 2.50),
(2, 'B12', 'Available', 'Compact', 2.50),
(2, 'B13', 'Available', 'Compact', 2.50),
(2, 'B14', 'Available', 'Compact', 2.50),
(2, 'B15', 'Available', 'Compact', 2.50),
(2, 'B16', 'Available', 'Compact', 2.50),
(2, 'B17', 'Available', 'Compact', 2.50),
(2, 'B18', 'Available', 'Compact', 2.50),
(2, 'B19', 'Available', 'Compact', 2.50),
(2, 'B20', 'Available', 'Compact', 2.50),
(2, 'B21', 'Available', 'Compact', 2.50),
(2, 'B22', 'Available', 'Compact', 2.50),
(2, 'B23', 'Available', 'Compact', 2.50),
(2, 'B24', 'Available', 'Compact', 2.50),
(2, 'B25', 'Available', 'Compact', 2.50),
(2, 'B26', 'Available', 'Compact', 2.50),
(2, 'B27', 'Available', 'Compact', 2.50),
(2, 'B28', 'Available', 'Compact', 2.50),
(2, 'B29', 'Available', 'Compact', 2.50),
(2, 'B30', 'Available', 'Compact', 2.50),

-- Large spots (15)
(2, 'B31', 'Available', 'Large', 3.00),
(2, 'B32', 'Available', 'Large', 3.00),
(2, 'B33', 'Available', 'Large', 3.00),
(2, 'B34', 'Available', 'Large', 3.00),
(2, 'B35', 'Available', 'Large', 3.00),
(2, 'B36', 'Available', 'Large', 3.00),
(2, 'B37', 'Available', 'Large', 3.00),
(2, 'B38', 'Available', 'Large', 3.00),
(2, 'B39', 'Available', 'Large', 3.00),
(2, 'B40', 'Available', 'Large', 3.00),
(2, 'B41', 'Available', 'Large', 3.00),
(2, 'B42', 'Available', 'Large', 3.00),
(2, 'B43', 'Available', 'Large', 3.00),
(2, 'B44', 'Available', 'Large', 3.00),
(2, 'B45', 'Available', 'Large', 3.00),

-- Handicapped spots (5)
(2, 'B46', 'Available', 'Handicapped', 2.00),
(2, 'B47', 'Available', 'Handicapped', 2.00),
(2, 'B48', 'Available', 'Handicapped', 2.00),
(2, 'B49', 'Available', 'Handicapped', 2.00),
(2, 'B50', 'Available', 'Handicapped', 2.00);

INSERT INTO users (name, email, phone, password) VALUES
('Alice Johnson', 'alice@example.com', '555-0101', 'password1'),
('Bob Smith', 'bob@example.com', '555-0202', 'password2'),
('Carol Davis', 'carol@example.com', '555-0303', 'password3');

INSERT INTO admins (user_id, role) VALUES
(1, 'SuperAdmin');

INSERT INTO customers (user_id, license_plate, subscription_type) VALUES
(2, 'ABC123', 'Monthly'),
(3, 'XYZ789', 'Daily');

INSERT INTO parking_lots (name, location, total_slots) VALUES
('Lot A', 'Near Science B Building', 50),
('Lot B', 'Near Engineering Building', 50),
('Lot C', 'Near MacEwan Hall', 50),
('Lot D', 'Near Olympic Oval', 50),
('Lot E', 'Near TFDL', 50);

INSERT INTO admin_parking_lots (admin_id, lot_id) VALUES
(1, 1),
(1, 2);

INSERT INTO parking_slots (lot_id, slot_number, status, type, hourly_rate) VALUES
(1, 'A1', 'Available', 'Compact', 2.50),
(1, 'A2', 'Available', 'Large', 3.00),
(1, 'A3', 'Occupied', 'Handicapped', 2.00);

INSERT INTO vehicles (license_plate, vehicle_type, owner_id) VALUES
('ABC123', 'Sedan', 2),
('XYZ789', 'SUV', 3);

INSERT INTO bookings (customer_id, slot_id, start_time, end_time, status) VALUES
(2, 1, '2025-03-01 08:00:00', '2025-03-01 12:00:00', 'Active'),
(3, 2, '2025-03-01 09:00:00', '2025-03-01 11:00:00', 'Completed');

INSERT INTO payments (booking_id, amount, payment_method) VALUES
(1, 10.00, 'Card'),
(2, 6.00, 'Mobile Pay');

INSERT INTO overstay_alerts (customer_id, booking_id, fine_amount, status) VALUES
(2, 1, 5.00, 'Pending');

INSERT INTO event_reservations (organizer, lot_id, start_time, end_time, reserved_slots) VALUES
('University Events', 2, '2025-04-15 07:00:00', '2025-04-15 19:00:00', 10);

INSERT INTO users (name, email, phone, password) VALUES
('Admin User', 'admin@parkingsystem.com', '555-0404', '$2y$12$NLReMHRQtfFO3/g/xIZ/8uNVKkDSd2UYCJ6lGFzXqzmhfeSamwEfW');

SET @new_admin_id = LAST_INSERT_ID();

INSERT INTO admins (user_id, role) VALUES
(@new_admin_id, 'admin');

INSERT INTO admin_parking_lots (admin_id, lot_id) VALUES
(@new_admin_id, 1),
(@new_admin_id, 2);