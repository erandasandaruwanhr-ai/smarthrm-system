<?php
  echo "PHP is working!<br>";
  echo "Current time: " . date('Y-m-d H:i:s') . "<br>";
//
  // Test database connection
  try {
      $host = 'maglev.proxy.rlwy.net';
      $port = '13358';
      $database = 'railway';
      $username = 'root';
      $password = 'lJemNJJMLbMGRUYZLvPLxgyzsxChdGHG';

      $pdo = new
  PDO("mysql:host=$host;port=$port;dbname=$database",
  $username, $password);
      echo "Database connection: SUCCESS!";
  } catch (Exception $e) {
      echo "Database connection error: " . $e->getMessage();
  }
  ?>
