<?php
session_start();
include 'db.php'; // Asegúrate que la ruta a tu archivo db.php sea correcta

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['agregar_usuario'])) {
    $nombre = $_POST['nombre'];
    $correo = $_POST['correo'];
    $contrasena = $_POST['contrasena'];
    $rol = $_POST['rol'];

    // Validaciones básicas
    if (empty($nombre) || empty($correo) || empty($contrasena) || empty($rol)) {
        $_SESSION['mensaje'] = "Todos los campos son obligatorios.";
        header("Location: dashboard.php");
        exit();
    }

    // Verificar si el correo ya está registrado
    try {
        $verificar = $conn->prepare("SELECT id FROM usuarios WHERE correo = :correo");
        $verificar->bindParam(':correo', $correo);
        $verificar->execute();

        if ($verificar->rowCount() > 0) {
            $_SESSION['mensaje'] = "Este correo ya está registrado.";
            header("Location: dashboard.php");
            exit();
        }
    } catch (PDOException $e) {
        error_log("Error al verificar correo en procesar_usuario.php: " . $e->getMessage());
        $_SESSION['mensaje'] = "Error al verificar el correo. Inténtalo de nuevo.";
        header("Location: dashboard.php");
        exit();
    }

    // Encriptar contraseña
    $contrasena_hash = password_hash($contrasena, PASSWORD_DEFAULT);

    // Insertar nuevo usuario
    try {
        $sql = "INSERT INTO usuarios (nombre, correo, contrasena, rol) VALUES (:nombre, :correo, :contrasena, :rol)";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':correo', $correo);
        $stmt->bindParam(':contrasena', $contrasena_hash);
        $stmt->bindParam(':rol', $rol);

        if ($stmt->execute()) {
            $_SESSION['mensaje'] = "Usuario '{$nombre}' registrado correctamente como {$rol}.";
            header("Location: dashboard.php");
            exit();
        } else {
            $_SESSION['mensaje'] = "Error al registrar el usuario.";
            header("Location: dashboard.php");
            exit();
        }
    } catch (PDOException $e) {
        error_log("Error al insertar usuario en procesar_usuario.php: " . $e->getMessage());
        $_SESSION['mensaje'] = "Error de base de datos al registrar el usuario.";
        header("Location: dashboard.php");
        exit();
    }
} else {
    $_SESSION['mensaje'] = "Acceso no autorizado.";
    header("Location: dashboard.php");
    exit();
}
?>
