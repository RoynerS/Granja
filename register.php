<?php
include 'db.php';

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = $_POST['nombre'];
    $correo = $_POST['correo'];
    $contrasena = $_POST['contrasena'];
    $rol = $_POST['rol'];

    // Verificar si el correo ya está registrado
    $verificar = $conn->prepare("SELECT id FROM usuarios WHERE correo = :correo");
    $verificar->bindParam(':correo', $correo);
    $verificar->execute();

    if ($verificar->rowCount() > 0) {
        $mensaje = "Este correo ya está registrado.";
    } else {
        // Encriptar contraseña
        $contrasena_hash = password_hash($contrasena, PASSWORD_DEFAULT);

        $sql = "INSERT INTO usuarios (nombre, correo, contrasena, rol) VALUES (:nombre, :correo, :contrasena, :rol)";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':correo', $correo);
        $stmt->bindParam(':contrasena', $contrasena_hash);
        $stmt->bindParam(':rol', $rol);

        if ($stmt->execute()) {
            $mensaje = "Usuario registrado correctamente. <a href='login.php'>Iniciar sesión</a>";
        } else {
            $mensaje = "Error al registrar usuario.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro - Granja</title>
</head>
<body>
    <h2>Registro de Usuario</h2>
    <form method="POST">
        <label>Nombre:</label><br>
        <input type="text" name="nombre" required><br>
        <label>Correo:</label><br>
        <input type="email" name="correo" required><br>
        <label>Contraseña:</label><br>
        <input type="password" name="contrasena" required><br>
        <label>Rol:</label><br>
        <select name="rol">
            <option value="trabajador">Trabajador</option>
            <option value="veterinario">Veterinario</option>
            <option value="administrador">Administrador</option>
        </select><br><br>
        <input type="submit" value="Registrar">
    </form>
    <p style="color:green;"><?php echo $mensaje; ?></p>
</body>
</html>
