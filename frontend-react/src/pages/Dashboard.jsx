import { useState, useEffect, useRef } from 'react'
import { Link } from 'react-router-dom'
import { AreaChart, Area, BarChart, Bar, LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';
import { DollarSign, ShoppingBag, Package, BarChart2, Monitor } from 'lucide-react';
import api from '../lib/api';
import { useCountUp } from '../hooks/useCountUp';

export default function Dashboard() {
  const [productos, setProductos] = useState([]);
  const [estadisticas, setEstadisticas] = useState({ kpis: {}, chart: [], recent_orders: [] });
  const [salesTrend, setSalesTrend] = useState([]);
  const [criticalStock, setCriticalStock] = useState([]);
  const [cargando, setCargando] = useState(true);

  useEffect(() => {
    const cargarDatos = async () => {
      setCargando(true);
      try {
        const [dataProd, dataEst, dataTrend, dataCritical] = await Promise.all([
          api.get('/products'),
          api.get('/estadisticas'),
          api.get('/admin/stats/sales-trend'),
          api.get('/admin/stats/critical-stock')
        ]);
        setProductos(dataProd.data || []);
        setEstadisticas(dataEst);
        setSalesTrend(dataTrend || []);
        setCriticalStock(dataCritical || []);
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

  // Animated progress width for Bodega bars
  const [progressWidths, setProgressWidths] = useState({});
  useEffect(() => {
    if (productos.length === 0) return;
    const t = setTimeout(() => {
      const widths = {};
      [...productosAgotados, ...productosBajoStock].forEach(p => {
        widths[p.id] = Math.min(100, (p.stock / 10) * 100);
      });
      setProgressWidths(widths);
    }, 100);
    return () => clearTimeout(t);
  }, [productos]);

  // Sync tooltip
  const [showSync, setShowSync] = useState(false);

  // Count-up values (live data)
  const revCount   = useCountUp(estadisticas.kpis.total_revenue  || 0, 1200, !cargando);
  const ordCount   = useCountUp(estadisticas.kpis.total_orders   || 0, 1200, !cargando);
  const pendCount  = useCountUp(estadisticas.kpis.pending_orders || 0, 1200, !cargando);
  const stockCount = useCountUp(estadisticas.kpis.low_stock_alerts || 0, 1200, !cargando);
  const capCount   = useCountUp(capitalInvertido, 1200, !cargando);

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
        <div
          className="relative flex items-center gap-2 glass px-4 py-2 rounded-full border-white/5 cursor-default"
          onMouseEnter={() => setShowSync(true)}
          onMouseLeave={() => setShowSync(false)}
        >
          <div className="w-2 h-2 bg-emerald-400 rounded-full" style={{ animation: 'pulse-dot 2s ease-in-out infinite' }} />
          <span className="text-[10px] font-black uppercase tracking-widest text-gray-400">Sincronizado</span>
          {showSync && (
            <div className="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 glass-dark border border-white/10 text-[10px] text-gray-400 px-3 py-1.5 rounded-xl whitespace-nowrap shadow-lg z-10">
              Última sync: hace 30s
            </div>
          )}
        </div>
      </header>

      {/* 🌟 KPI CARDS */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-6">

        {/* Ingresos Totales */}
        <div className="glass-dark p-6 rounded-[2rem] border-l-4 border-l-emerald-500 group relative overflow-hidden hover:-translate-y-0.5 hover:border-urban-blue/15 transition-all duration-300 flex flex-col justify-between">
          <DollarSign size={40} className="absolute top-4 right-4 text-white opacity-20" />
          <div>
            <p className="text-[10px] font-black text-gray-500 uppercase tracking-[0.2em] mb-2">Ingresos Totales</p>
            <h3 className="text-2xl font-black text-white tracking-tighter">${revCount.toLocaleString()}</h3>
          </div>
        </div>

        {/* Volumen Histórico */}
        <div className="glass-dark p-6 rounded-[2rem] border-l-4 border-l-blue-500 group relative overflow-hidden hover:-translate-y-0.5 hover:border-urban-blue/15 transition-all duration-300 flex flex-col justify-between">
          <ShoppingBag size={40} className="absolute top-4 right-4 text-white opacity-20" />
          <div>
            <p className="text-[10px] font-black text-gray-500 uppercase tracking-[0.2em] mb-2">Volumen Histórico</p>
            <h3 className="text-2xl font-black text-white tracking-tighter">{ordCount}</h3>
          </div>
        </div>

        {/* Órdenes Pendientes */}
        <div className="glass-dark p-6 rounded-[2rem] border-l-4 border-l-amber-500 group relative overflow-hidden hover:-translate-y-0.5 hover:border-urban-blue/15 transition-all duration-300 flex flex-col justify-between">
          <Package size={40} className="absolute top-4 right-4 text-white opacity-20" />
          <div>
            <p className="text-[10px] font-black text-gray-500 uppercase tracking-[0.2em] mb-2">Órdenes Pendientes</p>
            <h3 className="text-2xl font-black text-white tracking-tighter">{pendCount}</h3>
          </div>
        </div>

        {/* Stock Crítico */}
        <div className="glass-dark p-6 rounded-[2rem] border-l-4 border-l-red-500 group relative overflow-hidden hover:-translate-y-0.5 hover:border-urban-blue/15 transition-all duration-300 flex flex-col justify-between">
          <BarChart2 size={40} className="absolute top-4 right-4 text-white opacity-20" />
          <div>
            <p className="text-[10px] font-black text-gray-500 uppercase tracking-[0.2em] mb-2">Stock Crítico</p>
            <h3 className="text-2xl font-black text-white tracking-tighter">{stockCount}</h3>
          </div>
        </div>

        {/* Ingresos BI Semanal — hardcoded */}
        <div className="glass-dark p-6 rounded-[2rem] border-l-4 border-l-emerald-400 group relative overflow-hidden hover:-translate-y-0.5 hover:border-urban-blue/15 transition-all duration-300 flex flex-col justify-between">
          <DollarSign size={40} className="absolute top-4 right-4 text-white opacity-20" />
          <div>
            <p className="text-[10px] font-black text-gray-500 uppercase tracking-[0.2em] mb-2">Ingresos BI Semanal</p>
            <h3 className="text-2xl font-black text-white tracking-tighter flex items-center gap-1.5">
              <span className="w-2 h-2 rounded-full bg-amber-400 shrink-0" title="Dato de muestra - pendiente integración real" />
              $1,520,000
            </h3>
          </div>
        </div>

        {/* Equipos Vendidos BI — hardcoded */}
        <div className="glass-dark p-6 rounded-[2rem] border-l-4 border-l-blue-400 group relative overflow-hidden hover:-translate-y-0.5 hover:border-urban-blue/15 transition-all duration-300 flex flex-col justify-between">
          <Monitor size={40} className="absolute top-4 right-4 text-white opacity-20" />
          <div>
            <p className="text-[10px] font-black text-gray-500 uppercase tracking-[0.2em] mb-2">Equipos Vendidos BI</p>
            <h3 className="text-2xl font-black text-white tracking-tighter flex items-center gap-1.5">
              <span className="w-2 h-2 rounded-full bg-amber-400 shrink-0" title="Dato de muestra - pendiente integración real" />
              14
            </h3>
          </div>
        </div>

      </div>

      {/* 📈 CHART SECTION (NEON LINES) */}
      <div className="glass-dark p-10 rounded-[3rem] border border-white/5 shadow-2xl relative overflow-hidden">
        <div className="absolute top-0 right-0 w-64 h-64 bg-urban-blue/10 rounded-full blur-[100px] -mr-32 -mt-32"></div>

        <h3 className="text-xl font-black mb-10 flex items-center gap-3">
          <span className="w-8 h-1 bg-urban-blue rounded-full shadow-neon-blue"></span>
          TENDENCIA DE VENTAS (7D)
        </h3>

        <div className="w-full min-h-[320px]">
          <ResponsiveContainer width="99%" height={320}>
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

      {/* 📊 SECCIÓN DE GRÁFICOS DE BI (ESTILO NEÓN OSCURO COHERENTE) */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-10">
        
        {/* CURVA DE INGRESOS */}
        <div className="glass-dark p-8 rounded-[3rem] border border-white/5 shadow-2xl relative overflow-hidden">
          <div className="absolute top-0 right-0 w-48 h-48 bg-blue-500/10 rounded-full blur-[80px] -mr-24 -mt-24"></div>
          
          <h3 className="text-lg font-black mb-8 flex items-center gap-3 uppercase tracking-wider">
            <span className="w-6 h-1 bg-blue-500 rounded-full shadow-neon-blue"></span>
            Curva de Ingresos (Últimos 7 días)
          </h3>
          
          <div className="w-full min-h-[288px]">
            <ResponsiveContainer width="99%" height={288}>
              <LineChart data={salesTrend}>
                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#222" />
                <XAxis dataKey="dia" stroke="#444" tick={{ fontSize: 10, fontWeight: 'bold' }} axisLine={false} />
                <YAxis stroke="#444" tick={{ fontSize: 10, fontWeight: 'bold' }} axisLine={false} />
                <Tooltip contentStyle={{ borderRadius: '20px', border: '1px solid rgba(255,255,255,0.1)', background: '#1d1d1f', color: 'white', fontSize: '12px', fontWeight: 'bold' }} formatter={(value) => `$${value.toLocaleString()}`} />
                <Line type="monotone" dataKey="ingresos" stroke="#3b82f6" strokeWidth={3} dot={{ r: 4, stroke: '#3b82f6', strokeWidth: 2, fill: '#121212' }} />
              </LineChart>
            </ResponsiveContainer>
          </div>
        </div>

        {/* STOCK CRÍTICO */}
        <div className="glass-dark p-8 rounded-[3rem] border border-white/5 shadow-2xl relative overflow-hidden">
          <div className="absolute top-0 right-0 w-48 h-48 bg-red-500/10 rounded-full blur-[80px] -mr-24 -mt-24"></div>
          
          <h3 className="text-lg font-black mb-8 flex items-center gap-3 uppercase tracking-wider">
            <span className="w-6 h-1 bg-red-500 rounded-full shadow-neon-red"></span>
            Accesorios con Stock Crítico
          </h3>
          
          <div className="w-full min-h-[288px]">
            <ResponsiveContainer width="99%" height={288}>
              <BarChart data={criticalStock} layout="vertical" margin={{ top: 5, right: 10, left: 10, bottom: 5 }}>
                <CartesianGrid strokeDasharray="3 3" horizontal={false} stroke="#222" />
                <XAxis type="number" stroke="#444" tick={{ fontSize: 10, fontWeight: 'bold' }} axisLine={false} />
                <YAxis dataKey="producto" type="category" width={120} stroke="#888" tick={{ fontSize: 10, fontWeight: 'bold' }} axisLine={false} />
                <Tooltip contentStyle={{ borderRadius: '20px', border: '1px solid rgba(255,255,255,0.1)', background: '#1d1d1f', color: 'white', fontSize: '12px', fontWeight: 'bold' }} />
                <Bar dataKey="stock" fill="#ef4444" radius={[0, 10, 10, 0]} barSize={16} />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </div>

      </div>

      {/* 🧾 RECENT ORDERS & STOCK ALERTS */}
      <div className="grid grid-cols-1 xl:grid-cols-2 gap-10 pb-20">

        {/* ÚLTIMOS PEDIDOS */}
        <div className="glass-dark p-8 rounded-[3rem] border border-white/5">
          <div className="flex justify-between items-center mb-6">
            <h3 className="text-xl font-black uppercase tracking-widest border-l-2 border-blue-500 pl-4">Actividad Reciente</h3>
            <Link to="/admin/pedidos" className="text-[10px] font-bold text-blue-400 hover:text-white transition-colors uppercase tracking-[0.3em]">Ver Todo ➔</Link>
          </div>

          {/* Column headers */}
          <div className="grid grid-cols-4 gap-4 px-4 pb-3 border-b border-white/5">
            {['ORDEN','CLIENTE','ESTADO','TOTAL'].map(h => (
              <p key={h} className={`text-[9px] font-black text-gray-600 uppercase tracking-[0.2em] ${h==='TOTAL' ? 'text-right' : ''}`}>{h}</p>
            ))}
          </div>

          {estadisticas.recent_orders.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-12 text-gray-600">
              <BarChart2 size={36} className="opacity-30 mb-3" />
              <p className="text-xs font-black uppercase tracking-widest">Sin datos suficientes aún</p>
            </div>
          ) : (
            <div className="divide-y divide-white/5">
              {estadisticas.recent_orders.map(order => {
                const statusMap = {
                  paid:      'bg-emerald-500/15 text-emerald-400 border-emerald-500/25',
                  pending:   'bg-amber-500/15   text-amber-400   border-amber-500/25',
                  shipped:   'bg-blue-500/15    text-blue-400    border-blue-500/25',
                  delivered: 'bg-purple-500/15  text-purple-400  border-purple-500/25',
                  cancelled: 'bg-red-500/15     text-red-400     border-red-500/25',
                };
                const badge = statusMap[order.status] || statusMap.pending;
                return (
                  <div key={order.id} className="grid grid-cols-4 gap-4 px-4 py-3 hover:bg-white/[0.03] rounded-xl transition-all items-center">
                    <span className="font-mono text-xs text-gray-400">#{order.order_number}</span>
                    <span className="text-sm font-black text-white truncate">{order.customer?.name || 'Cliente'}</span>
                    <span className={`px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest border w-fit ${badge}`}>{order.status_label}</span>
                    <span className="text-right font-black text-white">${Number(order.total).toLocaleString()}</span>
                  </div>
                );
              })}
            </div>
          )}
        </div>

        {/* ALERTAS Y ACCESOS */}
        <div className="space-y-6">
          <div className="glass-dark p-8 rounded-[3rem] border border-white/5">
            <h3 className="text-xl font-black uppercase tracking-widest mb-6 border-l-2 border-blue-500 pl-4">Bodega Táctica</h3>
            <div className="space-y-4">
              {productosBajoStock.length === 0 && productosAgotados.length === 0 ? (
                <div className="bg-emerald-500/10 border border-emerald-500/10 p-6 rounded-2xl text-center">
                  <p className="text-emerald-400 font-black text-xs uppercase tracking-widest">Equipamiento al 100% ✅</p>
                </div>
              ) : (
                <div className="space-y-3">
                  {productosAgotados.map(p => {
                    const pct = progressWidths[p.id] ?? 0;
                    return (
                      <div key={p.id} className="space-y-1.5">
                        <div className="flex justify-between">
                          <span className="text-xs font-bold text-gray-300 truncate max-w-[70%]">{p.name}</span>
                          <span className="text-[10px] font-black text-red-400 uppercase tracking-widest">AGOTADO</span>
                        </div>
                        <div className="h-1.5 rounded-full bg-white/5 overflow-hidden">
                          <div className="h-full rounded-full bg-red-500 animate-pulse" style={{ width: '2%', transition: 'width 800ms cubic-bezier(0.22,1,0.36,1)' }} />
                        </div>
                      </div>
                    );
                  })}
                  {productosBajoStock.map(p => {
                    const pct = progressWidths[p.id] ?? 0;
                    const barColor = pct > 50 ? 'bg-emerald-500' : pct > 20 ? 'bg-amber-400' : 'bg-red-500 animate-pulse';
                    return (
                      <div key={p.id} className="space-y-1.5">
                        <div className="flex justify-between">
                          <span className="text-xs font-bold text-gray-300 truncate max-w-[70%]">{p.name}</span>
                          <span className="text-[10px] font-black text-amber-400 uppercase tracking-widest">Quedan {p.stock}</span>
                        </div>
                        <div className="h-1.5 rounded-full bg-white/5 overflow-hidden">
                          <div
                            className={`h-full rounded-full ${barColor}`}
                            style={{ width: `${pct}%`, transition: 'width 800ms cubic-bezier(0.22,1,0.36,1)' }}
                          />
                        </div>
                      </div>
                    );
                  })}
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