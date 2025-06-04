-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 04-06-2025 a las 16:41:13
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `granja_db`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `animales`
--

CREATE TABLE `animales` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `especie` varchar(50) DEFAULT NULL,
  `raza` varchar(50) DEFAULT NULL,
  `sexo` enum('macho','hembra') DEFAULT NULL,
  `edad` int(11) DEFAULT NULL,
  `peso` decimal(6,2) DEFAULT NULL,
  `estado_salud` text DEFAULT NULL,
  `fecha_ingreso` date DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `vacunado` tinyint(1) DEFAULT 0,
  `codigo_animal` varchar(50) NOT NULL,
  `fecha_ultima_vacuna` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `animales`
--

INSERT INTO `animales` (`id`, `nombre`, `especie`, `raza`, `sexo`, `edad`, `peso`, `estado_salud`, `fecha_ingreso`, `usuario_id`, `vacunado`, `codigo_animal`, `fecha_ultima_vacuna`) VALUES
(12, 'Pepito', 'Vaca', 'Jersey', 'macho', 12, 100.00, 'vacunado', '2025-05-23', NULL, 0, 'G5H6I', '2025-05-29 01:30:34'),
(14, 'Rosado', 'Cerdo', 'Pietrain', 'macho', 1, 150.20, 'vacunado', '2025-05-29', NULL, 0, 'J7K8L', '2025-05-30 16:18:45'),
(17, 'Pepe', 'Vaca', 'Holstein', 'hembra', 12, 200.00, 'sin vacuna', '2025-05-30', NULL, 0, 'B36KO', '2025-06-01 05:10:25'),
(18, 'pp', 'Gallina', 'Plymouth Rock', 'hembra', 12, 1.45, 'vacunado', '2025-06-01', NULL, 0, 'B36K1', '2025-06-04 05:25:26');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias_inventario`
--

CREATE TABLE `categorias_inventario` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `categorias_inventario`
--

INSERT INTO `categorias_inventario` (`id`, `nombre`, `descripcion`) VALUES
(1, 'Alimentos', 'Alimentos para animales'),
(2, 'Medicamentos', 'Medicinas y vacunas para animales'),
(3, 'Herramientas', 'Herramientas agrícolas y de mantenimiento'),
(4, 'Semillas', 'Semillas para cultivo'),
(5, 'Fertilizantes', 'Abonos y fertilizantes'),
(6, 'Equipo', 'Maquinaria y equipo pesado'),
(7, 'Otros', 'Otros suministros varios'),
(8, 'Producción', 'Productos derivados de animales (leche, huevos, carne, etc.)');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inventario`
--

CREATE TABLE `inventario` (
  `id` int(11) NOT NULL,
  `tipo` enum('alimento','medicamento','herramienta','semilla','fertilizante','equipo','produccion','otro') NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `cantidad` int(11) DEFAULT NULL,
  `unidad` varchar(50) DEFAULT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha_ingreso` date NOT NULL,
  `precio` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `inventario`
--

INSERT INTO `inventario` (`id`, `tipo`, `nombre`, `cantidad`, `unidad`, `fecha_vencimiento`, `descripcion`, `fecha_ingreso`, `precio`) VALUES
(5, 'medicamento', 'Naproseno', 18, NULL, NULL, 'Vaca', '2025-05-22', 10000.00),
(7, 'alimento', 'Concentrado para Cerdos', 250, 'kg', '2025-11-30', 'Alimento balanceado para cerdos en crecimiento', '2024-05-15', 0.85),
(8, 'medicamento', 'Vacuna Antirrábica', 49, 'dosis', '2026-08-01', 'Vacuna preventiva para animales de granja', '2024-06-01', 12.50),
(9, 'herramienta', 'Motosierra Stihl', 1, 'unidad', NULL, 'Motosierra de gasolina para poda y tala', '2024-03-10', 350.00),
(10, 'semilla', 'Semillas de Maíz Híbrido', 100, 'kg', '2025-02-28', 'Maíz de alto rendimiento para cultivo', '2024-04-05', 2.10),
(11, 'fertilizante', 'Urea Granulada', 500, 'kg', NULL, 'Fertilizante nitrogenado para suelos', '2024-01-20', 0.50),
(12, 'equipo', 'Bomba de Agua Sumergible', 1, 'unidad', NULL, 'Bomba para riego de cultivos', '2023-11-01', 180.00),
(13, 'otro', 'Desinfectante para Estabulos', 20, 'litros', '2026-10-01', 'Desinfectante de amplio espectro para superficies de granja', '2024-02-18', 8.75),
(14, 'alimento', 'Heno de Alfalfa', 1000, 'kg', '2024-09-30', 'Heno de alta calidad para rumiantes', '2024-05-20', 0.30),
(15, 'medicamento', 'Vitaminas para Aves', 98, 'pastillas', '2025-10-15', 'Suplemento vitamínico para gallinas ponedoras', '2024-07-01', 5.20),
(16, 'herramienta', 'Carretilla de Jardín', 2, 'unidades', NULL, 'Carretilla de metal resistente para transporte de materiales', '2024-06-20', 75.00),
(17, 'semilla', 'Semillas de Trigo', 200, 'kg', '2026-01-31', 'Variedad de trigo de alto rendimiento para siembra', '2024-08-10', 1.50),
(18, 'fertilizante', 'Compost Orgánico', 300, 'kg', NULL, 'Abono natural para mejorar la calidad del suelo', '2024-09-01', 0.25),
(19, 'equipo', 'Bebedero Automático', 5, 'unidades', NULL, 'Bebedero con sensor para aves de corral', '2024-04-12', 30.00),
(20, 'otro', 'Guantes de Trabajo', 10, 'pares', NULL, 'Guantes de cuero reforzados para tareas agrícolas', '2024-03-05', 7.00),
(21, 'alimento', 'Maíz en Grano', 10, 'kg', '2025-07-31', 'Maíz para alimentación de ganado en general', '2024-05-25', 0.60),
(22, 'medicamento', 'Desparasitante Bovino', 10, 'dosis', '2026-04-20', 'Tratamiento contra parásitos internos en bovinos', '2024-06-15', 22.00),
(23, 'equipo', 'Jabon', 10, NULL, NULL, '', '2025-05-30', 20000.00),
(27, 'produccion', 'Huevos Criollos', 20, NULL, NULL, '', '2025-06-02', 500.00),
(28, 'produccion', 'Leche', 188, NULL, NULL, 'Litro', '2025-06-02', 1000.00),
(30, 'produccion', 'Trigo', 12, NULL, NULL, 'eada', '2025-06-03', 10000.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `produccion`
--

CREATE TABLE `produccion` (
  `id` int(11) NOT NULL,
  `animal_id` int(11) DEFAULT NULL,
  `tipo_produccion` enum('leche','huevos','carne') DEFAULT NULL,
  `cantidad` decimal(10,2) DEFAULT NULL,
  `unidad` varchar(50) DEFAULT NULL,
  `fecha` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tareas`
--

CREATE TABLE `tareas` (
  `id` int(11) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `hora` time DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `completado` tinyint(1) DEFAULT 0,
  `animal_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `contrasena` varchar(255) DEFAULT NULL,
  `rol` enum('administrador','trabajador','veterinario') DEFAULT 'trabajador',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `correo`, `contrasena`, `rol`, `creado_en`) VALUES
(7, 'admin', 'admin@gmail.com', '$2y$10$c0qIh5Rph.fwPc91DyVbg.rqToWgu4t6gv6/xE3tfEX/.Uw4YC/X2', 'administrador', '2025-05-30 03:51:09'),
(10, 'mario', 'm@gmail.com', '$2y$10$.KGGZ7j1v3xb14ZJcftHYemhffdSiOvLNGqbXV/I5rKQ068qmI05W', 'veterinario', '2025-05-30 14:18:09'),
(13, 'roro', 'roynersimon@gmail.com', '$2y$10$BInYmFfFXEv6JOLfN6f32eEz9pLoAWEPbEnIUSBPnenQ.CJdPrpP6', '', '2025-06-02 16:16:44'),
(15, 'Luis', 'l@gmail.com', '$2y$10$FMXEdkY/oA4fzDwY2NuAOuBDj2qLyIZHodRNy6wx9wErX4g5bLxSm', 'trabajador', '2025-06-04 13:16:48');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `vacunas`
--

CREATE TABLE `vacunas` (
  `id` int(11) NOT NULL,
  `animal_id` int(11) DEFAULT NULL,
  `nombre_vacuna` varchar(100) DEFAULT NULL,
  `fecha_aplicacion` date DEFAULT NULL,
  `proxima_dosis` date DEFAULT NULL,
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `animales`
--
ALTER TABLE `animales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `categorias_inventario`
--
ALTER TABLE `categorias_inventario`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `inventario`
--
ALTER TABLE `inventario`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `produccion`
--
ALTER TABLE `produccion`
  ADD PRIMARY KEY (`id`),
  ADD KEY `animal_id` (`animal_id`);

--
-- Indices de la tabla `tareas`
--
ALTER TABLE `tareas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `tareas_ibfk_2` (`animal_id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `correo` (`correo`);

--
-- Indices de la tabla `vacunas`
--
ALTER TABLE `vacunas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `animal_id` (`animal_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `animales`
--
ALTER TABLE `animales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT de la tabla `categorias_inventario`
--
ALTER TABLE `categorias_inventario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `inventario`
--
ALTER TABLE `inventario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT de la tabla `produccion`
--
ALTER TABLE `produccion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tareas`
--
ALTER TABLE `tareas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `vacunas`
--
ALTER TABLE `vacunas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `animales`
--
ALTER TABLE `animales`
  ADD CONSTRAINT `animales_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `produccion`
--
ALTER TABLE `produccion`
  ADD CONSTRAINT `produccion_ibfk_1` FOREIGN KEY (`animal_id`) REFERENCES `animales` (`id`);

--
-- Filtros para la tabla `tareas`
--
ALTER TABLE `tareas`
  ADD CONSTRAINT `tareas_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `tareas_ibfk_2` FOREIGN KEY (`animal_id`) REFERENCES `animales` (`id`);

--
-- Filtros para la tabla `vacunas`
--
ALTER TABLE `vacunas`
  ADD CONSTRAINT `vacunas_ibfk_1` FOREIGN KEY (`animal_id`) REFERENCES `animales` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
