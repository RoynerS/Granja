<?php
include 'db.php';

// Inicializar la variable mensaje
$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = $_POST['nombre'];
    $correo = $_POST['correo'];
    $contrasena = $_POST['contrasena'];
    
    // Asignar el rol por defecto
    $rol = 'cliente';

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
            $mensaje = "success:Usuario registrado correctamente. Ahora puedes <a href='login.php' class='text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-500 font-medium'>Iniciar sesión</a>";
            // Redirigir al usuario al login después de un registro exitoso
            header("Location:index.php");
            exit();
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
    <link rel="shortcut icon" href="./uploads/logo.png" type="image/x-icon">
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
                            DEFAULT: '#22c55e'
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
        
        body {
            transition: background-color 0.3s ease, color 0.2s ease;
        }
        
        input, button {
            transition: all 0.2s ease;
        }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900 font-sans text-gray-800 dark:text-gray-100 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-soft-lg p-8 w-full max-w-md animate-fade-in">
        <div class="text-center mb-8 animate-fade-in">
            <div class="flex items-center justify-center w-20 h-20 mx-auto rounded-full bg-primary-100 dark:bg-primary-900/50 mb-4 shadow-inner-xl animate-float">
                <i class="bi bi-tree-fill text-primary-600 dark:text-primary-300 text-4xl"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Registro en Granja App</h2>
            <p class="text-gray-500 dark:text-gray-400">Crea tu cuenta para comenzar</p>
        </div>

        <?php if ($mensaje): ?>
            <?php 
                $isSuccess = strpos($mensaje, 'success:') === 0;
                $mensajeContent = $isSuccess ? substr($mensaje, 8) : $mensaje;
            ?>
            <div class="mb-6 p-3 text-sm <?= $isSuccess ? 'text-green-700 bg-green-100 dark:bg-green-200 dark:text-green-800' : 'text-red-700 bg-red-100 dark:bg-red-200 dark:text-red-800' ?> rounded-lg flex items-start" role="alert">
                <i class="bi <?= $isSuccess ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill' ?> mr-2 mt-0.5"></i>
                <span><?= $mensajeContent ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <div>
                <label for="nombre" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Nombre Completo</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="bi bi-person text-gray-400"></i>
                    </div>
                    <input type="text" name="nombre" id="nombre" 
                           class="block w-full pl-10 pr-3 py-3 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg shadow-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 sm:text-sm placeholder-gray-400 dark:placeholder-gray-500" 
                           placeholder="Tu nombre completo" 
                           required 
                           autocomplete="name">
                </div>
            </div>

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
                           autocomplete="new-password">
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Mínimo 8 caracteres</p>
            </div>
            
            
            
            <button type="submit" class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all duration-200 hover:shadow-md">
                <i class="bi bi-person-plus mr-2"></i> Registrarse
            </button>
        </form>

        <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700 text-center">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                ¿Ya tienes una cuenta? 
                <a href="index.php" class="font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300">Inicia Sesión</a>
            </p>
        </div>
    </div>

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