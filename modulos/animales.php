<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

include '../db.php'; // Asegúrate que la ruta a tu archivo db.php sea correcta

$mensaje = "";
$animales = []; 
$inventario_medicamentos_list = []; // Para almacenar los medicamentos del inventario

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
    $mensaje = "Error al verificar el rol del usuario.";
}

// Obtener la lista de animales
try {
    $sql_animales = "SELECT * FROM animales";
    if (isset($_POST['buscar']) && !empty($_POST['buscar'])) {
        $buscar = $_POST['buscar'];
        $sql_animales .= " WHERE nombre LIKE :nombre";
        $stmt = $conn->prepare($sql_animales);
        $stmt->execute(['nombre' => "%$buscar%"]);
    } else {
        $stmt = $conn->query($sql_animales);
    }
    
    if ($stmt) {
        $animales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $mensaje = "Error al cargar los animales.";
    }
} catch (PDOException $e) {
    $mensaje = "Error de base de datos al cargar animales: " . $e->getMessage();
    $animales = [];
}

// Obtener la lista de medicamentos del inventario
try {
    // Asumiendo que tienes una columna 'tipo' en tu tabla 'inventario' para identificar medicamentos
    $stmt_medicamentos = $conn->query("SELECT id, nombre, cantidad FROM inventario WHERE tipo = 'medicamento' AND cantidad > 0 ORDER BY nombre ASC");
    if ($stmt_medicamentos) {
        $inventario_medicamentos_list = $stmt_medicamentos->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Error al cargar lista de medicamentos del inventario: " . $e->getMessage());
    // No es un error crítico si no se cargan los medicamentos, pero se registra
}

// Definir el precio por kilogramo
$precio_por_kg = 10000; 
?>

<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Animales</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
        
        .health-badge { padding: 0.25rem 0.5rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 500; }
        .health-vacunado { background-color: #dcfce7; color: #166534; }
        .health-sin-vacuna { background-color: #fee2e2; color: #991b1b; }
        .health-default { background-color: #e5e7eb; color: #374151; }

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
                <div class="mb-4 p-4 text-sm text-red-700 bg-red-100 rounded-lg dark:bg-red-200 dark:text-red-800" role="alert">
                    <?= htmlspecialchars($mensaje) ?>
                </div>
            <?php endif; ?>
            <template x-if="alpineMessage">
                <div class="mb-4 p-4 text-sm text-red-700 bg-red-100 rounded-lg dark:bg-red-200 dark:text-red-800" role="alert" x-text="alpineMessage"></div>
            </template>

            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold flex items-center">
                        <i class="bi bi-egg-fried text-primary-600 mr-2"></i>
                        Gestión de Animales
                    </h1>
                    <p class="text-gray-500 dark:text-gray-400">Administra el ganado y animales de la granja</p>
                </div>
                
                <div class="mt-4 md:mt-0 flex space-x-3">
                    <button @click="openModalAgregar = true" class="flex items-center justify-center px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg transition-colors duration-200">
                        <i class="bi bi-plus-lg mr-2"></i>
                        Agregar Animal
                    </button>
                </div>
            </div>

            <form action="animales.php" method="POST" class="mb-6">
                <div class="relative max-w-md">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="bi bi-search text-gray-400"></i>
                    </div>
                    <input type="text" name="buscar" class="block w-full pl-10 pr-12 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg focus:ring-primary-500 focus:border-primary-500" placeholder="Buscar por nombre" value="<?= isset($_POST['buscar']) ? htmlspecialchars($_POST['buscar']) : '' ?>">
                    <div class="absolute inset-y-0 right-0 flex items-center">
                        <button type="submit" class="px-4 h-full bg-primary-600 text-white rounded-r-lg hover:bg-primary-700 focus:outline-none">
                            Buscar
                        </button>
                    </div>
                </div>
            </form>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-soft overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Código</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Nombre</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Especie</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Raza</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Sexo</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Edad</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Peso</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Precio</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Salud</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Ingreso</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach ($animales as $animal): ?>
                                <?php
                                $precio = $animal['peso'] * $precio_por_kg;
                                $estadoSaludClass = 'health-' . str_replace(' ', '-', strtolower(htmlspecialchars($animal['estado_salud'])));
                                if ($animal['estado_salud'] !== 'vacunado' && $animal['estado_salud'] !== 'sin vacuna') {
                                    $estadoSaludClass = 'health-default';
                                }
                                ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($animal['codigo_animal']) ?></div></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($animal['nombre']) ?></div></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm text-gray-900 dark:text-white"><?= htmlspecialchars($animal['especie']) ?></div></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm text-gray-900 dark:text-white"><?= htmlspecialchars($animal['raza']) ?></div></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm text-gray-900 dark:text-white capitalize"><?= htmlspecialchars($animal['sexo']) ?></div></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm text-gray-900 dark:text-white"><?= htmlspecialchars($animal['edad']) ?> años</div></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm text-gray-900 dark:text-white"><?= htmlspecialchars($animal['peso']) ?> kg</div></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm text-gray-900 dark:text-white">$<?= number_format($precio, 2) ?></div></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="health-badge <?= $estadoSaludClass ?>">
                                            <?= htmlspecialchars($animal['estado_salud']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm text-gray-500 dark:text-gray-400"><?= date('d/m/Y', strtotime($animal['fecha_ingreso'])) ?></div></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <?php if ($user_rol === 'veterinario' && strtolower($animal['estado_salud']) === 'sin vacuna'): ?>
                                                <button type="button" @click="openVacunarModal(<?= $animal['id'] ?>)" class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-500 flex items-center">
                                                    <i class="bi bi-syringe mr-1"></i> Vacunar
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" @click="openModalEditar = true; animalParaEditar = JSON.parse('<?= htmlspecialchars(json_encode($animal), ENT_QUOTES, 'UTF-8') ?>'); inicializarFormularioEdicion();" class="text-yellow-600 dark:text-yellow-400 hover:text-yellow-900 dark:hover:text-yellow-500">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <a href="eliminar_animal.php?id=<?= $animal['id'] ?>" class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-500" onclick="return confirm('¿Estás seguro de eliminar este animal?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($animales)): ?>
                                <tr>
                                    <td colspan="11" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                        No se encontraron animales registrados
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <div x-cloak x-show="openModalAgregar" x-transition class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-agregar-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="openModalAgregar" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" @click="openModalAgregar = false"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div x-show="openModalAgregar" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                <form action="procesar_animal.php" method="POST"> <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-primary-100 dark:bg-primary-900 sm:mx-0 sm:h-10 sm:w-10">
                                <i class="bi bi-egg-fried text-primary-600 dark:text-primary-300"></i>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="modal-agregar-title">
                                    Agregar Nuevo Animal
                                </h3>
                                <div class="mt-4 space-y-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label for="codigo_animal_agregar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Código de Animal:</label>
                                            <input type="text" name="codigo_animal" id="codigo_animal_agregar" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" required>
                                        </div>
                                        <div>
                                            <label for="nombre_agregar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nombre:</label>
                                            <input type="text" name="nombre" id="nombre_agregar" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" required>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label for="especie_agregar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Especie:</label>
                                            <select id="especie_agregar" name="especie" @change="actualizarRazas('agregar', $event.target.value)" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" required>
                                                <option value="">Seleccionar Especie</option>
                                                <option value="Vaca">Vaca</option> <option value="Cerdo">Cerdo</option> <option value="Oveja">Oveja</option> <option value="Caballo">Caballo</option> <option value="Gallina">Gallina</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="raza_agregar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Raza:</label>
                                            <select id="raza_agregar" name="raza" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" required>
                                                <option value="">Seleccionar Raza</option>
                                            </select>
                                        </div>
                                    </div>
                                     <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label for="sexo_agregar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Sexo:</label>
                                            <select name="sexo" id="sexo_agregar" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                                                <option value="macho">Macho</option> <option value="hembra">Hembra</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="edad_agregar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Edad (años):</label>
                                            <input type="number" name="edad" id="edad_agregar" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" min="0">
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label for="peso_agregar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Peso (kg):</label>
                                            <input type="number" name="peso" id="peso_agregar" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" min="0">
                                        </div>
                                        <div>
                                            <label for="estado_salud_agregar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Estado de Salud:</label>
                                            <select name="estado_salud" id="estado_salud_agregar" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                                                <option value="vacunado">Vacunado</option> <option value="sin vacuna">Sin Vacuna</option> </select>
                                        </div>
                                    </div>
                                    <div>
                                        <label for="fecha_ingreso_agregar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Fecha de Ingreso:</label>
                                        <input type="date" name="fecha_ingreso" id="fecha_ingreso_agregar" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" name="agregar_animal" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Guardar Animal
                        </button>
                        <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-white hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm" @click="openModalAgregar = false">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div x-cloak x-show="openModalEditar" x-transition class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-editar-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="openModalEditar" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" @click="openModalEditar = false; animalParaEditar = null;"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div x-show="openModalEditar" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                <form action="procesar_edicion_animal.php" method="POST" x-show="animalParaEditar"> 
                    <input type="hidden" name="id_animal" :value="animalParaEditar ? animalParaEditar.id : ''">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 dark:bg-yellow-900 sm:mx-0 sm:h-10 sm:w-10">
                                <i class="bi bi-pencil-fill text-yellow-600 dark:text-yellow-300"></i>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="modal-editar-title">
                                    Editar Animal
                                </h3>
                                <div class="mt-4 space-y-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label for="codigo_animal_editar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Código de Animal:</label>
                                            <input type="text" name="codigo_animal" id="codigo_animal_editar" :value="animalParaEditar ? animalParaEditar.codigo_animal : ''" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" required>
                                        </div>
                                        <div>
                                            <label for="nombre_editar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nombre:</label>
                                            <input type="text" name="nombre" id="nombre_editar" :value="animalParaEditar ? animalParaEditar.nombre : ''" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" required>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label for="especie_editar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Especie:</label>
                                            <select id="especie_editar" name="especie" :value="animalParaEditar ? animalParaEditar.especie : ''" @change="actualizarRazas('editar', $event.target.value)" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" required>
                                                <option value="">Seleccionar Especie</option>
                                                <option value="Vaca">Vaca</option> <option value="Cerdo">Cerdo</option> <option value="Oveja">Oveja</option> <option value="Caballo">Caballo</option> <option value="Gallina">Gallina</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="raza_editar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Raza:</label>
                                            <select id="raza_editar" name="raza" :value="animalParaEditar ? animalParaEditar.raza : ''" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" required>
                                                <option value="">Seleccionar Raza</option>
                                            </select>
                                        </div>
                                    </div>
                                     <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label for="sexo_editar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Sexo:</label>
                                            <select name="sexo" id="sexo_editar" :value="animalParaEditar ? animalParaEditar.sexo : ''" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                                                <option value="macho">Macho</option> <option value="hembra">Hembra</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="edad_editar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Edad (años):</label>
                                            <input type="number" name="edad" id="edad_editar" :value="animalParaEditar ? animalParaEditar.edad : ''" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" min="0">
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label for="peso_editar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Peso (kg):</label>
                                            <input type="number" name="peso" id="peso_editar" :value="animalParaEditar ? animalParaEditar.peso : ''" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" min="0">
                                        </div>
                                        <div>
                                            <label for="estado_salud_editar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Estado de Salud:</label>
                                            <select name="estado_salud" id="estado_salud_editar" :value="animalParaEditar ? animalParaEditar.estado_salud : ''" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                                                <option value="vacunado">Vacunado</option> <option value="sin vacuna">Sin Vacuna</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div>
                                        <label for="fecha_ingreso_editar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Fecha de Ingreso:</label>
                                        <input type="date" name="fecha_ingreso" id="fecha_ingreso_editar" :value="animalParaEditar ? animalParaEditar.fecha_ingreso.split(' ')[0] : ''" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" name="editar_animal" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Guardar Cambios
                        </button>
                        <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-white hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm" @click="openModalEditar = false; animalParaEditar = null;">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div x-cloak x-show="openModalVacunar" x-transition class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-vacunar-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="openModalVacunar" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" @click="openModalVacunar = false; animalParaVacunar = null; selectedMedicamentos = {};"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div x-show="openModalVacunar" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 dark:bg-blue-900 sm:mx-0 sm:h-10 sm:w-10">
                            <i class="bi bi-syringe text-blue-600 dark:text-blue-300"></i>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="modal-vacunar-title">
                                Vacunar Animal
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Selecciona los medicamentos a utilizar para la vacunación del animal.
                                </p>
                            </div>
                            <div class="mt-4 space-y-3">
                                <?php if (empty($inventario_medicamentos_list)): ?>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">No hay medicamentos disponibles en el inventario.</p>
                                <?php else: ?>
                                    <template x-for="med in inventarioMedicamentos" :key="med.id">
                                        <div class="flex items-center justify-between py-2 border-b border-gray-200 dark:border-gray-700 last:border-b-0">
                                            <label :for="'med_qty_' + med.id" class="text-sm font-medium text-gray-700 dark:text-gray-300 flex-grow">
                                                <span x-text="med.nombre"></span> 
                                                <span class="text-xs text-gray-500 dark:text-gray-400">(Disponible: <span x-text="med.cantidad"></span>)</span>
                                            </label>
                                            <input type="number" 
                                                   :id="'med_qty_' + med.id" 
                                                   x-model.number="selectedMedicamentos[med.id]" 
                                                   min="0" 
                                                   :max="med.cantidad" 
                                                   class="ml-4 w-24 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                                        </div>
                                    </template>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" @click="confirmarVacunacion()" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:ml-3 sm:w-auto sm:text-sm" :disabled="Object.keys(selectedMedicamentos).length === 0 && inventarioMedicamentos.length > 0">
                        Confirmar Vacunación
                    </button>
                    <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-white hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm" @click="openModalVacunar = false; animalParaVacunar = null; selectedMedicamentos = {};">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>


    <script>
        // Mapeo de razas por especie (mantener esto si lo necesitas para los modales de agregar/editar)
        const razasPorEspecie = {
            "Vaca": ["Holstein", "Jersey", "Pardo Suizo", "Angus", "Hereford", "Brahman"],
            "Cerdo": ["Landrace", "Yorkshire", "Duroc", "Pietrain", "Hampshire"],
            "Oveja": ["Merina", "Rasa Aragonesa", "Lacaune", "Dorper", "Katahdin"],
            "Caballo": ["Cuarto de Milla", "Pura Raza Española", "Árabe", "Frisón", "Appaloosa"],
            "Gallina": ["Leghorn", "Rhode Island Red", "Plymouth Rock", "Isa Brown", "Brahma"]
        };

        function appData() {
            return {
                openModalAgregar: false,
                openModalEditar: false,
                openModalVacunar: false, // Nuevo estado para el modal de vacunación
                animalParaEditar: null,
                animalParaVacunar: null, // Nuevo estado para el animal a vacunar
                alpineMessage: '', 
                userRol: '<?= $user_rol ?>', 
                
                // Inventario de medicamentos disponible en el frontend (pasado desde PHP)
                inventarioMedicamentos: <?= json_encode($inventario_medicamentos_list) ?>,
                selectedMedicamentos: {}, // Para almacenar las cantidades seleccionadas por el usuario

                // Función para actualizar el select de razas
                actualizarRazas(modalType, especieSeleccionada) {
                    const razaSelectId = modalType === 'agregar' ? 'raza_agregar' : 'raza_editar';
                    const razaSelect = document.getElementById(razaSelectId);
                    
                    if (!razaSelect) return;

                    const valorActualRaza = (modalType === 'editar' && this.animalParaEditar) ? this.animalParaEditar.raza : null;

                    razaSelect.innerHTML = '<option value="">Seleccionar Raza</option>'; // Limpiar

                    if (especieSeleccionada && razasPorEspecie[especieSeleccionada]) {
                        const razas = razasPorEspecie[especieSeleccionada];
                        razas.forEach(function(raza) {
                            const option = document.createElement('option');
                            option.value = raza;
                            option.textContent = raza;
                            razaSelect.appendChild(option);
                        });
                    }
                    if (valorActualRaza) {
                         razaSelect.value = valorActualRaza;
                    }
                },
                inicializarFormularioEdicion() {
                    this.$nextTick(() => {
                        if (this.animalParaEditar) {
                            this.actualizarRazas('editar', this.animalParaEditar.especie);
                            const razaSelectEdit = document.getElementById('raza_editar');
                            if(razaSelectEdit) razaSelectEdit.value = this.animalParaEditar.raza;

                            const fechaIngresoInput = document.getElementById('fecha_ingreso_editar');
                            if (fechaIngresoInput && this.animalParaEditar.fecha_ingreso) {
                                fechaIngresoInput.value = this.animalParaEditar.fecha_ingreso.split(' ')[0];
                            }
                        }
                    });
                },

                // Nueva función para abrir el modal de vacunación
                openVacunarModal(animalId) {
                    this.animalParaVacunar = animalId;
                    this.selectedMedicamentos = {}; // Resetear las cantidades seleccionadas
                    // Asegurar que el modal se muestre
                    this.openModalVacunar = true;
                },

                // Nueva función para confirmar la vacunación y enviar datos
                async confirmarVacunacion() {
                    if (!this.animalParaVacunar) {
                        this.alpineMessage = 'Error: No se ha seleccionado ningún animal para vacunar.';
                        return;
                    }

                    const medicamentosAUsar = Object.entries(this.selectedMedicamentos)
                        .filter(([medId, qty]) => qty > 0) // Solo incluir medicamentos con cantidad > 0
                        .map(([medId, qty]) => ({ id: parseInt(medId), cantidad: qty }));

                    if (medicamentosAUsar.length === 0 && this.inventarioMedicamentos.length > 0) {
                        if (!confirm('¿No has seleccionado ningún medicamento. Continuar con la vacunación sin descontar del inventario?')) {
                            return;
                        }
                    }

                    try {
                        const response = await fetch('procesar_vacunacion.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ 
                                animal_id: this.animalParaVacunar,
                                medicamentos: medicamentosAUsar
                            })
                        });

                        const result = await response.json();

                        if (result.success) {
                            this.alpineMessage = 'Animal vacunado exitosamente. ' + result.message;
                            this.openModalVacunar = false; // Cerrar el modal
                            this.animalParaVacunar = null;
                            this.selectedMedicamentos = {};
                            setTimeout(() => {
                                window.location.reload(); // Recargar la página para ver el cambio
                            }, 1500);
                        } else {
                            this.alpineMessage = 'Error al vacunar el animal: ' + result.message;
                        }
                    } catch (error) {
                        console.error('Error al enviar la solicitud de vacunación:', error);
                        this.alpineMessage = 'Hubo un error de conexión al intentar vacunar el animal.';
                    }
                },

                // Funciones existentes (mantenerlas si son necesarias)
                confirmDelete(id) {
                    if (confirm('¿Estás seguro de eliminar este animal?')) {
                        window.location.href = `eliminar_animal.php?id=${id}`;
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

                init() {
                    if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                        document.documentElement.classList.add('dark');
                    } else {
                        document.documentElement.classList.remove('dark');
                    }

                    const today = new Date().toISOString().split('T')[0];
                    const fechaIngresoAgregarInput = document.getElementById('fecha_ingreso_agregar');
                    if (fechaIngresoAgregarInput) {
                        fechaIngresoAgregarInput.value = today;
                    }
                }
            };
        }
    </script>
</body>
</html>
