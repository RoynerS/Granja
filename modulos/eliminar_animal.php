<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

include '../db.php'; // Asegúrate que la ruta a tu archivo db.php sea correcta

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    try {
        // Iniciar una transacción para asegurar la atomicidad de las operaciones
        $conn->beginTransaction();

        // 1. Eliminar las tareas asociadas a este animal primero
        // Esto evita la violación de la restricción de clave foránea
        $stmt_tareas = $conn->prepare("DELETE FROM tareas WHERE animal_id = :animal_id");
        $stmt_tareas->execute(['animal_id' => $id]);

        // 2. Ahora, eliminar el animal de la base de datos
        $stmt_animal = $conn->prepare("DELETE FROM animales WHERE id = :id");
        $stmt_animal->execute(['id' => $id]);

        // Confirmar la transacción
        $conn->commit();

        $_SESSION['mensaje'] = "Animal y sus tareas asociadas eliminados correctamente.";

    } catch (PDOException $e) {
        // Revertir la transacción en caso de error
        $conn->rollBack();
        $_SESSION['mensaje'] = "Error al eliminar el animal: " . $e->getMessage();
        error_log("Error al eliminar animal (eliminar_animal.php): " . $e->getMessage()); // Para logs del servidor
    }
} else {
    $_SESSION['mensaje'] = "ID de animal no proporcionado para eliminar.";
}

header("Location: animales.php");
exit();
?>
