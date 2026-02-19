<?php
function db(){
  static $pdo;
  if($pdo) return $pdo;

  // Dothome / XAMPP Environment
  $host = 'localhost';
  $db   = 'daseul0406';      
  $user = 'daseul0406';         
  $pass = 'a583400S@';            
  $dsn  = "mysql:host=$host;dbname=$db;charset=utf8mb4";

  try {
    $pdo = new PDO($dsn, $user, $pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false, 
    ]);
  } catch(PDOException $e){
    // If connection fails, try root/root (Local XAMPP fallback) just in case
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=shim_on;charset=utf8mb4", "root", "", [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    } catch(PDOException $e2){
        echo "DB Connection Failed: " . $e->getMessage();
        exit;
    }
  }

  // Check schema and auto-migrate if needed
  try {
      // 1. Check if users table exists
      $pdo->query("SELECT 1 FROM users LIMIT 1");
      
      // 2. Check for missing columns (Auto-Migration)
      $stmt = $pdo->query("SHOW COLUMNS FROM users");
      $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
      
      // If password_hash is missing, add it (or fix old password column)
      if (!in_array('password_hash', $cols)) {
          if (in_array('password', $cols)) {
              // Rename old 'password' column to 'password_hash'
              $pdo->exec("ALTER TABLE users CHANGE password password_hash VARCHAR(255) NOT NULL");
          } else {
              // Add new column
              $pdo->exec("ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) NOT NULL DEFAULT ''");
          }
      }
      
      // Check other potentially missing columns from older versions
      if (!in_array('nickname', $cols)) $pdo->exec("ALTER TABLE users ADD COLUMN nickname VARCHAR(80) NOT NULL DEFAULT 'User'");
      if (!in_array('theme', $cols))    $pdo->exec("ALTER TABLE users ADD COLUMN theme ENUM('light','dark') DEFAULT 'light'");
      if (!in_array('sound_on', $cols)) $pdo->exec("ALTER TABLE users ADD COLUMN sound_on TINYINT(1) DEFAULT 1");
      if (!in_array('role', $cols))     $pdo->exec("ALTER TABLE users ADD COLUMN role ENUM('user','admin') DEFAULT 'user'");
      
      // 3. Check for feeds table (for shared memos)
      $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
      if (!in_array('feeds', $tables)) {
           // Missing feeds table, trigger exception to run schema
           throw new Exception("Feeds table missing");
      }
      
  } catch (Exception $e) {
      // Table likely missing, run schema.sql
      $schemaPath = __DIR__ . '/schema.sql';
      if (file_exists($schemaPath)) {
          $sql = file_get_contents($schemaPath);
          $sql = preg_replace('/--.*$/m', '', $sql);
          $stmts = explode(';', $sql);
          foreach ($stmts as $stmt) {
              $stmt = trim($stmt);
              if ($stmt) {
                  try {
                      $pdo->exec($stmt);
                  } catch(Exception $ex) {
                      // Ignore errors if table already exists
                  }
              }
          }
      }
  }

  return $pdo;
}
