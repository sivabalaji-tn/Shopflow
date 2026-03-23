<?php
// manifest.php — Dynamic PWA Web App Manifest
// Served as JSON but themed per-shop

require_once 'config/db.php';

$slug = $_GET['shop'] ?? null;
$shop = null;

if ($slug) {
    $stmt = $conn->prepare("SELECT * FROM shops WHERE slug = ? AND is_active = 1");
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $shop = $stmt->get_result()->fetch_assoc();
}

$name       = $shop ? $shop['name']           : 'TamizhMart';
$short_name = $shop ? mb_substr($shop['name'], 0, 12) : 'TamizhMart';
$color      = $shop ? ($shop['theme_primary'] ?? '#c8a97e') : '#c8a97e';
$bg_color   = $shop ? ($shop['theme_bg']      ?? '#faf7f2') : '#faf7f2';
$start_url  = $shop ? "./shop/index.php?shop={$shop['slug']}" : './index.php';

header('Content-Type: application/manifest+json');
header('Cache-Control: public, max-age=3600');

$manifest = [
    "name"             => $name,
    "short_name"       => $short_name,
    "description"      => $shop['description'] ?? "Shop online at $name",
    "start_url"        => $start_url,
    "display"          => "standalone",
    "orientation"      => "portrait-primary",
    "background_color" => $bg_color,
    "theme_color"      => $color,
    "lang"             => "en",
    "scope"            => "./",
    "categories"       => ["shopping", "food"],
    "icons"            => [
        [
            "src"     => "assets/icons/icon-72.png",
            "sizes"   => "72x72",
            "type"    => "image/png",
            "purpose" => "any maskable"
        ],
        [
            "src"     => "assets/icons/icon-96.png",
            "sizes"   => "96x96",
            "type"    => "image/png",
            "purpose" => "any maskable"
        ],
        [
            "src"     => "assets/icons/icon-128.png",
            "sizes"   => "128x128",
            "type"    => "image/png",
            "purpose" => "any maskable"
        ],
        [
            "src"     => "assets/icons/icon-192.png",
            "sizes"   => "192x192",
            "type"    => "image/png",
            "purpose" => "any maskable"
        ],
        [
            "src"     => "assets/icons/icon-512.png",
            "sizes"   => "512x512",
            "type"    => "image/png",
            "purpose" => "any maskable"
        ]
    ],
    "shortcuts" => $shop ? [
        [
            "name"       => "Browse Products",
            "short_name" => "Products",
            "url"        => "./shop/products.php?shop={$shop['slug']}",
            "icons"      => [["src" => "assets/icons/icon-96.png", "sizes" => "96x96"]]
        ],
        [
            "name"       => "My Orders",
            "short_name" => "Orders",
            "url"        => "./shop/orders.php?shop={$shop['slug']}",
            "icons"      => [["src" => "assets/icons/icon-96.png", "sizes" => "96x96"]]
        ],
        [
            "name"       => "Cart",
            "short_name" => "Cart",
            "url"        => "./shop/cart.php?shop={$shop['slug']}",
            "icons"      => [["src" => "assets/icons/icon-96.png", "sizes" => "96x96"]]
        ]
    ] : [],
    "screenshots" => [],
    "prefer_related_applications" => false
];

echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>