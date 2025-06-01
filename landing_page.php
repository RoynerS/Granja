<?php
session_start();
include 'db.php'; // Asegúrate de que 'db.php' contenga la conexión a la base de datos

/**
 * Obtiene una imagen de animal de Unsplash o un placeholder si falla.
 * @param string $especie La especie del animal.
 * @param string $nombre El nombre del animal.
 * @return string La URL de la imagen.
 */
function obtenerImagenAnimal($especie, $nombre) {
    // Clave de acceso de Unsplash (considera usar variables de entorno para esto en producción)
    $UNSPLASH_ACCESS_KEY = 'Y2fTCP-qwJ10mlekoYOrgt5oMdrKjb1W7Uf5cWg02os'; 
    $especie_encoded = urlencode(strtolower($especie));
    $url = "https://api.unsplash.com/search/photos?query=$especie_encoded+animal&client_id=$UNSPLASH_ACCESS_KEY&per_page=1";
    
    try {
        // Realiza la solicitud a la API de Unsplash
        $response = @file_get_contents($url); // Usamos @ para suprimir advertencias si la URL no es accesible
        
        if ($response === FALSE) {
            throw new Exception("No se pudo conectar a Unsplash o la solicitud falló.");
        }
        
        $data = json_decode($response, true);
        
        // Verifica si la respuesta es válida y contiene la URL de la imagen
        if ($data && isset($data['results'][0]['urls']['regular'])) {
            return $data['results'][0]['urls']['regular'];
        }
    } catch (Exception $e) {
        error_log("Error al obtener imagen de Unsplash para especie '$especie': " . $e->getMessage());
    }
    
    // Genera un placeholder si la API falla o no devuelve resultados
    $placeholder_text = substr($nombre, 0, 1) . ' ' . substr($especie, 0, 1);
    return "https://placehold.co/400x300/e5e7eb/374151?text=" . urlencode($placeholder_text);
}

$animales = [];
$precio_por_kg = 10000; // Precio base por kilogramo

try {
    // Consulta para obtener animales vacunados, ordenados por nombre
    $stmt = $conn->query("SELECT id, nombre, especie, raza, sexo, edad, peso, estado_salud, codigo_animal, fecha_ingreso, vacunado FROM animales WHERE estado_salud = 'vacunado' ORDER BY nombre ASC");
    if ($stmt) {
        $animales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Error al cargar animales: " . $e->getMessage());
}

// Inicializa variables para estadísticas
$total_animales = 0;
$total_usuarios = 0;
$total_produccion_registros = 0;

try {
    // Obtener el total de animales
    $stmt_total_animales = $conn->prepare("SELECT COUNT(id) AS total FROM animales");
    $stmt_total_animales->execute();
    $result_total_animales = $stmt_total_animales->fetch(PDO::FETCH_ASSOC);
    $total_animales = $result_total_animales['total'];

    // Obtener el total de usuarios
    $stmt_total_usuarios = $conn->prepare("SELECT COUNT(id) AS total FROM usuarios");
    $stmt_total_usuarios->execute();
    $result_total_usuarios = $stmt_total_usuarios->fetch(PDO::FETCH_ASSOC);
    $total_usuarios = $result_total_usuarios['total'];

    // Obtener el total de registros de producción
    $stmt_total_produccion = $conn->prepare("SELECT COUNT(id) AS total FROM produccion");
    $stmt_total_produccion->execute();
    $result_total_produccion = $stmt_total_produccion->fetch(PDO::FETCH_ASSOC);
    $total_produccion_registros = $result_total_produccion['total'];
} catch (PDOException $e) {
    error_log("Error al obtener estadísticas: " . $e->getMessage());
}

// Obtener el rol y estado de autenticación del usuario
$rol = $_SESSION['rol'] ?? '';
$usuario_logueado = isset($_SESSION['usuario_id']);
$nombre_usuario = $usuario_logueado ? htmlspecialchars($_SESSION['nombre']) : '';
?>

<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Granja App - Tu Compañero Agrícola</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.12.0/dist/cdn.min.js" defer></script>
    
    <script>
        function applyTheme() {
            const savedTheme = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            
            if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
                document.documentElement.classList.add('dark');
                document.documentElement.style.colorScheme = 'dark';
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
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        /* Previene el "flash" de contenido sin estilo en el modo oscuro */
        html {
            color-scheme: light dark;
        }
        
        /* Transiciones suaves para cambios de tema */
        body {
            transition: background-color 0.2s ease, color 0.2s ease;
        }
        
        /* Efectos de hover para las tarjetas de animales */
        .animal-card {
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .animal-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        /* Estilos para la sección hero con imagen de fondo */
        .hero-section {
            background-image: url('https://images.unsplash.com/photo-1500595046743-cd271d694d30?ixlib=rb-1.2.1&auto=format&fit=crop&w=1200&q=80');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
        
        /* Capa oscura sobre la imagen de fondo en modo oscuro */
        .dark .hero-section {
            background-image: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), 
                              url('https://images.unsplash.com/photo-1500595046743-cd271d694d30?ixlib=rb-1.2.1&auto=format&fit=crop&w=1200&q=80');
        }

        /* Estilo de subrayado animado para enlaces de navegación */
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
        
        /* Oculta elementos con x-cloak de Alpine.js hasta que Alpine los inicialice */
        [x-cloak] { display: none !important; }
    </style>
</head>
<body x-data="{
        // Estado para controlar la visibilidad del modal de contacto
        isContactModalOpen: false,
        // Datos del animal seleccionado para el modal
        modalAnimal: {},
        // Precio formateado del animal seleccionado para el modal
        modalAnimalPrice: '',
        // Función para abrir el modal de contacto
        openContactModal(animal, price) {
            this.modalAnimal = animal;
            this.modalAnimalPrice = price;
            this.isContactModalOpen = true;
        },
        // Función para alternar el tema (claro/oscuro)
        toggleTheme() {
            const html = document.documentElement;
            const isDark = !html.classList.contains('dark');
            
            html.classList.toggle('dark', isDark); // Añade o quita la clase 'dark'
            html.style.colorScheme = isDark ? 'dark' : 'light'; // Ajusta la preferencia de color del navegador
            localStorage.setItem('theme', isDark ? 'dark' : 'light'); // Guarda la preferencia en localStorage
        }
    }" class="h-full bg-gray-50 dark:bg-gray-900 font-sans text-gray-800 dark:text-gray-100 flex flex-col min-h-screen">
    
    <header class="bg-white dark:bg-gray-800 shadow-sm sticky top-0 z-40" x-data="{ open: false }">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <div class="flex items-center">
                <a href="index.php" class="text-2xl font-bold text-primary-600 dark:text-primary-400 flex items-center">
                    <i class="bi bi-shop-window mr-2"></i> Granja Marketplace
                </a>
            </div>
            
            <div class="hidden md:flex items-center space-x-4">
                <a href="#about" class="nav-link font-medium transition-colors text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400">Sobre Nosotros</a>
                <a href="#animals-stats" class="nav-link font-medium transition-colors text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400">La Granja</a>
                <a href="#animal-listings" class="nav-link font-medium transition-colors text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400">Animales</a>
                <a href="#contact" class="nav-link font-medium transition-colors text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400">Contacto</a>

                <?php if ($usuario_logueado): ?>
                    <span class="text-lg font-medium text-gray-700 dark:text-gray-300 hidden md:inline">Hola, <?= $nombre_usuario ?></span>
                    <?php if ($rol === 'administrador' || $rol === 'veterinario' || $rol === 'trabajador'): ?>
                        <a href="dashboard.php" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors duration-200 flex items-center">
                            <i class="bi bi-person-fill-gear mr-2"></i> Panel
                        </a>
                    <?php endif; ?>
                    <a href="logout.php" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-200 flex items-center">
                        <i class="bi bi-box-arrow-right mr-2"></i> Cerrar Sesión
                    </a>
                <?php else: ?>
                    <a href="index.php" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors duration-200 flex items-center">
                        <i class="bi bi-box-arrow-in-right mr-2"></i> Iniciar Sesión
                    </a>
                    <a href="register.php" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600 transition-colors duration-200 flex items-center">
                        <i class="bi bi-person-plus mr-2"></i> Registrar
                    </a>
                <?php endif; ?>
                <button @click="toggleTheme()" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none">
                    <i class="bi bi-sun-fill text-yellow-500 dark:hidden"></i>
                    <i class="bi bi-moon-fill text-blue-400 hidden dark:inline"></i>
                </button>
            </div>

            <div class="md:hidden flex items-center">
                <button @click="open = !open" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none">
                    <i x-show="!open" class="bi bi-list text-2xl text-gray-700 dark:text-gray-300"></i>
                    <i x-show="open" x-cloak class="bi bi-x-lg text-2xl text-gray-700 dark:text-gray-300"></i>
                </button>
                <button @click="toggleTheme()" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none ml-2">
                    <i class="bi bi-sun-fill text-yellow-500 dark:hidden"></i>
                    <i class="bi bi-moon-fill text-blue-400 hidden dark:inline"></i>
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
                <a href="#about" @click="open = false" class="nav-link w-full text-center py-2 px-4 text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 rounded-md">Sobre Nosotros</a>
                <a href="#animals-stats" @click="open = false" class="nav-link w-full text-center py-2 px-4 text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 rounded-md">La Granja</a>
                <a href="#animal-listings" @click="open = false" class="nav-link w-full text-center py-2 px-4 text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 rounded-md">Animales</a>
                <a href="#contact" @click="open = false" class="nav-link w-full text-center py-2 px-4 text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 rounded-md">Contacto</a>

                <?php if ($usuario_logueado): ?>
                    <span class="text-lg font-medium text-gray-700 dark:text-gray-300 mt-4">Hola, <?= $nombre_usuario ?></span>
                    <?php if ($rol === 'administrador' || $rol === 'veterinario' || $rol === 'trabajador'): ?>
                        <a href="dashboard.php" class="w-full text-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors duration-200 flex items-center justify-center">
                            <i class="bi bi-person-fill-gear mr-2"></i> Panel
                        </a>
                    <?php endif; ?>
                    <a href="logout.php" class="w-full text-center px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-200 flex items-center justify-center">
                        <i class="bi bi-box-arrow-right mr-2"></i> Cerrar Sesión
                    </a>
                <?php else: ?>
                    <a href="index.php" class="w-full text-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors duration-200 flex items-center justify-center">
                        <i class="bi bi-box-arrow-in-right mr-2"></i> Iniciar Sesión
                    </a>
                    <a href="register.php" class="w-full text-center px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600 transition-colors duration-200 flex items-center justify-center">
                        <i class="bi bi-person-plus mr-2"></i> Registrar
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="flex-grow">
        <section class="hero-section py-20 px-4 sm:px-6 lg:px-8 text-center text-white shadow-lg">
            <div class="bg-black bg-opacity-40 p-8 rounded-xl inline-block max-w-4xl mx-auto">
                <h1 class="text-4xl sm:text-5xl font-extrabold mb-4 drop-shadow-lg">Nuestros Animales, Tu Futuro</h1>
                <p class="text-lg sm:text-xl max-w-3xl mx-auto mb-8 drop-shadow-md">
                    Descubre la calidad y el cuidado en cada uno de nuestros animales. Ideales para producción o como parte de tu familia.
                </p>
                <a href="#animal-listings" class="inline-block bg-primary-600 hover:bg-primary-700 text-white font-bold py-3 px-8 rounded-full text-lg shadow-lg transition-all duration-300 ease-in-out transform hover:scale-105">
                    Ver Animales Disponibles
                </a>
            </div>
        </section>

        <section id="about" class="py-16 md:py-24 bg-white dark:bg-gray-800">
            <div class="container mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl md:text-4xl font-extrabold text-center text-gray-900 dark:text-white mb-12">
                    Sobre Nosotros
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-xl shadow-soft-lg p-8 text-center border-t-4 border-primary-500 hover:shadow-xl transition-shadow duration-300">
                        <i class="bi bi-bullseye text-5xl text-primary-600 mb-4"></i>
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white mt-5">Nuestra Misión</h3>
                        <p class="text-gray-700 dark:text-gray-300 leading-relaxed mt-2">
                            Proveer productos agrícolas y ganaderos de la más alta calidad, cuidando el bienestar animal y el medio ambiente.
                        </p>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-xl shadow-soft-lg p-8 text-center border-t-4 border-secondary-500 hover:shadow-xl transition-shadow duration-300">
                        <i class="bi bi-lightbulb-fill text-5xl text-secondary-600 mb-4"></i>
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white mt-5">Nuestra Visión</h3>
                        <p class="text-gray-700 dark:text-gray-300 leading-relaxed mt-2">
                            Ser la granja líder en la región, reconocida por nuestra excelencia en producción sostenible e innovación.
                        </p>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-xl shadow-soft-lg p-8 text-center border-t-4 border-purple-500 hover:shadow-xl transition-shadow duration-300">
                        <i class="bi bi-check2-circle text-5xl text-purple-600 mb-4"></i>
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white mt-5">Nuestros Objetivos</h3>
                        <ul class="text-gray-700 dark:text-gray-300 leading-relaxed list-disc list-inside text-left mx-auto max-w-md mt-2">
                            <li>Eficiencia productiva con estándares de calidad</li>
                            <li>Bienestar y salud animal</li>
                            <li>Tecnologías innovadoras</li>
                            <li>Productos frescos y naturales</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <section id="animals-stats" class="py-16 md:py-24 bg-gray-100 dark:bg-gray-900">
            <div class="container mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <h2 class="text-3xl md:text-4xl font-extrabold text-gray-900 dark:text-white mb-12">
                    Nuestra Granja en Cifras
                </h2>
                <p class="text-lg text-gray-700 dark:text-gray-300 mb-8 max-w-3xl mx-auto">
                    Conoce el impacto y la escala de nuestras operaciones.
                </p>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-soft p-6 transition-all duration-300 transform hover:scale-105 hover:shadow-lg">
        <i class="fas fa-horse text-5xl text-primary-600 mb-4"></i>
        <h3 class="text-3xl font-bold text-gray-900 dark:text-white"><?= htmlspecialchars($total_animales) ?>+</h3>
        <p class="text-primary-700 dark:text-primary-300 font-semibold">Animales</p>
    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-soft p-6 transition-all duration-300 transform hover:scale-105 hover:shadow-lg">
                        <i class="fas fa-egg text-5xl text-yellow-500 mb-4"></i>
                        <h3 class="text-3xl font-bold text-gray-900 dark:text-white"><?= htmlspecialchars($total_produccion_registros) ?>+</h3>
                        <p class="text-primary-700 dark:text-primary-300 font-semibold">Producción</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-soft p-6 transition-all duration-300 transform hover:scale-105 hover:shadow-lg">
                        <i class="fas fa-users text-5xl text-teal-500 mb-4"></i>
                        <h3 class="text-3xl font-bold text-gray-900 dark:text-white"><?= htmlspecialchars($total_usuarios) ?>+</h3>
                        <p class="text-primary-700 dark:text-primary-300 font-semibold">Equipo</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="animal-listings" class="p-6 py-16 md:py-24 bg-white dark:bg-gray-800">
            <div class="container mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl font-bold text-gray-900 dark:text-white text-center mb-10">Animales Disponibles</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <?php if (empty($animales)): ?>
                        <div class="col-span-full text-center p-8 bg-gray-50 dark:bg-gray-700 rounded-xl shadow-soft">
                            <p class="text-lg text-gray-500 dark:text-gray-400">No hay animales disponibles actualmente.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($animales as $animal): ?>
                            <?php 
                                $precio = $animal['peso'] * $precio_por_kg;
                                $image_url = obtenerImagenAnimal($animal['especie'], $animal['nombre']);
                                
                                // Determina el icono basado en la especie del animal
                                $icono = match(strtolower($animal['especie'])) {
                                    'cerdo', 'porcino' => 'fa-piggy-bank',
                                    'vaca', 'bovino' => 'fa-cow',
                                    'gallina', 'pollo', 'ave' => 'fa-kiwi-bird',
                                    'oveja', 'ovino' => 'fa-sheep',
                                    'caballo', 'equino' => 'fa-horse',
                                    default => 'fa-paw' // Icono por defecto
                                };
                            ?>
                            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-soft overflow-hidden flex flex-col animal-card">
                                <img src="<?= $image_url ?>" alt="<?= htmlspecialchars($animal['nombre']) ?>" class="w-full h-48 object-cover">
                                <div class="p-5 flex-grow flex flex-col">
                                    <div class="flex items-center mb-2">
                                        <i class="fas <?= $icono ?> text-2xl text-primary-600 mr-2"></i>
                                        <h3 class="text-xl font-bold text-gray-900 dark:text-white"><?= htmlspecialchars($animal['nombre']) ?></h3>
                                    </div>
                                    
                                    <div class="text-gray-600 dark:text-gray-400 text-sm mb-3 space-y-1">
                                        <p><span class="font-semibold flex items-center"><i class="bi bi-gem mr-2"></i>Raza:</span> <?= htmlspecialchars($animal['raza']) ?></p>
                                        <p><span class="font-semibold flex items-center"><i class="bi bi-calendar-event mr-2"></i>Edad:</span> <?= htmlspecialchars($animal['edad']) ?> años</p>
                                        <p><span class="font-semibold flex items-center"><i class="bi bi-rulers mr-2"></i>Peso:</span> <?= htmlspecialchars($animal['peso']) ?> kg</p>
                                        <?php if ($animal['vacunado']): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 mt-2">
                                                <i class="bi bi-shield-fill-check mr-1"></i> Vacunado
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="mt-auto pt-4 border-t border-gray-200 dark:border-gray-700">
                                        <p class="text-3xl font-extrabold text-primary-700 dark:text-primary-400 mb-4">$<?= number_format($precio, 2) ?></p>
                                        <button @click="openContactModal(<?= htmlspecialchars(json_encode($animal), ENT_QUOTES, 'UTF-8') ?>, '<?= number_format($precio, 2) ?>')" class="w-full py-2 px-4 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors duration-200 flex items-center justify-center">
                                            <i class="bi bi-whatsapp mr-2"></i> Contactar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section id="contact" class="py-16 md:py-24 bg-gray-100 dark:bg-gray-900">
            <div class="container mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <h2 class="text-3xl md:text-4xl font-extrabold text-gray-900 dark:text-white mb-12">
                    Contáctanos
                </h2>
                <div class="flex flex-col md:flex-row justify-center items-center space-y-6 md:space-y-0 md:space-x-8">
                    <div class="flex items-center text-gray-700 dark:text-gray-300">
                        <i class="bi bi-geo-alt-fill text-primary-600 text-3xl mr-3"></i>
                        <span>123 Calle de la Granja, Colombia</span>
                    </div>
                    <div class="flex items-center text-gray-700 dark:text-gray-300">
                        <i class="bi bi-envelope-fill text-primary-600 text-3xl mr-3"></i>
                        <a href="mailto:info@granjaapp.com" class="hover:underline">info@granjaapp.com</a>
                    </div>
                    <div class="flex items-center text-gray-700 dark:text-gray-300">
                        <i class="bi bi-phone-fill text-primary-600 text-3xl mr-3"></i>
                        <span>+57 300 123 4567</span>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 py-6 px-4 sm:px-6 lg:px-8 mt-auto">
        <div class="container mx-auto flex flex-col md:flex-row justify-between items-center text-center md:text-left">
            <div class="text-sm text-gray-500 dark:text-gray-400 mb-2 md:mb-0">
                © <?= date('Y') ?> Granja App - Todos los derechos reservados.
            </div>
            <div class="flex space-x-4">
                <a href="#" class="text-sm text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400">Términos</a>
                <a href="#" class="text-sm text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400">Privacidad</a>
                <a href="#contact" class="text-sm text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400">Contacto</a>
            </div>
        </div>
    </footer>

    <div x-cloak x-show="isContactModalOpen" x-transition class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen p-4 text-center sm:block sm:p-0">
            <div @click="isContactModalOpen = false" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
            
            <div x-show="isContactModalOpen" x-transition class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-primary-100 dark:bg-primary-900 sm:mx-0 sm:h-10 sm:w-10">
                            <i class="bi bi-whatsapp text-primary-600 dark:text-primary-300"></i>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                                Contactar por {{ modalAnimal.nombre }}
                            </h3>
                            <div class="mt-2">
                                <ul class="text-sm text-gray-700 dark:text-gray-300 list-disc list-inside">
                                    <li><span class="font-semibold">Raza:</span> {{ modalAnimal.raza }}</li>
                                    <li><span class="font-semibold">Edad:</span> {{ modalAnimal.edad }} años</li>
                                    <li><span class="font-semibold">Peso:</span> {{ modalAnimal.peso }} kg</li>
                                    <li><span class="font-semibold">Precio:</span> ${{ modalAnimalPrice }}</li>
                                </ul>
                                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                                    Nos pondremos en contacto contigo pronto.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button @click="isContactModalOpen = false" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-white hover:bg-primary-700 sm:ml-3 sm:w-auto sm:text-sm">
                        Entendido
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
