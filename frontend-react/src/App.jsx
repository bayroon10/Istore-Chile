import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom'
import { useAuth } from './contexts/AuthContext'

import Tienda from './pages/Tienda'
import Login from './pages/login' 
import Inventario from './pages/Inventario'
import Dashboard from './pages/Dashboard'
import Pedidos from './pages/Pedidos'
import AdminLayout from './layouts/AdminLayout'
import MiCuenta from './pages/MiCuenta'

// ==========================================
// 🛡️ GUARDIA DE SEGURIDAD (SOLO ADMINS)
// ==========================================
function RutaProtegida({ children }) {
  const { isAdmin, loading } = useAuth();

  // Mientras carga la autenticación, mostramos un loader
  if (loading) {
    return (
      <div style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '100vh', background: '#000', color: 'white', fontSize: '18px' }}>
        Cargando...
      </div>
    );
  }

  if (isAdmin) {
    return children; 
  }
  
  return <Navigate to="/mi-cuenta" />; 
}

export default function App() {
  return (
    <Router>
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
    </Router>
  )
}