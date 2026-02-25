<?php
session_start();
require '../config/db.php';
header('Content-Type: application/json');

$slug = $_POST['shop'] ?? $_SESSION['current_shop_slug'] ?? null;
if (!$slug) { echo json_encode(['success'=>false,'message'=>'Shop not found']); exit; }

$stmt = $conn->prepare("SELECT id FROM shops WHERE slug=? AND is_active=1");
$stmt->bind_param("s", $slug);
$stmt->execute();
$shop = $stmt->get_result()->fetch_assoc();
if (!$shop) { echo json_encode(['success'=>false,'message'=>'Shop not found']); exit; }
$shop_id = $shop['id'];

// Check login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success'=>false,'message'=>'Please sign in to add items to cart','redirect'=>'../auth/login.php?shop='.$slug]);
    exit;
}
$user_id = $_SESSION['user_id'];
$action  = $_POST['action'] ?? '';

function getCartCount($conn, $user_id, $shop_id) {
    $r = $conn->query("SELECT COALESCE(SUM(quantity),0) as c FROM cart WHERE user_id=$user_id AND shop_id=$shop_id");
    return (int)$r->fetch_assoc()['c'];
}

if ($action === 'add') {
    $product_id = (int)$_POST['product_id'];
    $qty        = max(1, (int)($_POST['quantity'] ?? 1));

    // Validate product belongs to this shop and has stock
    $ps = $conn->prepare("SELECT * FROM products WHERE id=? AND shop_id=? AND is_active=1");
    $ps->bind_param("ii", $product_id, $shop_id);
    $ps->execute();
    $product = $ps->get_result()->fetch_assoc();

    if (!$product) { echo json_encode(['success'=>false,'message'=>'Product not found']); exit; }
    if ($product['stock'] <= 0) { echo json_encode(['success'=>false,'message'=>'This product is out of stock']); exit; }

    // Check existing cart item
    $existing = $conn->query("SELECT id, quantity FROM cart WHERE user_id=$user_id AND shop_id=$shop_id AND product_id=$product_id")->fetch_assoc();
    $new_qty  = $existing ? ($existing['quantity'] + $qty) : $qty;
    if ($new_qty > $product['stock']) $new_qty = $product['stock'];

    if ($existing) {
        $conn->query("UPDATE cart SET quantity=$new_qty WHERE id={$existing['id']}");
    } else {
        $conn->query("INSERT INTO cart (user_id, shop_id, product_id, quantity) VALUES ($user_id, $shop_id, $product_id, $new_qty)");
    }

    echo json_encode(['success'=>true,'message'=>htmlspecialchars($product['name']).' added to cart!','cart_count'=>getCartCount($conn,$user_id,$shop_id)]);

} elseif ($action === 'update') {
    $cart_id = (int)$_POST['cart_id'];
    $qty     = (int)$_POST['quantity'];

    if ($qty <= 0) {
        $conn->query("DELETE FROM cart WHERE id=$cart_id AND user_id=$user_id");
        echo json_encode(['success'=>true,'message'=>'Item removed','cart_count'=>getCartCount($conn,$user_id,$shop_id)]);
    } else {
        // Validate stock
        $p = $conn->query("SELECT p.stock FROM cart c JOIN products p ON c.product_id=p.id WHERE c.id=$cart_id AND c.user_id=$user_id")->fetch_assoc();
        if ($p && $qty > $p['stock']) $qty = $p['stock'];
        $conn->query("UPDATE cart SET quantity=$qty WHERE id=$cart_id AND user_id=$user_id");
        echo json_encode(['success'=>true,'message'=>'Cart updated','cart_count'=>getCartCount($conn,$user_id,$shop_id)]);
    }

} elseif ($action === 'remove') {
    $cart_id = (int)$_POST['cart_id'];
    $conn->query("DELETE FROM cart WHERE id=$cart_id AND user_id=$user_id");
    echo json_encode(['success'=>true,'message'=>'Item removed','cart_count'=>getCartCount($conn,$user_id,$shop_id)]);

} elseif ($action === 'clear') {
    $conn->query("DELETE FROM cart WHERE user_id=$user_id AND shop_id=$shop_id");
    echo json_encode(['success'=>true,'message'=>'Cart cleared','cart_count'=>0]);

} else {
    echo json_encode(['success'=>false,'message'=>'Invalid action']);
}
?>
