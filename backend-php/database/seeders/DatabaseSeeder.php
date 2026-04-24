<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Review;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;


class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // -------------------------------------------------------
        // 1. Usuarios
        // -------------------------------------------------------
        $admin = User::firstOrCreate(
            ['email' => 'admin@istore.com'],
            [
                'name'     => 'Bairon Admin',
                'password' => Hash::make('12345678'),
                'role'     => 'admin',
            ]
        );

        $customer = User::firstOrCreate(
            ['email' => 'cliente@istore.com'],
            [
                'name'     => 'María González',
                'password' => Hash::make('12345678'),
                'role'     => 'customer',
                'phone'    => '+56912345678',
            ]
        );

        // -------------------------------------------------------
        // 2. Categorías
        // -------------------------------------------------------
        $categoriesData = [
            ['name' => 'Headphones',   'icon' => '🎧'],
            ['name' => 'Chargers',     'icon' => '🔌'],
            ['name' => 'Smartwatches', 'icon' => '⌚'],
            ['name' => 'Phone Cases',  'icon' => '📱'],
            ['name' => 'Cables',       'icon' => '🔋'],
        ];

        foreach ($categoriesData as $cat) {
            Category::firstOrCreate(
                ['name' => $cat['name']],
                ['icon' => $cat['icon']]
            );
        }

        $headphones = Category::where('name', 'Headphones')->first();
        $chargers   = Category::where('name', 'Chargers')->first();

        // -------------------------------------------------------
        // 3. Productos
        // -------------------------------------------------------
        $airpods = Product::firstOrCreate(
            ['name' => 'AirPods Pro 2'],
            [
                'category_id' => $headphones->id,
                'description' => 'Active noise cancellation with Adaptive Transparency.',
                'price'       => 89990,
                'stock'       => 20,
                'is_featured' => 'true',
            ]
        );

        $sony = Product::firstOrCreate(
            ['name' => 'Sony WH-1000XM5'],
            [
                'category_id' => $headphones->id,
                'description' => 'Industry-leading noise canceling headphones.',
                'price'       => 129990,
                'stock'       => 10,
                'is_featured' => 'true',
            ]
        );

        $charger = Product::firstOrCreate(
            ['name' => 'USB-C 20W Fast Charger'],
            [
                'category_id' => $chargers->id,
                'description' => 'Compatible with iPhone 15 and Android.',
                'price'       => 12990,
                'stock'       => 50,
            ]
        );

        // -------------------------------------------------------
        // 4. Dirección del cliente demo
        // -------------------------------------------------------
        Address::firstOrCreate(
            ['user_id' => $customer->id, 'label' => 'Casa'],
            [
                'full_name'  => 'María González',
                'phone'      => '+56912345678',
                'street'     => 'Av. Providencia 1234, Depto 5B',
                'city'       => 'Santiago',
                'region'     => 'Región Metropolitana',
                'is_default' => 'true',
            ]
        );

        // -------------------------------------------------------
        // 5. Carrito activo del cliente demo
        // -------------------------------------------------------
        $cart = Cart::firstOrCreate(['user_id' => $customer->id]);

        CartItem::firstOrCreate(
            ['cart_id' => $cart->id, 'product_id' => $charger->id],
            ['quantity' => 2]
        );

        // -------------------------------------------------------
        // 6. Orden completada (status: delivered)
        // -------------------------------------------------------
        $order = Order::firstOrCreate(
            ['order_number' => 'IST-20260301-0001'],
            [
                'user_id'         => $customer->id,
                'shipping_name'   => 'María González',
                'shipping_phone'  => '+56912345678',
                'shipping_street' => 'Av. Providencia 1234, Depto 5B',
                'shipping_city'   => 'Santiago',
                'shipping_region' => 'Región Metropolitana',
                'shipping_method' => 'Starken',
                'status'          => 'delivered',
                'subtotal'        => $airpods->price,
                'shipping_cost'   => 3990,
                'total'           => $airpods->price + 3990,
                'payment_method'  => 'stripe',
                'paid_at'         => now()->subDays(15),
                'shipped_at'      => now()->subDays(13),
                'delivered_at'    => now()->subDays(10),
            ]
        );

        OrderItem::firstOrCreate(
            ['order_id' => $order->id, 'product_id' => $airpods->id],
            [
                'product_name'  => $airpods->name,
                'product_price' => $airpods->price,
                'quantity'      => 1,
                'subtotal'      => $airpods->price,
            ]
        );

        // -------------------------------------------------------
        // 7. Segunda orden (status: paid)
        // -------------------------------------------------------
        $order2 = Order::firstOrCreate(
            ['order_number' => 'IST-20260315-0001'],
            [
                'user_id'         => $customer->id,
                'shipping_name'   => 'María González',
                'shipping_phone'  => '+56912345678',
                'shipping_street' => 'Av. Providencia 1234, Depto 5B',
                'shipping_city'   => 'Santiago',
                'shipping_region' => 'Región Metropolitana',
                'shipping_method' => 'Chilexpress',
                'status'          => 'paid',
                'subtotal'        => $sony->price,
                'shipping_cost'   => 4990,
                'total'           => $sony->price + 4990,
                'payment_method'  => 'stripe',
                'paid_at'         => now()->subDays(2),
            ]
        );

        OrderItem::firstOrCreate(
            ['order_id' => $order2->id, 'product_id' => $sony->id],
            [
                'product_name'  => $sony->name,
                'product_price' => $sony->price,
                'quantity'      => 1,
                'subtotal'      => $sony->price,
            ]
        );

        // -------------------------------------------------------
        // 8. Reseña del cliente en el producto entregado
        // -------------------------------------------------------
        Review::firstOrCreate(
            ['user_id' => $customer->id, 'product_id' => $airpods->id],
            [
                'order_id' => $order->id,
                'rating'   => 5,
                'comment'  => 'Excelente calidad de sonido. Los mejores audífonos que he tenido.',
            ]
        );

        // Recalcular el rating_avg y reviews_count del producto
        $airpods->recalculateRating();

        // -------------------------------------------------------
        // 9. Catálogo iStore Premium (Migrado desde Backdoor)
        // -------------------------------------------------------
        $categoriasInfo = [
            ['name' => 'iPhone', 'slug' => 'iphone'],
            ['name' => 'MacBook', 'slug' => 'macbook'],
            ['name' => 'Accesorios', 'slug' => 'accesorios'],
        ];
        
        $categorias = [];
        foreach ($categoriasInfo as $catData) {
            $categorias[$catData['slug']] = Category::firstOrCreate(
                ['slug' => $catData['slug']],
                ['name' => $catData['name']]
            );
        }


        $productosInfo = [
            [
                'name' => 'iPhone 15 Pro Max',
                'description' => 'El iPhone más avanzado con titanio.',
                'price' => 1299990,
                'stock' => 15,
                'category_slug' => 'iphone',
                'image' => 'https://via.placeholder.com/400?text=iPhone+15+Pro+Max'
            ],
            [
                'name' => 'iPhone 14 Pro',
                'description' => 'Cámara de 48MP y Dynamic Island.',
                'price' => 999990,
                'stock' => 10,
                'category_slug' => 'iphone',
                'image' => 'https://via.placeholder.com/400?text=iPhone+14+Pro'
            ],
            [
                'name' => 'MacBook Pro 16" M3 Max',
                'description' => 'Potencia bestial para creadores.',
                'price' => 3499990,
                'stock' => 5,
                'category_slug' => 'macbook',
                'image' => 'https://via.placeholder.com/400?text=MacBook+Pro+16'
            ],
            [
                'name' => 'MacBook Air 15" M2',
                'description' => 'Pantalla Liquid Retina expansiva.',
                'price' => 1499990,
                'stock' => 20,
                'category_slug' => 'macbook',
                'image' => 'https://via.placeholder.com/400?text=MacBook+Air+15'
            ],
            [
                'name' => 'AirPods Pro 2 Plus',
                'description' => 'Cancelación activa de ruido al doble.',
                'price' => 249990,
                'stock' => 30,
                'category_slug' => 'accesorios',
                'image' => 'https://via.placeholder.com/400?text=AirPods+Pro+2'
            ],
            [
                'name' => 'MagSafe Charger Pro',
                'description' => 'Carga inalámbrica rápida y sencilla.',
                'price' => 49990,
                'stock' => 50,
                'category_slug' => 'accesorios',
                'image' => 'https://via.placeholder.com/400?text=MagSafe+Charger'
            ],
        ];

        foreach ($productosInfo as $prodData) {
            $cat = $categorias[$prodData['category_slug']];
            $slug = Str::slug($prodData['name']);

            $product = Product::firstOrCreate(
                ['slug' => $slug],
                [
                    'category_id' => $cat->id,
                    'name' => $prodData['name'],
                    'description' => $prodData['description'],
                    'price' => $prodData['price'],
                    'stock' => $prodData['stock'],
                    'is_active' => 'true',
                ]

            );

            \App\Models\ProductImage::firstOrCreate(
                ['product_id' => $product->id, 'is_primary' => 'true'],
                ['image_url' => $prodData['image']]
            );
        }
    }
}