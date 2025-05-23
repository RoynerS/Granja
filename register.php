<?php
include 'db.php'; // Asegúrate que la ruta a tu archivo db.php sea correcta

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = $_POST['nombre'];
    $correo = $_POST['correo'];
    $contrasena = $_POST['contrasena'];
    $rol = $_POST['rol'];

    // Verificar si el correo ya está registrado
    $verificar = $conn->prepare("SELECT id FROM usuarios WHERE correo = :correo");
    $verificar->bindParam(':correo', $correo);
    $verificar->execute();

    if ($verificar->rowCount() > 0) {
        $mensaje = "Este correo ya está registrado.";
    } else {
        // Encriptar contraseña
        $contrasena_hash = password_hash($contrasena, PASSWORD_DEFAULT);

        $sql = "INSERT INTO usuarios (nombre, correo, contrasena, rol) VALUES (:nombre, :correo, :contrasena, :rol)";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':correo', $correo);
        $stmt->bindParam(':contrasena', $contrasena_hash);
        $stmt->bindParam(':rol', $rol);

        if ($stmt->execute()) {
            $mensaje = "Usuario registrado correctamente. Ahora puedes <a href='login.php' class='text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-500 font-medium'>Iniciar sesión</a>";
        } else {
            $mensaje = "Error al registrar usuario.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Granja App</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script>
        tailwind.config = {
            darkMode: 'class', // Enable dark mode based on the 'dark' class in HTML
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
<body class="h-full bg-gray-100 dark:bg-gray-900 font-sans text-gray-800 dark:text-gray-100 transition-colors duration-200 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-soft-lg p-8 w-full max-w-md">
        <div class="text-center mb-6">
            <div class="flex items-center justify-center w-16 h-16 mx-auto rounded-full bg-primary-100 dark:bg-primary-900 mb-4">
                <i class="bi bi-person-plus-fill text-primary-600 dark:text-primary-300 text-3xl"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Registro de Usuario</h2>
            <p class="text-gray-500 dark:text-gray-400">Crea tu cuenta para acceder a Granja App</p>
        </div>

        <?php if ($mensaje): ?>
            <div class="mb-4 p-3 text-sm <?= strpos($mensaje, 'correctamente') !== false ? 'text-green-700 bg-green-100 dark:bg-green-200 dark:text-green-800' : 'text-red-700 bg-red-100 dark:bg-red-200 dark:text-red-800' ?> rounded-lg" role="alert">
                <?= $mensaje ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <div>
                <label for="nombre" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre:</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="bi bi-person text-gray-400"></i>
                    </div>
                    <input type="text" name="nombre" id="nombre" class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm" required autocomplete="name">
                </div>
            </div>

            <div>
                <label for="correo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Correo Electrónico:</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="bi bi-envelope text-gray-400"></i>
                    </div>
                    <input type="email" name="correo" id="correo" class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm" required autocomplete="email">
                </div>
            </div>

            <div>
                <label for="contrasena" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contraseña:</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="bi bi-lock text-gray-400"></i>
                    </div>
                    <input type="password" name="contrasena" id="contrasena" class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm" required autocomplete="new-password">
                </div>
            </div>
            
            <div>
                <label for="rol" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Rol:</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="bi bi-person-badge text-gray-400"></i>
                    </div>
                    <select name="rol" id="rol" class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                        <option value="trabajador">Trabajador</option>
                        <option value="veterinario">Veterinario</option>
                        <option value="administrador">Administrador</option>
                    </select>
                </div>
            </div>
            
            <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors duration-200">
                <i class="bi bi-person-plus mr-2"></i> Registrar
            </button>
        </form>

        <div class="mt-6 text-center">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                ¿Ya tienes una cuenta? 
                <a href="login.php" class="text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-500 font-medium">Inicia Sesión</a>
            </p>
        </div>
    </div>

    <button id="theme-toggle" class="fixed bottom-4 right-4 p-3 rounded-full bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 shadow-lg focus:outline-none transition-colors duration-200">
        <i class="bi bi-sun-fill text-yellow-500 dark:hidden"></i>
        <i class="bi bi-moon-fill text-blue-400 hidden dark:inline"></i>
    </button>

    <script>
        // Script para manejar el modo oscuro
        const themeToggle = document.getElementById('theme-toggle');
        const html = document.documentElement;

        // Function to apply the theme
        function applyTheme(theme) {
            if (theme === 'dark') {
                html.classList.add('dark');
            } else {
                html.classList.remove('dark');
            }
            localStorage.setItem('theme', theme);
        }

        // Load saved theme or detect system preference
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme) {
            applyTheme(savedTheme);
        } else if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
            applyTheme('dark');
        } else {
            applyTheme('light');
        }

        // Listen for theme toggle button click
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
