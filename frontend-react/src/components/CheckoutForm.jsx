import { useState } from 'react';
import { CardElement, useStripe, useElements } from '@stripe/react-stripe-js';
import api from '../lib/api';
import Swal from 'sweetalert2';

export default function CheckoutForm({ total, cerrarModal, onSuccess }) {
  const stripe = useStripe();
  const elements = useElements();
  const [cargando, setCargando] = useState(false);

  // Datos del cliente
  const [datos, setDatos] = useState({
    nombre: '',
    email: '',
    direccion: '',
    ciudad: ''
  });

  const [shippingMethod, setShippingMethod] = useState('Starken');

  const handleChange = (e) => {
    setDatos({ ...datos, [e.target.name]: e.target.value });
  };

  const handleSubmit = async (event) => {
    event.preventDefault();
    if (!stripe || !elements) return;

    setCargando(true);

    try {
      // 1. Crear Orden y obtener Client Secret de Stripe
      const response = await api.post('/orders/checkout', {
        shipping_name: datos.nombre,
        shipping_email: datos.email,
        shipping_street: datos.direccion,
        shipping_city: datos.ciudad,
        shipping_region: 'Metropolitana', // Default
        shipping_phone: '999999999',     // Placeholder
        shipping_method: shippingMethod
      });

      const { client_secret, order_id } = response.data;

      // 2. Confirmar pago con Stripe
      const result = await stripe.confirmCardPayment(client_secret, {
        payment_method: {
          card: elements.getElement(CardElement),
          billing_details: {
            name: datos.nombre,
            email: datos.email,
          },
        },
      });

      if (result.error) {
        Swal.fire({
          title: 'Error en el pago',
          text: result.error.message,
          icon: 'error',
          background: '#000',
          color: '#fff',
          confirmButtonColor: '#0071e3'
        });
      } else {
        if (result.paymentIntent.status === 'succeeded') {
          Swal.fire({
            title: '¡Pago Exitoso!',
            text: `Tu orden #${order_id} ha sido procesada. Recibirás un correo pronto.`,
            icon: 'success',
            background: '#000',
            color: '#fff',
            confirmButtonColor: '#0071e3'
          });
          onSuccess();
        }
      }
    } catch (error) {
      Swal.fire({
        title: 'Error',
        text: error.message || 'No se pudo procesar la orden',
        icon: 'error',
        background: '#000',
        color: '#fff',
        confirmButtonColor: '#0071e3'
      });
    } finally {
      setCargando(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-6">

      {/* SECCIÓN DE DATOS (CON FLOATING LABELS TECH) */}
      <div className="grid grid-cols-1 gap-4">
        {[
          { label: 'Nombre Completo', name: 'nombre', type: 'text', placeholder: ' ' },
          { label: 'Correo Electrónico', name: 'email', type: 'email', placeholder: ' ' },
          { label: 'Dirección de Envío', name: 'direccion', type: 'text', placeholder: ' ' },
          { label: 'Ciudad', name: 'ciudad', type: 'text', placeholder: ' ' }
        ].map((field) => (
          <div key={field.name} className="relative group">
            <input
              required
              type={field.type}
              name={field.name}
              value={datos[field.name]}
              onChange={handleChange}
              placeholder={field.placeholder}
              className="peer w-full h-14 bg-carbon-grey/40 border border-white/5 rounded-[1.2rem] px-5 pt-4 text-white text-sm outline-none focus:border-urban-blue/50 focus:shadow-neon-blue transition-all"
            />
            <label className="absolute left-5 top-4 text-gray-500 text-sm font-bold uppercase tracking-widest pointer-events-none transition-all duration-300 peer-focus:-top-1 peer-focus:left-4 peer-focus:text-[10px] peer-focus:text-urban-blue peer-[:not(:placeholder-shown)]:-top-1 peer-[:not(:placeholder-shown)]:left-4 peer-[:not(:placeholder-shown)]:text-[10px] peer-[:not(:placeholder-shown)]:text-urban-blue">
              {field.label}
            </label>
          </div>
        ))}
      </div>

      {/* MÉTODO DE ENVÍO (SINCRONIZADO CON BACKEND) */}
      <div className="space-y-3">
        <p className="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 pl-2">Método de Envío</p>
        <div className="relative group">
          <select
            value={shippingMethod}
            onChange={(e) => setShippingMethod(e.target.value)}
            className="w-full h-14 bg-carbon-grey/40 border border-white/5 rounded-[1.2rem] px-5 text-white text-sm outline-none focus:border-urban-blue/50 focus:shadow-neon-blue transition-all appearance-none cursor-pointer"
          >
            <option value="Starken" className="bg-space-grey">Starken (Envío a Domicilio - $3.990)</option>
            <option value="Chilexpress" className="bg-space-grey">Chilexpress (Envío Express - $4.500)</option>
            <option value="Retiro" className="bg-space-grey">Retiro en Tienda (Gratis)</option>
          </select>
          <span className="absolute right-5 top-1/2 -translate-y-1/2 pointer-events-none text-gray-400">▼</span>
        </div>
      </div>

      {/* TARJETA STRIPE (URBAN STYLE) */}
      <div className="space-y-3">
        <p className="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 pl-2">Información de Pago</p>
        <div className="glass-dark p-6 rounded-[1.2rem] border border-white/5 group focus-within:border-urban-blue/30 transition-all">
          <CardElement options={{
            style: {
              base: {
                fontSize: '16px',
                color: '#fff',
                fontFamily: 'Inter, sans-serif',
                '::placeholder': { color: '#666' },
              },
              invalid: { color: '#ef4444' },
            },
          }} />
        </div>
      </div>

      <div className="flex gap-3 pt-4">
        <button
          type="button"
          onClick={cerrarModal}
          className="flex-1 py-4 rounded-2xl bg-space-grey text-gray-400 font-black uppercase text-xs tracking-widest hover:text-white transition-all"
        >
          Cancelar
        </button>
        <button
          disabled={!stripe || cargando}
          className="flex-[2] py-4 rounded-2xl bg-urban-blue text-white font-black uppercase text-xs tracking-widest shadow-neon-blue hover:shadow-neon-glow transition-all disabled:opacity-50 disabled:grayscale"
        >
          {cargando ? 'PROCESANDO...' : `CONFIRMAR — $${total.toLocaleString()}`}
        </button>
      </div>

      <p className="text-[10px] text-center text-gray-600 font-bold uppercase tracking-widest leading-relaxed">
        🔒 Encriptación AES-256 de grado militar.<br />Certificado por Stripe & iStore .
      </p>
    </form>
  );
}