<?php
require_once '../../config/config.php';

$db = new Database();

try {
    // Create vehicles table
    $db->query("CREATE TABLE IF NOT EXISTS vehicles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        vehicle_number VARCHAR(50) NOT NULL UNIQUE,
        vehicle_type VARCHAR(50) NOT NULL,
        brand VARCHAR(50),
        model VARCHAR(50),
        year INT,
        color VARCHAR(30),
        fuel_type VARCHAR(20),
        seating_capacity INT,
        status ENUM('available', 'in_use', 'maintenance', 'out_of_service') DEFAULT 'available',
        location VARCHAR(50),
        insurance_expiry DATE,
        license_expiry DATE,
        notes TEXT,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    echo "Vehicles table created successfully<br>";

    // Create drivers table
    $db->query("CREATE TABLE IF NOT EXISTS drivers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        emp_id INT,
        emp_number VARCHAR(20),
        emp_name VARCHAR(100) NOT NULL,
        license_number VARCHAR(50) NOT NULL UNIQUE,
        license_type VARCHAR(50),
        license_expiry DATE,
        experience_years INT,
        status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
        is_on_duty BOOLEAN DEFAULT 0,
        location VARCHAR(50),
        phone VARCHAR(20),
        notes TEXT,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    echo "Drivers table created successfully<br>";

    // Create transport_requests table
    $db->query("CREATE TABLE IF NOT EXISTS transport_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        emp_id INT NOT NULL,
        emp_number VARCHAR(20),
        emp_name VARCHAR(100),
        emp_location VARCHAR(50),
        request_type ENUM('one_way', 'round_trip', 'multi_destination') NOT NULL,
        purpose TEXT NOT NULL,
        departure_location VARCHAR(200) NOT NULL,
        destination VARCHAR(200) NOT NULL,
        departure_date DATE NOT NULL,
        departure_time TIME NOT NULL,
        return_date DATE,
        return_time TIME,
        passenger_count INT NOT NULL DEFAULT 1,
        passenger_names TEXT NOT NULL,
        special_requirements TEXT,
        urgency_level ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
        status ENUM('pending', 'approved', 'allocated', 'completed', 'cancelled') DEFAULT 'pending',
        approved_by INT,
        approved_at TIMESTAMP NULL,
        allocated_vehicle_id INT,
        allocated_driver_id INT,
        allocated_at TIMESTAMP NULL,
        scheduled_departure DATETIME,
        scheduled_return DATETIME,
        actual_departure DATETIME,
        actual_return DATETIME,
        completion_notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (allocated_vehicle_id) REFERENCES vehicles(id),
        FOREIGN KEY (allocated_driver_id) REFERENCES drivers(id)
    )");
    echo "Transport requests table created successfully<br>";

    // Create transport_allocations table
    $db->query("CREATE TABLE IF NOT EXISTS transport_allocations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT NOT NULL,
        vehicle_id INT NOT NULL,
        driver_id INT NOT NULL,
        allocated_by INT,
        scheduled_departure DATETIME NOT NULL,
        scheduled_return DATETIME,
        actual_departure DATETIME,
        actual_return DATETIME,
        status ENUM('scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
        odometer_start INT,
        odometer_end INT,
        fuel_start DECIMAL(5,2),
        fuel_end DECIMAL(5,2),
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (request_id) REFERENCES transport_requests(id),
        FOREIGN KEY (vehicle_id) REFERENCES vehicles(id),
        FOREIGN KEY (driver_id) REFERENCES drivers(id)
    )");
    echo "Transport allocations table created successfully<br>";

    // Insert sample vehicles
    $sample_vehicles = [
        ['CBZ-1234', 'Car', 'Toyota', 'Corolla', 2020, 'White', 'Petrol', 5, 'available', '7C'],
        ['CBZ-5678', 'Van', 'Nissan', 'Caravan', 2019, 'Silver', 'Diesel', 8, 'available', 'Pannala'],
        ['CBZ-9012', 'Bus', 'Tata', 'LP 909', 2018, 'Blue', 'Diesel', 25, 'maintenance', 'Head Office'],
        ['CBZ-3456', 'Car', 'Honda', 'Civic', 2021, 'Black', 'Petrol', 5, 'available', 'Kobeigane'],
        ['CBZ-7890', 'Truck', 'Mahindra', 'Bolero', 2017, 'Red', 'Diesel', 3, 'out_of_service', 'JECOE']
    ];

    foreach ($sample_vehicles as $vehicle) {
        try {
            $db->query("INSERT IGNORE INTO vehicles (vehicle_number, vehicle_type, brand, model, year, color, fuel_type, seating_capacity, status, location, created_by)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)", $vehicle);
        } catch (Exception $e) {
            // Ignore duplicate entries
        }
    }
    echo "Sample vehicles added<br>";

    // Insert sample drivers
    $sample_drivers = [
        ['EMP001', 'John Silva', 'DL123456', 'Heavy Vehicle', '2025-12-31', 10, 'active', 1, '7C', '0771234567'],
        ['EMP002', 'Kamal Perera', 'DL789012', 'Light Vehicle', '2025-06-30', 5, 'active', 0, 'Pannala', '0777654321'],
        ['EMP003', 'Nimal Fernando', 'DL345678', 'Heavy Vehicle', '2025-03-15', 8, 'active', 1, 'Head Office', '0712345678'],
        ['EMP004', 'Sunil Wijesinghe', 'DL901234', 'Light Vehicle', '2026-01-20', 3, 'inactive', 0, 'Kobeigane', '0789876543'],
        ['EMP005', 'Gamini Rajapakse', 'DL567890', 'Light Vehicle', '2024-11-10', 12, 'active', 0, 'JECOE', '0701122334']
    ];

    foreach ($sample_drivers as $driver) {
        try {
            $db->query("INSERT IGNORE INTO drivers (emp_number, emp_name, license_number, license_type, license_expiry, experience_years, status, is_on_duty, location, phone, created_by)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)", $driver);
        } catch (Exception $e) {
            // Ignore duplicate entries
        }
    }
    echo "Sample drivers added<br>";

    echo "<br><strong>Transport module database tables initialized successfully!</strong><br>";
    echo "<a href='index.php'>Go to Transport Management</a>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>