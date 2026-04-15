<?php

// Datos obtenidos del .env
$host = 'ep-gentle-silence-amby38xz.us-east-1.aws.neon.tech';
$port = '5432';
$dbname = 'neondb';
$user = 'neondb_owner';
$password = 'npg_3FqdW8CDyefu';
$sslmode = 'require';

// 1. Probar conexión básica
echo "=========================================\n";
echo "1. Probando PDO directo...\n";
$dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=$sslmode";

try {
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "[ÉXITO] Conexión PDO establecida correctamente.\n";
} catch (PDOException $e) {
    echo "[FALLO] Error en PDO directo: " . $e->getMessage() . "\n";
    
    // Intento 2: Añadir endpoint ID al nombre de usuario (workaround antiguo de Neon)
    echo "\n2. Probando workaround de endpoint ID en el usuario...\n";
    $endpointId = explode('.', $host)[0]; // ep-gentle-silence-amby38xz
    $workaroundUser = $user . " endpoint=" . $endpointId; // Formato username: user endpoint=endpoint_id (A veces Neon requiere string especial si SNI falla)
    
    // El formato comúnmente aceptado si SNI falla es pasar options en el DSN
    $dsnWithOptions = $dsn . ";options='endpoint=$endpointId'";
    
    try {
         $pdo = new PDO($dsnWithOptions, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        echo "[ÉXITO] Conexión PDO establecida enviando options en DSN.\n";
    } catch (PDOException $e) {
        echo "[FALLO] Error con options endpoint: " . $e->getMessage() . "\n";
    }
}
