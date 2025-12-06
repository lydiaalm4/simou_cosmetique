<?php
// Database connection
$servername = "127.0.0.1:3306";
$username = "root"; 
$password = "admine"; 
$dbname = "oussama";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to calculate customer loyalty tier
function getLoyaltyTier($totalSpent) {
    if ($totalSpent >= 20000) {
        return ['tier' => 'gold', 'label_fr' => 'Or', 'label_ar' => 'ذهبي'];
    } elseif ($totalSpent >= 10000) {
        return ['tier' => 'silver', 'label_fr' => 'Argent', 'label_ar' => 'فضي'];
    } else {
        return ['tier' => 'bronze', 'label_fr' => 'Bronze', 'label_ar' => 'برونزي'];
    }
}

// Function to calculate loyalty points (1 point per 10 DA spent)
function calculatePoints($totalSpent) {
    return intval($totalSpent / 10);
}

// Get loyal customers data
$sql = "SELECT 
            c.customer_id,
            c.first_name,
            c.last_name,
            c.email,
            c.phone,
            c.address,
            c.city,
            c.registration_date,
            c.profile_image,
            COUNT(o.order_id) as total_orders,
            COALESCE(SUM(oi.total_price), 0) as total_spent
        FROM customers c
        LEFT JOIN orders o ON c.customer_id = o.user_id
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        GROUP BY c.customer_id
        HAVING total_orders >= 3
        ORDER BY total_spent DESC";

$result = $conn->query($sql);

$loyalCustomers = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $loyaltyTier = getLoyaltyTier($row['total_spent']);
        $points = calculatePoints($row['total_spent']);
        
        $loyalCustomers[] = [
            'id' => $row['customer_id'],
            'name' => $row['first_name'] . ' ' . $row['last_name'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'address' => $row['address'],
            'city' => $row['city'],
            'registration_date' => $row['registration_date'],
            'profile_image' => $row['profile_image'],
            'total_orders' => $row['total_orders'],
            'total_spent' => $row['total_spent'],
            'points' => $points,
            'tier' => $loyaltyTier['tier'],
            'tier_label_fr' => $loyaltyTier['label_fr'],
            'tier_label_ar' => $loyaltyTier['label_ar']
        ];
    }
}

// Calculate loyalty program statistics
$goldCount = 0;
$silverCount = 0;
$bronzeCount = 0;
$totalSpent = 0;

foreach ($loyalCustomers as $customer) {
    $totalSpent += $customer['total_spent'];
    if ($customer['tier'] === 'gold') $goldCount++;
    elseif ($customer['tier'] === 'silver') $silverCount++;
    else $bronzeCount++;
}

$averageSpent = count($loyalCustomers) > 0 ? $totalSpent / count($loyalCustomers) : 0;

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clients Fidèles - Cosmetique SIMOU</title>
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
        
        /* Loyal Customers Cards */
        .loyal-customers-container {
            margin: 0 20px 20px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .loyal-customer-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            padding: 20px;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .loyal-customer-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .customer-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .customer-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--accent);
            margin-right: 15px;
        }
        
        /* RTL customer avatar margin */
        body[dir="rtl"] .customer-avatar {
            margin-right: 0;
            margin-left: 15px;
        }
        
        .customer-info {
            flex: 1;
        }
        
        .customer-name {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 3px;
        }
        
        .customer-email {
            color: #666;
            font-size: 0.8rem;
            margin-bottom: 5px;
            word-break: break-all;
        }
        
        .customer-tier {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .tier-gold {
            background: #ffd700;
            color: #856404;
        }
        
        .tier-silver {
            background: #c0c0c0;
            color: #333;
        }
        
        .tier-bronze {
            background: #cd7f32;
            color: white;
        }
        
        .customer-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .stat-item {
            text-align: center;
            padding: 10px;
            border-radius: 10px;
            background: #f9f9f9;
        }
        
        .stat-value {
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--primary);
            margin-bottom: 3px;
        }
        
        .stat-label {
            font-size: 0.7rem;
            color: #666;
        }
        
        .customer-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .action-btn {
            flex: 1;
            padding: 8px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        .action-btn.view {
            background: var(--info);
            color: white;
        }
        
        .action-btn.message {
            background: var(--primary);
            color: white;
        }
        
        .action-btn.reward {
            background: var(--success);
            color: white;
        }
        
        .action-btn:hover {
            opacity: 0.8;
        }
        
        /* Loyalty Program Summary */
        .loyalty-summary {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            padding: 20px;
            margin: 0 20px 20px;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .summary-card {
            background: #f9f9f9;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
        }
        
        .summary-value {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--primary);
            margin-bottom: 5px;
        }
        
        .summary-label {
            font-size: 0.8rem;
            color: #666;
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
            max-width: 500px;
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
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
        }
        
        @media (max-width: 576px) {
            /* Mobile styles */
            .menu-toggle {
                display: block;
            }
            
            .user-profile {
                gap: 5px;
            }
            
            .loyal-customers-container {
                grid-template-columns: 1fr;
            }
            
            .customer-stats {
                grid-template-columns: 1fr;
            }
            
            .summary-grid {
                grid-template-columns: 1fr;
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
                <a href="dashboard.php" class="menu-item">
                    <div class="menu-icon"><i class="fas fa-home"></i></div>
                    <div class="menu-text" data-fr="Tableau de Bord" data-ar="لوحة التحكم">Tableau de Bord</div>
                </a>
                
                <div class="menu-category" data-fr="Gestion" data-ar="الإدارة">Gestion</div>
                <a href="addproduit.php" class="menu-item">
                    <div class="menu-icon"><i class="fas fa-box-open"></i></div>
                    <div class="menu-text" data-fr="Produits" data-ar="المنتجات">Produits</div>
                </a>
                <a href="commande.php" class="menu-item">
                    <div class="menu-icon"><i class="fas fa-shopping-cart"></i></div>
                    <div class="menu-text" data-fr="Commandes" data-ar="الطلبات">Commandes</div>
                </a>
                <a href="clientsfideles.php" class="menu-item active">
                    <div class="menu-icon"><i class="fas fa-users"></i></div>
                    <div class="menu-text" data-fr="Clients" data-ar="العملاء">Clients</div>
                </a>
                
                <a href="categorie.php" class="menu-item">
                    <div class="menu-icon"><i class="fas fa-tags"></i></div>
                    <div class="menu-text" data-fr="Catégories" data-ar="الفئات">Catégories</div>
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
                    <input type="text" class="search-input" placeholder="Rechercher..." data-fr-placeholder="Rechercher..." data-ar-placeholder="بحث..." onkeyup="filterCustomers(this.value)">
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
                                <div class="user-name" data-fr="Oussama" data-ar="سارة جونسون">Oussama</div>
                                <div class="user-role" data-fr="Administrateur" data-ar="مدير">Administrateur</div>
                            </div>
                            <i class="fas fa-chevron-down user-dropdown"></i>
                        </div>
                    </button>
                </div>
            </nav>
            
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title" data-fr="Clients Fidèles" data-ar="العملاء المخلصين">Clients Fidèles</h1>
                <div class="page-actions">
                    
                        
                        
                    </button>
                    <button class="btn btn-primary" onclick="showAddRewardModal()">
                        <i class="fas fa-gift"></i>
                        <span data-fr="Ajouter Récompense" data-ar="إضافة مكافأة">Ajouter Récompense</span>
                    </button>
                </div>
            </div>
            
            <!-- Loyalty Program Summary -->
            <div class="loyalty-summary">
                <h3 data-fr="Programme de Fidélité" data-ar="برنامج الولاء">Programme de Fidélité</h3>
                <div class="summary-grid">
                    <div class="summary-card">
                        <div class="summary-value"><?php echo count($loyalCustomers); ?></div>
                        <div class="summary-label" data-fr="Clients Fidèles" data-ar="عملاء مخلصين">Clients Fidèles</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-value"><?php echo $goldCount; ?></div>
                        <div class="summary-label" data-fr="Niveau Or" data-ar="مستوى ذهبي">Niveau Or</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-value"><?php echo $silverCount; ?></div>
                        <div class="summary-label" data-fr="Niveau Argent" data-ar="مستوى فضي">Niveau Argent</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-value"><?php echo $bronzeCount; ?></div>
                        <div class="summary-label" data-fr="Niveau Bronze" data-ar="مستوى برونزي">Niveau Bronze</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-value"><?php echo number_format($averageSpent, 0, ',', ' '); ?> DA</div>
                        <div class="summary-label" data-fr="Moyenne Dépenses" data-ar="متوسط الإنفاق">Moyenne Dépenses</div>
                    </div>
                </div>
            </div>
            
            <!-- Loyal Customers Cards -->
            <div class="loyal-customers-container" id="customersContainer">
                <?php if (count($loyalCustomers) > 0): ?>
                    <?php foreach ($loyalCustomers as $customer): ?>
                        <div class="loyal-customer-card" data-customer-name="<?php echo strtolower($customer['name']); ?>">
                            <div class="customer-header">
                                
                                <div class="customer-info">
                                    <div class="customer-name"><?php echo $customer['name']; ?></div>
                                    <div class="customer-email"><?php echo $customer['email']; ?></div>
                                    <span class="customer-tier tier-<?php echo $customer['tier']; ?>" 
                                          data-fr="<?php echo $customer['tier_label_fr']; ?>" 
                                          data-ar="<?php echo $customer['tier_label_ar']; ?>">
                                        <?php echo $customer['tier_label_fr']; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="customer-stats">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $customer['total_orders']; ?></div>
                                    <div class="stat-label" data-fr="Commandes" data-ar="طلبات">Commandes</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo number_format($customer['total_spent'], 0, ',', ' '); ?> DA</div>
                                    <div class="stat-label" data-fr="Total Dépensé" data-ar="إجمالي الإنفاق">Total Dépensé</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo number_format($customer['points'], 0, ',', ' '); ?></div>
                                    <div class="stat-label" data-fr="Points" data-ar="نقاط">Points</div>
                                </div>
                            </div>
                            
                            <div class="customer-actions">
                                <button class="action-btn view" onclick="viewCustomer(<?php echo $customer['id']; ?>, '<?php echo $customer['name']; ?>', '<?php echo $customer['email']; ?>', '<?php echo $customer['phone']; ?>', '<?php echo $customer['address']; ?>', '<?php echo $customer['registration_date']; ?>', <?php echo $customer['total_orders']; ?>, <?php echo $customer['total_spent']; ?>, <?php echo $customer['points']; ?>, '<?php echo $customer['profile_image']; ?>')">
                                    <i class="fas fa-eye"></i>
                                    <span data-fr="Voir" data-ar="عرض">Voir</span>
                                </button>
                                <button class="action-btn message" onclick="messageCustomer(<?php echo $customer['id']; ?>, '<?php echo $customer['name']; ?>', '<?php echo $customer['email']; ?>')">
                                    <i class="fas fa-envelope"></i>
                                    <span data-fr="Message" data-ar="رسالة">Message</span>
                                </button>
                                <button class="action-btn reward" onclick="rewardCustomer(<?php echo $customer['id']; ?>, '<?php echo $customer['name']; ?>')">
                                    <i class="fas fa-gift"></i>
                                    <span data-fr="Récompense" data-ar="مكافأة">Récompense</span>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 40px; background: white; border-radius: 15px;">
                        <i class="fas fa-users" style="font-size: 3rem; color: #ddd; margin-bottom: 15px;"></i>
                        <h3 data-fr="Aucun client fidèle trouvé" data-ar="لم يتم العثور على عملاء مخلصين">Aucun client fidèle trouvé</h3>
                        <p data-fr="Les clients avec au moins 3 commandes apparaîtront ici" data-ar="العملاء الذين لديهم 3 طلبات على الأقل سيظهرون هنا">Les clients avec au moins 3 commandes apparaîtront ici</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <!-- View Customer Modal -->
    <div class="modal" id="viewCustomerModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" data-fr="Détails du Client" data-ar="تفاصيل العميل">Détails du Client</h3>
                <button class="modal-close" onclick="closeModal('viewCustomerModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div style="text-align: center; margin-bottom: 20px;">
                    <img id="customerModalImage" src="" alt="Client" style="width: 100px; height: 100px; border-radius: 50%; border: 3px solid var(--accent);">
                    <h3 style="margin-top: 10px;" id="customerModalName"></h3>
                    <span class="customer-tier" id="customerModalTier" style="margin-bottom: 15px;"></span>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <div class="form-label" data-fr="Email:" data-ar="البريد الإلكتروني:">Email:</div>
                        <div style="padding: 8px; background: #f9f9f9; border-radius: 5px;" id="customerModalEmail"></div>
                    </div>
                    <div>
                        <div class="form-label" data-fr="Téléphone:" data-ar="الهاتف:">Téléphone:</div>
                        <div style="padding: 8px; background: #f9f9f9; border-radius: 5px;" id="customerModalPhone"></div>
                    </div>
                    <div>
                        <div class="form-label" data-fr="Date d'inscription:" data-ar="تاريخ التسجيل:">Date d'inscription:</div>
                        <div style="padding: 8px; background: #f9f9f9; border-radius: 5px;" id="customerModalJoinDate"></div>
                    </div>
                    <div>
                        <div class="form-label" data-fr="Dernière Commande:" data-ar="آخر طلب:">Dernière Commande:</div>
                        <div style="padding: 8px; background: #f9f9f9; border-radius: 5px;" id="customerModalLastOrder">N/A</div>
                    </div>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <div class="form-label" data-fr="Adresse:" data-ar="العنوان:">Adresse:</div>
                    <div style="padding: 8px; background: #f9f9f9; border-radius: 5px;" id="customerModalAddress"></div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <div class="form-label" data-fr="Total Commandes:" data-ar="إجمالي الطلبات:">Total Commandes:</div>
                        <div style="padding: 8px; background: #f9f9f9; border-radius: 5px;" id="customerModalTotalOrders"></div>
                    </div>
                    <div>
                        <div class="form-label" data-fr="Total Dépensé:" data-ar="إجمالي الإنفاق:">Total Dépensé:</div>
                        <div style="padding: 8px; background: #f9f9f9; border-radius: 5px;" id="customerModalTotalSpent"></div>
                    </div>
                    <div>
                        <div class="form-label" data-fr="Points de Fidélité:" data-ar="نقاط الولاء:">Points de Fidélité:</div>
                        <div style="padding: 8px; background: #f9f9f9; border-radius: 5px;" id="customerModalPoints"></div>
                    </div>
                    <div>
                        <div class="form-label" data-fr="Moyenne Commande:" data-ar="متوسط الطلب:">Moyenne Commande:</div>
                        <div style="padding: 8px; background: #f9f9f9; border-radius: 5px;" id="customerModalAvgOrder"></div>
                    </div>
                </div>
                
                <div>
                    <div class="form-label" data-fr="Notes:" data-ar="ملاحظات:">Notes:</div>
                    <textarea class="form-control" rows="3" id="customerNotes" data-fr-placeholder="Ajouter des notes sur ce client..." data-ar-placeholder="إضافة ملاحظات حول هذا العميل..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('viewCustomerModal')" data-fr="Fermer" data-ar="إغلاق">Fermer</button>
                <button class="btn btn-primary" onclick="saveCustomerNotes()" data-fr="Enregistrer" data-ar="حفظ">Enregistrer</button>
            </div>
        </div>
    </div>
    
    <!-- Reward Customer Modal -->
    <div class="modal" id="rewardCustomerModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" data-fr="Envoyer une Récompense" data-ar="إرسال مكافأة">Envoyer une Récompense</h3>
                <button class="modal-close" onclick="closeModal('rewardCustomerModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" data-fr="Client:" data-ar="العميل:">Client:</label>
                    <input type="text" class="form-control" id="rewardCustomerName" readonly>
                </div>
                
                <div class="form-group">
                    <label class="form-label" data-fr="Type de Récompense:" data-ar="نوع المكافأة:">Type de Récompense:</label>
                    <select class="form-select" id="rewardType">
                        <option value="" selected disabled data-fr="Sélectionner un type" data-ar="اختر نوع">Sélectionner un type</option>
                        <option value="discount" data-fr="Code de Réduction" data-ar="كود خصم">Code de Réduction</option>
                        <option value="gift" data-fr="Cadeau" data-ar="هدية">Cadeau</option>
                        <option value="points" data-fr="Points de Fidélité" data-ar="نقاط ولاء">Points de Fidélité</option>
                        <option value="other" data-fr="Autre" data-ar="أخرى">Autre</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" data-fr="Valeur:" data-ar="القيمة:">Valeur:</label>
                    <div style="display: flex;">
                        <input type="number" class="form-control" id="rewardValue" style="border-top-right-radius: 0; border-bottom-right-radius: 0;" placeholder="100">
                        <select class="form-select" id="rewardUnit" style="flex: 0 0 100px; border-top-left-radius: 0; border-bottom-left-radius: 0;">
                            <option value="DA" selected>DA</option>
                            <option value="points" data-fr="Points" data-ar="نقاط">Points</option>
                            <option value="%" data-fr="%" data-ar="%">%</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" data-fr="Message:" data-ar="الرسالة:">Message:</label>
                    <textarea class="form-control" id="rewardMessage" rows="3" data-fr-placeholder="Message personnalisé pour le client..." data-ar-placeholder="رسالة مخصصة للعميل..."></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label" data-fr="Date d'Expiration:" data-ar="تاريخ الانتهاء:">Date d'Expiration:</label>
                    <input type="date" class="form-control" id="rewardExpiry">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('rewardCustomerModal')" data-fr="Annuler" data-ar="إلغاء">Annuler</button>
                <button class="btn btn-primary" onclick="sendReward()" data-fr="Envoyer" data-ar="إرسال">Envoyer</button>
            </div>
        </div>
    </div>
    
    <!-- Message Customer Modal -->
    <div class="modal" id="messageCustomerModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" data-fr="Envoyer un Message" data-ar="إرسال رسالة">Envoyer un Message</h3>
                <button class="modal-close" onclick="closeModal('messageCustomerModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" data-fr="À:" data-ar="إلى:">À:</label>
                    <input type="text" class="form-control" id="messageRecipient" readonly>
                </div>
                
                <div class="form-group">
                    <label class="form-label" data-fr="Sujet:" data-ar="الموضوع:">Sujet:</label>
                    <input type="text" class="form-control" id="messageSubject" data-fr-placeholder="Sujet du message..." data-ar-placeholder="موضوع الرسالة...">
                </div>
                
                <div class="form-group">
                    <label class="form-label" data-fr="Message:" data-ar="الرسالة:">Message:</label>
                    <textarea class="form-control" id="messageContent" rows="5" data-fr-placeholder="Écrivez votre message ici..." data-ar-placeholder="اكتب رسالتك هنا..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('messageCustomerModal')" data-fr="Annuler" data-ar="إلغاء">Annuler</button>
                <button class="btn btn-primary" onclick="sendMessage()" data-fr="Envoyer" data-ar="إرسال">Envoyer</button>
            </div>
        </div>
    </div>
    
    <!-- Add Reward Modal -->
    <div class="modal" id="addRewardModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" data-fr="Ajouter une Récompense" data-ar="إضافة مكافأة">Ajouter une Récompense</h3>
                <button class="modal-close" onclick="closeModal('addRewardModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" data-fr="Nom de la Récompense:" data-ar="اسم المكافأة:">Nom de la Récompense:</label>
                    <input type="text" class="form-control" id="rewardName" data-fr-placeholder="Ex: Réduction de 10%" data-ar-placeholder="مثال: خصم 10%">
                </div>
                
                <div class="form-group">
                    <label class="form-label" data-fr="Type:" data-ar="النوع:">Type:</label>
                    <select class="form-select" id="newRewardType">
                        <option value="" selected disabled data-fr="Sélectionner un type" data-ar="اختر نوع">Sélectionner un type</option>
                        <option value="discount" data-fr="Réduction" data-ar="خصم">Réduction</option>
                        <option value="gift" data-fr="Cadeau" data-ar="هدية">Cadeau</option>
                        <option value="free_shipping" data-fr="Livraison Gratuite" data-ar="شحن مجاني">Livraison Gratuite</option>
                        <option value="points" data-fr="Points" data-ar="نقاط">Points</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" data-fr="Valeur:" data-ar="القيمة:">Valeur:</label>
                    <div style="display: flex;">
                        <input type="text" class="form-control" id="newRewardValue" style="border-top-right-radius: 0; border-bottom-right-radius: 0;" placeholder="10">
                        <select class="form-select" id="newRewardUnit" style="flex: 0 0 100px; border-top-left-radius: 0; border-bottom-left-radius: 0;">
                            <option value="DA" selected>DA</option>
                            <option value="%" data-fr="%" data-ar="%">%</option>
                            <option value="points" data-fr="Points" data-ar="نقاط">Points</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" data-fr="Niveau Requis:" data-ar="المستوى المطلوب:">Niveau Requis:</label>
                    <select class="form-select" id="rewardLevel">
                        <option value="all" selected data-fr="Tous les clients" data-ar="كل العملاء">Tous les clients</option>
                        <option value="bronze" data-fr="Bronze et plus" data-ar="برونزي فما فوق">Bronze et plus</option>
                        <option value="silver" data-fr="Argent et plus" data-ar="فضي فما فوق">Argent et plus</option>
                        <option value="gold" data-fr="Or seulement" data-ar="ذهبي فقط">Or seulement</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" data-fr="Date d'Expiration:" data-ar="تاريخ الانتهاء:">Date d'Expiration:</label>
                    <input type="date" class="form-control" id="newRewardExpiry">
                </div>
                
                <div class="form-group">
                    <label class="form-label" data-fr="Description:" data-ar="الوصف:">Description:</label>
                    <textarea class="form-control" id="rewardDescription" rows="3" data-fr-placeholder="Description de la récompense..." data-ar-placeholder="وصف المكافأة..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('addRewardModal')" data-fr="Annuler" data-ar="إلغاء">Annuler</button>
                <button class="btn btn-primary" onclick="createReward()" data-fr="Créer" data-ar="إنشاء">Créer</button>
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
        
        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        // Customer management functions
        function viewCustomer(customerId, name, email, phone, address, joinDate, totalOrders, totalSpent, points, profileImage) {
            document.getElementById('customerModalName').textContent = name;
            document.getElementById('customerModalEmail').textContent = email;
            document.getElementById('customerModalPhone').textContent = phone || 'N/A';
            document.getElementById('customerModalAddress').textContent = address || 'N/A';
            document.getElementById('customerModalJoinDate').textContent = joinDate;
            document.getElementById('customerModalTotalOrders').textContent = totalOrders;
            document.getElementById('customerModalTotalSpent').textContent = numberFormat(totalSpent) + ' DA';
            document.getElementById('customerModalPoints').textContent = numberFormat(points);
            document.getElementById('customerModalAvgOrder').textContent = numberFormat(totalSpent / totalOrders) + ' DA';
            document.getElementById('customerModalImage').src = profileImage;
            
            // Set tier based on total spent
            let tierElement = document.getElementById('customerModalTier');
            if (totalSpent >= 20000) {
                tierElement.className = 'customer-tier tier-gold';
                tierElement.textContent = document.body.dir === 'rtl' ? 'ذهبي' : 'Or';
            } else if (totalSpent >= 10000) {
                tierElement.className = 'customer-tier tier-silver';
                tierElement.textContent = document.body.dir === 'rtl' ? 'فضي' : 'Argent';
            } else {
                tierElement.className = 'customer-tier tier-bronze';
                tierElement.textContent = document.body.dir === 'rtl' ? 'برونزي' : 'Bronze';
            }
            
            openModal('viewCustomerModal');
        }
        
        function messageCustomer(customerId, name, email) {
            document.getElementById('messageRecipient').value = name + ' (' + email + ')';
            openModal('messageCustomerModal');
        }
        
        function rewardCustomer(customerId, name) {
            document.getElementById('rewardCustomerName').value = name;
            openModal('rewardCustomerModal');
        }
        
        function showAddRewardModal() {
            openModal('addRewardModal');
        }
        
        function saveCustomerNotes() {
            // In a real app, you would save the notes to the database
            alert('Notes enregistrées avec succès!');
            closeModal('viewCustomerModal');
        }
        
        function sendReward() {
            // In a real app, you would send the reward to the customer
            alert('Récompense envoyée avec succès!');
            closeModal('rewardCustomerModal');
        }
        
        function sendMessage() {
            // In a real app, you would send the message to the customer
            alert('Message envoyé avec succès!');
            closeModal('messageCustomerModal');
        }
        
        function createReward() {
            // In a real app, you would create the reward in the system
            alert('Récompense créée avec succès!');
            closeModal('addRewardModal');
        }
        
        function exportLoyalCustomers() {
            alert('Export des clients fidèles en cours...');
        }
        
        // Search filter function
        function filterCustomers(searchTerm) {
            const customers = document.querySelectorAll('.loyal-customer-card');
            const searchLower = searchTerm.toLowerCase();
            
            customers.forEach(customer => {
                const customerName = customer.getAttribute('data-customer-name');
                if (customerName.includes(searchLower)) {
                    customer.style.display = 'block';
                } else {
                    customer.style.display = 'none';
                }
            });
        }
        
        // Number formatting function
        function numberFormat(number) {
            return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, " ");
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
        });
    </script>
</body>
</html>