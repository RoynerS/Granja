<?php
session_start();
header('Content-Type: application/json'); // Asegurarse de que la respuesta sea JSON

$response = ['success' => false, 'message' => ''];

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    $response['message'] = 'Error de autenticación: Usuario no autenticado. Por favor, inicia sesión de nuevo.';
    error_log("Intento de marcar tarea como completada sin autenticación.");
    echo json_encode($response);
    exit();
}

include '../db.php'; // Asegúrate que la ruta a tu archivo db.php sea correcta

// Decodificar el cuerpo de la solicitud JSON
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id'])) {
    $response['message'] = 'Error de solicitud: ID de tarea no proporcionado en el cuerpo de la solicitud.';
    error_log("ID de tarea no proporcionado en marcar_tarea_completada.php. Datos recibidos: " . print_r($data, true));
    echo json_encode($response);
    exit();
}

$tarea_id = $data['id'];
$user_id = $_SESSION['usuario_id'];

// Obtener el rol del usuario logueado
$user_rol = '';
try {
    $stmt_user_rol = $conn->prepare("SELECT rol FROM usuarios WHERE id = :user_id");
    $stmt_user_rol->bindParam(':user_id', $user_id);
    $stmt_user_rol->execute();
    $user_data = $stmt_user_rol->fetch(PDO::FETCH_ASSOC);
    if ($user_data) {
        $user_rol = $user_data['rol'];
    } else {
        $response['message'] = "Error de seguridad: No se pudo encontrar el rol del usuario en la base de datos.";
        error_log("No se encontró el rol para el usuario ID: " . $user_id);
        echo json_encode($response);
        exit();
    }
} catch (PDOException $e) {
    error_log("Error PDO al obtener el rol del usuario en marcar_tarea_completada.php: " . $e->getMessage());
    $response['message'] = "Error de base de datos al verificar el rol del usuario: " . $e->getMessage();
    echo json_encode($response);
    exit();
}

// Obtener la tarea para verificar la asignación y el estado actual
$tarea_info = null;
try {
    $stmt_tarea = $conn->prepare("SELECT usuario_id, completado FROM tareas WHERE id = :tarea_id");
    $stmt_tarea->bindParam(':tarea_id', $tarea_id);
    $stmt_tarea->execute();
    $tarea_info = $stmt_tarea->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error PDO al obtener información de la tarea en marcar_tarea_completada.php: " . $e->getMessage());
    $response['message'] = "Error de base de datos al obtener la información de la tarea: " . $e->getMessage();
    echo json_encode($response);
    exit();
}

if (!$tarea_info) {
    $response['message'] = 'Error: Tarea con el ID ' . $tarea_id . ' no encontrada en la base de datos.';
    error_log("Tarea no encontrada con ID: " . $tarea_id);
    echo json_encode($response);
    exit();
}

// Validar permisos y estado:
// 1. Administrador puede marcar cualquier tarea.
// 2. Cualquier otro rol asignado a la tarea puede marcar sus propias tareas Y si la tarea está pendiente.
// MODIFICACIÓN CRÍTICA AQUÍ: Ajusta los roles que pueden completar sus propias tareas.
if ($user_rol === 'administrador' || 
    (($user_rol === 'veterinario' || $user_rol === 'trabajador' || $user_rol === 'enfermero') && $tarea_info['usuario_id'] == $user_id && $tarea_info['completado'] == 0)) {
    // Si la tarea ya está completada, no hacer nada y devolver éxito
    if ($tarea_info['completado'] == 1) {
        $response['success'] = true;
        $response['message'] = 'La tarea ya estaba marcada como completada.';
        echo json_encode($response);
        exit();
    }

    try {
        $fecha_actual = date('Y-m-d');
        $hora_actual = date('H:i:s');

        $stmt = $conn->prepare("UPDATE tareas SET completado = 1, fecha = :fecha_actual, hora = :hora_actual WHERE id = :id");
        $stmt->bindParam(':fecha_actual', $fecha_actual);
        $stmt->bindParam(':hora_actual', $hora_actual);
        $stmt->bindParam(':id', $tarea_id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $response['success'] = true;
            $response['message'] = 'Tarea marcada como completada exitosamente.';
        } else {
            $response['message'] = 'Advertencia: No se realizó la actualización. Posiblemente la tarea ya estaba completada o el ID no es válido.';
            error_log("No se afectaron filas al actualizar tarea ID: " . $tarea_id);
        }
    } catch (PDOException $e) {
        error_log("Error de base de datos al marcar tarea como completada: " . $e->getMessage());
        $response['message'] = 'Error de base de datos al actualizar la tarea: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Error de permisos: No tienes autorización para marcar esta tarea como completada. Solo puedes completar tus tareas pendientes o eres un rol no autorizado.';
    error_log("Intento de marcar tarea ID " . $tarea_id . " por usuario ID " . $user_id . " con rol " . $user_rol . " sin permisos adecuados.");
}

echo json_encode($response);
exit();
?>