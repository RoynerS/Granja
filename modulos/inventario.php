<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

include '../db.php';

// Obtener el filtro de categoría si existe
$tipo_filtro = isset($_GET['tipo']) ? $_GET['tipo'] : null;
$buscar_termino = isset($_GET['buscar']) ? $_GET['buscar'] : null;

// Construir y ejecutar la consulta según el filtro y búsqueda
$sql = "SELECT * FROM inventario";
$conditions = [];
$params = [];

if ($tipo_filtro && $tipo_filtro !== 'todos') {
    $conditions[] = "tipo = :tipo";
    $params[':tipo'] = $tipo_filtro;
}

if ($buscar_termino) {
    $conditions[] = "(nombre LIKE :buscar_nombre OR descripcion LIKE :buscar_descripcion)";
    $params[':buscar_nombre'] = "%$buscar_termino%";
    $params[':buscar_descripcion'] = "%$buscar_termino%";
}

if (count($conditions) > 0) {
    $sql .= " WHERE " . implode(' AND ', $conditions);
}
$sql .= " ORDER BY fecha_ingreso DESC";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Manejar excepciones de PDO
    $productos = []; // Asegurar que $productos sea un array vacío en caso de error
    error_log("Error de base de datos en inventario.php: " . $e->getMessage());
    // Podrías establecer un mensaje de error para el usuario aquí si lo deseas
}


// Manejar mensajes de sesión
$mensaje = isset($_SESSION['mensaje']) ? $_SESSION['mensaje'] : "";
unset($_SESSION['mensaje']);

// Función para mostrar el nombre de la categoría
function getNombreCategoria($tipo) {
    $nombres = [
        'alimento' => 'Alimento',
        'medicamento' => 'Medicamento',
        'herramienta' => 'Herramienta',
        'semilla' => 'Semilla',
        'fertilizante' => 'Fertilizante',
        'equipo' => 'Equipo',
        'otro' => 'Otro'
    ];
    return $nombres[$tipo] ?? $tipo;
}
?>

<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Inventario</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <script>
        // Configuración de Tailwind CSS para colores y fuentes personalizadas
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
        /* Importa la fuente Inter */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        /* Estilos personalizados para las insignias de categoría */
        .category-badge { padding: 0.25rem 0.5rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 500; }
        .category-alimento { background-color: #dcfce7; color: #166534; } /* Verde */
        .category-medicamento { background-color: #fee2e2; color: #991b1b; } /* Rojo */
        .category-herramienta { background-color: #fef9c3; color: #a16207; } /* Amarillo */
        .category-semilla { background-color: #e0f2fe; color: #075985; } /* Azul claro/Cyan */
        .category-fertilizante { background-color: #f3e8ff; color: #6b21a8; } /* Púrpura */
        .category-equipo { background-color: #fff7ed; color: #c2410c; } /* Naranja */
        .category-otro { background-color: #e5e7eb; color: #374151; } /* Gris por defecto */

        /* Oculta elementos con x-cloak de Alpine.js antes de que se inicialice */
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
                        <i class="fas fa-boxes text-primary-600 mr-2"></i> Gestión de Inventario
                    </h1>
                    <p class="text-gray-500 dark:text-gray-400">Administra los diferentes productos de tu inventario</p>
                </div>
                
                <div class="mt-4 md:mt-0 flex space-x-3">
                    <button @click="openModalAgregar = true" class="flex items-center justify-center px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg transition-colors duration-200">
                        <i class="bi bi-plus-lg mr-2"></i>
                        Agregar Producto
                    </button>
                </div>
            </div>

            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 space-y-4 md:space-y-0 md:space-x-4">
                <form action="inventario.php" method="GET" class="w-full md:w-auto flex-grow">
                    <div class="relative max-w-md">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="bi bi-search text-gray-400"></i>
                        </div>
                        <input type="text" name="buscar" class="block w-full pl-10 pr-12 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg focus:ring-primary-500 focus:border-primary-500" placeholder="Buscar por nombre o descripción" value="<?= htmlspecialchars($buscar_termino ?? '') ?>">
                        <div class="absolute inset-y-0 right-0 flex items-center">
                            <button type="submit" class="px-4 h-full bg-primary-600 text-white rounded-r-lg hover:bg-primary-700 focus:outline-none">
                                Buscar
                            </button>
                        </div>
                    </div>
                </form>

                <div class="flex space-x-2 overflow-x-auto pb-2 md:pb-0 flex-shrink-0">
                    <a href="inventario.php?<?= $buscar_termino ? 'buscar=' . htmlspecialchars($buscar_termino) . '&' : '' ?>tipo=todos" class="px-3 py-1 rounded-full transition-colors duration-200 <?= ($tipo_filtro === null || $tipo_filtro === 'todos') ? 'bg-primary-600 text-white' : 'border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' ?>">Todos</a>
                    <a href="inventario.php?<?= $buscar_termino ? 'buscar=' . htmlspecialchars($buscar_termino) . '&' : '' ?>tipo=alimento" class="px-3 py-1 rounded-full transition-colors duration-200 <?= ($tipo_filtro === 'alimento') ? 'bg-green-600 text-white' : 'border border-green-500 text-green-700 hover:bg-green-50' ?>">Alimentos</a>
                    <a href="inventario.php?<?= $buscar_termino ? 'buscar=' . htmlspecialchars($buscar_termino) . '&' : '' ?>tipo=medicamento" class="px-3 py-1 rounded-full transition-colors duration-200 <?= ($tipo_filtro === 'medicamento') ? 'bg-red-600 text-white' : 'border border-red-500 text-red-700 hover:bg-red-50' ?>">Medicamentos</a>
                    <a href="inventario.php?<?= $buscar_termino ? 'buscar=' . htmlspecialchars($buscar_termino) . '&' : '' ?>tipo=herramienta" class="px-3 py-1 rounded-full transition-colors duration-200 <?= ($tipo_filtro === 'herramienta') ? 'bg-yellow-600 text-white' : 'border border-yellow-500 text-yellow-700 hover:bg-yellow-50' ?>">Herramientas</a>
                    <a href="inventario.php?<?= $buscar_termino ? 'buscar=' . htmlspecialchars($buscar_termino) . '&' : '' ?>tipo=semilla" class="px-3 py-1 rounded-full transition-colors duration-200 <?= ($tipo_filtro === 'semilla') ? 'bg-cyan-600 text-white' : 'border border-cyan-500 text-cyan-700 hover:bg-cyan-50' ?>">Semillas</a>
                    <a href="inventario.php?<?= $buscar_termino ? 'buscar=' . htmlspecialchars($buscar_termino) . '&' : '' ?>tipo=fertilizante" class="px-3 py-1 rounded-full transition-colors duration-200 <?= ($tipo_filtro === 'fertilizante') ? 'bg-purple-600 text-white' : 'border border-purple-500 text-purple-700 hover:bg-purple-50' ?>">Fertilizantes</a>
                    <a href="inventario.php?<?= $buscar_termino ? 'buscar=' . htmlspecialchars($buscar_termino) . '&' : '' ?>tipo=equipo" class="px-3 py-1 rounded-full transition-colors duration-200 <?= ($tipo_filtro === 'equipo') ? 'bg-orange-600 text-white' : 'border border-orange-500 text-orange-700 hover:bg-orange-50' ?>">Equipos</a>
                    <a href="inventario.php?<?= $buscar_termino ? 'buscar=' . htmlspecialchars($buscar_termino) . '&' : '' ?>tipo=otro" class="px-3 py-1 rounded-full transition-colors duration-200 <?= ($tipo_filtro === 'otro') ? 'bg-gray-600 text-white' : 'border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' ?>">Otro</a>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-soft overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Nombre</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Categoría</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Descripción</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Cantidad</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Precio</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Ingreso</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <?php if (empty($productos)): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                        No se encontraron productos registrados
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($productos as $producto): ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($producto['nombre']) ?></div></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php 
                                                $badgeClasses = [
                                                    'alimento' => 'bg-green-100 text-green-800',
                                                    'medicamento' => 'bg-red-100 text-red-800',
                                                    'herramienta' => 'bg-yellow-100 text-yellow-800',
                                                    'semilla' => 'bg-cyan-100 text-cyan-800',
                                                    'fertilizante' => 'bg-purple-100 text-purple-800',
                                                    'equipo' => 'bg-orange-100 text-orange-800',
                                                    'otro' => 'bg-gray-100 text-gray-800'
                                                ];
                                                $badgeClass = $badgeClasses[$producto['tipo']] ?? 'bg-gray-100 text-gray-800';
                                            ?>
                                            <span class="category-badge <?= $badgeClass ?>">
                                                <?= getNombreCategoria($producto['tipo']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-normal text-sm text-gray-500 dark:text-gray-400 max-w-xs"><?= htmlspecialchars($producto['descripcion']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm text-gray-900 dark:text-white"><?= htmlspecialchars($producto['cantidad']) ?></div></td>
                                        <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm text-gray-900 dark:text-white">$<?= number_format($producto['precio'], 2) ?></div></td>
                                        <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm text-gray-500 dark:text-gray-400"><?= htmlspecialchars($producto['fecha_ingreso']) ?></div></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-2">
                                                <button type="button" @click="openModalEditar = true; productoParaEditar = JSON.parse('<?= htmlspecialchars(json_encode($producto), ENT_QUOTES, 'UTF-8') ?>'); inicializarFormularioEdicion();" class="text-yellow-600 dark:text-yellow-400 hover:text-yellow-900 dark:hover:text-yellow-500">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <button type="button" @click="confirmDelete(<?= $producto['id'] ?>)" class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-500">
                                                    <i class="bi bi-trash"></i>
                                                </button>
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
            <div x-show="openModalAgregar" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" @click="openModalAgregar = false; resetNewProductoForm()"></div>
            
            <div x-show="openModalAgregar" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle w-full max-w-lg sm:max-w-2xl"> 
                <form action="procesar_producto.php" method="POST">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-primary-100 dark:bg-primary-900 sm:mx-0 sm:h-10 sm:w-10">
                                <i class="fas fa-box text-primary-600 dark:text-primary-300"></i>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="modal-agregar-title">
                                    Agregar Nuevo Producto
                                </h3>
                                <div class="mt-4 space-y-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label for="nombre_agregar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nombre:</label>
                                            <input type="text" name="nombre" id="nombre_agregar" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" required>
                                        </div>
                                        <div>
                                            <label for="tipo_agregar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Categoría:</label>
                                            <select name="tipo" id="tipo_agregar" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" required>
                                                <option value="">Seleccionar Categoría</option>
                                                <option value="alimento">Alimento</option>
                                                <option value="medicamento">Medicamento</option>
                                                <option value="herramienta">Herramienta</option>
                                                <option value="semilla">Semilla</option>
                                                <option value="fertilizante">Fertilizante</option>
                                                <option value="equipo">Equipo</option>
                                                <option value="otro">Otro</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div>
                                        <label for="descripcion_agregar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Descripción:</label>
                                        <textarea name="descripcion" id="descripcion_agregar" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm h-24"></textarea>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label for="cantidad_agregar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Cantidad:</label>
                                            <input type="number" name="cantidad" id="cantidad_agregar" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" min="0" required>
                                        </div>
                                        <div>
                                            <label for="precio_agregar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Precio:</label>
                                            <input type="number" name="precio" id="precio_agregar" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" min="0" required>
                                        </div>
                                    </div>
                                    <div>
                                        <label for="fecha_ingreso_agregar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Fecha de Ingreso:</label>
                                        <input type="date" name="fecha_ingreso" id="fecha_ingreso_agregar" x-model="newProducto.fecha_ingreso" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Guardar Producto
                        </button>
                        <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-white hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm" @click="openModalAgregar = false; resetNewProductoForm()">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div x-cloak x-show="openModalEditar" x-transition class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-editar-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen p-4"> 
            <div x-show="openModalEditar" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" @click="openModalEditar = false; productoParaEditar = null;"></div>
            
            <div x-show="openModalEditar" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle w-full max-w-lg sm:max-w-2xl"> 
                <form action="procesar_edicion_producto.php" method="POST" x-show="productoParaEditar"> 
                    <input type="hidden" name="id" :value="productoParaEditar ? productoParaEditar.id : ''">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 dark:bg-yellow-900 sm:mx-0 sm:h-10 sm:w-10">
                                <i class="bi bi-pencil-fill text-yellow-600 dark:text-yellow-300"></i>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="modal-editar-title">
                                    Editar Producto
                                </h3>
                                <div class="mt-4 space-y-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label for="nombre_editar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nombre:</label>
                                            <input type="text" name="nombre" id="nombre_editar" :value="productoParaEditar ? productoParaEditar.nombre : ''" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" required>
                                        </div>
                                        <div>
                                            <label for="tipo_editar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Categoría:</label>
                                            <select name="tipo" id="tipo_editar" :value="productoParaEditar ? productoParaEditar.tipo : ''" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" required>
                                                <option value="">Seleccionar Categoría</option>
                                                <option value="alimento">Alimento</option>
                                                <option value="medicamento">Medicamento</option>
                                                <option value="herramienta">Herramienta</option>
                                                <option value="semilla">Semilla</option>
                                                <option value="fertilizante">Fertilizante</option>
                                                <option value="equipo">Equipo</option>
                                                <option value="otro">Otro</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div>
                                        <label for="descripcion_editar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Descripción:</label>
                                        <textarea name="descripcion" id="descripcion_editar" :value="productoParaEditar ? productoParaEditar.descripcion : ''" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm h-24"></textarea>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label for="cantidad_editar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Cantidad:</label>
                                            <input type="number" name="cantidad" id="cantidad_editar" :value="productoParaEditar ? productoParaEditar.cantidad : ''" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" min="0">
                                        </div>
                                        <div>
                                            <label for="precio_editar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Precio:</label>
                                            <input type="number" name="precio" id="precio_editar" :value="productoParaEditar ? productoParaEditar.precio : ''" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" min="0">
                                        </div>
                                    </div>
                                    <div>
                                        <label for="fecha_ingreso_editar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Fecha de Ingreso:</label>
                                        <input type="date" name="fecha_ingreso" id="fecha_ingreso_editar" :value="productoParaEditar ? productoParaEditar.fecha_ingreso : ''" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Actualizar Producto
                        </button>
                        <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-white hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm" @click="openModalEditar = false; productoParaEditar = null;">
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
                                Eliminar Producto
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    ¿Estás seguro de que quieres eliminar este producto? Esta acción no se puede deshacer.
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
                // Alpine.js state variables
                openModalAgregar: false,
                openModalEditar: false,
                openConfirmModal: false,
                alpineMessage: '', // Mensajes generados por Alpine.js (no PHP)
                productoToDeleteId: null,
                
                // Objeto para el nuevo producto (formulario de agregar)
                newProducto: {
                    nombre: '',
                    tipo: '',
                    descripcion: '',
                    cantidad: 0,
                    precio: 0.00,
                    fecha_ingreso: new Date().toISOString().slice(0, 10) // Set to current date
                },

                // Objeto para el producto a editar (formulario de edición)
                productoParaEditar: null,

                // Función para cambiar el tema (claro/oscuro)
                toggleTheme() {
                    if (document.documentElement.classList.contains('dark')) {
                        document.documentElement.classList.remove('dark');
                        localStorage.setItem('theme', 'light');
                    } else {
                        document.documentElement.classList.add('dark');
                        localStorage.setItem('theme', 'dark');
                    }
                },

                // Inicializa el tema al cargar la página
                init() {
                    if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                        document.documentElement.classList.add('dark');
                    } else {
                        document.documentElement.classList.remove('dark');
                    }
                },

                // Obtiene la clase CSS para la insignia de categoría
                getCategoryBadgeClass(tipo) {
                    const typeClass = tipo.toLowerCase().replace(' ', '-');
                    const validTypes = ['alimento', 'medicamento', 'herramienta', 'semilla', 'fertilizante', 'equipo', 'otro'];
                    if (validTypes.includes(typeClass)) {
                        return `category-${typeClass}`;
                    }
                    return 'category-otro'; // Fallback a 'otro' si no coincide
                },

                // Función para obtener el nombre legible de la categoría
                getNombreCategoria(tipo) {
                    const nombres = {
                        'alimento': 'Alimento',
                        'medicamento': 'Medicamento',
                        'herramienta': 'Herramienta',
                        'semilla': 'Semilla',
                        'fertilizante': 'Fertilizante',
                        'equipo': 'Equipo',
                        'otro': 'Otro'
                    };
                    return nombres[tipo] || tipo;
                },

                // Resetea el formulario de nuevo producto
                resetNewProductoForm() {
                    this.newProducto = {
                        nombre: '',
                        tipo: '',
                        descripcion: '',
                        cantidad: 0,
                        precio: 0.00,
                        fecha_ingreso: new Date().toISOString().slice(0, 10) // Reset to current date
                    };
                },

                // Inicializa el formulario de edición con los datos del producto seleccionado
                inicializarFormularioEdicion() {
                    // El bindeo de x-model en los inputs del modal de edición se encargará de esto.
                    // Asegúrate de que productoParaEditar ya ha sido asignado al hacer clic en editar.
                    console.log('Producto para editar:', this.productoParaEditar);
                },

                // Abre el modal de confirmación de eliminación
                confirmDelete(id) {
                    this.productoToDeleteId = id;
                    this.openConfirmModal = true;
                },

                // Redirige para eliminar el producto
                deleteConfirmed() {
                    if (this.productoToDeleteId) {
                        window.location.href = `eliminar_producto.php?id=${this.productoToDeleteId}`;
                    }
                },
                
                // Formatea la fecha para mostrarla en la tabla
                formatDate(dateString) {
                    if (!dateString) return '';
                    try {
                        const date = new Date(dateString);
                        const options = { year: 'numeric', month: '2-digit', day: '2-digit' };
                        return date.toLocaleDateString('es-ES', options);
                    } catch (e) {
                        console.error("Error formatting date:", dateString, e);
                        return dateString; // Retorna el string original si hay un error
                    }
                }
            }));
        });
    </script>
</body>
</html>
