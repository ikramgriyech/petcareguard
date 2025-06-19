<?php
$host = 'localhost';
$dbname = 'petcaredb';
$username = 'root';
$password = '';

try {
    // Connect to the database using PDO
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);

    // Enable error reporting mode
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    // Error message
    die(" Connection failed: " . $e->getMessage());
}
?>



