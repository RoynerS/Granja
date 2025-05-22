<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

include '../db.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Eliminar el producto de la base de datos
    $stmt = $conn->prepare("DELETE FROM inventario WHERE id = :id");
    $stmt->execute(['id' => $id]);

    $_SESSION['mensaje'] = "Producto eliminado correctamente";
}

header("Location: inventario.php");
exit();
?>
