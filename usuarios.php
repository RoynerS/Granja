<?php
// Inicia la sesión. Es crucial que esto esté al principio de cada script que use sesiones.
session_start();

// Redirige al login si el usuario no ha iniciado sesión
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Incluye el archivo de conexión a la base de datos.
// Asegúrate de que la ruta a 'db.php' sea correcta y que este archivo establezca
// una conexión PDO a tu base de datos.
include 'db.php'; 

$nombre_usuario_sesion = $_SESSION['nombre']; // Nombre del usuario logueado
$rol_usuario_sesion = $_SESSION['rol'];      // Rol del usuario logueado
$id_usuario_sesion = $_SESSION['usuario_id']; // ID del usuario logueado

// Restringir acceso solo a administradores
// Si el usuario no es administrador, se le deniega el acceso y se redirige al dashboard.
if ($rol_usuario_sesion !== 'administrador') {
    $_SESSION['message'] = "Acceso denegado. Solo administradores pueden gestionar usuarios.";
    $_SESSION['message_type'] = "error";
    header("Location: dashboard.php"); // Redirige a una página a la que tenga acceso
    exit();
}

$message = ''; // Variable para almacenar mensajes de éxito o error
$message_type = ''; // Variable para el tipo de mensaje (success, error)

// Función auxiliar para establecer mensajes en la sesión y que persistan entre redirecciones.
function set_message($msg, $type) {
    $_SESSION['message'] = $msg;
    $_SESSION['message_type'] = $type;
}

// Detecta si la solicitud es AJAX para enviar una respuesta JSON
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Procesar acciones (agregar, editar, eliminar) cuando se recibe una solicitud POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? ''; // Obtiene la acción a realizar (add o edit)
    $response = ['success' => false, 'message' => '']; // Para respuestas AJAX

    // Lógica para agregar un nuevo usuario
    if ($action === 'add') {
        $nombre = trim($_POST['nombre'] ?? '');
        $correo = trim($_POST['correo'] ?? ''); 
        $contrasena = $_POST['contrasena'] ?? '';
        $rol = $_POST['rol'] ?? 'trabajador'; // Rol por defecto si no se especifica
        
        // Validaciones de los campos del formulario
        if (empty($nombre) || empty($correo) || empty($contrasena) || empty($rol)) {
            $response['message'] = "Todos los campos son obligatorios para agregar un usuario.";
        } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) { 
            $response['message'] = "Formato de correo inválido.";
        } else {
            try {
                // Verificar si el correo ya está registrado en la base de datos
                $stmt = $conn->prepare("SELECT COUNT(*) FROM usuarios WHERE correo = ?"); 
                $stmt->execute([$correo]);
                if ($stmt->fetchColumn() > 0) {
                    $response['message'] = "El correo ya está registrado.";
                } else {
                    // Encriptar la contraseña antes de almacenarla por seguridad
                    $hashed_password = password_hash($contrasena, PASSWORD_BCRYPT);
                    // Insertar el nuevo usuario en la tabla 'usuarios'
                    $stmt = $conn->prepare("INSERT INTO usuarios (nombre, correo, contrasena, rol) VALUES (?, ?, ?, ?)"); 
                    if ($stmt->execute([$nombre, $correo, $hashed_password, $rol])) {
                        $response['success'] = true;
                        $response['message'] = "Usuario agregado exitosamente.";
                    } else {
                        $response['message'] = "Error al agregar el usuario.";
                    }
                }
            } catch (PDOException $e) {
                // Registrar el error en el log del servidor
                error_log("Error al agregar usuario: " . $e->getMessage());
                $response['message'] = "Error de base de datos al agregar usuario: " . $e->getMessage();
            }
        }
    } 
    // Lógica para editar un usuario existente
    elseif ($action === 'edit') {
        $id = $_POST['id'] ?? 0;
        $nombre = trim($_POST['nombre'] ?? '');
        $correo = trim($_POST['correo'] ?? ''); 
        $contrasena = $_POST['contrasena_edit'] ?? ''; // Contraseña opcional para edición
        $rol = $_POST['rol'] ?? 'trabajador';

        // Validaciones de los campos del formulario de edición
        if (empty($nombre) || empty($correo) || empty($rol)) {
            $response['message'] = "Todos los campos son obligatorios para editar un usuario (excepto contraseña si no se cambia).";
        } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) { 
            $response['message'] = "Formato de correo inválido.";
        } else {
            try {
                // Verificar si el correo ya está registrado por otro usuario (excluyendo el usuario actual)
                $stmt = $conn->prepare("SELECT COUNT(*) FROM usuarios WHERE correo = ? AND id != ?"); 
                $stmt->execute([$correo, $id]);
                if ($stmt->fetchColumn() > 0) {
                    $response['message'] = "El correo ya está registrado para otro usuario.";
                } else {
                    // Construir la consulta SQL para actualizar el usuario
                    $sql = "UPDATE usuarios SET nombre = ?, correo = ?, rol = ? WHERE id = ?"; 
                    $params = [$nombre, $correo, $rol, $id];

                    // Si se proporciona una nueva contraseña, encriptarla y añadirla a la consulta
                    if (!empty($contrasena)) {
                        $hashed_password = password_hash($contrasena, PASSWORD_BCRYPT);
                        $sql = "UPDATE usuarios SET nombre = ?, correo = ?, contrasena = ?, rol = ? WHERE id = ?"; 
                        $params = [$nombre, $correo, $hashed_password, $rol, $id];
                    }

                    $stmt = $conn->prepare($sql);
                    if ($stmt->execute($params)) {
                        $response['success'] = true;
                        $response['message'] = "Usuario actualizado exitosamente.";
                    } else {
                        $response['message'] = "Error al actualizar el usuario.";
                    }
                }
            } catch (PDOException $e) {
                // Registrar el error en el log del servidor
                error_log("Error al editar usuario: " . $e->getMessage());
                $response['message'] = "Error de base de datos al editar usuario: " . $e->getMessage();
            }
        }
    }

    // Si la solicitud es AJAX, devuelve la respuesta JSON y termina la ejecución
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    } else {
        // Si no es AJAX, redirige (esto solo debería ocurrir si JS está deshabilitado o hay un error)
        set_message($response['message'], $response['success'] ? 'success' : 'error');
        header("Location: usuarios.php"); 
        exit(); 
    }
} 
// Procesar acciones (eliminar) cuando se recibe una solicitud GET
elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    // Lógica para eliminar un usuario
    if ($action === 'delete') {
        $id = $_GET['id'] ?? 0; // Obtiene el ID del usuario a eliminar

        // No permitir que un usuario se elimine a sí mismo mientras está logueado
        if ($id == $id_usuario_sesion) {
            set_message("No puedes eliminar tu propia cuenta mientras estás logueado.", "error");
        } elseif ($id > 0) {
            try {
                // Iniciar una transacción para asegurar la integridad de los datos
                $conn->beginTransaction();

                // 1. Eliminar registros de la tabla `tareas` asociados a este usuario
                $stmt = $conn->prepare("DELETE FROM tareas WHERE usuario_id = ?");
                $stmt->execute([$id]);

                // // 2. Obtener los IDs de los animales asociados a este usuario
                // $stmt_animal_ids = $conn->prepare("SELECT id FROM animales WHERE usuario_id = ?");
                // $stmt_animal_ids->execute([$id]);
                // $animal_ids = $stmt_animal_ids->fetchAll(PDO::FETCH_COLUMN);

                // // Si hay animales asociados, eliminar sus registros relacionados en otras tablas
                // if (!empty($animal_ids)) {
                //     // Crear placeholders para la cláusula IN de SQL
                //     $placeholders = implode(',', array_fill(0, count($animal_ids), '?'));

                //     // 3. Eliminar registros de la tabla `vacunas` asociados a los animales del usuario
                //     $stmt = $conn->prepare("DELETE FROM vacunas WHERE animal_id IN ($placeholders)");
                //     $stmt->execute($animal_ids);

                //     // 4. Eliminar registros de la tabla `produccion` asociados a los animales del usuario
                //     $stmt = $conn->prepare("DELETE FROM produccion WHERE animal_id IN ($placeholders)");
                //     $stmt->execute($animal_ids);

                //     // 5. Eliminar registros de la tabla `tareas` asociados a los animales del usuario (si no se eliminaron ya por usuario_id)
                //     // Esto es importante si una tarea está asociada a un animal y no directamente al usuario que se elimina,
                //     // pero el animal pertenece a ese usuario.
                //     $stmt = $conn->prepare("DELETE FROM tareas WHERE animal_id IN ($placeholders)");
                //     $stmt->execute($animal_ids);

                //     // 6. Eliminar registros de la tabla `animales` asociados a este usuario
                //     $stmt = $conn->prepare("DELETE FROM animales WHERE usuario_id = ?");
                //     $stmt->execute([$id]);
                // }

                // 7. Finalmente, eliminar el usuario de la tabla `usuarios`
                $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
                if ($stmt->execute([$id])) {
                    $conn->commit(); // Confirmar la transacción si todo fue exitoso
                    set_message("Usuario eliminado exitosamente.", "success");
                } else {
                    $conn->rollBack(); // Revertir la transacción si hubo un error en la eliminación del usuario
                    set_message("Error al eliminar el usuario.", "error");
                }
            } catch (PDOException $e) {
                $conn->rollBack(); // Revertir la transacción en caso de cualquier error de base de datos
                error_log("Error al eliminar usuario: " . $e->getMessage());
                set_message("Error de base de datos al eliminar usuario: " . $e->getMessage(), "error");
            }
        } else {
            set_message("ID de usuario no válido para eliminar.", "error");
        }
        // Redirige después de procesar la acción de eliminación
        header("Location: usuarios.php"); 
        exit(); 
    }
}

// Recuperar mensajes de sesión para mostrarlos en la interfaz de usuario
// Estos mensajes son para las redirecciones completas (ej. eliminación o si JS está deshabilitado)
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']); // Limpiar el mensaje de la sesión para que no se muestre de nuevo
    unset($_SESSION['message_type']);
}

// Obtener todos los usuarios de la base de datos para mostrar en la tabla
$users = [];
try {
    $stmt = $conn->query("SELECT id, nombre, correo, rol, creado_en FROM usuarios ORDER BY nombre ASC"); 
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al obtener usuarios: " . $e->getMessage());
    set_message("Error al cargar la lista de usuarios: " . $e->getMessage(), "error");
    // Si hay un error al cargar usuarios, asegúrate de que el mensaje se muestre
    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        $message_type = $_SESSION['message_type'];
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    }
}
?>

<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Granja</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script>
        // Configuración de Tailwind CSS para personalizar colores, fuentes y sombras.
        tailwind.config = {
            darkMode: 'class', // Habilita el modo oscuro basado en la clase 'dark' en el elemento html
            theme: {
                extend: {
                    colors: {
                        // Define una paleta de colores 'primary' personalizada.
                        primary: {
                            50: '#f0fdf4', 100: '#dcfce7', 200: '#bbf7d0', 300: '#86efac', 400: '#4ade80', 500: '#22c55e', 600: '#16a34a', 700: '#15803d', 800: '#166534', 900: '#14532d',
                        },
                    },
                    fontFamily: {
                        // Establece 'Inter' como la fuente principal.
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                    },
                    boxShadow: {
                        // Define sombras personalizadas para un estilo "soft".
                        'soft': '0 4px 20px -2px rgba(0, 0, 0, 0.08)',
                        'soft-lg': '0 10px 30px -3px rgba(0, 0, 0, 0.12)',
                    }
                }
            }
        }

        // Se define el componente Alpine.js 'appData' dentro de alpine:init.
        // Esto asegura que Alpine.js esté completamente cargado antes de intentar definir el componente.
        document.addEventListener('alpine:init', () => {
            console.log('Alpine:init event fired. Registrando appData...'); // Mensaje de depuración
            Alpine.data('appData', () => ({
                // Estados de visibilidad de los modales
                openAddModal: false,
                openEditModal: false,
                openConfirmModal: false,
                // Objeto para almacenar los datos del usuario que se está editando
                editingUser: { id: '', nombre: '', correo: '', rol: '', contrasena_edit: '' }, // Añadido contrasena_edit
                // Variables para el formulario de agregar usuario (se inicializan aquí para x-model)
                add_nombre: '',
                add_correo: '',
                add_contrasena: '',
                add_rol: 'trabajador', // Valor por defecto para el rol en el modal de añadir
                userToDeleteId: null, // ID del usuario a eliminar
                modalMessage: { text: '', type: '' }, // Mensaje para mostrar dentro de los modales

                // Función para alternar entre el modo claro y oscuro
                toggleTheme() {
                    if (document.documentElement.classList.contains('dark')) {
                        document.documentElement.classList.remove('dark');
                        localStorage.setItem('theme', 'light'); // Guarda la preferencia en localStorage
                    } else {
                        document.documentElement.classList.add('dark');
                        localStorage.setItem('theme', 'dark'); // Guarda la preferencia en localStorage
                    }
                },
                // Función de inicialización de Alpine.js, se ejecuta al cargar el componente.
                init() {
                    console.log('appData init() method called.'); // Mensaje de depuración
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    const savedTheme = localStorage.getItem('theme');

                    // Aplica el tema guardado en localStorage o detecta la preferencia del sistema
                    if (savedTheme === 'dark' || (savedTheme === null && prefersDark)) {
                        document.documentElement.classList.add('dark');
                    } else {
                        document.documentElement.classList.remove('dark');
                    }
                },

                // Resetea los campos del formulario de agregar usuario y los mensajes del modal
                resetAddUserForm() {
                    this.add_nombre = '';
                    this.add_correo = '';
                    this.add_contrasena = '';
                    this.add_rol = 'trabajador'; // Restablece al valor por defecto
                    this.modalMessage = { text: '', type: '' }; // Limpia mensajes del modal
                },
                // Resetea los campos del formulario de editar usuario y los mensajes del modal
                resetEditUserForm() {
                    this.editingUser = { id: '', nombre: '', correo: '', rol: '', contrasena_edit: '' }; // Asegura que contrasena_edit se limpie
                    this.modalMessage = { text: '', type: '' }; // Limpia mensajes del modal
                },

                // Abre el modal de edición y carga los datos del usuario seleccionado
                editUser(user) {
                    console.log('editUser called with:', user); // Depuración: Verifica el objeto 'user'
                    this.editingUser = { ...user, contrasena_edit: '' }; // Copia el objeto user y limpia contrasena_edit
                    this.modalMessage = { text: '', type: '' }; // Limpia mensajes del modal al abrir
                    this.openEditModal = true;
                },
                // Abre el modal de confirmación de eliminación y guarda el ID del usuario
                confirmDelete(id) {
                    console.log('confirmDelete called with ID:', id); // Depuración
                    this.userToDeleteId = id;
                    this.openConfirmModal = true;
                },
                // Ejecuta la eliminación del usuario después de la confirmación
                deleteConfirmed() {
                    console.log('Delete confirmed for ID:', this.userToDeleteId); // Depuración
                    if (this.userToDeleteId) {
                        // Redirige a la misma página con la acción de eliminar y el ID del usuario
                        // Esta acción seguirá recargando la página para confirmar la eliminación.
                        window.location.href = `usuarios.php?action=delete&id=${this.userToDeleteId}`;
                    }
                },

                // Envía el formulario de agregar usuario de forma asíncrona
                async submitAddUserForm() {
                    this.modalMessage = { text: 'Procesando...', type: 'info' }; // Mensaje de carga
                    const formData = new FormData();
                    formData.append('action', 'add');
                    formData.append('nombre', this.add_nombre);
                    formData.append('correo', this.add_correo);
                    formData.append('contrasena', this.add_contrasena);
                    formData.append('rol', this.add_rol);

                    try {
                        const response = await fetch('usuarios.php', {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest' // Indica que es una solicitud AJAX
                            }
                        });
                        const result = await response.json(); // Espera una respuesta JSON

                        if (result.success) {
                            this.modalMessage = { text: result.message, type: 'success' };
                            // Cierra el modal y recarga la página después de un breve retraso para mostrar el éxito y actualizar la tabla.
                            setTimeout(() => {
                                this.openAddModal = false;
                                window.location.reload(); // Recarga completa para actualizar la tabla de usuarios
                            }, 1500); 
                        } else {
                            this.modalMessage = { text: result.message, type: 'error' };
                        }
                    } catch (error) {
                        console.error('Error al agregar usuario:', error);
                        this.modalMessage = { text: 'Error de conexión o del servidor.', type: 'error' };
                    }
                },

                // Envía el formulario de editar usuario de forma asíncrona
                async submitEditUserForm() {
                    this.modalMessage = { text: 'Procesando...', type: 'info' }; // Mensaje de carga
                    const formData = new FormData();
                    formData.append('action', 'edit');
                    formData.append('id', this.editingUser.id);
                    formData.append('nombre', this.editingUser.nombre);
                    formData.append('correo', this.editingUser.correo);
                    formData.append('rol', this.editingUser.rol);
                    // Solo envía la contraseña si no está vacía
                    if (this.editingUser.contrasena_edit) {
                        formData.append('contrasena_edit', this.editingUser.contrasena_edit);
                    }

                    try {
                        const response = await fetch('usuarios.php', {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest' // Indica que es una solicitud AJAX
                            }
                        });
                        const result = await response.json(); // Espera una respuesta JSON

                        if (result.success) {
                            this.modalMessage = { text: result.message, type: 'success' };
                            // Cierra el modal y recarga la página después de un breve retraso para mostrar el éxito y actualizar la tabla.
                            setTimeout(() => {
                                this.openEditModal = false;
                                window.location.reload(); // Recarga completa para actualizar la tabla de usuarios
                            }, 1500); 
                        } else {
                            this.modalMessage = { text: result.message, type: 'error' };
                        }
                    } catch (error) {
                        console.error('Error al editar usuario:', error);
                        this.modalMessage = { text: 'Error de conexión o del servidor.', type: 'error' };
                    }
                }
            }));
        });
        console.log('Script tag finished executing.'); // Mensaje de depuración: Final del script
    </script>
    <style>
        /* Importa la fuente 'Inter' de Google Fonts. */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        /* Estilos generales para botones y enlaces, usando @apply de Tailwind para reutilización */
        .btn-primary {
            @apply bg-primary-600 text-white px-4 py-2 rounded-lg hover:bg-primary-700 transition duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-opacity-50;
        }
        .btn-secondary {
            @apply bg-gray-200 text-gray-800 px-4 py-2 rounded-lg hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-100 dark:hover:bg-gray-700 transition duration-200 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-opacity-50;
        }
        .btn-danger {
            @apply bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition duration-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-50;
        }
        .btn-icon {
            @apply p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 transition duration-200;
        }

        /* Estilos para modales */
        .modal-overlay {
            @apply fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4;
        }
        .modal-content {
            /* Estilos base para el contenido del modal (tamaño mediano) */
            @apply bg-white dark:bg-gray-800 rounded-lg shadow-soft-lg p-6 w-full max-w-md max-h-[90vh] overflow-y-auto transform scale-95 opacity-0 transition-all duration-300 ease-out;
        }
        .modal-content-small {
            /* Estilos para el contenido del modal (tamaño pequeño, ej. confirmación) */
            @apply bg-white dark:bg-gray-800 rounded-lg shadow-soft-lg p-6 w-full max-w-sm transform scale-95 opacity-0 transition-all duration-300 ease-out;
        }
        /* Estilos para la transición de entrada de los modales */
        [x-show].modal-content, [x-show].modal-content-small {
            transform: scale(1);
            opacity: 1;
        }

        /* Estilos para mensajes de alerta (éxito/error) */
        .message-box {
            @apply p-4 mb-4 rounded-lg text-sm;
        }
        .message-box.success {
            @apply bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100;
        }
        .message-box.error {
            @apply bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100;
        }
        /* Estilo para mensajes de información/carga */
        .message-box.info {
            @apply bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100;
        }


        /* Estilos del pie de página para asegurar visibilidad y consistencia */
        footer {
            background-color: #ffffff;
            border-top: 1px solid #e5e7eb;
            position: relative;
            z-index: 10; /* Asegura que el footer esté por encima de otros elementos si hay superposiciones */
        }

        .dark footer {
            background-color: #1f2937;
            border-top: 1px solid #374151;
        }

        footer .text-gray-500 {
            color: #6b7280;
            font-size: 0.875rem;
        }

        .dark footer .text-gray-500 {
            color: #9ca3af;
        }

        footer a.text-gray-500 {
            color: #6b7280;
            font-size: 0.875rem;
        }

        .dark footer a.text-gray-500 {
            color: #9ca3af;
        }

        footer a.text-gray-500:hover {
            color: #22c55e;
        }

        .dark footer a.text-gray-500:hover {
            color: #4ade80;
        }
    </style>
</head>
<body x-data="appData()" x-init="init()" class="h-full bg-gray-50 font-sans text-gray-800 dark:bg-gray-900 dark:text-gray-100 transition-colors duration-200 flex flex-col min-h-screen">
    <header class="bg-white dark:bg-gray-800 shadow-sm sticky top-0 z-40">
        <div class="px-6 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <div class="flex items-center space-x-4">
                    <a href="./dashboard.php" class="flex items-center text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400">
                        <i class="bi bi-arrow-left text-xl mr-2"></i>
                        <span class="font-medium">Volver al Panel</span>
                    </a>
                </div>
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
    </header>
    
    <div class="flex-grow p-6">
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-6">Gestión de Usuarios</h2>

        <?php if ($message): ?>
            <div class="message-box <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Botón para abrir el modal de "Nuevo Usuario".
             Se llama a resetAddUserForm() para limpiar los campos del formulario antes de abrir el modal. -->
        <button @click="openAddModal = true; resetAddUserForm()" class="btn-primary mb-6">
            <i class="bi bi-plus-circle mr-2"></i> Nuevo Usuario
        </button>

        <div class="overflow-x-auto bg-white dark:bg-gray-800 rounded-lg shadow-soft">
            <table class="w-full text-left whitespace-nowrap">
                <thead>
                    <tr class="bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 uppercase text-sm leading-normal">
                        <th class="py-3 px-6 text-left">ID</th>
                        <th class="py-3 px-6 text-left">Nombre</th>
                        <th class="py-3 px-6 text-left">Correo</th>
                        <th class="py-3 px-6 text-left">Rol</th>
                        <th class="py-3 px-6 text-left">Creado En</th>
                        <th class="py-3 px-6 text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody class="text-gray-700 dark:text-gray-200 text-sm font-light">
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="6" class="py-3 px-6 text-center">No hay usuarios registrados.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="py-3 px-6"><?php echo htmlspecialchars($user['id']); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($user['nombre']); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($user['correo']); ?></td>
                                <td class="py-3 px-6 capitalize"><?php echo htmlspecialchars($user['rol']); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($user['creado_en']))); ?></td>
                                <td class="py-3 px-6 text-center">
                                    <div class="flex item-center justify-center space-x-2">
                                        <button @click="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)" 
                                                class="btn-icon text-blue-500 hover:text-blue-700" title="Editar">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button @click="confirmDelete(<?php echo htmlspecialchars($user['id']); ?>)" 
                                                class="btn-icon text-red-500 hover:text-red-700" title="Eliminar">
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

    <div x-cloak x-show="openAddModal" x-transition class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-agregar-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen p-4"> 
            <div x-show="openAddModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" @click="openAddModal = false; resetAddUserForm()"></div>
            
            <div x-show="openAddModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle w-full max-w-lg sm:max-w-2xl"> 
                <form @submit.prevent="submitAddUserForm()">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-primary-100 dark:bg-primary-900 sm:mx-0 sm:h-10 sm:w-10">
                                <i class="bi bi-plus-circle text-primary-600 dark:text-primary-300"></i>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="modal-agregar-title">
                                    Añadir Nuevo Usuario
                                </h3>
                                <div class="mt-4 space-y-4">
                                    <div x-show="modalMessage.text" :class="{
                                        'message-box success': modalMessage.type === 'success',
                                        'message-box error': modalMessage.type === 'error',
                                        'message-box info': modalMessage.type === 'info'
                                    }" class="mb-4">
                                        <span x-text="modalMessage.text"></span>
                                    </div>
                                    <div>
                                        <label for="add_nombre" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nombre:</label>
                                        <input type="text" id="add_nombre" x-model="add_nombre" required
                                               class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label for="add_correo" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Correo Electrónico:</label>
                                        <input type="email" id="add_correo" x-model="add_correo" required
                                               class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label for="add_contrasena" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Contraseña:</label>
                                        <input type="password" id="add_contrasena" x-model="add_contrasena" required
                                               class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label for="add_rol" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Rol:</label>
                                        <select id="add_rol" x-model="add_rol" required
                                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                                            <option value="trabajador">Trabajador</option>
                                            <option value="administrador">Administrador</option>
                                            <option value="veterinario">veterinario</option>
                                            <option value="">Usuario</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Añadir Usuario
                        </button>
                        <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-white hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm" @click="openAddModal = false; resetAddUserForm()">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div x-cloak x-show="openEditModal" x-transition class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-editar-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen p-4"> 
            <div x-show="openEditModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" @click="openEditModal = false; resetEditUserForm()"></div>
            
            <div x-show="openEditModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle w-full max-w-lg sm:max-w-2xl"> 
                <form @submit.prevent="submitEditUserForm()" x-show="editingUser.id"> 
                    <input type="hidden" name="id" :value="editingUser.id">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 dark:bg-yellow-900 sm:mx-0 sm:h-10 sm:w-10">
                                <i class="bi bi-pencil-fill text-yellow-600 dark:text-yellow-300"></i>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="modal-editar-title">
                                    Editar Usuario
                                </h3>
                                <div class="mt-4 space-y-4">
                                    <div x-show="modalMessage.text" :class="{
                                        'message-box success': modalMessage.type === 'success',
                                        'message-box error': modalMessage.type === 'error',
                                        'message-box info': modalMessage.type === 'info'
                                    }" class="mb-4">
                                        <span x-text="modalMessage.text"></span>
                                    </div>
                                    <div>
                                        <label for="edit_nombre" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nombre:</label>
                                        <input type="text" id="edit_nombre" x-model="editingUser.nombre" required
                                               class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label for="edit_correo" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Correo Electrónico:</label>
                                        <input type="email" id="edit_correo" x-model="editingUser.correo" required
                                               class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label for="edit_contrasena" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nueva Contraseña (opcional):</label>
                                        <input type="password" id="edit_contrasena" x-model="editingUser.contrasena_edit"
                                               class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Deja en blanco para mantener la contraseña actual.</p>
                                    </div>
                                    <div>
                                        <label for="edit_rol" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Rol:</label>
                                        <select id="edit_rol" x-model="editingUser.rol" required
                                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                                            <option value="trabajador">Trabajador</option>
                                            <option value="administrador">Administrador</option>
                                            <option value="veterinario">veterinario</option>
                                            <option value="">Usuario</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Actualizar Usuario
                        </button>
                        <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-white hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm" @click="openEditModal = false; resetEditUserForm()">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div x-cloak x-show="openConfirmModal" x-transition class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-confirm-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen p-4"> 
            <div x-show="openConfirmModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" @click="openConfirmModal = false"></div>
            
            <div x-show="openConfirmModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle w-full max-w-sm"> 
                <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900 sm:mx-0 sm:h-10 sm:w-10">
                            <i class="bi bi-exclamation-triangle-fill text-red-600 dark:text-red-300"></i>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="modal-confirm-title">
                                Confirmar Eliminación
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    ¿Estás seguro de que quieres eliminar este usuario? Esta acción es irreversible y eliminará todos los datos asociados (tareas).
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" @click="deleteConfirmed()" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Eliminar
                    </button>
                    <button type="button" @click="openConfirmModal = false" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-white hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
