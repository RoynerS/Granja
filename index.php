<?php
session_start();
include 'db.php'; // Asegúrate que la ruta a tu archivo db.php sea correcta

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $correo = $_POST['correo'];
    $contrasena = $_POST['contrasena'];

    try {
        $sql = "SELECT * FROM usuarios WHERE correo = :correo";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':correo', $correo);
        $stmt->execute();

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verifica la contraseña
        if ($usuario && password_verify($contrasena, $usuario['contrasena'])) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['nombre'] = $usuario['nombre'];
            $_SESSION['rol'] = $usuario['rol'];
            if ($usuario['rol'] === '') {
                header("Location: landing_page.php"); 
            } else {
                header("Location: dashboard.php");
            }
            exit(); // Es importante usar exit() después de un header Location
        } else {
            $mensaje = "Correo o contraseña incorrectos.";
        }
    } catch (PDOException $e) {
        error_log("Error de login: " . $e->getMessage());
        $mensaje = "Error en el servidor. Inténtalo de nuevo más tarde.";
    }
}
?>


<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Granja App</title>
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
                            DEFAULT: '#22c55e' // Añadido color DEFAULT para mejor consistencia
                        },
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                    },
                    boxShadow: {
                        'soft': '0 4px 20px -2px rgba(0, 0, 0, 0.08)',
                        'soft-lg': '0 10px 30px -3px rgba(0, 0, 0, 0.12)',
                        'inner-xl': 'inset 0 2px 4px 0 rgba(0, 0, 0, 0.05)'
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.3s ease-in-out',
                        'float': 'float 3s ease-in-out infinite'
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0', transform: 'translateY(10px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' }
                        },
                        float: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-5px)' }
                        }
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        /* Mejor transición para modo oscuro */
        body {
            transition: background-color 0.3s ease, color 0.2s ease;
        }
        
        /* Suavizar transiciones de inputs */
        input, button {
            transition: all 0.2s ease;
        }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900 font-sans text-gray-800 dark:text-gray-100 flex items-center justify-center p-4">
    <!-- Contenedor principal con animación de entrada -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-soft-lg p-8 w-full max-w-md animate-fade-in">
        <!-- Logo con animación sutil -->
        <div class="text-center mb-8 animate-fade-in">
            <div class="flex items-center justify-center w-20 h-20 mx-auto rounded-full bg-primary-100 dark:bg-primary-900/50 mb-4 shadow-inner-xl animate-float">
                <i class="bi bi-tree-fill text-primary-600 dark:text-primary-300 text-4xl"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Bienvenido a LA GRANJA DE RORON</h2>
            <p class="text-gray-500 dark:text-gray-400">Gestiona tu granja de manera eficiente</p>
        </div>


        <form method="POST" class="space-y-6">
            <div>
                <label for="correo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Correo Electrónico</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="bi bi-envelope text-gray-400"></i>
                    </div>
                    <input type="email" name="correo" id="correo" 
                           class="block w-full pl-10 pr-3 py-3 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg shadow-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 sm:text-sm placeholder-gray-400 dark:placeholder-gray-500" 
                           placeholder="tu@email.com" 
                           required 
                           autocomplete="email">
                </div>
            </div>

            <div>
                <label for="contrasena" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Contraseña</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="bi bi-lock text-gray-400"></i>
                    </div>
                    <input type="password" name="contrasena" id="contrasena" 
                           class="block w-full pl-10 pr-3 py-3 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg shadow-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 sm:text-sm placeholder-gray-400 dark:placeholder-gray-500" 
                           placeholder="••••••••" 
                           required 
                           autocomplete="current-password">
                </div>
            </div>
            
            <div class="flex items-center justify-between">
                
                
            </div>
            
            <button type="submit" class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all duration-200 hover:shadow-md">
                <i class="bi bi-box-arrow-in-right mr-2"></i> Iniciar Sesión
            </button>
        </form>

        <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700 text-center">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                ¿No tienes una cuenta? 
                <a href="./register.php" class="font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300">Regístrate</a>
            </p>
        </div>
    </div>

    <!-- Botón de tema con mejor diseño -->
    <button id="theme-toggle" type="button" class="fixed bottom-6 right-6 p-3 rounded-full bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 shadow-lg hover:shadow-xl focus:outline-none transition-all duration-300 hover:scale-110 border border-gray-200 dark:border-gray-600">
        <i class="bi bi-sun-fill text-yellow-500 dark:hidden"></i>
        <i class="bi bi-moon-fill text-blue-400 hidden dark:inline"></i>
        <span class="sr-only">Cambiar tema</span>
    </button>

    <script>
        // Script para manejar el modo oscuro (mejorado)
        const themeToggle = document.getElementById('theme-toggle');
        const html = document.documentElement;

        // Función para aplicar el tema
        function applyTheme(theme) {
            if (theme === 'dark') {
                html.classList.add('dark');
                document.querySelector('meta[name="theme-color"]')?.setAttribute('content', '#111827');
            } else {
                html.classList.remove('dark');
                document.querySelector('meta[name="theme-color"]')?.setAttribute('content', '#f9fafb');
            }
            localStorage.setItem('theme', theme);
        }

        // Verificar tema al cargar
        function checkTheme() {
            const savedTheme = localStorage.getItem('theme');
            const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            
            if (savedTheme) {
                applyTheme(savedTheme);
            } else if (systemPrefersDark) {
                applyTheme('dark');
            } else {
                applyTheme('light');
            }
            
            // Añadir meta tag para color de tema en móviles
            if (!document.querySelector('meta[name="theme-color"]')) {
                const meta = document.createElement('meta');
                meta.name = 'theme-color';
                meta.content = html.classList.contains('dark') ? '#111827' : '#f9fafb';
                document.head.appendChild(meta);
            }
        }

        // Escuchar cambios en la preferencia del sistema
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
            if (!localStorage.getItem('theme')) {
                applyTheme(e.matches ? 'dark' : 'light');
            }
        });

        // Alternar tema manualmente
        themeToggle.addEventListener('click', () => {
            const isDark = html.classList.contains('dark');
            applyTheme(isDark ? 'light' : 'dark');
        });

        // Inicializar tema al cargar
        document.addEventListener('DOMContentLoaded', checkTheme);
    </script>
</body>
</html>