import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';

export default function Dashboard() {
  const [productos, setProductos] = useState([]);
  const [estadisticas, setEstadisticas] = useState({ kpis: {}, grafico: [] });
  const token = localStorage.getItem('token_istore');

  const API_PRODUCTOS = 'https://istore-backend-nxvt.onrender.com/api/productos';
  const API_ESTADISTICAS = 'https://istore-backend-nxvt.onrender.com/api/estadisticas';

  useEffect(() => { 
    // 1. Traemos los productos para tus cálculos de bodega
    fetch(API_PRODUCTOS)
      .then(res => res.json())
      .then(data => setProductos(data));

    // 2. Traemos las finanzas y el gráfico desde Laravel
    fetch(API_ESTADISTICAS, { headers: { 'Authorization': `Bearer ${token}` } })
      .then(res => res.json())
      .then(data => setEstadisticas(data));
  }, [token])

  // 🧮 MATEMÁTICAS PARA LA BODEGA (Tu código original)
  const totalProductos = productos.length;
  const capitalInvertido = productos.reduce((suma, p) => suma + (p.precio * p.stock_actual), 0);
  const productosBajoStock = productos.filter(p => p.stock_actual <= 3 && p.stock_actual > 0);
  const productosAgotados = productos.filter(p => p.stock_actual === 0);

  return (
    <div style={{ color: 'white' }}>
      <h2 style={{ fontSize: '28px', marginBottom: '10px' }}>📊 Resumen del Negocio</h2>
      <p style={{ color: '#888', marginBottom: '30px' }}>Bienvenido al panel de control de iStore.</p>

      {/* 🌟 4 TARJETAS DE ESTADÍSTICAS FUSIONADAS */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: '20px', marginBottom: '30px' }}>
        
        <div style={{ background: '#2c2c2e', padding: '25px', borderRadius: '16px', borderLeft: '5px solid #0071e3' }}>
          <p style={{ color: '#888', margin: '0 0 10px 0', fontWeight: 'bold', fontSize: '13px' }}>INGRESOS POR VENTAS</p>
          <h3 style={{ fontSize: '32px', margin: 0, color: 'white' }}>${estadisticas.kpis.ingresos?.toLocaleString() || 0}</h3>
        </div>

        <div style={{ background: '#2c2c2e', padding: '25px', borderRadius: '16px', borderLeft: '5px solid #8e8e93' }}>
          <p style={{ color: '#888', margin: '0 0 10px 0', fontWeight: 'bold', fontSize: '13px' }}>CAPITAL EN BODEGA</p>
          <h3 style={{ fontSize: '32px', margin: 0, color: 'white' }}>${capitalInvertido.toLocaleString()}</h3>
        </div>

        <div style={{ background: '#2c2c2e', padding: '25px', borderRadius: '16px', borderLeft: '5px solid #34c759' }}>
          <p style={{ color: '#888', margin: '0 0 10px 0', fontWeight: 'bold', fontSize: '13px' }}>PEDIDOS COMPLETADOS</p>
          <h3 style={{ fontSize: '32px', margin: 0, color: 'white' }}>{estadisticas.kpis.pedidos || 0}</h3>
        </div>

        <div style={{ background: '#2c2c2e', padding: '25px', borderRadius: '16px', borderLeft: '5px solid #ff3b30' }}>
          <p style={{ color: '#888', margin: '0 0 10px 0', fontWeight: 'bold', fontSize: '13px' }}>ALERTAS DE STOCK</p>
          <h3 style={{ fontSize: '32px', margin: 0, color: '#ff3b30' }}>{productosBajoStock.length + productosAgotados.length}</h3>
        </div>

      </div>

      {/* 📈 EL GRÁFICO MAJESTUOSO (Adaptado a tu modo oscuro) */}
      <div style={{ background: '#1d1d1f', padding: '30px', borderRadius: '24px', marginBottom: '30px', boxShadow: '0 10px 30px rgba(0,0,0,0.5)' }}>
        <h3 style={{ marginTop: 0, marginBottom: '20px', color: 'white', borderBottom: '1px solid #333', paddingBottom: '15px' }}>📈 Rendimiento de los últimos 7 días</h3>
        <div style={{ width: '100%', height: '300px' }}>
          <ResponsiveContainer width="100%" height="100%">
            <AreaChart data={estadisticas.grafico} margin={{ top: 10, right: 10, left: -20, bottom: 0 }}>
              <defs>
                <linearGradient id="colorVentas" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="5%" stopColor="#0071e3" stopOpacity={0.8}/>
                  <stop offset="95%" stopColor="#0071e3" stopOpacity={0}/>
                </linearGradient>
              </defs>
              <XAxis dataKey="fecha" stroke="#888" tick={{fontSize: 12}} />
              <YAxis stroke="#888" tick={{fontSize: 12}} />
              <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#333" />
              <Tooltip contentStyle={{ borderRadius: '10px', border: 'none', background: '#2c2c2e', color: 'white', boxShadow: '0 5px 15px rgba(0,0,0,0.5)' }} />
              <Area type="monotone" dataKey="total_ventas" stroke="#0071e3" strokeWidth={3} fillOpacity={1} fill="url(#colorVentas)" />
            </AreaChart>
          </ResponsiveContainer>
        </div>
      </div>

      {/* ⚠️ SECCIÓN DE ALARMAS Y ACCIONES RÁPIDAS (Tu código intacto) */}
      <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: '30px' }}>
        
        {/* Lista de productos que hay que reponer */}
        <div style={{ background: '#1d1d1f', padding: '25px', borderRadius: '16px' }}>
          <h3 style={{ margin: '0 0 20px 0', borderBottom: '1px solid #333', paddingBottom: '10px' }}>⚠️ Productos por Agotarse</h3>
          
          {productosBajoStock.length === 0 && productosAgotados.length === 0 ? (
            <p style={{ color: '#34c759' }}>Todo el inventario está sano. ✅</p>
          ) : (
            <ul style={{ listStyle: 'none', padding: 0, margin: 0 }}>
              {productosAgotados.map(p => (
                <li key={p.id} style={{ display: 'flex', justifyContent: 'space-between', padding: '10px 0', color: '#ff3b30', borderBottom: '1px solid #333' }}>
                  <span>{p.nombre}</span> <strong>AGOTADO</strong>
                </li>
              ))}
              {productosBajoStock.map(p => (
                <li key={p.id} style={{ display: 'flex', justifyContent: 'space-between', padding: '10px 0', color: '#ff9500', borderBottom: '1px solid #333' }}>
                  <span>{p.nombre}</span> <strong>Quedan {p.stock_actual}</strong>
                </li>
              ))}
            </ul>
          )}
        </div>

        {/* Botones de acceso rápido */}
        <div style={{ display: 'flex', flexDirection: 'column', gap: '15px' }}>
          <h3 style={{ margin: '0 0 5px 0' }}>⚡ Accesos Rápidos</h3>
          <Link to="/admin/inventario" style={{ background: '#333', color: 'white', textDecoration: 'none', padding: '20px', borderRadius: '12px', textAlign: 'center', fontWeight: 'bold', transition: '0.2s' }}>
            📦 Ir al Inventario
          </Link>
          <Link to="/admin/pedidos" style={{ background: '#0071e3', color: 'white', textDecoration: 'none', padding: '20px', borderRadius: '12px', textAlign: 'center', fontWeight: 'bold', transition: '0.2s' }}>
            🚚 Ver Envíos Pendientes
          </Link>
        </div>

      </div>
    </div>
  )
}