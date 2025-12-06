<?php
session_start();

// Initialize cart and wishlist if not set
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
if (!isset($_SESSION['wishlist'])) {
    $_SESSION['wishlist'] = [];
}

// Database connection
$servername = "localhost";
$username = "root"; // Change if needed
$password = "admine"; // Change if needed
$dbname = "oussama";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");

// Handle add to cart
if (isset($_POST['add_to_cart'])) {
    $product_id = intval($_POST['product_id']);
    $product_name = $_POST['product_name'];
    $product_price = floatval($_POST['product_price']);
    
    // Check if product already in cart
    $found = false;
    foreach ($_SESSION['cart'] as &$item) {
        if ($item['id'] == $product_id) {
            $item['quantity']++;
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        $_SESSION['cart'][] = [
            'id' => $product_id,
            'name' => $product_name,
            'price' => $product_price,
            'quantity' => 1
        ];
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Handle remove from cart
if (isset($_POST['remove_from_cart'])) {
    $product_id = intval($_POST['product_id']);
    $_SESSION['cart'] = array_filter($_SESSION['cart'], function($item) use ($product_id) {
        return $item['id'] != $product_id;
    });
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Handle update cart quantity
if (isset($_POST['update_cart_quantity'])) {
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);
    
    foreach ($_SESSION['cart'] as &$item) {
        if ($item['id'] == $product_id) {
            if ($quantity <= 0) {
                // Remove item if quantity is 0 or less
                $_SESSION['cart'] = array_filter($_SESSION['cart'], function($cartItem) use ($product_id) {
                    return $cartItem['id'] != $product_id;
                });
            } else {
                $item['quantity'] = $quantity;
            }
            break;
        }
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Handle order submission
if (isset($_POST['submit_order'])) {
    // Generate order number
    $order_number = 'CMD-' . date('YmdHis') . '-' . rand(1000, 9999);
    
    // Calculate total amount
    $total_amount = 0;
    foreach ($_SESSION['cart'] as $item) {
        $total_amount += $item['price'] * $item['quantity'];
    }
    
    // Get form data
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $payment_method = $_POST['payment_method'];
    
    // Insert order into database
    $sql = "INSERT INTO orders (order_number, total_amount, status, payment_method, payment_status, shipping_address, created_at) 
            VALUES (?, ?, 'pending', ?, 'pending', ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sdss", $order_number, $total_amount, $payment_method, $address);
    
    if ($stmt->execute()) {
        $order_id = $stmt->insert_id;
        
        // Insert order items
        foreach ($_SESSION['cart'] as $item) {
            $sql_item = "INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price) 
                         VALUES (?, ?, ?, ?, ?)";
            $stmt_item = $conn->prepare($sql_item);
            $total_price = $item['price'] * $item['quantity'];
            $stmt_item->bind_param("iiidd", $order_id, $item['id'], $item['quantity'], $item['price'], $total_price);
            $stmt_item->execute();
        }
        
        // Clear cart after successful order
        $_SESSION['cart'] = [];
        
        // Show success message
        $_SESSION['order_success'] = "Votre commande #$order_number a été passée avec succès!";
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Handle add to wishlist
if (isset($_POST['add_to_wishlist'])) {
    $product_id = intval($_POST['product_id']);
    
    if (!in_array($product_id, $_SESSION['wishlist'])) {
        $_SESSION['wishlist'][] = $product_id;
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Handle remove from wishlist
if (isset($_POST['remove_from_wishlist'])) {
    $product_id = intval($_POST['product_id']);
    $_SESSION['wishlist'] = array_diff($_SESSION['wishlist'], [$product_id]);
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Handle move from wishlist to cart
if (isset($_POST['move_to_cart'])) {
    $product_id = intval($_POST['product_id']);
    
    // Get product details from database
    $sql = "SELECT * FROM products WHERE product_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
        
        // Check if product already in cart
        $found = false;
        foreach ($_SESSION['cart'] as &$item) {
            if ($item['id'] == $product_id) {
                $item['quantity']++;
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $_SESSION['cart'][] = [
                'id' => $product_id,
                'name' => $product['name_fr'],
                'price' => $product['price'],
                'quantity' => 1
            ];
        }
        
        // Remove from wishlist
        $_SESSION['wishlist'] = array_diff($_SESSION['wishlist'], [$product_id]);
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Build SQL query based on filters
$sql_where = [];
$sql_params = [];
$sql_types = "";

// Category filter
if (isset($_GET['category']) && is_array($_GET['category'])) {
    $placeholders = implode(',', array_fill(0, count($_GET['category']), '?'));
    $sql_where[] = "p.category_id IN ($placeholders)";
    $sql_types .= str_repeat('i', count($_GET['category']));
    $sql_params = array_merge($sql_params, $_GET['category']);
}

// Price filter
if (isset($_GET['min_price']) && is_numeric($_GET['min_price'])) {
    $sql_where[] = "p.price >= ?";
    $sql_types .= 'd';
    $sql_params[] = floatval($_GET['min_price']);
}

if (isset($_GET['max_price']) && is_numeric($_GET['max_price'])) {
    $sql_where[] = "p.price <= ?";
    $sql_types .= 'd';
    $sql_params[] = floatval($_GET['max_price']);
}

// Search filter
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $sql_where[] = "(p.name_fr LIKE ? OR p.name_ar LIKE ? OR p.description_fr LIKE ?)";
    $sql_types .= 'sss';
    $search_term = "%" . $_GET['search'] . "%";
    $sql_params[] = $search_term;
    $sql_params[] = $search_term;
    $sql_params[] = $search_term;
}

// Build final SQL
$sql = "SELECT p.*, c.name_fr as category_name_fr, c.name_ar as category_name_ar 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.category_id";
        
if (!empty($sql_where)) {
    $sql .= " WHERE " . implode(" AND ", $sql_where);
}

// Add sorting
$sort_options = [
    'relevance' => 'p.product_id',
    'price_asc' => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'newest' => 'p.created_at DESC'
];

$sort = isset($_GET['sort']) && isset($sort_options[$_GET['sort']]) ? $_GET['sort'] : 'relevance';
$sql .= " ORDER BY " . $sort_options[$sort];

// Prepare and execute query
$stmt = $conn->prepare($sql);
if (!empty($sql_params)) {
    $stmt->bind_param($sql_types, ...$sql_params);
}
$stmt->execute();
$result = $stmt->get_result();

$products = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

// Get wishlist products
$wishlist_products = [];
if (!empty($_SESSION['wishlist'])) {
    $wishlist_ids = implode(',', array_map('intval', $_SESSION['wishlist']));
    $sql_wishlist = "SELECT p.*, c.name_fr as category_name_fr 
                     FROM products p 
                     LEFT JOIN categories c ON p.category_id = c.category_id 
                     WHERE p.product_id IN ($wishlist_ids)";
    $result_wishlist = $conn->query($sql_wishlist);
    
    if ($result_wishlist->num_rows > 0) {
        while($row = $result_wishlist->fetch_assoc()) {
            $wishlist_products[] = $row;
        }
    }
}

// Get categories for filter
$sql_categories = "SELECT * FROM categories WHERE is_active = 1";
$result_categories = $conn->query($sql_categories);

$categories = [];
if ($result_categories->num_rows > 0) {
    while($row = $result_categories->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Calculate cart and wishlist counts
$cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));
$wishlist_count = count($_SESSION['wishlist']);

// Calculate cart total
$cart_total = 0;
foreach ($_SESSION['cart'] as $item) {
    $cart_total += $item['price'] * $item['quantity'];
}

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tous les Produits - Cosmetique SIMOU</title>
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
            padding: 30px 5%;
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
            width: 150px;
            padding: 5px 10px;
            border: none;
            border-bottom: 2px solid var(--primary);
            background: transparent;
            color: var(--secondary);
            font-family: 'Montserrat', sans-serif;
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
        
        /* Main Content */
        .main-content {
            display: flex;
            margin-top: 166px;
            min-height: calc(100vh - 166px);
        }
        
        /* Enhanced Sidebar */
        .sidebar {
            width: 300px;
            background: white;
            padding: 30px 25px;
            box-shadow: 5px 0 25px rgba(232, 62, 140, 0.1);
            position: sticky;
            top: 166px;
            height: calc(100vh - 166px);
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: var(--primary) #f5f5f5;
            transition: all 0.3s ease;
            border-right: 1px solid rgba(232, 62, 140, 0.1);
        }

        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: #f5f5f5;
            border-radius: 10px;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background-color: var(--primary);
            border-radius: 10px;
        }

        .sidebar h2 {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            color: var(--primary);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--accent);
            position: relative;
        }

        .sidebar h2::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 50px;
            height: 2px;
            background: var(--primary);
        }

        /* Enhanced Filter Sections */
        .filter-section {
            margin-bottom: 25px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }

        .filter-section:hover {
            box-shadow: 0 5px 20px rgba(232, 62, 140, 0.15);
        }

        .filter-section h3 {
            font-family: 'Montserrat', sans-serif;
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
            padding: 15px 20px;
            color: var(--secondary);
            background: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .filter-section h3:hover {
            color: var(--primary);
        }

        .filter-section h3::after {
            content: '\f078';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            font-size: 0.9rem;
            transition: transform 0.3s;
        }

        .filter-section.active h3::after {
            transform: rotate(180deg);
        }

        .filter-options {
            background: rgba(254, 218, 230, 0.3);
            padding: 0 20px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out, padding 0.3s ease;
        }

        .filter-section.active .filter-options {
            max-height: 500px;
            padding: 15px 20px;
        }

        /* Enhanced Filter Groups */
        .filter-group {
            margin-bottom: 12px;
            position: relative;
            padding-left: 30px;
            transition: all 0.2s;
        }

        .filter-group:hover {
            transform: translateX(3px);
        }

        .filter-group label {
            display: block;
            margin-bottom: 0;
            cursor: pointer;
            font-size: 0.9rem;
            color: #555;
            transition: color 0.2s;
            padding: 5px 0;
        }

        .filter-group:hover label {
            color: var(--secondary);
        }

        .filter-group input[type="checkbox"],
        .filter-group input[type="radio"] {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }

        .checkmark {
            position: absolute;
            top: 6px;
            left: 0;
            height: 18px;
            width: 18px;
            background-color: white;
            border: 2px solid #ddd;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .filter-group:hover .checkmark {
            border-color: var(--primary);
        }

        .filter-group input:checked ~ .checkmark {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .checkmark:after {
            content: "";
            position: absolute;
            display: none;
        }

        .filter-group input:checked ~ .checkmark:after {
            display: block;
        }

        .filter-group .checkmark:after {
            left: 4px;
            top: 1px;
            width: 5px;
            height: 10px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }

        /* Stars for ratings */
        .stars {
            color: #FFD700;
            letter-spacing: 2px;
            font-size: 0.9rem;
        }

        /* Enhanced Price Range */
        .price-range-container {
            padding: 10px 0;
        }

        .price-range {
            -webkit-appearance: none;
            width: 100%;
            height: 6px;
            border-radius: 3px;
            background: #ddd;
            outline: none;
            margin: 15px 0;
        }

        .price-range::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: var(--primary);
            cursor: pointer;
            transition: all 0.2s;
        }

        .price-range::-webkit-slider-thumb:hover {
            transform: scale(1.2);
            box-shadow: 0 0 5px rgba(232, 62, 140, 0.5);
        }

        .price-inputs {
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }

        .price-inputs input {
            width: 48%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 20px;
            font-family: 'Montserrat', sans-serif;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .price-inputs input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 2px rgba(232, 62, 140, 0.2);
        }

        /* Enhanced Buttons */
        .filter-buttons {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 20px;
        }

        .filter-button {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 30px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
            width: 100%;
            font-size: 0.95rem;
            letter-spacing: 0.5px;
            box-shadow: 0 3px 10px rgba(232, 62, 140, 0.3);
        }

        .filter-button:hover {
            background: #d42d7a;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(232, 62, 140, 0.4);
        }

        .reset-button {
            background: white;
            color: var(--primary);
            border: 2px solid var(--primary);
            padding: 10px 25px;
            border-radius: 30px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
            width: 100%;
            font-size: 0.95rem;
        }

        .reset-button:hover {
            background: var(--primary);
            color: white;
        }
        
        /* Products Grid */
        .products-container {
            flex: 1;
            padding: 40px;
            background-color: var(--light);
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .page-title {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            color: var(--primary);
            padding-left: 50px;
        }
        
        .sort-options {
            display: flex;
            align-items: center;
        }
        
        .sort-options label {
            margin-right: 10px;
            font-weight: 500;
        }
        
        .sort-options select {
            padding: 8px 15px;
            border-radius: 20px;
            border: 1px solid #ddd;
            background: white;
            cursor: pointer;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
        }
        
        .product-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 25px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(232, 62, 140, 0.2);
        }
        
        .product-image {
            height: 350px;
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
        }
        
        .product-info {
            padding: 20px;
        }
        
        .product-category {
            color: var(--primary);
            font-size: 0.9rem;
            margin-bottom: 5px;
            display: block;
        }
        
        .product-name {
            font-family: 'Playfair Display', serif;
            font-size: 1.3rem;
            margin-bottom: 10px;
            color: var(--secondary);
        }
        
        .product-description {
            color: #666;
            margin-bottom: 15px;
            line-height: 1.5;
            font-size: 0.9rem;
        }
        
        .product-price {
            font-weight: 700;
            color: var(--primary);
            font-size: 1.3rem;
            margin: 15px 0;
        }
        
        .product-actions {
            display: flex;
            gap: 10px;
        }
        
        .add-to-cart {
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px;
            border-radius: 30px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .add-to-cart:hover {
            background: var(--secondary);
        }
        
        .wishlist-btn {
            background: white;
            color: var(--primary);
            border: 1px solid var(--primary);
            padding: 10px;
            border-radius: 30px;
            cursor: pointer;
            transition: all 0.3s;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .wishlist-btn:hover, .wishlist-btn.active {
            background: var(--primary);
            color: white;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 50px;
        }
        
        .pagination a {
            color: var(--secondary);
            padding: 8px 16px;
            text-decoration: none;
            border: 1px solid white;
            margin: 0 4px;
            border-radius: 4px;
            transition: all 0.3s;
        }
        
        .pagination a.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .pagination a:hover:not(.active) {
            background: #e83e8c;
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
        
        /* Responsive Design */
        @media (max-width: 992px) {
            .main-content {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                position: static;
                height: auto;
                padding: 20px;
                border-right: none;
                border-bottom: 1px solid rgba(232, 62, 140, 0.1);
            }
            
            .products-container {
                padding: 20px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .sort-options {
                width: 100%;
                justify-content: flex-end;
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
            
            .main-content {
                margin-top: 200px;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
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
        
        body[dir="rtl"] .filter-group label {
            padding-left: 0;
            padding-right: 30px;
        }
        
        body[dir="rtl"] .checkmark {
            left: auto;
            right: 0;
        }
        
        body[dir="rtl"] .footer-column a:hover {
            padding-left: 0;
            padding-right: 5px;
        }

        /* Toast notifications */
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--primary);
            color: white;
            padding: 15px 25px;
            border-radius: 5px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            z-index: 1000;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s;
        }

        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }

        /* Cart Modal */
        .cart-modal, .wishlist-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            animation: fadeIn 0.3s;
        }

        .cart-modal-content, .wishlist-modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            border-radius: 15px;
            box-shadow: 0 10px 50px rgba(0,0,0,0.3);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .cart-modal-header, .wishlist-modal-header {
            background: var(--primary);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cart-modal-header h2, .wishlist-modal-header h2 {
            margin: 0;
            font-family: 'Playfair Display', serif;
        }

        .close-cart, .close-wishlist {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
        }

        .cart-modal-body, .wishlist-modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
        }

        .cart-items, .wishlist-items {
            margin-bottom: 20px;
        }

        .cart-item, .wishlist-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
            gap: 15px;
        }

        .cart-item-image, .wishlist-item-image {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            object-fit: cover;
        }

        .cart-item-details, .wishlist-item-details {
            flex: 1;
        }

        .cart-item-name, .wishlist-item-name {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .cart-item-price, .wishlist-item-price {
            color: var(--primary);
            font-weight: 700;
        }

        .cart-item-quantity {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .quantity-btn {
            background: var(--primary);
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .quantity-input {
            width: 50px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 5px;
        }

        .remove-item, .move-to-cart-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 5px;
        }

        .remove-item {
            color: #ff4444;
        }

        .move-to-cart-btn {
            background: var(--primary);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            transition: all 0.3s;
        }

        .move-to-cart-btn:hover {
            background: #d42d7a;
        }

        .cart-total {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .total-final {
            font-size: 1.2rem;
            color: var(--primary);
            border-top: 2px solid #eee;
            padding-top: 10px;
        }

        .order-form {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 10px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: 'Montserrat', sans-serif;
        }

        .form-group textarea {
            height: 80px;
            resize: vertical;
        }

        .submit-order {
            background: var(--primary);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 600;
            width: 100%;
            font-size: 1.1rem;
            transition: all 0.3s;
        }

        .submit-order:hover {
            background: #d42d7a;
            transform: translateY(-2px);
        }

        .empty-cart, .empty-wishlist {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .empty-cart i, .empty-wishlist i {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 20px;
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
            <form method="GET" action="" class="search-container">
                <input type="text" class="search-input" name="search" placeholder="Recherche..." 
                       value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                       data-fr-placeholder="Recherche..." data-ar-placeholder="بحث...">
                <button type="submit" class="search-btn"><i class="fas fa-search"></i></button>
            </form>
            
            <!-- Wishlist and Cart -->
            <div class="header-icons">
                <button class="wishlist-btn" onclick="showWishlistModal()">
                    <i class="fas fa-heart"></i>
                    <span class="wishlist-count"><?php echo $wishlist_count; ?></span>
                </button>
                <button class="cart-btn" onclick="showCartModal()">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="cart-count"><?php echo $cart_count; ?></span>
                </button>
            </div>
        </div>
        
        <!-- Centered Logo -->
        <div class="logo-container">
            <img src="simou.jpg" alt="SIMOL Logo" class="logo-img">
        </div>
        
        <nav>
            <ul>
                <li><a href="mainpage.html">Accueil</a></li>
                <li><a href="produit.php">Produits</a></li>
                <li><a href="#categories" data-fr="Catégories" data-ar="الفئات">Catégories</a></li>
                <li><a href="#about" data-fr="À propos" data-ar="من نحن">À propos</a></li>
                <li><a href="#contact" data-fr="Contact" data-ar="اتصل بنا">Contact</a></li>
            </ul>
        </nav>
    </header>
    
    <div class="main-content">
        <!-- Sidebar with filters -->
        <aside class="sidebar">
            <h2 data-fr="Filtrer les Produits" data-ar="تصفية المنتجات">Filtrer les Produits</h2>
            
            <form method="GET" action="" id="filter-form">
                <!-- Keep search in URL -->
                <?php if (isset($_GET['search'])): ?>
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($_GET['search']); ?>">
                <?php endif; ?>
                
                <div class="filter-section active">
                    <h3 data-fr="Catégories" data-ar="الفئات">Catégories</h3>
                    <div class="filter-options">
                        <?php foreach($categories as $category): ?>
                        <div class="filter-group">
                            <label>
                                <input type="checkbox" name="category[]" value="<?php echo $category['category_id']; ?>"
                                    <?php echo (isset($_GET['category']) && in_array($category['category_id'], $_GET['category'])) ? 'checked' : ''; ?>>
                                <span class="checkmark"></span>
                                <?php echo $category['name_fr']; ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="filter-section">
                    <h3 data-fr="Prix" data-ar="السعر">Prix</h3>
                    <div class="filter-options">
                        <div class="price-range-container">
                            <input type="range" min="0" max="10000" value="<?php echo isset($_GET['max_price']) ? $_GET['max_price'] : 5000; ?>" 
                                   class="price-range" id="priceRange">
                            <div class="price-inputs">
                                <input type="number" id="minPrice" name="min_price" placeholder="Min" 
                                       data-fr-placeholder="Min" data-ar-placeholder="الحد الأدنى" 
                                       value="<?php echo isset($_GET['min_price']) ? $_GET['min_price'] : 0; ?>">
                                <input type="number" id="maxPrice" name="max_price" placeholder="Max" 
                                       data-fr-placeholder="Max" data-ar-placeholder="الحد الأقصى" 
                                       value="<?php echo isset($_GET['max_price']) ? $_GET['max_price'] : 5000; ?>">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="filter-buttons">
                    <button type="submit" class="filter-button" data-fr="Appliquer les Filtres" data-ar="تطبيق الفلاتر">Appliquer les Filtres</button>
                    <a href="produit.php" class="reset-button" data-fr="Réinitialiser" data-ar="إعادة تعيين">Réinitialiser</a>
                </div>
            </form>
        </aside>
        
        <!-- Main products grid -->
        <main class="products-container">
            <div class="page-header">
                <h1 class="page-title" data-fr="Nos Produits" data-ar="منتجات">Nos Produits</h1>
                <div class="sort-options">
                    <label data-fr="Trier par:" data-ar="ترتيب حسب:">Trier par:</label>
                    <select name="sort" onchange="updateSort(this.value)">
                        <option value="relevance" <?php echo $sort == 'relevance' ? 'selected' : ''; ?> data-fr="Pertinence" data-ar="الأكثر صلة">Pertinence</option>
                        <option value="price_asc" <?php echo $sort == 'price_asc' ? 'selected' : ''; ?> data-fr="Prix: Croissant" data-ar="السعر: من الأقل إلى الأعلى">Prix: Croissant</option>
                        <option value="price_desc" <?php echo $sort == 'price_desc' ? 'selected' : ''; ?> data-fr="Prix: Décroissant" data-ar="السعر: من الأعلى إلى الأقل">Prix: Décroissant</option>
                        <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?> data-fr="Nouveautés" data-ar="الأحدث">Nouveautés</option>
                    </select>
                </div>
            </div>
            
            <div class="products-grid">
                <?php if (count($products) > 0): ?>
                    <?php foreach($products as $product): 
                        $is_in_wishlist = in_array($product['product_id'], $_SESSION['wishlist']);
                    ?>
                    <div class="product-card">
                        <div class="product-image">
                            <img src="<?php echo $product['image_url'] ?: 'https://via.placeholder.com/300x400?text=No+Image'; ?>" alt="<?php echo htmlspecialchars($product['name_fr']); ?>">
                            <?php if($product['is_featured']): ?>
                                <span class="product-badge" data-fr="Best-seller" data-ar="الأكثر مبيعاً">Best-seller</span>
                            <?php elseif($product['is_new']): ?>
                                <span class="product-badge" data-fr="Nouveau" data-ar="جديد">Nouveau</span>
                            <?php endif; ?>
                        </div>
                        <div class="product-info">
                            <span class="product-category"><?php echo $product['category_name_fr']; ?></span>
                            <h3 class="product-name"><?php echo $product['name_fr']; ?></h3>
                            <p class="product-description"><?php echo $product['description_fr']; ?></p>
                            <div class="product-price"><?php echo number_format($product['price'], 0, ',', ' '); ?> DA</div>
                            <div class="product-actions">
                                <form method="POST" style="display: inline; flex: 1;">
                                    <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                    <input type="hidden" name="product_name" value="<?php echo htmlspecialchars($product['name_fr']); ?>">
                                    <input type="hidden" name="product_price" value="<?php echo $product['price']; ?>">
                                    <button type="submit" name="add_to_cart" class="add-to-cart" data-fr="Ajouter" data-ar="أضف">Ajouter</button>
                                </form>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                    <?php if ($is_in_wishlist): ?>
                                        <button type="submit" name="remove_from_wishlist" class="wishlist-btn active">
                                            <i class="fas fa-heart"></i>
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" name="add_to_wishlist" class="wishlist-btn">
                                            <i class="far fa-heart"></i>
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="grid-column: 1 / -1; text-align: center; padding: 40px; font-size: 1.2rem;">
                        Aucun produit trouvé avec les critères sélectionnés.
                    </p>
                <?php endif; ?>
            </div>
            
            <!-- Pagination -->
            <div class="pagination">
                <a href="#">&laquo;</a>
                <a href="#" class="active">1</a>
                <a href="#">2</a>
                <a href="#">3</a>
                <a href="#">4</a>
                <a href="#">5</a>
                <a href="#">&raquo;</a>
            </div>
        </main>
    </div>
    
    <!-- Cart Modal -->
    <div id="cartModal" class="cart-modal">
        <div class="cart-modal-content">
            <div class="cart-modal-header">
                <h2 data-fr="Votre Panier" data-ar="سلة التسوق">Votre Panier</h2>
                <button class="close-cart" onclick="closeCartModal()">&times;</button>
            </div>
            <div class="cart-modal-body">
                <?php if (count($_SESSION['cart']) > 0): ?>
                    <div class="cart-items">
                        <?php foreach($_SESSION['cart'] as $item): ?>
                        <div class="cart-item">
                            <img src="https://via.placeholder.com/80x80?text=Produit" alt="<?php echo htmlspecialchars($item['name']); ?>" class="cart-item-image">
                            <div class="cart-item-details">
                                <div class="cart-item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                <div class="cart-item-price"><?php echo number_format($item['price'], 0, ',', ' '); ?> DA</div>
                            </div>
                            <div class="cart-item-quantity">
                                <form method="POST" style="display: flex; align-items: center; gap: 10px;">
                                    <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" name="update_cart_quantity" class="quantity-btn" onclick="this.form.quantity.value = <?php echo $item['quantity'] - 1; ?>">-</button>
                                    <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" class="quantity-input" min="1" onchange="this.form.submit()">
                                    <button type="submit" name="update_cart_quantity" class="quantity-btn" onclick="this.form.quantity.value = <?php echo $item['quantity'] + 1; ?>">+</button>
                                </form>
                            </div>
                            <form method="POST">
                                <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                <button type="submit" name="remove_from_cart" class="remove-item" title="Supprimer">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="cart-total">
                        <div class="total-row">
                            <span data-fr="Sous-total" data-ar="المجموع الجزئي">Sous-total:</span>
                            <span><?php echo number_format($cart_total, 0, ',', ' '); ?> DA</span>
                        </div>
                        <div class="total-row">
                            <span data-fr="Livraison" data-ar="التوصيل">Livraison:</span>
                            <span data-fr="Gratuite" data-ar="مجاني">Gratuite</span>
                        </div>
                        <div class="total-row total-final">
                            <span data-fr="Total" data-ar="المجموع">Total:</span>
                            <span><?php echo number_format($cart_total, 0, ',', ' '); ?> DA</span>
                        </div>
                    </div>
                    
                    <div class="order-form">
                        <h3 data-fr="Informations de Livraison" data-ar="معلومات التوصيل">Informations de Livraison</h3>
                        <form method="POST">
                            <div class="form-group">
                                <label for="full_name" data-fr="Nom Complet" data-ar="الاسم الكامل">Nom Complet *</label>
                                <input type="text" id="full_name" name="full_name" required>
                            </div>
                            <div class="form-group">
                                <label for="email" data-fr="Email" data-ar="البريد الإلكتروني">Email *</label>
                                <input type="email" id="email" name="email" required>
                            </div>
                            <div class="form-group">
                                <label for="phone" data-fr="Téléphone" data-ar="الهاتف">Téléphone *</label>
                                <input type="tel" id="phone" name="phone" required>
                            </div>
                            <div class="form-group">
                                <label for="address" data-fr="Adresse de Livraison" data-ar="عنوان التوصيل">Adresse de Livraison *</label>
                                <textarea id="address" name="address" required></textarea>
                            </div>
                            <div class="form-group">
                                <label for="payment_method" data-fr="Méthode de Paiement" data-ar="طريقة الدفع">Méthode de Paiement *</label>
                                <select id="payment_method" name="payment_method" required>
                                    <option value="cash" data-fr="Paiement à la Livraison" data-ar="الدفع عند الاستلام">Paiement à la Livraison</option>
                                    <option value="credit_card" data-fr="Carte de Crédit" data-ar="بطاقة ائتمان">Carte de Crédit</option>
                                </select>
                            </div>
                            <button type="submit" name="submit_order" class="submit-order" data-fr="Passer la Commande" data-ar="تأكيد الطلب">
                                Passer la Commande
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="empty-cart">
                        <i class="fas fa-shopping-cart"></i>
                        <h3 data-fr="Votre panier est vide" data-ar="سلة التسوق فارغة">Votre panier est vide</h3>
                        <p data-fr="Ajoutez des produits pour les voir ici" data-ar="أضف منتجات لتراها هنا">Ajoutez des produits pour les voir ici</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Wishlist Modal -->
    <div id="wishlistModal" class="wishlist-modal">
        <div class="wishlist-modal-content">
            <div class="wishlist-modal-header">
                <h2 data-fr="Vos Produits Favoris" data-ar="منتجاتك المفضلة">Vos Produits Favoris</h2>
                <button class="close-wishlist" onclick="closeWishlistModal()">&times;</button>
            </div>
            <div class="wishlist-modal-body">
                <?php if (count($wishlist_products) > 0): ?>
                    <div class="wishlist-items">
                        <?php foreach($wishlist_products as $product): ?>
                        <div class="wishlist-item">
                            <img src="<?php echo $product['image_url'] ?: 'https://via.placeholder.com/80x80?text=Produit'; ?>" alt="<?php echo htmlspecialchars($product['name_fr']); ?>" class="wishlist-item-image">
                            <div class="wishlist-item-details">
                                <div class="wishlist-item-name"><?php echo htmlspecialchars($product['name_fr']); ?></div>
                                <div class="wishlist-item-price"><?php echo number_format($product['price'], 0, ',', ' '); ?> DA</div>
                            </div>
                            <div class="wishlist-item-actions">
                                <form method="POST" style="display: flex; gap: 10px; align-items: center;">
                                    <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                    <button type="submit" name="move_to_cart" class="move-to-cart-btn" data-fr="Ajouter au Panier" data-ar="أضف إلى السلة">
                                        <i class="fas fa-shopping-cart"></i> Ajouter
                                    </button>
                                    <button type="submit" name="remove_from_wishlist" class="remove-item" title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-wishlist">
                        <i class="fas fa-heart"></i>
                        <h3 data-fr="Votre liste de souhaits est vide" data-ar="قائمة أمنياتك فارغة">Votre liste de souhaits est vide</h3>
                        <p data-fr="Ajoutez des produits à vos favoris pour les voir ici" data-ar="أضف منتجات إلى المفضلة لتراها هنا">Ajoutez des produits à vos favoris pour les voir ici</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
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
                    <li><a href="mainpage.html" data-fr="Accueil" data-ar="الرئيسية">Accueil</a></li>
                    <li><a href="produit.php" data-fr="Produits" data-ar="المنتجات">Produits</a></li>
                    <li><a href="#categories" data-fr="Catégories" data-ar="الفئات">Catégories</a></li>
                    <li><a href="#about" data-fr="À propos" data-ar="من نحن">À propos</a></li>
                    <li><a href="#contact" data-fr="Contact" data-ar="اتصل بنا">Contact</a></li>
                </ul>
            </div>
            
            <div class="footer-column">
                <h3 data-fr="Informations" data-ar="معلومات">Informations</h3>
                <ul>
                    <li><a href="#" data-fr="Livraison & Retours" data-ar="الشحن والمرتجعات">Livraison & Retours</a></li>
                    <li><a href="#" data-fr="Politique de Confidentialité" data-ar="سياسة الخصوصية">Politique de Confidentialité</a></li>
                    <li><a href="#" data-fr="Conditions Générales" data-ar="الشروط والأحكام">Conditions Générales</a></li>
                    <li><a href="#" data-fr="FAQ" data-ar="أسئلة شائعة">FAQ</a></li>
                </ul>
            </div>
            
            <div class="footer-column">
                <h3 data-fr="Contactez-nous" data-ar="اتصل بنا">Contactez-nous</h3>
                <ul>
                    <li data-fr="Salah Bey Mall UV 07 El Khroub,Constantine" data-ar="Salah Bey Mall UV 07 El Khroub,Constantine">Salah Bey Mall UV 07 El Khroub,Constantine</li>
                    <li>contact@cosmetiquesimou.com</li>
                    <li data-fr=" 0699 50 60 60" data-ar=" 0699 50 60 60"> 0699 50 60 60</li>
                    <li data-fr="Lundi-Vendredi: 9h-18h" data-ar="الإثنين-الجمعة: 9 صباحًا - 6 مساءً">Lundi-Vendredi: 9h-18h</li>
                </ul>
            </div>
        </div>
        
        <div class="copyright">
            <p>&copy; <span data-fr="2023 Cosmetique SIMOU. Tous droits réservés." data-ar="2023 كوزماتيك سيمو. جميع الحقوق محفوظة.">2023 Cosmetique SIMOU. Tous droits réservés.</span></p>
        </div>
    </footer>

    <div id="toast" class="toast"></div>

    <script>
        // Header scroll effect
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
        
        // Language switcher
        function changeLanguage(lang) {
            document.body.dir = lang === 'ar' ? 'rtl' : 'ltr';
            document.querySelectorAll('[data-fr], [data-ar]').forEach(element => {
                if (element.hasAttribute('data-' + lang)) {
                    if (element.tagName === 'INPUT' && element.hasAttribute('data-' + lang + '-placeholder')) {
                        element.placeholder = element.getAttribute('data-' + lang + '-placeholder');
                    } else {
                        element.textContent = element.getAttribute('data-' + lang);
                    }
                }
            });
            
            // Update active language button
            document.querySelectorAll('.language-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
        }
        
        // Enhanced filter section toggling with animation
        document.querySelectorAll('.filter-section h3').forEach(header => {
            header.addEventListener('click', function() {
                const section = this.parentElement;
                const isActive = section.classList.contains('active');
                
                // Close all other sections
                if (!isActive) {
                    document.querySelectorAll('.filter-section').forEach(s => {
                        if (s !== section) {
                            s.classList.remove('active');
                        }
                    });
                }
                
                section.classList.toggle('active');
            });
        });

        // Price range interaction
        const priceRange = document.getElementById('priceRange');
        const minPrice = document.getElementById('minPrice');
        const maxPrice = document.getElementById('maxPrice');
        
        priceRange.addEventListener('input', function() {
            maxPrice.value = this.value;
        });
        
        minPrice.addEventListener('change', function() {
            if (parseInt(this.value) > parseInt(maxPrice.value)) {
                this.value = maxPrice.value;
            }
            priceRange.min = this.value;
        });
        
        maxPrice.addEventListener('change', function() {
            if (parseInt(this.value) < parseInt(minPrice.value)) {
                this.value = minPrice.value;
            }
            priceRange.value = this.value;
        });

        // Update sort parameter
        function updateSort(sortValue) {
            const url = new URL(window.location.href);
            url.searchParams.set('sort', sortValue);
            window.location.href = url.toString();
        }

        // Show toast notification
        function showToast(message) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.classList.add('show');
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // Cart Modal Functions
        function showCartModal() {
            document.getElementById('cartModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeCartModal() {
            document.getElementById('cartModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Wishlist Modal Functions
        function showWishlistModal() {
            document.getElementById('wishlistModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeWishlistModal() {
            document.getElementById('wishlistModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            const cartModal = document.getElementById('cartModal');
            const wishlistModal = document.getElementById('wishlistModal');
            
            if (event.target === cartModal) {
                closeCartModal();
            }
            if (event.target === wishlistModal) {
                closeWishlistModal();
            }
        });

        // Auto-submit filter form when price inputs change
        document.getElementById('minPrice').addEventListener('change', function() {
            document.getElementById('filter-form').submit();
        });
        
        document.getElementById('maxPrice').addEventListener('change', function() {
            document.getElementById('filter-form').submit();
        });

        // Show confirmation for add to cart
        document.querySelectorAll('form[method="POST"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (this.querySelector('button[name="add_to_cart"]')) {
                    const productName = this.querySelector('input[name="product_name"]').value;
                    showToast('"' + productName + '" ajouté au panier!');
                }
                if (this.querySelector('button[name="move_to_cart"]')) {
                    const productName = this.closest('.wishlist-item').querySelector('.wishlist-item-name').textContent;
                    showToast('"' + productName + '" déplacé vers le panier!');
                }
            });
        });

        // Show success message if order was placed
        <?php if (isset($_SESSION['order_success'])): ?>
        document.addEventListener('DOMContentLoaded', function() {
            showToast('<?php echo $_SESSION['order_success']; ?>');
            <?php unset($_SESSION['order_success']); ?>
        });
        <?php endif; ?>
    </script>
</body>
</html>