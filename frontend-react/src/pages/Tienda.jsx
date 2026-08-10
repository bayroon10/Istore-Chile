import { useState, useEffect, useCallback, useRef } from 'react'
import Swal from 'sweetalert2'
import CheckoutForm from '../components/CheckoutForm';
import { useCart } from '../contexts/CartContext';
import { useAuth } from '../contexts/AuthContext';
import api from '../lib/api';
import Footer from '../components/Footer';
import { useDebounce } from '../hooks/useDebounce';
import { useStaggerReveal } from '../hooks/useStaggerReveal';

// 🌟 Stripe
import { loadStripe } from '@stripe/stripe-js';
import { Elements } from '@stripe/react-stripe-js';

const key = import.meta.env.VITE_STRIPE_PUBLISHABLE_KEY;
if (!key) {
  console.warn("⚠️ [iStore] Warning: VITE_STRIPE_PUBLISHABLE_KEY is not defined. Stripe checkout will be unavailable.");
}
const stripePromise = key ? loadStripe(key) : null;
export default function Tienda() {
  const [productos, setProductos] = useState([]);
  const [categorias, setCategorias] = useState([]);
  const [verCarrito, setVerCarrito] = useState(false);
  const [busqueda, setBusqueda] = useState('');
  const [categoriaActiva, setCategoriaActiva] = useState('Todas');
  const [mostrarCheckout, setMostrarCheckout] = useState(false);

  // Estados de paginación y carga
  const [pagina, setPagina] = useState(1);
  const [hayMas, setHayMas] = useState(true);
  const [cargando, setCargando] = useState(false);

  // Estado por card: null | 'loading' | 'success' | 'error'
  const [cardStates, setCardStates] = useState({});

  // Stagger reveal para el grid de productos
  const gridRef = useStaggerReveal(60);

  const debouncedSearch = useDebounce(busqueda, 500);

  // Carrito del backend vía CartContext
  const { items, totalItems, totalPrice, addItem, updateQuantity, removeItem, refreshCart } = useCart();
  const { isAuthenticated } = useAuth();

  // 1. Cargar categorías al inicio
  useEffect(() => {
    api.get('/categories')
      .then(res => {
        setCategorias(res.data || []);
      })
      .catch(err => {
        console.error('Error cargando categorías:', err);
      });
  }, []);

  // 2. Función principal de carga de productos (Backend Driven)
  const cargarProductos = useCallback(async (pageToLoad = 1, append = false) => {
    setCargando(true);
    try {
      // Construcción de query params
      let endpoint = `/products?page=${pageToLoad}&per_page=12`;
      if (debouncedSearch) endpoint += `&search=${encodeURIComponent(debouncedSearch)}`;
      if (categoriaActiva !== 'Todas') endpoint += `&category=${encodeURIComponent(categoriaActiva)}`;

      const res = await api.get(endpoint);

      const nuevosProductos = res.data || [];
      const meta = res.meta || {};

      setProductos(prev => append ? [...prev, ...nuevosProductos] : nuevosProductos);
      setHayMas(meta.current_page < meta.last_page);
      setPagina(meta.current_page);
    } catch (err) {
      Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'error',
        title: 'Error de conexión',
        text: 'No pudimos sincronizar el catálogo. Reintenta en unos segundos.',
        background: '#1d1d1f',
        color: '#fff',
        showConfirmButton: false,
        timer: 4000
      });
    } finally {
      setCargando(false);
    }
  }, [debouncedSearch, categoriaActiva]);

  // 3. Resetear y cargar cuando cambian filtros
  useEffect(() => {
    setPagina(1);
    cargarProductos(1, false);
  }, [debouncedSearch, categoriaActiva, cargarProductos]);

  const handleCargarMas = () => {
    if (!cargando && hayMas) {
      cargarProductos(pagina + 1, true);
    }
  };

  const agregarAlCarrito = async (producto) => {
    const pid = producto.id;
    setCardStates(prev => ({ ...prev, [pid]: 'loading' }));
    const result = await addItem(pid);
    if (!result.success) {
      setCardStates(prev => ({ ...prev, [pid]: 'error' }));
      setTimeout(() => setCardStates(prev => ({ ...prev, [pid]: null })), 1500);
      Swal.fire({
        title: 'Error',
        text: result.error,
        icon: 'warning',
        background: '#000',
        color: '#fff',
        confirmButtonColor: '#3b82f6'
      });
    } else {
      setCardStates(prev => ({ ...prev, [pid]: 'success' }));
      setTimeout(() => setCardStates(prev => ({ ...prev, [pid]: null })), 1500);
    }
  };

  // Ya no necesitamos filtrar en cliente, lo hace el backend
  const totalEnPantalla = productos.length;

  return (
    <div className="bg-pitch-black min-h-screen text-white font-sans selection:bg-urban-blue/30 selection:text-urban-blue">

      {/* 💳 MODAL DE CHECKOUT (GLASSMORPHISM) */}
      {mostrarCheckout && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 backdrop-blur-xl bg-black/60 transition-all duration-300">
          <div className="glass-dark w-full max-w-md p-8 rounded-[2.5rem] shadow-neon-glow border border-white/10 animate-in fade-in zoom-in duration-300">
            <div className="text-center mb-8">
              <h2 className="text-3xl font-black text-white mb-2 leading-tight">Checkout Seguro</h2>
              <p className="text-gray-400 text-sm">Completa tu orden con tecnología de encriptación Stripe.</p>
            </div>

            {stripePromise ? (
              <Elements stripe={stripePromise}>
                <CheckoutForm
                  total={totalPrice}
                  cerrarModal={() => setMostrarCheckout(false)}
                  onSuccess={() => {
                    refreshCart();
                    setMostrarCheckout(false);
                  }}
                />
              </Elements>
            ) : (
              <div className="text-center p-6 bg-red-500/10 border border-red-500/20 rounded-2xl text-red-200">
                <p className="font-semibold mb-2 text-base">Pasarela Desactivada</p>
                <p className="text-xs text-red-300 mb-6">La clave pública de Stripe (VITE_STRIPE_PUBLISHABLE_KEY) no está configurada en producción.</p>
                <button
                  onClick={() => setMostrarCheckout(false)}
                  className="w-full py-3 px-6 rounded-xl bg-white/10 hover:bg-white/15 text-white font-bold transition-all text-sm"
                >
                  Volver a la Tienda
                </button>
              </div>
            )}
          </div>
        </div>
      )}

      {/* 🛍️ SIDE-CART DRAWER (PINTA TECH-WEAR) */}
      <div className={`fixed inset-y-0 right-0 z-[150] w-full max-w-sm glass-dark border-l border-white/10 shadow-[-20px_0_50px_rgba(0,0,0,0.5)] transform transition-transform duration-500 ease-out p-6 flex flex-col ${verCarrito ? 'translate-x-0' : 'translate-x-full'}`}>
        <div className="flex items-center justify-between mb-8 border-b border-white/10 pb-4">
          <h3 className="text-2xl font-black">Tu Bolsa <span className="text-urban-blue">({totalItems})</span></h3>
          <button onClick={() => setVerCarrito(false)} className="p-2 hover:bg-white/5 rounded-full transition-colors text-gray-400 hover:text-white">✕</button>
        </div>

        <div className="flex-1 overflow-y-auto pr-2 space-y-6">
          {items.length === 0 ? (
            <div className="flex flex-col items-center justify-center h-40 text-gray-400 italic">
              <span className="text-4xl mb-4">🛒</span>
              Tu bolsa está vacía.
            </div>
          ) : (
            items.map((item) => (
              <div key={item.id} className="flex items-center gap-4 group">
                <div className="w-20 h-20 bg-space-grey rounded-2xl flex items-center justify-center border border-white/5 group-hover:border-urban-blue/30 transition-all">
                  <img
                    src={item.product_image && !item.product_image.includes('via.placeholder.com') ? item.product_image : `https://placehold.co/80x80/0a0a0a/3b82f6?text=${encodeURIComponent((item.product_name || 'i').slice(0, 2))}`}
                    alt={item.product_name || 'Producto'}
                    loading="lazy"
                    onError={(e) => { e.target.src = 'https://placehold.co/80x80/000/3b82f6?text=i'; }}
                    className="w-16 h-16 object-contain"
                  />
                </div>
                <div className="flex-1 min-w-0">
                  <p className="font-bold text-sm truncate">{item.product_name}</p>
                  <div className="flex items-center gap-3 mt-2">
                    <button onClick={() => updateQuantity(item.product_id, item.quantity - 1)} className="w-6 h-6 rounded-full glass border border-white/10 flex items-center justify-center hover:bg-urban-blue/20 transition-all">-</button>
                    <span className="text-sm font-black">{item.quantity}</span>
                    <button onClick={() => updateQuantity(item.product_id, item.quantity + 1)} className="w-6 h-6 rounded-full glass border border-white/10 flex items-center justify-center hover:bg-urban-blue/20 transition-all">+</button>
                    <button onClick={() => removeItem(item.product_id)} className="ml-auto text-xs font-bold text-red-500/80 hover:text-red-500 transition-colors uppercase tracking-widest">Eliminar</button>
                  </div>
                </div>
                <div className="text-right font-black text-urban-blue">${item.subtotal.toLocaleString()}</div>
              </div>
            ))
          )}
        </div>

        <div className="mt-8 pt-6 border-t border-white/10">
          <div className="flex justify-between items-center mb-6 px-2">
            <span className="text-gray-400 font-bold uppercase tracking-tight">Gran Total</span>
            <span className="text-3xl font-black text-white">${totalPrice.toLocaleString()}</span>
          </div>
          <button
            disabled={items.length === 0}
            onClick={() => {
              if (!isAuthenticated) {
                Swal.fire({
                  title: 'Inicia Sesión',
                  text: 'Debes estar autenticado para comprar.',
                  icon: 'info',
                  background: '#000',
                  color: '#fff',
                  confirmButtonColor: '#0071e3'
                }).then(() => window.location.href = '/mi-cuenta');
                return;
              }
              setVerCarrito(false);
              setMostrarCheckout(true);
            }}
            className="w-full py-5 rounded-[1.2rem] bg-urban-blue text-white font-black text-lg shadow-neon-blue hover:shadow-neon-glow transition-all duration-300 disabled:opacity-50 disabled:grayscale"
          >
            PAGAR AHORA ➔
          </button>
        </div>
      </div>

      {/* 🚀 NAVBAR URBANO */}
      <nav className="fixed top-0 left-0 right-0 z-[100] px-10 py-6 flex items-center justify-between pointer-events-none">
        <div className="pointer-events-auto">
          {/* El logo ya está en la Navbar global de App.jsx, aquí podemos poner el indicador del carrito */}
        </div>
        <div className="pointer-events-auto flex items-center gap-4">
          <button
            onClick={() => setVerCarrito(true)}
            className="glass-dark px-6 py-3 rounded-full flex items-center gap-3 group transition-all duration-300 hover:border-urban-blue/50 hover:shadow-neon-blue"
          >
            <span className="text-xl">🛍️</span>
            <span className="font-black text-sm tracking-widest uppercase">Carrito</span>
            <span className="bg-urban-blue text-white text-[10px] w-5 h-5 flex items-center justify-center rounded-full font-black animate-pulse">{totalItems}</span>
          </button>
        </div>
      </nav>

      {/* 🌃 CONTENIDO PRINCIPAL */}
      <div className="max-w-7xl mx-auto px-6 lg:px-10 space-y-24">

        {/* ⚡ HERO BANNER — AURORA MESH */}
        <section className="relative h-[90vh] min-h-[560px] flex items-center justify-center overflow-hidden rounded-[3rem] mt-4 bg-pitch-black">

          {/* Aurora blobs — layer 1 */}
          <div className="absolute pointer-events-none" style={{
            width: 700, height: 700,
            top: -200, left: -100,
            background: 'rgba(59,130,246,0.10)',
            borderRadius: '50%',
            filter: 'blur(120px)',
            animation: 'float-1 12s ease-in-out infinite'
          }} />
          {/* Aurora blobs — layer 2 */}
          <div className="absolute pointer-events-none" style={{
            width: 400, height: 400,
            bottom: -100, right: 0,
            background: 'rgba(34,211,238,0.08)',
            borderRadius: '50%',
            filter: 'blur(80px)',
            animation: 'float-2 9s ease-in-out infinite reverse'
          }} />
          {/* Aurora blobs — layer 3 */}
          <div className="absolute pointer-events-none" style={{
            width: 300, height: 300,
            top: '30%', right: '20%',
            background: 'rgba(99,102,241,0.08)',
            borderRadius: '50%',
            filter: 'blur(60px)',
            animation: 'float-3 15s ease-in-out infinite'
          }} />

          {/* Grain texture SVG overlay */}
          <div className="absolute inset-0 pointer-events-none opacity-[0.04]" style={{ zIndex: 1 }}>
            <svg width="100%" height="100%">
              <filter id="grain">
                <feTurbulence type="fractalNoise" baseFrequency="0.65" numOctaves="3" stitchTiles="stitch" />
                <feColorMatrix type="saturate" values="0" />
              </filter>
              <rect width="100%" height="100%" filter="url(#grain)" />
            </svg>
          </div>

          {/* Bottom fade to black */}
          <div className="absolute bottom-0 left-0 right-0 h-40 bg-gradient-to-t from-pitch-black to-transparent pointer-events-none" style={{ zIndex: 2 }} />

          <div className="relative text-center px-4 max-w-3xl" style={{ zIndex: 3 }}>
            <span className="inline-block px-4 py-1.5 rounded-full glass border border-blue-500/30 text-blue-400 text-[10px] font-black tracking-[0.3em] uppercase mb-8 shadow-neon-blue">
              NUEVA COLECCIÓN 2026
            </span>
            <h1 className="text-7xl md:text-9xl font-black text-white leading-none mb-6" style={{ letterSpacing: '-0.04em' }}>
              TECH-WEAR{' '}
              <span style={{
                background: 'linear-gradient(to right, #3b82f6, #22d3ee)',
                WebkitBackgroundClip: 'text',
                WebkitTextFillColor: 'transparent',
                backgroundClip: 'text'
              }}>PRO.</span>
            </h1>
            <p className="text-gray-400 text-lg md:text-xl font-medium mb-10 max-w-xl mx-auto">
              Equipamiento táctico para tu ecosistema digital. Diseñado para los que no se detienen.
            </p>
            <button
              onClick={() => document.getElementById('catalogo')?.scrollIntoView({ behavior: 'smooth' })}
              className="px-10 py-5 bg-white text-black rounded-[1.2rem] font-black uppercase text-sm tracking-[0.2em] hover:bg-blue-500 hover:text-white hover:shadow-neon-blue active:scale-95 transition-all duration-300"
            >
              EXPLORAR CATÁLOGO →
            </button>
          </div>
        </section>

        {/* 🛡️ BENEFICIOS SECTION */}
        <section className="grid grid-cols-1 md:grid-cols-3 gap-8 pb-12 border-b border-white/5">
          {[
            { icon: '🚀', title: 'Envío Flash', desc: 'Entrega prioritaria en menos de 24h.' },
            { icon: '🤖', title: 'Soporte con IA', desc: 'Santi resuelve tus dudas al instante.' },
            { icon: '🔒', title: 'Pago Encriptado', desc: 'Transacciones 100% seguras vía Stripe.' }
          ].map((b, i) => (
            <div key={i} className="glass-dark p-8 rounded-[2rem] flex flex-col items-center text-center group transition-smooth hover:border-urban-blue/20">
              <span className="text-5xl mb-4 group-hover:scale-110 transition-transform duration-500">{b.icon}</span>
              <h4 className="text-xl font-black mb-2">{b.title}</h4>
              <p className="text-gray-400 text-sm leading-relaxed">{b.desc}</p>
            </div>
          ))}
        </section>

        {/* 🔍 FILTROS URBANO */}
        <div id="catalogo" className="flex flex-col md:flex-row gap-6 items-center justify-between pt-12">
          <div className="w-full md:max-w-md relative group">
            <input
              type="text"
              placeholder="Busca tu equipamiento..."
              value={busqueda}
              onChange={(e) => setBusqueda(e.target.value)}
              className="w-full h-14 glass-dark pl-14 pr-6 rounded-2xl outline-none border border-white/5 focus:border-urban-blue/50 focus:shadow-neon-blue transition-all duration-300"
            />
            <span className="absolute left-6 top-1/2 -translate-y-1/2 text-xl opacity-50 group-focus-within:opacity-100 transition-opacity">🔍</span>
          </div>

          <div className="flex gap-2 overflow-x-auto w-full md:w-auto pb-4 md:pb-0 no-scrollbar">
            <button
              onClick={() => setCategoriaActiva('Todas')}
              className={`px-6 py-3 rounded-full text-sm font-black transition-smooth whitespace-nowrap border ${categoriaActiva === 'Todas' ? 'bg-urban-blue text-white border-urban-blue shadow-neon-blue' : 'glass border-white/5 text-gray-400 hover:text-white'}`}
            >
              TODAS
            </button>
            {categorias.map(cat => (
              <button
                key={cat.id}
                onClick={() => setCategoriaActiva(cat.slug)}
                className={`px-6 py-3 rounded-full text-sm font-black transition-smooth whitespace-nowrap border ${categoriaActiva === cat.slug ? 'bg-urban-blue text-white border-urban-blue shadow-neon-blue' : 'glass border-white/5 text-gray-400 hover:text-white'}`}
              >
                {cat.name.toUpperCase()}
              </button>
            ))}
          </div>
        </div>

        {/* 📦 GRID DE PRODUCTOS (LUXURY) */}
        <div className="pb-24">
          <div className="flex items-end justify-between mb-10">
            <h2 className="text-4xl font-black tracking-tighter">
              EL PRÓXIMO NIVEL <span className="text-gray-400">({totalEnPantalla})</span>
            </h2>
          </div>

          {productos.length === 0 && !cargando ? (
            <div className="text-center py-20 glass rounded-[3rem] border-dashed border-white/10 text-gray-400 text-xl italic font-medium">
              No se han encontrado resultados en este cuadrante. 🛰️
            </div>
          ) : (
            <>
              <div ref={gridRef} className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
                {productos.map(p => {
                  const cs = cardStates[p.id] || null;
                  const agotado = p.stock === 0;
                  const fb = `https://placehold.co/600x600/000000/3b82f6?text=${encodeURIComponent((p.name || 'iStore').slice(0, 8))}`;
                  const fallbackFinal = '/assets/placeholder-product.png';
                  return (
                    <div
                      key={p.id}
                      data-stagger
                      className="glass-dark group relative rounded-[2.5rem] p-7 flex flex-col h-[460px] transition-smooth hover:-translate-y-2 hover:shadow-neon-glow hover:border-blue-500/20"
                    >
                      {/* Agotado overlay */}
                      {agotado && (
                        <div className="absolute inset-0 z-10 bg-black/60 rounded-[2.5rem] flex items-center justify-center">
                          <span className="text-[10px] font-black tracking-widest text-red-400 border border-red-500/30 bg-red-500/10 px-4 py-1.5 rounded-full uppercase">
                            AGOTADO
                          </span>
                        </div>
                      )}

                      {/* Badge categoría + precio */}
                      <div className="flex justify-between items-start mb-4">
                        <span className="text-[9px] font-black tracking-[0.25em] text-blue-400 px-3 py-1 rounded-full glass border border-blue-500/20 uppercase">
                          {p.category?.name || 'TECH'}
                        </span>
                        <div className="text-right">
                          {p.compare_price > 0 && (
                            <span className="block text-xs text-gray-400 line-through">
                              ${Number(p.compare_price).toLocaleString()}
                            </span>
                          )}
                          <span className="text-lg font-black tracking-tighter">$ {Number(p.price).toLocaleString()}</span>
                        </div>
                      </div>

                      {/* Imagen */}
                      <div className="flex-1 flex items-center justify-center p-4 relative">
                        <div className="absolute inset-0 rounded-full blur-[60px] opacity-0 group-hover:opacity-100 transition-opacity duration-500"
                          style={{ background: 'radial-gradient(circle, rgba(59,130,246,0.10) 0%, transparent 70%)' }} />
                        <img
                          src={p.primary_image_url && p.primary_image_url.trim() !== '' ? p.primary_image_url : fb}
                          onError={(e) => { if (e.target.src !== fallbackFinal) e.target.src = fallbackFinal; }}
                          alt={p.name}
                          loading="lazy"
                          className="max-h-full max-w-full object-contain drop-shadow-[0_20px_50px_rgba(0,0,0,0.5)] group-hover:scale-105 transition-transform duration-500"
                        />
                      </div>

                      {/* Nombre + botón */}
                      <div className="mt-5">
                        <h3 className="text-base font-black text-white leading-tight mb-4 line-clamp-2">{p.name}</h3>
                        <button
                          onClick={() => !agotado && cs === null && agregarAlCarrito(p)}
                          disabled={agotado || cs === 'loading'}
                          className={[
                            'w-full py-4 rounded-2xl font-black uppercase text-xs tracking-widest transition-all duration-300 flex items-center justify-center gap-2',
                            agotado ? 'opacity-50 grayscale cursor-not-allowed bg-white text-black' :
                            cs === 'success' ? 'bg-emerald-500 text-white' :
                            cs === 'error'   ? 'bg-red-500/20 text-red-400 border border-red-500/30 animate-shake' :
                            cs === 'loading' ? 'bg-white/10 text-gray-400 cursor-not-allowed' :
                            'bg-white text-black hover:bg-blue-500 hover:text-white hover:shadow-neon-blue'
                          ].join(' ')}
                        >
                          {cs === 'loading' && <span className="w-3.5 h-3.5 border-2 border-current border-t-transparent rounded-full animate-spin" />}
                          {cs === 'success' ? '✓ AÑADIDO' : cs === 'loading' ? 'AÑADIENDO...' : cs === 'error' ? 'ERROR — REINTENTAR' : 'AÑADIR A LA BOLSA'}
                        </button>
                      </div>
                    </div>
                  );
                })}
              </div>

              {/* ⚡ LOAD MORE BUTTON (TECH-STYLE) */}
              <div className="mt-20 flex flex-col items-center gap-6">
                {hayMas && (
                  <button
                    onClick={handleCargarMas}
                    disabled={cargando}
                    className="group relative px-12 py-5 bg-transparent border border-white/10 rounded-full overflow-hidden transition-all duration-500 hover:border-urban-blue/50"
                  >
                    <div className="absolute inset-0 bg-urban-blue/5 translate-y-full group-hover:translate-y-0 transition-transform duration-500"></div>
                    <span className="relative z-10 font-black tracking-[0.3em] uppercase text-xs flex items-center gap-3">
                      {cargando ? (
                        <>
                          <div className="w-4 h-4 border-2 border-urban-blue border-t-transparent rounded-full animate-spin"></div>
                          Sincronizando...
                        </>
                      ) : (
                        'Cargar Más Equipamiento'
                      )}
                    </span>
                  </button>
                )}

                {!hayMas && productos.length > 0 && (
                  <p className="text-gray-400 font-bold text-xs uppercase tracking-[0.4em] animate-pulse">
                    Catálogo Completo — Fin de la Transmisión
                  </p>
                )}
              </div>
            </>
          )}
        </div>

      </div>

      <Footer />
    </div>
  )
}