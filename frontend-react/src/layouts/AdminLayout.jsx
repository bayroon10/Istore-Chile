import { Link, Outlet } from 'react-router-dom'

export default function AdminLayout() {
  return (
    <div style={{ minHeight: '100vh', background: '#121212', color: 'white' }}>
      
      {/* BARRA DE NAVEGACIÓN */}
      <nav style={{ background: '#1d1d1f', padding: '15px 40px', display: 'flex', gap: '20px', alignItems: 'center', borderBottom: '1px solid #333' }}>
        <h3 style={{ color: 'white', margin: 0, marginRight: '20px' }}> iStore Admin</h3>
        <Link to="/admin" style={{ color: '#f5f5f7', textDecoration: 'none', fontWeight: 'bold' }}>Panel</Link>
        <Link to="/admin/inventario" style={{ color: '#f5f5f7', textDecoration: 'none', fontWeight: 'bold' }}>Inventario</Link>
        <Link to="/admin/pedidos" style={{ color: '#f5f5f7', textDecoration: 'none', fontWeight: 'bold' }}>Órdenes y Envíos</Link>
        
        <div style={{ marginLeft: 'auto', display: 'flex', gap: '20px', alignItems: 'center' }}>
          <Link to="/" style={{ color: '#ff9500', textDecoration: 'none', fontWeight: 'bold' }}>Ir a la Tienda ➔</Link>
          <button onClick={() => { localStorage.removeItem('token_istore'); window.location.href = '/login'; }} style={{ background: 'transparent', border: '1px solid #ff3b30', color: '#ff3b30', padding: '5px 15px', borderRadius: '20px', cursor: 'pointer', fontWeight: 'bold', transition: '0.2s' }}>
            Salir
          </button>
        </div>
      </nav>

      {/* 🌟 AQUÍ SE PROYECTAN LAS VISTAS (LA PANTALLA DE CINE) */}
      <div style={{ padding: '40px', maxWidth: '1200px', margin: '0 auto', fontFamily: 'sans-serif' }}>
        <Outlet />
      </div>

    </div>
  )
}