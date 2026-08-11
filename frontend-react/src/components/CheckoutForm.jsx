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
    ciudad: '',
    telefono: '',
    region: 'Metropolitana'
  });

  const [shippingMethod, setShippingMethod] = useState('Starken');

  const [errors, setErrors] = useState({});

  const handleChange = (e) => {
    setDatos({ ...datos, [e.target.name]: e.target.value });
    if (errors[e.target.name]) {
      setErrors(prev => ({ ...prev, [e.target.name]: false }));
    }
  };

  const handleSubmit = async (event) => {
    event.preventDefault();
    if (!stripe || !elements) return;

    // Validar campos requeridos y resaltar en rojo si están vacíos
    const nuevosErrores = {};
    if (!datos.nombre.trim()) nuevosErrores.nombre = true;
    if (!datos.email.trim()) nuevosErrores.email = true;
    if (!datos.telefono.trim()) nuevosErrores.telefono = true;
    if (!datos.direccion.trim()) nuevosErrores.direccion = true;
    if (!datos.ciudad.trim()) nuevosErrores.ciudad = true;

    if (Object.keys(nuevosErrores).length > 0) {
      setErrors(nuevosErrores);
      Swal.fire({
        title: 'Formulario Incompleto',
        text: 'Por favor completa todos los campos requeridos marcados en rojo.',
        icon: 'warning',
        background: '#000',
        color: '#fff',
        confirmButtonColor: '#0071e3'
      });
      return;
    }

    setCargando(true);

    try {
      // 1. Crear Orden y obtener Client Secret de Stripe
      const response = await api.post('/orders/checkout', {
        shipping_name: datos.nombre,
        shipping_email: datos.email,
        shipping_street: datos.direccion,
        shipping_city: datos.ciudad,
        shipping_region: datos.region,
        shipping_phone: datos.telefono,
        shipping_method: shippingMethod
      });

      const { client_secret, data } = response.data;
      const order_id = data?.id;

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
            text: `Tu orden #${order_id} ha sido procesada con éxito. Redirigiendo a tu perfil...`,
            icon: 'success',
            timer: 2000,
            showConfirmButton: false,
            background: '#000',
            color: '#fff'
          }).then(() => {
            onSuccess();
            window.location.href = `/mi-cuenta?order_id=${order_id}`;
          });
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
          { label: 'Teléfono de Contacto (9 dígitos)', name: 'telefono', type: 'tel', placeholder: ' ', pattern: '[0-9]{9}' },
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
              pattern={field.pattern}
              className={`peer w-full h-14 bg-carbon-grey/40 border ${errors[field.name] ? 'border-red-500/60 shadow-[0_0_15px_rgba(239,68,68,0.3)]' : 'border-white/5'} rounded-[1.2rem] px-5 pt-4 text-white text-sm outline-none focus:border-urban-blue/50 focus:shadow-neon-blue transition-all`}
            />
            <label className="absolute left-5 top-4 text-gray-400 text-sm font-bold uppercase tracking-widest pointer-events-none transition-all duration-300 peer-focus:-top-1 peer-focus:left-4 peer-focus:text-[10px] peer-focus:text-urban-blue peer-[:not(:placeholder-shown)]:-top-1 peer-[:not(:placeholder-shown)]:left-4 peer-[:not(:placeholder-shown)]:text-[10px] peer-[:not(:placeholder-shown)]:text-urban-blue">
              {field.label}
            </label>
          </div>
        ))}

        {/* Región con dropdown */}
        <div className="relative group">
          <select
            required
            name="region"
            value={datos.region}
            onChange={handleChange}
            className="w-full h-14 bg-carbon-grey/40 border border-white/5 rounded-[1.2rem] px-5 pt-4 text-white text-sm outline-none focus:border-urban-blue/50 focus:shadow-neon-blue transition-all appearance-none cursor-pointer"
          >
            <option value="Arica y Parinacota" className="bg-space-grey">Arica y Parinacota</option>
            <option value="Tarapacá" className="bg-space-grey">Tarapacá</option>
            <option value="Antofagasta" className="bg-space-grey">Antofagasta</option>
            <option value="Atacama" className="bg-space-grey">Atacama</option>
            <option value="Coquimbo" className="bg-space-grey">Coquimbo</option>
            <option value="Valparaíso" className="bg-space-grey">Valparaíso</option>
            <option value="Metropolitana" className="bg-space-grey">Metropolitana</option>
            <option value="O'Higgins" className="bg-space-grey">O'Higgins</option>
            <option value="Maule" className="bg-space-grey">Maule</option>
            <option value="Ñuble" className="bg-space-grey">Ñuble</option>
            <option value="Biobío" className="bg-space-grey">Biobío</option>
            <option value="Araucanía" className="bg-space-grey">Araucanía</option>
            <option value="Los Ríos" className="bg-space-grey">Los Ríos</option>
            <option value="Los Lagos" className="bg-space-grey">Los Lagos</option>
            <option value="Aysén" className="bg-space-grey">Aysén</option>
            <option value="Magallanes" className="bg-space-grey">Magallanes</option>
          </select>
          <label className="absolute left-5 top-1.5 text-[9px] text-urban-blue font-bold uppercase tracking-widest pointer-events-none transition-all duration-300">
            Región de Envío
          </label>
          <span className="absolute right-5 top-1/2 -translate-y-1/2 pointer-events-none text-gray-400">▼</span>
        </div>
      </div>

      {/* MÉTODO DE ENVÍO (SINCRONIZADO CON BACKEND) */}
      <div className="space-y-3">
        <p className="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 pl-2">Método de Envío</p>
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
        <p className="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 pl-2">Información de Pago</p>
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

      <p className="text-[10px] text-center text-gray-400 font-bold uppercase tracking-widest leading-relaxed">
        🔒 Encriptación AES-256 de grado militar.<br />Certificado por Stripe & iStore .
      </p>
    </form>
  );
}