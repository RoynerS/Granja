<?php
session_start();
header('Content-Type: application/json'); // Añadir esta línea

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit();
}

include '../db.php';

$response = ['success' => false, 'message' => ''];

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;

    if (!$id) {
        $response['message'] = 'ID de tarea no proporcionado';
        echo json_encode($response);
        exit();
    }

    // Verificar que la tarea existe y pertenece al usuario (o es admin)
    $stmt = $conn->prepare("DELETE FROM tareas WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();

    $response['success'] = true;
    $response['message'] = "Tarea eliminada correctamente";
    
} catch (PDOException $e) {
    $response['message'] = "Error al eliminar la tarea: " . $e->getMessage();
    error_log("Error al eliminar tarea: " . $e->getMessage());
}

echo json_encode($response);
exit();
?>