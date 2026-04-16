<div align="center">

# 🛒 iStore Chile — Tech & Wear Edition

**E-commerce moderno de tecnología y ropa, desarrollado con React + PHP + PostgreSQL**

[
[
[
[
[

### 🔗 [Ver demo en vivo → istore-chile.vercel.app](https://istore-chile.vercel.app)

</div>

***

## 📋 Descripción

**iStore Chile** es un e-commerce full-stack desarrollado desde cero, enfocado en productos de tecnología y ropa. Cuenta con flujo de compra completo, gestión de productos, carrito de compras y proceso de checkout integrado con base de datos en la nube.

Este proyecto fue construido como parte de mi portfolio para demostrar capacidad de desarrollo web full-stack en un contexto real de producción.

***

## ✨ Funcionalidades

- 🛍️ **Catálogo de productos** con imágenes, categorías y filtros
- 🛒 **Carrito de compras** con persistencia de estado
- 💳 **Checkout completo** con formulario de datos y confirmación de pedido
- 🔐 **Autenticación de usuarios** (registro, login, sesión)
- 📦 **Gestión de órdenes** y estado de compra
- 📱 **Diseño responsive** adaptado a móvil y escritorio
- 🚀 **Deploy continuo** en Vercel con más de 42 despliegues en producción

***

## 🛠️ Stack Tecnológico

### Frontend
| Tecnología | Uso |
|---|---|
| **React** | Framework principal UI |
| **JavaScript (ES6+)** | Lógica del cliente |
| **HTML5 / CSS3** | Estructura y estilos |
| **Vercel** | Deploy y hosting |

### Backend
| Tecnología | Uso |
|---|---|
| **PHP** | API REST y lógica de negocio |
| **PostgreSQL** | Base de datos relacional en la nube |
| **APIs REST** | Comunicación frontend ↔ backend |

***

## 🗂️ Estructura del Proyecto

```
Istore-Chile/
├── frontend-react/          # Aplicación React
│   ├── src/
│   │   ├── components/      # Componentes reutilizables
│   │   ├── pages/           # Vistas principales
│   │   └── ...
│   └── package.json
│
└── backend-php/             # API PHP
    ├── api/                 # Endpoints REST
    ├── config/              # Configuración BD
    └── ...
```

***

## 🚀 Instalación local

### Requisitos
- Node.js 18+
- PHP 8+
- PostgreSQL o conexión a base de datos cloud

### Frontend
```bash
cd frontend-react
npm install
npm run dev
```

### Backend
```bash
cd backend-php
# Configura las variables de entorno en config/database.php
# Asegúrate de tener PHP y una conexión a PostgreSQL activa
php -S localhost:8000
```

### Variables de entorno (Frontend)
```env
VITE_API_URL=http://localhost:8000
```

***

## 📸 Screenshots

> _Próximamente — agrega capturas de pantalla del catálogo, carrito y checkout_

***

## 📈 Estado del Proyecto

- ✅ Catálogo de productos
- ✅ Carrito de compras
- ✅ Checkout completo
- ✅ Autenticación de usuarios
- ✅ Deploy en producción (Vercel)
- 🔄 Pasarela de pago (en desarrollo)
- 🔄 Panel de administración (próximamente)

***

## 👤 Autor

**Bairon Meneses**
- GitHub: [@bayroon10](https://github.com/bayroon10)
- LinkedIn: [linkedin.com/in/baironmeneses](https://linkedin.com/in/baironmeneses)
- Deploy: [istore-chile.vercel.app](https://istore-chile.vercel.app)

***

<div align="center">
  <sub>Desarrollado con 💻 por Bairon Meneses — Santiago, Chile 🇨🇱</sub>
</div>
