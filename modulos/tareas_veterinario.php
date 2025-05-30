<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

include '../db.php'; // Ensure the path to your db.php is correct

// Get the logged-in user's role
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

// Fetch all users/workers for assignment dropdowns
$all_users_list = [];
try {
    $stmt_users = $conn->prepare("SELECT id, nombre, rol FROM usuarios WHERE rol IN ('veterinario', 'trabajador', 'administrador') ORDER BY nombre ASC");
    $stmt_users->execute();
    $all_users_list = $stmt_users->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al obtener la lista de usuarios: " . $e->getMessage());
    // Handle error appropriately
}

// Fetch all animals for the animal dropdown
$animales_list = [];
try {
    $stmt_animals = $conn->prepare("SELECT id, nombre, codigo_animal FROM animales ORDER BY nombre ASC");
    $stmt_animals->execute();
    $animales_list = $stmt_animals->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al obtener la lista de animales: " . $e->getMessage());
    // Handle error appropriately
}


// Get filters and search terms
$estado_filtro = isset($_GET['estado']) ? $_GET['estado'] : 'todos';
$buscar_termino = isset($_GET['buscar']) ? $_GET['buscar'] : null;
$fecha_filtro = isset($_GET['fecha']) ? $_GET['fecha'] : null;

// Build and execute the query to get all relevant tasks
$sql = "SELECT t.*, u.nombre as nombre_usuario, a.nombre as nombre_animal
        FROM tareas t
        LEFT JOIN usuarios u ON t.usuario_id = u.id
        LEFT JOIN animales a ON t.animal_id = a.id";
$conditions = [];
$params = [];

// Adjust task visibility based on user role
if ($user_rol !== 'administrador') { // If not an admin, only show tasks assigned to them
    $conditions[] = "t.usuario_id = :session_user_id";
    $params[':session_user_id'] = $_SESSION['usuario_id'];
}

if ($buscar_termino) {
    // Search by task description, assigned user's name, or animal's name
    $conditions[] = "(t.descripcion LIKE :buscar_descripcion OR u.nombre LIKE :buscar_usuario OR a.nombre LIKE :buscar_animal)";
    $params[':buscar_descripcion'] = "%$buscar_termino%";
    $params[':buscar_usuario'] = "%$buscar_termino%";
    $params[':buscar_animal'] = "%$buscar_termino%";
}

if ($fecha_filtro) {
    $conditions[] = "t.fecha = :fecha_filtro";
    $params[':fecha_filtro'] = $fecha_filtro;
}

// Add state filter
if ($estado_filtro === 'pendiente') {
    $conditions[] = "t.completado = 0";
} elseif ($estado_filtro === 'completado') {
    $conditions[] = "t.completado = 1";
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

// Handle session messages
$mensaje = isset($_SESSION['mensaje']) ? $_SESSION['mensaje'] : "";
unset($_SESSION['mensaje']);
?>

<!DOCTYPE html>
<html lang="es" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Tareas</title>
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
                        primary: {
                            50: '#f0fdf4',
                            100: '#dcfce7',
                            200: '#bbf7d0',
                            300: '#86efac',
                            400: '#4ade80',
                            500: '#22c55e',
                            600: '#16a34a',
                            700: '#15803d',
                            800: '#166534',
                            900: '#14532d'
                        },
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

        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-completado {
            background-color: #dcfce7;
            color: #166534;
        }

        /* Verde */
        .status-pendiente {
            background-color: #fef9c3;
            color: #a16207;
        }

        /* Amarillo */

        [x-cloak] {
            display: none !important;
        }
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
                        <i class="fas fa-clipboard-list text-primary-600 mr-2"></i> Gestión de Tareas
                    </h1>
                    <p class="text-gray-500 dark:text-gray-400">Administra las tareas y citas para el cuidado de los animales</p>
                </div>

                <div class="mt-4 md:mt-0 flex space-x-3">
                    <?php if ($user_rol === 'administrador'): // Only administrators can add tasks
                    ?>
                        <button @click="openModalAgregar = true" class="flex items-center justify-center px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg transition-colors duration-200">
                            <i class="bi bi-plus-lg mr-2"></i>
                            Agregar Tarea
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 space-y-4 md:space-y-0 md:space-x-4">
                <form action="tareas_veterinario.php" method="GET" class="w-full md:w-auto flex-grow" id="filterForm">
                    <div class="relative max-w-md">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="bi bi-search text-gray-400"></i>
                        </div>
                        <input type="text" name="buscar" class="block w-full pl-10 pr-12 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg focus:ring-primary-500 focus:border-primary-500" placeholder="Buscar por descripción, trabajador o animal" value="<?= htmlspecialchars($buscar_termino ?? '') ?>">
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

                    <input type="date" name="fecha" form="filterForm" class="px-3 py-1 rounded-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-gray-700 dark:text-gray-300 focus:ring-primary-500 focus:border-primary-500" value="<?= htmlspecialchars($fecha_filtro ?? '') ?>" onchange="document.getElementById('filterForm').submit()">
                </div>
            </div>

            <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-4">Todas las Tareas</h2>
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
                            <?php if (empty($all_tareas)): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                        No se encontraron tareas.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($all_tareas as $tarea): ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-6 py-4 whitespace-normal text-sm font-medium text-gray-900 dark:text-white max-w-xs"><?= htmlspecialchars($tarea['descripcion']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900 dark:text-white"><?= htmlspecialchars($tarea['fecha']) ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900 dark:text-white"><?= htmlspecialchars(substr($tarea['hora'], 0, 5)) ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900 dark:text-white"><?= htmlspecialchars($tarea['nombre_usuario'] ?? 'N/A') ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900 dark:text-white"><?= htmlspecialchars($tarea['nombre_animal'] ?? 'N/A') ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($tarea['completado'] == 0): ?>
                                                <span class="status-badge status-pendiente">Pendiente</span>
                                            <?php else: ?>
                                                <span class="status-badge status-completado">Completada</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-2">
                                                <?php if ($tarea['completado'] == 0 && ($user_rol !== 'administrador' && $tarea['usuario_id'] == $_SESSION['usuario_id'])): // Any assigned worker can complete their own pending tasks
                                                ?>
                                                    <button type="button" @click="markAsCompleted(<?= $tarea['id'] ?>)" class="text-primary-600 dark:text-primary-400 hover:text-primary-900 dark:hover:text-primary-500 flex items-center">
                                                        <i class="bi bi-check-circle mr-1"></i> Completar
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($user_rol === 'administrador'): // Admin can edit/delete any task, assigned worker can edit/delete their own tasks
                                                ?>
                                                    <button type="button" @click="openModalEditar = true; tareaParaEditar = JSON.parse('<?= htmlspecialchars(json_encode($tarea), ENT_QUOTES, 'UTF-8') ?>'); inicializarFormularioEdicion();" class="text-yellow-600 dark:text-yellow-400 hover:text-yellow-900 dark:hover:text-yellow-500">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($user_rol === 'administrador'): // Only administrators can delete any task
                                                ?>
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
                                    Agregar Nueva Tarea
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
                                        <label for="usuario_id_agregar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Asignar a:</label> <select name="usuario_id" id="usuario_id_agregar" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" required>
                                                <option value="">Seleccionar Trabajador</option> <?php foreach ($all_users_list as $user): // Iterate through all users/workers
                                                                                                        ?>
                                                    <option value="<?= htmlspecialchars($user['id']) ?>"><?= htmlspecialchars($user['nombre']) ?> (<?= htmlspecialchars($user['rol']) ?>)</option>
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
                        <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm dark:bg-gray-600 dark:text-white dark:hover:bg-gray-500" @click="openModalAgregar = false; resetNewTareaForm()">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div x-cloak x-show="openModalEditar" x-transition class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-editar-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div x-show="openModalEditar" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" @click="openModalEditar = false; resetEditTareaForm()"></div>

            <div x-show="openModalEditar" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle w-full max-w-lg sm:max-w-2xl">
                <form action="procesar_edicion_tarea.php" method="POST">
                    <input type="hidden" name="id" x-model="tareaParaEditar.id">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 dark:bg-yellow-900 sm:mx-0 sm:h-10 sm:w-10">
                                <i class="fas fa-edit text-yellow-600 dark:text-yellow-300"></i>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="modal-editar-title">
                                    Editar Tarea
                                </h3>
                                <div class="mt-4 space-y-4">
                                    <div>
                                        <label for="descripcion_editar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Descripción:</label>
                                        <textarea name="descripcion" id="descripcion_editar" x-model="tareaParaEditar.descripcion" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-yellow-500 focus:ring-yellow-500 sm:text-sm h-24" required></textarea>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label for="fecha_editar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Fecha:</label>
                                            <input type="date" name="fecha" id="fecha_editar" x-model="tareaParaEditar.fecha" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-yellow-500 focus:ring-yellow-500 sm:text-sm" required>
                                        </div>
                                        <div>
                                            <label for="hora_editar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Hora:</label>
                                            <input type="time" name="hora" id="hora_editar" x-model="tareaParaEditar.hora" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-yellow-500 focus:ring-yellow-500 sm:text-sm" required>
                                        </div>
                                    </div>
                                    <div>
                                        <label for="usuario_id_editar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Asignar a:</label>
                                        <select name="usuario_id" id="usuario_id_editar" x-model="tareaParaEditar.usuario_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-yellow-500 focus:ring-yellow-500 sm:text-sm" required>
                                            <option value="">Seleccionar Trabajador</option>
                                            <?php foreach ($all_users_list as $user): ?>
                                                <option value="<?= htmlspecialchars($user['id']) ?>"><?= htmlspecialchars($user['nombre']) ?> (<?= htmlspecialchars($user['rol']) ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="animal_id_editar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Animal Relacionado (Opcional):</label>
                                        <select name="animal_id" id="animal_id_editar" x-model="tareaParaEditar.animal_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-yellow-500 focus:ring-yellow-500 sm:text-sm">
                                            <option value="">Ninguno</option>
                                            <?php foreach ($animales_list as $animal): ?>
                                                <option value="<?= htmlspecialchars($animal['id']) ?>"><?= htmlspecialchars($animal['nombre']) ?> (<?= htmlspecialchars($animal['codigo_animal']) ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="completado_editar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Estado:</label>
                                        <select name="completado" id="completado_editar" x-model="tareaParaEditar.completado" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-yellow-500 focus:ring-yellow-500 sm:text-sm">
                                            <option value="0">Pendiente</option>
                                            <option value="1">Completada</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-yellow-600 text-base font-medium text-white hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Guardar Cambios
                        </button>
                        <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm dark:bg-gray-600 dark:text-white dark:hover:bg-gray-500" @click="openModalEditar = false; resetEditTareaForm()">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <div x-cloak x-show="openModalEliminar" x-transition class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-eliminar-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div x-show="openModalEliminar" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" @click="openModalEliminar = false"></div>

            <div x-show="openModalEliminar" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle w-full max-w-lg">
                <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900 sm:mx-0 sm:h-10 sm:w-10">
                            <i class="fas fa-exclamation-triangle text-red-600 dark:text-red-300"></i>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="modal-eliminar-title">
                                Eliminar Tarea
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    ¿Estás seguro de que deseas eliminar esta tarea? Esta acción no se puede deshacer.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" @click="deleteTask()" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Eliminar
                    </button>
                    <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm dark:bg-gray-600 dark:text-white dark:hover:bg-gray-500" @click="openModalEliminar = false">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function appData() {
            return {
                openModalAgregar: false,
                openModalEditar: false,
                openModalEliminar: false,
                tareaParaEditar: {},
                tareaParaEliminarId: null,
                alpineMessage: '',
                newTarea: {
                    fecha: '',
                    hora: ''
                },
                allUsers: <?= json_encode($all_users_list) ?>,
                allAnimals: <?= json_encode($animales_list) ?>,

                init() {
                    this.setInitialTheme();
                    this.setCurrentDateForNewTarea();
                },

                setInitialTheme() {
                    if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                        document.documentElement.classList.add('dark');
                    } else {
                        document.documentElement.classList.remove('dark');
                    }
                },

                toggleTheme() {
                    if (document.documentElement.classList.contains('dark')) {
                        document.documentElement.classList.remove('dark');
                        localStorage.setItem('theme', 'light');
                    } else {
                        document.documentElement.classList.add('dark');
                        localStorage.setItem('theme', 'dark');
                    }
                },

                setCurrentDateForNewTarea() {
                    const today = new Date();
                    const yyyy = today.getFullYear();
                    const mm = String(today.getMonth() + 1).padStart(2, '0'); // Months start at 0!
                    const dd = String(today.getDate()).padStart(2, '0');
                    this.newTarea.fecha = `${yyyy}-${mm}-${dd}`;
                },

                inicializarFormularioEdicion() {
                    // Ensure the date and time formats are correct for input fields
                    if (this.tareaParaEditar.fecha) {
                        this.tareaParaEditar.fecha = this.formatDate(this.tareaParaEditar.fecha);
                    }
                    if (this.tareaParaEditar.hora && this.tareaParaEditar.hora.length > 5) {
                        this.tareaParaEditar.hora = this.tareaParaEditar.hora.substring(0, 5);
                    }
                },

                resetNewTareaForm() {
                    this.newTarea = {
                        descripcion: '',
                        fecha: this.newTarea.fecha, // Keep current date
                        hora: '',
                        usuario_id: '',
                        animal_id: '',
                        completado: '0'
                    };
                    this.alpineMessage = '';
                },

                resetEditTareaForm() {
                    this.tareaParaEditar = {};
                    this.alpineMessage = '';
                },

                confirmDelete(id) {
                    this.tareaParaEliminarId = id;
                    this.openModalEliminar = true;
                },

                async deleteTask() {
                    try {
                        const response = await fetch('eliminar_tarea.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ id: this.tareaParaEliminarId })
                        });

                        const result = await response.json();

                        if (result.success) {
                            alert('Tarea eliminada exitosamente.');
                            window.location.reload(); // Recargar la página para actualizar las tablas
                        } else {
                            alert('Error al eliminar la tarea: ' + result.message);
                        }
                    } catch (error) {
                        console.error('Error al enviar la solicitud:', error);
                        alert('Hubo un error de conexión al intentar eliminar la tarea.');
                    } finally {
                        this.openModalEliminar = false;
                        this.tareaParaEliminarId = null;
                    }
                },

                async markAsCompleted(taskId) {
                    if (!confirm('¿Estás seguro de que quieres marcar esta tarea como completada?')) {
                        return;
                    }

                    try {
                        const response = await fetch('marcar_completada.php', {
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
            };
        };
    </script>
</body>
</html>