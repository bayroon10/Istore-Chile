import { BrowserRouter as Router, Routes, Route, Navigate, NavLink } from 'react-router-dom'
import { useAuth } from './contexts/AuthContext'

import Tienda from './pages/Tienda'
import Login from './pages/login'
import Inventario from './pages/Inventario'
import Dashboard from './pages/Dashboard'
import Pedidos from './pages/Pedidos'
import AdminLayout from './layouts/AdminLayout'
import MiCuenta from './pages/MiCuenta'
import ChatAssistant from './components/ChatAssistant'

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
  const { user } = useAuth(); // Importar el estado del usuario

  return (
    <div className="fixed top-6 left-1/2 -translate-x-1/2 z-50 px-4 w-full max-w-2xl">
      <nav
        className="glass-dark px-6 py-3 rounded-full flex items-center justify-between shadow-2xl backdrop-blur-xl border border-white/10"
      >
        <div className="flex items-center gap-2">
          <span className="text-xl font-black tracking-tighter text-white">iStore<span className="text-urban-blue"></span></span>
        </div>

        <div className="flex items-center gap-1">
          <NavLink to="/" className={({ isActive }) =>
            `px-4 py-2 rounded-full text-sm font-bold transition-all duration-300 ${isActive ? 'bg-urban-blue/20 text-urban-blue shadow-neon-blue border border-urban-blue/30' : 'text-gray-400 hover:text-white'}`
          }>
            Catálogo
          </NavLink>
          <NavLink to="/mi-cuenta" className={({ isActive }) =>
            `px-4 py-2 rounded-full text-sm font-bold transition-all duration-300 ${isActive ? 'bg-urban-blue/20 text-urban-blue shadow-neon-blue border border-urban-blue/30' : 'text-gray-400 hover:text-white'}`
          }>
            Cuenta
          </NavLink>

          {/* 🛡️ NAVEGACIÓN SELECTIVA: Solo admin ve el botón */}
          {user?.role === 'admin' && (
            <NavLink to="/admin" className={({ isActive }) =>
              `px-4 py-2 rounded-full text-sm font-bold transition-all duration-300 ${isActive ? 'bg-urban-blue/20 text-urban-blue shadow-neon-blue border border-urban-blue/30' : 'text-gray-400 hover:text-white'}`
            }>
              Admin
            </NavLink>
          )}
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
            <Route path="/acceso-secreto-bairon" element={<Login />} />

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

        {/* 🤖 ASISTENTE VIRTUAL (GLOBAL) */}
        <ChatAssistant />
      </div>
    </Router>
  )
}