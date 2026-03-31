import { useState, useEffect } from 'react';
import { useStripe, useElements, CardElement } from '@stripe/react-stripe-js';
import Swal from 'sweetalert2';
import { useAuth } from '../contexts/AuthContext';
import { useCart } from '../contexts/CartContext';
import api from '../lib/api';

export default function CheckoutForm({ total = 0, cerrarModal, onSuccess }) {
  const stripe = useStripe();
  const elements = useElements();
  const [cargando, setCargando] = useState(false);
  const { user } = useAuth();

  const [datosEnvio, setDatosEnvio] = useState({
    shipping_name: '',
    shipping_phone: '',
    shipping_street: '',
    shipping_city: 'Santiago',
    shipping_region: 'Región Metropolitana',
    shipping_method: 'Starken',
  });

  // Pre-rellenar con los datos del usuario autenticado
  useEffect(() => {
    if (user) {
      setDatosEnvio(prev => ({
        ...prev,
        shipping_name: user.name || '',
        shipping_phone: user.phone || '',
      }));
    }
  }, [user]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!stripe || !elements) return;

    setCargando(true);

    // 1. Crear PaymentMethod con Stripe
    const cardElement = elements.getElement(CardElement);
    const { error, paymentMethod } = await stripe.createPaymentMethod({
      type: 'card',
      card: cardElement,
    });

    if (error) {
      Swal.fire('Error en la tarjeta', error.message, 'error');
      setCargando(false);
      return;
    }

    // 2. Enviar al nuevo endpoint de checkout
    try {
      const data = await api.post('/orders/checkout', {
        ...datosEnvio,
        shipping_cost: datosEnvio.shipping_method === 'Retiro en Tienda' ? 0 : 3990,
        stripe_payment_id: paymentMethod.id,
      });

      Swal.fire({
        title: '¡Pago Exitoso!',
        html: `
          <p>Tu orden <strong>${data.data.order_number}</strong> ha sido creada.</p>
          <p style="color: #86868b; font-size: 14px;">Recibirás un correo con los detalles.</p>
        `,
        icon: 'success',
        confirmButtonColor: '#0071e3'
      });

      if (onSuccess) onSuccess();
    } catch (err) {
      Swal.fire('Error', err.message || 'Hubo un problema con el pago.', 'error');
    }

    setCargando(false);
  };

  return (
    <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '15px' }}>
      
      <input type="text" placeholder="Nombre completo" required value={datosEnvio.shipping_name} onChange={e => setDatosEnvio({...datosEnvio, shipping_name: e.target.value})} style={inputStyle} />
      <input type="tel" placeholder="Teléfono" required value={datosEnvio.shipping_phone} onChange={e => setDatosEnvio({...datosEnvio, shipping_phone: e.target.value})} style={inputStyle} />
      <input type="text" placeholder="Dirección de envío" required value={datosEnvio.shipping_street} onChange={e => setDatosEnvio({...datosEnvio, shipping_street: e.target.value})} style={inputStyle} />
      
      <div style={{ display: 'flex', gap: '10px' }}>
        <input type="text" placeholder="Ciudad" required value={datosEnvio.shipping_city} onChange={e => setDatosEnvio({...datosEnvio, shipping_city: e.target.value})} style={{ ...inputStyle, flex: 1 }} />
        <input type="text" placeholder="Región" required value={datosEnvio.shipping_region} onChange={e => setDatosEnvio({...datosEnvio, shipping_region: e.target.value})} style={{ ...inputStyle, flex: 1 }} />
      </div>

      <select value={datosEnvio.shipping_method} onChange={e => setDatosEnvio({...datosEnvio, shipping_method: e.target.value})} style={inputStyle}>
        <option value="Starken">Envío por Starken ($3.990)</option>
        <option value="Chilexpress">Envío por Chilexpress ($3.990)</option>
        <option value="Retiro en Tienda">Retiro en Tienda (Gratis)</option>
      </select>

      <div style={{ padding: '15px', border: '1px solid #ddd', borderRadius: '10px', background: 'white', marginTop: '10px' }}>
        <CardElement options={{ style: { base: { fontSize: '16px', color: '#333', '::placeholder': { color: '#aaa' } } } }} />
      </div>

      <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: '20px', alignItems: 'center' }}>
        <div>
          <h3 style={{ margin: 0 }}>Total: ${(total ?? 0).toLocaleString('es-CL')}</h3>
          {datosEnvio.shipping_method !== 'Retiro en Tienda' && (
            <p style={{ margin: '2px 0 0 0', fontSize: '13px', color: '#86868b' }}>+ $3.990 envío</p>
          )}
        </div>
        <div style={{ display: 'flex', gap: '10px' }}>
          <button type="button" onClick={cerrarModal} style={{ padding: '12px 20px', borderRadius: '10px', border: 'none', background: '#e5e5ea', cursor: 'pointer', fontWeight: 'bold' }}>
            Cancelar
          </button>
          <button type="submit" disabled={!stripe || cargando} style={{ padding: '12px 20px', borderRadius: '10px', border: 'none', background: cargando ? '#999' : '#0071e3', color: 'white', cursor: cargando ? 'not-allowed' : 'pointer', fontWeight: 'bold' }}>
            {cargando ? 'Procesando...' : 'Pagar Ahora'}
          </button>
        </div>
      </div>
      
    </form>
  );
}

const inputStyle = {
  padding: '12px',
  borderRadius: '10px',
  border: '1px solid #ddd',
  outline: 'none',
  fontSize: '15px'
};