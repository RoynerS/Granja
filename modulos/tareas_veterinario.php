<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

include '../db.php'; // Asegúrate que la ruta a tu archivo db.php sea correcta

// Obtener el rol del usuario logueado
$user_rol = '';
try {
    $stmt_user_rol = $conn->prepare("SELECT rol FROM usuarios WHERE id = :user_id");
    $stmt_user_rol->bindParam(':user_id', $_SESSION['usuario_id']);
    $stmt_user_rol->execute();
    $user_data = $stmt_user_rol->fetch(PDO::FETCH_ASSOC);
    if ($user_data) {
        $user_rol = $user_data['rol'];
    }
} catch (PDOException $e) {
    error_log("Error al obtener el rol del usuario: " . $e->getMessage());
    $_SESSION['mensaje'] = "Error al verificar el rol del usuario.";
    header("Location: ../dashboard.php");
    exit();
}

// Obtener filtros y términos de búsqueda
$estado_filtro = isset($_GET['estado']) ? $_GET['estado'] : 'todos';
$buscar_termino = isset($_GET['buscar']) ? $_GET['buscar'] : null;
$fecha_filtro = isset($_GET['fecha']) ? $_GET['fecha'] : null;

// Construir y ejecutar la consulta para obtener todas las tareas relevantes
$sql = "SELECT t.*, u.nombre as nombre_usuario, a.nombre as nombre_animal 
        FROM tareas t
        LEFT JOIN usuarios u ON t.usuario_id = u.id
        LEFT JOIN animales a ON t.animal_id = a.id";
$conditions = [];
$params = [];

if ($user_rol === 'veterinario') {
    $conditions[] = "t.usuario_id = :session_user_id";
    $params[':session_user_id'] = $_SESSION['usuario_id'];
}

if ($buscar_termino) {
    $conditions[] = "(t.descripcion LIKE :buscar_descripcion OR u.nombre LIKE :buscar_usuario OR a.nombre LIKE :buscar_animal)";
    $params[':buscar_descripcion'] = "%$buscar_termino%";
    $params[':buscar_usuario'] = "%$buscar_termino%";
    $params[':buscar_animal'] = "%$buscar_termino%";
}

if ($fecha_filtro) {
    $conditions[] = "t.fecha = :fecha_filtro";
    $params[':fecha_filtro'] = $fecha_filtro;
}

if (count($conditions) > 0) {
    $sql .= " WHERE " . implode(' AND ', $conditions);
}
$sql .= " ORDER BY t.fecha DESC, t.hora ASC";

$all_tareas = [];
try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $all_tareas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error de base de datos en tareas_veterinario.php: " . $e->getMessage());
}

// Separar tareas en pendientes y completadas
$tareas_pendientes = [];
$tareas_completadas = [];
foreach ($all_tareas as $tarea) {
    if ($tarea['completado'] == 0) {
        $tareas_pendientes[] = $tarea;
    } else {
        $tareas_completadas[] = $tarea;
    }
}

// Manejar mensajes de sesión
$mensaje = isset($_SESSION['mensaje']) ? $_SESSION['mensaje'] : "";
unset($_SESSION['mensaje']);
?>

<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Tareas Veterinarias</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: { 50: '#f0fdf4', 100: '#dcfce7', 200: '#bbf7d0', 300: '#86efac', 400: '#4ade80', 500: '#22c55e', 600: '#16a34a', 700: '#15803d', 800: '#166534', 900: '#14532d' },
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                    },
                    boxShadow: {
                        'soft': '0 4px 20px -2px rgba(0, 0, 0, 0.08)',
                        'soft-lg': '0 10px 30px -3px rgba(0, 0, 0, 0.12)',
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        .status-badge { padding: 0.25rem 0.5rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 500; }
        .status-completado { background-color: #dcfce7; color: #166534; } /* Verde */
        .status-pendiente { background-color: #fef9c3; color: #a16207; } /* Amarillo */

        [x-cloak] { display: none !important; }
    </style>
</head>
<body x-data="appData()" class="h-full bg-gray-50 dark:bg-gray-900 font-sans text-gray-800 dark:text-gray-100 transition-colors duration-200">
    <div class="min-h-full">
        <header class="bg-white dark:bg-gray-800 shadow-sm sticky top-0 z-40">
            <div class="px-6 py-4 flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <a href="../dashboard.php" class="flex items-center text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400">
                        <i class="bi bi-arrow-left text-xl mr-2"></i>
                        <span class="font-medium">Volver al Panel</span>
                    </a>
                </div>
                
                <div class="flex items-center space-x-4">
                    <div class="hidden md:flex items-center space-x-4">
                        <div class="relative">
                            <button id="theme-toggle" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none" @click="toggleTheme()">
                                <i class="bi bi-sun-fill text-yellow-500 dark:hidden"></i>
                                <i class="bi bi-moon-fill text-blue-400 hidden dark:inline"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        
        <main class="p-6">
            <?php if ($mensaje): ?>
                <div class="mb-4 p-4 text-sm text-green-700 bg-green-100 rounded-lg dark:bg-green-200 dark:text-green-800" role="alert">
                    <?= htmlspecialchars($mensaje) ?>
                </div>
            <?php endif; ?>
            <template x-if="alpineMessage">
                <div class="mb-4 p-4 text-sm text-red-700 bg-red-100 rounded-lg dark:bg-red-200 dark:text-red-800" role="alert" x-text="alpineMessage"></div>
            </template>

            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold flex items-center">
                        <i class="fas fa-clipboard-list text-primary-600 mr-2"></i> Gestión de Tareas Veterinarias
                    </h1>
                    <p class="text-gray-500 dark:text-gray-400">Administra las tareas y citas para el cuidado de los animales</p>
                </div>
                
                <div class="mt-4 md:mt-0 flex space-x-3">
                    <?php if ($user_rol === 'administrador'): ?>
                    <button @click="openModalAgregar = true" class="flex items-center justify-center px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg transition-colors duration-200">
                        <i class="bi bi-plus-lg mr-2"></i>
                        Agregar Tarea
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 space-y-4 md:space-y-0 md:space-x-4">
                <form action="tareas_veterinario.php" method="GET" class="w-full md:w-auto flex-grow">
                    <div class="relative max-w-md">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="bi bi-search text-gray-400"></i>
                        </div>
                        <input type="text" name="buscar" class="block w-full pl-10 pr-12 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg focus:ring-primary-500 focus:border-primary-500" placeholder="Buscar por descripción, veterinario o animal" value="<?= htmlspecialchars($buscar_termino ?? '') ?>">
                        <div class="absolute inset-y-0 right-0 flex items-center">
                            <button type="submit" class="px-4 h-full bg-primary-600 text-white rounded-r-lg hover:bg-primary-700 focus:outline-none">
                                Buscar
                            </button>
                        </div>
                    </div>
                </form>

                <div class="flex space-x-2 overflow-x-auto pb-2 md:pb-0 flex-shrink-0">
                    <a href="tareas_veterinario.php?<?= $buscar_termino ? 'buscar=' . htmlspecialchars($buscar_termino) . '&' : '' ?><?= $fecha_filtro ? 'fecha=' . htmlspecialchars($fecha_filtro) . '&' : '' ?>estado=todos" class="px-3 py-1 rounded-full transition-colors duration-200 <?= ($estado_filtro === 'todos') ? 'bg-primary-600 text-white' : 'border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' ?>">Todos</a>
                    <a href="tareas_veterinario.php?<?= $buscar_termino ? 'buscar=' . htmlspecialchars($buscar_termino) . '&' : '' ?><?= $fecha_filtro ? 'fecha=' . htmlspecialchars($fecha_filtro) . '&' : '' ?>estado=pendiente" class="px-3 py-1 rounded-full transition-colors duration-200 <?= ($estado_filtro === 'pendiente') ? 'bg-yellow-600 text-white' : 'border border-yellow-500 text-yellow-700 hover:bg-yellow-50' ?>">Pendientes</a>
                    <a href="tareas_veterinario.php?<?= $buscar_termino ? 'buscar=' . htmlspecialchars($buscar_termino) . '&' : '' ?><?= $fecha_filtro ? 'fecha=' . htmlspecialchars($fecha_filtro) . '&' : '' ?>estado=completado" class="px-3 py-1 rounded-full transition-colors duration-200 <?= ($estado_filtro === 'completado') ? 'bg-green-600 text-white' : 'border border-green-500 text-green-700 hover:bg-green-50' ?>">Completadas</a>
                    
                    <input type="date" name="fecha" form="filterForm" class="px-3 py-1 rounded-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-gray-700 dark:text-gray-300 focus:ring-primary-500 focus:border-primary-500" value="<?= htmlspecialchars($fecha_filtro ?? '') ?>" onchange="this.form.submit()">
                </div>
            </div>

            <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-4">Tareas Pendientes</h2>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-soft overflow-hidden mb-8">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Descripción</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Fecha</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Hora</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Asignado a</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Animal</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Estado</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <?php if (empty($tareas_pendientes)): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                        No se encontraron tareas pendientes.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($tareas_pendientes as $tarea): ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-6 py-4 whitespace-normal text-sm font-medium text-gray-900 dark:text-white max-w-xs"><?= htmlspecialchars($tarea['descripcion']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm text-gray-900 dark:text-white"><?= htmlspecialchars($tarea['fecha']) ?></div></td>
                                        <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm text-gray-900 dark:text-white"><?= htmlspecialchars(substr($tarea['hora'], 0, 5)) ?></div></td>
                                        <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm text-gray-900 dark:text-white"><?= htmlspecialchars($tarea['nombre_usuario'] ?? 'N/A') ?></div></td>
                                        <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm text-gray-900 dark:text-white"><?= htmlspecialchars($tarea['nombre_animal'] ?? 'N/A') ?></div></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="status-badge status-pendiente">Pendiente</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-2">
                                                <?php if ($user_rol === 'veterinario' && $tarea['usuario_id'] == $_SESSION['usuario_id']): ?>
                                                <button type="button" @click="markAsCompleted(<?= $tarea['id'] ?>)" class="text-primary-600 dark:text-primary-400 hover:text-primary-900 dark:hover:text-primary-500 flex items-center">
                                                    <i class="bi bi-check-circle mr-1"></i> Completar
                                                </button>
                                                <?php endif; ?>
                                                <?php if ($user_rol === 'administrador'): ?>
                                                <button type="button" @click="openModalEditar = true; tareaParaEditar = JSON.parse('<?= htmlspecialchars(json_encode($tarea), ENT_QUOTES, 'UTF-8') ?>'); inicializarFormularioEdicion();" class="text-yellow-600 dark:text-yellow-400 hover:text-yellow-900 dark:hover:text-yellow-500">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <button type="button" @click="confirmDelete(<?= $tarea['id'] ?>)" class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-500">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-4">Tareas Completadas</h2>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-soft overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Descripción</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Fecha</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Hora</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Asignado a</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Animal</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Estado</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <?php if (empty($tareas_completadas)): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                        No se encontraron tareas completadas.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($tareas_completadas as $tarea): ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-6 py-4 whitespace-normal text-sm font-medium text-gray-900 dark:text-white max-w-xs"><?= htmlspecialchars($tarea['descripcion']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm text-gray-900 dark:text-white"><?= htmlspecialchars($tarea['fecha']) ?></div></td>
                                        <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm text-gray-900 dark:text-white"><?= htmlspecialchars(substr($tarea['hora'], 0, 5)) ?></div></td>
                                        <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm text-gray-900 dark:text-white"><?= htmlspecialchars($tarea['nombre_usuario'] ?? 'N/A') ?></div></td>
                                        <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm text-gray-900 dark:text-white"><?= htmlspecialchars($tarea['nombre_animal'] ?? 'N/A') ?></div></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="status-badge status-completado">Completada</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-2">
                                                <?php if ($user_rol === 'administrador' || ($user_rol === 'veterinario' && $tarea['completado'] == 1)): ?>
                                                <button type="button" @click="openModalEditar = true; tareaParaEditar = JSON.parse('<?= htmlspecialchars(json_encode($tarea), ENT_QUOTES, 'UTF-8') ?>'); inicializarFormularioEdicion();" class="text-yellow-600 dark:text-yellow-400 hover:text-yellow-900 dark:hover:text-yellow-500">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <?php endif; ?>
                                                <?php if ($user_rol === 'administrador'): ?>
                                                <button type="button" @click="confirmDelete(<?= $tarea['id'] ?>)" class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-500">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <div x-cloak x-show="openModalAgregar" x-transition class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-agregar-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen p-4"> 
            <div x-show="openModalAgregar" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" @click="openModalAgregar = false; resetNewTareaForm()"></div>
            
            <div x-show="openModalAgregar" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle w-full max-w-lg sm:max-w-2xl"> 
                <form action="procesar_tarea.php" method="POST">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-primary-100 dark:bg-primary-900 sm:mx-0 sm:h-10 sm:w-10">
                                <i class="fas fa-plus-circle text-primary-600 dark:text-primary-300"></i>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="modal-agregar-title">
                                    Agregar Nueva Tarea Veterinaria
                                </h3>
                                <div class="mt-4 space-y-4">
                                    <div>
                                        <label for="descripcion_agregar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Descripción:</label>
                                        <textarea name="descripcion" id="descripcion_agregar" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm h-24" required></textarea>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label for="fecha_agregar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Fecha:</label>
                                            <input type="date" name="fecha" id="fecha_agregar" x-model="newTarea.fecha" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" required>
                                        </div>
                                        <div>
                                            <label for="hora_agregar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Hora:</label>
                                            <input type="time" name="hora" id="hora_agregar" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" required>
                                        </div>
                                    </div>
                                    <div>
                                        <label for="usuario_id_agregar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Asignar a Veterinario:</label>
                                        <select name="usuario_id" id="usuario_id_agregar" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" required>
                                            <option value="">Seleccionar Veterinario</option>
                                            <?php foreach ($veterinarios_list as $vet): ?>
                                                <option value="<?= htmlspecialchars($vet['id']) ?>"><?= htmlspecialchars($vet['nombre']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="animal_id_agregar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Animal Relacionado (Opcional):</label>
                                        <select name="animal_id" id="animal_id_agregar" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                                            <option value="">Ninguno</option>
                                            <?php foreach ($animales_list as $animal): ?>
                                                <option value="<?= htmlspecialchars($animal['id']) ?>"><?= htmlspecialchars($animal['nombre']) ?> (<?= htmlspecialchars($animal['codigo_animal']) ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="completado_agregar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Estado:</label>
                                        <select name="completado" id="completado_agregar" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                                            <option value="0">Pendiente</option>
                                            <option value="1">Completada</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Guardar Tarea
                        </button>
                        <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-white hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm" @click="openModalAgregar = false; resetNewTareaForm()">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div x-cloak x-show="openModalEditar" x-transition class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-editar-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen p-4"> 
            <div x-show="openModalEditar" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" @click="openModalEditar = false; tareaParaEditar = null;"></div>
            
            <div x-show="openModalEditar" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle w-full max-w-lg sm:max-w-2xl"> 
                <form action="procesar_edicion_tarea.php" method="POST" x-show="tareaParaEditar"> 
                    <input type="hidden" name="id" :value="tareaParaEditar ? tareaParaEditar.id : ''">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 dark:bg-yellow-900 sm:mx-0 sm:h-10 sm:w-10">
                                <i class="bi bi-pencil-fill text-yellow-600 dark:text-yellow-300"></i>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="modal-editar-title">
                                    Editar Tarea Veterinaria
                                </h3>
                                <div class="mt-4 space-y-4">
                                    <div>
                                        <label for="descripcion_editar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Descripción:</label>
                                        <textarea name="descripcion" id="descripcion_editar" :value="tareaParaEditar ? tareaParaEditar.descripcion : ''" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm h-24" required></textarea>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label for="fecha_editar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Fecha:</label>
                                            <input type="date" name="fecha" id="fecha_editar" :value="tareaParaEditar ? tareaParaEditar.fecha : ''" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" required>
                                        </div>
                                        <div>
                                            <label for="hora_editar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Hora:</label>
                                            <input type="time" name="hora" id="hora_editar" :value="tareaParaEditar ? tareaParaEditar.hora : ''" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" required>
                                        </div>
                                    </div>
                                    <div>
                                        <label for="usuario_id_editar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Asignar a Veterinario:</label>
                                        <select name="usuario_id" id="usuario_id_editar" :value="tareaParaEditar ? tareaParaEditar.usuario_id : ''" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" required>
                                            <option value="">Seleccionar Veterinario</option>
                                            <?php foreach ($veterinarios_list as $vet): ?>
                                                <option value="<?= htmlspecialchars($vet['id']) ?>"><?= htmlspecialchars($vet['nombre']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="animal_id_editar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Animal Relacionado (Opcional):</label>
                                        <select name="animal_id" id="animal_id_editar" :value="tareaParaEditar ? tareaParaEditar.animal_id : ''" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                                            <option value="">Ninguno</option>
                                            <?php foreach ($animales_list as $animal): ?>
                                                <option value="<?= htmlspecialchars($animal['id']) ?>"><?= htmlspecialchars($animal['nombre']) ?> (<?= htmlspecialchars($animal['codigo_animal']) ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="completado_editar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Estado:</label>
                                        <select name="completado" id="completado_editar" :value="tareaParaEditar ? tareaParaEditar.completado : '0'" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                                            <option value="0">Pendiente</option>
                                            <option value="1">Completada</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Actualizar Tarea
                        </button>
                        <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-white hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm" @click="openModalEditar = false; tareaParaEditar = null;">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div x-cloak x-show="openConfirmModal" x-transition class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-confirm-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen p-4"> 
            <div x-show="openConfirmModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
            <div x-show="openConfirmModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle w-full max-w-lg"> 
                <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900 sm:mx-0 sm:h-10 sm:w-10">
                            <i class="bi bi-exclamation-triangle-fill text-red-600 dark:text-red-300"></i>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="modal-confirm-title">
                                Eliminar Tarea
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    ¿Estás seguro de que quieres eliminar esta tarea? Esta acción no se puede deshacer.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm" @click="deleteConfirmed()">
                        Eliminar
                    </button>
                    <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-white hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm" @click="openConfirmModal = false; productoToDeleteId = null;">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('appData', () => ({
                openModalAgregar: false,
                openModalEditar: false,
                openConfirmModal: false,
                alpineMessage: '', 
                tareaToDeleteId: null,
                userRol: '<?= $user_rol ?>', // Pasa el rol del usuario a Alpine.js
                
                newTarea: {
                    descripcion: '',
                    fecha: new Date().toISOString().slice(0, 10), // Fecha actual por defecto
                    hora: '',
                    usuario_id: '<?php echo $_SESSION['usuario_id']; ?>', // Pre-seleccionar el ID del usuario logueado
                    animal_id: '', // Opcional, si se añade a la tabla tareas
                    completado: '0' // Por defecto pendiente
                },

                tareaParaEditar: null,

                toggleTheme() {
                    if (document.documentElement.classList.contains('dark')) {
                        document.documentElement.classList.remove('dark');
                        localStorage.setItem('theme', 'light');
                    } else {
                        document.documentElement.classList.add('dark');
                        localStorage.setItem('theme', 'dark');
                    }
                },

                init() {
                    if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                        document.documentElement.classList.add('dark');
                    } else {
                        document.documentElement.classList.remove('dark');
                    }
                },

                resetNewTareaForm() {
                    this.newTarea = {
                        descripcion: '',
                        fecha: new Date().toISOString().slice(0, 10),
                        hora: '',
                        usuario_id: '<?php echo $_SESSION['usuario_id']; ?>', // Resetear al ID del usuario logueado
                        animal_id: '',
                        completado: '0'
                    };
                },

                inicializarFormularioEdicion() {
                    if (this.tareaParaEditar && this.tareaParaEditar.hora && this.tareaParaEditar.hora.length > 5) {
                        this.tareaParaEditar.hora = this.tareaParaEditar.hora.slice(0, 5);
                    }
                    console.log('Tarea para editar:', this.tareaParaEditar);
                },

                confirmDelete(id) {
                    this.tareaToDeleteId = id;
                    this.openConfirmModal = true;
                },

                deleteConfirmed() {
                    if (this.tareaToDeleteId) {
                        window.location.href = `eliminar_tarea.php?id=${this.tareaToDeleteId}`;
                    }
                },
                
                // Nueva función para marcar tarea como completada
                async markAsCompleted(taskId) {
                    if (!confirm('¿Estás seguro de que quieres marcar esta tarea como completada?')) {
                        return;
                    }

                    try {
                        const response = await fetch('marcar_tarea_completada.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ id: taskId })
                        });

                        const result = await response.json();

                        if (result.success) {
                            alert('Tarea marcada como completada exitosamente.');
                            window.location.reload(); // Recargar la página para actualizar las tablas
                        } else {
                            alert('Error al marcar la tarea como completada: ' + result.message);
                        }
                    } catch (error) {
                        console.error('Error al enviar la solicitud:', error);
                        alert('Hubo un error de conexión al intentar completar la tarea.');
                    }
                },

                formatDate(dateString) {
                    if (!dateString) return '';
                    try {
                        const date = new Date(dateString);
                        const options = { year: 'numeric', month: '2-digit', day: '2-digit' };
                        return date.toLocaleDateString('es-ES', options);
                    } catch (e) {
                        console.error("Error formatting date:", dateString, e);
                        return dateString;
                    }
                }
            }));
        });
    </script>
</body>
</html>
