import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';
import api from '../lib/api';

export default function Dashboard() {
  const [productos, setProductos] = useState([]);
  const [estadisticas, setEstadisticas] = useState({ kpis: {}, chart: [], recent_orders: [] });
  const [cargando, setCargando] = useState(true);

  useEffect(() => {
    const cargarDatos = async () => {
      setCargando(true);
      try {
        const [dataProd, dataEst] = await Promise.all([
          api.get('/products'),
          api.get('/estadisticas')
        ]);
        setProductos(dataProd.data || []);
        setEstadisticas(dataEst);
      } catch (err) {
        // Silencio en Dashboard
      } finally {
        setCargando(false);
      }
    };

    cargarDatos();
  }, [])

  const capitalInvertido = productos.reduce((suma, p) => suma + (p.price * p.stock), 0);
  const productosBajoStock = productos.filter(p => p.stock <= 5 && p.stock > 0);
  const productosAgotados = productos.filter(p => p.stock === 0);

  if (cargando) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[60vh] text-urban-blue">
        <div className="w-12 h-12 border-4 border-urban-blue border-t-transparent rounded-full animate-spin mb-4"></div>
        <span className="font-black uppercase tracking-[0.3em] text-xs">Accediendo a Central de Mando...</span>
      </div>
    )
  }

  return (
    <div className="space-y-10 animate-in fade-in duration-700">

      <header className="flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
          <h2 className="text-4xl font-black tracking-tighter text-white">CENTRAL DE MANDO <span className="text-urban-blue"></span></h2>
          <p className="text-gray-500 text-sm font-bold uppercase tracking-widest mt-2">Métricas en tiempo real — iStore Chile Hub.</p>
        </div>
        <div className="flex items-center gap-2 glass px-4 py-2 rounded-full border-white/5">
          <div className="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
          <span className="text-[10px] font-black uppercase tracking-widest text-gray-400">Sincronizado</span>
        </div>
      </header>

      {/* 🌟 KPI CARDS (TECH-WEAR STYLE) */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">

        <div className="glass-dark p-8 rounded-[2.5rem] border-l-4 border-l-green-500 group hover:border-urban-blue transition-all duration-500">
          <p className="text-[10px] font-black text-gray-500 uppercase tracking-[0.2em] mb-4">Ingresos Totales</p>
          <h3 className="text-4xl font-black text-white tracking-tighter group-hover:scale-105 transition-transform origin-left">
            ${(estadisticas.kpis.total_revenue || 0).toLocaleString()}
          </h3>
        </div>

        <div className="glass-dark p-8 rounded-[2.5rem] border-l-4 border-l-urban-blue transition-all duration-500">
          <p className="text-[10px] font-black text-gray-500 uppercase tracking-[0.2em] mb-4">Volumen Histórico</p>
          <h3 className="text-4xl font-black text-white tracking-tighter">
            {estadisticas.kpis.total_orders || 0}
          </h3>
        </div>

        <div className="glass-dark p-8 rounded-[2.5rem] border-l-4 border-l-orange-500 transition-all duration-500">
          <p className="text-[10px] font-black text-gray-500 uppercase tracking-[0.2em] mb-4">Órdenes Pendientes</p>
          <h3 className="text-4xl font-black text-white tracking-tighter">
            {estadisticas.kpis.pending_orders || 0}
          </h3>
        </div>

        <div className="glass-dark p-8 rounded-[2.5rem] border-l-4 border-l-red-500 transition-all duration-500">
          <p className="text-[10px] font-black text-gray-500 uppercase tracking-[0.2em] mb-4">Stock Crítico</p>
          <h3 className="text-4xl font-black text-white tracking-tighter">
            {estadisticas.kpis.low_stock_alerts || 0}
          </h3>
        </div>

      </div>

      {/* 📈 CHART SECTION (NEON LINES) */}
      <div className="glass-dark p-10 rounded-[3rem] border border-white/5 shadow-2xl relative overflow-hidden">
        <div className="absolute top-0 right-0 w-64 h-64 bg-urban-blue/10 rounded-full blur-[100px] -mr-32 -mt-32"></div>

        <h3 className="text-xl font-black mb-10 flex items-center gap-3">
          <span className="w-8 h-1 bg-urban-blue rounded-full shadow-neon-blue"></span>
          TENDENCIA DE VENTAS (7D)
        </h3>

        <div className="w-full h-80">
          <ResponsiveContainer width="100%" height="100%">
            <AreaChart data={estadisticas.chart} margin={{ top: 10, right: 10, left: -20, bottom: 0 }}>
              <defs>
                <linearGradient id="neonGradient" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="5%" stopColor="#0071e3" stopOpacity={0.3} />
                  <stop offset="95%" stopColor="#0071e3" stopOpacity={0} />
                </linearGradient>
              </defs>
              <XAxis dataKey="fecha" stroke="#444" tick={{ fontSize: 10, fontWeight: 'bold' }} axisLine={false} />
              <YAxis stroke="#444" tick={{ fontSize: 10, fontWeight: 'bold' }} axisLine={false} />
              <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#222" />
              <Tooltip contentStyle={{ borderRadius: '20px', border: '1px solid rgba(255,255,255,0.1)', background: '#1d1d1f', color: 'white', fontSize: '12px', fontWeight: 'bold' }} />
              <Area type="monotone" dataKey="total_ventas" stroke="#0071e3" strokeWidth={4} fillOpacity={1} fill="url(#neonGradient)" />
            </AreaChart>
          </ResponsiveContainer>
        </div>
      </div>

      {/* 🧾 RECENT ORDERS & STOCK ALERTS */}
      <div className="grid grid-cols-1 xl:grid-cols-2 gap-10 pb-20">

        {/* ÚLTIMOS PEDIDOS */}
        <div className="glass-dark p-8 rounded-[3rem] border border-white/5">
          <div className="flex justify-between items-center mb-8">
            <h3 className="text-xl font-black uppercase tracking-widest">Actividad Reciente</h3>
            <Link to="/admin/pedidos" className="text-[10px] font-bold text-urban-blue hover:text-white transition-colors uppercase tracking-[0.3em]">Ver Todo ➔</Link>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-left">
              <thead>
                <tr className="text-[10px] font-black text-gray-500 uppercase tracking-[0.2em] border-b border-white/5">
                  <th className="pb-4 pl-4 text-urban-blue">ORDEN</th>
                  <th className="pb-4">CLIENTE</th>
                  <th className="pb-4 text-center">ESTADO</th>
                  <th className="pb-4 text-right pr-4">TOTAL</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-white/5">
                {estadisticas.recent_orders.map(order => (
                  <tr key={order.id} className="group hover:bg-white/[0.02] transition-all">
                    <td className="py-5 pl-4 font-black text-sm">#{order.order_number}</td>
                    <td className="py-5 text-gray-400 text-xs font-bold">{order.customer?.name || 'Cliente'}</td>
                    <td className="py-5 text-center">
                      <span className={`px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest ${order.status === 'paid' ? 'bg-green-500/10 text-green-500 border border-green-500/20' : 'bg-orange-500/10 text-orange-500 border border-orange-500/20'}`}>
                        {order.status_label}
                      </span>
                    </td>
                    <td className="py-5 text-right pr-4 font-black">${order.total.toLocaleString()}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        {/* ALERTAS Y ACCESOS */}
        <div className="space-y-6">
          <div className="glass-dark p-8 rounded-[3rem] border border-white/5">
            <h3 className="text-xl font-black uppercase tracking-widest mb-6">Bodega Táctica</h3>
            <div className="space-y-4">
              {productosBajoStock.length === 0 && productosAgotados.length === 0 ? (
                <div className="bg-green-500/10 border border-green-500/10 p-6 rounded-2xl text-center">
                  <p className="text-green-500 font-black text-xs uppercase tracking-widest">Equipamiento al 100% ✅</p>
                </div>
              ) : (
                <div className="space-y-3">
                  {productosAgotados.map(p => (
                    <div key={p.id} className="flex justify-between items-center p-4 bg-red-500/10 border border-red-500/10 rounded-2xl">
                      <span className="text-xs font-bold text-gray-200">{p.name}</span>
                      <span className="text-[10px] font-black text-red-500 uppercase tracking-widest">Agotado</span>
                    </div>
                  ))}
                  {productosBajoStock.map(p => (
                    <div key={p.id} className="flex justify-between items-center p-4 bg-orange-500/10 border border-orange-500/10 rounded-2xl">
                      <span className="text-xs font-bold text-gray-200">{p.name}</span>
                      <span className="text-[10px] font-black text-orange-500 uppercase tracking-widest">Quedan {p.stock}</span>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <Link to="/admin/inventario" className="glass-dark p-8 rounded-[2rem] border border-white/5 flex flex-col items-center justify-center gap-3 hover:border-urban-blue transition-all group">
              <span className="text-3xl group-hover:scale-110 transition-transform">📦</span>
              <span className="text-[10px] font-black uppercase tracking-[0.2em] text-center">Gestionar Bodega</span>
            </Link>
            <div className="glass-dark p-8 rounded-[2rem] border border-white/5 flex flex-col items-center justify-center gap-2">
              <p className="text-[10px] font-black text-gray-500 uppercase tracking-widest">Valor Bodega</p>
              <h4 className="text-2xl font-black text-white tracking-tighter">${capitalInvertido.toLocaleString()}</h4>
            </div>
          </div>
        </div>

      </div>
    </div>
  )
}