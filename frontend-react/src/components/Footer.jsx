import { Link } from 'react-router-dom';

export default function Footer() {
  return (
    <footer className="w-full bg-black/40 border-t border-white/5 py-12 px-6 lg:px-10 mt-20 backdrop-blur-md">
      <div className="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-4 gap-12">

        {/* LOGO & TAGLINE */}
        <div className="space-y-4">
          <div className="flex items-center gap-2">
            <span className="text-2xl font-black tracking-tighter text-white">iStore<span className="text-urban-blue"></span></span>
          </div>
          <p className="text-gray-500 text-sm font-medium max-w-xs uppercase tracking-widest leading-relaxed">
            Equipamiento táctico para tu ecosistema digital.
          </p>
        </div>

        {/* SHOP LINKS */}
        <div className="space-y-4">
          <h5 className="text-xs font-black uppercase tracking-[0.3em] text-urban-blue">Cátalogo</h5>
          <ul className="space-y-3">
            <li><Link to="/" className="text-gray-400 hover:text-white text-sm transition-colors font-medium">Todos los Productos</Link></li>
            <li><Link to="/" className="text-gray-400 hover:text-white text-sm transition-colors font-medium">Nuevos Ingresos</Link></li>
            <li><Link to="/" className="text-gray-400 hover:text-white text-sm transition-colors font-medium">Ofertas Hype</Link></li>
          </ul>
        </div>

        {/* SUPPORT LINKS */}
        <div className="space-y-4">
          <h5 className="text-xs font-black uppercase tracking-[0.3em] text-urban-blue">Soporte</h5>
          <ul className="space-y-3">
            <li><Link to="/mi-cuenta" className="text-gray-400 hover:text-white text-sm transition-colors font-medium">Mi Cuenta</Link></li>
            <li><Link to="/" className="text-gray-400 hover:text-white text-sm transition-colors font-medium">Envíos y Devoluciones</Link></li>
            <li><Link to="/" className="text-gray-400 hover:text-white text-sm transition-colors font-medium">Contacto</Link></li>
          </ul>
        </div>

        {/* SOCIAL/NEWSLETTER */}
        <div className="space-y-4">
          <h5 className="text-xs font-black uppercase tracking-[0.3em] text-urban-blue">Comunidad</h5>
          <div className="flex gap-4">
            <a href="javascript:void(0)" aria-label="Sintonizar Instagram" className="h-10 w-10 glass border border-white/10 flex items-center justify-center rounded-full hover:border-urban-blue transition-colors">📸</a>
            <a href="javascript:void(0)" aria-label="Sintonizar Twitter" className="h-10 w-10 glass border border-white/10 flex items-center justify-center rounded-full hover:border-urban-blue transition-colors">🐦</a>
          </div>
          <p className="text-gray-600 text-[10px] uppercase font-black tracking-widest pt-4">© 2026 iStore Chile — Urban Tech Wear.</p>
        </div>

      </div>
    </footer>
  );
}
