# 🛡️ LabStock Pro

[![Laravel 12](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel)](https://laravel.com)
[![React 19](https://img.shields.io/badge/React-19.x-61DAFB?style=for-the-badge&logo=react)](https://react.dev)
[![Vite 7](https://img.shields.io/badge/Vite-7.x-646CFF?style=for-the-badge&logo=vite)](https://vitejs.dev)
[![Tailwind v4](https://img.shields.io/badge/Tailwind_v4-38B2AC?style=for-the-badge&logo=tailwind-css)](https://tailwindcss.com)
[![PHP 8.3](https://img.shields.io/badge/PHP-8.3-777BB4?style=for-the-badge&logo=php)](https://www.php.net)

**LabStock Pro** es un ecosistema e-commerce de grado industrial diseñado para el suministro táctico y tecnológico. Este proyecto demuestra una arquitectura Full Stack robusta, optimizada para el rendimiento, la escalabilidad y la mantenibilidad, siguiendo los estándares más exigentes de la ingeniería de software actual.

---

## ⚡ Key Features (Technical Flex)

Este proyecto no es solo un e-commerce; es una vitrina de prácticas avanzadas de desarrollo:

*   **📈 Paginación Dinámica Server-Side:** Implementación de un catálogo impulsado 100% por el backend. Evita la sobrecarga de memoria en el cliente al procesar miles de registros, gestionando filtros y búsqueda directamente en la capa de persistencia.
*   **⏱️ Frontend Optimization (Debounced Search):** Uso de un custom hook `useDebounce` propietario. Esta técnica previene la saturación de la API (API flooding) al retrasar las peticiones de búsqueda hasta que el usuario deja de escribir, mejorando drásticamente la UX y reduciendo costos de infraestructura.
*   **🧪 Automated Resilient Testing:** Suite de pruebas automatizadas con **PHPUnit**. Los tests de integración (Feature Tests) se ejecutan en un entorno aislado con **SQLite (In-Memory)**, garantizando que la lógica de negocio, autenticación y transacciones sean resilientes ante cualquier cambio sin depender de una base de datos externa.
*   **💎 Gilded Heirloom Design System:** Sistema de diseño personalizado construido sobre **Tailwind CSS v4**. Utiliza tokens semánticos, utilidades de glassmorphism y una estética "Tech-Wear" premium que prioriza la legibilidad y la interacción fluida.

---

## 🏗️ Arquitectura

El proyecto está desacoplado siguiendo el patrón de **Single Page Application (SPA)** con una comunicación estricta vía **RESTful API**:

*   **Backend:** Laravel 12 actuando como una API Stateless. Gestiona la lógica de negocio, seguridad (Sanctum/Spatie), integración con Stripe y almacenamiento en la nube.
*   **Frontend:** React 19 (Vite) enfocado en una interfaz de usuario reactiva, gestión de estado eficiente y consumo de API optimizado.

---

## ⚙️ Instalación Local (Laragon)

Sigue estos pasos para desplegar el entorno de desarrollo en tu máquina local:

### 1. Clonar el repositorio
```bash
git clone https://github.com/bayroon10/Istore-Chile.git labstock-pro
cd labstock-pro
```

### 2. Configuración del Backend (Laravel)
```bash
cd backend-php
composer install
cp .env.example .env
# Configura tus credenciales de DB en el .env
php artisan key:generate
php artisan migrate --seed
```

### 3. Configuración del Frontend (React)
```bash
cd ../frontend-react
npm install
cp .env.example .env
```

---

## 🚀 Comandos Clave

| Acción | Comando |
| :--- | :--- |
| **Levantar Entorno Dev** | `npm run dev` (En ambas carpetas) |
| **Ejecutar Suite de Tests** | `php artisan test` |
| **Optimizar Backend** | `php artisan optimize` |
| **Build de Producción** | `npm run build` |

---

> [!TIP]
> **LabStock Pro** utiliza **Stripe** en modo de prueba para todas las transacciones financieras, garantizando un flujo de pago seguro y verificado.
