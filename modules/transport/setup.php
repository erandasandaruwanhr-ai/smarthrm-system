<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Transport Module Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Transport Module Database Setup</h4>
                    </div>
                    <div class="card-body">
                        <?php
                        require_once '../../config/config.php';

                        try {
                            $db = new Database();

                            echo '<div class="alert alert-info">Creating transport module tables...</div>';

                            // Create vehicles table
                            $db->execute("CREATE TABLE IF NOT EXISTS vehicles (
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
                            echo '<div class="alert alert-success">✓ Vehicles table created</div>';

                            // Create drivers table
                            $db->execute("CREATE TABLE IF NOT EXISTS drivers (
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
                            echo '<div class="alert alert-success">✓ Drivers table created</div>';

                            // Create transport_requests table
                            $db->execute("CREATE TABLE IF NOT EXISTS transport_requests (
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
                                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                            )");

                            // Create transport_allocations table
                            $db->execute("CREATE TABLE IF NOT EXISTS transport_allocations (
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
                                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                            )");
                            echo '<div class="alert alert-success">✓ Transport requests table created</div>';
                            echo '<div class="alert alert-success">✓ Transport allocations table created</div>';

                            // Add sample data
                            echo '<div class="alert alert-info">Adding sample data...</div>';

                            // Sample vehicles
                            $sample_vehicles = [
                                ['CBZ-1234', 'Car', 'Toyota', 'Corolla', 2020, 'White', 'Petrol', 5, 'available', '7C'],
                                ['CBZ-5678', 'Van', 'Nissan', 'Caravan', 2019, 'Silver', 'Diesel', 8, 'available', 'Pannala'],
                                ['CBZ-9012', 'Bus', 'Tata', 'LP 909', 2018, 'Blue', 'Diesel', 25, 'maintenance', 'Head Office'],
                                ['CBZ-3456', 'Car', 'Honda', 'Civic', 2021, 'Black', 'Petrol', 5, 'available', 'Kobeigane'],
                                ['CBZ-7890', 'Truck', 'Mahindra', 'Bolero', 2017, 'Red', 'Diesel', 3, 'out_of_service', 'JECOE']
                            ];

                            foreach ($sample_vehicles as $vehicle) {
                                try {
                                    $db->execute("INSERT IGNORE INTO vehicles (vehicle_number, vehicle_type, brand, model, year, color, fuel_type, seating_capacity, status, location, created_by)
                                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)", $vehicle);
                                } catch (Exception $e) {
                                    // Ignore duplicate entries
                                }
                            }

                            // Sample drivers
                            $sample_drivers = [
                                ['EMP001', 'John Silva', 'DL123456', 'Heavy Vehicle', '2025-12-31', 10, 'active', 1, '7C', '0771234567'],
                                ['EMP002', 'Kamal Perera', 'DL789012', 'Light Vehicle', '2025-06-30', 5, 'active', 0, 'Pannala', '0777654321'],
                                ['EMP003', 'Nimal Fernando', 'DL345678', 'Heavy Vehicle', '2025-03-15', 8, 'active', 1, 'Head Office', '0712345678'],
                                ['EMP004', 'Sunil Wijesinghe', 'DL901234', 'Light Vehicle', '2026-01-20', 3, 'inactive', 0, 'Kobeigane', '0789876543'],
                                ['EMP005', 'Gamini Rajapakse', 'DL567890', 'Light Vehicle', '2024-11-10', 12, 'active', 0, 'JECOE', '0701122334']
                            ];

                            foreach ($sample_drivers as $driver) {
                                try {
                                    $db->execute("INSERT IGNORE INTO drivers (emp_number, emp_name, license_number, license_type, license_expiry, experience_years, status, is_on_duty, location, phone, created_by)
                                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)", $driver);
                                } catch (Exception $e) {
                                    // Ignore duplicate entries
                                }
                            }

                            echo '<div class="alert alert-success">✓ Sample data added successfully</div>';
                            echo '<div class="alert alert-success"><h5>Setup completed successfully!</h5></div>';
                            echo '<div class="text-center mt-4">';
                            echo '<a href="index.php" class="btn btn-primary me-3">Go to Transport Management</a>';
                            echo '<a href="vehicle_register.php" class="btn btn-secondary me-3">Manage Vehicles</a>';
                            echo '<a href="vehicle_pool.php" class="btn btn-success">Vehicle Pool</a>';
                            echo '</div>';

                        } catch (Exception $e) {
                            echo '<div class="alert alert-danger"><strong>Setup Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
                            echo '<div class="alert alert-warning">Please check your database connection and try again.</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>