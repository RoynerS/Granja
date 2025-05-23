<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

include 'db.php'; // Asegúrate que la ruta a tu archivo db.php sea correcta

$nombre = $_SESSION['nombre'];
$rol = $_SESSION['rol'];

// --- Obtener datos dinámicos para las tarjetas de resumen ---
$total_animales = 0;
$total_inventario = 0;
$total_tareas_pendientes = 0; // Nuevo total para tareas pendientes
$total_tareas_completadas = 0; // Nuevo total para tareas completadas

try {
    // Contar animales
    $stmt_count_animales = $conn->query("SELECT COUNT(*) AS total FROM animales");
    $result_animales = $stmt_count_animales->fetch(PDO::FETCH_ASSOC);
    $total_animales = $result_animales['total'];

    // Contar ítems de inventario (distintos ítems, no cantidad total)
    $stmt_count_inventario = $conn->query("SELECT COUNT(*) AS total FROM inventario");
    $result_inventario = $stmt_count_inventario->fetch(PDO::FETCH_ASSOC);
    $total_inventario = $result_inventario['total'];

    // Contar tareas pendientes
    $sql_pendientes = "SELECT COUNT(*) AS total FROM tareas WHERE completado = 0";
    $params_pendientes = [];
    if ($rol === 'veterinario') {
        $sql_pendientes .= " AND usuario_id = :session_user_id";
        $params_pendientes[':session_user_id'] = $_SESSION['usuario_id'];
    }
    $stmt_count_pendientes = $conn->prepare($sql_pendientes);
    $stmt_count_pendientes->execute($params_pendientes);
    $result_pendientes = $stmt_count_pendientes->fetch(PDO::FETCH_ASSOC);
    $total_tareas_pendientes = $result_pendientes['total'];

    // Contar tareas completadas
    $sql_completadas = "SELECT COUNT(*) AS total FROM tareas WHERE completado = 1";
    $params_completadas = [];
    if ($rol === 'veterinario') {
        $sql_completadas .= " AND usuario_id = :session_user_id";
        $params_completadas[':session_user_id'] = $_SESSION['usuario_id'];
    }
    $stmt_count_completadas = $conn->prepare($sql_completadas);
    $stmt_count_completadas->execute($params_completadas);
    $result_completadas = $stmt_count_completadas->fetch(PDO::FETCH_ASSOC);
    $total_tareas_completadas = $result_completadas['total'];
    
} catch (PDOException $e) {
    error_log("Error al obtener datos de resumen: " . $e->getMessage());
    // Los totales se mantendrán en 0 si hay un error
}


// --- Obtener Actividad Reciente Dinámica (Tareas Pendientes y Completadas) ---
$actividad_reciente = [];

try {
    // Obtener tareas (pendientes y completadas)
    $sql_actividad = "SELECT t.id, t.descripcion, t.fecha, t.hora, t.completado, u.nombre as nombre_usuario, 'tarea' as tipo_actividad 
                      FROM tareas t LEFT JOIN usuarios u ON t.usuario_id = u.id";
    $params_actividad = [];

    // Si el rol es veterinario, filtrar por sus tareas
    if ($rol === 'veterinario') {
        $sql_actividad .= " WHERE t.usuario_id = :session_user_id";
        $params_actividad[':session_user_id'] = $_SESSION['usuario_id'];
    }
    
    $sql_actividad .= " ORDER BY t.fecha DESC, t.hora DESC LIMIT 5"; // Ordenar por las más recientes primero

    $stmt_actividad = $conn->prepare($sql_actividad);
    $stmt_actividad->execute($params_actividad);
    while ($row = $stmt_actividad->fetch(PDO::FETCH_ASSOC)) {
        $estado_text = $row['completado'] ? 'Completada' : 'Pendiente';
        $icon_class = $row['completado'] ? 'bi-list-check' : 'bi-hourglass-split';
        $color_class = $row['completado'] ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
        
        $actividad_reciente[] = [
            'descripcion' => 'Tarea ' . strtolower($estado_text),
            'detalle' => htmlspecialchars($row['descripcion']),
            'fecha' => $row['fecha'],
            'hora' => substr($row['hora'], 0, 5),
            'usuario' => htmlspecialchars($row['nombre_usuario'] ?? 'N/A'),
            'estado' => $estado_text,
            'icon' => $icon_class,
            'color_class' => $color_class
        ];
    }

} catch (PDOException $e) {
    error_log("Error al obtener actividad reciente: " . $e->getMessage());
    // Puedes mostrar un mensaje de error en la UI si lo deseas
}

?>

<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Control - Granja</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
                            900: '#14532d',
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
        
        .menu-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            backface-visibility: hidden;
        }
        
        .menu-card:hover {
            transform: translateY(-4px) scale(1.01);
        }
        
        .menu-card.animales {
            background: linear-gradient(135deg, #d4edda, #a7e4b8);
        }
        .menu-card.inventario {
            background: linear-gradient(135deg, #cce5ff, #99ccff);
        }
        .menu-card.tareas {
            background: linear-gradient(135deg, #fff3cd, #ffe69c);
        }
        .menu-card.reportes {
            background: linear-gradient(135deg, #f8d7da, #f5b5ba);
        }
        .menu-card.salir {
            background: linear-gradient(135deg, #e2e3e5, #c8cbce);
        }
        
        .nav-link {
            position: relative;
            overflow: hidden;
        }
        
        .nav-link::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 100%;
            height: 2px;
            background-color: currentColor;
            transform: scaleX(0);
            transform-origin: right;
            transition: transform 0.3s ease;
        }
        
        .nav-link:hover::after,
        .nav-link.active::after {
            transform: scaleX(1);
            transform-origin: left;
        }
    </style>
</head>
<body class="h-full bg-gray-50 font-sans text-gray-800 dark:bg-gray-900 dark:text-gray-100 transition-colors duration-200 flex flex-col min-h-screen">
    <header class="bg-white dark:bg-gray-800 shadow-sm sticky top-0 z-40">
        <div class="px-6 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <div class="flex items-center">
                    <h1 class="text-xl font-bold text-gray-800 dark:text-white">Panel de Control</h1>
                </div>
                
                <nav class="hidden md:flex items-center space-x-1">
                    <a href="#" class="nav-link px-3 py-2 text-sm font-medium text-primary-600 dark:text-primary-400 active">
                        <i class="bi bi-speedometer2 mr-1"></i> Inicio
                    </a>
                    <a href="./modulos/animales.php" class="nav-link px-3 py-2 text-sm font-medium text-primary-600 dark:text-primary-400">
                        <i class="bi bi-egg-fried mr-1"></i> Animales
                    </a>
                    <a href="./modulos/inventario.php" class="nav-link px-3 py-2 text-sm font-medium text-primary-600 dark:text-primary-400">
                        <i class="bi bi-box-seam mr-1"></i> Inventario
                    </a>
                    <a href="./modulos/tareas_veterinario.php" class="nav-link px-3 py-2 text-sm font-medium text-primary-600 dark:text-primary-400">
                        <i class="bi bi-list-check mr-1"></i> Tareas
                    </a>
                    <?php if ($rol !== 'veterinario'): // Solo mostrar si NO es veterinario ?>
                    <a href="reportes.php" class="nav-link px-3 py-2 text-sm font-medium text-primary-600 dark:text-primary-400">
                        <i class="bi bi-graph-up mr-1"></i> Reportes
                    </a>
                    <?php endif; ?>
                </nav>
            </div>
            
            <div class="flex items-center space-x-4">
                <div class="hidden md:flex items-center space-x-4">
                    <div class="relative">
                        <button id="theme-toggle" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none">
                            <i class="bi bi-sun-fill text-yellow-500 dark:hidden"></i>
                            <i class="bi bi-moon-fill text-blue-400 hidden dark:inline"></i>
                        </button>
                    </div>
                    
                    <div class="flex items-center bg-gray-100 dark:bg-gray-700 px-3 py-1 rounded-full">
                        <i class="bi bi-calendar mr-2 text-gray-500 dark:text-gray-300"></i>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200"><?php echo date('d/m/Y'); ?></span>
                    </div>
                </div>
                
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="flex items-center space-x-2 focus:outline-none">
                        <div class="w-9 h-9 rounded-full bg-primary-600 flex items-center justify-center text-white font-semibold">
                            <?php echo strtoupper(substr($nombre, 0, 1)); ?>
                        </div>
                        <span class="hidden md:inline text-sm font-medium"><?php echo htmlspecialchars($nombre); ?></span>
                    </button>
                    
                    <div x-show="open" @click.away="open = false" 
                         class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-md shadow-lg py-1 z-50">
                        <div class="px-4 py-2 border-b border-gray-200 dark:border-gray-700">
                            <p class="text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($nombre); ?></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($rol); ?></p>
                        </div>
                        <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                            <i class="bi bi-box-arrow-right mr-2"></i> Cerrar sesión
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="md:hidden bg-gray-50 dark:bg-gray-700 px-4 py-2">
            <div class="flex space-x-4 overflow-x-auto">
                <a href="#" class="nav-link px-2 py-1 text-sm font-medium text-primary-600 dark:text-primary-400 whitespace-nowrap active">
                    <i class="bi bi-speedometer2 mr-1"></i> Inicio
                </a>
                <a href="./modulos/animales.php" class="nav-link px-2 py-1 text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap hover:text-primary-600 dark:hover:text-primary-400">
                    <i class="bi bi-egg-fried mr-1"></i> Animales
                </a>
                <a href="./modulos/inventario.php" class="nav-link px-2 py-1 text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap hover:text-primary-600 dark:hover:text-primary-400">
                    <i class="bi bi-box-seam mr-1"></i> Inventario
                </a>
                <a href="./modulos/tareas_veterinario.php" class="nav-link px-2 py-1 text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap hover:text-primary-600 dark:hover:text-primary-400">
                    <i class="bi bi-list-check mr-1"></i> Tareas
                </a>
                <?php if ($rol !== 'veterinario'): // Solo mostrar si NO es veterinario ?>
                <a href="reportes.php" class="nav-link px-2 py-1 text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap hover:text-primary-600 dark:hover:text-primary-400">
                    <i class="bi bi-graph-up mr-1"></i> Reportes
                </a>
                <?php endif; ?>
            </div>
        </div>
    </header>
    
    <div class="min-h-full flex-grow">
        <main class="p-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-soft p-6 mb-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-gray-800 dark:text-white">Bienvenido, <?php echo htmlspecialchars($nombre); ?></h2>
                        <p class="text-gray-600 dark:text-gray-400">Aquí puedes gestionar todas las actividades de tu granja</p>
                    </div>
                    <div class="mt-4 md:mt-0">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200">
                            <i class="bi bi-person-badge mr-1"></i> <?php echo htmlspecialchars($rol); ?>
                        </span>
                    </div>
                </div>
            </div>

            <h2 class="text-lg font-semibold mb-4 text-gray-800 dark:text-white">Resumen General</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-soft p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Animales</p>
                            <h3 class="text-2xl font-bold mt-1"><?= number_format($total_animales) ?></h3>
                        </div>
                        <div class="p-2 rounded-lg bg-green-50 dark:bg-green-900/30 text-green-600 dark:text-green-300">
                            <i class="bi bi-egg-fried text-lg"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <div class="flex items-center text-xs text-green-600 dark:text-green-400">
                            <i class="bi bi-arrow-up-short mr-1"></i>
                            <span>Total de animales</span>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-soft p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tareas Pendientes</p>
                            <h3 class="text-2xl font-bold mt-1"><?= number_format($total_tareas_pendientes) ?></h3>
                        </div>
                        <div class="p-2 rounded-lg bg-yellow-50 dark:bg-yellow-900/30 text-yellow-600 dark:text-yellow-300">
                            <i class="bi bi-hourglass-split text-lg"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <div class="flex items-center text-xs text-yellow-600 dark:text-yellow-400">
                            <i class="bi bi-exclamation-triangle mr-1"></i>
                            <span>Tareas por completar</span>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-soft p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tareas Completadas</p>
                            <h3 class="text-2xl font-bold mt-1"><?= number_format($total_tareas_completadas) ?></h3>
                        </div>
                        <div class="p-2 rounded-lg bg-green-50 dark:bg-green-900/30 text-green-600 dark:text-green-300">
                            <i class="bi bi-check-circle text-lg"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <div class="flex items-center text-xs text-green-600 dark:text-green-400">
                            <i class="bi bi-arrow-up-short mr-1"></i>
                            <span>Tareas finalizadas</span>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-soft p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Inventario</p>
                            <h3 class="text-2xl font-bold mt-1"><?= number_format($total_inventario) ?></h3>
                        </div>
                        <div class="p-2 rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-300">
                            <i class="bi bi-box-seam text-lg"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <div class="flex items-center text-xs text-yellow-600 dark:text-yellow-400">
                            <i class="bi bi-exclamation-triangle mr-1"></i>
                            <span>Ítems en inventario</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <h2 class="text-lg font-semibold mb-4 text-gray-800 dark:text-white">Actividad Reciente</h2>
            <div class="mb-8">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-soft overflow-hidden">
                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                        <?php if (empty($actividad_reciente)): ?>
                            <div class="p-4 text-center text-gray-500 dark:text-gray-400">
                                No hay actividad reciente para mostrar.
                            </div>
                        <?php else: ?>
                            <?php foreach ($actividad_reciente as $actividad): ?>
                                <div class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0 h-10 w-10 <?= $actividad['color_class'] ?> rounded-full flex items-center justify-center mr-3">
                                            <i class="bi <?= $actividad['icon'] ?> text-lg"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900 dark:text-white"><?= $actividad['descripcion'] ?></p>
                                            <p class="text-sm text-gray-500 dark:text-gray-400 truncate"><?= $actividad['detalle'] ?></p>
                                            <div class="mt-1 flex items-center text-xs text-gray-500 dark:text-gray-400">
                                                <?php if (!empty($actividad['usuario'])): ?>
                                                    <span><?= $actividad['usuario'] ?></span>
                                                    <span class="mx-1">•</span>
                                                <?php endif; ?>
                                                <span><?= date('d/m/Y', strtotime($actividad['fecha'])) ?></span>
                                                <?php if (!empty($actividad['hora'])): ?>
                                                    <span class="mx-1">•</span>
                                                    <span><?= $actividad['hora'] ?></span>
                                                <?php endif; ?>
                                                <span class="ml-auto px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $actividad['color_class'] ?>">
                                                    <?= $actividad['estado'] ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <h2 class="text-lg font-semibold mb-4 text-gray-800 dark:text-white">Acciones Rápidas</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4 mb-8">
                <a href="./modulos/animales.php" class="menu-card animales rounded-xl shadow-soft overflow-hidden no-underline">
                    <div class="p-4 text-center h-full flex flex-col items-center justify-center">
                        <div class="w-12 h-12 rounded-full bg-white bg-opacity-50 flex items-center justify-center mb-3">
                            <i class="bi bi-egg-fried text-green-600 text-2xl"></i>
                        </div>
                        <h3 class="text-base font-semibold mb-1 text-gray-800">Animales</h3>
                        <p class="text-xs text-gray-600">Gestionar ganado</p>
                    </div>
                </a>
                
                <a href="./modulos/inventario.php" class="menu-card inventario rounded-xl shadow-soft overflow-hidden no-underline">
                    <div class="p-4 text-center h-full flex flex-col items-center justify-center">
                        <div class="w-12 h-12 rounded-full bg-white bg-opacity-50 flex items-center justify-center mb-3">
                            <i class="bi bi-box-seam text-blue-600 text-2xl"></i>
                        </div>
                        <h3 class="text-base font-semibold mb-1 text-gray-800">Inventario</h3>
                        <p class="text-xs text-gray-600">Control de suministros</p>
                    </div>
                </a>
                
                <a href="./modulos/tareas_veterinario.php" class="menu-card tareas rounded-xl shadow-soft overflow-hidden no-underline">
                    <div class="p-4 text-center h-full flex flex-col items-center justify-center">
                        <div class="w-12 h-12 rounded-full bg-white bg-opacity-50 flex items-center justify-center mb-3">
                            <i class="bi bi-list-check text-yellow-600 text-2xl"></i>
                        </div>
                        <h3 class="text-base font-semibold mb-1 text-gray-800">Tareas</h3>
                        <p class="text-xs text-gray-600">Actividades diarias</p>
                    </div>
                </a>
                
                <?php if ($rol !== 'veterinario'): // Solo mostrar si NO es veterinario ?>
                <a href="reportes.php" class="menu-card reportes rounded-xl shadow-soft overflow-hidden no-underline">
                    <div class="p-4 text-center h-full flex flex-col items-center justify-center">
                        <div class="w-12 h-12 rounded-full bg-white bg-opacity-50 flex items-center justify-center mb-3">
                            <i class="bi bi-graph-up text-red-600 text-2xl"></i>
                        </div>
                        <h3 class="text-base font-semibold mb-1 text-gray-800">Reportes</h3>
                        <p class="text-xs text-gray-600">Estadísticas</p>
                    </div>
                </a>
                <?php endif; ?>
                
                <a href="logout.php" class="menu-card salir rounded-xl shadow-soft overflow-hidden no-underline">
                    <div class="p-4 text-center h-full flex flex-col items-center justify-center">
                        <div class="w-12 h-12 rounded-full bg-white bg-opacity-50 flex items-center justify-center mb-3">
                            <i class="bi bi-box-arrow-right text-gray-600 text-2xl"></i>
                        </div>
                        <h3 class="text-base font-semibold mb-1 text-gray-800">Salir</h3>
                        <p class="text-xs text-gray-600">Cerrar sesión</p>
                    </div>
                </a>
            </div>
            
        </main>
        
        <footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 py-4 px-6 mt-auto">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="text-sm text-gray-500 dark:text-gray-400 mb-2 md:mb-0">
                    © <?php echo date('Y'); ?> Granja App - Sistema de Gestión
                </div>
                <div class="flex space-x-4">
                    <a href="#" class="text-sm text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400">Términos</a>
                    <a href="#" class="text-sm text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400">Privacidad</a>
                    <a href="#" class="text-sm text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400">Ayuda</a>
                </div>
            </div>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script>
        // Dark mode toggle
        const themeToggle = document.getElementById('theme-toggle');
        const html = document.documentElement;
        
        themeToggle?.addEventListener('click', () => {
            html.classList.toggle('dark');
            localStorage.setItem('theme', html.classList.contains('dark') ? 'dark' : 'light');
        });
        
        // Check for saved theme preference
        if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            html.classList.add('dark');
        } else {
            html.classList.remove('dark');
        }
    </script>
</body>
</html>
