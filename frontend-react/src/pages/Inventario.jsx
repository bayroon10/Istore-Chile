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

  return (
    <div>
      <h2 style={{ fontSize: '28px', marginBottom: '5px' }}>📦 Inventario</h2>
      <p style={{ color: '#888', marginBottom: '30px' }}>Agrega, edita o elimina productos de tu tienda.</p>

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