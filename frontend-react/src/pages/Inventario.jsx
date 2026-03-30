import { useState, useEffect } from 'react';
import Swal from 'sweetalert2';

export default function Inventario() {
  const [productos, setProductos] = useState([]);
  const [formulario, setFormulario] = useState({ nombre: '', categoria: '', precio: '', stock_actual: '' });
  const [imagenActual, setImagenActual] = useState(null);
  const [editandoId, setEditandoId] = useState(null);

  const API_URL = 'https://istore-backend-nxvt.onrender.com/api/productos';
  
  // 🌟 AQUÍ ESTÁ LA PULSERA VIP
  const token = localStorage.getItem('token_istore'); 

  const cargarProductos = () => {
    fetch(API_URL)
      .then(res => res.json())
      .then(data => setProductos(data));
  };

  useEffect(() => { cargarProductos() }, []);

  const guardarProducto = async (e) => {
    e.preventDefault();
    const formData = new FormData();
    formData.append('nombre', formulario.nombre);
    formData.append('categoria', formulario.categoria);
    formData.append('precio', formulario.precio);
    formData.append('stock_actual', formulario.stock_actual);
    if (imagenActual) { formData.append('imagen', imagenActual); }
    if (editandoId) { formData.append('_method', 'PUT'); }

    const url = editandoId ? `${API_URL}/${editandoId}` : API_URL;

    try {
      const response = await fetch(url, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}` }, // 🌟 MOSTRAMOS LA PULSERA
        body: formData,
      });

      if (response.ok) {
        Swal.fire('¡Éxito!', 'Producto guardado correctamente', 'success');
        setFormulario({ nombre: '', categoria: '', precio: '', stock_actual: '' });
        setImagenActual(null);
        setEditandoId(null);
        document.getElementById('fileInput').value = "";
        cargarProductos();
      } else {
        Swal.fire('Error', 'No se pudo guardar el producto', 'error');
      }
    } catch (error) {
      Swal.fire('Error de Conexión', 'No se pudo contactar al servidor', 'error');
    }
  };

  const eliminarProducto = (id) => {
    Swal.fire({
      title: '¿Estás seguro?', text: "¡No podrás revertir esto!", icon: 'warning',
      showCancelButton: true, confirmButtonColor: '#ff3b30', cancelButtonColor: '#333', confirmButtonText: 'Sí, borrar', cancelButtonText: 'Cancelar'
    }).then(async (result) => {
      if (result.isConfirmed) {
        await fetch(`${API_URL}/${id}`, {
          method: 'DELETE',
          headers: { 'Authorization': `Bearer ${token}` } // 🌟 MOSTRAMOS LA PULSERA AL BORRAR
        });
        Swal.fire('¡Borrado!', 'El producto ha sido eliminado.', 'success');
        cargarProductos();
      }
    });
  };

  const editarProducto = (p) => {
    setFormulario({ nombre: p.nombre, categoria: p.categoria, precio: p.precio, stock_actual: p.stock_actual });
    setEditandoId(p.id);
  };

  // 🌟 FUNCIÓN PARA DESCARGAR EL EXCEL
  const descargarExcel = async () => {
    try {
      // Usamos la misma base de tu API_URL, pero apuntando a la ruta de reportes
      const urlExcel = 'https://istore-backend-nxvt.onrender.com/api/reports/products';
      
      const response = await fetch(urlExcel, {
        method: 'GET',
        headers: { 
          'Authorization': `Bearer ${token}` // Usamos la pulsera VIP que ya tienes definida
        }
      });

      if (!response.ok) {
        throw new Error('Error al generar el reporte');
      }

      // Convertimos la respuesta en un archivo binario (Blob)
      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', 'Inventario_iStore.xlsx');
      
      document.body.appendChild(link);
      link.click();
      link.parentNode.removeChild(link);
      
      Swal.fire('¡Listo!', 'El Excel se descargó correctamente', 'success');

    } catch (error) {
      console.error("Error al descargar:", error);
      Swal.fire('Error', 'No se pudo descargar el Excel', 'error');
    }
  };

  return (
    <div>
      {/* 🌟 AQUÍ CAMBIAMOS EL TÍTULO PARA AGREGAR EL BOTÓN */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '30px' }}>
        <div>
          <h2 style={{ fontSize: '28px', marginBottom: '5px' }}>📦 Inventario</h2>
          <p style={{ color: '#888', margin: 0 }}>Agrega, edita o elimina productos de tu tienda.</p>
        </div>
        
        <button 
          onClick={descargarExcel}
          style={{ background: '#34c759', color: 'white', border: 'none', padding: '12px 20px', borderRadius: '8px', cursor: 'pointer', fontWeight: 'bold', display: 'flex', gap: '8px', alignItems: 'center' }}
        >
          <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
            <path d="M5.884 6.68a.5.5 0 1 0-.768.64L7.349 10l-2.233 2.68a.5.5 0 0 0 .768.64L8 10.781l2.116 2.54a.5.5 0 0 0 .768-.641L8.651 10l2.233-2.68a.5.5 0 0 0-.768-.64L8 9.219l-2.116-2.54z"/>
            <path d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2zM9.5 3A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5v2z"/>
          </svg>
          Descargar Excel
        </button>
      </div>

      <form onSubmit={guardarProducto} style={{ background: '#1d1d1f', padding: '25px', borderRadius: '16px', marginBottom: '30px', display: 'flex', flexWrap: 'wrap', gap: '15px' }}>
        <input type="text" placeholder="Nombre" required value={formulario.nombre} onChange={e => setFormulario({...formulario, nombre: e.target.value})} style={{ flex: 1, minWidth: '200px', padding: '12px', borderRadius: '8px', border: 'none', background: '#2c2c2e', color: 'white', outline: 'none' }} />
        <input type="text" placeholder="Categoría" required value={formulario.categoria} onChange={e => setFormulario({...formulario, categoria: e.target.value})} style={{ flex: 1, minWidth: '150px', padding: '12px', borderRadius: '8px', border: 'none', background: '#2c2c2e', color: 'white', outline: 'none' }} />
        <input type="number" placeholder="Precio" required value={formulario.precio} onChange={e => setFormulario({...formulario, precio: e.target.value})} style={{ width: '120px', padding: '12px', borderRadius: '8px', border: 'none', background: '#2c2c2e', color: 'white', outline: 'none' }} />
        <input type="number" placeholder="Stock" required value={formulario.stock_actual} onChange={e => setFormulario({...formulario, stock_actual: e.target.value})} style={{ width: '100px', padding: '12px', borderRadius: '8px', border: 'none', background: '#2c2c2e', color: 'white', outline: 'none' }} />
        <input type="file" id="fileInput" onChange={e => setImagenActual(e.target.files[0])} style={{ padding: '10px', color: '#888' }} />
        
        <button type="submit" style={{ background: '#0071e3', color: 'white', border: 'none', padding: '12px 20px', borderRadius: '8px', cursor: 'pointer', fontWeight: 'bold' }}>
          {editandoId ? 'Actualizar' : 'Guardar'}
        </button>
        {editandoId && <button type="button" onClick={() => {setEditandoId(null); setFormulario({nombre:'', categoria:'', precio:'', stock_actual:''})}} style={{ background: '#444', color: 'white', border: 'none', padding: '12px 20px', borderRadius: '8px', cursor: 'pointer' }}>Cancelar</button>}
      </form>

      <div style={{ background: '#1d1d1f', borderRadius: '16px', overflow: 'hidden' }}>
        <table style={{ width: '100%', borderCollapse: 'collapse', color: 'white' }}>
          <thead>
            <tr style={{ background: '#2c2c2e', textAlign: 'left' }}>
              <th style={{ padding: '15px' }}>Imagen</th>
              <th style={{ padding: '15px' }}>Producto</th>
              <th style={{ padding: '15px' }}>Precio</th>
              <th style={{ padding: '15px' }}>Stock</th>
              <th style={{ padding: '15px', textAlign: 'center' }}>Acciones</th>
            </tr>
          </thead>
          <tbody>
            {productos.map(p => (
              <tr key={p.id} style={{ borderBottom: '1px solid #333' }}>
                <td style={{ padding: '15px' }}><img src={p.imagen ? `https://istore-backend-nxvt.onrender.com/storage/${p.imagen}` : 'https://via.placeholder.com/50'} alt="img" style={{ width: '50px', height: '50px', objectFit: 'cover', borderRadius: '8px', background: 'white' }}/></td>
                <td style={{ padding: '15px' }}><b>{p.nombre}</b><br/><small style={{color:'#888'}}>{p.categoria}</small></td>
                <td style={{ padding: '15px' }}>${p.precio}</td>
                <td style={{ padding: '15px' }}>{p.stock_actual}</td>
                <td style={{ padding: '15px', textAlign: 'center', display: 'flex', gap: '10px', justifyContent: 'center' }}>
                  <button onClick={() => editarProducto(p)} style={{ background: '#ff9500', color: 'white', border: 'none', padding: '8px 12px', borderRadius: '6px', cursor: 'pointer' }}>Editar</button>
                  <button onClick={() => eliminarProducto(p.id)} style={{ background: '#ff3b30', color: 'white', border: 'none', padding: '8px 12px', borderRadius: '6px', cursor: 'pointer' }}>Borrar</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}