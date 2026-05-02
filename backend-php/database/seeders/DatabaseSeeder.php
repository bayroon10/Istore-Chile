<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // -------------------------------------------------------
        // 1. USUARIOS
        // -------------------------------------------------------
        $admin = User::firstOrCreate(
            ['email' => 'admin@labstock.com'],
            [
                'name'     => 'LabStock Admin',
                'password' => Hash::make('password123'),
                'role'     => 'admin',
            ]
        );

        $customer = User::firstOrCreate(
            ['email' => 'demo@labstock.com'],
            [
                'name'     => 'María González',
                'password' => Hash::make('password123'),
                'role'     => 'customer',
                'phone'    => '+56912345678',
            ]
        );

        // -------------------------------------------------------
        // 2. CATEGORÍAS
        // -------------------------------------------------------
        $cats = [];

        $catData = [
            ['name' => 'Laptops',       'slug' => 'laptops',       'icon' => '💻'],
            ['name' => 'Componentes',   'slug' => 'componentes',   'icon' => '🔧'],
            ['name' => 'Periféricos',   'slug' => 'perifericos',   'icon' => '🖱️'],
            ['name' => 'Audio',         'slug' => 'audio',         'icon' => '🎧'],
        ];

        foreach ($catData as $c) {
            $cats[$c['slug']] = Category::firstOrCreate(
                ['slug' => $c['slug']],
                ['name' => $c['name'], 'icon' => $c['icon']]
            );
        }

        // -------------------------------------------------------
        // 3. CATÁLOGO DE PRODUCTOS (48 productos)
        // Picsum: https://picsum.photos/seed/{palabra}/400/300
        // Seeds distintos = imágenes distintas y estables.
        // -------------------------------------------------------
        $products = [

            // LAPTOPS (12 productos)
            ['name' => 'MacBook Pro 16" M3 Max',       'cat' => 'laptops',     'price' => 3499990, 'compare' => 3799990, 'stock' => 8,  'featured' => true,  'seed' => 'macbook1'],
            ['name' => 'MacBook Air 15" M2',            'cat' => 'laptops',     'price' => 1499990, 'compare' => 1649990, 'stock' => 20, 'featured' => true,  'seed' => 'macbook2'],
            ['name' => 'Dell XPS 15 OLED',              'cat' => 'laptops',     'price' => 1899990, 'compare' => null,    'stock' => 5,  'featured' => false, 'seed' => 'dell1'],
            ['name' => 'ASUS ROG Zephyrus G16',        'cat' => 'laptops',     'price' => 2199990, 'compare' => 2399990, 'stock' => 7,  'featured' => true,  'seed' => 'asus1'],
            ['name' => 'Lenovo ThinkPad X1 Carbon',    'cat' => 'laptops',     'price' => 1799990, 'compare' => null,    'stock' => 12, 'featured' => false, 'seed' => 'lenovo1'],
            ['name' => 'HP Spectre x360 14"',           'cat' => 'laptops',     'price' => 1599990, 'compare' => 1749990, 'stock' => 9,  'featured' => false, 'seed' => 'hp1'],
            ['name' => 'Microsoft Surface Laptop 5',    'cat' => 'laptops',     'price' => 1399990, 'compare' => null,    'stock' => 6,  'featured' => false, 'seed' => 'surface1'],
            ['name' => 'Razer Blade 15 Advanced',       'cat' => 'laptops',     'price' => 2599990, 'compare' => 2799990, 'stock' => 4,  'featured' => false, 'seed' => 'razer1'],
            ['name' => 'Acer Swift X 16 OLED',          'cat' => 'laptops',     'price' => 1199990, 'compare' => null,    'stock' => 15, 'featured' => false, 'seed' => 'acer1'],
            ['name' => 'Samsung Galaxy Book3 Ultra',    'cat' => 'laptops',     'price' => 1999990, 'compare' => 2199990, 'stock' => 8,  'featured' => false, 'seed' => 'samsung1'],
            ['name' => 'LG Gram 17" 2024',              'cat' => 'laptops',     'price' => 1349990, 'compare' => null,    'stock' => 10, 'featured' => false, 'seed' => 'lg1'],
            ['name' => 'Framework Laptop 16 AMD',       'cat' => 'laptops',     'price' => 1249990, 'compare' => null,    'stock' => 3,  'featured' => false, 'seed' => 'framework1'],

            // COMPONENTES (12 productos)
            ['name' => 'NVIDIA GeForce RTX 4090',       'cat' => 'componentes', 'price' => 1899990, 'compare' => 2199990, 'stock' => 5,  'featured' => true,  'seed' => 'nvidia1'],
            ['name' => 'AMD Ryzen 9 7950X',             'cat' => 'componentes', 'price' => 499990,  'compare' => null,    'stock' => 12, 'featured' => true,  'seed' => 'amd1'],
            ['name' => 'Samsung 990 Pro 2TB NVMe',      'cat' => 'componentes', 'price' => 149990,  'compare' => 179990,  'stock' => 30, 'featured' => false, 'seed' => 'samsung2'],
            ['name' => 'Corsair Vengeance DDR5 64GB',   'cat' => 'componentes', 'price' => 219990,  'compare' => null,    'stock' => 18, 'featured' => false, 'seed' => 'corsair1'],
            ['name' => 'ASUS ROG Maximus Z790',         'cat' => 'componentes', 'price' => 579990,  'compare' => 649990,  'stock' => 7,  'featured' => false, 'seed' => 'asus2'],
            ['name' => 'Seasonic PRIME TX-1000W',       'cat' => 'componentes', 'price' => 239990,  'compare' => null,    'stock' => 10, 'featured' => false, 'seed' => 'seasonic1'],
            ['name' => 'AMD Radeon RX 7900 XTX',        'cat' => 'componentes', 'price' => 849990,  'compare' => 949990,  'stock' => 6,  'featured' => false, 'seed' => 'amd2'],
            ['name' => 'WD Black SN850X 4TB',           'cat' => 'componentes', 'price' => 279990,  'compare' => null,    'stock' => 20, 'featured' => false, 'seed' => 'wd1'],
            ['name' => 'Intel Core i9-14900K',          'cat' => 'componentes', 'price' => 449990,  'compare' => 499990,  'stock' => 14, 'featured' => false, 'seed' => 'intel1'],
            ['name' => 'Noctua NH-D15 Chromax',         'cat' => 'componentes', 'price' => 99990,   'compare' => null,    'stock' => 25, 'featured' => false, 'seed' => 'noctua1'],
            ['name' => 'NVIDIA GeForce RTX 4070 Ti',    'cat' => 'componentes', 'price' => 699990,  'compare' => 779990,  'stock' => 9,  'featured' => false, 'seed' => 'nvidia2'],
            ['name' => 'G.Skill Trident Z5 RGB 32GB',   'cat' => 'componentes', 'price' => 129990,  'compare' => null,    'stock' => 22, 'featured' => false, 'seed' => 'gskill1'],

            // PERIFÉRICOS (12 productos)
            ['name' => 'LG UltraGear 27" 4K 144Hz',    'cat' => 'perifericos', 'price' => 649990,  'compare' => 749990,  'stock' => 12, 'featured' => true,  'seed' => 'lg2'],
            ['name' => 'Logitech MX Master 3S',         'cat' => 'perifericos', 'price' => 89990,   'compare' => null,    'stock' => 40, 'featured' => true,  'seed' => 'logi1'],
            ['name' => 'Keychron Q3 Pro QMK',           'cat' => 'perifericos', 'price' => 139990,  'compare' => 159990,  'stock' => 15, 'featured' => false, 'seed' => 'keyc1'],
            ['name' => 'Samsung Odyssey G9 49" Curved', 'cat' => 'perifericos', 'price' => 1299990, 'compare' => 1499990, 'stock' => 4,  'featured' => false, 'seed' => 'samsung3'],
            ['name' => 'Razer DeathAdder V3 Pro',       'cat' => 'perifericos', 'price' => 119990,  'compare' => null,    'stock' => 28, 'featured' => false, 'seed' => 'razer2'],
            ['name' => 'Elgato Stream Deck XL',         'cat' => 'perifericos', 'price' => 199990,  'compare' => 229990,  'stock' => 10, 'featured' => false, 'seed' => 'elgato1'],
            ['name' => 'BenQ PD3220U 32" 4K',          'cat' => 'perifericos', 'price' => 899990,  'compare' => null,    'stock' => 6,  'featured' => false, 'seed' => 'benq1'],
            ['name' => 'Logitech G Pro X Superlight 2', 'cat' => 'perifericos', 'price' => 109990,  'compare' => 124990,  'stock' => 20, 'featured' => false, 'seed' => 'logi2'],
            ['name' => 'Das Keyboard 6 Pro',            'cat' => 'perifericos', 'price' => 189990,  'compare' => null,    'stock' => 8,  'featured' => false, 'seed' => 'das1'],
            ['name' => 'Elgato Facecam Pro 4K',         'cat' => 'perifericos', 'price' => 279990,  'compare' => 319990,  'stock' => 12, 'featured' => false, 'seed' => 'elgato2'],
            ['name' => 'Wacom Intuos Pro Large',        'cat' => 'perifericos', 'price' => 399990,  'compare' => null,    'stock' => 7,  'featured' => false, 'seed' => 'wacom1'],
            ['name' => 'Ergotron LX Dual Monitor Arm',  'cat' => 'perifericos', 'price' => 149990,  'compare' => 174990,  'stock' => 18, 'featured' => false, 'seed' => 'ergotron1'],

            // AUDIO (12 productos)
            ['name' => 'Sony WH-1000XM5',              'cat' => 'audio',       'price' => 299990,  'compare' => 349990,  'stock' => 25, 'featured' => true,  'seed' => 'sony1'],
            ['name' => 'Apple AirPods Pro 2',           'cat' => 'audio',       'price' => 249990,  'compare' => null,    'stock' => 35, 'featured' => true,  'seed' => 'apple1'],
            ['name' => 'Sennheiser Momentum 4',        'cat' => 'audio',       'price' => 349990,  'compare' => 399990,  'stock' => 10, 'featured' => false, 'seed' => 'senn1'],
            ['name' => 'Shure MV7+ USB/XLR Mic',       'cat' => 'audio',       'price' => 199990,  'compare' => null,    'stock' => 15, 'featured' => false, 'seed' => 'shure1'],
            ['name' => 'Beyerdynamic DT 900 Pro X',     'cat' => 'audio',       'price' => 279990,  'compare' => 319990,  'stock' => 8,  'featured' => false, 'seed' => 'beyer1'],
            ['name' => 'Focusrite Scarlett 2i2 4th Gen','cat' => 'audio',       'price' => 149990,  'compare' => null,    'stock' => 20, 'featured' => false, 'seed' => 'focus1'],
            ['name' => 'Bose QuietComfort Ultra',       'cat' => 'audio',       'price' => 399990,  'compare' => 449990,  'stock' => 12, 'featured' => false, 'seed' => 'bose1'],
            ['name' => 'Audio-Technica ATH-M50xBT2',   'cat' => 'audio',       'price' => 179990,  'compare' => null,    'stock' => 18, 'featured' => false, 'seed' => 'at1'],
            ['name' => 'Rode Wireless GO II',           'cat' => 'audio',       'price' => 339990,  'compare' => 389990,  'stock' => 6,  'featured' => false, 'seed' => 'rode1'],
            ['name' => 'Samsung Galaxy Buds3 Pro',      'cat' => 'audio',       'price' => 199990,  'compare' => 219990,  'stock' => 22, 'featured' => false, 'seed' => 'samsung4'],
            ['name' => 'Logitech G Pro X 2 Headset',   'cat' => 'audio',       'price' => 229990,  'compare' => null,    'stock' => 14, 'featured' => false, 'seed' => 'logi3'],
            ['name' => 'Elgato Wave:3 Plus',            'cat' => 'audio',       'price' => 129990,  'compare' => 149990,  'stock' => 25, 'featured' => false, 'seed' => 'elgato3'],
        ];

        $createdProducts = [];

        foreach ($products as $p) {
            $slug = Str::slug($p['name']) . '-' . $p['seed'];

            $product = Product::firstOrCreate(
                ['slug' => $slug],
                [
                    'category_id'   => $cats[$p['cat']]->id,
                    'name'          => $p['name'],
                    'description'   => $this->description($p['name']),
                    'price'         => $p['price'],
                    'compare_price' => $p['compare'],
                    'stock'         => $p['stock'],
                    'sku'           => strtoupper('LSP-' . $p['seed']),
                    'is_active'     => true,
                    'is_featured'   => $p['featured'],
                    'compatibility' => null,
                    'version'       => 1,
                ]
            );

            // Asignar imagen primaria con Picsum (URL estable por seed)
            ProductImage::firstOrCreate(
                ['product_id' => $product->id, 'is_primary' => true],
                [
                    'image_url'  => "https://picsum.photos/seed/{$p['seed']}/600/400",
                    'sort_order' => 0,
                ]
            );

            $createdProducts[$p['seed']] = $product;
        }

        // -------------------------------------------------------
        // 4. DIRECCIÓN DEL CLIENTE DEMO
        // -------------------------------------------------------
        Address::firstOrCreate(
            ['user_id' => $customer->id, 'label' => 'Casa'],
            [
                'full_name'  => 'María González',
                'phone'      => '+56912345678',
                'street'     => 'Av. Providencia 1234, Depto 5B',
                'city'       => 'Santiago',
                'region'     => 'Región Metropolitana',
                'is_default' => true,
            ]
        );

        // -------------------------------------------------------
        // 5. CARRITO ACTIVO DEL CLIENTE DEMO
        // -------------------------------------------------------
        $demoProduct = $createdProducts['sony1'];
        $cart = Cart::firstOrCreate(['user_id' => $customer->id]);
        CartItem::firstOrCreate(
            ['cart_id' => $cart->id, 'product_id' => $demoProduct->id],
            ['quantity' => 1]
        );

        // -------------------------------------------------------
        // 6. ORDEN DEMO - ENTREGADA
        // -------------------------------------------------------
        $p1 = $createdProducts['macbook1'];
        $order = Order::firstOrCreate(
            ['order_number' => 'LST-20260415-0001'],
            [
                'user_id'         => $customer->id,
                'shipping_name'   => 'María González',
                'shipping_phone'  => '+56912345678',
                'shipping_street' => 'Av. Providencia 1234, Depto 5B',
                'shipping_city'   => 'Santiago',
                'shipping_region' => 'Región Metropolitana',
                'shipping_method' => 'Starken',
                'status'          => 'delivered',
                'subtotal'        => $p1->price,
                'shipping_cost'   => 3990,
                'discount'        => 0,
                'total'           => $p1->price + 3990,
                'payment_method'  => 'stripe',
                'stripe_payment_id' => 'pi_demo_delivered_001',
                'paid_at'         => now()->subDays(20),
                'shipped_at'      => now()->subDays(18),
                'delivered_at'    => now()->subDays(15),
            ]
        );

        OrderItem::firstOrCreate(
            ['order_id' => $order->id, 'product_id' => $p1->id],
            [
                'product_name'       => $p1->name,
                'product_price'      => $p1->price,
                'product_image'      => "https://picsum.photos/seed/macbook1/600/400",
                'quantity'           => 1,
                'subtotal'           => $p1->price,
                'fulfillment_status' => 'delivered',
            ]
        );

        // -------------------------------------------------------
        // 7. ORDEN DEMO - PAGADA / EN PROCESO
        // -------------------------------------------------------
        $p2 = $createdProducts['nvidia1'];
        $order2 = Order::firstOrCreate(
            ['order_number' => 'LST-20260430-0002'],
            [
                'user_id'           => $customer->id,
                'shipping_name'     => 'María González',
                'shipping_phone'    => '+56912345678',
                'shipping_street'   => 'Av. Providencia 1234, Depto 5B',
                'shipping_city'     => 'Santiago',
                'shipping_region'   => 'Región Metropolitana',
                'shipping_method'   => 'Chilexpress',
                'status'            => 'processing',
                'subtotal'          => $p2->price,
                'shipping_cost'     => 4500,
                'discount'          => 0,
                'total'             => $p2->price + 4500,
                'payment_method'    => 'stripe',
                'stripe_payment_id' => 'pi_demo_processing_002',
                'paid_at'           => now()->subDays(2),
            ]
        );

        OrderItem::firstOrCreate(
            ['order_id' => $order2->id, 'product_id' => $p2->id],
            [
                'product_name'       => $p2->name,
                'product_price'      => $p2->price,
                'product_image'      => "https://picsum.photos/seed/nvidia1/600/400",
                'quantity'           => 1,
                'subtotal'           => $p2->price,
                'fulfillment_status' => 'pending',
            ]
        );

        // -------------------------------------------------------
        // 8. RESEÑA EN EL PRODUCTO ENTREGADO
        // -------------------------------------------------------
        Review::firstOrCreate(
            ['user_id' => $customer->id, 'product_id' => $p1->id],
            [
                'order_id' => $order->id,
                'rating'   => 5,
                'comment'  => 'Rendimiento increíble. El mejor equipo que he tenido, totalmente recomendado para trabajo profesional.',
            ]
        );

        if (method_exists($p1, 'recalculateRating')) {
            $p1->recalculateRating();
        }
    }

    // -------------------------------------------------------
    // Helper: generar descripción realista por nombre de producto
    // -------------------------------------------------------
    private function description(string $name): string
    {
        $descriptions = [
            'MacBook Pro 16" M3 Max'        => 'La laptop más poderosa de Apple con chip M3 Max, pantalla Liquid Retina XDR de 16", hasta 128GB de RAM unificada y 40 núcleos de GPU. Ideal para workflows de video, 3D y machine learning.',
            'MacBook Air 15" M2'            => 'La laptop más delgada con pantalla Liquid Retina de 15.3", chip M2, hasta 24GB de RAM y 18 horas de batería. Diseño sin ventilador, silenciosa y con potencia para cualquier tarea.',
            'Dell XPS 15 OLED'             => 'Pantalla OLED InfinityEdge de 15.6" con 100% DCI-P3, procesador Intel Core i9 de 13ª generación y GPU NVIDIA RTX 4070. La élite de las laptops para creadores.',
            'ASUS ROG Zephyrus G16'        => 'La laptop gaming definitiva con panel OLED 240Hz, AMD Ryzen 9 7945HX, GPU RTX 4090 y diseño de 16" ultradelgado. Performance sin compromisos.',
            'NVIDIA GeForce RTX 4090'      => 'La GPU más potente del mercado con arquitectura Ada Lovelace, 24GB GDDR6X y soporte nativo para DLSS 3. Renderizado, gaming 8K y workloads de IA de próxima generación.',
            'AMD Ryzen 9 7950X'            => 'Procesador de 16 núcleos y 32 hilos con arquitectura Zen 4, boost de hasta 5.7 GHz y fabricado en proceso de 5nm. El rey del multithreading para workstations.',
            'Sony WH-1000XM5'             => 'Los mejores auriculares de cancelación de ruido del mercado con 8 micrófonos, controlador de 30mm, 30 horas de batería y sonido Hi-Res Audio. Compatibles con Multipoint.',
            'Apple AirPods Pro 2'          => 'Cancelación activa de ruido de clase mundial, audio espacial personalizado, chip H2, resistencia al agua IPX4 y hasta 30 horas de batería con el estuche MagSafe.',
            'LG UltraGear 27" 4K 144Hz'   => 'Monitor gaming de 27" con panel Nano IPS, resolución 4K, 144Hz, tiempo de respuesta de 1ms y compatibilidad con NVIDIA G-Sync y AMD FreeSync Premium.',
            'Logitech MX Master 3S'        => 'El mouse premium para productividad con scroll MagSpeed silencioso, sensor de 8000 DPI, 7 botones programables y conexión a hasta 3 dispositivos mediante Bolt o Bluetooth.',
        ];

        return $descriptions[$name]
            ?? "Producto de alta gama con las mejores especificaciones del mercado. {$name} ofrece rendimiento excepcional, materiales premium y garantía de fabricante. Ideal para usuarios profesionales y entusiastas de la tecnología.";
    }
}