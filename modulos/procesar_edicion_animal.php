<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

include '../db.php'; // Asegúrate que la ruta a tu archivo db.php sea correcta

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id_animal'])) {
    $id = $_POST['id_animal'];
    $codigo_animal = $_POST['codigo_animal'];
    $nombre = $_POST['nombre'];
    $especie = $_POST['especie'];
    $raza = $_POST['raza'];
    $sexo = $_POST['sexo'];
    $edad = $_POST['edad'];
    $peso = $_POST['peso'];
    $estado_salud = $_POST['estado_salud'];
    $fecha_ingreso = $_POST['fecha_ingreso'];

    try {
        $stmt = $conn->prepare("UPDATE animales SET 
                                codigo_animal = :codigo_animal, 
                                nombre = :nombre, 
                                especie = :especie, 
                                raza = :raza, 
                                sexo = :sexo, 
                                edad = :edad, 
                                peso = :peso, 
                                estado_salud = :estado_salud, 
                                fecha_ingreso = :fecha_ingreso 
                                WHERE id = :id");
        $stmt->bindParam(':codigo_animal', $codigo_animal);
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':especie', $especie);
        $stmt->bindParam(':raza', $raza);
        $stmt->bindParam(':sexo', $sexo);
        $stmt->bindParam(':edad', $edad);
        $stmt->bindParam(':peso', $peso);
        $stmt->bindParam(':estado_salud', $estado_salud);
        $stmt->bindParam(':fecha_ingreso', $fecha_ingreso);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        $_SESSION['mensaje'] = "Animal actualizado correctamente.";
    } catch (PDOException $e) {
        $_SESSION['mensaje'] = "Error al actualizar el animal: " . $e->getMessage();
        error_log("Error al actualizar animal: " . $e->getMessage());
    }
} else {
    $_SESSION['mensaje'] = "Solicitud inválida para actualizar animal.";
}

header("Location: animales.php");
exit();
?>
