<?php
session_start();

// Redirige al usuario a la página de inicio de sesión si no está autenticado
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
$total_tareas_pendientes = 0;
$total_tareas_completadas = 0;
$total_usuarios = 0;

try {
    // Contar animales
    $stmt_count_animales = $conn->query("SELECT COUNT(*) AS total FROM animales");
    $result_animales = $stmt_count_animales->fetch(PDO::FETCH_ASSOC);
    $total_animales = $result_animales['total'];

    // Contar ítems de inventario (distintos ítems, no cantidad total)
    $stmt_count_inventario = $conn->query("SELECT COUNT(*) AS total FROM inventario");
    $result_inventario = $stmt_count_inventario->fetch(PDO::FETCH_ASSOC);
    $total_inventario = $result_inventario['total'];

    // Contar tareas pendientes (filtrado por usuario si es veterinario)
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

    // Contar tareas completadas (filtrado por usuario si es veterinario)
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

    // Contar usuarios (solo para administradores)
    if ($rol === 'administrador') {
        $stmt_count_usuarios = $conn->query("SELECT COUNT(*) AS total FROM usuarios");
        $result_usuarios = $stmt_count_usuarios->fetch(PDO::FETCH_ASSOC);
        $total_usuarios = $result_usuarios['total'];
    }
    
} catch (PDOException $e) {
    error_log("Error al obtener datos de resumen: " . $e->getMessage());
    // Los totales se mantendrán en 0 si hay un error en la base de datos
}


// --- Obtener Actividad Reciente Dinámica (Tareas Pendientes y Completadas) ---
$actividad_reciente = [];

try {
    // Consulta para obtener tareas recientes, uniendo con usuarios para el nombre
    $sql_actividad = "SELECT t.id, t.descripcion, t.fecha, t.hora, t.completado, u.nombre as nombre_usuario, 'tarea' as tipo_actividad 
                      FROM tareas t LEFT JOIN usuarios u ON t.usuario_id = u.id";
    $params_actividad = [];

    // Si el rol es veterinario, filtrar por sus tareas específicas
    if ($rol === 'veterinario') {
        $sql_actividad .= " WHERE t.usuario_id = :session_user_id";
        $params_actividad[':session_user_id'] = $_SESSION['usuario_id'];
    }
    
    $sql_actividad .= " ORDER BY t.fecha DESC, t.hora DESC LIMIT 5"; // Obtener las 5 actividades más recientes

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
            'hora' => substr($row['hora'], 0, 5), // Formato HH:MM
            'usuario' => htmlspecialchars($row['nombre_usuario'] ?? 'N/A'),
            'estado' => $estado_text,
            'icon' => $icon_class,
            'color_class' => $color_class
        ];
    }

} catch (PDOException $e) {
    error_log("Error al obtener actividad reciente: " . $e->getMessage());
    // En caso de error, la lista de actividad_reciente estará vacía
}

// Manejar mensajes de sesión (ej. de éxito/error de operaciones anteriores)
$mensaje = isset($_SESSION['mensaje']) ? $_SESSION['mensaje'] : "";
unset($_SESSION['mensaje']); // Limpia el mensaje después de mostrarlo
?>

<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Control - Granja</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="shortcut icon" href="./uploads/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <script>
        // Función para aplicar el tema guardado o la preferencia del sistema
        function applyTheme() {
            const savedTheme = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            
            if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
                document.documentElement.classList.add('dark');
                document.documentElement.style.colorScheme = 'dark'; // Sugiere al navegador el esquema de color
            } else {
                document.documentElement.classList.remove('dark');
                document.documentElement.style.colorScheme = 'light';
            }
        }
        applyTheme(); // Ejecuta la función al cargar el script
    </script>

    <script>
        tailwind.config = {
            darkMode: 'class', // Habilita el modo oscuro basado en la clase 'dark' en el HTML
            theme: {
                extend: {
                    colors: {
                        primary: { // Paleta de colores primaria (verde)
                            50: '#f0fdf4', 100: '#dcfce7', 200: '#bbf7d0', 300: '#86efac', 
                            400: '#4ade80', 500: '#22c55e', 600: '#16a34a', 700: '#15803d', 
                            800: '#166534', 900: '#14532d', DEFAULT: '#22c55e'
                        },
                        secondary: { // Paleta de colores secundaria (amarillo)
                            50: '#fffbeb', 100: '#fef3c7', 200: '#fde68a', 300: '#fcd34d', 
                            400: '#fbbf24', 500: '#f59e0b', 600: '#d97706', 700: '#b45309', 
                            800: '#92400e', 900: '#78350f'
                        },
                        gray: { // Paleta de colores grises
                            50: '#f9fafb', 100: '#f3f4f6', 200: '#e5e7eb', 300: '#d1d5db',
                            400: '#9ca3af', 500: '#6b7280', 600: '#4b5563', 700: '#374151',
                            800: '#1f2937', 900: '#111827'
                        },
                        purple: { // Nueva paleta de colores para usuarios
                            50: '#f5f3ff', 100: '#ede9fe', 200: '#ddd6fe', 300: '#c4b5fd',
                            400: '#a78bfa', 500: '#8b5cf6', 600: '#7c3aed', 700: '#6d28d9',
                            800: '#5b21b6', 900: '#4c1d95'
                        },
                        blue: { // Nueva paleta de colores para inventario
                            50: '#eff6ff', 100: '#dbeafe', 200: '#bfdbfe', 300: '#93c5fd',
                            400: '#60a5fa', 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8',
                            800: '#1e40af', 900: '#1e3a8a'
                        },
                        red: { // Nueva paleta de colores para reportes
                            50: '#fef2f2', 100: '#fee2e2', 200: '#fecaca', 300: '#fca5a5',
                            400: '#f87171', 500: '#ef4444', 600: '#dc2626', 700: '#b91c1c',
                            800: '#991b1b', 900: '#7f1d1d'
                        },
                        teal: { // Nueva paleta de colores para equipo
                            50: '#f0fdfa', 100: '#ccfbf1', 200: '#99f6e4', 300: '#5eead4',
                            400: '#2dd4bf', 500: '#14b8a6', 600: '#0d9488', 700: '#0f766e',
                            800: '#115e59', 900: '#134e4a'
                        }
                    },
                    fontFamily: { // Fuente personalizada
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                    },
                    boxShadow: { // Sombras personalizadas
                        'soft': '0 4px 20px -2px rgba(0, 0, 0, 0.08)',
                        'soft-lg': '0 10px 30px -3px rgba(0, 0, 0, 0.12)',
                    }
                }
            }
        }
    </script>
    <style>
        /* Importa la fuente Inter de Google Fonts */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        /* Previene el "flash" de contenido sin estilo en el modo oscuro */
        html {
            color-scheme: light dark;
        }
        
        /* Transiciones suaves para cambios de tema */
        body {
            transition: background-color 0.2s ease, color 0.2s ease;
        }
        
        /* Estilos para las tarjetas de menú con gradientes y efectos de hover */
        .menu-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            backface-visibility: hidden; /* Evita parpadeos en algunas transiciones */
            display: flex; /* Asegura que el contenido esté centrado verticalmente */
            align-items: center; /* Centra verticalmente */
            justify-content: center; /* Centra horizontalmente */
            text-decoration: none; /* Elimina el subrayado de los enlaces */
        }
        
        .menu-card:hover {
            transform: translateY(-4px) scale(1.01); /* Efecto de elevación y ligero aumento de tamaño */
            box-shadow: 0 10px 20px rgba(0,0,0,0.15); /* Sombra más pronunciada al hacer hover */
        }
        
        /* Gradientes de fondo para cada tipo de tarjeta de menú */
        .menu-card.animales { background: linear-gradient(135deg, #d4edda, #a7e4b8); } /* Verde claro */
        .menu-card.inventario { background: linear-gradient(135deg, #cce5ff, #99ccff); } /* Azul claro */
        .menu-card.tareas { background: linear-gradient(135deg, #fff3cd, #ffe69c); } /* Amarillo claro */
        .menu-card.reportes { background: linear-gradient(135deg, #f8d7da, #f5b5ba); } /* Rojo claro */
        .menu-card.usuarios { background: linear-gradient(135deg, #e0e7ff, #c3daff); } /* Morado claro */
        .menu-card.salir { background: linear-gradient(135deg, #e2e3e5, #c8cbce); } /* Gris claro */
        
        /* Estilo de subrayado animado para enlaces de navegación */
        .nav-link {
            position: relative;
            overflow: hidden;
            display: flex; /* Asegura que el icono y el texto estén en línea */
            align-items: center; /* Alinea verticalmente el icono y el texto */
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

        /* Oculta elementos con x-cloak de Alpine.js hasta que Alpine los inicialice */
        [x-cloak] { display: none !important; }
    </style>
</head>
<body x-data="appData()" class="h-full bg-gray-50 font-sans text-gray-800 dark:bg-gray-900 dark:text-gray-100 transition-colors duration-200 flex flex-col min-h-screen">
    <script>
        function appData() {
            return {
                openModalAgregarUsuario: false,
                // Función para alternar el tema (claro/oscuro)
                toggleTheme() {
                    const html = document.documentElement;
                    const isDark = !html.classList.contains('dark');
                    
                    html.classList.toggle('dark', isDark); // Añade o quita la clase 'dark'
                    html.style.colorScheme = isDark ? 'dark' : 'light'; // Ajusta la preferencia de color del navegador
                    localStorage.setItem('theme', isDark ? 'dark' : 'light'); // Guarda la preferencia en localStorage
                },
                // Función para resetear el formulario de nuevo usuario (si existiera)
                resetNewUserForm() {
                    // Implementa la lógica para resetear el formulario aquí
                    console.log('Formulario de nuevo usuario reseteado');
                }
            }
        }
    </script>

    <header class="bg-white dark:bg-gray-800 shadow-sm sticky top-0 z-40" x-data="{ open: false }">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <div class="flex items-center">
                <h1 class="text-xl font-bold text-gray-800 dark:text-white">Panel de Control</h1>
            </div>
            
            <nav class="hidden md:flex items-center space-x-1">
                <a href="#" class="nav-link px-3 py-2 text-sm font-medium text-primary-600 dark:text-primary-400 active">
                    <i class="bi bi-speedometer2 mr-1"></i> Inicio
                </a>
                 <?php if ($rol === 'administrador' || $rol === 'veterinario'): ?>
                <a href="./modulos/animales.php" class="nav-link px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400">
                    <i class="bi bi-egg-fried mr-1"></i> Animales
                </a>
                <?php endif; ?>
                <a href="./modulos/inventario.php" class="nav-link px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400">
                    <i class="bi bi-box-seam mr-1"></i> Inventario
                </a>
                <a href="./modulos/tareas_veterinario.php" class="nav-link px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400">
                    <i class="bi bi-list-check mr-1"></i> Tareas
                </a>
                <?php if ($rol === 'administrador'): // Solo mostrar si NO es veterinario ?>
                <a href="reportes.php" class="nav-link px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400">
                    <i class="bi bi-graph-up mr-1"></i> Gráficas
                </a>
                <?php endif; ?>
                <?php if ($rol === 'administrador'): // Solo mostrar si es administrador ?>
                <a href="usuarios.php" class="nav-link px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400">
                    <i class="bi bi-people mr-1"></i> Usuarios
                </a>
                <?php endif; ?>
                <a href="./landing_page.php" class="nav-link px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400">
                    <i class="bi bi-shop-window mr-1"></i> Animales - Venta
                </a>
            </nav>
            
            <div class="flex items-center space-x-4">
                <button id="theme-toggle" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none" @click="toggleTheme()">
                    <i class="bi bi-sun-fill text-yellow-500 dark:hidden text-lg"></i>
                    <i class="bi bi-moon-fill text-blue-400 hidden dark:inline text-lg"></i>
                </button>

                <div class="hidden md:flex items-center bg-gray-100 dark:bg-gray-700 px-3 py-1 rounded-full">
                    <i class="bi bi-calendar mr-2 text-gray-500 dark:text-gray-300"></i>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200"><?php echo date('d/m/Y'); ?></span>
                </div>
                
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="flex items-center space-x-2 focus:outline-none">
                        <div class="w-9 h-9 rounded-full bg-primary-600 flex items-center justify-center text-white font-semibold text-lg">
                            <?php echo strtoupper(substr($nombre, 0, 1)); ?>
                        </div>
                        <span class="hidden md:inline text-sm font-medium text-gray-700 dark:text-gray-200"><?php echo htmlspecialchars($nombre); ?></span>
                        <i class="bi bi-chevron-down text-gray-500 dark:text-gray-400 text-xs hidden md:inline"></i>
                    </button>
                    
                    <div x-show="open" @click.away="open = false" x-cloak
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="transform opacity-0 scale-95"
                         x-transition:enter-end="transform opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="transform opacity-100 scale-100"
                         x-transition:leave-end="transform opacity-0 scale-95"
                         class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-md shadow-lg py-1 z-50 origin-top-right">
                        <div class="px-4 py-2 border-b border-gray-200 dark:border-gray-700">
                            <p class="text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($nombre); ?></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($rol); ?></p>
                        </div>
                        <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                            <i class="bi bi-box-arrow-right mr-2"></i> Cerrar sesión
                        </a>
                    </div>
                </div>

                <button @click="open = !open" class="md:hidden p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none ml-2">
                    <i x-show="!open" class="bi bi-list text-2xl text-gray-700 dark:text-gray-300"></i>
                    <i x-show="open" x-cloak class="bi bi-x-lg text-2xl text-gray-700 dark:text-gray-300"></i>
                </button>
            </div>
        </div>
        
        <div x-show="open" x-cloak 
             x-transition:enter="transition ease-out duration-200" 
             x-transition:enter-start="opacity-0 scale-95" 
             x-transition:enter-end="opacity-100 scale-100" 
             x-transition:leave="transition ease-in duration-150" 
             x-transition:leave-start="opacity-100 scale-100" 
             x-transition:leave-end="opacity-0 scale-95" 
             class="md:hidden bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 pb-4">
            <div class="flex flex-col items-center space-y-3 pt-4 px-4">
                <a href="#" @click="open = false" class="nav-link w-full text-center py-2 px-4 text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 rounded-md">
                    <i class="bi bi-speedometer2 mr-2"></i> Inicio
                </a>
                <?php if ($rol === 'administrador' || $rol === 'veterinario'): ?>
                <a href="./modulos/animales.php" @click="open = false" class="nav-link w-full text-center py-2 px-4 text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 rounded-md">
                    <i class="bi bi-egg-fried mr-2"></i> Animales
                </a>
                <?php endif; ?>
                <a href="./modulos/inventario.php" @click="open = false" class="nav-link w-full text-center py-2 px-4 text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 rounded-md">
                    <i class="bi bi-box-seam mr-2"></i> Inventario
                </a>
                <a href="./modulos/tareas_veterinario.php" @click="open = false" class="nav-link w-full text-center py-2 px-4 text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 rounded-md">
                    <i class="bi bi-list-check mr-2"></i> Tareas
                </a>
                <?php if ($rol === 'administrador'): ?>
                <a href="reportes.php" @click="open = false" class="nav-link w-full text-center py-2 px-4 text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 rounded-md">
                    <i class="bi bi-graph-up mr-2"></i> Gráficas
                </a>
                <?php endif; ?>
                <?php if ($rol === 'administrador'): ?>
                <a href="usuarios.php" @click="open = false" class="nav-link w-full text-center py-2 px-4 text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 rounded-md">
                    <i class="bi bi-people mr-2"></i> Usuarios
                </a>
                <?php endif; ?>
                <a href="./landing_page.php" @click="open = false" class="nav-link w-full text-center py-2 px-4 text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 rounded-md">
                    <i class="bi bi-shop-window mr-2"></i> Animales - Venta
                </a>
            </div>
        </div>
    </header>
    
    <main class="flex-grow py-6">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <?php if ($mensaje): ?>
                <div class="mb-4 p-4 text-sm rounded-lg 
                    <?= strpos($mensaje, 'correctamente') !== false || strpos($mensaje, 'exitosamente') !== false ? 'text-green-700 bg-green-100 dark:bg-green-200 dark:text-green-800' : 'text-red-700 bg-red-100 dark:bg-red-200 dark:text-red-800' ?>" 
                    role="alert">
                    <?= htmlspecialchars($mensaje) ?>
                </div>
            <?php endif; ?>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-soft p-6 mb-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-xl sm:text-2xl font-bold text-gray-800 dark:text-white">Bienvenido, <?php echo htmlspecialchars($nombre); ?></h2>
                        <p class="text-gray-600 dark:text-gray-400 text-sm sm:text-base">Aquí puedes gestionar todas las actividades de tu granja</p>
                    </div>
                    <div class="mt-4 md:mt-0 flex items-center space-x-3">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200">
                            <i class="bi bi-person-badge mr-1"></i> <?php echo htmlspecialchars($rol); ?>
                        </span>
                        <?php if ($rol === 'administrador'): ?>
                        <!-- <button @click="openModalAgregarUsuario = true; resetNewUserForm()" class="flex items-center justify-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors duration-200">
                            <i class="bi bi-person-plus-fill mr-2"></i>
                            Agregar Usuario
                        </button> -->
                        <?php endif; ?>
                    </div>
                </div>

                <h2 class="text-lg sm:text-xl font-semibold mb-4 text-gray-800 dark:text-white">Resumen General</h2>
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
                            <div class="flex items-center text-xs text-blue-600 dark:text-blue-400">
                                <i class="bi bi-info-circle mr-1"></i>
                                <span>Ítems en inventario</span>
                            </div>
                        </div>
                    </div>

                    <?php if ($rol === 'administrador'): ?>
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-soft p-4 col-span-full sm:col-span-1">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Usuarios Registrados</p>
                                <h3 class="text-2xl font-bold mt-1"><?= number_format($total_usuarios) ?></h3>
                            </div>
                            <div class="p-2 rounded-lg bg-purple-50 dark:bg-purple-900/30 text-purple-600 dark:text-purple-300">
                                <i class="bi bi-people text-lg"></i>
                            </div>
                        </div>
                        <div class="mt-2">
                            <div class="flex items-center text-xs text-purple-600 dark:text-purple-400">
                                <i class="bi bi-info-circle mr-1"></i>
                                <span>Total de cuentas de usuario</span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <h2 class="text-lg sm:text-xl font-semibold mb-4 text-gray-800 dark:text-white">Actividad Reciente</h2>
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
                                                <div class="mt-1 flex flex-wrap items-center text-xs text-gray-500 dark:text-gray-400">
                                                    <?php if (!empty($actividad['usuario'])): ?>
                                                        <span><?= $actividad['usuario'] ?></span>
                                                        <span class="mx-1">•</span>
                                                    <?php endif; ?>
                                                    <span><?= date('d/m/Y', strtotime($actividad['fecha'])) ?></span>
                                                    <?php if (!empty($actividad['hora'])): ?>
                                                        <span class="mx-1">•</span>
                                                        <span><?= $actividad['hora'] ?></span>
                                                    <?php endif; ?>
                                                    <span class="ml-auto px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $actividad['color_class'] ?> mt-1 md:mt-0">
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

                <h2 class="text-lg sm:text-xl font-semibold mb-4 text-gray-800 dark:text-white">Acciones Rápidas</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4 mb-8">
                    <a href="./modulos/animales.php" class="menu-card animales rounded-xl shadow-soft overflow-hidden">
                        <div class="p-4 text-center h-full flex flex-col items-center justify-center">
                            <div class="w-12 h-12 rounded-full bg-white bg-opacity-50 flex items-center justify-center mb-3">
                                <i class="bi bi-egg-fried text-green-600 text-2xl"></i>
                            </div>
                            <h3 class="text-base font-semibold mb-1 text-gray-800">Animales</h3>
                            <p class="text-xs text-gray-600">Gestionar ganado</p>
                        </div>
                    </a>
                    
                    <a href="./modulos/inventario.php" class="menu-card inventario rounded-xl shadow-soft overflow-hidden">
                        <div class="p-4 text-center h-full flex flex-col items-center justify-center">
                            <div class="w-12 h-12 rounded-full bg-white bg-opacity-50 flex items-center justify-center mb-3">
                                <i class="bi bi-box-seam text-blue-600 text-2xl"></i>
                            </div>
                            <h3 class="text-base font-semibold mb-1 text-gray-800">Inventario</h3>
                            <p class="text-xs text-gray-600">Control de suministros</p>
                        </div>
                    </a>
                    
                    <a href="./modulos/tareas_veterinario.php" class="menu-card tareas rounded-xl shadow-soft overflow-hidden">
                        <div class="p-4 text-center h-full flex flex-col items-center justify-center">
                            <div class="w-12 h-12 rounded-full bg-white bg-opacity-50 flex items-center justify-center mb-3">
                                <i class="bi bi-list-check text-yellow-600 text-2xl"></i>
                            </div>
                            <h3 class="text-base font-semibold mb-1 text-gray-800">Tareas</h3>
                            <p class="text-xs text-gray-600">Actividades diarias</p>
                        </div>
                    </a>
                    
                    <?php if ($rol === 'administrador' || $rol === 'trabajador'): ?>
                    <a href="reportes.php" class="menu-card reportes rounded-xl shadow-soft overflow-hidden">
                        <div class="p-4 text-center h-full flex flex-col items-center justify-center">
                            <div class="w-12 h-12 rounded-full bg-white bg-opacity-50 flex items-center justify-center mb-3">
                                <i class="bi bi-graph-up text-red-600 text-2xl"></i>
                            </div>
                            <h3 class="text-base font-semibold mb-1 text-gray-800">Gráficas</h3>
                            <p class="text-xs text-gray-600">Estadísticas</p>
                        </div>
                    </a>
                    <?php endif; ?>

                    <?php if ($rol === 'administrador'): ?>
                    <a href="./modulos/usuarios.php" class="menu-card usuarios rounded-xl shadow-soft overflow-hidden">
                        <div class="p-4 text-center h-full flex flex-col items-center justify-center">
                            <div class="w-12 h-12 rounded-full bg-white bg-opacity-50 flex items-center justify-center mb-3">
                                <i class="bi bi-people text-purple-600 text-2xl"></i>
                            </div>
                            <h3 class="text-base font-semibold mb-1 text-gray-800">Usuarios</h3>
                            <p class="text-xs text-gray-600">Gestionar cuentas</p>
                        </div>
                    </a>
                    <?php endif; ?>
                    
                    <a href="logout.php" class="menu-card salir rounded-xl shadow-soft overflow-hidden">
                        <div class="p-4 text-center h-full flex flex-col items-center justify-center">
                            <div class="w-12 h-12 rounded-full bg-white bg-opacity-50 flex items-center justify-center mb-3">
                                <i class="bi bi-box-arrow-right text-gray-600 text-2xl"></i>
                            </div>
                            <h3 class="text-base font-semibold mb-1 text-gray-800">Salir</h3>
                            <p class="text-xs text-gray-600">Cerrar sesión</p>
                        </div>
                    </a>
                </div>
            </div>
        </main>
    </main> <footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 py-6">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row justify-between items-center text-center md:text-left">
            <div class="text-sm text-gray-500 dark:text-gray-400 mb-2 md:mb-0">
                © <?= date('Y') ?> LA GRANJA DE RORON - Todos los derechos reservados.
            </div>
            <div class="flex space-x-4">
                <a href="#" class="text-sm text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400">Términos</a>
                <a href="#" class="text-sm text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400">Privacidad</a>
                <a href="#contact" class="text-sm text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400">Contacto</a>
            </div>
        </div>
    </footer>

    <div x-cloak x-show="openModalAgregarUsuario" x-transition class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-agregar-usuario-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="openModalAgregarUsuario" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" @click="openModalAgregarUsuario = false; resetNewUserForm()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div x-show="openModalAgregarUsuario" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                </div>
        </div>
    </div>
</body>
</html>
