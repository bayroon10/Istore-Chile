import Swal from 'sweetalert2';
import { useCart } from '../contexts/CartContext';
import { useAuth } from '../contexts/AuthContext';

export default function CartDrawer({ onCheckoutStart }) {
  const { verCarrito, cerrarCarrito, items, totalItems, totalPrice, updateQuantity, removeItem } = useCart();
  const { isAuthenticated } = useAuth();

  return (
    <div
      className={`fixed inset-y-0 right-0 z-[150] w-full max-w-sm glass-dark border-l border-white/10 shadow-[-20px_0_50px_rgba(0,0,0,0.5)] transform transition-transform duration-500 ease-out p-6 flex flex-col ${
        verCarrito ? 'translate-x-0' : 'translate-x-full'
      }`}
    >
      {/* Header */}
      <div className="flex items-center justify-between mb-8 border-b border-white/10 pb-4">
        <h3 className="text-2xl font-black">
          Tu Bolsa <span className="text-urban-blue">({totalItems})</span>
        </h3>
        <button
          onClick={cerrarCarrito}
          aria-label="Cerrar bolsa de compras"
          className="p-2 hover:bg-white/5 rounded-full transition-colors text-gray-400 hover:text-white cursor-pointer"
        >
          ✕
        </button>
      </div>

      {/* Item list */}
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
                  src={
                    item.product_image && !item.product_image.includes('via.placeholder.com')
                      ? item.product_image
                      : `https://placehold.co/80x80/0a0a0a/3b82f6?text=${encodeURIComponent((item.product_name || 'i').slice(0, 2))}`
                  }
                  alt={item.product_name || 'Producto'}
                  loading="lazy"
                  onError={(e) => {
                    e.target.src = 'https://placehold.co/80x80/000/3b82f6?text=i';
                  }}
                  className="w-16 h-16 object-contain"
                />
              </div>
              <div className="flex-1 min-w-0">
                <p className="font-bold text-sm truncate">{item.product_name}</p>
                <div className="flex items-center gap-3 mt-2">
                  <button
                    onClick={() => updateQuantity(item.product_id, item.quantity - 1)}
                    aria-label={`Disminuir cantidad de ${item.product_name}`}
                    className="w-6 h-6 rounded-full glass border border-white/10 flex items-center justify-center hover:bg-urban-blue/20 transition-all cursor-pointer"
                  >
                    -
                  </button>
                  <span className="text-sm font-black">{item.quantity}</span>
                  <button
                    onClick={() => updateQuantity(item.product_id, item.quantity + 1)}
                    aria-label={`Aumentar cantidad de ${item.product_name}`}
                    className="w-6 h-6 rounded-full glass border border-white/10 flex items-center justify-center hover:bg-urban-blue/20 transition-all cursor-pointer"
                  >
                    +
                  </button>
                  <button
                    onClick={() => removeItem(item.product_id)}
                    className="ml-auto text-xs font-bold text-red-500/80 hover:text-red-500 transition-colors uppercase tracking-widest cursor-pointer"
                  >
                    Eliminar
                  </button>
                </div>
              </div>
              <div className="text-right font-black text-urban-blue">${item.subtotal.toLocaleString()}</div>
            </div>
          ))
        )}
      </div>

      {/* Footer / Total & Checkout button */}
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
                confirmButtonColor: '#0071e3',
              }).then(() => (window.location.href = '/mi-cuenta'));
              return;
            }
            cerrarCarrito();
            if (onCheckoutStart) {
              onCheckoutStart();
            } else {
              window.location.href = '/tienda?checkout=true';
            }
          }}
          className="w-full py-5 rounded-[1.2rem] bg-urban-blue text-white font-black text-lg shadow-neon-blue hover:shadow-neon-glow transition-all duration-300 disabled:opacity-50 disabled:grayscale cursor-pointer"
        >
          PAGAR AHORA ➔
        </button>
      </div>
    </div>
  );
}
