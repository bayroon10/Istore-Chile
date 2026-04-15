$base = "https://istore-backend-nxvt.onrender.com"
$headers_json = @{"Content-Type"="application/json";"Accept"="application/json"}

# PASO 1 - Login
$login = Invoke-RestMethod -Uri "$base/api/login" `
  -Method POST -Headers $headers_json `
  -Body '{"email":"admin@istore.com","password":"12345678"}'
$token = $login.token
Write-Host "1. LOGIN: $(if($token){'✅ OK'}else{'❌ FAIL'})"

$headers_auth = @{
  "Authorization"="Bearer $token"
  "Accept"="application/json"
  "Content-Type"="application/json"
}

# PASO 2 - Ver productos disponibles
$products = Invoke-RestMethod -Uri "$base/api/products" `
  -Method GET -Headers $headers_auth
$productId = $products.data[0].id
Write-Host "2. PRODUCTO ID: $productId - $($products.data[0].name)"

# PASO 3 - Agregar al carrito
try {
  $cart = Invoke-RestMethod -Uri "$base/api/cart/items" `
    -Method POST -Headers $headers_auth `
    -Body "{`"product_id`":$productId,`"quantity`":1}"
  Write-Host "3. CARRITO: ✅ Producto agregado"
} catch {
  $err = $_.Exception.Response
  $reader = New-Object System.IO.StreamReader($err.GetResponseStream())
  Write-Host "3. CARRITO ERROR: $($reader.ReadToEnd())"
}

# PASO 4 - Checkout con Stripe
try {
  $checkout = Invoke-RestMethod -Uri "$base/api/orders/checkout" `
    -Method POST -Headers $headers_auth `
    -Body '{
      "shipping_name":"Bairon Test",
      "shipping_phone":"+56912345678",
      "shipping_street":"Av. Test 123",
      "shipping_city":"Santiago",
      "shipping_region":"RM",
      "shipping_method":"standard",
      "shipping_cost":0,
      "discount":0
    }'
  Write-Host "4. CHECKOUT: ✅ OK"
  Write-Host "   client_secret: $($checkout.client_secret.Substring(0,20))..."
  Write-Host "   order_id: $($checkout.data.id)"
  Write-Host "   status: $($checkout.data.status)"
} catch {
  $err = $_.Exception.Response
  $reader = New-Object System.IO.StreamReader($err.GetResponseStream())
  $errBody = $reader.ReadToEnd()
  Write-Host "4. CHECKOUT ERROR $($_.Exception.Response.StatusCode):"
  Write-Host $errBody
}
