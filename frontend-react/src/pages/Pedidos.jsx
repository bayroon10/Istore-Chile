import { useState, useEffect } from 'react';
import Swal from 'sweetalert2';

export default function Pedidos() {
  const [pedidos, setPedidos] = useState([]);
  const API_URL = 'http://127.0.0.1:8000/api/pedidos';
  
  // 🌟 SACAMOS LA PULSERA DEL BOLSILLO
  const token = localStorage.getItem('token_istore');

  const cargarPedidos = () => {
    // 🌟 LE MOSTRAMOS LA PULSERA AL GUARDIA PARA VER LOS PEDIDOS
    fetch(API_URL, {
      headers: { 'Authorization': `Bearer ${token}` }
    })
      .then(res => res.json())
      .then(data => setPedidos(data));
  };

  useEffect(() => { cargarPedidos() }, []);

  const cambiarEstado = async (id, nuevoEstado) => {
    try {
      const response = await fetch(`${API_URL}/${id}`, {
        method: 'PUT',
        headers: { 
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}` // 🌟 PULSERA PARA EDITAR
        },
        body: JSON.stringify({ estado: nuevoEstado })
      });
      if (response.ok) {
        Swal.fire({ title: 'Actualizado', icon: 'success', toast: true, position: 'top-end', showConfirmButton: false, timer: 1500 });
        cargarPedidos();
      }
    } catch (error) {
      Swal.fire('Error', 'No se pudo actualizar el estado', 'error');
    }
  };

  const verDetalle = (carrito) => {
    let listaHTML = carrito.map(item => `<li>${item.cantidad}x <b>${item.nombre}</b> ($${item.precio})</li>`).join('');
    Swal.fire({ title: '📦 Detalle del Paquete', html: `<ul style="text-align: left;">${listaHTML}</ul>`, confirmButtonColor: '#0071e3' });
  };

  return (
    <div>
      <h2 style={{ fontSize: '28px', marginBottom: '5px' }}>🚚 Órdenes y Envíos</h2>
      <p style={{ color: '#888', marginBottom: '30px' }}>Gestiona los pedidos de tus clientes aquí.</p>

      {pedidos.length === 0 ? (
        <div style={{ background: '#1d1d1f', padding: '40px', textAlign: 'center', borderRadius: '16px', color: '#888' }}>No hay pedidos registrados aún.</div>
      ) : (
        <div style={{ background: '#1d1d1f', borderRadius: '16px', overflow: 'hidden' }}>
          <table style={{ width: '100%', borderCollapse: 'collapse', color: 'white', fontSize: '15px' }}>
            <thead>
              <tr style={{ background: '#2c2c2e', textAlign: 'left' }}>
                <th style={{ padding: '15px 20px', color: '#888' }}>ID</th>
                <th style={{ padding: '15px 20px', color: '#888' }}>Cliente</th>
                <th style={{ padding: '15px 20px', color: '#888' }}>Envío</th>
                <th style={{ padding: '15px 20px', color: '#888' }}>Total</th>
                <th style={{ padding: '15px 20px', color: '#888' }}>Estado</th>
                <th style={{ padding: '15px 20px', color: '#888', textAlign: 'center' }}>Acciones</th>
              </tr>
            </thead>
            <tbody>
              {pedidos.map(p => (
                <tr key={p.id} style={{ borderBottom: '1px solid #333' }}>
                  <td style={{ padding: '15px 20px', fontWeight: 'bold' }}>#{p.id}</td>
                  <td style={{ padding: '15px 20px' }}>
                    <div style={{ fontWeight: 'bold' }}>{p.cliente_nombre}</div>
                    <div style={{ fontSize: '13px', color: '#888' }}>{p.direccion}</div>
                  </td>
                  <td style={{ padding: '15px 20px' }}><span style={{ background: '#333', padding: '5px 10px', borderRadius: '8px', fontSize: '13px' }}>{p.metodo_envio}</span></td>
                  <td style={{ padding: '15px 20px', fontWeight: 'bold', color: '#34c759' }}>${p.total.toLocaleString()}</td>
                  <td style={{ padding: '15px 20px' }}>
                    <select value={p.estado} onChange={(e) => cambiarEstado(p.id, e.target.value)} style={{ background: p.estado === 'Pendiente' ? '#ff9500' : p.estado === 'Enviado' ? '#34c759' : '#ff3b30', color: 'white', border: 'none', padding: '8px 12px', borderRadius: '8px', fontWeight: 'bold', cursor: 'pointer', outline: 'none' }}>
                      <option value="Pendiente">Pendiente</option>
                      <option value="Enviado">Enviado</option>
                      <option value="Cancelado">Cancelado</option>
                    </select>
                  </td>
                  <td style={{ padding: '15px 20px', textAlign: 'center' }}>
                    <button onClick={() => verDetalle(p.carrito)} style={{ background: '#444', color: 'white', border: 'none', padding: '8px 15px', borderRadius: '8px', cursor: 'pointer', transition: '0.2s' }}>Ver Paquete</button>
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