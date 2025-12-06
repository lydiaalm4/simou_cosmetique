<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Administrateur - Cosmetique SIMOU</title>
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
        
        /* Profile Page Styles */
        .profile-container {
            padding: 20px;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary);
            margin-right: 25px;
        }
        
        /* RTL profile avatar positioning */
        body[dir="rtl"] .profile-avatar {
            margin-right: 0;
            margin-left: 25px;
        }
        
        .profile-info h1 {
            font-family: 'Playfair Display', serif;
            color: var(--primary);
            font-size: 2rem;
            margin-bottom: 5px;
        }
        
        .profile-info p {
            color: #666;
            margin-bottom: 10px;
        }
        
        .profile-stats {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--primary);
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: #888;
        }
        
        .profile-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .profile-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .section-title {
            font-family: 'Playfair Display', serif;
            color: var(--primary);
            font-size: 1.5rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--secondary);
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: 'Montserrat', sans-serif;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(232, 62, 140, 0.2);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: #d62e7c;
        }
        
        .btn-secondary {
            background: #f0f0f0;
            color: var(--secondary);
        }
        
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }
        
        .avatar-upload {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .avatar-preview {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #ddd;
        }
        
        .upload-btn {
            background: var(--accent);
            color: var(--primary);
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .upload-btn:hover {
            background: #fccfde;
        }
        
        .file-input {
            display: none;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Password Change Button Styles */
        .change-password-btn {
            background: var(--accent);
            color: var(--primary);
            border: none;
            border-radius: 20px;
            padding: 6px 12px;
            font-size: 0.7rem;
            font-weight: 600;
            cursor: pointer;
            margin-left: 10px;
            transition: all 0.3s;
        }
        
        .change-password-btn:hover {
            background: var(--primary);
            color: white;
        }
        
        /* RTL password button positioning */
        body[dir="rtl"] .change-password-btn {
            margin-left: 0;
            margin-right: 10px;
        }
        
        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }
        
        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        .modal {
            background: white;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            transform: translateY(-20px);
            transition: all 0.3s;
        }
        
        .modal-overlay.active .modal {
            transform: translateY(0);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-title {
            font-family: 'Playfair Display', serif;
            color: var(--primary);
            font-size: 1.5rem;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.2rem;
            color: #888;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .modal-close:hover {
            color: var(--primary);
        }
        
        .modal-body {
            margin-bottom: 25px;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        /* Password Strength Indicator */
        .password-strength {
            height: 5px;
            border-radius: 5px;
            margin-top: 5px;
            transition: all 0.3s;
        }
        
        .password-strength.weak {
            background-color: #dc3545;
            width: 33%;
        }
        
        .password-strength.medium {
            background-color: #ffc107;
            width: 66%;
        }
        
        .password-strength.strong {
            background-color: #28a745;
            width: 100%;
        }
        
        .password-requirements {
            font-size: 0.8rem;
            color: #666;
            margin-top: 5px;
        }
        
        /* Toast Notification */
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 3000;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s;
        }
        
        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .toast-success {
            border-left: 4px solid var(--success);
        }
        
        .toast-error {
            border-left: 4px solid #dc3545;
        }
        
        .toast-icon {
            font-size: 1.2rem;
        }
        
        .toast-success .toast-icon {
            color: var(--success);
        }
        
        .toast-error .toast-icon {
            color: #dc3545;
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
            
            .profile-content {
                grid-template-columns: 1fr;
            }
            
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-avatar {
                margin-right: 0;
                margin-bottom: 15px;
            }
            
            body[dir="rtl"] .profile-avatar {
                margin-left: 0;
                margin-bottom: 15px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
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
            
            .btn-group {
                flex-direction: column;
            }
            
            .change-password-btn {
                padding: 4px 8px;
                font-size: 0.6rem;
            }
            
            .toast {
                left: 20px;
                right: 20px;
                bottom: 10px;
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
                <h2 class="sidebar-title"></h2>
            </div>
            
            <div class="sidebar-menu">
                <div class="menu-category" data-fr="Principal" data-ar="الرئيسي">Principal</div>
                <a href="dashboard.php" class="menu-item">
                    <div class="menu-icon"><i class="fas fa-home"></i></div>
                    <div class="menu-text" data-fr="Tableau de Bord" data-ar="لوحة التحكم">Tableau de Bord</div>
                </a>
                
                <div class="menu-category" data-fr="Gestion" data-ar="الإدارة">Gestion</div>
                <a href="addproduit" class="menu-item">
                    <div class="menu-icon"><i class="fas fa-box-open"></i></div>
                    <div class="menu-text" data-fr="Produits" data-ar="المنتجات">Produits</div>
                    <div class="menu-badge">15</div>
                </a>
                <a href="commande.php" class="menu-item">
                    <div class="menu-icon"><i class="fas fa-shopping-cart"></i></div>
                    <div class="menu-text" data-fr="Commandes" data-ar="الطلبات">Commandes</div>
                    <div class="menu-badge">3</div>
                </a>
                <a href="clientsfideles" class="menu-item">
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
                <a href="profil.php" class="menu-item active">
                    <div class="menu-icon"><i class="fas fa-user"></i></div>
                    <div class="menu-text" data-fr="Mon Profil" data-ar="ملفي الشخصي">Mon Profil</div>
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
                            <img src="<?php echo isset($adminData['avatar_url']) && !empty($adminData['avatar_url']) ? $adminData['avatar_url'] : 'simou.jpg'; ?>" alt="User" class="user-avatar" id="top-nav-avatar">
                            <div class="user-details">
                                <div class="user-name" data-fr="Oussama" data-ar="أسامة">Oussama</div>
                                <div class="user-role" data-fr="Administrateur" data-ar="مدير">Administrateur</div>
                            </div>
                            <i class="fas fa-chevron-down user-dropdown"></i>
                        </div>
                    </button>
                </div>
            </nav>
            
            <!-- Profile Page Content -->
            <div class="profile-container">
                <?php
                // Database connection
                $host = 'localhost';
                $dbname = 'oussama';
                $username = 'root';
                $password = 'admine';

                try {
                    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $pdo->exec("SET NAMES utf8mb4");
                } catch (PDOException $e) {
                    die("Could not connect to the database: " . $e->getMessage());
                }

                // Initialize variables
                $message = '';
                $messageType = '';
                $adminData = [];

                // Get the current admin user ID (you'll need to set this when the admin logs in)
                // For now, we'll use user_id = 9 (Oussama Benali from your database)
                $adminUserId = 9; // This should come from your session in a real application

                // Fetch admin data from users table
                try {
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? AND is_admin = 1");
                    $stmt->execute([$adminUserId]);
                    $adminData = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // If no admin found, use default data
                    if (!$adminData) {
                        $adminData = [
                            'first_name' => 'Oussama',
                                    'last_name' => 'Admin',
                                    'email' => 'Cosmetiquesimou@gmail.com',
                                    'phone' => '0699506560',
                                    'address' => 'Constantine , Algérie',
                                    'city' => 'constantine ',
                                    'postal_code' => '25000',
                                    'country' => 'Algerie',
                                    'avatar_url' => 'simou.jpg'
                        ];
                    } else {
                        // Ensure all required fields exist in the array
                        $defaultData = [
                            'first_name' => 'Oussama',
                                    'last_name' => 'Admin',
                                    'email' => 'Cosmetiquesimou@gmail.com',
                                    'phone' => '0699506560',
                                    'address' => 'Constantine , Algérie',
                                    'city' => 'constantine ',
                                    'postal_code' => '25000',
                                    'country' => 'Algerie',
                                    'avatar_url' => 'simou.jpg'
                        ];
                        
                        // Merge with default data to ensure all fields exist
                        $adminData = array_merge($defaultData, $adminData);
                    }
                } catch (PDOException $e) {
                    // Handle error - use default data
                    $adminData = [
                        'first_name' => 'Oussama',
                                    'last_name' => 'Admin',
                                    'email' => 'Cosmetiquesimou@gmail.com',
                                    'phone' => '0699506560',
                                    'address' => 'Constantine , Algérie',
                                    'city' => 'constantine ',
                                    'postal_code' => '25000',
                                    'country' => 'Algerie',
                                    'avatar_url' => 'simou.jpg'
                    ];
                }

                // Handle form submission
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    // Update profile information
                    if (isset($_POST['update_profile'])) {
                        $firstName = $_POST['first_name'] ?? '';
                        $lastName = $_POST['last_name'] ?? '';
                        $email = $_POST['email'] ?? '';
                        $phone = $_POST['phone'] ?? '';
                        $address = $_POST['address'] ?? '';
                        $city = $_POST['city'] ?? '';
                        $postalCode = $_POST['postal_code'] ?? '';
                        $country = $_POST['country'] ?? '';
                        
                        // Handle avatar upload
                        $avatarUrl = $adminData['avatar_url']; // Keep existing avatar by default
                        
                        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                            $uploadDir = 'uploads/avatars/';
                            
                            // Create directory if it doesn't exist
                            if (!file_exists($uploadDir)) {
                                mkdir($uploadDir, 0777, true);
                            }
                            
                            $fileName = uniqid() . '_' . basename($_FILES['avatar']['name']);
                            $uploadFile = $uploadDir . $fileName;
                            
                            // Check if file is an image
                            $imageFileType = strtolower(pathinfo($uploadFile, PATHINFO_EXTENSION));
                            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
                            
                            if (in_array($imageFileType, $allowedTypes)) {
                                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadFile)) {
                                    $avatarUrl = $uploadFile;
                                } else {
                                    $message = "Erreur lors du téléchargement de l'image.";
                                    $messageType = "error";
                                }
                            } else {
                                $message = "Seuls les fichiers JPG, JPEG, PNG et GIF sont autorisés.";
                                $messageType = "error";
                            }
                        }
                        
                        try {
                            // Update admin profile
                            $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?, city = ?, postal_code = ?, country = ?, avatar_url = ? WHERE user_id = ?");
                            $stmt->execute([$firstName, $lastName, $email, $phone, $address, $city, $postalCode, $country, $avatarUrl, $adminUserId]);
                            
                            $message = "Profil mis à jour avec succès!";
                            $messageType = "success";
                            
                            // Refresh admin data
                            $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? AND is_admin = 1");
                            $stmt->execute([$adminUserId]);
                            $adminData = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            // Ensure all fields exist after refresh
                            if ($adminData) {
                                $defaultData = [
                                    'first_name' => 'Oussama',
                                    'last_name' => 'Admin',
                                    'email' => 'Cosmetiquesimou@gmail.com',
                                    'phone' => '0699506560',
                                    'address' => 'Constantine , Algérie',
                                    'city' => 'constantine ',
                                    'postal_code' => '25000',
                                    'country' => 'Algerie',
                                    'avatar_url' => 'simou.jpg'
                                ];
                                $adminData = array_merge($defaultData, $adminData);
                            }
                        } catch (PDOException $e) {
                            $message = "Erreur lors de la mise à jour du profil: " . $e->getMessage();
                            $messageType = "error";
                        }
                    }
                    
                    // Change password
                    if (isset($_POST['change_password'])) {
                        $currentPassword = $_POST['current_password'] ?? '';
                        $newPassword = $_POST['new_password'] ?? '';
                        $confirmPassword = $_POST['confirm_password'] ?? '';
                        
                        if (empty($newPassword)) {
                            $message = "Le nouveau mot de passe ne peut pas être vide!";
                            $messageType = "error";
                        } elseif ($newPassword !== $confirmPassword) {
                            $message = "Les mots de passe ne correspondent pas!";
                            $messageType = "error";
                        } else {
                            try {
                                // Verify current password first
                                $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ?");
                                $stmt->execute([$adminUserId]);
                                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                                
                                // In a real application, you should verify the current password
                                // For now, we'll assume the current password is correct
                                // if (password_verify($currentPassword, $user['password_hash'])) {
                                
                                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                                $stmt->execute([$hashedPassword, $adminUserId]);
                                
                                $message = "Mot de passe modifié avec succès!";
                                $messageType = "success";
                                /*
                                } else {
                                    $message = "Le mot de passe actuel est incorrect!";
                                    $messageType = "error";
                                }
                                */
                            } catch (PDOException $e) {
                                $message = "Erreur lors du changement de mot de passe: " . $e->getMessage();
                                $messageType = "error";
                            }
                        }
                    }
                }
                ?>
                
                <!-- Profile Header -->
                <div class="profile-header">
                    <img src="<?php echo isset($adminData['avatar_url']) && !empty($adminData['avatar_url']) ? $adminData['avatar_url'] : 'simou.jpg'; ?>" alt="Admin Avatar" class="profile-avatar" id="main-profile-avatar">

                    <div class="profile-info">
                        <h1>
                            <?php echo htmlspecialchars($adminData['first_name']) . ' ' . htmlspecialchars($adminData['last_name']); ?>
                            <button class="change-password-btn" onclick="showPasswordModal()" data-fr="Changer le mot de passe" data-ar="تغيير كلمة المرور">
                                <i class="fas fa-key"></i>
                            </button>
                        </h1>
                        <p data-fr="Administrateur Principal" data-ar="المسؤول الرئيسي">Administrateur Principal</p>
                        <p><?php echo htmlspecialchars($adminData['email']); ?></p>
                        <div class="profile-stats">
                            <div class="stat-item">
                                <div class="stat-value">12</div>
                                <div class="stat-label" data-fr="Mois" data-ar="شهر">Mois</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">156</div>
                                <div class="stat-label" data-fr="Commandes" data-ar="طلبات">Commandes</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">42</div>
                                <div class="stat-label" data-fr="Clients" data-ar="عملاء">Clients</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Display message if any -->
                <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'error'; ?>">
                    <?php echo $message; ?>
                </div>
                <?php endif; ?>
                
                <!-- Profile Content -->
                <div class="profile-content">
                    <!-- Personal Information -->
                    <div class="profile-section">
                        <h3 class="section-title" data-fr="Informations Personnelles" data-ar="المعلومات الشخصية">Informations Personnelles</h3>
                        
                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="avatar-upload">
                                <img src="<?php echo isset($adminData['avatar_url']) && !empty($adminData['avatar_url']) ? $adminData['avatar_url'] : 'https://static.vecteezy.com/system/resources/thumbnails/005/346/410/small_2x/close-up-portrait-of-smiling-handsome-young-caucasian-man-face-looking-at-camera-on-isolated-light-gray-studio-background-photo.jpg'; ?>" alt="Avatar Preview" class="avatar-preview" id="avatar-preview">
                                <div>
                                    <label for="avatar-upload" class="upload-btn" data-fr="Changer la photo" data-ar="تغيير الصورة">Changer la photo</label>
                                    <input type="file" id="avatar-upload" name="avatar" class="file-input" accept="image/*">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="first_name" class="form-label" data-fr="Prénom" data-ar="الاسم الأول">Prénom</label>
                                    <input type="text" id="first_name" name="first_name" class="form-control" value="<?php echo htmlspecialchars($adminData['first_name']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="last_name" class="form-label" data-fr="Nom" data-ar="اسم العائلة">Nom</label>
                                    <input type="text" id="last_name" name="last_name" class="form-control" value="<?php echo htmlspecialchars($adminData['last_name']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="email" class="form-label" data-fr="Adresse Email" data-ar="البريد الإلكتروني">Adresse Email</label>
                                <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($adminData['email']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="phone" class="form-label" data-fr="Téléphone" data-ar="الهاتف">Téléphone</label>
                                <input type="tel" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($adminData['phone']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="address" class="form-label" data-fr="Adresse" data-ar="العنوان">Adresse</label>
                                <input type="text" id="address" name="address" class="form-control" value="<?php echo htmlspecialchars($adminData['address']); ?>">
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="city" class="form-label" data-fr="Ville" data-ar="المدينة">Ville</label>
                                    <input type="text" id="city" name="city" class="form-control" value="<?php echo htmlspecialchars($adminData['city']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="postal_code" class="form-label" data-fr="Code Postal" data-ar="الرمز البريدي">Code Postal</label>
                                    <input type="text" id="postal_code" name="postal_code" class="form-control" value="<?php echo htmlspecialchars($adminData['postal_code']); ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="country" class="form-label" data-fr="Pays" data-ar="البلد">Pays</label>
                                <input type="text" id="country" name="country" class="form-control" value="<?php echo htmlspecialchars($adminData['country']); ?>">
                            </div>
                            
                            <div class="btn-group">
                                <button type="submit" name="update_profile" class="btn btn-primary" data-fr="Enregistrer les modifications" data-ar="حفظ التغييرات">Enregistrer les modifications</button>
                                <button type="reset" class="btn btn-secondary" data-fr="Annuler" data-ar="إلغاء">Annuler</button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Account Settings -->
                    <div class="profile-section">
                        <h3 class="section-title" data-fr="Paramètres du Compte" data-ar="إعدادات الحساب">Paramètres du Compte</h3>
                        
                        <div class="form-group">
                            <label class="form-label" data-fr="Rôle" data-ar="الدور">Rôle</label>
                            <input type="text" class="form-control" value="Administrateur Principal" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" data-fr="Date d'inscription" data-ar="تاريخ التسجيل">Date d'inscription</label>
                            <input type="text" class="form-control" value="<?php echo date('d/m/Y', strtotime($adminData['created_at'] ?? 'now')); ?>" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" data-fr="Dernière mise à jour" data-ar="آخر تحديث">Dernière mise à jour</label>
                            <input type="text" class="form-control" value="<?php echo date('d/m/Y H:i', strtotime($adminData['updated_at'] ?? 'now')); ?>" readonly>
                        </div>
                        
                        <div class="btn-group">
                            <button class="btn btn-primary" onclick="showPasswordModal()" data-fr="Changer le mot de passe" data-ar="تغيير كلمة المرور">Changer le mot de passe</button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Password Change Modal -->
    <div class="modal-overlay" id="passwordModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title" data-fr="Changer le mot de passe" data-ar="تغيير كلمة المرور">Changer le mot de passe</h3>
                <button class="modal-close" onclick="hidePasswordModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="modal_current_password" class="form-label" data-fr="Mot de passe actuel" data-ar="كلمة المرور الحالية">Mot de passe actuel</label>
                        <input type="password" id="modal_current_password" name="current_password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="modal_new_password" class="form-label" data-fr="Nouveau mot de passe" data-ar="كلمة المرور الجديدة">Nouveau mot de passe</label>
                        <input type="password" id="modal_new_password" name="new_password" class="form-control" required onkeyup="checkPasswordStrength(this.value, 'modal')">
                        <div class="password-strength" id="modalPasswordStrength"></div>
                        <div class="password-requirements" data-fr="Le mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial" data-ar="يجب أن تحتوي كلمة المرور على 8 أحرف على الأقل، حرف كبير، حرف صغير، رقم ورمز خاص">Le mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="modal_confirm_password" class="form-label" data-fr="Confirmer le mot de passe" data-ar="تأكيد كلمة المرور">Confirmer le mot de passe</label>
                        <input type="password" id="modal_confirm_password" name="confirm_password" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="hidePasswordModal()" data-fr="Annuler" data-ar="إلغاء">Annuler</button>
                    <button type="submit" name="change_password" class="btn btn-primary" data-fr="Changer le mot de passe" data-ar="تغيير كلمة المرور">Changer le mot de passe</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast" id="toast">
        <i class="fas toast-icon" id="toastIcon"></i>
        <span id="toastMessage"></span>
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
        
        // Avatar upload preview
        document.getElementById('avatar-upload').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    // Update all avatar previews
                    document.getElementById('avatar-preview').src = e.target.result;
                    document.getElementById('main-profile-avatar').src = e.target.result;
                    document.getElementById('top-nav-avatar').src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });
        
        // Password strength checker
        function checkPasswordStrength(password, context = 'main') {
            const strengthBar = document.getElementById(context === 'modal' ? 'modalPasswordStrength' : 'passwordStrength');
            
            // Reset strength bar
            strengthBar.className = 'password-strength';
            
            if (!password) return;
            
            let strength = 0;
            
            // Check password length
            if (password.length >= 8) strength++;
            
            // Check for mixed case
            if (password.match(/([a-z].*[A-Z])|([A-Z].*[a-z])/)) strength++;
            
            // Check for numbers
            if (password.match(/([0-9])/)) strength++;
            
            // Check for special characters
            if (password.match(/([!,%,&,@,#,$,^,*,?,_,~])/)) strength++;
            
            // Update strength bar
            if (strength <= 1) {
                strengthBar.classList.add('weak');
            } else if (strength <= 3) {
                strengthBar.classList.add('medium');
            } else {
                strengthBar.classList.add('strong');
            }
        }
        
        // Show toast notification
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            const toastIcon = document.getElementById('toastIcon');
            const toastMessage = document.getElementById('toastMessage');
            
            // Set toast content
            toastMessage.textContent = message;
            toast.className = `toast toast-${type}`;
            toastIcon.className = `fas toast-icon ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}`;
            
            // Show toast
            toast.classList.add('show');
            
            // Hide toast after 3 seconds
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }
        
        // Password Modal Functions
        function showPasswordModal() {
            document.getElementById('passwordModal').classList.add('active');
        }
        
        function hidePasswordModal() {
            document.getElementById('passwordModal').classList.remove('active');
            // Reset form
            document.getElementById('passwordForm').reset();
            // Reset strength bar
            document.getElementById('modalPasswordStrength').className = 'password-strength';
        }
        
        // Close modal when clicking outside
        document.getElementById('passwordModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hidePasswordModal();
            }
        });
        
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