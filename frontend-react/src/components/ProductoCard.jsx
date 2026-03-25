// src/components/ProductoCard.jsx

export default function ProductoCard({ producto, alEditar, alEliminar }) {
  return (
    <div style={{ border: '1px solid #ddd', padding: '15px', borderRadius: '10px', background: 'white', color: 'black', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
      <div>
        <strong>{producto.nombre}</strong> - ${producto.precio} <br/>
        <small>Categoría: {producto.categoria} | Stock: {producto.stock_actual}</small>
      </div>
      
      <div style={{ display: 'flex', gap: '10px' }}>
        <button onClick={() => alEditar(producto)} style={{ background: '#ff9500', color: 'white', border: 'none', padding: '8px 15px', borderRadius: '5px', cursor: 'pointer', fontWeight: 'bold' }}>
          Editar
        </button>
        <button onClick={() => alEliminar(producto.id)} style={{ background: '#ff3b30', color: 'white', border: 'none', padding: '8px 15px', borderRadius: '5px', cursor: 'pointer', fontWeight: 'bold' }}>
          Borrar
        </button>
      </div>
    </div>
  )
}