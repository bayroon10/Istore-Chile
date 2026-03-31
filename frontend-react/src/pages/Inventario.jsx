import { useState, useEffect } from 'react';
import Swal from 'sweetalert2';
import api from '../lib/api';

export default function Inventario() {
  const [productos, setProductos] = useState([]);
  const [categorias, setCategorias] = useState([]);
  const [formulario, setFormulario] = useState({ name: '', category_id: '', price: '', stock: '', description: '' });
  const [imagenActual, setImagenActual] = useState(null);
  const [editandoId, setEditandoId] = useState(null);
  const [loading, setLoading] = useState(true);

  const cargarDatos = async () => {
    setLoading(true);
    try {
      const [resProd, resCat] = await Promise.all([
        api.get('/products'),
        api.get('/categories')
      ]);
      setProductos(resProd.data || []);
      setCategorias(resCat.data || []);
    } catch (err) {
      console.error('Error cargando datos:', err);
      Swal.fire('Error', 'No se pudieron cargar los datos del inventario', 'error');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { cargarDatos() }, []);

  const guardarProducto = async (e) => {
    e.preventDefault();
    const formData = new FormData();
    formData.append('name', formulario.name);
    formData.append('category_id', formulario.category_id);
    formData.append('price', formulario.price);
    formData.append('stock', formulario.stock);
    formData.append('description', formulario.description);
    
    if (imagenActual) { 
      formData.append('image', imagenActual); 
    }
    
    if (editandoId) { 
      formData.append('_method', 'PUT'); 
    }

    const url = editandoId ? `/products/${editandoId}` : '/products';

    try {
      // Usamos fetch directo para Multipart/Form-Data con el helper de token
      const token = api.getToken();
      const response = await fetch(`${api.API_BASE}${url}`, {
        method: 'POST',
        headers: { 
          'Authorization': `Bearer ${token}`,
          'Accept': 'application/json'
        },
        body: formData,
      });

      const data = await response.json();

      if (response.ok) {
        Swal.fire('¡Éxito!', 'Producto guardado correctamente', 'success');
        resetFormulario();
        cargarDatos();
      } else {
        Swal.fire('Error', data.message || 'No se pudo guardar el producto', 'error');
      }
    } catch (error) {
      Swal.fire('Error de Conexión', 'No se pudo contactar al servidor', 'error');
    }
  };

  const resetFormulario = () => {
    setFormulario({ name: '', category_id: '', price: '', stock: '', description: '' });
    setImagenActual(null);
    setEditandoId(null);
    if (document.getElementById('fileInput')) {
      document.getElementById('fileInput').value = "";
    }
  }

  const eliminarProducto = (id) => {
    Swal.fire({
      title: '¿Estás seguro?', 
      text: "¡No podrás revertir esto!", 
      icon: 'warning',
      showCancelButton: true, 
      confirmButtonColor: '#ff3b30', 
      cancelButtonColor: '#333', 
      confirmButtonText: 'Sí, borrar', 
      cancelButtonText: 'Cancelar'
    }).then(async (result) => {
      if (result.isConfirmed) {
        try {
          await api.delete(`/products/${id}`);
          Swal.fire('¡Borrado!', 'El producto ha sido eliminado.', 'success');
          cargarDatos();
        } catch (err) {
          Swal.fire('Error', err.message, 'error');
        }
      }
    });
  };

  const editarProducto = (p) => {
    setFormulario({ 
      name: p.name, 
      category_id: p.category?.id || '', 
      price: p.price, 
      stock: p.stock,
      description: p.description || ''
    });
    setEditandoId(p.id);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const descargarExcel = async () => {
    try {
      const response = await fetch(`${api.API_BASE}/reports/products`, {
        method: 'GET',
        headers: { 
          'Authorization': `Bearer ${api.getToken()}`
        }
      });

      if (!response.ok) throw new Error('Error al generar el reporte');

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
      Swal.fire('Error', 'No se pudo descargar el Excel', 'error');
    }
  };

  if (loading) {
    return <div style={{ color: 'white', textAlign: 'center', padding: '100px' }}>Cargando inventario...</div>
  }

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '30px' }}>
        <div>
          <h2 style={{ fontSize: '28px', marginBottom: '5px' }}>📦 Inventario</h2>
          <p style={{ color: '#888', margin: 0 }}>Gestiona los productos del marketplace.</p>
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
        <input type="text" placeholder="Nombre" required value={formulario.name} onChange={e => setFormulario({...formulario, name: e.target.value})} style={inputStyle} />
        
        <select required value={formulario.category_id} onChange={e => setFormulario({...formulario, category_id: e.target.value})} style={inputStyle}>
            <option value="">Selecciona Categoría</option>
            {categorias.map(cat => (
                <option key={cat.id} value={cat.id}>{cat.name}</option>
            ))}
        </select>

        <input type="number" placeholder="Precio" required value={formulario.price} onChange={e => setFormulario({...formulario, price: e.target.value})} style={{ ...inputStyle, width: '120px' }} />
        <input type="number" placeholder="Stock" required value={formulario.stock} onChange={e => setFormulario({...formulario, stock: e.target.value})} style={{ ...inputStyle, width: '100px' }} />
        <input type="file" id="fileInput" onChange={e => setImagenActual(e.target.files[0])} style={{ padding: '10px', color: '#888' }} />
        
        <button type="submit" style={{ background: '#0071e3', color: 'white', border: 'none', padding: '12px 20px', borderRadius: '8px', cursor: 'pointer', fontWeight: 'bold' }}>
          {editandoId ? 'Actualizar' : 'Guardar'}
        </button>
        {editandoId && <button type="button" onClick={resetFormulario} style={{ background: '#444', color: 'white', border: 'none', padding: '12px 20px', borderRadius: '8px', cursor: 'pointer' }}>Cancelar</button>}
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
                <td style={{ padding: '15px' }}>
                    <img src={p.primary_image_url || 'https://via.placeholder.com/50'} alt={p.name} style={{ width: '50px', height: '50px', objectFit: 'contain', borderRadius: '8px', background: 'white' }}/>
                </td>
                <td style={{ padding: '15px' }}><b>{p.name}</b><br/><small style={{color:'#888'}}>{p.category?.name}</small></td>
                <td style={{ padding: '15px' }}>${p.price.toLocaleString()}</td>
                <td style={{ padding: '15px' }}>{p.stock}</td>
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

const inputStyle = {
    flex: 1, 
    minWidth: '150px', 
    padding: '12px', 
    borderRadius: '8px', 
    border: 'none', 
    background: '#2c2c2e', 
    color: 'white', 
    outline: 'none'
};