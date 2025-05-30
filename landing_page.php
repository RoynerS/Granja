<?php
session_start(); // Inicia la sesión de PHP al principio del script
include 'db.php'; // Asegúrate que la ruta a tu archivo db.php sea correcta

$animales = [];
$precio_por_kg = 10000; // Define el precio por kilogramo aquí

try {
    // Obtener solo animales con estado_salud 'vacunado' o que consideres 'en venta'
    // Puedes ajustar esta condición según lo que signifique "en venta" para ti
    $stmt = $conn->query("SELECT id, nombre, especie, raza, sexo, edad, peso, estado_salud, codigo_animal FROM animales WHERE estado_salud = 'vacunado' ORDER BY nombre ASC");
    if ($stmt) {
        $animales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Error al cargar animales para la landing page: " . $e->getMessage());
    // En un entorno de producción, podrías mostrar un mensaje más amigable al usuario
    // o simplemente no mostrar animales si hay un error.
}

$rol = $_SESSION['rol'];
$usuario_logueado = isset($_SESSION['usuario_id']);
$nombre_usuario = $usuario_logueado ? htmlspecialchars($_SESSION['nombre']) : '';
?>

<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuestros Animales en Venta - Granja App</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900 font-sans text-gray-800 dark:text-gray-100 transition-colors duration-200 flex flex-col min-h-screen">
    <header class="bg-white dark:bg-gray-800 shadow-sm sticky top-0 z-40">
        <div class="px-6 py-4 flex justify-between items-center">
            <div class="flex items-center">
                <a href="landing_page.php" class="text-2xl font-bold text-primary-600 dark:text-primary-400 flex items-center">
                    <i class="bi bi-shop-window mr-2"></i> LA GRANJA DE RORON
                </a>
            </div>
            
            <div class="flex items-center space-x-4">
                 
               <?php if ($usuario_logueado): ?>
    <span class="text-lg font-medium text-gray-700 dark:text-gray-300">Hola, <?= $nombre_usuario ?></span>

    <?php
    // Asegúrate de que $user_rol esté definido, por ejemplo, al principio del script:
    // $user_rol = isset($_SESSION['user_rol']) ? $_SESSION['user_rol'] : '';
    ?>

    <?php if ($rol === 'administrador' || $rol === 'veterinario' || $rol === 'trabajador' ) :  // Aquí el 'if' anidado ?>
        <a href="dashboard.php" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors duration-200 flex items-center">
            <i class="bi bi-person-fill-gear mr-2"></i> Ir al Panel Admin
        </a>
    <?php endif; ?>

    <a href="logout.php" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-200 flex items-center">
        <i class="bi bi-box-arrow-right mr-2"></i> Cerrar Sesión
    </a>

<?php else: ?>
    <a href="login.php" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors duration-200 flex items-center">
        <i class="bi bi-box-arrow-in-right mr-2"></i> Iniciar Sesión
    </a>
    <a href="registrar.php" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600 transition-colors duration-200 flex items-center">
        <i class="bi bi-person-plus mr-2"></i> Registrar
    </a>
<?php endif; ?>

                <button id="theme-toggle" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none">
                    <i class="bi bi-sun-fill text-yellow-500 dark:hidden"></i>
                    <i class="bi bi-moon-fill text-blue-400 hidden dark:inline"></i>
                </button>
            </div>
        </div>
    </header>

    <main class="flex-grow p-6">
        <section class="text-center m-10">
            <h1 class="text-4xl font-extrabold text-gray-900 dark:text-white mb-4">Encuentra tu Próximo Animal</h1>
            <p class="text-lg text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
                Descubre nuestra selección de animales sanos y bien cuidados, listos para un nuevo hogar o para tu producción.
            </p>
        </section>

        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php if (empty($animales)): ?>
                <div class="col-span-full text-center p-8 bg-white dark:bg-gray-800 rounded-xl shadow-soft">
                    <p class="text-lg text-gray-500 dark:text-gray-400">No hay animales disponibles para la venta en este momento.</p>
                </div>
            <?php else: ?>
                <?php foreach ($animales as $animal): ?>
                    <?php 
                        $precio = $animal['peso'] * $precio_por_kg;
                        $placeholder_text = substr(htmlspecialchars($animal['nombre']), 0, 1) . ' ' . substr(htmlspecialchars($animal['especie']), 0, 1);
                        $image_url = "https://placehold.co/400x300/e5e7eb/374151?text={$placeholder_text}";
                    ?>
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-soft overflow-hidden flex flex-col">
                        <img src="<?= $image_url ?>" alt="Imagen de <?= htmlspecialchars($animal['nombre']) ?>" class="w-full h-48 object-cover">
                        <div class="p-5 flex-grow flex flex-col">
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2"><?= htmlspecialchars($animal['nombre']) ?> (<?= htmlspecialchars($animal['codigo_animal']) ?>)</h3>
                            <p class="text-gray-600 dark:text-gray-400 text-sm mb-1">
                                <span class="font-semibold">Especie:</span> <?= htmlspecialchars($animal['especie']) ?>
                            </p>
                            <p class="text-gray-600 dark:text-gray-400 text-sm mb-1">
                                <span class="font-semibold">Raza:</span> <?= htmlspecialchars($animal['raza']) ?>
                            </p>
                            <p class="text-gray-600 dark:text-gray-400 text-sm mb-1">
                                <span class="font-semibold">Edad:</span> <?= htmlspecialchars($animal['edad']) ?> años
                            </p>
                            <p class="text-gray-600 dark:text-gray-400 text-sm mb-3">
                                <span class="font-semibold">Peso:</span> <?= htmlspecialchars($animal['peso']) ?> kg
                            </p>
                            <div class="mt-auto pt-4 border-t border-gray-200 dark:border-gray-700">
                                <p class="text-2xl font-extrabold text-primary-700 dark:text-primary-400 mb-4">$<?= number_format($precio, 2) ?></p>
                                <button onclick="alert('¡Has contactado por <?= htmlspecialchars($animal['nombre']) ?>! Te contactaremos pronto.')" class="w-full py-2 px-4 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors duration-200 flex items-center justify-center">
                                    <i class="bi bi-whatsapp mr-2"></i> Contactar
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </main>

    <footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 py-6 px-6 mt-auto">
        <div class="flex flex-col md:flex-row justify-between items-center text-center md:text-left">
            <div class="text-sm text-gray-500 dark:text-gray-400 mb-2 md:mb-0">
                © <?php echo date('Y'); ?> LA GRNAJA DE RORON - Todos los derechos reservados.
            </div>
            <div class="flex space-x-4">
                <a href="#" class="text-sm text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400">Términos de Servicio</a>
                <a href="#" class="text-sm text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400">Política de Privacidad</a>
                <a href="#" class="text-sm text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400">Contáctanos</a>
            </div>
        </div>
    </footer>

    <script>
        // Script para manejar el modo oscuro
        const themeToggle = document.getElementById('theme-toggle');
        const html = document.documentElement;

        // Función para aplicar el tema
        function applyTheme(theme) {
            if (theme === 'dark') {
                html.classList.add('dark');
            } else {
                html.classList.remove('dark');
            }
            localStorage.setItem('theme', theme);
        }

        // Cargar el tema guardado o detectar la preferencia del sistema
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme) {
            applyTheme(savedTheme);
        } else if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
            applyTheme('dark');
        } else {
            applyTheme('light');
        }

        // Escuchar el evento click del botón de alternar tema
        themeToggle.addEventListener('click', () => {
            if (html.classList.contains('dark')) {
                applyTheme('light');
            } else {
                applyTheme('dark');
            }
        });
    </script>
</body>
</html>