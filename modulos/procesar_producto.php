<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

include '../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'];
    $cantidad = $_POST['cantidad'];
    $precio = $_POST['precio'];
    $fecha_ingreso = $_POST['fecha_ingreso'];
    $tipo = $_POST['tipo']; // Nuevo campo para la categoría

    // Insertar el nuevo producto en la base de datos
    $stmt = $conn->prepare("INSERT INTO inventario (nombre, descripcion, cantidad, precio, fecha_ingreso, tipo) VALUES (:nombre, :descripcion, :cantidad, :precio, :fecha_ingreso, :tipo)");
    $stmt->execute([
        'nombre' => $nombre,
        'descripcion' => $descripcion,
        'cantidad' => $cantidad,
        'precio' => $precio,
        'fecha_ingreso' => $fecha_ingreso,
        'tipo' => $tipo
    ]);

    $_SESSION['mensaje'] = "Producto agregado correctamente";
}

header("Location: inventario.php");
exit();
?>