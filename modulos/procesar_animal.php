<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

include '../db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validar y obtener los datos del formulario
    $codigo_animal = $_POST['codigo_animal']; // Nuevo campo
    $nombre = $_POST['nombre'];
    $especie = $_POST['especie'];
    $raza = $_POST['raza'];
    $sexo = $_POST['sexo'];
    $edad = $_POST['edad'];
    $peso = $_POST['peso'];
    $estado_salud = $_POST['estado_salud'];
    $fecha_ingreso = $_POST['fecha_ingreso'];

    // Insertar en la base de datos
    $sql = "INSERT INTO animales (codigo_animal, nombre, especie, raza, sexo, edad, peso, estado_salud, fecha_ingreso) 
            VALUES (:codigo_animal, :nombre, :especie, :raza, :sexo, :edad, :peso, :estado_salud, :fecha_ingreso)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':codigo_animal', $codigo_animal);
    $stmt->bindParam(':nombre', $nombre);
    $stmt->bindParam(':especie', $especie);
    $stmt->bindParam(':raza', $raza);
    $stmt->bindParam(':sexo', $sexo);
    $stmt->bindParam(':edad', $edad);
    $stmt->bindParam(':peso', $peso);
    $stmt->bindParam(':estado_salud', $estado_salud);
    $stmt->bindParam(':fecha_ingreso', $fecha_ingreso);

    if ($stmt->execute()) {
        // Si se inserta correctamente, redirigir a la página de animales
        header("Location: animales.php");
        exit();
    } else {
        // Si hay un error en la inserción
        echo "Hubo un error al agregar el animal.";
    }
}
?>
