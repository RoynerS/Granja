<?php
$host = "localhost";
$dbname = "granja_db";
$username = "root";  // Cambia si usas otro usuario
$password = "";      // Cambia si tienes contraseña


try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // echo "Conexión exitosa";
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>
