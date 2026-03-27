import { useState, useEffect } from 'react';
import { useStripe, useElements, CardElement } from '@stripe/react-stripe-js';
import Swal from 'sweetalert2';


// ✅ FIX 1: Agregamos "= 0" como valor por defecto en la firma
export default function CheckoutForm({ carrito, total = 0, cerrarModal, vaciarCarrito }) {
  const stripe = useStripe();
  const elements = useElements();
  const [cargando, setCargando] = useState(false);


  const [datosCliente, setDatosCliente] = useState({
    nombre: '',
    email: '',
    telefono: '',
    direccion: '',
    metodo_envio: 'Starken'
  });


  useEffect(() => {
    const token = localStorage.getItem('cliente_token');
    if (token) {
      fetch('https://istore-backend-nxvt.onrender.com/api/cliente/perfil', {
        headers: { 
          'Authorization': `Bearer ${token}`,
          'Accept': 'application/json'
        }
      })
      .then(res => {
        if (!res.ok) throw new Error("Token inválido");
        return res.json();
      })
      .then(data => {
        if (data.user) {
          setDatosCliente(prev => ({ ...prev, nombre: data.user.name, email: data.user.email }));
        }
      })
      .catch(err => console.log("Usuario no logueado o token expirado."));
    }
  }, []);


  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!stripe || !elements) return;

    setCargando(true);

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

    try {
      const response = await fetch('https://istore-backend-nxvt.onrender.com/api/pedidos', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          cliente_nombre: datosCliente.nombre,
          cliente_email: datosCliente.email,
          cliente_telefono: datosCliente.telefono,
          direccion: datosCliente.direccion,
          metodo_envio: datosCliente.metodo_envio,
          total: total,
          carrito: carrito,
          stripe_token: paymentMethod.id
        })
      });

      const data = await response.json();

      if (response.ok) {
        Swal.fire({
          title: '¡Pago Exitoso!',
          text: 'Tu boleta ha sido generada y enviada a tu correo.',
          icon: 'success',
          confirmButtonColor: '#0071e3'
        });
        vaciarCarrito();
        cerrarModal();
      } else {
        Swal.fire('Error', data.error || 'Hubo un problema con el pago.', 'error');
      }
    } catch (error) {
      Swal.fire('Error de Conexión', 'No se pudo contactar al servidor.', 'error');
    }

    setCargando(false);
  };


  return (
    <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '15px' }}>
      
      <input type="text" placeholder="Nombre completo" required value={datosCliente.nombre} onChange={e => setDatosCliente({...datosCliente, nombre: e.target.value})} style={inputStyle} />
      <input type="email" placeholder="Correo electrónico" required value={datosCliente.email} onChange={e => setDatosCliente({...datosCliente, email: e.target.value})} style={inputStyle} />
      <input type="tel" placeholder="Teléfono" required value={datosCliente.telefono} onChange={e => setDatosCliente({...datosCliente, telefono: e.target.value})} style={inputStyle} />
      <input type="text" placeholder="Dirección de envío" required value={datosCliente.direccion} onChange={e => setDatosCliente({...datosCliente, direccion: e.target.value})} style={inputStyle} />
      
      <select value={datosCliente.metodo_envio} onChange={e => setDatosCliente({...datosCliente, metodo_envio: e.target.value})} style={inputStyle}>
        <option value="Starken">Envío por Starken</option>
        <option value="Chilexpress">Envío por Chilexpress</option>
        <option value="Retiro en Tienda">Retiro en Tienda</option>
      </select>

      <div style={{ padding: '15px', border: '1px solid #ddd', borderRadius: '10px', background: 'white', marginTop: '10px' }}>
        <CardElement options={{ style: { base: { fontSize: '16px', color: '#333', '::placeholder': { color: '#aaa' } } } }} />
      </div>

      <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: '20px', alignItems: 'center' }}>
        {/* ✅ FIX 2: Doble seguro con (total ?? 0) antes del .toLocaleString() */}
        <h3 style={{ margin: 0 }}>Total: ${(total ?? 0).toLocaleString('es-CL')}</h3>
        <div style={{ display: 'flex', gap: '10px' }}>
          <button type="button" onClick={cerrarModal} style={{ padding: '12px 20px', borderRadius: '10px', border: 'none', background: '#e5e5ea', cursor: 'pointer', fontWeight: 'bold' }}>
            Cancelar
          </button>
          <button type="submit" disabled={!stripe || cargando} style={{ padding: '12px 20px', borderRadius: '10px', border: 'none', background: '#0071e3', color: 'white', cursor: 'pointer', fontWeight: 'bold' }}>
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