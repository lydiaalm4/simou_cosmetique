<?php
// Single file: orders_management.php
session_start();

// Database configuration
$host = 'localhost';
$dbname = 'oussama';
$username = 'root';
$password = 'admine';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_order_details':
            getOrderDetails($pdo);
            break;
        case 'update_order_status':
            updateOrderStatus($pdo);
            break;
        case 'get_order_data':
            getOrderData($pdo);
            break;
        case 'update_order':
            updateOrder($pdo);
            break;
        case 'delete_order':
            deleteOrder($pdo);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
    }
    exit;
}

// Function to get order details
function getOrderDetails($pdo) {
    if (!isset($_POST['id'])) {
        echo json_encode(['success' => false, 'message' => 'ID manquant']);
        return;
    }
    
    $orderId = $_POST['id'];
    
    // Get order details
    $sql = "
        SELECT o.*, 
               CONCAT(c.first_name, ' ', c.last_name) as customer_name,
               c.email, c.phone, c.address, c.city, c.postal_code, c.country,
               c.profile_image
        FROM orders o 
        LEFT JOIN customers c ON o.user_id = c.customer_id 
        WHERE o.order_id = ?
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Commande non trouvée']);
        return;
    }
    
    // Get order items
    $sql_items = "
        SELECT oi.*, p.name_fr as product_name, p.image_url, p.price
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.product_id
        WHERE oi.order_id = ?
    ";
    
    $stmt_items = $pdo->prepare($sql_items);
    $stmt_items->execute([$orderId]);
    $orderItems = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
    
    $orderDate = new DateTime($order['created_at']);
    $formattedDate = $orderDate->format('d/m/Y à H:i');
    $totalAmount = number_format($order['total_amount'], 2, ',', ' ');
    
    $statusText = [
        'pending' => 'En attente',
        'processing' => 'En cours', 
        'completed' => 'Complété',
        'cancelled' => 'Annulé'
    ];
    
    $paymentText = [
        'pending' => 'En attente',
        'paid' => 'Payé',
        'failed' => 'Échoué'
    ];
    
    $html = '
    <div class="order-details">
        <div class="order-details-item">
            <span class="order-details-label">N° Commande:</span>
            <span class="order-details-value">' . htmlspecialchars($order['order_number']) . '</span>
        </div>
        <div class="order-details-item">
            <span class="order-details-label">Date:</span>
            <span class="order-details-value">' . $formattedDate . '</span>
        </div>
        <div class="order-details-item">
            <span class="order-details-label">Client:</span>
            <span class="order-details-value">' . htmlspecialchars($order['customer_name'] ?? 'Client non enregistré') . '</span>
        </div>
        <div class="order-details-item">
            <span class="order-details-label">Email:</span>
            <span class="order-details-value">' . htmlspecialchars($order['email'] ?? 'N/A') . '</span>
        </div>
        <div class="order-details-item">
            <span class="order-details-label">Téléphone:</span>
            <span class="order-details-value">' . htmlspecialchars($order['phone'] ?? 'N/A') . '</span>
        </div>
        <div class="order-details-item">
            <span class="order-details-label">Adresse:</span>
            <span class="order-details-value">';
    
    $address = [];
    if ($order['address']) $address[] = $order['address'];
    if ($order['city']) $address[] = $order['city'];
    if ($order['postal_code']) $address[] = $order['postal_code'];
    if ($order['country']) $address[] = $order['country'];
    $html .= htmlspecialchars(implode(', ', $address) ?: 'N/A');
    
    $html .= '</span>
        </div>
        <div class="order-details-item">
            <span class="order-details-label">Statut:</span>
            <span class="order-details-value">
                <span class="order-status status-' . $order['status'] . '">' . $statusText[$order['status']] . '</span>
            </span>
        </div>
        <div class="order-details-item">
            <span class="order-details-label">Paiement:</span>
            <span class="order-details-value">
                <span class="order-status status-' . $order['payment_status'] . '">' . $paymentText[$order['payment_status']] . '</span>
            </span>
        </div>
        <div class="order-details-item">
            <span class="order-details-label">Méthode de paiement:</span>
            <span class="order-details-value">' . htmlspecialchars($order['payment_method'] ?? 'N/A') . '</span>
        </div>
        <div class="order-details-item">
            <span class="order-details-label">Notes:</span>
            <span class="order-details-value">' . nl2br(htmlspecialchars($order['notes'] ?? 'Aucune')) . '</span>
        </div>
    </div>
    
    <div class="order-products">
        <h4>Produits</h4>';
    
    foreach ($orderItems as $item) {
        $html .= '
        <div class="order-product">
            <img src="' . htmlspecialchars($item['image_url'] ?? 'https://via.placeholder.com/50') . '" alt="Product" class="order-product-img">
            <div class="order-product-info">
                <div class="order-product-name">' . htmlspecialchars($item['product_name']) . '</div>
                <div class="order-product-price">' . number_format($item['unit_price'], 2, ',', ' ') . ' DA</div>
                <div class="order-product-quantity">Quantité: ' . $item['quantity'] . '</div>
                <div class="order-product-total">Total: ' . number_format($item['total_price'], 2, ',', ' ') . ' DA</div>
            </div>
        </div>';
    }
    
    $html .= '
    </div>
    
    <div class="order-summary">
        <div class="order-summary-row order-summary-total">
            <span>Total:</span>
            <span>' . $totalAmount . ' DA</span>
        </div>
    </div>';
    
    echo json_encode(['success' => true, 'html' => $html]);
}

// Function to get order data for editing
function getOrderData($pdo) {
    if (!isset($_POST['id'])) {
        echo json_encode(['success' => false, 'message' => 'ID manquant']);
        return;
    }
    
    $orderId = $_POST['id'];
    
    $sql = "SELECT * FROM orders WHERE order_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Commande non trouvée']);
        return;
    }
    
    echo json_encode(['success' => true, 'order' => $order]);
}

// Function to update order status
function updateOrderStatus($pdo) {
    if (!isset($_POST['id']) || !isset($_POST['status'])) {
        echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
        return;
    }
    
    $orderId = $_POST['id'];
    $status = $_POST['status'];
    
    try {
        $sql = "UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$status, $orderId]);
        
        echo json_encode(['success' => true, 'message' => 'Statut mis à jour avec succès']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
}

// Function to update order
function updateOrder($pdo) {
    $required = ['id', 'order_number', 'status', 'payment_status', 'payment_method', 'total_amount'];
    foreach ($required as $field) {
        if (!isset($_POST[$field])) {
            echo json_encode(['success' => false, 'message' => 'Champ manquant: ' . $field]);
            return;
        }
    }
    
    $orderId = $_POST['id'];
    $orderNumber = $_POST['order_number'];
    $status = $_POST['status'];
    $paymentStatus = $_POST['payment_status'];
    $paymentMethod = $_POST['payment_method'];
    $totalAmount = $_POST['total_amount'];
    $shippingAddress = $_POST['shipping_address'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    try {
        // Check if order exists
        $checkSql = "SELECT order_id FROM orders WHERE order_id = ?";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([$orderId]);
        
        if (!$checkStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Commande non trouvée']);
            return;
        }
        
        // Update order
        $sql = "UPDATE orders SET 
                order_number = ?, 
                status = ?, 
                payment_status = ?, 
                payment_method = ?, 
                total_amount = ?, 
                shipping_address = ?, 
                notes = ?, 
                updated_at = NOW() 
                WHERE order_id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$orderNumber, $status, $paymentStatus, $paymentMethod, $totalAmount, $shippingAddress, $notes, $orderId]);
        
        echo json_encode(['success' => true, 'message' => 'Commande mise à jour avec succès']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
}

// Function to delete order
function deleteOrder($pdo) {
    if (!isset($_POST['id'])) {
        echo json_encode(['success' => false, 'message' => 'ID manquant']);
        return;
    }
    
    $orderId = $_POST['id'];
    
    try {
        $pdo->beginTransaction();
        
        // First delete order items
        $sql_items = "DELETE FROM order_items WHERE order_id = ?";
        $stmt_items = $pdo->prepare($sql_items);
        $stmt_items->execute([$orderId]);
        
        // Then delete the order
        $sql = "DELETE FROM orders WHERE order_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$orderId]);
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Commande supprimée avec succès']);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
}

// Get all orders for display
$sql = "
    SELECT o.*, 
           CONCAT(c.first_name, ' ', c.last_name) as customer_name,
           c.email, c.phone, c.profile_image
    FROM orders o 
    LEFT JOIN customers c ON o.user_id = c.customer_id 
    ORDER BY o.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get customers for edit form
$sql_customers = "SELECT customer_id, CONCAT(first_name, ' ', last_name) as name FROM customers ORDER BY first_name";
$stmt_customers = $pdo->prepare($sql_customers);
$stmt_customers->execute();
$customers = $stmt_customers->fetchAll(PDO::FETCH_ASSOC);

// Get users for edit form
$sql_users = "SELECT user_id, CONCAT(first_name, ' ', last_name) as name FROM users ORDER BY first_name";
$stmt_users = $pdo->prepare($sql_users);
$stmt_users->execute();
$users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Commandes - Cosmetique SIMOU</title>
    <link rel="icon" href="simou.jpg" type="image/jpg" sizes="512x512">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Montserrat:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #e83e8c;
            --secondary: #333333;
            --accent: #fedae6;
            --light: #fedae6;
            --success: #28a745;
            --warning: #ffc107;
            --info: #17a2b8;
            --danger: #dc3545;
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
        
        body:not([dir="rtl"]) .dashboard-sidebar {
            left: 0;
            transform: translateX(-100%);
        }
        
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
        
        body:not([dir="rtl"]) .menu-item.active::before {
            left: 0;
        }
        
        body[dir="rtl"] .menu-item.active::before {
            right: 0;
        }
        
        .menu-icon {
            margin-right: 12px;
            font-size: 1.1rem;
            width: 24px;
            text-align: center;
        }
        
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
        
        body:not([dir="rtl"]) .dashboard-content {
            margin-left: 0;
        }
        
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
        
        body:not([dir="rtl"]) .search-icon {
            left: 15px;
        }
        
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
        
        body[dir="rtl"] .user-dropdown {
            margin-left: 0;
            margin-right: 5px;
        }
        
        /* Page Header */
        .page-header {
            padding: 20px;
            background: white;
            margin: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-title {
            font-family: 'Playfair Display', serif;
            color: var(--primary);
            font-size: 1.5rem;
        }
        
        .page-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 30px;
            border: none;
            font-family: 'Montserrat', sans-serif;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: #d62e7c;
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-outline:hover {
            background: var(--accent);
        }
        
        /* Orders Table */
        .orders-container {
            margin: 0 20px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            padding: 20px;
        }
        
        .table-filters {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filter-label {
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--secondary);
        }
        
        .filter-select {
            padding: 8px 15px;
            border-radius: 20px;
            border: 1px solid #ddd;
            font-family: 'Montserrat', sans-serif;
            font-size: 0.8rem;
            min-width: 150px;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .orders-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }
        
        .orders-table th {
            text-align: left;
            padding: 12px 15px;
            font-weight: 600;
            color: var(--secondary);
            border-bottom: 2px solid var(--accent);
            font-size: 0.8rem;
        }
        
        body[dir="rtl"] .orders-table th,
        body[dir="rtl"] .orders-table td {
            text-align: right;
        }
        
        .orders-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            font-size: 0.8rem;
            vertical-align: middle;
        }
        
        .order-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            text-align: center;
            min-width: 80px;
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
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.8rem;
        }
        
        .action-btn.view {
            background: var(--info);
            color: white;
        }
        
        .action-btn.edit {
            background: var(--warning);
            color: white;
        }
        
        .action-btn.delete {
            background: var(--danger);
            color: white;
        }
        
        .action-btn.confirm {
            background: var(--success);
            color: white;
        }
        
        .action-btn:hover {
            opacity: 0.8;
        }
        
        .order-client {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .client-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .client-name {
            font-weight: 500;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 5px 30px rgba(0,0,0,0.3);
            animation: modalFadeIn 0.3s;
        }
        
        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            font-family: 'Playfair Display', serif;
            color: var(--primary);
            font-size: 1.2rem;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #888;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            font-size: 0.8rem;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: 'Montserrat', sans-serif;
            font-size: 0.9rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .form-select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: 'Montserrat', sans-serif;
            font-size: 0.9rem;
            background-color: white;
        }
        
        .form-select:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        /* Order Details */
        .order-details {
            margin-bottom: 20px;
        }
        
        .order-details-item {
            display: flex;
            margin-bottom: 10px;
        }
        
        .order-details-label {
            font-weight: 500;
            min-width: 150px;
            color: #666;
        }
        
        .order-details-value {
            flex: 1;
        }
        
        .order-products {
            margin-top: 20px;
        }
        
        .order-product {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .order-product:last-child {
            border-bottom: none;
        }
        
        .order-product-img {
            width: 50px;
            height: 50px;
            border-radius: 5px;
            object-fit: cover;
            margin-right: 15px;
        }
        
        .order-product-info {
            flex: 1;
        }
        
        .order-product-name {
            font-weight: 500;
            margin-bottom: 3px;
        }
        
        .order-product-price {
            color: var(--primary);
            font-weight: 600;
        }
        
        .order-product-quantity {
            color: #666;
            font-size: 0.8rem;
        }
        
        .order-product-total {
            font-weight: 600;
            color: #333;
        }
        
        .order-summary {
            margin-top: 20px;
            background: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
        }
        
        .order-summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .order-summary-total {
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--primary);
            border-top: 1px solid #ddd;
            padding-top: 10px;
            margin-top: 10px;
        }
        
        @media (min-width: 992px) {
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
            
            .menu-toggle {
                display: none;
            }
        }
        
        @media (max-width: 768px) {
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
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .table-filters {
                flex-direction: column;
                align-items: flex-start;
            }
        }
        
        @media (max-width: 576px) {
            .menu-toggle {
                display: block;
            }
            
            .user-profile {
                gap: 5px;
            }
            
            .action-btn {
                width: 25px;
                height: 25px;
                font-size: 0.7rem;
            }
            
            .modal-content {
                width: 95%;
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
                <a href="dashboard.html" class="menu-item">
                    <div class="menu-icon"><i class="fas fa-home"></i></div>
                    <div class="menu-text" data-fr="Tableau de Bord" data-ar="لوحة التحكم">Tableau de Bord</div>
                </a>
                
                <div class="menu-category" data-fr="Gestion" data-ar="الإدارة">Gestion</div>
                <a href="addproduit.html" class="menu-item">
                    <div class="menu-icon"><i class="fas fa-box-open"></i></div>
                    <div class="menu-text" data-fr="Produits" data-ar="المنتجات">Produits</div>
                </a>
                <a href="commande.php" class="menu-item active">
                    <div class="menu-icon"><i class="fas fa-shopping-cart"></i></div>
                    <div class="menu-text" data-fr="Commandes" data-ar="الطلبات">Commandes</div>
                    <div class="menu-badge"><?php echo count($orders); ?></div>
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
                
                <div class="search-container">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" class="search-input" placeholder="Rechercher..." data-fr-placeholder="Rechercher..." data-ar-placeholder="بحث..." id="searchInput" onkeyup="searchOrders()">
                </div>
                
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
                                <div class="user-name" data-fr="Oussama" data-ar="oussama">Oussama</div>
                                <div class="user-role" data-fr="Administrateur" data-ar="مدير">Administrateur</div>
                            </div>
                            <i class="fas fa-chevron-down user-dropdown"></i>
                        </div>
                    </button>
                </div>
            </nav>
            
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title" data-fr="Gestion des Commandes" data-ar="إدارة الطلبات">Gestion des Commandes</h1>
                <div class="page-actions">
                    
                    
                </div>
            </div>
            
            <!-- Orders Table -->
            <div class="orders-container">
                <div class="table-filters">
                    <div class="filter-group">
                        <span class="filter-label" data-fr="Statut:" data-ar="الحالة:">Statut:</span>
                        <select class="filter-select" id="statusFilter" onchange="filterOrders()">
                            <option value="all" data-fr="Tous" data-ar="الكل">Tous</option>
                            <option value="pending" data-fr="En attente" data-ar="قيد الانتظار">En attente</option>
                            <option value="processing" data-fr="En cours" data-ar="قيد المعالجة">En cours</option>
                            <option value="completed" data-fr="Complété" data-ar="مكتمل">Complété</option>
                            <option value="cancelled" data-fr="Annulé" data-ar="ملغى">Annulé</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <span class="filter-label" data-fr="Date:" data-ar="التاريخ:">Date:</span>
                        <select class="filter-select" id="dateFilter" onchange="filterOrders()">
                            <option value="all" data-fr="Toutes" data-ar="الكل">Toutes</option>
                            <option value="today" data-fr="Aujourd'hui" data-ar="اليوم">Aujourd'hui</option>
                            <option value="week" data-fr="Cette semaine" data-ar="هذا الأسبوع">Cette semaine</option>
                            <option value="month" selected data-fr="Ce mois" data-ar="هذا الشهر">Ce mois</option>
                            <option value="year" data-fr="Cette année" data-ar="هذه السنة">Cette année</option>
                        </select>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="orders-table" id="ordersTable">
                        <thead>
                            <tr>
                                <th data-fr="N° Commande" data-ar="رقم الطلب">N° Commande</th>
                                <th data-fr="Client" data-ar="العميل">Client</th>
                                <th data-fr="Date" data-ar="التاريخ">Date</th>
                                <th data-fr="Montant" data-ar="المبلغ">Montant</th>
                                <th data-fr="Statut" data-ar="الحالة">Statut</th>
                                <th data-fr="Paiement" data-ar="الدفع">Paiement</th>
                                <th data-fr="Actions" data-ar="إجراءات">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="ordersTableBody">
                            <?php if (count($orders) > 0): ?>
                                <?php foreach ($orders as $order): ?>
                                    <?php
                                    $orderDate = new DateTime($order['created_at']);
                                    $formattedDate = $orderDate->format('d/m/Y');
                                    $totalAmount = number_format($order['total_amount'], 2, ',', ' ');
                                    $statusClass = 'status-' . $order['status'];
                                    $paymentStatusClass = $order['payment_status'] == 'paid' ? 'status-completed' : ($order['payment_status'] == 'pending' ? 'status-pending' : 'status-cancelled');
                                    
                                    $statusText = [
                                        'pending' => ['fr' => 'En attente', 'ar' => 'قيد الانتظار'],
                                        'processing' => ['fr' => 'En cours', 'ar' => 'قيد المعالجة'],
                                        'completed' => ['fr' => 'Complété', 'ar' => 'مكتمل'],
                                        'cancelled' => ['fr' => 'Annulé', 'ar' => 'ملغى']
                                    ];
                                    
                                    $paymentText = [
                                        'pending' => ['fr' => 'En attente', 'ar' => 'قيد الانتظار'],
                                        'paid' => ['fr' => 'Payé', 'ar' => 'مدفوع'],
                                        'failed' => ['fr' => 'Échoué', 'ar' => 'فشل']
                                    ];
                                    ?>
                                    <tr data-status="<?php echo $order['status']; ?>" data-date="<?php echo $order['created_at']; ?>" data-search="<?php echo strtolower(htmlspecialchars($order['order_number'] . ' ' . ($order['customer_name'] ?? '') . ' ' . $formattedDate . ' ' . $totalAmount)); ?>">
                                        <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                                        <td>
                                            <div class="order-client">
                                                <img src="<?php echo htmlspecialchars($order['profile_image'] ?? 'https://via.placeholder.com/30'); ?>" alt="Client" class="client-avatar">
                                                <span class="client-name"><?php echo htmlspecialchars($order['customer_name'] ?? 'Client non enregistré'); ?></span>
                                            </div>
                                        </td>
                                        <td><?php echo $formattedDate; ?></td>
                                        <td><?php echo $totalAmount; ?> DA</td>
                                        <td>
                                            <span class="order-status <?php echo $statusClass; ?>" 
                                                  data-fr="<?php echo $statusText[$order['status']]['fr']; ?>" 
                                                  data-ar="<?php echo $statusText[$order['status']]['ar']; ?>">
                                                <?php echo $statusText[$order['status']]['fr']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="order-status <?php echo $paymentStatusClass; ?>" 
                                                  data-fr="<?php echo $paymentText[$order['payment_status']]['fr']; ?>" 
                                                  data-ar="<?php echo $paymentText[$order['payment_status']]['ar']; ?>">
                                                <?php echo $paymentText[$order['payment_status']]['fr']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="order-actions">
                                                <button class="action-btn view" title="View" onclick="viewOrder(<?php echo $order['order_id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="action-btn edit" title="Edit" onclick="editOrder(<?php echo $order['order_id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($order['status'] != 'completed' && $order['status'] != 'cancelled'): ?>
                                                    <button class="action-btn confirm" title="Confirm" onclick="confirmOrder(<?php echo $order['order_id']; ?>)">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="action-btn delete" title="Delete" onclick="confirmDelete(<?php echo $order['order_id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 20px;">
                                        <p data-fr="Aucune commande trouvée" data-ar="لم يتم العثور على طلبات">Aucune commande trouvée</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <!-- View Order Modal -->
    <div class="modal" id="viewOrderModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" data-fr="Détails de la Commande" data-ar="تفاصيل الطلب">Détails de la Commande</h3>
                <button class="modal-close" onclick="closeModal('viewOrderModal')">&times;</button>
            </div>
            <div class="modal-body" id="viewOrderContent">
                <!-- Content will be loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('viewOrderModal')" data-fr="Fermer" data-ar="إغلاق">Fermer</button>
                <button class="btn btn-primary" onclick="printOrder()" data-fr="Imprimer" data-ar="طباعة">Imprimer</button>
            </div>
        </div>
    </div>
    
    <!-- Edit Order Modal -->
    <div class="modal" id="editOrderModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" data-fr="Modifier Commande" data-ar="تعديل الطلب">Modifier Commande</h3>
                <button class="modal-close" onclick="closeModal('editOrderModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editOrderForm" onsubmit="return false;">
                    <input type="hidden" id="edit_order_id" name="id">
                    
                    <div class="form-group">
                        <label class="form-label" data-fr="N° Commande" data-ar="رقم الطلب">N° Commande:</label>
                        <input type="text" class="form-control" id="edit_order_number" name="order_number" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" data-fr="Statut" data-ar="الحالة">Statut:</label>
                        <select class="form-select" id="edit_status" name="status" required>
                            <option value="pending" data-fr="En attente" data-ar="قيد الانتظار">En attente</option>
                            <option value="processing" data-fr="En cours" data-ar="قيد المعالجة">En cours</option>
                            <option value="completed" data-fr="Complété" data-ar="مكتمل">Complété</option>
                            <option value="cancelled" data-fr="Annulé" data-ar="ملغى">Annulé</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" data-fr="Statut Paiement" data-ar="حالة الدفع">Statut Paiement:</label>
                        <select class="form-select" id="edit_payment_status" name="payment_status" required>
                            <option value="pending" data-fr="En attente" data-ar="قيد الانتظار">En attente</option>
                            <option value="paid" data-fr="Payé" data-ar="مدفوع">Payé</option>
                            <option value="failed" data-fr="Échoué" data-ar="فشل">Échoué</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" data-fr="Méthode de Paiement" data-ar="طريقة الدفع">Méthode de Paiement:</label>
                        <select class="form-select" id="edit_payment_method" name="payment_method" required>
                            <option value="cash" data-fr="Espèces" data-ar="نقدا">Espèces</option>
                            <option value="credit_card" data-fr="Carte de crédit" data-ar="بطاقة ائتمان">Carte de crédit</option>
                            <option value="paypal" data-fr="PayPal" data-ar="باي بال">PayPal</option>
                            <option value="cash_on_delivery" data-fr="Paiement à la livraison" data-ar="الدفع عند الاستلام">Paiement à la livraison</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" data-fr="Montant Total" data-ar="المبلغ الإجمالي">Montant Total (DA):</label>
                        <input type="number" class="form-control" id="edit_total_amount" name="total_amount" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" data-fr="Adresse de Livraison" data-ar="عنوان التسليم">Adresse de Livraison:</label>
                        <textarea class="form-control" id="edit_shipping_address" name="shipping_address" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" data-fr="Notes" data-ar="ملاحظات">Notes:</label>
                        <textarea class="form-control" id="edit_notes" name="notes" rows="3"></textarea>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" onclick="closeModal('editOrderModal')" data-fr="Annuler" data-ar="إلغاء">Annuler</button>
                        <button type="submit" class="btn btn-primary" onclick="saveOrderChanges()" data-fr="Enregistrer" data-ar="حفظ">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3 class="modal-title" data-fr="Confirmer la suppression" data-ar="تأكيد الحذف">Confirmer la suppression</h3>
                <button class="modal-close" onclick="closeModal('deleteModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p data-fr="Êtes-vous sûr de vouloir supprimer cette commande? Cette action est irréversible." data-ar="هل أنت متأكد أنك تريد حذف هذا الطلب؟ هذا الإجراء لا يمكن التراجع عنه.">
                    Êtes-vous sûr de vouloir supprimer cette commande? Cette action est irréversible.
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('deleteModal')" data-fr="Annuler" data-ar="إلغاء">Annuler</button>
                <button class="btn btn-primary" onclick="deleteOrder()" data-fr="Supprimer" data-ar="حذف">Supprimer</button>
            </div>
        </div>
    </div>
    
    <!-- Confirm Order Modal -->
    <div class="modal" id="confirmOrderModal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3 class="modal-title" data-fr="Confirmer la Commande" data-ar="تأكيد الطلب">Confirmer la Commande</h3>
                <button class="modal-close" onclick="closeModal('confirmOrderModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p data-fr="Voulez-vous marquer cette commande comme complétée?" data-ar="هل تريد وضع علامة على هذا الطلب كمكتمل؟">
                    Voulez-vous marquer cette commande comme complétée?
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('confirmOrderModal')" data-fr="Annuler" data-ar="إلغاء">Annuler</button>
                <button class="btn btn-primary" onclick="completeOrder()" data-fr="Confirmer" data-ar="تأكيد">Confirmer</button>
            </div>
        </div>
    </div>

    <script>
        let currentOrderId = '';
        
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
            
            document.querySelectorAll('.language-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            if (window.innerWidth < 992) {
                toggleSidebar();
            }
        }
        
        function toggleSidebar() {
            const sidebar = document.querySelector('.dashboard-sidebar');
            sidebar.classList.toggle('active');
        }
        
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
        
        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        // Order management functions
        function viewOrder(orderId) {
            currentOrderId = orderId;
            
            const formData = new FormData();
            formData.append('action', 'get_order_details');
            formData.append('id', orderId);
            
            fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('viewOrderContent').innerHTML = data.html;
                    openModal('viewOrderModal');
                } else {
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Erreur lors du chargement des détails de la commande');
            });
        }
        
        function editOrder(orderId) {
            currentOrderId = orderId;
            
            // Load order data via AJAX
            const formData = new FormData();
            formData.append('action', 'get_order_data');
            formData.append('id', orderId);
            
            fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const order = data.order;
                    
                    // Populate form fields
                    document.getElementById('edit_order_id').value = order.order_id;
                    document.getElementById('edit_order_number').value = order.order_number;
                    document.getElementById('edit_status').value = order.status;
                    document.getElementById('edit_payment_status').value = order.payment_status;
                    document.getElementById('edit_payment_method').value = order.payment_method || 'cash';
                    document.getElementById('edit_total_amount').value = order.total_amount;
                    document.getElementById('edit_shipping_address').value = order.shipping_address || '';
                    document.getElementById('edit_notes').value = order.notes || '';
                    
                    openModal('editOrderModal');
                } else {
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Erreur lors du chargement des données de la commande');
            });
        }
        
        function confirmDelete(orderId) {
            currentOrderId = orderId;
            openModal('deleteModal');
        }
        
        function confirmOrder(orderId) {
            currentOrderId = orderId;
            openModal('confirmOrderModal');
        }
        
        function deleteOrder() {
            const formData = new FormData();
            formData.append('action', 'delete_order');
            formData.append('id', currentOrderId);
            
            fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Commande supprimée avec succès!');
                    location.reload();
                } else {
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Erreur lors de la suppression');
            });
            
            closeModal('deleteModal');
        }
        
        function completeOrder() {
            const formData = new FormData();
            formData.append('action', 'update_order_status');
            formData.append('id', currentOrderId);
            formData.append('status', 'completed');
            
            fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Commande marquée comme complétée!');
                    location.reload();
                } else {
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Erreur lors de la mise à jour');
            });
            
            closeModal('confirmOrderModal');
        }
        
        function saveOrderChanges() {
            // Get form data
            const formData = new FormData();
            formData.append('action', 'update_order');
            formData.append('id', document.getElementById('edit_order_id').value);
            formData.append('order_number', document.getElementById('edit_order_number').value);
            formData.append('status', document.getElementById('edit_status').value);
            formData.append('payment_status', document.getElementById('edit_payment_status').value);
            formData.append('payment_method', document.getElementById('edit_payment_method').value);
            formData.append('total_amount', document.getElementById('edit_total_amount').value);
            formData.append('shipping_address', document.getElementById('edit_shipping_address').value);
            formData.append('notes', document.getElementById('edit_notes').value);
            
            // Validate form
            if (!document.getElementById('edit_order_number').value.trim()) {
                alert('Veuillez saisir le numéro de commande');
                return;
            }
            
            if (!document.getElementById('edit_total_amount').value || document.getElementById('edit_total_amount').value <= 0) {
                alert('Veuillez saisir un montant total valide');
                return;
            }
            
            fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Modifications enregistrées avec succès!');
                    closeModal('editOrderModal');
                    location.reload();
                } else {
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Erreur lors de l\'enregistrement');
            });
        }
        
        function searchOrders() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('#ordersTableBody tr');
            
            rows.forEach(row => {
                const searchData = row.getAttribute('data-search');
                if (searchData && searchData.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        function filterOrders() {
            const statusFilter = document.getElementById('statusFilter').value;
            const dateFilter = document.getElementById('dateFilter').value;
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            
            const rows = document.querySelectorAll('#ordersTableBody tr');
            
            rows.forEach(row => {
                const status = row.getAttribute('data-status');
                const date = new Date(row.getAttribute('data-date'));
                const now = new Date();
                const searchData = row.getAttribute('data-search');
                
                let statusMatch = statusFilter === 'all' || status === statusFilter;
                let dateMatch = true;
                let searchMatch = searchData && searchData.includes(searchTerm);
                
                if (dateFilter !== 'all') {
                    switch(dateFilter) {
                        case 'today':
                            dateMatch = date.toDateString() === now.toDateString();
                            break;
                        case 'week':
                            const startOfWeek = new Date(now.setDate(now.getDate() - now.getDay()));
                            dateMatch = date >= startOfWeek;
                            break;
                        case 'month':
                            dateMatch = date.getMonth() === now.getMonth() && date.getFullYear() === now.getFullYear();
                            break;
                        case 'year':
                            dateMatch = date.getFullYear() === now.getFullYear();
                            break;
                    }
                }
                
                if (statusMatch && dateMatch && searchMatch) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        function exportOrders() {
            alert('Export des commandes en cours...');
        }
        
        function showAddOrderModal() {
            alert('Fonctionnalité à implémenter');
        }
        
        function printOrder() {
            const printContent = document.getElementById('viewOrderContent').innerHTML;
            const originalContent = document.body.innerHTML;
            
            document.body.innerHTML = `
                <html>
                <head>
                    <title>Commande ${currentOrderId}</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; }
                        .order-details { margin-bottom: 20px; }
                        .order-details-item { display: flex; margin-bottom: 10px; }
                        .order-details-label { font-weight: bold; min-width: 150px; }
                        .order-products { margin-top: 20px; }
                        .order-product { display: flex; align-items: center; padding: 10px 0; border-bottom: 1px solid #eee; }
                        .order-summary { margin-top: 20px; background: #f9f9f9; padding: 15px; }
                        .order-summary-total { font-weight: bold; font-size: 1.2em; }
                        @media print {
                            .no-print { display: none; }
                        }
                    </style>
                </head>
                <body>
                    <h2>Détails de la Commande</h2>
                    ${printContent}
                    <div class="no-print" style="margin-top: 20px;">
                        <button onclick="window.print()">Imprimer</button>
                        <button onclick="window.close()">Fermer</button>
                    </div>
                </body>
                </html>
            `;
            
            window.print();
            document.body.innerHTML = originalContent;
        }
        
        // Initialize the dashboard
        document.addEventListener('DOMContentLoaded', function() {
            changeLanguage('fr');
            
            if (window.innerWidth >= 992) {
                document.querySelector('.dashboard-sidebar').classList.add('active');
            }
            
            window.addEventListener('resize', function() {
                const sidebar = document.querySelector('.dashboard-sidebar');
                
                if (window.innerWidth >= 992) {
                    sidebar.classList.add('active');
                } else {
                    sidebar.classList.remove('active');
                }
            });
            
            document.getElementById('searchInput').addEventListener('input', searchOrders);
        });
    </script>
</body>
</html>