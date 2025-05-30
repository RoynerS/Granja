<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}
include '../db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $descripcion = $_POST['descripcion'];
    $fecha = $_POST['fecha'];
    $hora = $_POST['hora'];
    $usuario_id = $_POST['usuario_id']; // ID of the assigned user/worker
    $animal_id = isset($_POST['animal_id']) && $_POST['animal_id'] !== '' ? $_POST['animal_id'] : null;
    $completado = $_POST['completado'];

    try {
        $stmt = $conn->prepare("INSERT INTO tareas (descripcion, fecha, hora, usuario_id, animal_id, completado) VALUES (:descripcion, :fecha, :hora, :usuario_id, :animal_id, :completado)");
        $stmt->bindParam(':descripcion', $descripcion);
        $stmt->bindParam(':fecha', $fecha);
        $stmt->bindParam(':hora', $hora);
        $stmt->bindParam(':usuario_id', $usuario_id);
        $stmt->bindParam(':animal_id', $animal_id);
        $stmt->bindParam(':completado', $completado);
        $stmt->execute();

        $_SESSION['mensaje'] = "Tarea agregada correctamente.";
    } catch (PDOException $e) {
        $_SESSION['mensaje'] = "Error al agregar la tarea: " . $e->getMessage();
    }
}
header("Location: tareas_veterinario.php"); // Consider renaming this redirection for clarity
exit();
?>