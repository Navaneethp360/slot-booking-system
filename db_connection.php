<?php
$host = 'localhost';
$dbname = 'ashtiric_precedence_test';
$username = 'ashtiric_precedence';
$password = 'Precedence@2024';

try {
    // Establish a PDO connection to MySQL
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Set error mode to exception for better error handling
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connection successful!";
} catch (PDOException $e) {
    // If the connection fails, display an error message
    die("Database connection failed: " . $e->getMessage());
}
?>
