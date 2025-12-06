<?php
// Database connection
$host = 'localhost';
$dbname = 'oussama';
$username = 'root';
$password = 'admine';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'utf8mb4'");
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Start session
session_start();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Newsletter subscription
    if (isset($_POST['newsletter_email'])) {
        $email = filter_var($_POST['newsletter_email'], FILTER_SANITIZE_EMAIL);
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response = [
                'success' => false,
                'message' => isset($_POST['lang']) && $_POST['lang'] === 'ar' ? 
                    'البريد الإلكتروني غير صالح' : 
                    'Adresse email invalide'
            ];
            echo json_encode($response);
            exit;
        }
        
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT subscription_id FROM newsletter_subscriptions WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                // Email exists, update subscription
                $stmt = $pdo->prepare("UPDATE newsletter_subscriptions SET is_active = 1, unsubscribed_at = NULL WHERE email = ?");
                $stmt->execute([$email]);
            } else {
                // Insert new subscription
                $stmt = $pdo->prepare("INSERT INTO newsletter_subscriptions (email) VALUES (?)");
                $stmt->execute([$email]);
            }
            
            $response = [
                'success' => true,
                'message' => isset($_POST['lang']) && $_POST['lang'] === 'ar' ? 
                    'شكرًا لك على الاشتراك! سيصلك آخر أخبارنا قريبًا.' : 
                    'Merci pour votre inscription ! Vous recevrez bientôt nos dernières nouveautés.'
            ];
            echo json_encode($response);
            exit;
        } catch (PDOException $e) {
            $response = [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
            echo json_encode($response);
            exit;
        }
    }
    
    // Add to cart
    if (isset($_POST['add_to_cart'])) {
        $product_id = (int)$_POST['product_id'];
        
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id]++;
        } else {
            $_SESSION['cart'][$product_id] = 1;
        }
        
        $response = [
            'success' => true,
            'count' => array_sum($_SESSION['cart'])
        ];
        echo json_encode($response);
        exit;
    }
    
    // Update cart quantity
    if (isset($_POST['update_cart'])) {
        $product_id = (int)$_POST['product_id'];
        $quantity = (int)$_POST['quantity'];
        
        if ($quantity <= 0) {
            unset($_SESSION['cart'][$product_id]);
        } else {
            $_SESSION['cart'][$product_id] = $quantity;
        }
        
        $response = [
            'success' => true,
            'count' => array_sum($_SESSION['cart'])
        ];
        echo json_encode($response);
        exit;
    }
    
    // Process order
    if (isset($_POST['process_order'])) {
        if (empty($_SESSION['cart'])) {
            $response = [
                'success' => false,
                'message' => 'Your cart is empty'
            ];
            echo json_encode($response);
            exit;
        }
        
        try {
            $pdo->beginTransaction();
            
            // Generate order number
            $order_number = 'CMD-' . date('YmdHis') . '-' . mt_rand(1000, 9999);
            
            // Calculate total amount
            $total_amount = 0;
            $product_ids = array_keys($_SESSION['cart']);
            $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
            $stmt = $pdo->prepare("SELECT product_id, price FROM products WHERE product_id IN ($placeholders)");
            $stmt->execute($product_ids);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $product_prices = [];
            foreach ($products as $product) {
                $product_prices[$product['product_id']] = $product['price'];
            }
            
            foreach ($_SESSION['cart'] as $product_id => $quantity) {
                if (isset($product_prices[$product_id])) {
                    $total_amount += $product_prices[$product_id] * $quantity;
                }
            }
            
            // Insert order
            $stmt = $pdo->prepare("INSERT INTO orders (order_number, total_amount, status, payment_method, payment_status, shipping_address) VALUES (?, ?, 'pending', ?, 'pending', ?)");
            
            $stmt->execute([
                $order_number,
                $total_amount,
                $_POST['payment_method'],
                $_POST['shipping_address']
            ]);
            
            $order_id = $pdo->lastInsertId();
            
            // Insert order items
            $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)");
            
            foreach ($_SESSION['cart'] as $product_id => $quantity) {
                if (isset($product_prices[$product_id])) {
                    $unit_price = $product_prices[$product_id];
                    $total_price = $unit_price * $quantity;
                    $stmt->execute([$order_id, $product_id, $quantity, $unit_price, $total_price]);
                    
                    // Update product stock
                    $update_stmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ?");
                    $update_stmt->execute([$quantity, $product_id]);
                }
            }
            
            $pdo->commit();
            
            // Clear cart
            unset($_SESSION['cart']);
            
            $response = [
                'success' => true,
                'message' => 'Order placed successfully!',
                'order_number' => $order_number
            ];
            echo json_encode($response);
            exit;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $response = [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
            echo json_encode($response);
            exit;
        }
    }
}

// Handle search request
if (isset($_GET['search'])) {
    $search_term = '%' . $_GET['search'] . '%';
    
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, c.name_fr as category_name 
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.category_id
            WHERE p.stock_quantity > 0 
            AND (p.name_fr LIKE ? OR p.name_ar LIKE ? OR p.description_fr LIKE ?)
            ORDER BY p.is_bestseller DESC, p.is_featured DESC, p.is_new DESC
            LIMIT 20
        ");
        $stmt->execute([$search_term, $search_term, $search_term]);
        $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'results' => $search_results]);
        exit;
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// Handle cart display request
if (isset($_GET['get_cart'])) {
    displayCart();
    exit;
}

function displayCart() {
    if (empty($_SESSION['cart'])) {
        echo '<div class="empty-cart" data-fr="Votre panier est vide" data-ar="سلة التسوق فارغة">Votre panier est vide</div>';
        return;
    }

    global $pdo;
    
    $product_ids = array_keys($_SESSION['cart']);
    $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
    $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id IN ($placeholders)");
    $stmt->execute($product_ids);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $product_map = [];
    foreach ($products as $product) {
        $product_map[$product['product_id']] = $product;
    }

    $cart_total = 0; // Initialize total
    
    foreach ($_SESSION['cart'] as $product_id => $quantity) {
        if (isset($product_map[$product_id])) {
            $product = $product_map[$product_id];
            $total = $product['price'] * $quantity;
            $cart_total += $total; // Add to cart total
            
            echo '<div class="cart-item" data-product-id="' . $product_id . '">';
            echo '<div class="cart-item-info">';
            echo '<img src="' . htmlspecialchars($product['image_url'] ?? 'https://via.placeholder.com/60x60') . '" alt="' . htmlspecialchars($product['name_fr']) . '" class="cart-item-image">';
            echo '<div class="cart-item-details">';
            echo '<h4>' . htmlspecialchars($product['name_fr']) . '</h4>';
            echo '<div class="cart-item-price">' . number_format($product['price'], 2) . ' DA</div>';
            echo '</div>';
            echo '</div>';
            echo '<div class="cart-item-quantity">';
            echo '<button class="quantity-btn" onclick="updateQuantity(' . $product_id . ', -1)">-</button>';
            echo '<span>' . $quantity . '</span>';
            echo '<button class="quantity-btn" onclick="updateQuantity(' . $product_id . ', 1)">+</button>';
            echo '</div>';
            echo '<div class="cart-item-total">' . number_format($total, 2) . ' DA</div>';
            echo '<button class="remove-btn" onclick="removeFromCart(' . $product_id . ')" data-fr="×" data-ar="×">×</button>';
            echo '</div>';
        }
    }
    
    // Store the total in a data attribute for JavaScript to access
    echo '<div id="cart-total-amount" data-total="' . $cart_total . '" style="display:none;"></div>';
}

// Fetch categories from database
$categories = [];
try {
    $stmt = $pdo->query("
        SELECT * FROM categories 
        WHERE is_active = 1 AND parent_id IS NULL
        ORDER BY category_id
    ");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categories = [];
}

// Fetch products from database
$products = [];
try {
    $stmt = $pdo->query("
        SELECT p.*, c.name_fr as category_name 
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.category_id
        WHERE p.stock_quantity > 0
        ORDER BY p.is_bestseller DESC, p.is_featured DESC, p.is_new DESC
        LIMIT 6
    ");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $products = [];
}

// Get cart count for header
$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cosmetique SIMOU</title>
    <link rel="icon" href="simou.jpg" type="image/jpg" sizes="512x512">
    <link rel="apple-touch-icon" href="simou.jpg">
    <meta name="msapplication-TileImage" content="simou.jpg">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Montserrat:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #e83e8c;
            --secondary: #333333;
            --accent: #fedae6;
            --light: #fedae6;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: var(--light);
            color: var(--secondary);
            overflow-x: hidden;
        }
        
        /* Header with left elements, centered logo, and right nav */
        header {
            background: white;
            box-shadow: 0 5px 30px rgba(0,0,0,0.4);
            padding: 38px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            transform: translateY(0);
            transition: transform 0.3s;
        }
        
        header.hidden {
            transform: translateY(-100%);
        }
        
        /* Left side elements */
        .header-left {
            display: flex;
            align-items: center;
            gap: 25px;
        }
        
        /* Language selector */
        .language-selector {
            display: flex;
        }
        
        .language-btn {
            background: none;
            border: none;
            padding: 5px 10px;
            margin: 0 5px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            border-radius: 3px;
        }
        
        .language-btn.active {
            background: var(--primary);
            color: white;
        }
        
        /* Search container */
        .search-container {
            position: relative;
        }
        
        .search-input {
            width: 0;
            padding: 0;
            border: none;
            border-bottom: 2px solid transparent;
            transition: all 0.3s ease;
            background: transparent;
            color: var(--secondary);
            font-family: 'Montserrat', sans-serif;
        }
        
        .search-input.active {
            width: 150px;
            padding: 5px 10px;
            border-bottom-color: var(--primary);
        }
        
        /* Search Results */
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            max-height: 400px;
            overflow-y: auto;
            z-index: 1001;
            display: none;
        }
        
        .search-results.active {
            display: block;
        }
        
        .search-result-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .search-result-item:hover {
            background: var(--accent);
        }
        
        .search-result-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .search-result-info h4 {
            margin-bottom: 5px;
        }
        
        .search-result-price {
            color: var(--primary);
            font-weight: 600;
        }
        
        .no-results {
            padding: 20px;
            text-align: center;
            color: #666;
            font-style: italic;
        }
        
        /* Icons */
        .header-icons {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .search-btn, .wishlist-btn, .cart-btn {
            background: none;
            border: none;
            color: var(--secondary);
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }
        
        .search-btn:hover, .wishlist-btn:hover, .cart-btn:hover {
            color: var(--primary);
        }
        
        .cart-count, .wishlist-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Centered Logo */
        .logo-container {
            position: absolute;
            left: 45%;
            transform: translateX(-50%);
            animation: fadeInDown 1s both;
        }
        
        .logo-img {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary);
        }
        
        /* Navigation on right */
        nav ul {
            display: flex;
            list-style: none;
            animation: fadeIn 1s 0.5s both;
        }
        
        nav li {
            margin: 0 15px;
        }
        
        nav a {
            text-decoration: none;
            color: var(--secondary);
            font-weight: 600;
            position: relative;
            transition: all 0.3s;
            padding: 5px 0;
            font-size: 16px;
        }
        
        nav a:hover {
            color: var(--primary);
        }
        
        nav a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background-color: var(--primary);
            transition: width 0.3s;
        }
        
        nav a:hover::after {
            width: 100%;
        }
        
        /* Hero Section with Parallax */
        .hero {
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 0 10%;
            margin-top: 100px;
            position: relative;
            overflow: hidden;
        }
        
        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('https://scontent.faae1-2.fna.fbcdn.net/v/t39.30808-6/472669273_122206081142229532_6804704846917848227_n.jpg?_nc_cat=103&ccb=1-7&_nc_sid=86c6b0&_nc_ohc=6nw_AkWbBpYQ7kNvwEJ19-6&_nc_oc=AdkFl5I1AXN39DbDEP1ZWbqFSwC1giHIjvzYhD_uvAF_DsPF9lbkAl2IvCAm3RqQv5o&_nc_zt=23&_nc_ht=scontent.faae1-2.fna&_nc_gid=fEuRXXLqJfTtfVHoEWTu7Q&oh=00_AfnrzU0PrVPMKUk2L36ApGLVrjUt4M7U_135yfH91S5dEg&oe=6935C1EC') center/cover;
            z-index: -1;
            transform: translateZ(-1px) scale(1.2);
            animation: zoomOut 8s infinite alternate;
        }
        
        .hero::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.2);
            z-index: -1;
        }
        
        .hero h2 {
            font-family: 'Playfair Display', serif;
            font-size: 4rem;
            margin-bottom: 20px;
            color: var(--secondary);
            animation: fadeInUp 1s both;
            text-shadow: 1px 1px 3px rgba(255,255,255,0.8);
        }
        
        .hero p {
            font-size: 1.3rem;
            margin-bottom: 40px;
            line-height: 1.6;
            max-width: 700px;
            animation: fadeInUp 1s 0.3s both;
        }
        
        .cta-button {
            background: var(--primary);
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.4s;
            animation: fadeInUp 1s 0.6s both, pulse 2s 2s infinite;
            box-shadow: 0 5px 20px rgba(232, 62, 140, 0.4);
        }
        
        .cta-button:hover {
            background: var(--secondary);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(232, 62, 140, 0.5);
        }
        
        /* View All Products Button */
        .view-all-btn {
            display: block;
            background: var(--secondary);
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.4s;
            margin: 60px auto 0;
            text-decoration: none;
            text-align: center;
            width: fit-content;
            box-shadow: 0 5px 20px rgba(51, 51, 51, 0.3);
        }
        
        .view-all-btn:hover {
            background: var(--primary);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(232, 62, 140, 0.4);
        }
        
        .view-all-btn i {
            margin-left: 10px;
            transition: transform 0.3s;
        }
        
        .view-all-btn:hover i {
            transform: translateX(5px);
        }
        
        /* Animated Photo Categories */
        .categories {
            padding: 100px 5%;
            background: white;
            position: relative;
            overflow: hidden;
        }
        
        .categories::before {
            content: '';
            position: absolute;
            top: -100px;
            left: 0;
            width: 100%;
            height: 200px;
            background: var(--light);
            transform: skewY(-3deg);
            z-index: 1;
        }
        
        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 2.8rem;
            text-align: center;
            margin-bottom: 60px;
            color: var(--primary);
            position: relative;
            z-index: 2;
        }
        
        .category-slider {
            display: flex;
            gap: 30px;
            padding: 20px 0;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            scrollbar-width: none;
            -ms-overflow-style: none;
            animation: slideIn 1s both;
        }
        
        .category-slider::-webkit-scrollbar {
            display: none;
        }
        
        .category-card {
            min-width: 300px;
            height: 400px;
            border-radius: 15px;
            overflow: hidden;
            position: relative;
            scroll-snap-align: start;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.5s, box-shadow 0.5s;
            animation: float 6s ease-in-out infinite;
        }
        
        .category-card:nth-child(1) { animation-delay: 0s; }
        .category-card:nth-child(2) { animation-delay: 0.5s; }
        .category-card:nth-child(3) { animation-delay: 1s; }
        .category-card:nth-child(4) { animation-delay: 1.5s; }
        
        .category-card:hover {
            transform: scale(1.05);
            box-shadow: 0 15px 40px rgba(232, 62, 140, 0.3);
        }
        
        .category-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }
        
        .category-card:hover .category-image {
            transform: scale(1.1);
        }
        
        .category-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            padding: 30px;
            background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
            color: white;
            transform: translateY(20px);
            opacity: 0;
            transition: all 0.5s;
        }
        
        .category-card:hover .category-overlay {
            transform: translateY(0);
            opacity: 1;
        }
        
        .category-name {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .shop-now {
            display: inline-block;
            padding: 8px 20px;
            background: var(--primary);
            color: white;
            border-radius: 30px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .shop-now:hover {
            background: white;
            color: var(--primary);
        }
        
        /* Popular Products */
        .popular-products {
            padding: 100px 5%;
            background: var(--light);
            position: relative;
        }
        
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 40px;
            margin-top: 50px;
        }
        
        .product-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 25px rgba(0,0,0,0.05);
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            transform: translateY(50px);
            opacity: 0;
        }
        
        .product-card.visible {
            transform: translateY(0);
            opacity: 1;
        }
        
        .product-card:hover {
            transform: translateY(-10px) !important;
            box-shadow: 0 15px 40px rgba(232, 62, 140, 0.2);
        }
        
        .product-image {
            height: 300px;
            overflow: hidden;
            position: relative;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.8s;
        }
        
        .product-card:hover .product-image img {
            transform: scale(1.1);
        }
        
        .product-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: var(--accent);
            color: var(--secondary);
            padding: 5px 15px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.8rem;
            animation: pulse 2s infinite;
        }
        
        .product-info {
            padding: 25px;
        }
        
        .product-name {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: var(--secondary);
        }
        
        .product-description {
            color: #666;
            margin-bottom: 15px;
            line-height: 1.5;
        }
        
        .product-price {
            font-weight: 700;
            color: var(--primary);
            font-size: 1.5rem;
            margin: 20px 0;
        }
        
        .add-to-cart {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 30px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .add-to-cart:hover {
            background: var(--secondary);
        }
        
        .add-to-cart::before {
            content: '🛒';
            margin-right: 10px;
            font-size: 1.1rem;
        }
        
        /* Cart Modal */
        .cart-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        
        .cart-content {
            background: white;
            border-radius: 15px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 30px;
            position: relative;
        }
        
        .close-cart {
            position: absolute;
            top: 15px;
            right: 20px;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--secondary);
        }
        
        .cart-items {
            margin: 20px 0;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        
        .cart-item-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
        }
        
        .cart-item-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .cart-item-details h4 {
            margin-bottom: 5px;
        }
        
        .cart-item-price {
            color: var(--primary);
            font-weight: 600;
        }
        
        .cart-item-quantity {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .quantity-btn {
            background: var(--accent);
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .remove-btn {
            background: #ff4757;
            color: white;
            border: none;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
        
        .cart-total {
            text-align: right;
            font-size: 1.3rem;
            font-weight: 700;
            margin: 20px 0;
            color: var(--primary);
        }
        
        .checkout-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1.1rem;
            width: 100%;
            transition: all 0.3s;
        }
        
        .checkout-btn:hover {
            background: var(--secondary);
        }
        
        .empty-cart {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }
        
        /* Order Form Modal */
        .order-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        
        .order-content {
            background: white;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 30px;
            position: relative;
        }
        
        .close-order {
            position: absolute;
            top: 15px;
            right: 20px;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--secondary);
        }
        
        .order-form {
            margin-top: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: 'Montserrat', sans-serif;
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 2px rgba(232, 62, 140, 0.2);
        }
        
        .form-row {
            display: flex;
            gap: 15px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .submit-order {
            background: var(--primary);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1.1rem;
            width: 100%;
            transition: all 0.3s;
            margin-top: 20px;
        }
        
        .submit-order:hover {
            background: var(--secondary);
        }
        
        .order-summary {
            background: var(--accent);
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        /* Success Modal */
        .success-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        
        .success-content {
            background: white;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            padding: 40px;
            text-align: center;
            position: relative;
        }
        
        .success-icon {
            font-size: 4rem;
            color: #4CAF50;
            margin-bottom: 20px;
        }
        
        .close-success {
            position: absolute;
            top: 15px;
            right: 20px;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--secondary);
        }
        
        /* About Section */
        .about-section {
            padding: 100px 5%;
            background: white;
            position: relative;
        }
        
        .about-content {
            display: flex;
            align-items: center;
            gap: 50px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .about-image {
            flex: 1;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 15px 40px rgba(0,0,0,0.1);
        }
        
        .about-image img {
            width: 100%;
            height: auto;
            display: block;
        }
        
        .about-text {
            flex: 1;
        }
        
        .about-text h2 {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 20px;
        }
        
        .about-text p {
            margin-bottom: 20px;
            line-height: 1.8;
            color: #555;
        }
        
        /* Newsletter Section */
        .newsletter {
            padding: 80px 5%;
            background: var(--primary);
            color: white;
            text-align: center;
        }
        
        .newsletter h2 {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            margin-bottom: 20px;
        }
        
        .newsletter p {
            max-width: 600px;
            margin: 0 auto 30px;
            line-height: 1.6;
        }
        
        .newsletter-form {
            display: flex;
            max-width: 500px;
            margin: 0 auto;
        }
        
        .newsletter-input {
            flex: 1;
            padding: 15px 20px;
            border: none;
            border-radius: 50px 0 0 50px;
            font-size: 1rem;
        }
        
        .newsletter-button {
            background: var(--secondary);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 0 50px 50px 0;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .newsletter-button:hover {
            background: black;
        }
        
        /* Footer */
        footer {
            background: var(--secondary);
            color: white;
            padding: 60px 5% 30px;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 40px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .footer-column h3 {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            margin-bottom: 20px;
            color: var(--accent);
        }
        
        .footer-column ul {
            list-style: none;
        }
        
        .footer-column li {
            margin-bottom: 10px;
        }
        
        .footer-column a {
            color: #ddd;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .footer-column a:hover {
            color: white;
            padding-left: 5px;
        }
        
        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        .social-links a {
            display: inline-block;
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            text-align: center;
            line-height: 40px;
            color: white;
            transition: all 0.3s;
        }
        
        .social-links a:hover {
            background: var(--primary);
            transform: translateY(-3px);
        }
        
        .copyright {
            text-align: center;
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            color: #aaa;
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes fadeInDown {
            from { 
                opacity: 0;
                transform: translateY(-30px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeInUp {
            from { 
                opacity: 0;
                transform: translateY(30px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideIn {
            from { 
                opacity: 0;
                transform: translateX(-50px);
            }
            to { 
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
            100% { transform: translateY(0px); }
        }
        
        @keyframes zoomOut {
            from { transform: scale(1.1); }
            to { transform: scale(1); }
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        /* Responsive Design */
        @media (max-width: 992px) {
            .hero h2 {
                font-size: 3rem;
            }
            
            .section-title {
                font-size: 2.2rem;
            }
            
            .category-card {
                min-width: 250px;
                height: 350px;
            }
            
            .about-content {
                flex-direction: column;
            }
            
            .about-image {
                width: 100%;
            }
            
            .header-left {
                gap: 15px;
            }
            
            .search-input.active {
                width: 120px;
            }
        }
        
        @media (max-width: 768px) {
            header {
                flex-wrap: wrap;
                padding-bottom: 80px;
            }
            
            .header-left {
                order: 1;
                width: 100%;
                justify-content: space-between;
                margin-bottom: 15px;
            }
            
            .logo-container {
                position: static;
                order: 2;
                transform: none;
                margin: 0 auto 15px;
            }
            
            nav {
                order: 3;
                width: 100%;
            }
            
            nav ul {
                justify-content: center;
                flex-wrap: wrap;
            }
            
            nav li {
                margin: 5px 10px;
            }
            
            .hero {
                margin-top: 200px;
                padding: 0 5%;
            }
            
            .hero h2 {
                font-size: 2.2rem;
            }
            
            .hero p {
                font-size: 1.1rem;
            }
            
            .category-card {
                min-width: 220px;
                height: 300px;
            }
            
            .newsletter-form {
                flex-direction: column;
            }
            
            .newsletter-input {
                border-radius: 50px;
                margin-bottom: 10px;
            }
            
            .newsletter-button {
                border-radius: 50px;
            }
            
            .search-input.active {
                width: 150px;
            }
            
            .cart-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .cart-item-info {
                width: 100%;
            }
            
            .cart-item-quantity {
                width: 100%;
                justify-content: space-between;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }
        
        /* RTL styles for Arabic */
        body[dir="rtl"] {
            text-align: right;
        }
        
        body[dir="rtl"] .logo-container {
            margin-right: 0;
            margin-left: 15px;
        }
        
        body[dir="rtl"] .language-selector {
            margin-left: 0;
            margin-right: auto;
        }
        
        body[dir="rtl"] nav a::after {
            left: auto;
            right: 0;
        }
        
        body[dir="rtl"] .newsletter-input {
            border-radius: 0 50px 50px 0;
        }
        
        body[dir="rtl"] .newsletter-button {
            border-radius: 50px 0 0 50px;
        }
        
        body[dir="rtl"] .footer-column a:hover {
            padding-left: 0;
            padding-right: 5px;
        }
        
        body[dir="rtl"] .add-to-cart::before {
            margin-right: 0;
            margin-left: 10px;
        }
        
        body[dir="rtl"] .close-cart,
        body[dir="rtl"] .close-order,
        body[dir="rtl"] .close-success {
            right: auto;
            left: 20px;
        }
        
        body[dir="rtl"] .view-all-btn i {
            margin-left: 0;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <header id="main-header">
        <!-- Left side: Language, Search, Wishlist, Cart -->
        <div class="header-left">
            <!-- Language selector -->
            <div class="language-selector">
                <button class="language-btn active" onclick="changeLanguage('fr')">FR</button>
                <button class="language-btn" onclick="changeLanguage('ar')">AR</button>
            </div>
            
            <!-- Search -->
            <div class="search-container">
                <input type="text" class="search-input" placeholder="Recherche..." data-fr-placeholder="Recherche..." data-ar-placeholder="بحث..."
                       onkeyup="handleSearch(event)">
                <button class="search-btn" onclick="toggleSearch()"><i class="fas fa-search"></i></button>
                <div class="search-results" id="searchResults"></div>
            </div>
            
            <!-- Wishlist and Cart -->
            <div class="header-icons">
                <button class="wishlist-btn">
                    <i class="fas fa-heart"></i>
                    <span class="wishlist-count">0</span>
                </button>
                <button class="cart-btn" onclick="openCart()">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="cart-count"><?= $cart_count ?></span>
                </button>
            </div>
        </div>
        
        <!-- Centered Logo -->
        <div class="logo-container">
            <img src="simou.jpg" alt="SIMOL Logo" class="logo-img">
        </div>
        
        <nav>
            <ul>
                <li><a href="#home" data-fr="Accueil" data-ar="الرئيسية">Accueil</a></li>
                <li><a href="#categories" data-fr="Catégories" data-ar="الفئات">Catégories</a></li>
                <li><a href="#products" data-fr="Produits" data-ar="المنتجات">Produits</a></li>
                <li><a href="#about" data-fr="À propos" data-ar="من نحن">À propos</a></li>
                <li><a href="#contact" data-fr="Contact" data-ar="اتصل بنا">Contact</a></li>
            </ul>
        </nav>
    </header>
    
    <!-- Cart Modal -->
    <div class="cart-modal" id="cartModal">
        <div class="cart-content">
            <button class="close-cart" onclick="closeCart()">&times;</button>
            <h2 data-fr="Votre Panier" data-ar="سلة التسوق">Votre Panier</h2>
            <div class="cart-items" id="cartItems">
                <!-- Cart items will be loaded here -->
            </div>
            <div class="cart-total" id="cartTotal"></div>
            <button class="checkout-btn" onclick="openOrderForm()" data-fr="Passer la Commande" data-ar="إتمام الطلب">Passer la Commande</button>
        </div>
    </div>
    
    <!-- Order Form Modal -->
    <div class="order-modal" id="orderModal">
        <div class="order-content">
            <button class="close-order" onclick="closeOrderForm()">&times;</button>
            <h2 data-fr="Informations de Commande" data-ar="معلومات الطلب">Informations de Commande</h2>
            <form class="order-form" id="orderForm">
                <div class="form-row">
                    <div class="form-group">
                        <label data-fr="Prénom" data-ar="الاسم الأول">Prénom</label>
                        <input type="text" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label data-fr="Nom" data-ar="اسم العائلة">Nom</label>
                        <input type="text" name="last_name" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label data-fr="Email" data-ar="البريد الإلكتروني">Email</label>
                    <input type="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label data-fr="Téléphone" data-ar="رقم الهاتف">Téléphone</label>
                    <input type="tel" name="phone" required>
                </div>
                
                <div class="form-group">
                    <label data-fr="Adresse de Livraison" data-ar="عنوان التوصيل">Adresse de Livraison</label>
                    <textarea name="shipping_address" rows="3" required data-fr-placeholder="Votre adresse complète..." data-ar-placeholder="عنوانك الكامل..."></textarea>
                </div>
                
                <div class="form-group">
                    <label data-fr="Méthode de Paiement" data-ar="طريقة الدفع">Méthode de Paiement</label>
                    <select name="payment_method" required>
                        <option value="" data-fr="Sélectionnez..." data-ar="اختر...">Sélectionnez...</option>
                        <option value="cash" data-fr="Paiement à la Livraison" data-ar="الدفع عند الاستلام">Paiement à la Livraison</option>
                        <option value="ccp" data-fr="Virement CCP" data-ar="تحويل CCP">Virement CCP</option>
                        <option value="carte" data-fr="Carte Bancaire" data-ar="البطاقة البنكية">Carte Bancaire</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label data-fr="Notes (Optionnel)" data-ar="ملاحظات (اختياري)">Notes (Optionnel)</label>
                    <textarea name="notes" rows="3" data-fr-placeholder="Notes supplémentaires..." data-ar-placeholder="ملاحظات إضافية..."></textarea>
                </div>
                
                <div class="order-summary">
                    <h3 data-fr="Résumé de la Commande" data-ar="ملخص الطلب">Résumé de la Commande</h3>
                    <div id="orderSummary"></div>
                </div>
                
                <button type="submit" class="submit-order" data-fr="Confirmer la Commande" data-ar="تأكيد الطلب">Confirmer la Commande</button>
            </form>
        </div>
    </div>
    
    <!-- Success Modal -->
    <div class="success-modal" id="successModal">
        <div class="success-content">
            <button class="close-success" onclick="closeSuccess()">&times;</button>
            <div class="success-icon">✓</div>
            <h2 data-fr="Commande Confirmée!" data-ar="تم تأكيد الطلب!">Commande Confirmée!</h2>
            <p id="successMessage" data-fr="Merci pour votre commande. Votre numéro de commande est: " data-ar="شكراً لطلبك. رقم طلبك هو: "></p>
            <button class="cta-button" onclick="closeSuccess()" data-fr="Fermer" data-ar="إغلاق">Fermer</button>
        </div>
    </div>
    
    <section class="hero" id="home">
        
    </section>
    
    <section class="categories" id="categories">
        <h2 class="section-title" data-fr="Nos Catégories" data-ar="فئاتنا">Nos Catégories</h2>
        <div class="category-slider">
            <?php if (!empty($categories)): ?>
                <?php foreach ($categories as $category): ?>
                <div class="category-card" onclick="location.href='#category-<?= $category['category_id'] ?>'">
                    <img src="<?= htmlspecialchars($category['image_url'] ?? 'https://via.placeholder.com/300x400') ?>" 
                         alt="<?= htmlspecialchars($category['name_fr']) ?>" class="category-image">
                    <div class="category-overlay">
                        <h3 class="category-name" 
                            data-fr="<?= htmlspecialchars($category['name_fr']) ?>" 
                            data-ar="<?= htmlspecialchars($category['name_ar']) ?>">
                            <?= htmlspecialchars($category['name_fr']) ?>
                        </h3>
                        <a href="#category-<?= $category['category_id'] ?>" class="shop-now" 
                           data-fr="Consulter" data-ar="تسوق الآن">Consulter</a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-categories" style="text-align: center; padding: 50px; width: 100%;">
                    <h3 data-fr="Aucune catégorie disponible" data-ar="لا توجد فئات متاحة">
                        Aucune catégorie disponible
                    </h3>
                </div>
            <?php endif; ?>
        </div>
    </section>
    
    <section class="popular-products" id="products">
        <h2 class="section-title" data-fr="Produits Populaires" data-ar="المنتجات الأكثر مبيعاً">Nos Meilleur Produits</h2>
        <div class="product-grid">
            <?php if (!empty($products)): ?>
                <?php foreach ($products as $product): ?>
                <div class="product-card">
                    <div class="product-image">
                        <img src="<?= htmlspecialchars($product['image_url'] ?? 'https://via.placeholder.com/300x300') ?>" alt="<?= htmlspecialchars($product['name_fr']) ?>">
                        <?php if ($product['is_bestseller']): ?>
                        <span class="product-badge" data-fr="Best-seller" data-ar="الأكثر مبيعاً">Best-seller</span>
                        <?php elseif ($product['is_new']): ?>
                        <span class="product-badge" data-fr="Nouveau" data-ar="جديد">Nouveau</span>
                        <?php endif; ?>
                    </div>
                    <div class="product-info">
                        <h3 class="product-name" data-fr="<?= htmlspecialchars($product['name_fr']) ?>" data-ar="<?= htmlspecialchars($product['name_ar']) ?>">
                            <?= htmlspecialchars($product['name_fr']) ?>
                        </h3>
                        <p class="product-description" data-fr="<?= htmlspecialchars($product['description_fr']) ?>" data-ar="<?= htmlspecialchars($product['description_ar'] ?? $product['description_fr']) ?>">
                            <?= htmlspecialchars($product['description_fr']) ?>
                        </p>
                        <div class="product-price"><?= number_format($product['price'], 2) ?> DA</div>
                        
                        <button class="add-to-cart" data-fr="Ajouter au Panier" data-ar="أضف إلى السلة" 
                                onclick="addToCart(<?= $product['product_id'] ?>, '<?= addslashes($product['name_fr']) ?>')">
                            Ajouter au Panier
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-products" style="grid-column: 1 / -1; text-align: center; padding: 50px;">
                    <h3 data-fr="Aucun produit disponible pour le moment" data-ar="لا توجد منتجات متاحة حاليا">
                        Aucun produit disponible pour le moment
                    </h3>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- View All Products Button -->
        <a href="produit.php" class="view-all-btn" data-fr="Voir Tous les Produits" data-ar="عرض جميع المنتجات">
            Voir Tous les Produits <i class="fas fa-arrow-right"></i>
        </a>
    </section>
    
    <section class="about-section" id="about">
    <div class="about-content">
        <div class="about-image">
            <img src="oussama1.jpg" alt="About SIMOU">
        </div>
        <div class="about-text">
            <h2 data-fr="À propos de Cosmetique SIMOU" data-ar="حول كوزماتيك سيمو">
                À propos de Cosmetique SIMOU
            </h2>

            <p 
                data-fr="
Simou Cosmétique est votre destination beauté à Constantine. Nous proposons des produits authentiques, efficaces et accessibles, soigneusement sélectionnés parmi les meilleures marques.
Dans notre boutique, vous trouverez soins du visage et du corps, produits capillaires, parfums, maquillage et accessoires.

🎯 Notre vision : offrir une expérience d'achat simple, élégante et fiable.
💛 Nos valeurs : Authenticité, Confiance et Qualité.
⚡ Notre mission : faciliter l'accès à des produits de beauté sûrs, en magasin ou en ligne."
                
                data-ar="
سيمو كوزميتيك هي وجهتكم الأولى للجمال في قسنطينة. نقدّم منتجات تجميل أصلية وفعّالة وبأسعار مناسبة، ونختارها بعناية من أفضل الماركات.
في متجرنا تجدون: مستحضرات العناية، منتجات الشعر، العطور، والمكياج.

🎯 رؤيتنا: تقديم تجربة شراء راقية وسهلة وموثوقة.
💛 قيمنا: الأصالة، الثقة، الجودة.
⚡ مهمّتنا: تسهيل الوصول إلى منتجات تجميل موثوقة، سواء في المتجر أو عبر موقعنا.">

               Simou Cosmétique est votre destination beauté à Constantine. Nous proposons des produits authentiques, efficaces et accessibles, soigneusement sélectionnés parmi les meilleures marques.
Dans notre boutique, vous trouverez soins du visage et du corps, produits capillaires, parfums, maquillage et accessoires.

🎯 Notre vision : offrir une expérience d'achat simple, élégante et fiable.
💛 Nos valeurs : Authenticité, Confiance et Qualité.
⚡ Notre mission : faciliter l'accès à des produits de beauté sûrs, en magasin ou en ligne.
            </p>

        </div>
    </div>
</section>

    
    <section class="newsletter" id="contact">
        <h2 data-fr="Abonnez-vous à Notre Newsletter" data-ar="اشترك في نشرتنا الإخبارية">Abonnez-vous à Notre Newsletter</h2>
        <p data-fr="Recevez en exclusivité nos offres spéciales, conseils beauté et nouveautés" 
           data-ar="احصل حصريًا على عروضنا الخاصة ونصائح الجديد والجمال">
            Recevez en exclusivité nos offres spéciales, conseils beauté et nouveautés
        </p>
        <form class="newsletter-form" id="newsletterForm">
            <input type="email" class="newsletter-input" name="newsletter_email" placeholder="Votre email" 
                   data-fr-placeholder="Votre email" data-ar-placeholder="بريدك الإلكتروني" required>
            <input type="hidden" name="lang" value="fr" id="newsletterLang">
            <button type="submit" class="newsletter-button" data-fr="S'abonner" data-ar="اشتراك">S'abonner</button>
        </form>
    </section>
    
    <footer>
        <div class="footer-content">
            <div class="footer-column">
                <img src="simou.jpg" alt="SIMOL Logo" class="logo-img">
                
                 <div class="social-links">
                    <a href="https://web.facebook.com/p/Simou-cosm%C3%A9tique-61556885977278/?_rdc=1&_rdr#"><i class="fab fa-facebook-f"></i></a>
                    <a href="https://www.instagram.com/simou_cosmetiquee/?hl=en"><i class="fab fa-instagram"></i></a>
                    <a href="https://www.tiktok.com/@simoucosmetique"><i class="fab fa-tiktok"></i></a>
                    <a href="#"><i class="fab fa-telegram"></i></a>
                </div>
            </div>
            
            <div class="footer-column">
                <h3 data-fr="Liens Rapides" data-ar="روابط سريعة">Liens Rapides</h3>
                <ul>
                    <li><a href="#home" data-fr="Accueil" data-ar="الرئيسية">Accueil</a></li>
                    <li><a href="#categories" data-fr="Catégories" data-ar="الفئات">Catégories</a></li>
                    <li><a href="#products" data-fr="Produits" data-ar="المنتجات">Produits</a></li>
                    <li><a href="#about" data-fr="À propos" data-ar="من نحن">À propos</a></li>
                    <li><a href="#contact" data-fr="Contact" data-ar="اتصل بنا">Contact</a></li>
                </ul>
            </div>
            
            <div class="footer-column">
                
            </div>
            
            <div class="footer-column">
                <h3 data-fr="Contactez-nous" data-ar="اتصل بنا">Contactez-nous</h3>
                <ul>
                    <li data-fr="Salah Bey Mall UV 07 El Khroub,Constantine" data-ar="Salah Bey Mall UV 07 El Khroub,Constantine">Salah Bey Mall UV 07 El Khroub,Constantine</li>
                    <li>contact@cosmetiquesimol.com</li>
                    <li data-fr="0699 50 60 60" data-ar="0699 50 60 60">0699 50 60 60</li>
                    <li data-fr="Samedi-Vendredi: 9h-18h" data-ar="الإثنين-الجمعة: 9 صباحًا - 6 مساءً">Lundi-Vendredi: 9h-18h</li>
                </ul>
            </div>
        </div>
        
        <div class="copyright">
            <p>&copy; <span data-fr="2025 Cosmetique SIMOU. Tous droits réservés." data-ar="2023 كوزماتيك سيمو. جميع الحقوق محفوظة.">2023 Cosmetique SIMOU. Tous droits réservés.</span></p>
        </div>
    </footer>

    <script>
        // Search functionality
        let searchTimeout;
        
        function handleSearch(event) {
            const searchInput = event.target;
            const searchTerm = searchInput.value.trim();
            
            // Clear previous timeout
            clearTimeout(searchTimeout);
            
            if (searchTerm.length < 2) {
                document.getElementById('searchResults').classList.remove('active');
                return;
            }
            
            // Set timeout to prevent too many requests
            searchTimeout = setTimeout(() => {
                performSearch(searchTerm);
            }, 300);
        }
        
        function performSearch(searchTerm) {
            fetch(`?search=${encodeURIComponent(searchTerm)}`)
                .then(response => response.json())
                .then(data => {
                    const resultsContainer = document.getElementById('searchResults');
                    
                    if (data.success && data.results.length > 0) {
                        let resultsHTML = '';
                        
                        data.results.forEach(product => {
                            resultsHTML += `
                                <div class="search-result-item" onclick="viewProduct(${product.product_id})">
                                    <img src="${product.image_url || 'https://via.placeholder.com/60x60'}" alt="${product.name_fr}" class="search-result-image">
                                    <div class="search-result-info">
                                        <h4>${product.name_fr}</h4>
                                        <div class="search-result-price">${parseFloat(product.price).toFixed(2)} DA</div>
                                        <small>${product.description_fr ? product.description_fr.substring(0, 50) + '...' : ''}</small>
                                    </div>
                                </div>
                            `;
                        });
                        
                        resultsContainer.innerHTML = resultsHTML;
                        resultsContainer.classList.add('active');
                    } else {
                        resultsContainer.innerHTML = `
                            <div class="no-results">
                                ${document.body.dir === 'rtl' ? 'لم يتم العثور على منتجات' : 'Aucun produit trouvé'}
                            </div>
                        `;
                        resultsContainer.classList.add('active');
                    }
                })
                .catch(error => {
                    console.error('Search error:', error);
                });
        }
        
        // View product when clicked in search results
        function viewProduct(productId) {
            // Hide search results
            document.getElementById('searchResults').classList.remove('active');
            document.querySelector('.search-input').value = '';
            document.querySelector('.search-input').classList.remove('active');
            
            // Scroll to products section
            document.getElementById('products').scrollIntoView({ behavior: 'smooth' });
            
            // You could also highlight the specific product here
            const productCard = document.querySelector(`[onclick*="addToCart(${productId},"]`);
            if (productCard) {
                productCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                productCard.style.boxShadow = '0 0 0 3px var(--primary)';
                setTimeout(() => {
                    productCard.style.boxShadow = '';
                }, 2000);
            }
        }
        
        // Close search results when clicking outside
        document.addEventListener('click', function(e) {
            const searchContainer = document.querySelector('.search-container');
            const searchResults = document.getElementById('searchResults');
            
            if (!searchContainer.contains(e.target) && searchResults.classList.contains('active')) {
                searchResults.classList.remove('active');
            }
        });
        
        // Cart functions
        function openCart() {
            console.log('Opening cart...');
            updateCartDisplay();
            document.getElementById('cartModal').style.display = 'flex';
        }

        function closeCart() {
            document.getElementById('cartModal').style.display = 'none';
        }

        function addToCart(productId, productName) {
            console.log('Adding product:', productId, productName);
            
            // Hide search results
            document.getElementById('searchResults').classList.remove('active');
            
            // Send AJAX request to add to cart
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'add_to_cart=true&product_id=' + productId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update cart count
                    document.querySelector('.cart-count').textContent = data.count;
                    // Show success message
                    const message = document.body.dir === 'rtl' ? 
                        `${productName} تمت إضافته إلى سلة التسوق` : 
                        `${productName} a été ajouté à votre panier`;
                    alert(message);
                    // Update cart display if it's open
                    updateCartDisplay();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                const message = document.body.dir === 'rtl' ? 
                    'حدث خطأ أثناء الإضافة إلى السلة' : 
                    'Erreur lors de l\'ajout au panier';
                alert(message);
            });
        }

        function updateCartDisplay() {
            // Send request to get cart contents
            fetch('?get_cart=true')
            .then(response => response.text())
            .then(html => {
                document.getElementById('cartItems').innerHTML = html;
                updateCartTotal();
            })
            .catch(error => {
                console.error('Error:', error);
                const message = document.body.dir === 'rtl' ? 
                    'خطأ في تحميل السلة' : 
                    'Erreur de chargement du panier';
                document.getElementById('cartItems').innerHTML = '<p>' + message + '</p>';
            });
        }

        function updateCartTotal() {
            // Get the total from the hidden element
            const totalElement = document.getElementById('cart-total-amount');
            if (totalElement) {
                const total = parseFloat(totalElement.dataset.total);
                document.getElementById('cartTotal').textContent = 'Total: ' + total.toFixed(2) + ' DA';
            } else {
                // Fallback: calculate from visible items
                let total = 0;
                document.querySelectorAll('.cart-item-total').forEach(item => {
                    const priceText = item.textContent;
                    const price = parseFloat(priceText.replace(' DA', ''));
                    total += price;
                });
                document.getElementById('cartTotal').textContent = 'Total: ' + total.toFixed(2) + ' DA';
            }
        }

        function updateQuantity(productId, change) {
            // Get current quantity
            const quantityElement = document.querySelector('.cart-item[data-product-id="' + productId + '"] .cart-item-quantity span');
            let quantity = parseInt(quantityElement.textContent);
            
            quantity += change;
            
            if (quantity < 1) {
                removeFromCart(productId);
                return;
            }
            
            // Update via AJAX
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'update_cart=true&product_id=' + productId + '&quantity=' + quantity
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.querySelector('.cart-count').textContent = data.count;
                    updateCartDisplay();
                }
            });
        }

        function removeFromCart(productId) {
            // Update via AJAX
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'update_cart=true&product_id=' + productId + '&quantity=0'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.querySelector('.cart-count').textContent = data.count;
                    updateCartDisplay();
                }
            });
        }

        function openOrderForm() {
            if (document.querySelectorAll('.cart-item').length === 0) {
                const message = document.body.dir === 'rtl' ? 
                    'سلة التسوق فارغة!' : 
                    'Votre panier est vide!';
                alert(message);
                return;
            }
            
            const orderModal = document.getElementById('orderModal');
            const orderSummary = document.getElementById('orderSummary');
            
            // Build order summary
            let summaryHTML = '';
            let totalAmount = 0;
            
            document.querySelectorAll('.cart-item').forEach(item => {
                const productName = item.querySelector('.cart-item-details h4').textContent;
                const quantity = item.querySelector('.cart-item-quantity span').textContent;
                const itemTotal = item.querySelector('.cart-item-total').textContent;
                const itemTotalValue = parseFloat(itemTotal.replace(' DA', ''));
                
                summaryHTML += `
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span>${productName} x ${quantity}</span>
                        <span>${itemTotal}</span>
                    </div>
                `;
                
                totalAmount += itemTotalValue;
            });
            
            summaryHTML += `
                <div style="display: flex; justify-content: space-between; font-weight: bold; border-top: 1px solid #ddd; padding-top: 10px; margin-top: 10px;">
                    <span data-fr="Total" data-ar="المجموع">Total</span>
                    <span>${totalAmount.toFixed(2)} DA</span>
                </div>
            `;
            
            orderSummary.innerHTML = summaryHTML;
            
            closeCart();
            orderModal.style.display = 'flex';
        }
        
        function closeOrderForm() {
            document.getElementById('orderModal').style.display = 'none';
        }

        // Handle order form submission
        document.getElementById('orderForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('process_order', 'true');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Close order form and show success modal
                    closeOrderForm();
                    
                    // Show success message
                    const prefix = document.body.dir === 'rtl' ? 'شكراً لطلبك. رقم طلبك هو: ' : 'Merci pour votre commande. Votre numéro de commande est: ';
                    document.getElementById('successMessage').textContent = prefix + data.order_number;
                    document.getElementById('successModal').style.display = 'flex';
                    
                    // Update cart count
                    document.querySelector('.cart-count').textContent = '0';
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                const message = document.body.dir === 'rtl' ? 
                    'حدث خطأ أثناء معالجة الطلب' : 
                    'Une erreur est survenue lors du traitement de la commande';
                alert(message);
            });
        });

        function closeSuccess() {
            document.getElementById('successModal').style.display = 'none';
            location.reload();
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const cartModal = document.getElementById('cartModal');
            const orderModal = document.getElementById('orderModal');
            const successModal = document.getElementById('successModal');
            
            if (event.target === cartModal) {
                closeCart();
            }
            if (event.target === orderModal) {
                closeOrderForm();
            }
            if (event.target === successModal) {
                closeSuccess();
            }
        }

        // Your existing JavaScript functions
        let lastScroll = 0;
        const header = document.getElementById('main-header');
        
        window.addEventListener('scroll', () => {
            const currentScroll = window.pageYOffset;
            
            if (currentScroll <= 0) {
                header.classList.remove('hidden');
                return;
            }
            
            if (currentScroll > lastScroll && !header.classList.contains('hidden')) {
                header.classList.add('hidden');
            } else if (currentScroll < lastScroll && header.classList.contains('hidden')) {
                header.classList.remove('hidden');
            }
            
            lastScroll = currentScroll;
        });
        
        // Product card animation on scroll
        const productCards = document.querySelectorAll('.product-card');
        
        const animateOnScroll = () => {
            productCards.forEach(card => {
                const cardPosition = card.getBoundingClientRect().top;
                const screenPosition = window.innerHeight / 1.3;
                
                if (cardPosition < screenPosition) {
                    card.classList.add('visible');
                }
            });
        };
        
        window.addEventListener('scroll', animateOnScroll);
        window.addEventListener('load', animateOnScroll);
        
        // Language switcher
        function changeLanguage(lang) {
            document.body.dir = lang === 'ar' ? 'rtl' : 'ltr';
            document.querySelectorAll('[data-fr], [data-ar]').forEach(element => {
                if (element.hasAttribute('data-' + lang)) {
                    if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') {
                        if (element.hasAttribute('data-' + lang + '-placeholder')) {
                            element.placeholder = element.getAttribute('data-' + lang + '-placeholder');
                        }
                    } else {
                        element.textContent = element.getAttribute('data-' + lang);
                    }
                }
            });
            
            document.querySelectorAll('.language-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            document.getElementById('newsletterLang').value = lang;
        }
        
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
        
        // Newsletter form submission
        const newsletterForm = document.getElementById('newsletterForm');
        newsletterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const lang = formData.get('lang');
            
            // Validate email
            const email = formData.get('newsletter_email');
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (!emailRegex.test(email)) {
                alert(lang === 'ar' ? 'يرجى إدخال بريد إلكتروني صالح' : 'Veuillez entrer une adresse email valide');
                return;
            }
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    this.reset();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert(lang === 'ar' ? 'حدث خطأ أثناء الإرسال' : 'Une erreur est survenue lors de l\'envoi');
            });
        });
        
        // Toggle search bar
        function toggleSearch() {
            const searchInput = document.querySelector('.search-input');
            searchInput.classList.toggle('active');
            if (searchInput.classList.contains('active')) {
                searchInput.focus();
            }
        }
        
        // Sample functionality for wishlist
        document.querySelector('.wishlist-btn').addEventListener('click', function() {
            alert(document.body.dir === 'rtl' ? 
                 'سيتم عرض المنتجات المفضلة هنا' : 
                 'Vos produits favoris seront affichés ici');
        });
    </script>
</body>
</html>