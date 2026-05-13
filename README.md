# 🍎 iStore Chile

[![Laravel 12](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel)](https://laravel.com)
[![React 19](https://img.shields.io/badge/React-19.x-61DAFB?style=for-the-badge&logo=react)](https://react.dev)
[![Vite 7](https://img.shields.io/badge/Vite-7.x-646CFF?style=for-the-badge&logo=vite)](https://vitejs.dev)
[![Tailwind v4](https://img.shields.io/badge/Tailwind_v4-38B2AC?style=for-the-badge&logo=tailwind-css)](https://tailwindcss.com)
[![PHP 8.3](https://img.shields.io/badge/PHP-8.3-777BB4?style=for-the-badge&logo=php)](https://www.php.net)

**iStore Chile** es un ecosistema e-commerce premium diseñado para la venta de productos tecnológicos Apple. Este proyecto demuestra una arquitectura Full Stack robusta, optimizada para el rendimiento y la escalabilidad, integrando IA y servicios de pago modernos.

---

## ⚡ Key Features (Technical Flex)

*   **📈 Paginación Dinámica Server-Side:** Catálogo impulsado 100% por el backend con filtros avanzados y búsqueda optimizada en PostgreSQL.
*   **🤖 Asistente IA (Santi):** Integración con **Google Gemini API** para consultas de stock y asesoría técnica personalizada.
*   **💳 Checkout Seguro:** Flujo de pago completo con **Stripe** (modo test), incluyendo manejo de webhooks.
*   **📸 Gestión Cloud:** Almacenamiento y optimización de imágenes mediante **Cloudinary**.
*   **⏱️ Frontend Optimization:** Uso de custom hooks como `useDebounce` para prevenir API flooding y mejorar la UX.

---

## 🏗️ Arquitectura

*   **Backend:** Laravel 12 (API Stateless) + PostgreSQL (Neon.tech). Gestión de inventario, órdenes y seguridad con Sanctum.
*   **Frontend:** React 19 + Vite 7 + Tailwind CSS v4. SPA reactiva desplegada en Vercel.
*   **Infraestructura:** Docker (Backend) desplegado en Koyeb/Railway.

---

## ⚙️ Instalación Local

### 1. Clonar el repositorio
```bash
git clone https://github.com/bayroon10/Istore-Chile.git istore-chile
cd istore-chile
```

### 2. Configuración del Backend (Laravel)
```bash
cd backend-php
composer install
# Copia el .env y configura tus credenciales (DB, Stripe, Gemini, Cloudinary)
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

### 3. Configuración del Frontend (React)
```bash
cd ../frontend-react
npm install
# Configura el VITE_API_URL en tu .env
npm run dev
```

---

## 🚀 Comandos Clave

| Acción | Comando |
| :--- | :--- |
| **Levantar Entorno Dev** | `php artisan serve` / `npm run dev` |
| **Ejecutar Tests** | `php artisan test` |
| **Optimizar Producción** | `php artisan optimize` |

---

> [!TIP]
> **iStore Chile** utiliza **Stripe** en modo de prueba para todas las transacciones financieras.
