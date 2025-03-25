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

----------------------------------------------------
-- 1. Supertype: Users
----------------------------------------------------
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL
);

----------------------------------------------------
-- 2. Subtype: Admins (ISA: Admin is a User)
----------------------------------------------------
CREATE TABLE admins (
    user_id INT PRIMARY KEY,
    role VARCHAR(50),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

----------------------------------------------------
-- 3. Subtype: Customers (ISA: Customer is a User)
----------------------------------------------------
CREATE TABLE customers (
    user_id INT PRIMARY KEY,
    license_plate VARCHAR(20),
    subscription_type ENUM('Monthly','Daily','Hourly') DEFAULT 'Daily',
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

----------------------------------------------------
-- 4. ParkingLot
----------------------------------------------------
CREATE TABLE parking_lots (
    lot_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    location VARCHAR(255),
    total_slots INT NOT NULL
);

----------------------------------------------------
-- 5. ParkingSlot (Weak Entity: depends on ParkingLot)
----------------------------------------------------
CREATE TABLE parking_slots (
    slot_id INT AUTO_INCREMENT PRIMARY KEY,
    lot_id INT NOT NULL,
    slot_number VARCHAR(10) NOT NULL,
    status ENUM('Available','Occupied','Reserved') NOT NULL DEFAULT 'Available',
    type ENUM('Compact','Large','Handicapped') NOT NULL,
    hourly_rate DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (lot_id) REFERENCES parking_lots(lot_id) ON DELETE CASCADE
);

----------------------------------------------------
-- 6. Vehicle (Owned by Customer)
----------------------------------------------------
CREATE TABLE vehicles (
    vehicle_id INT AUTO_INCREMENT PRIMARY KEY,
    license_plate VARCHAR(20) NOT NULL UNIQUE,
    vehicle_type ENUM('Sedan','SUV','Truck','Other') NOT NULL,
    owner_id INT NOT NULL,
    FOREIGN KEY (owner_id) REFERENCES customers(user_id) ON DELETE CASCADE
);

----------------------------------------------------
-- 7. Booking (Links Customer with ParkingSlot)
----------------------------------------------------
CREATE TABLE bookings (
    booking_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    slot_id INT NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    status ENUM('Active','Completed','Cancelled') NOT NULL DEFAULT 'Active',
    FOREIGN KEY (customer_id) REFERENCES customers(user_id) ON DELETE CASCADE,
    FOREIGN KEY (slot_id) REFERENCES parking_slots(slot_id) ON DELETE CASCADE
);

----------------------------------------------------
-- 8. Payment (Linked to Booking)
----------------------------------------------------
CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('Card','Mobile Pay','Cash') NOT NULL,
    payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE
);

----------------------------------------------------
-- 9. OverstayAlert (Generated if Booking exceeds allotted time)
----------------------------------------------------
CREATE TABLE overstay_alerts (
    alert_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    booking_id INT NOT NULL,
    fine_amount DECIMAL(10,2) NOT NULL,
    status ENUM('Pending','Paid') DEFAULT 'Pending',
    FOREIGN KEY (customer_id) REFERENCES customers(user_id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE
);

----------------------------------------------------
-- 10. EventReservation (Reserves Parking Slots for Events)
----------------------------------------------------
CREATE TABLE event_reservations (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    organizer VARCHAR(100) NOT NULL,
    lot_id INT NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    reserved_slots INT NOT NULL,
    FOREIGN KEY (lot_id) REFERENCES parking_lots(lot_id) ON DELETE CASCADE
);

----------------------------------------------------
-- 11. Admin-ParkingLot Assignment (Many-to-Many Relationship)
----------------------------------------------------
CREATE TABLE admin_parking_lots (
    admin_id INT NOT NULL,
    lot_id INT NOT NULL,
    PRIMARY KEY (admin_id, lot_id),
    FOREIGN KEY (admin_id) REFERENCES admins(user_id) ON DELETE CASCADE,
    FOREIGN KEY (lot_id) REFERENCES parking_lots(lot_id) ON DELETE CASCADE
);

----------------------------------------------------
-- Data Seeding: Sample Data.
----------------------------------------------------

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
('Lot A', 'North Campus', 50),
('Lot B', 'South Campus', 30);

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