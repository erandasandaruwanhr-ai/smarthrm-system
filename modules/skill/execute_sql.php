<?php
require_once '../../config/config.php';

try {
    $db = new Database();

    // Create skill_matrix_years table
    $sql = "CREATE TABLE IF NOT EXISTS skill_matrix_years (
        id INT PRIMARY KEY AUTO_INCREMENT,
        year INT NOT NULL UNIQUE,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $db->query($sql);
    echo "✓ Created skill_matrix_years table<br>";

    // Create skill_matrix_categories table
    $sql = "CREATE TABLE IF NOT EXISTS skill_matrix_categories (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        display_order INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $db->query($sql);
    echo "✓ Created skill_matrix_categories table<br>";

    // Create skill_matrix_skills table
    $sql = "CREATE TABLE IF NOT EXISTS skill_matrix_skills (
        id INT PRIMARY KEY AUTO_INCREMENT,
        year_id INT NOT NULL,
        category_id INT NOT NULL,
        skill_name VARCHAR(255) NOT NULL,
        skill_description TEXT,
        display_order INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (year_id) REFERENCES skill_matrix_years(id) ON DELETE CASCADE,
        FOREIGN KEY (category_id) REFERENCES skill_matrix_categories(id) ON DELETE CASCADE,
        UNIQUE KEY unique_year_category_skill (year_id, category_id, skill_name)
    )";
    $db->query($sql);
    echo "✓ Created skill_matrix_skills table<br>";

    // Create skill_matrix_assessments table
    $sql = "CREATE TABLE IF NOT EXISTS skill_matrix_assessments (
        id INT PRIMARY KEY AUTO_INCREMENT,
        year_id INT NOT NULL,
        employee_epf VARCHAR(20) NOT NULL,
        manager_epf VARCHAR(20) NOT NULL,
        skill_id INT NOT NULL,
        target_rating INT NOT NULL CHECK (target_rating BETWEEN 1 AND 5),
        current_rating INT NOT NULL CHECK (current_rating BETWEEN 1 AND 5),
        gap_rating INT NOT NULL CHECK (gap_rating BETWEEN 1 AND 5),
        submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (year_id) REFERENCES skill_matrix_years(id) ON DELETE CASCADE,
        FOREIGN KEY (skill_id) REFERENCES skill_matrix_skills(id) ON DELETE CASCADE,
        FOREIGN KEY (employee_epf) REFERENCES employees(epf_number) ON DELETE CASCADE,
        FOREIGN KEY (manager_epf) REFERENCES employees(epf_number) ON DELETE CASCADE,
        UNIQUE KEY unique_assessment (year_id, employee_epf, skill_id)
    )";
    $db->query($sql);
    echo "✓ Created skill_matrix_assessments table<br>";

    // Insert default skill categories
    $sql = "INSERT IGNORE INTO skill_matrix_categories (id, name, display_order) VALUES
        (1, 'Technical Skills', 1),
        (2, 'Leadership & Management', 2),
        (3, 'Communication & Interpersonal Skills', 3),
        (4, 'Adaptability & Learning Agility', 4),
        (5, 'Innovation & Creativity', 5),
        (6, 'Problem-Solving & Critical Thinking', 6)";
    $db->query($sql);
    echo "✓ Inserted default skill categories<br>";

    // Create indexes (MySQL syntax)
    $indexes = [
        "CREATE INDEX idx_skill_matrix_assessments_employee ON skill_matrix_assessments(employee_epf)",
        "CREATE INDEX idx_skill_matrix_assessments_manager ON skill_matrix_assessments(manager_epf)",
        "CREATE INDEX idx_skill_matrix_assessments_year ON skill_matrix_assessments(year_id)",
        "CREATE INDEX idx_skill_matrix_skills_year_category ON skill_matrix_skills(year_id, category_id)"
    ];

    foreach ($indexes as $index) {
        try {
            $db->query($index);
        } catch (Exception $e) {
            // Index might already exist, ignore duplicate key errors
            if (strpos($e->getMessage(), 'Duplicate key name') === false) {
                echo "Index warning: " . $e->getMessage() . "<br>";
            }
        }
    }
    echo "✓ Created indexes<br>";

    echo "<br><strong style='color: green;'>✅ All skill matrix tables created successfully!</strong><br>";
    echo "<br><a href='index.php' class='btn btn-primary'>Go to Skill Matrix Dashboard</a>";
    echo " <a href='setup_form.php' class='btn btn-warning'>Setup Skills</a>";

} catch (Exception $e) {
    echo "<strong style='color: red;'>❌ Error: " . $e->getMessage() . "</strong><br>";
    echo "Please check your database connection and try again.";
}
?>
<style>
body { font-family: Arial, sans-serif; padding: 20px; }
.btn { display: inline-block; padding: 8px 16px; margin: 5px; text-decoration: none; border-radius: 4px; }
.btn-primary { background-color: #007bff; color: white; }
.btn-warning { background-color: #ffc107; color: black; }
</style>