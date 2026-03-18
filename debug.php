<?php
  echo "1. PHP is working!<br>";
  echo "2. Current time: " . date('Y-m-d H:i:s') . "<br>";

  // Test if we can load config
  try {
      echo "3. Testing config load...<br>";
      require_once 'config/config.php';
      echo "4. Config loaded successfully!<br>";
  } catch (Exception $e) {
      echo "4. Config error: " . $e->getMessage() . "<br>";
  }

  // Test database connection directly
  try {
      echo "5. Testing direct database connection...<br>";
      $host = 'maglev.proxy.rlwy.net';
      $port = '13358';
      $database = 'railway';
      $username = 'root';
      $password = 'lJemNJJMLbMGRUYZLvPLxgyzsxChdGHG';

      $pdo = new
  PDO("mysql:host=$host;port=$port;dbname=$database",
  $username, $password);
      echo "6. Direct database connection: SUCCESS!<br>";
  } catch (Exception $e) {
      echo "6. Direct database error: " . $e->getMessage() .
  "<br>";
  }

  // Test Database class
  try {
      echo "7. Testing Database class...<br>";
      $db = new Database();
      echo "8. Database class: SUCCESS!<br>";
  } catch (Exception $e) {
      echo "8. Database class error: " . $e->getMessage() .
  "<br>";
  }
  ?>
