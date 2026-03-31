import { useState, useEffect } from 'react';
import Swal from 'sweetalert2';
import api from '../lib/api';

export default function Pedidos() {
  const [pedidos, setPedidos] = useState([]);
  const [cargando, setCargando] = useState(true);

  const cargarPedidos = async () => {
    try {
      setCargando(true);
      const response = await api.get('/admin/orders');
      setPedidos(response.data || []);
    } catch (err) {
      // Pedidos cargados silenciosamente
    } finally {
      setCargando(false);
    }
  };

  useEffect(() => { cargarPedidos() }, []);

  const cambiarEstado = async (id, nuevoEstado) => {
    try {
      const response = await api.put(`/admin/orders/${id}/status`, { status: nuevoEstado });
      if (response) {
        Swal.fire({ title: 'Estado Actualizado', icon: 'success', toast: true, position: 'top-end', showConfirmButton: false, timer: 1500 });
        cargarPedidos();
      }
    } catch (error) {
      Swal.fire('Error', error.message || 'No se pudo actualizar el estado', 'error');
    }
  };

  const verDetalle = (items) => {
    let listaHTML = items.map(item => `
      <li style="margin-bottom: 8px; display: flex; justify-content: space-between; gap: 15px;">
        <span>${item.quantity}x <b>${item.product_name}</b></span>
        <span style="color: #34c759; font-weight: bold;">$${(item.unit_price * item.quantity).toLocaleString('es-CL')}</span>
      </li>`).join('');

    Swal.fire({
      title: '📦 Detalle del Paquete',
      html: `<ul style="text-align: left; list-style: none; padding: 0;">${listaHTML}</ul>`,
      confirmButtonColor: '#0071e3'
    });
  };

  const getStatusBadgeStyle = (status) => {
    switch (status) {
      case 'paid': return { background: '#34c75933', color: '#34c759' };
      case 'shipped': return { background: '#0071e333', color: '#0071e3' };
      case 'pending': return { background: '#ff950033', color: '#ff9500' };
      case 'cancelled': return { background: '#ff3b3033', color: '#ff3b30' };
      default: return { background: '#333', color: 'white' };
    }
  };

  return (
    <div>
      <h2 style={{ fontSize: '28px', marginBottom: '5px' }}>🚚 Órdenes y Envíos</h2>
      <p style={{ color: '#888', marginBottom: '30px' }}>Gestiona los pedidos de tus clientes en tiempo real.</p>

      {cargando ? (
        <div style={{ color: 'white', textAlign: 'center', padding: '40px' }}>Cargando pedidos...</div>
      ) : pedidos.length === 0 ? (
        <div style={{ background: '#1d1d1f', padding: '40px', textAlign: 'center', borderRadius: '16px', color: '#888' }}>No hay pedidos registrados aún.</div>
      ) : (
        <div style={{ background: '#1d1d1f', borderRadius: '24px', overflow: 'hidden', boxShadow: '0 10px 30px rgba(0,0,0,0.5)' }}>
          <table style={{ width: '100%', borderCollapse: 'collapse', color: 'white', fontSize: '14px' }}>
            <thead>
              <tr style={{ background: '#2c2c2e', textAlign: 'left', color: '#888' }}>
                <th style={{ padding: '15px 20px' }}>Nº ORDEN</th>
                <th style={{ padding: '15px 20px' }}>CLIENTE</th>
                <th style={{ padding: '15px 20px' }}>ENVÍO</th>
                <th style={{ padding: '15px 20px' }}>TOTAL</th>
                <th style={{ padding: '15px 20px' }}>ESTADO</th>
                <th style={{ padding: '15px 20px', textAlign: 'center' }}>ACCIONES</th>
              </tr>
            </thead>
            <tbody>
              {pedidos.map(p => (
                <tr key={p.id} style={{ borderBottom: '1px solid #2c2c2e' }}>
                  <td style={{ padding: '15px 20px', fontWeight: 'bold' }}>#{p.order_number}</td>
                  <td style={{ padding: '15px 20px' }}>
                    <div style={{ fontWeight: 'bold' }}>{p.shipping.name}</div>
                    <div style={{ fontSize: '12px', color: '#888' }}>{p.shipping.street}, {p.shipping.city}</div>
                  </td>
                  <td style={{ padding: '15px 20px' }}>
                    <span style={{ background: '#333', padding: '4px 10px', borderRadius: '20px', fontSize: '12px' }}>
                      {p.shipping.method}
                    </span>
                  </td>
                  <td style={{ padding: '15px 20px', fontWeight: 'bold' }}>${p.total.toLocaleString()}</td>
                  <td style={{ padding: '15px 20px' }}>
                    <select
                      value={p.status}
                      onChange={(e) => cambiarEstado(p.id, e.target.value)}
                      style={{
                        ...getStatusBadgeStyle(p.status),
                        border: 'none',
                        padding: '6px 12px',
                        borderRadius: '20px',
                        fontWeight: 'bold',
                        cursor: 'pointer',
                        outline: 'none',
                        fontSize: '11px'
                      }}
                    >
                      <option value="pending">PENDIENTE</option>
                      <option value="paid">PAGADO</option>
                      <option value="shipped">ENVIADO</option>
                      <option value="delivered">ENTREGADO</option>
                      <option value="cancelled">CANCELADO</option>
                    </select>
                  </td>
                  <td style={{ padding: '15px 20px', textAlign: 'center' }}>
                    <button onClick={() => verDetalle(p.items)} style={{ background: '#0071e3', color: 'white', border: 'none', padding: '8px 15px', borderRadius: '8px', cursor: 'pointer', fontSize: '12px', fontWeight: 'bold' }}>
                      Ver Productos
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}