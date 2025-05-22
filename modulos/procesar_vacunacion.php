<?php
session_start();
header('Content-Type: application/json'); // Asegurarse de que la respuesta sea JSON

$response = ['success' => false, 'message' => ''];

// 1. Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    $response['message'] = 'Error de autenticación: Usuario no autenticado. Por favor, inicia sesión de nuevo.';
    error_log("Intento de vacunar animal sin autenticación. IP: " . $_SERVER['REMOTE_ADDR']);
    echo json_encode($response);
    exit();
}

include '../db.php'; // Asegúrate que la ruta a tu archivo db.php sea correcta

// Decodificar el cuerpo de la solicitud JSON
$input_data = file_get_contents('php://input');
$data = json_decode($input_data, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    $response['message'] = 'Error de JSON: Datos recibidos no válidos. Error: ' . json_last_error_msg();
    error_log("Error de JSON en procesar_vacunacion.php. Input: " . $input_data);
    echo json_encode($response);
    exit();
}

if (!isset($data['animal_id'])) {
    $response['message'] = 'Error de solicitud: ID de animal no proporcionado.';
    error_log("ID de animal no proporcionado en procesar_vacunacion.php. Datos recibidos: " . print_r($data, true));
    echo json_encode($response);
    exit();
}

$animal_id = $data['animal_id'];
$medicamentos_usados = isset($data['medicamentos']) ? $data['medicamentos'] : [];
$user_id = $_SESSION['usuario_id'];

// 2. Obtener el rol del usuario logueado
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
        error_log("No se encontró el rol para el usuario ID: " . $user_id . " en procesar_vacunacion.php");
        echo json_encode($response);
        exit();
    }
} catch (PDOException $e) {
    error_log("Error PDO al obtener el rol del usuario en procesar_vacunacion.php: " . $e->getMessage());
    $response['message'] = "Error de base de datos al verificar el rol del usuario: " . $e->getMessage();
    echo json_encode($response);
    exit();
}

// 3. Validar que el usuario sea un veterinario
if ($user_rol !== 'veterinario') {
    $response['message'] = 'Permiso denegado: Solo los veterinarios pueden vacunar animales.';
    error_log("Intento de vacunar animal por usuario no veterinario (ID: " . $user_id . ", Rol: " . $user_rol . ").");
    echo json_encode($response);
    exit();
}

// Iniciar una transacción para asegurar la atomicidad de las operaciones
$conn->beginTransaction();

try {
    // 4. Obtener el estado actual del animal y verificar si ya está vacunado
    $stmt_animal = $conn->prepare("SELECT estado_salud FROM animales WHERE id = :animal_id FOR UPDATE"); // FOR UPDATE para bloqueo de fila
    $stmt_animal->bindParam(':animal_id', $animal_id);
    $stmt_animal->execute();
    $animal_info = $stmt_animal->fetch(PDO::FETCH_ASSOC);

    if (!$animal_info) {
        throw new Exception('Error: Animal con el ID ' . $animal_id . ' no encontrado en la base de datos.');
    }

    if (strtolower($animal_info['estado_salud']) === 'vacunado') {
        // Si el animal ya está vacunado, se considera un éxito pero se informa
        $response['success'] = true;
        $response['message'] = 'El animal ya estaba vacunado. No se realizaron cambios.';
        $conn->commit(); // Confirmar la transacción vacía
        echo json_encode($response);
        exit();
    }

    // 5. Procesar los medicamentos utilizados y descontar del inventario
    foreach ($medicamentos_usados as $item) {
        $med_id = $item['id'];
        $qty_used = (int)$item['cantidad']; // Asegurarse de que sea un entero

        if ($qty_used <= 0) {
            error_log("Medicamento ID: " . $med_id . " con cantidad 0 o negativa ignorado.");
            continue; // Si la cantidad es 0 o menos, no hacer nada
        }

        // Obtener la cantidad actual del medicamento en el inventario
        $stmt_check_qty = $conn->prepare("SELECT cantidad, nombre FROM inventario WHERE id = :id FOR UPDATE");
        $stmt_check_qty->bindParam(':id', $med_id);
        $stmt_check_qty->execute();
        $inv_item = $stmt_check_qty->fetch(PDO::FETCH_ASSOC);

        if (!$inv_item) {
            throw new Exception('Medicamento (ID: ' . $med_id . ') no encontrado en el inventario. Por favor, verifica el ID.');
        }
        if ($inv_item['cantidad'] < $qty_used) {
            throw new Exception('Cantidad insuficiente de ' . htmlspecialchars($inv_item['nombre']) . ' en el inventario. Disponible: ' . $inv_item['cantidad'] . ', Requerido: ' . $qty_used);
        }

        // Descontar la cantidad
        $new_qty = $inv_item['cantidad'] - $qty_used;
        $stmt_update_qty = $conn->prepare("UPDATE inventario SET cantidad = :new_qty WHERE id = :id");
        $stmt_update_qty->bindParam(':new_qty', $new_qty);
        $stmt_update_qty->bindParam(':id', $med_id);
        $stmt_update_qty->execute();

        if ($stmt_update_qty->rowCount() === 0) {
            throw new Exception('Error al descontar el medicamento ' . htmlspecialchars($inv_item['nombre']) . ' del inventario. No se afectaron filas.');
        }
    }

    // 6. Actualizar el estado de salud del animal a 'vacunado'
    $fecha_vacunacion = date('Y-m-d H:i:s'); // Fecha y hora actual de la vacunación

    $stmt_update_animal = $conn->prepare("UPDATE animales SET estado_salud = 'vacunado', fecha_ultima_vacuna = :fecha_vacunacion WHERE id = :id");
    $stmt_update_animal->bindParam(':fecha_vacunacion', $fecha_vacunacion);
    $stmt_update_animal->bindParam(':id', $animal_id);
    $stmt_update_animal->execute();

    if ($stmt_update_animal->rowCount() === 0) {
        throw new Exception('No se realizó la actualización del estado de salud del animal. Posiblemente el animal ya estaba vacunado o el ID es incorrecto.');
    }

    // Si todo salió bien, confirmar la transacción
    $conn->commit();
    $response['success'] = true;
    $response['message'] = 'Animal vacunado exitosamente y medicamentos descontados del inventario.';

} catch (Exception $e) {
    // En caso de cualquier error, revertir la transacción
    $conn->rollBack();
    error_log("Excepción en procesar_vacunacion.php (animal ID: " . $animal_id . "): " . $e->getMessage());
    $response['message'] = 'Error en la vacunación: ' . $e->getMessage();
} catch (PDOException $e) {
    $conn->rollBack();
    error_log("Error PDO en procesar_vacunacion.php (animal ID: " . $animal_id . "): " . $e->getMessage());
    $response['message'] = 'Error de base de datos durante la vacunación: ' . $e->getMessage();
}

echo json_encode($response);
exit();
?>
