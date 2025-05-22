<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

include '../db.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM inventario WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'];
    $cantidad = $_POST['cantidad'];
    $precio = $_POST['precio'];
    $fecha_ingreso = $_POST['fecha_ingreso'];
    $tipo = $_POST['tipo'];

    // Actualizar el producto en la base de datos
    $stmt = $conn->prepare("UPDATE inventario SET nombre = :nombre, descripcion = :descripcion, cantidad = :cantidad, precio = :precio, fecha_ingreso = :fecha_ingreso, tipo = :tipo WHERE id = :id");
    $stmt->execute([
        'nombre' => $nombre,
        'descripcion' => $descripcion,
        'cantidad' => $cantidad,
        'precio' => $precio,
        'fecha_ingreso' => $fecha_ingreso,
        'tipo' => $tipo,
        'id' => $id
    ]);

    $_SESSION['mensaje'] = "Producto actualizado correctamente";
    header("Location: inventario.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Producto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Editar Producto</h2>
        <form action="" method="POST">
            <div class="mb-3">
                <label class="form-label">Nombre:</label>
                <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($producto['nombre']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Descripción:</label>
                <textarea name="descripcion" class="form-control"><?= htmlspecialchars($producto['descripcion']) ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Categoría:</label>
                <select name="tipo" class="form-select" required>
                    <option value="alimento" <?= $producto['tipo'] == 'alimento' ? 'selected' : '' ?>>Alimento</option>
                    <option value="medicamento" <?= $producto['tipo'] == 'medicamento' ? 'selected' : '' ?>>Medicamento</option>
                    <option value="herramienta" <?= $producto['tipo'] == 'herramienta' ? 'selected' : '' ?>>Herramienta</option>
                    <option value="semilla" <?= $producto['tipo'] == 'semilla' ? 'selected' : '' ?>>Semilla</option>
                    <option value="fertilizante" <?= $producto['tipo'] == 'fertilizante' ? 'selected' : '' ?>>Fertilizante</option>
                    <option value="equipo" <?= $producto['tipo'] == 'equipo' ? 'selected' : '' ?>>Equipo</option>
                    <option value="otro" <?= $producto['tipo'] == 'otro' ? 'selected' : '' ?>>Otro</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Cantidad:</label>
                <input type="number" name="cantidad" class="form-control" value="<?= htmlspecialchars($producto['cantidad']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Precio:</label>
                <input type="number" name="precio" class="form-control" step="0.01" value="<?= htmlspecialchars($producto['precio']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Fecha de Ingreso:</label>
                <input type="date" name="fecha_ingreso" class="form-control" value="<?= htmlspecialchars($producto['fecha_ingreso']) ?>" required>
            </div>
            <button type="submit" class="btn btn-primary">Actualizar</button>
        </form>
    </div>
</body>
</html>