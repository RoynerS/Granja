<?php
session_start();

// Redirige al login si el usuario no ha iniciado sesión
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Incluye el archivo de conexión a la base de datos
// Asegúrate de que la ruta a tu archivo db.php sea correcta.
// Nota: Este archivo espera la base de datos 'granja_db',
// mientras que 'k (1).sql' define una base de datos 'k'.
// Asegúrate de usar la base de datos correcta para tus reportes.
include 'db.php'; 

$nombre = $_SESSION['nombre']; // Nombre del usuario logueado
$rol = $_SESSION['rol'];     // Rol del usuario logueado

// Inicialización de arrays para almacenar los datos de los gráficos
$animal_species_data = [];
$inventario_tipo_data = [];
$tareas_status_data = ['Pendientes' => 0, 'Completadas' => 0];
$animal_sex_data = [];
$animal_vaccination_data = [];
$produccion_tipo_data = [];
$usuarios_rol_data = [];
$tareas_por_responsable_data = []; // Nuevo array para datos de tareas por responsable
$animal_raza_data = [];            // Nuevo array para datos de animales por raza

try {
    // --- Gráfico 1: Animales por Especie ---
    // Consulta para contar animales por especie
    $stmt_animal_species = $conn->query("SELECT especie, COUNT(*) AS count FROM animales GROUP BY especie");
    $animal_species_data[] = ['Especie', 'Cantidad']; // Encabezados para el gráfico
    while ($row = $stmt_animal_species->fetch(PDO::FETCH_NUM)) {
        $animal_species_data[] = $row; // Añade cada fila de resultados al array
    }

    // --- Gráfico 2: Inventario por Tipo ---
    // Consulta para contar ítems de inventario por tipo
    $stmt_inventario_tipo = $conn->query("SELECT tipo, COUNT(*) AS count FROM inventario GROUP BY tipo");
    $inventario_tipo_data[] = ['Tipo', 'Cantidad']; // Encabezados para el gráfico
    while ($row = $stmt_inventario_tipo->fetch(PDO::FETCH_NUM)) {
        $inventario_tipo_data[] = $row; // Añade cada fila de resultados al array
    }

    // --- Gráfico 3: Estado de Tareas (Pendientes vs. Completadas) ---
    // Consulta para contar tareas pendientes
    $sql_tareas_status_pendientes = "SELECT COUNT(*) AS total FROM tareas WHERE completado = 0";
    $params_tareas_status_pendientes = [];
    // Si el rol es veterinario, filtra las tareas por su ID de usuario
    if ($rol === 'veterinario') {
        $sql_tareas_status_pendientes .= " AND usuario_id = :session_user_id";
        $params_tareas_status_pendientes[':session_user_id'] = $_SESSION['usuario_id'];
    }
    $stmt_tareas_status_pendientes = $conn->prepare($sql_tareas_status_pendientes);
    $stmt_tareas_status_pendientes->execute($params_tareas_status_pendientes);
    $result_pendientes = $stmt_tareas_status_pendientes->fetch(PDO::FETCH_ASSOC);
    $tareas_status_data['Pendientes'] = $result_pendientes['total'];

    // Consulta para contar tareas completadas
    $sql_tareas_status_completadas = "SELECT COUNT(*) AS total FROM tareas WHERE completado = 1";
    $params_tareas_status_completadas = [];
    // Si el rol es veterinario, filtra las tareas por su ID de usuario
    if ($rol === 'veterinario') {
        $sql_tareas_status_completadas .= " AND usuario_id = :session_user_id";
        $params_tareas_status_completadas[':session_user_id'] = $_SESSION['usuario_id'];
    }
    $stmt_tareas_status_completadas = $conn->prepare($sql_tareas_status_completadas);
    $stmt_tareas_status_completadas->execute($params_tareas_status_completadas);
    $result_completadas = $stmt_tareas_status_completadas->fetch(PDO::FETCH_ASSOC);
    $tareas_status_data['Completadas'] = $result_completadas['total'];

    // --- Gráfico 4: Animales por Sexo ---
    // Consulta para contar animales por sexo
    $stmt_animal_sex = $conn->query("SELECT sexo, COUNT(*) AS count FROM animales GROUP BY sexo");
    $animal_sex_data[] = ['Sexo', 'Cantidad']; // Encabezados para el gráfico
    while ($row = $stmt_animal_sex->fetch(PDO::FETCH_NUM)) {
        $animal_sex_data[] = $row; // Añade cada fila de resultados al array
    }

    // --- Gráfico 5: Estado de Vacunación de Animales ---
    // Consulta para contar animales vacunados y no vacunados
    $stmt_animal_vaccination = $conn->query("SELECT CASE WHEN vacunado = 1 THEN 'Vacunado' ELSE 'No Vacunado' END AS status, COUNT(*) AS count FROM animales GROUP BY vacunado");
    $animal_vaccination_data[] = ['Estado de Vacunación', 'Cantidad']; // Encabezados para el gráfico
    while ($row = $stmt_animal_vaccination->fetch(PDO::FETCH_NUM)) {
        $animal_vaccination_data[] = $row; // Añade cada fila de resultados al array
    }

    // --- Gráfico 6: Producción por Tipo ---
    // Consulta para sumar la cantidad de producción por tipo
    $stmt_produccion_tipo = $conn->query("SELECT tipo_produccion, SUM(cantidad) AS total_cantidad FROM produccion GROUP BY tipo_produccion");
    $produccion_tipo_data[] = ['Tipo de Producción', 'Cantidad Total']; // Encabezados para el gráfico
    while ($row = $stmt_produccion_tipo->fetch(PDO::FETCH_NUM)) {
        $produccion_tipo_data[] = $row; // Añade cada fila de resultados al array
    }

    // --- Gráfico 7: Usuarios por Rol (solo para administradores) ---
    // Esta sección solo se ejecuta si el usuario logueado es un administrador
    if ($rol === 'administrador') {
        $stmt_usuarios_rol = $conn->query("SELECT rol, COUNT(*) AS count FROM usuarios GROUP BY rol");
        $usuarios_rol_data[] = ['Rol', 'Cantidad']; // Encabezados para el gráfico
        while ($row = $stmt_usuarios_rol->fetch(PDO::FETCH_NUM)) {
            $usuarios_rol_data[] = $row; // Añade cada fila de resultados al array
        }
    }

    // --- Gráfico 8: Tareas por Responsable (solo para administradores) ---
    if ($rol === 'administrador') {
        $stmt_tareas_por_responsable = $conn->query("SELECT u.nombre_usuario, COUNT(t.id) AS count 
                                                    FROM tareas t
                                                    JOIN usuarios u ON t.usuario_id = u.id
                                                    GROUP BY u.nombre_usuario");
        $tareas_por_responsable_data[] = ['Responsable', 'Cantidad de Tareas']; // Encabezados para el gráfico
        while ($row = $stmt_tareas_por_responsable->fetch(PDO::FETCH_NUM)) {
            $tareas_por_responsable_data[] = $row; // Añade cada fila de resultados al array
        }
    }

    // --- Gráfico 9: Animales por Raza ---
    // Asumiendo que existe una columna 'raza' en la tabla 'animales'
    $stmt_animal_raza = $conn->query("SELECT raza, COUNT(*) AS count FROM animales GROUP BY raza");
    $animal_raza_data[] = ['Raza', 'Cantidad']; // Encabezados para el gráfico
    while ($row = $stmt_animal_raza->fetch(PDO::FETCH_NUM)) {
        $animal_raza_data[] = $row; // Añade cada fila de resultados al array
    }

} catch (PDOException $e) {
    // Registra cualquier error de base de datos en el log del servidor
    error_log("Error al obtener datos para las estadísticas: " . $e->getMessage());
    // En un entorno de producción, podrías mostrar un mensaje de error genérico al usuario
    // o redirigir a una página de error.
}
?>

<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadísticas - Granja</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>

    <script type="text/javascript">
        // Configuración de Tailwind CSS, incluyendo el modo oscuro
        tailwind.config = {
            darkMode: 'class', // Habilita el modo oscuro basado en la clase 'dark' en el HTML
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0fdf4', 100: '#dcfce7', 200: '#bbf7d0', 300: '#86efac', 400: '#4ade80', 500: '#22c55e', 600: '#16a34a', 700: '#15803d', 800: '#166534', 900: '#14532d',
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

        // Carga la librería de Google Charts
        google.charts.load('current', {'packages':['corechart']});
        // Una vez que Google Charts esté cargado, llama a la función drawCharts
        google.charts.setOnLoadCallback(drawCharts);

        // Función principal para dibujar todos los gráficos
        function drawCharts() {
            console.log('drawCharts() called.');
            // Determina si el modo oscuro está activo para ajustar los colores del texto y la cuadrícula
            const isDarkMode = document.documentElement.classList.contains('dark');
            console.log('drawCharts - isDarkMode:', isDarkMode);
            const textColor = isDarkMode ? '#e5e7eb' : '#374151'; // Color de texto para modo oscuro/claro
            const gridColor = isDarkMode ? '#4b5563' : '#e5e7eb'; // Color de la cuadrícula para modo oscuro/claro

            // Función de ayuda para obtener opciones comunes del gráfico (títulos, colores de texto, etc.)
            function getChartOptions(title) {
                return {
                    title: title,
                    backgroundColor: 'transparent', // Fondo transparente para que el fondo de la página se vea
                    titleTextStyle: { color: textColor }, // Estilo del texto del título
                    legend: { textStyle: { color: textColor } }, // Estilo del texto de la leyenda
                    hAxis: { // Eje horizontal
                        textStyle: { color: textColor },
                        gridlines: { color: gridColor }
                    },
                    vAxis: { // Eje vertical
                        textStyle: { color: textColor },
                        gridlines: { color: gridColor }
                    }
                };
            }

            // --- Gráfico 1: Animales por Especie (Gráfico de Tarta) ---
            // Convierte los datos PHP a un formato de tabla de datos de Google Charts
            const animalSpeciesData = new google.visualization.arrayToDataTable(<?php echo json_encode($animal_species_data); ?>);
            const animalSpeciesChartDiv = document.getElementById('animal_species_chart');
            if (animalSpeciesChartDiv) {
                const animalSpeciesChart = new google.visualization.PieChart(animalSpeciesChartDiv);
                animalSpeciesChart.draw(animalSpeciesData, {
                    ...getChartOptions('Animales por Especie'), // Opciones comunes
                    is3D: true, // Gráfico 3D
                });
            } else {
                console.error("Elemento 'animal_species_chart' no encontrado.");
            }


            // --- Gráfico 2: Inventario por Tipo (Gráfico de Barras) ---
            const inventoryTypeData = new google.visualization.arrayToDataTable(<?php echo json_encode($inventario_tipo_data); ?>);
            const inventoryTypeChartDiv = document.getElementById('inventario_tipo_chart');
            if (inventoryTypeChartDiv) {
                const inventoryTypeChart = new google.visualization.BarChart(inventoryTypeChartDiv);
                inventoryTypeChart.draw(inventoryTypeData, {
                    ...getChartOptions('Inventario por Tipo'),
                    chartArea: {width: '50%'}, // Ajusta el ancho del área del gráfico
                });
            } else {
                console.error("Elemento 'inventario_tipo_chart' no encontrado.");
            }


            // --- Gráfico 3: Estado de Tareas (Gráfico de Tarta) ---
            const tasksStatusData = new google.visualization.arrayToDataTable([
                ['Estado', 'Cantidad'],
                ['Pendientes', <?php echo $tareas_status_data['Pendientes']; ?>],
                ['Completadas', <?php echo $tareas_status_data['Completadas']; ?>]
            ]);
            const tasksStatusChartDiv = document.getElementById('tareas_status_chart');
            if (tasksStatusChartDiv) {
                const tasksStatusChart = new google.visualization.PieChart(tasksStatusChartDiv);
                tasksStatusChart.draw(tasksStatusData, {
                    ...getChartOptions('Estado de Tareas'),
                    is3D: true,
                    colors: ['#fcd34d', '#4ade80'], // Colores personalizados: Amarillo para pendientes, verde para completadas
                });
            } else {
                console.error("Elemento 'tareas_status_chart' no encontrado.");
            }


            // --- Gráfico 4: Animales por Sexo (Gráfico de Tarta) ---
            const animalSexData = new google.visualization.arrayToDataTable(<?php echo json_encode($animal_sex_data); ?>);
            const animalSexChartDiv = document.getElementById('animal_sex_chart');
            if (animalSexChartDiv) {
                const animalSexChart = new google.visualization.PieChart(animalSexChartDiv);
                animalSexChart.draw(animalSexData, {
                    ...getChartOptions('Animales por Sexo'),
                    is3D: true,
                });
            } else {
                console.error("Elemento 'animal_sex_chart' no encontrado.");
            }


            // --- Gráfico 5: Estado de Vacunación de Animales (Gráfico de Tarta) ---
            const animalVaccinationData = new google.visualization.arrayToDataTable(<?php echo json_encode($animal_vaccination_data); ?>);
            const animalVaccinationChartDiv = document.getElementById('animal_vaccination_chart');
            if (animalVaccinationChartDiv) {
                const animalVaccinationChart = new google.visualization.PieChart(animalVaccinationChartDiv);
                animalVaccinationChart.draw(animalVaccinationData, {
                    ...getChartOptions('Estado de Vacunación de Animales'),
                    is3D: true,
                    colors: ['#4ade80', '#ef4444'], // Colores personalizados: Verde para vacunados, rojo para no vacunados
                });
            } else {
                console.error("Elemento 'animal_vaccination_chart' no encontrado.");
            }


            // --- Gráfico 6: Producción por Tipo (Gráfico de Columnas) ---
            const produccionTipoData = new google.visualization.arrayToDataTable(<?php echo json_encode($produccion_tipo_data); ?>);
            const produccionTipoChartDiv = document.getElementById('produccion_tipo_chart');
            if (produccionTipoChartDiv) {
                const produccionTipoChart = new google.visualization.ColumnChart(produccionTipoChartDiv);
                produccionTipoChart.draw(produccionTipoData, {
                    ...getChartOptions('Producción por Tipo'),
                    chartArea: {width: '70%'}, // Ajusta el ancho del área del gráfico
                });
            } else {
                console.error("Elemento 'produccion_tipo_chart' no encontrado.");
            }


            // --- Gráfico 7: Usuarios por Rol (Gráfico de Tarta - solo para administradores) ---
            <?php if ($rol === 'administrador'): ?>
            const usuariosRolData = new google.visualization.arrayToDataTable(<?php echo json_encode($usuarios_rol_data); ?>);
            const usuariosRolChartDiv = document.getElementById('usuarios_rol_chart');
            if (usuariosRolChartDiv) {
                const usuariosRolChart = new google.visualization.PieChart(usuariosRolChartDiv);
                usuariosRolChart.draw(usuariosRolData, {
                    ...getChartOptions('Usuarios por Rol'),
                    is3D: true,
                });
            } else {
                console.error("Elemento 'usuarios_rol_chart' no encontrado.");
            }
            <?php endif; ?>

            // --- Gráfico 8: Tareas por Responsable (Gráfico de Barras - solo para administradores) ---
            <?php if ($rol === 'administrador'): ?>
            const tareasPorResponsableData = new google.visualization.arrayToDataTable(<?php echo json_encode($tareas_por_responsable_data); ?>);
            const tareasPorResponsableChartDiv = document.getElementById('tareas_por_responsable_chart');
            if (tareasPorResponsableChartDiv) {
                const tareasPorResponsableChart = new google.visualization.BarChart(tareasPorResponsableChartDiv);
                tareasPorResponsableChart.draw(tareasPorResponsableData, {
                    ...getChartOptions('Tareas por Responsable'),
                    chartArea: {width: '50%'},
                });
            } else {
                console.error("Elemento 'tareas_por_responsable_chart' no encontrado.");
            }
            <?php endif; ?>

            // --- Gráfico 9: Animales por Raza (Gráfico de Tarta) ---
            const animalRazaData = new google.visualization.arrayToDataTable(<?php echo json_encode($animal_raza_data); ?>);
            const animalRazaChartDiv = document.getElementById('animal_raza_chart');
            if (animalRazaChartDiv) {
                const animalRazaChart = new google.visualization.PieChart(animalRazaChartDiv);
                animalRazaChart.draw(animalRazaData, {
                    ...getChartOptions('Animales por Raza'),
                    is3D: true,
                });
            } else {
                console.error("Elemento 'animal_raza_chart' no encontrado.");
            }
        }

        // Observa cambios en la clase 'dark' del elemento <html> para redibujar los gráficos
        document.addEventListener('DOMContentLoaded', () => {
            console.log('DOMContentLoaded: Configurando MutationObserver.');
            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.attributeName === 'class' && mutation.target === document.documentElement) {
                        console.log('MutationObserver: La clase "dark" cambió en <html>. Redibujando gráficos.');
                        drawCharts(); // Vuelve a dibujar los gráficos después del cambio de tema
                    }
                });
            });
            observer.observe(document.documentElement, { attributes: true });
        });

    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        /* Define variables CSS para los colores principales de los enlaces, adaptándose al tema */
        :root {
            --primary-link-color-light: #16a34a; /* primary-600 para modo claro */
            --primary-link-color-dark: #4ade80;  /* primary-400 para modo oscuro */
        }

        .nav-link {
            position: relative;
            overflow: hidden;
            color: #4b5563; /* Default gray-700 */
        }

        .dark .nav-link {
            color: #d1d5db; /* Default gray-300 for dark mode */
        }
        
        .nav-link::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 100%;
            height: 2px;
            background-color: var(--primary-link-color-light); /* Color por defecto */
            transform: scaleX(0);
            transform-origin: right;
            transition: transform 0.3s ease;
        }

        .dark .nav-link::after {
            background-color: var(--primary-link-color-dark); /* Color en modo oscuro */
        }
        
        .nav-link:hover,
        .nav-link.active {
            color: var(--primary-link-color-light); /* Color al pasar el ratón/activo en modo claro */
        }

        .dark .nav-link:hover,
        .dark .nav-link.active {
            color: var(--primary-link-color-dark); /* Color al pasar el ratón/activo en modo oscuro */
        }

        .nav-link:hover::after,
        .nav-link.active::after {
            transform: scaleX(1);
            transform-origin: left;
        }

        /* Estilos para el pie de página, adaptándose al tema */
        footer {
            background-color: #ffffff; /* Default light mode background */
            border-top: 1px solid #e5e7eb; /* Default light mode border */
            position: relative; /* Asegura que el z-index funcione */
            z-index: 10; /* Eleva el footer por encima de otros elementos si hay solapamiento */
        }

        .dark footer {
            background-color: #1f2937; /* dark-gray-800 background */
            border-top: 1px solid #374151; /* dark-gray-700 border */
        }

        footer .text-gray-500 {
            color: #6b7280; /* Default gray-500 */
            font-size: 0.875rem; /* text-sm */
        }

        .dark footer .text-gray-500 {
            color: #9ca3af; /* dark-gray-400 */
        }

        footer a.text-gray-500 {
            color: #6b7280; /* Default gray-500 for links */
            font-size: 0.875rem; /* text-sm */
        }

        .dark footer a.text-gray-500 {
            color: #9ca3af; /* dark-gray-400 for links */
        }

        footer a.text-gray-500:hover {
            color: #22c55e; /* primary-600 on hover */
        }

        .dark footer a.text-gray-500:hover {
            color: #4ade80; /* primary-400 on hover in dark mode */
        }

        /* Oculta elementos con x-cloak de Alpine.js hasta que Alpine se inicialice */
        [x-cloak] { display: none !important; }
    </style>
</head>
<body x-data="appData()" class="h-full bg-gray-50 font-sans text-gray-800 dark:bg-gray-900 dark:text-gray-100 transition-colors duration-200 flex flex-col min-h-screen">
    <header class="bg-white dark:bg-gray-800 shadow-sm sticky top-0 z-40">
        <div class="px-6 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="flex items-center text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400">
                        <i class="bi bi-arrow-left text-xl mr-2"></i>
                        <span class="font-medium">Volver al Panel</span>
                    </a>
                </div>
                
                <nav class="hidden md:flex items-center space-x-1">
                    </nav>
            </div>
            
            <div class="flex items-center space-x-4">
                <div class="hidden md:flex items-center space-x-4">
                    
                        <button id="theme-toggle" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none" @click="toggleTheme()">
                            <i class="bi bi-sun-fill text-yellow-500 dark:hidden"></i>
                            <i class="bi bi-moon-fill text-blue-400 hidden dark:inline"></i>
                        </button>
                    </div>
        
                </div>
                
                
            </div>
        </div>
        
        <div class="md:hidden bg-gray-50 dark:bg-gray-700 px-4 py-2">
            <div class="flex space-x-4 overflow-x-auto">
                </div>
        </div>
    </header>
    
    <div class="flex-grow p-6"> <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-6">Estadísticas Generales de la Granja</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-soft p-4">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-3">Animales por Especie</h3>
                <div id="animal_species_chart" style="width: 100%; height: 300px;"></div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-soft p-4">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-3">Inventario por Tipo</h3>
                <div id="inventario_tipo_chart" style="width: 100%; height: 300px;"></div>
            </div>
            
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-soft p-4">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-3">Estado de Tareas</h3>
                <div id="tareas_status_chart" style="width: 100%; height: 300px;"></div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-soft p-4">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-3">Animales por Sexo</h3>
                <div id="animal_sex_chart" style="width: 100%; height: 300px;"></div>
            </div>

           
        </div>
    </div>
        
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

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('appData', () => ({
                // Función para alternar el tema (claro/oscuro)
                toggleTheme() {
                    console.log('toggleTheme() called.');
                    if (document.documentElement.classList.contains('dark')) {
                        document.documentElement.classList.remove('dark');
                        localStorage.setItem('theme', 'light');
                        console.log('Tema establecido en claro.');
                    } else {
                        document.documentElement.classList.add('dark');
                        localStorage.setItem('theme', 'dark');
                        console.log('Tema establecido en oscuro.');
                    }
                    // No es necesario llamar a drawCharts() aquí directamente, ya que el MutationObserver lo manejará.
                },
                // Inicialización al cargar la página para aplicar el tema guardado o el preferido por el sistema
                init() {
                    console.log('Alpine init() llamado.');
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    const savedTheme = localStorage.getItem('theme');

                    if (savedTheme === 'dark' || (savedTheme === null && prefersDark)) {
                        document.documentElement.classList.add('dark');
                        console.log('Tema inicial aplicado: Oscuro.');
                    } else {
                        document.documentElement.classList.remove('dark');
                        console.log('Tema inicial aplicado: Claro.');
                    }
                    // El MutationObserver detectará este cambio de clase inicial y llamará a drawCharts.
                    // Si Google Charts aún no está cargado, google.charts.setOnLoadCallback se encargará del primer dibujo.
                },
            }));
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>
