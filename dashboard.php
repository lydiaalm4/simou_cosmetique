<?php
// Database connection
$host = 'localhost';
$dbname = 'oussama';
$username = 'root'; // Change to your database username
$password = 'admine'; // Change to your database password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");
} catch (PDOException $e) {
    die("Could not connect to the database: " . $e->getMessage());
}

// Handle search functionality
$searchTerm = '';
$searchResults = [];
$searchActive = false;

if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $searchTerm = trim($_GET['search']);
    $searchActive = true;
    
    try {
        // Search in orders
        $stmt = $pdo->prepare("
            SELECT o.order_id, o.order_number, o.total_amount, o.status, o.created_at, 
                   c.first_name, c.last_name, c.email, c.phone
            FROM orders o 
            LEFT JOIN customers c ON o.user_id = c.customer_id 
            WHERE o.order_number LIKE :search 
               OR c.first_name LIKE :search 
               OR c.last_name LIKE :search 
               OR c.email LIKE :search
            ORDER BY o.created_at DESC
        ");
        $searchParam = "%$searchTerm%";
        $stmt->bindParam(':search', $searchParam);
        $stmt->execute();
        $orderResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Search in products
        $stmt = $pdo->prepare("
            SELECT p.product_id, p.name_fr, p.name_ar, p.price, p.description_fr,
                   pc.name as category_name,
                   (SELECT image_url FROM product_images WHERE product_id = p.product_id LIMIT 1) as image_url
            FROM products p
            LEFT JOIN product_categories pc ON p.category_id = pc.category_id
            WHERE p.name_fr LIKE :search 
               OR p.name_ar LIKE :search 
               OR p.description_fr LIKE :search
            ORDER BY p.product_id DESC
        ");
        $stmt->bindParam(':search', $searchParam);
        $stmt->execute();
        $productResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Search in customers
        $stmt = $pdo->prepare("
            SELECT customer_id, first_name, last_name, email, phone, registration_date
            FROM customers
            WHERE first_name LIKE :search 
               OR last_name LIKE :search 
               OR email LIKE :search 
               OR phone LIKE :search
            ORDER BY registration_date DESC
        ");
        $stmt->bindParam(':search', $searchParam);
        $stmt->execute();
        $customerResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Combine all search results
        $searchResults = [
            'orders' => $orderResults,
            'products' => $productResults,
            'customers' => $customerResults
        ];
        
    } catch (PDOException $e) {
        // Handle error
        error_log("Search error: " . $e->getMessage());
    }
}

// Fetch data from database for dashboard (only if not searching)
if (!$searchActive) {
    $totalSales = 0;
    $newOrders = 0;
    $newCustomers = 0;
    $totalRevenue = 0;

    // Calculate total sales (sum of all orders)
    try {
        $stmt = $pdo->query("SELECT SUM(total_amount) as total FROM orders WHERE status = 'completed'");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalSales = $result['total'] ?? 0;
    } catch (PDOException $e) {
        // Handle error
    }

    // Count new orders (pending status)
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $newOrders = $result['count'] ?? 0;
    } catch (PDOException $e) {
        // Handle error
    }

    // Count new customers (registered this month)
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM customers WHERE registration_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $newCustomers = $result['count'] ?? 0;
    } catch (PDOException $e) {
        // Handle error
    }

    // Calculate total revenue (sum of all completed orders)
    try {
        $stmt = $pdo->query("SELECT SUM(total_amount) as total FROM orders WHERE status = 'completed'");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalRevenue = $result['total'] ?? 0;
    } catch (PDOException $e) {
        // Handle error
    }

    // Get recent orders
    $recentOrders = [];
    try {
        $stmt = $pdo->query("SELECT o.order_id, o.order_number, o.total_amount, o.status, o.created_at, 
                             c.first_name, c.last_name 
                             FROM orders o 
                             LEFT JOIN customers c ON o.user_id = c.customer_id 
                             ORDER BY o.created_at DESC LIMIT 5");
        $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Handle error
    }

    // Get top products (mock data since we don't have sales data)
    $topProducts = [];
    try {
        $stmt = $pdo->query("SELECT p.product_id, p.name_fr, p.name_ar, p.price, pc.name as category_name, 
                             (SELECT image_url FROM product_images WHERE product_id = p.product_id LIMIT 1) as image_url
                             FROM products p
                             LEFT JOIN product_categories pc ON p.category_id = pc.category_id
                             ORDER BY p.product_id DESC LIMIT 4");
        $topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add mock sales data
        foreach ($topProducts as &$product) {
            $product['sales'] = rand(10, 25);
            $product['revenue'] = $product['sales'] * $product['price'];
        }
    } catch (PDOException $e) {
        // Handle error
    }
}

// Get recent activities (mock data since we don't have activity data)
$recentActivities = [
    [
        'time' => 'Aujourd\'hui, 10:45',
        'title' => 'Nouvelle commande',
        'description' => 'de Amel Bouchareb (#SIM-2541)'
    ],
    [
        'time' => 'Aujourd\'hui, 09:30',
        'title' => 'Commande complétée',
        'description' => '(#SIM-2538)'
    ],
    [
        'time' => 'Hier, 16:20',
        'title' => 'Nouveau produit ajouté',
        'description' => 'Sérum Hydratant'
    ],
    [
        'time' => 'Hier, 14:05',
        'title' => 'Nouveau client enregistré',
        'description' => 'Karim Belkacem'
    ]
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - Cosmetique SIMOU</title>
    <link rel="icon" href="simou.jpg" type="image/jpg" sizes="512x512">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Montserrat:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        :root {
            --primary: #e83e8c;
            --secondary: #333333;
            --accent: #fedae6;
            --light: #fedae6;
            --success: #28a745;
            --warning: #ffc107;
            --info: #17a2b8;
            --dark: #343a40;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #f8f9fa;
            color: var(--secondary);
            overflow-x: hidden;
            transition: all 0.3s;
        }
        
        /* Dashboard Layout */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .dashboard-sidebar {
            width: 230px;
            background: white;
            box-shadow: 5px 0 25px rgba(232, 62, 140, 0.1);
            position: fixed;
            height: 100vh;
            transition: all 0.3s ease;
            z-index: 1000;
            overflow-y: auto;
        }
        
        /* LTR sidebar positioning */
        body:not([dir="rtl"]) .dashboard-sidebar {
            left: 0;
            transform: translateX(-100%);
        }
        
        /* RTL sidebar positioning */
        body[dir="rtl"] .dashboard-sidebar {
            right: 0;
            transform: translateX(100%);
        }
        
        .dashboard-sidebar.active {
            transform: translateX(0);
        }
        
        .sidebar-header {
            padding: 25px;
            border-bottom: 1px solid rgba(232, 62, 140, 0.1);
            text-align: center;
            position: relative;
        }
        
        .sidebar-logo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary);
            margin-bottom: 15px;
        }
        
        .sidebar-title {
            font-family: 'Playfair Display', serif;
            color: var(--primary);
            font-size: 1.5rem;
        }
        
        .sidebar-menu {
            padding: 10px 0;
            
        }
        
        .menu-category {
            color: #888;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 15px 25px 10px;
            margin-top: 10px;
        }
        
        .menu-item {
            display: flex;
            align-items: center;
            padding: 12px 25px;
            color: var(--secondary);
            text-decoration: none;
            transition: all 0.3s;
            position: relative;
        }
        
        .menu-item:hover, .menu-item.active {
            background: var(--accent);
            color: var(--primary);
        }
        
        .menu-item.active::before {
            content: '';
            position: absolute;
            top: 0;
            height: 100%;
            width: 4px;
            background: var(--primary);
        }
        
        /* LTR active menu indicator */
        body:not([dir="rtl"]) .menu-item.active::before {
            left: 0;
        }
        
        /* RTL active menu indicator */
        body[dir="rtl"] .menu-item.active::before {
            right: 0;
        }
        
        .menu-icon {
            margin-right: 12px;
            font-size: 1.1rem;
            width: 24px;
            text-align: center;
        }
        
        /* RTL menu icon positioning */
        body[dir="rtl"] .menu-icon {
            margin-right: 0;
            margin-left: 12px;
        }
        
        .menu-text {
            font-weight: 500;
        }
        
        .menu-badge {
            margin-left: auto;
            background: var(--primary);
            color: white;
            font-size: 0.7rem;
            padding: 3px 8px;
            border-radius: 10px;
        }
        
        /* RTL badge positioning */
        body[dir="rtl"] .menu-badge {
            margin-left: 0;
            margin-right: auto;
        }
        
        /* Main Content */
        .dashboard-content {
            flex: 1;
            transition: all 0.3s;
            width: 100%;
        }
        
        /* LTR content positioning */
        body:not([dir="rtl"]) .dashboard-content {
            margin-left: 0;
        }
        
        /* RTL content positioning */
        body[dir="rtl"] .dashboard-content {
            margin-right: 0;
        }
        
        /* Top Navigation */
        .dashboard-topnav {
            background: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 100;
            
           
        }
        
        .menu-toggle {
            background: none;
            border: none;
            color: var(--secondary);
            font-size: 1.5rem;
            cursor: pointer;
            margin-right: 15px;
            display: none;
        }
        
        /* RTL menu toggle positioning */
        body[dir="rtl"] .menu-toggle {
            margin-right: 0;
            margin-left: 15px;
        }
        
        .search-container {
            position: relative;
            flex: 1;
            max-width: 400px;
            margin-right: 15px;
        }
        
        /* RTL search container margin */
        body[dir="rtl"] .search-container {
            margin-right: 0;
            margin-left: 15px;
        }
        
        .search-input {
            width: 100%;
            padding: 10px 15px 10px 40px;
            border-radius: 30px;
            border: 1px solid #ddd;
            font-family: 'Montserrat', sans-serif;
            transition: all 0.3s;
        }
        
        /* RTL search input padding */
        body[dir="rtl"] .search-input {
            padding: 10px 40px 10px 15px;
        }
        
        .search-input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(232, 62, 140, 0.2);
        }
        
        .search-icon {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            color: #888;
        }
        
        /* LTR search icon positioning */
        body:not([dir="rtl"]) .search-icon {
            left: 15px;
        }
        
        /* RTL search icon positioning */
        body[dir="rtl"] .search-icon {
            right: 15px;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .language-toggle {
            display: flex;
            background: var(--accent);
            border-radius: 20px;
            overflow: hidden;
        }
        
        .language-btn {
            background: none;
            border: none;
            padding: 8px 15px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        
        .language-btn.active {
            background: var(--primary);
            color: white;
        }
        
        .notification-btn, .user-btn {
            background: none;
            border: none;
            color: var(--secondary);
            font-size: 1.2rem;
            cursor: pointer;
            position: relative;
        }
        
        .notification-btn:hover, .user-btn:hover {
            color: var(--primary);
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
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
        
        /* RTL notification badge positioning */
        body[dir="rtl"] .notification-badge {
            right: auto;
            left: -5px;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--accent);
        }
        
        .user-name {
            font-weight: 600;
            font-size: 0.9rem;
            white-space: nowrap;
        }
        
        .user-role {
            font-size: 0.7rem;
            color: #888;
            white-space: nowrap;
        }
        
        .user-dropdown {
            margin-left: 5px;
            font-size: 0.8rem;
        }
        
        /* RTL user dropdown positioning */
        body[dir="rtl"] .user-dropdown {
            margin-left: 0;
            margin-right: 5px;
        }
        
        /* Dashboard Cards */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            padding: 20px;
        }
        
        .card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(232, 62, 140, 0.15);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .card-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            color: var(--secondary);
            font-size: 0.9rem;
        }
        
        .card-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
        }
        
        .card-icon.sales {
            background: linear-gradient(135deg, #e83e8c, #ff6b9e);
        }
        
        .card-icon.orders {
            background: linear-gradient(135deg, #28a745, #5cb85c);
        }
        
        .card-icon.customers {
            background: linear-gradient(135deg, #17a2b8, #5bc0de);
        }
        
        .card-icon.revenue {
            background: linear-gradient(135deg, #ffc107, #ffd351);
        }
        
        .card-value {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            color: var(--secondary);
            margin-bottom: 5px;
        }
        
        .card-change {
            display: flex;
            align-items: center;
            font-size: 0.8rem;
        }
        
        .card-change i {
            font-size: 0.7rem;
            margin-right: 3px;
        }
        
        /* RTL change icon positioning */
        body[dir="rtl"] .card-change i {
            margin-right: 0;
            margin-left: 3px;
        }
        
        .card-change.positive {
            color: var(--success);
        }
        
        .card-change.negative {
            color: #dc3545;
        }
        
        /* Main Content Sections */
        .dashboard-sections {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            padding: 0 20px 20px;
        }
        
        .section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .section-title {
            font-family: 'Playfair Display', serif;
            color: var(--primary);
            font-size: 1.3rem;
        }
        
        .section-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.8rem;
        }
        
        .section-link:hover {
            text-decoration: underline;
        }
        
        /* Recent Orders Table */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .orders-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }
        
        .orders-table th {
            text-align: left;
            padding: 10px 12px;
            font-weight: 600;
            color: var(--secondary);
            border-bottom: 2px solid var(--accent);
            font-size: 0.8rem;
        }
        
        /* RTL table text alignment */
        body[dir="rtl"] .orders-table th,
        body[dir="rtl"] .orders-table td {
            text-align: right;
        }
        
        .orders-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #eee;
            font-size: 0.8rem;
        }
        
        .order-status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-processing {
            background: #cce5ff;
            color: #004085;
        }
        
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .order-actions {
            display: flex;
            gap: 8px;
        }
        
        .action-btn {
            background: none;
            border: none;
            color: #888;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        
        .action-btn:hover {
            color: var(--primary);
        }
        
        /* Top Products */
        .product-list {
            list-style: none;
        }
        
        .product-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        
        .product-item:last-child {
            border-bottom: none;
        }
        
        .product-img {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            object-fit: cover;
            margin-right: 12px;
        }
        
        /* RTL product image positioning */
        body[dir="rtl"] .product-img {
            margin-right: 0;
            margin-left: 12px;
        }
        
        .product-info {
            flex: 1;
            min-width: 0;
        }
        
        .product-name {
            font-weight: 600;
            margin-bottom: 3px;
            font-size: 0.9rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .product-category {
            font-size: 0.7rem;
            color: #888;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .product-stats {
            text-align: right;
            margin-left: 10px;
            white-space: nowrap;
        }
        
        /* RTL product stats positioning */
        body[dir="rtl"] .product-stats {
            text-align: left;
            margin-left: 0;
            margin-right: 10px;
        }
        
        .product-sales {
            font-weight: 600;
            color: var(--secondary);
            font-size: 0.8rem;
        }
        
        .product-revenue {
            font-size: 0.7rem;
            color: var(--primary);
        }
        
        /* Activity Timeline */
        .timeline {
            position: relative;
            padding-left: 25px;
            list-style: none;
        }
        
        /* RTL timeline positioning */
        body[dir="rtl"] .timeline {
            padding-left: 0;
            padding-right: 25px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--accent);
        }
        
        /* RTL timeline line positioning */
        body[dir="rtl"] .timeline::before {
            left: auto;
            right: 8px;
        }
        
        .timeline-item {
            position: relative;
            padding-bottom: 15px;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -25px;
            top: 4px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--primary);
            border: 2px solid white;
        }
        
        /* RTL timeline item positioning */
        body[dir="rtl"] .timeline-item::before {
            left: auto;
            right: -25px;
        }
        
        .timeline-time {
            font-size: 0.7rem;
            color: #888;
            margin-bottom: 3px;
        }
        
        .timeline-content {
            background: #f9f9f9;
            padding: 10px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
        }
        
        .timeline-content strong {
            color: var(--primary);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 15px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--accent);
        }
        
        .modal-title {
            font-family: 'Playfair Display', serif;
            color: var(--primary);
            font-size: 1.5rem;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #888;
            cursor: pointer;
        }
        
        .close-modal:hover {
            color: var(--primary);
        }
        
        /* Invoice Styles */
        .invoice-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .invoice-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--accent);
        }
        
        .invoice-logo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 15px;
            border: 3px solid var(--primary);
        }
        
        .invoice-title {
            font-family: 'Playfair Display', serif;
            color: var(--primary);
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .invoice-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .invoice-from, .invoice-to {
            padding: 15px;
            background: #f9f9f9;
            border-radius: 8px;
        }
        
        .invoice-items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        .invoice-items th {
            background: var(--primary);
            color: white;
            padding: 12px;
            text-align: left;
        }
        
        .invoice-items td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        
        .invoice-total {
            text-align: right;
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--primary);
        }
        
        .invoice-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        /* Status Update Form */
        .status-form {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-top: 10px;
        }
        
        .status-select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: 'Montserrat', sans-serif;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }
        
        /* Search Results Styles */
        .search-results {
            margin: 20px;
        }
        
        .search-section {
            margin-bottom: 30px;
        }
        
        .search-section-title {
            font-family: 'Playfair Display', serif;
            color: var(--primary);
            font-size: 1.3rem;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--accent);
        }
        
        .search-count {
            font-size: 0.9rem;
            color: #888;
            margin-bottom: 15px;
        }
        
        .no-results {
            text-align: center;
            padding: 30px;
            color: #888;
            font-style: italic;
        }
        
        .search-highlight {
            background-color: #fff3cd;
            padding: 2px 4px;
            border-radius: 3px;
            font-weight: 600;
        }
        
        /* Print Styles */
        @media print {
            body * {
                visibility: hidden;
            }
            .invoice-container, .invoice-container * {
                visibility: visible;
            }
            .invoice-container {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                box-shadow: none;
            }
            .invoice-actions {
                display: none;
            }
        }
        
        /* Responsive Design */
        @media (min-width: 992px) {
            /* Desktop styles */
            body:not([dir="rtl"]) .dashboard-sidebar {
                transform: translateX(0);
            }
            
            body[dir="rtl"] .dashboard-sidebar {
                transform: translateX(0);
            }
            
            body:not([dir="rtl"]) .dashboard-content {
                margin-left: 280px;
            }
            
            body[dir="rtl"] .dashboard-content {
                margin-right: 280px;
            }
            
            .dashboard-sections {
                grid-template-columns: 2fr 1fr;
            }
            
            .menu-toggle {
                display: none;
            }
        }
        
        @media (max-width: 768px) {
            /* Tablet styles */
            .dashboard-topnav {
                flex-wrap: wrap;
            }
            
            .search-container {
                order: 3;
                width: 100%;
                max-width: 100%;
                margin: 10px 0 0 0;
            }
            
            body[dir="rtl"] .search-container {
                margin: 10px 0 0 0;
            }
            
            .user-menu {
                margin-left: auto;
            }
            
            body[dir="rtl"] .user-menu {
                margin-left: 0;
                margin-right: auto;
            }
            
            .language-toggle {
                display: none;
            }
            
            .user-name, .user-role {
                display: none;
            }
        }
        
        @media (max-width: 576px) {
            /* Mobile styles */
            .dashboard-cards {
                grid-template-columns: 1fr;
            }
            
            .card-value {
                font-size: 1.8rem;
            }
            
            .menu-toggle {
                display: block;
            }
            
            .user-profile {
                gap: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="dashboard-sidebar">
            <div class="sidebar-header">
                <img src="simou.jpg" alt="SIMOU Logo" class="sidebar-logo">
                
            </div>
            
            <div class="sidebar-menu">
                <div class="menu-category" data-fr="Principal" data-ar="الرئيسي">Principal</div>
                <a href="dashboard.php" class="menu-item active">
                    <div class="menu-icon"><i class="fas fa-home"></i></div>
                    <div class="menu-text" data-fr="Tableau de Bord" data-ar="لوحة التحكم">Tableau de Bord</div>
                </a>
                
                <div class="menu-category" data-fr="Gestion" data-ar="الإدارة">Gestion</div>
                <a href="addproduit.php" class="menu-item">
                    <div class="menu-icon"><i class="fas fa-box-open"></i></div>
                    <div class="menu-text" data-fr="Produits" data-ar="المنتجات">Produits</div>
                    <div class="menu-badge">15</div>
                </a>
                <a href="commande.php" class="menu-item">
                    <div class="menu-icon"><i class="fas fa-shopping-cart"></i></div>
                    <div class="menu-text" data-fr="Commandes" data-ar="الطلبات">Commandes</div>
                    <div class="menu-badge"><?php echo $newOrders; ?></div>
                </a>
                <a href="clientsfideles.html" class="menu-item">
                    <div class="menu-icon"><i class="fas fa-users"></i></div>
                    <div class="menu-text" data-fr="Clients" data-ar="العملاء">Clients</div>
                </a>
                <div class="menu-category" data-fr="Analyse" data-ar="التحليل">Analyse</div>
                <a href="dashboard.php" class="menu-item">
                    <div class="menu-icon"><i class="fas fa-chart-line"></i></div>
                    <div class="menu-text" data-fr="Ventes" data-ar="المبيعات">Ventes</div>
                </a>
                <div class="menu-category" data-fr="Personnel" data-ar="الشخصي">Personnel</div>
                <a href="profil.php" class="menu-item ">
                    <div class="menu-icon"><i class="fas fa-user"></i></div>
                    <div class="menu-text" data-fr="Mon Profil" data-ar="ملفي الشخصي">Mon Profil</div>
                </a>
                </a>
                
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="dashboard-content">
            <!-- Top Navigation -->
            <nav class="dashboard-topnav">
                <button class="menu-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                
                <form method="GET" class="search-container" id="searchForm">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" name="search" class="search-input" placeholder="Rechercher..." 
                           data-fr-placeholder="Rechercher..." data-ar-placeholder="بحث..."
                           value="<?php echo htmlspecialchars($searchTerm); ?>">
                </form>
                
                <div class="user-menu">
                    <div class="language-toggle">
                        <button class="language-btn active" onclick="changeLanguage('fr')">FR</button>
                        <button class="language-btn" onclick="changeLanguage('ar')">AR</button>
                    </div>
                    
                    <button class="notification-btn">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge">3</span>
                    </button>
                    
                    <button class="user-btn">
                        <div class="user-profile">
                            <img src="simou.jpg" alt="User" class="user-avatar">
                            <div class="user-details">
                                <div class="user-name" data-fr="Oussama" data-ar="أسامة">Oussama </div>
                                <div class="user-role" data-fr="Administrateur" data-ar="مدير">Administrateur</div>
                            </div>
                            <i class="fas fa-chevron-down user-dropdown"></i>
                        </div>
                    </button>
                </div>
            </nav>
            
            <?php if ($searchActive): ?>
            <!-- Search Results -->
            <div class="search-results">
                <div class="section">
                    <div class="section-header">
                        <h3 class="section-title">Résultats de recherche pour "<?php echo htmlspecialchars($searchTerm); ?>"</h3>
                        <a href="?" class="section-link">Retour au tableau de bord</a>
                    </div>
                    
                    <!-- Orders Results -->
                    <div class="search-section">
                        <h4 class="search-section-title">Commandes (<?php echo count($searchResults['orders']); ?>)</h4>
                        <?php if (!empty($searchResults['orders'])): ?>
                            <div class="table-responsive">
                                <table class="orders-table">
                                    <thead>
                                        <tr>
                                            <th>N° Commande</th>
                                            <th>Client</th>
                                            <th>Date</th>
                                            <th>Montant</th>
                                            <th>Statut</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($searchResults['orders'] as $order): 
                                            $orderDate = new DateTime($order['created_at']);
                                            $formattedDate = $orderDate->format('d/m/Y');
                                            $customerName = $order['first_name'] . ' ' . $order['last_name'];
                                        ?>
                                        <tr>
                                            <td><?php echo highlightSearchTerm($order['order_number'], $searchTerm); ?></td>
                                            <td><?php echo highlightSearchTerm($customerName, $searchTerm); ?></td>
                                            <td><?php echo $formattedDate; ?></td>
                                            <td><?php echo number_format($order['total_amount'], 2, '.', ' '); ?> DA</td>
                                            <td>
                                                <?php 
                                                $statusClass = '';
                                                $statusText = '';
                                                switch ($order['status']) {
                                                    case 'pending':
                                                        $statusClass = 'status-pending';
                                                        $statusText = 'En attente';
                                                        break;
                                                    case 'processing':
                                                        $statusClass = 'status-processing';
                                                        $statusText = 'En cours';
                                                        break;
                                                    case 'completed':
                                                        $statusClass = 'status-completed';
                                                        $statusText = 'Complété';
                                                        break;
                                                    case 'cancelled':
                                                        $statusClass = 'status-cancelled';
                                                        $statusText = 'Annulé';
                                                        break;
                                                }
                                                ?>
                                                <span class="order-status <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                            </td>
                                            <td>
                                                <div class="order-actions">
                                                    <button class="action-btn" title="View" onclick="viewOrder(<?php echo $order['order_id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="action-btn" title="Print" onclick="printInvoice(<?php echo $order['order_id']; ?>)">
                                                        <i class="fas fa-print"></i>
                                                    </button>
                                                    <button class="action-btn" title="Edit Status" onclick="editStatus(<?php echo $order['order_id']; ?>, '<?php echo $order['status']; ?>')">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="no-results">Aucune commande trouvée</div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Products Results -->
                    <div class="search-section">
                        <h4 class="search-section-title">Produits (<?php echo count($searchResults['products']); ?>)</h4>
                        <?php if (!empty($searchResults['products'])): ?>
                            <ul class="product-list">
                                <?php foreach ($searchResults['products'] as $product): ?>
                                <li class="product-item">
                                    <img src="<?php echo $product['image_url'] ?: 'https://via.placeholder.com/50'; ?>" alt="<?php echo $product['name_fr']; ?>" class="product-img">
                                    <div class="product-info">
                                        <div class="product-name"><?php echo highlightSearchTerm($product['name_fr'], $searchTerm); ?></div>
                                        <div class="product-category"><?php echo $product['category_name']; ?></div>
                                        <div class="product-description" style="font-size: 0.7rem; color: #666; margin-top: 5px;">
                                            <?php 
                                            $description = $product['description_fr'] ?? '';
                                            if (!empty($description)) {
                                                echo highlightSearchTerm(substr($description, 0, 100) . (strlen($description) > 100 ? '...' : ''), $searchTerm);
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <div class="product-stats">
                                        <div class="product-price"><?php echo number_format($product['price'], 2, '.', ' '); ?> DA</div>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <div class="no-results">Aucun produit trouvé</div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Customers Results -->
                    <div class="search-section">
                        <h4 class="search-section-title">Clients (<?php echo count($searchResults['customers']); ?>)</h4>
                        <?php if (!empty($searchResults['customers'])): ?>
                            <div class="table-responsive">
                                <table class="orders-table">
                                    <thead>
                                        <tr>
                                            <th>Nom</th>
                                            <th>Email</th>
                                            <th>Téléphone</th>
                                            <th>Date d'inscription</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($searchResults['customers'] as $customer): 
                                            $regDate = new DateTime($customer['registration_date']);
                                            $formattedRegDate = $regDate->format('d/m/Y');
                                            $customerName = $customer['first_name'] . ' ' . $customer['last_name'];
                                        ?>
                                        <tr>
                                            <td><?php echo highlightSearchTerm($customerName, $searchTerm); ?></td>
                                            <td><?php echo highlightSearchTerm($customer['email'], $searchTerm); ?></td>
                                            <td><?php echo highlightSearchTerm($customer['phone'], $searchTerm); ?></td>
                                            <td><?php echo $formattedRegDate; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="no-results">Aucun client trouvé</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php else: ?>
            <!-- Normal Dashboard Content -->
            
            <!-- Dashboard Cards -->
            <div class="dashboard-cards">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title" data-fr="Ventes Totales" data-ar="إجمالي المبيعات">Ventes Totales</h3>
                        <div class="card-icon sales"><i class="fas fa-shopping-bag"></i></div>
                    </div>
                    <div class="card-value"><?php echo number_format($totalSales, 2, '.', ' '); ?> DA</div>
                    <div class="card-change positive">
                        <i class="fas fa-arrow-up"></i> 12% <span data-fr="vs mois dernier" data-ar="مقارنة بالشهر الماضي">vs mois dernier</span>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title" data-fr="Nouvelles Commandes" data-ar="طلبات جديدة">Nouvelles Commandes</h3>
                        <div class="card-icon orders"><i class="fas fa-clipboard-list"></i></div>
                    </div>
                    <div class="card-value"><?php echo $newOrders; ?></div>
                    <div class="card-change positive">
                        <i class="fas fa-arrow-up"></i> 5% <span data-fr="vs mois dernier" data-ar="مقارنة بالشهر الماضي">vs mois dernier</span>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title" data-fr="Nouveaux Clients" data-ar="عملاء جدد">Nouveaux Clients</h3>
                        <div class="card-icon customers"><i class="fas fa-users"></i></div>
                    </div>
                    <div class="card-value"><?php echo $newCustomers; ?></div>
                    <div class="card-change negative">
                        <i class="fas fa-arrow-down"></i> 2% <span data-fr="vs mois dernier" data-ar="مقارنة بالشهر الماضي">vs mois dernier</span>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title" data-fr="Revenu Total" data-ar="إجمالي الإيرادات">Revenu Total</h3>
                        <div class="card-icon revenue"><i class="fas fa-dollar-sign"></i></div>
                    </div>
                    <div class="card-value"><?php echo number_format($totalRevenue, 2, '.', ' '); ?> DA</div>
                    <div class="card-change positive">
                        <i class="fas fa-arrow-up"></i> 8% <span data-fr="vs mois dernier" data-ar="مقارنة بالشهر الماضي">vs mois dernier</span>
                    </div>
                </div>
            </div>
            
            <!-- Main Sections -->
            <div class="dashboard-sections">
                <!-- Recent Orders -->
                <section class="section">
                    <div class="section-header">
                        <h3 class="section-title" data-fr="Commandes Récentes" data-ar="الطلبات الأخيرة">Commandes Récentes</h3>
                        <a href="#" class="section-link" data-fr="Voir tout" data-ar="عرض الكل">Voir tout</a>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="orders-table">
                            <thead>
                                <tr>
                                    <th data-fr="N° Commande" data-ar="رقم الطلب">N° Commande</th>
                                    <th data-fr="Client" data-ar="العميل">Client</th>
                                    <th data-fr="Date" data-ar="التاريخ">Date</th>
                                    <th data-fr="Montant" data-ar="المبلغ">Montant</th>
                                    <th data-fr="Statut" data-ar="الحالة">Statut</th>
                                    <th data-fr="Actions" data-ar="إجراءات">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentOrders as $order): 
                                    $orderDate = new DateTime($order['created_at']);
                                    $formattedDate = $orderDate->format('d/m/Y');
                                    $customerName = $order['first_name'] . ' ' . $order['last_name'];
                                ?>
                                <tr>
                                    <td>#<?php echo $order['order_number']; ?></td>
                                    <td><?php echo $customerName; ?></td>
                                    <td><?php echo $formattedDate; ?></td>
                                    <td><?php echo number_format($order['total_amount'], 2, '.', ' '); ?> DA</td>
                                    <td>
                                        <?php 
                                        $statusClass = '';
                                        $statusText = '';
                                        switch ($order['status']) {
                                            case 'pending':
                                                $statusClass = 'status-pending';
                                                $statusText = 'En attente';
                                                break;
                                            case 'processing':
                                                $statusClass = 'status-processing';
                                                $statusText = 'En cours';
                                                break;
                                            case 'completed':
                                                $statusClass = 'status-completed';
                                                $statusText = 'Complété';
                                                break;
                                            case 'cancelled':
                                                $statusClass = 'status-cancelled';
                                                $statusText = 'Annulé';
                                                break;
                                        }
                                        ?>
                                        <span class="order-status <?php echo $statusClass; ?>" data-fr="<?php echo $statusText; ?>" data-ar="<?php 
                                            switch($statusText) {
                                                case 'En attente': echo 'قيد الانتظار'; break;
                                                case 'En cours': echo 'قيد المعالجة'; break;
                                                case 'Complété': echo 'مكتمل'; break;
                                                case 'Annulé': echo 'ملغى'; break;
                                            }
                                        ?>"><?php echo $statusText; ?></span>
                                    </td>
                                    <td>
                                        <div class="order-actions">
                                            <button class="action-btn" title="View" onclick="viewOrder(<?php echo $order['order_id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="action-btn" title="Print" onclick="printInvoice(<?php echo $order['order_id']; ?>)">
                                                <i class="fas fa-print"></i>
                                            </button>
                                            <button class="action-btn" title="Edit Status" onclick="editStatus(<?php echo $order['order_id']; ?>, '<?php echo $order['status']; ?>')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($recentOrders)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center;">Aucune commande récente</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
                
                <!-- Top Products -->
                <section class="section">
                    <div class="section-header">
                        <h3 class="section-title" data-fr="Produits Populaires" data-ar="المنتجات الشائعة">Produits Populaires</h3>
                        <a href="#" class="section-link" data-fr="Voir tout" data-ar="عرض الكل">Voir tout</a>
                    </div>
                    
                    <ul class="product-list">
                        <?php foreach ($topProducts as $product): ?>
                        <li class="product-item">
                            <img src="<?php echo $product['image_url'] ?: 'https://via.placeholder.com/50'; ?>" alt="<?php echo $product['name_fr']; ?>" class="product-img">
                            <div class="product-info">
                                <div class="product-name" data-fr="<?php echo $product['name_fr']; ?>" data-ar="<?php echo $product['name_ar']; ?>"><?php echo $product['name_fr']; ?></div>
                                <div class="product-category"><?php echo $product['category_name']; ?></div>
                            </div>
                            <div class="product-stats">
                                <div class="product-sales"><?php echo $product['sales']; ?> <span data-fr="ventes" data-ar="مبيع">ventes</span></div>
                                <div class="product-revenue"><?php echo number_format($product['revenue'], 2, '.', ' '); ?> DA</div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                        <?php if (empty($topProducts)): ?>
                        <li class="product-item" style="justify-content: center;">
                            Aucun produit disponible
                        </li>
                        <?php endif; ?>
                    </ul>
                </section>
            </div>
            
           
            
            <?php endif; ?>
        </main>
    </div>

    <!-- Invoice Modal -->
    <div id="invoiceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" data-fr="Facture" data-ar="فاتورة">Facture</h3>
                <button class="close-modal" onclick="closeModal('invoiceModal')">&times;</button>
            </div>
            <div id="invoiceContent">
                <!-- Invoice content will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3 class="modal-title" data-fr="Modifier le Statut" data-ar="تعديل الحالة">Modifier le Statut</h3>
                <button class="close-modal" onclick="closeModal('statusModal')">&times;</button>
            </div>
            <div id="statusContent">
                <!-- Status form will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        // Language switcher
        function changeLanguage(lang) {
            // Set document direction
            document.body.dir = lang === 'ar' ? 'rtl' : 'ltr';
            
            // Update all elements with data attributes
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
            
            // Close sidebar on mobile after language change
            if (window.innerWidth < 992) {
                toggleSidebar();
            }
        }
        
        // Toggle sidebar on mobile
        function toggleSidebar() {
            const sidebar = document.querySelector('.dashboard-sidebar');
            sidebar.classList.toggle('active');
        }
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            const sidebar = document.querySelector('.dashboard-sidebar');
            const menuToggle = document.querySelector('.menu-toggle');
            
            if (window.innerWidth < 992 && 
                !sidebar.contains(e.target) && 
                !menuToggle.contains(e.target) && 
                sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
            }
        });

        // View Order Details
        function viewOrder(orderId) {
            fetch(`get_order_details.php?order_id=${orderId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayInvoice(data.order, data.items);
                        openModal('invoiceModal');
                    } else {
                        alert('Erreur lors du chargement des détails de la commande');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Erreur lors du chargement des détails de la commande');
                });
        }

        // Print Invoice
        function printInvoice(orderId) {
            fetch(`get_order_details.php?order_id=${orderId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const invoiceHtml = generateInvoiceHtml(data.order, data.items, true);
                        const printWindow = window.open('', '_blank');
                        printWindow.document.write(`
                            <!DOCTYPE html>
                            <html>
                            <head>
                                <title>Facture #${data.order.order_number}</title>
                                <style>
                                    body { font-family: Arial, sans-serif; margin: 20px; }
                                    .invoice-header { text-align: center; margin-bottom: 30px; }
                                    .invoice-details { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
                                    .invoice-items { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
                                    .invoice-items th { background: #e83e8c; color: white; padding: 10px; }
                                    .invoice-items td { padding: 10px; border-bottom: 1px solid #ddd; }
                                    .invoice-total { text-align: right; font-size: 1.2rem; font-weight: bold; }
                                </style>
                            </head>
                            <body>
                                ${invoiceHtml}
                            </body>
                            </html>
                        `);
                        printWindow.document.close();
                        printWindow.print();
                    } else {
                        alert('Erreur lors du chargement de la facture');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Erreur lors du chargement de la facture');
                });
        }

        // Edit Order Status
        function editStatus(orderId, currentStatus) {
            const statusContent = document.getElementById('statusContent');
            statusContent.innerHTML = `
                <form class="status-form" onsubmit="updateOrderStatus(event, ${orderId})">
                    <select class="status-select" name="status" required>
                        <option value="pending" ${currentStatus === 'pending' ? 'selected' : ''}>En attente</option>
                        <option value="processing" ${currentStatus === 'processing' ? 'selected' : ''}>En cours</option>
                        <option value="completed" ${currentStatus === 'completed' ? 'selected' : ''}>Complété</option>
                        <option value="cancelled" ${currentStatus === 'cancelled' ? 'selected' : ''}>Annulé</option>
                    </select>
                    <button type="submit" class="btn btn-success btn-sm">Mettre à jour</button>
                </form>
            `;
            openModal('statusModal');
        }

        // Update Order Status
        function updateOrderStatus(event, orderId) {
            event.preventDefault();
            const formData = new FormData(event.target);
            const status = formData.get('status');

            fetch('update_order_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `order_id=${orderId}&status=${status}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Statut mis à jour avec succès');
                    closeModal('statusModal');
                    location.reload(); // Refresh the page to show updated status
                } else {
                    alert('Erreur lors de la mise à jour du statut');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Erreur lors de la mise à jour du statut');
            });
        }

        // Display Invoice in Modal
        function displayInvoice(order, items) {
            const invoiceContent = document.getElementById('invoiceContent');
            invoiceContent.innerHTML = generateInvoiceHtml(order, items, false);
        }

        // Generate Invoice HTML
        function generateInvoiceHtml(order, items, isForPrint = false) {
            const orderDate = new Date(order.created_at);
            const formattedDate = orderDate.toLocaleDateString('fr-FR');
            
            let itemsHtml = '';
            items.forEach(item => {
                itemsHtml += `
                    <tr>
                        <td>${item.name_fr}</td>
                        <td>${item.quantity}</td>
                        <td>${parseFloat(item.unit_price).toFixed(2)} DA</td>
                        <td>${parseFloat(item.total_price).toFixed(2)} DA</td>
                    </tr>
                `;
            });

            const actionsHtml = isForPrint ? '' : `
                <div class="invoice-actions">
                    <button class="btn btn-secondary" onclick="closeModal('invoiceModal')">Fermer</button>
                    <button class="btn btn-primary" onclick="downloadInvoice(${order.order_id})">Télécharger PDF</button>
                    <button class="btn btn-success" onclick="printInvoice(${order.order_id})">Imprimer</button>
                </div>
            `;

            return `
                <div class="invoice-container">
                    <div class="invoice-header">
                        <img src="https://scontent.falg7-1.fna.fbcdn.net/v/t39.30808-1/439901533_122139769340229532_6761088317019887005_n.jpg?stp=dst-jpg_s160x160_tt6&_nc_cat=105&ccb=1-7&_nc_sid=2d3e12&_nc_ohc=Fxb7aCvHXSEQ7kNvwFpfQBq&_nc_oc=AdmrbULiYNwMvE3foUArFV1d5RuEMRq15JyDfx5hs2kh0IMOtrwJEV12mKhEtffAZC8&_nc_zt=24&_nc_ht=scontent.falg7-1.fna&_nc_gid=UBb_oZYU8rv1dGl0gjUs2w&oh=00_AfTLEPFmCyMqmWwphOPmIHvU8z7BZ8_q9R3ZhzFPdZ3nAQ&oe=6874ABD5" 
                             alt="SIMOU Logo" class="invoice-logo">
                        <h1 class="invoice-title">COSMETIQUE SIMOU</h1>
                        <p>Votre partenaire beauté</p>
                    </div>
                    
                    <div class="invoice-details">
                        <div class="invoice-from">
                            <h3>De:</h3>
                            <p><strong>COSMETIQUE SIMOU</strong></p>
                            <p>Alger, Algérie</p>
                            <p>Tél: +213 XXX XXX XXX</p>
                            <p>Email: contact@cosmetique-simou.com</p>
                        </div>
                        
                        <div class="invoice-to">
                            <h3>À:</h3>
                            <p><strong>${order.first_name} ${order.last_name}</strong></p>
                            <p>${order.shipping_address || 'Adresse non spécifiée'}</p>
                            <p>Email: ${order.email || 'Non spécifié'}</p>
                            <p>Tél: ${order.phone || 'Non spécifié'}</p>
                        </div>
                    </div>
                    
                    <div class="invoice-info">
                        <p><strong>Facture #:</strong> ${order.order_number}</p>
                        <p><strong>Date:</strong> ${formattedDate}</p>
                        <p><strong>Statut:</strong> ${getStatusText(order.status)}</p>
                    </div>
                    
                    <table class="invoice-items">
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th>Quantité</th>
                                <th>Prix Unitaire</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${itemsHtml}
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" style="text-align: right;"><strong>Total:</strong></td>
                                <td class="invoice-total">${parseFloat(order.total_amount).toFixed(2)} DA</td>
                            </tr>
                        </tfoot>
                    </table>
                    
                    ${actionsHtml}
                </div>
            `;
        }

        // Get Status Text
        function getStatusText(status) {
            const statusMap = {
                'pending': 'En attente',
                'processing': 'En cours',
                'completed': 'Complété',
                'cancelled': 'Annulé'
            };
            return statusMap[status] || status;
        }

        // Download PDF
        function downloadInvoice(orderId) {
            const element = document.getElementById('invoiceContent');
            const opt = {
                margin: 1,
                filename: `facture-${orderId}.pdf`,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'cm', format: 'a4', orientation: 'portrait' }
            };
            
            html2pdf().set(opt).from(element).save();
        }

        // Modal Functions
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }

        // Initialize the dashboard
        document.addEventListener('DOMContentLoaded', function() {
            // Set default language to French
            changeLanguage('fr');
            
            // Show sidebar on desktop by default
            if (window.innerWidth >= 992) {
                document.querySelector('.dashboard-sidebar').classList.add('active');
            }
            
            // Handle window resize
            window.addEventListener('resize', function() {
                const sidebar = document.querySelector('.dashboard-sidebar');
                
                if (window.innerWidth >= 992) {
                    sidebar.classList.add('active');
                } else {
                    sidebar.classList.remove('active');
                }
            });
            
            // Auto-submit search form when user stops typing (with debounce)
            let searchTimeout;
            const searchInput = document.querySelector('.search-input');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        document.getElementById('searchForm').submit();
                    }, 500);
                });
            }
        });
    </script>
</body>
</html>

<?php
// Helper function to highlight search terms in results
function highlightSearchTerm($text, $searchTerm) {
    if (empty($searchTerm)) {
        return htmlspecialchars($text);
    }
    
    $pattern = '/' . preg_quote($searchTerm, '/') . '/i';
    $replacement = '<span class="search-highlight">$0</span>';
    
    return preg_replace($pattern, $replacement, htmlspecialchars($text));
}
?>