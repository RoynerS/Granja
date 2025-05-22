<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}
include '../db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];
    $descripcion = $_POST['descripcion'];
    $fecha = $_POST['fecha'];
    $hora = $_POST['hora'];
    $usuario_id = $_POST['usuario_id'];
    $animal_id = isset($_POST['animal_id']) && $_POST['animal_id'] !== '' ? $_POST['animal_id'] : null;
    $completado = $_POST['completado'];

    try {
        $stmt = $conn->prepare("UPDATE tareas SET descripcion = :descripcion, fecha = :fecha, hora = :hora, usuario_id = :usuario_id, animal_id = :animal_id, completado = :completado WHERE id = :id");
        $stmt->bindParam(':descripcion', $descripcion);
        $stmt->bindParam(':fecha', $fecha);
        $stmt->bindParam(':hora', $hora);
        $stmt->bindParam(':usuario_id', $usuario_id);
        $stmt->bindParam(':animal_id', $animal_id);
        $stmt->bindParam(':completado', $completado);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        $_SESSION['mensaje'] = "Tarea actualizada correctamente.";
    } catch (PDOException $e) {
        $_SESSION['mensaje'] = "Error al actualizar la tarea: " . $e->getMessage();
    }
}
header("Location: tareas_veterinario.php");
exit();
?>