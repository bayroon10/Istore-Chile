import { BrowserRouter as Router, Routes, Route, Navigate, NavLink } from 'react-router-dom'
import { useAuth } from './contexts/AuthContext'
import { useCart } from './contexts/CartContext'
import useScrollShrink from './hooks/useScrollShrink'

import Tienda from './pages/Tienda'
import Login from './pages/login'
import Inventario from './pages/Inventario'
import Dashboard from './pages/Dashboard'
import Pedidos from './pages/Pedidos'
import AdminLayout from './layouts/AdminLayout'
import MiCuenta from './pages/MiCuenta'
import ChatAssistant from './components/ChatAssistant'
import CartDrawer from './components/CartDrawer'

// ==========================================
// 🛡️ GUARDIA DE SEGURIDAD (SOLO ADMINS)
// ==========================================
function RutaProtegida({ children }) {
  const { isAdmin, loading } = useAuth();

  if (loading) {
    return (
      <div className="flex justify-center items-center min-h-screen bg-pitch-black text-white text-lg">
        <div className="animate-pulse flex flex-col items-center gap-4">
          <div className="w-12 h-12 border-4 border-urban-blue border-t-transparent rounded-full animate-spin"></div>
          <span>Identificando sesión...</span>
        </div>
      </div>
    );
  }

  if (isAdmin) {
    return children;
  }

  // 🚪 "Patitas a la calle" -> Al home si no es admin
  return <Navigate to="/" />;
}

// 🌐 COMPONENTE DE NAVEGACIÓN FLOTANTE (PILLS)
function Navbar() {
  const { user } = useAuth();
  const { totalItems, toggleCarrito } = useCart();
  const scrolled = useScrollShrink();

  // Base NavLink classes — pill style with responsive padding
  const linkClass = ({ isActive }) =>
    `relative px-2.5 py-1.5 sm:px-4 sm:py-2 rounded-full text-xs sm:text-sm font-bold transition-all duration-300 ${
      isActive
        ? 'bg-urban-blue/20 text-urban-blue shadow-neon-blue border border-urban-blue/30'
        : 'text-gray-400 hover:text-white'
    }`;

  return (
    <div className="fixed top-4 sm:top-6 left-1/2 -translate-x-1/2 z-50 px-3 sm:px-4 w-full max-w-2xl">
      <nav
        className={`glass-dark px-3 sm:px-6 rounded-full flex items-center justify-between shadow-2xl backdrop-blur-xl border border-white/10 transition-all duration-300 ${
          scrolled ? 'py-2 sm:py-3' : 'py-3 sm:py-5'
        }`}
      >
        {/* Logo */}
        <div className="flex items-center gap-1.5">
          <span className="text-lg sm:text-xl font-black tracking-tighter text-white">
            iStore<span className="text-urban-blue"></span>
          </span>
        </div>

        {/* Links + Cart */}
        <div className="flex items-center gap-1">
          <NavLink to="/" className={linkClass}>Catálogo</NavLink>

          <NavLink to="/mi-cuenta" className={linkClass}>Cuenta</NavLink>

          {/* 🛡️ Solo admin */}
          {user?.role === 'admin' && (
            <NavLink to="/admin" className={linkClass}>Admin</NavLink>
          )}

          {/* 🛍️ Global Cart Trigger Pill */}
          <button
            onClick={toggleCarrito}
            aria-label="Abrir bolsa de compras"
            className="relative px-2.5 py-1.5 sm:px-4 sm:py-2 rounded-full text-xs sm:text-sm font-bold glass text-gray-300 hover:text-white hover:border-urban-blue/40 transition-all flex items-center gap-1.5 cursor-pointer ml-1"
          >
            <span>🛍️</span>
            {totalItems > 0 && (
              <span
                key={totalItems}
                className="inline-flex items-center justify-center w-4 h-4 rounded-full bg-urban-blue text-white text-[9px] font-black"
                style={{ animation: 'flip-number 300ms ease-in-out' }}
              >
                {totalItems}
              </span>
            )}
          </button>
        </div>
      </nav>
    </div>
  );
}

export default function App() {
  return (
    <Router>
      <div className="bg-pitch-black min-h-screen text-white relative">

        {/* ✨ NAVEGACIÓN FLOTANTE */}
        <Navbar />

        <main className="pt-24 pb-12 transition-all duration-500">
          <Routes>
            {/* RUTAS PÚBLICAS */}
            <Route path="/" element={<Tienda />} />
            <Route path="/tienda" element={<Tienda />} />
            <Route path="/mi-cuenta" element={<MiCuenta />} />
            <Route path="/admin/login" element={<Login />} />

            {/* RUTAS PRIVADAS (ADMIN) */}
            <Route path="/admin" element={
              <RutaProtegida>
                <AdminLayout />
              </RutaProtegida>
            }>
              <Route index element={<Dashboard />} />
              <Route path="inventario" element={<Inventario />} />
              <Route path="pedidos" element={<Pedidos />} />
            </Route>
          </Routes>
        </main>

        {/* 🛒 BOLSA DE COMPRAS (GLOBAL) */}
        <CartDrawer />

        {/* 🤖 ASISTENTE VIRTUAL (GLOBAL) */}
        <ChatAssistant />
      </div>
    </Router>
  )
}