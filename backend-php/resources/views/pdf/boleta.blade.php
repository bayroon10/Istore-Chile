<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Boleta de Compra</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; color: #1d1d1f; }
        .header { text-align: center; border-bottom: 2px solid #f5f5f7; padding-bottom: 20px; margin-bottom: 20px; }
        .logo { font-size: 24px; font-weight: bold; margin: 0; }
        .subtitle { color: #86868b; font-size: 14px; margin: 5px 0; }
        .datos-cliente { background: #f5f5f7; padding: 15px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; }
        .datos-cliente p { margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background: #1d1d1f; color: white; padding: 10px; text-align: left; font-size: 14px; }
        td { padding: 10px; border-bottom: 1px solid #eee; font-size: 14px; }
        .total-container { text-align: right; font-size: 18px; font-weight: bold; color: #0071e3; }
        .footer { text-align: center; margin-top: 40px; font-size: 12px; color: #86868b; }
    </style>
</head>
<body>

    <div class="header">
        <h1 class="logo"> iStore Chile</h1>
        <p class="subtitle">Boleta Electrónica de Venta - Pedido #{{ $pedido->id }}</p>
    </div>

    <div class="datos-cliente">
        <p><strong>👤 Cliente:</strong> {{ $pedido->cliente_nombre }}</p>
        <p><strong>📧 Correo:</strong> {{ $pedido->cliente_email }}</p>
        <p><strong>📍 Dirección:</strong> {{ $pedido->direccion }}</p>
        <p><strong>🚚 Envío:</strong> {{ $pedido->metodo_envio }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th>Cant.</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pedido->carrito as $item)
            <tr>
                <td>{{ $item['nombre'] }}</td>
                <td>{{ $item['cantidad'] }}</td>
                <td>${{ number_format($item['precio'] * $item['cantidad'], 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total-container">
        Total Pagado: ${{ number_format($pedido->total, 0, ',', '.') }}
    </div>

    <div class="footer">
        <p>¡Gracias por tu compra en iStore!</p>
        <p>Si elegiste Starken o Chilexpress, te enviaremos el número de seguimiento pronto.</p>
    </div>

</body>
</html>