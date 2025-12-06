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

// Handle form submissions
$message = '';
$messageType = '';

// Add new product
if (isset($_POST['add_product'])) {
    $name_fr = $_POST['name_fr'];
    $name_ar = $_POST['name_ar'];
    $description_fr = $_POST['description_fr'];
    $description_ar = $_POST['description_ar'];
    $price = $_POST['price'];
    $category_id = $_POST['category_id'];
    $stock_quantity = $_POST['stock_quantity'];
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $is_bestseller = isset($_POST['is_bestseller']) ? 1 : 0;
    $is_new = isset($_POST['is_new']) ? 1 : 0;
    
    // Handle image upload
    $image_url = NULL;
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === 0) {
        $uploadDir = 'uploads/products/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileName = time() . '_' . basename($_FILES['product_image']['name']);
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['product_image']['tmp_name'], $targetPath)) {
            $image_url = $targetPath;
        }
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO products (name_fr, name_ar, description_fr, description_ar, price, category_id, stock_quantity, image_url, is_featured, is_bestseller, is_new) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name_fr, $name_ar, $description_fr, $description_ar, $price, $category_id, $stock_quantity, $image_url, $is_featured, $is_bestseller, $is_new]);
        
        $message = "Produit ajouté avec succès!";
        $messageType = "success";
    } catch (PDOException $e) {
        $message = "Erreur lors de l'ajout du produit: " . $e->getMessage();
        $messageType = "error";
    }
}

// Update product
if (isset($_POST['update_product'])) {
    $product_id = $_POST['product_id'];
    $name_fr = $_POST['name_fr'];
    $name_ar = $_POST['name_ar'];
    $description_fr = $_POST['description_fr'];
    $description_ar = $_POST['description_ar'];
    $price = $_POST['price'];
    $category_id = $_POST['category_id'];
    $stock_quantity = $_POST['stock_quantity'];
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $is_bestseller = isset($_POST['is_bestseller']) ? 1 : 0;
    $is_new = isset($_POST['is_new']) ? 1 : 0;
    
    // Handle image upload
    $image_url = $_POST['current_image'];
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === 0) {
        $uploadDir = 'uploads/products/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileName = time() . '_' . basename($_FILES['product_image']['name']);
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['product_image']['tmp_name'], $targetPath)) {
            // Delete old image if exists
            if ($image_url && file_exists($image_url)) {
                unlink($image_url);
            }
            $image_url = $targetPath;
        }
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE products SET name_fr = ?, name_ar = ?, description_fr = ?, 
                              description_ar = ?, price = ?, category_id = ?, stock_quantity = ?, 
                              image_url = ?, is_featured = ?, is_bestseller = ?, is_new = ?, 
                              updated_at = CURRENT_TIMESTAMP WHERE product_id = ?");
        $stmt->execute([$name_fr, $name_ar, $description_fr, $description_ar, $price, $category_id, 
                       $stock_quantity, $image_url, $is_featured, $is_bestseller, $is_new, $product_id]);
        
        $message = "Produit mis à jour avec succès!";
        $messageType = "success";
    } catch (PDOException $e) {
        $message = "Erreur lors de la mise à jour du produit: " . $e->getMessage();
        $messageType = "error";
    }
}

// Delete product
if (isset($_GET['delete_product'])) {
    $product_id = $_GET['delete_product'];
    
    try {
        // Check if product has orders
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $orderCount = $stmt->fetchColumn();
        
        if ($orderCount > 0) {
            $message = "Impossible de supprimer ce produit. Il est associé à des commandes.";
            $messageType = "error";
        } else {
            // Get image path to delete
            $stmt = $pdo->prepare("SELECT image_url FROM products WHERE product_id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Delete product
            $stmt = $pdo->prepare("DELETE FROM products WHERE product_id = ?");
            $stmt->execute([$product_id]);
            
            // Delete image file if exists
            if ($product['image_url'] && file_exists($product['image_url'])) {
                unlink($product['image_url']);
            }
            
            $message = "Produit supprimé avec succès!";
            $messageType = "success";
        }
    } catch (PDOException $e) {
        $message = "Erreur lors de la suppression du produit: " . $e->getMessage();
        $messageType = "error";
    }
}

// Get all products with category names
try {
    $stmt = $pdo->query("SELECT p.*, c.name_fr as category_name_fr, c.name_ar as category_name_ar 
                         FROM products p 
                         LEFT JOIN categories c ON p.category_id = c.category_id 
                         ORDER BY p.created_at DESC");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $products = [];
}

// Get all categories
try {
    $stmt = $pdo->query("SELECT category_id, name_fr, name_ar FROM categories WHERE is_active = 1 ORDER BY name_fr");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categories = [];
}

// Get product for editing
$editProduct = null;
if (isset($_GET['edit_product'])) {
    $product_id = $_GET['edit_product'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $editProduct = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $message = "Erreur lors du chargement du produit: " . $e->getMessage();
        $messageType = "error";
    }
}

// Cancel edit
if (isset($_GET['cancel_edit'])) {
    $editProduct = null;
    header("Location: products.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Produits - Cosmetique SIMOU</title>
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
            left: 0;
            height: 100%;
            width: 4px;
            background: var(--primary);
        }
        
        .menu-icon {
            margin-right: 12px;
            font-size: 1.1rem;
            width: 24px;
            text-align: center;
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
        
        /* Main Content */
        .dashboard-content {
            flex: 1;
            transition: all 0.3s;
            width: 100%;
            margin-left: 230px;
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
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--accent);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--accent);
        }
        
        .logo-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary);
        }
        
        .title {
            font-family: 'Playfair Display', serif;
            color: var(--primary);
            font-size: 2rem;
        }
        
        .message {
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-weight: 500;
        }
        
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        @media (max-width: 992px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .dashboard-sidebar {
                transform: translateX(-100%);
            }
            
            .dashboard-content {
                margin-left: 0;
            }
            
            .menu-toggle {
                display: block;
            }
        }
        
        .card {
            background: white;
            border-radius: 15px;
            padding: 25px;
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
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .card-title {
            font-family: 'Playfair Display', serif;
            color: var(--primary);
            font-size: 1.5rem;
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
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-check {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .form-check-input {
            width: 18px;
            height: 18px;
        }
        
        .image-preview {
            margin-top: 10px;
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .image-preview img {
            width: 100%;
            height: auto;
            object-fit: cover;
        }
        
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            font-family: 'Montserrat', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 8px;
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
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .btn-sm {
            padding: 8px 15px;
            font-size: 0.9rem;
        }
        
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .products-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }
        
        .products-table th {
            text-align: left;
            padding: 12px 15px;
            font-weight: 600;
            color: var(--secondary);
            border-bottom: 2px solid var(--accent);
        }
        
        .products-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
        }
        
        .product-name {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .product-description {
            font-size: 0.8rem;
            color: #888;
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .product-price {
            font-weight: 600;
            color: var(--primary);
        }
        
        .product-category {
            font-size: 0.8rem;
            color: #666;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .feature-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 15px;
            font-size: 0.6rem;
            font-weight: 500;
            margin-right: 5px;
            margin-bottom: 5px;
        }
        
        .featured-badge {
            background: #fff3cd;
            color: #856404;
        }
        
        .bestseller-badge {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .new-badge {
            background: #d4edda;
            color: #155724;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #888;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #ddd;
        }
        
        .empty-state p {
            font-size: 1.1rem;
        }
        
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
            max-width: 500px;
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
        
        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="dashboard-sidebar active">
            <div class="sidebar-header">
                <img src="simou.jpg" alt="SIMOU Logo" class="sidebar-logo">
                <div class="sidebar-title"></div>
            </div>
            
            <div class="sidebar-menu">
                <div class="menu-category">Principal</div>
                <a href="dashboard.php" class="menu-item">
                    <div class="menu-icon"><i class="fas fa-home"></i></div>
                    <div class="menu-text">Tableau de Bord</div>
                </a>
                
                <div class="menu-category">Gestion</div>
                <a href="categorie.php" class="menu-item">
                    <div class="menu-icon"><i class="fas fa-tags"></i></div>
                    <div class="menu-text">Catégories</div>
                </a>
                <a href="addproduit.php" class="menu-item active">
                    <div class="menu-icon"><i class="fas fa-box-open"></i></div>
                    <div class="menu-text">Produits</div>
                    <div class="menu-badge"><?php echo count($products); ?></div>
                </a>
                <a href="commande.php" class="menu-item">
                    <div class="menu-icon"><i class="fas fa-shopping-cart"></i></div>
                    <div class="menu-text">Commandes</div>
                    <div class="menu-badge">8</div>
                </a>
                <a href="clientsfideles.php" class="menu-item">
                    <div class="menu-icon"><i class="fas fa-users"></i></div>
                    <div class="menu-text">Clients</div>
                </a>
                
                <div class="menu-category">Analyse</div>
                <a href="dashboard.php" class="menu-item">
                    <div class="menu-icon"><i class="fas fa-chart-line"></i></div>
                    <div class="menu-text">Ventes</div>
                </a>
                
                
                <div class="menu-category">Personnel</div>
                
                <a href="profil.php" class="menu-item ">
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
                
                <div class="user-menu">
                    <img src="simou.jpg" 
                         alt="User" class="user-avatar">
                    <div>
                        <div class="user-name">Oussama</div>
                        <div class="user-role">Administrateur</div>
                    </div>
                </div>
            </nav>
            
            <div class="container">
                <!-- Header -->
                <div class="header">
                    <div class="logo-title">
                        <img src="simou.jpg" 
                             alt="SIMOU Logo" class="logo">
                        <h1 class="title">Gestion des Produits</h1>
                    </div>
                </div>
                
                <!-- Message Display -->
                <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
                <?php endif; ?>
                
                <div class="dashboard-grid">
                    <!-- Add/Edit Product Form -->
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">
                                <?php echo $editProduct ? 'Modifier le Produit' : 'Ajouter un Produit'; ?>
                            </h2>
                        </div>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <?php if ($editProduct): ?>
                            <input type="hidden" name="product_id" value="<?php echo $editProduct['product_id']; ?>">
                            <input type="hidden" name="current_image" value="<?php echo $editProduct['image_url']; ?>">
                            <?php endif; ?>
                            
                            <div class="form-group">
                                <label class="form-label" for="name_fr">Nom (Français)</label>
                                <input type="text" class="form-control" id="name_fr" name="name_fr" 
                                       value="<?php echo $editProduct ? htmlspecialchars($editProduct['name_fr']) : ''; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="name_ar">Nom (Arabe)</label>
                                <input type="text" class="form-control" id="name_ar" name="name_ar" 
                                       value="<?php echo $editProduct ? htmlspecialchars($editProduct['name_ar']) : ''; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="description_fr">Description (Français)</label>
                                <textarea class="form-control" id="description_fr" name="description_fr"><?php echo $editProduct ? htmlspecialchars($editProduct['description_fr']) : ''; ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="description_ar">Description (Arabe)</label>
                                <textarea class="form-control" id="description_ar" name="description_ar"><?php echo $editProduct ? htmlspecialchars($editProduct['description_ar']) : ''; ?></textarea>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="price">Prix (DZD)</label>
                                    <input type="number" step="0.01" class="form-control" id="price" name="price" 
                                           value="<?php echo $editProduct ? $editProduct['price'] : ''; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="stock_quantity">Quantité en Stock</label>
                                    <input type="number" class="form-control" id="stock_quantity" name="stock_quantity" 
                                           value="<?php echo $editProduct ? $editProduct['stock_quantity'] : '0'; ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="category_id">Catégorie</label>
                                <select class="form-control" id="category_id" name="category_id" required>
                                    <option value="">Sélectionnez une catégorie</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['category_id']; ?>" 
                                        <?php if ($editProduct && $editProduct['category_id'] == $cat['category_id']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($cat['name_fr']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="product_image">Image du Produit</label>
                                <input type="file" class="form-control" id="product_image" name="product_image" accept="image/*">
                                
                                <?php if ($editProduct && $editProduct['image_url']): ?>
                                <div class="image-preview">
                                    <img src="<?php echo $editProduct['image_url']; ?>" alt="Current Image">
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Options du Produit</label>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="is_featured" name="is_featured" 
                                           <?php if ($editProduct && $editProduct['is_featured']) echo 'checked'; ?>>
                                    <label class="form-label" for="is_featured">Produit en Vedette</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="is_bestseller" name="is_bestseller" 
                                           <?php if ($editProduct && $editProduct['is_bestseller']) echo 'checked'; ?>>
                                    <label class="form-label" for="is_bestseller">Meilleure Vente</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="is_new" name="is_new" 
                                           <?php if ($editProduct && $editProduct['is_new']) echo 'checked'; ?>>
                                    <label class="form-label" for="is_new">Nouveau Produit</label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <?php if ($editProduct): ?>
                                <button type="submit" class="btn btn-success" name="update_product">
                                    <i class="fas fa-save"></i> Mettre à jour
                                </button>
                                <a href="?cancel_edit" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Annuler
                                </a>
                                <?php else: ?>
                                <button type="submit" class="btn btn-primary" name="add_product">
                                    <i class="fas fa-plus"></i> Ajouter le Produit
                                </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Products List -->
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Liste des Produits</h2>
                            <span class="badge"><?php echo count($products); ?> produits</span>
                        </div>
                        
                        <?php if (count($products) > 0): ?>
                        <div class="table-responsive">
                            <table class="products-table">
                                <thead>
                                    <tr>
                                        <th>Image</th>
                                        <th>Nom</th>
                                        <th>Prix</th>
                                        <th>Catégorie</th>
                                        <th>Stock</th>
                                        <th>Options</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td>
                                            <?php if ($product['image_url']): ?>
                                            <img src="<?php echo $product['image_url']; ?>" alt="<?php echo htmlspecialchars($product['name_fr']); ?>" class="product-image">
                                            <?php else: ?>
                                            <div style="width: 60px; height: 60px; background: #f0f0f0; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-image" style="color: #ccc;"></i>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="product-name"><?php echo htmlspecialchars($product['name_fr']); ?></div>
                                            <div class="product-name" style="font-size: 0.8rem; color: #666;"><?php echo htmlspecialchars($product['name_ar']); ?></div>
                                            <div class="product-description"><?php echo htmlspecialchars($product['description_fr']); ?></div>
                                        </td>
                                        <td>
                                            <div class="product-price"><?php echo number_format($product['price'], 2, ',', ' '); ?> DZD</div>
                                        </td>
                                        <td>
                                            <div class="product-category"><?php echo $product['category_name_fr'] ? htmlspecialchars($product['category_name_fr']) : '—'; ?></div>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo $product['stock_quantity'] > 0 ? 'status-active' : 'status-inactive'; ?>">
                                                <?php echo $product['stock_quantity']; ?> en stock
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($product['is_featured']): ?>
                                            <span class="feature-badge featured-badge">Vedette</span>
                                            <?php endif; ?>
                                            <?php if ($product['is_bestseller']): ?>
                                            <span class="feature-badge bestseller-badge">Meilleure Vente</span>
                                            <?php endif; ?>
                                            <?php if ($product['is_new']): ?>
                                            <span class="feature-badge new-badge">Nouveau</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="?edit_product=<?php echo $product['product_id']; ?>" class="btn btn-success btn-sm">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?php echo $product['product_id']; ?>, '<?php echo addslashes($product['name_fr']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-box-open"></i>
                            <p>Aucun produit trouvé</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Confirmer la Suppression</h3>
                <button class="close-modal" onclick="closeModal('deleteModal')">&times;</button>
            </div>
            <div id="deleteModalContent">
                <!-- Content will be filled by JavaScript -->
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeModal('deleteModal')">Annuler</button>
                <a id="confirmDeleteBtn" class="btn btn-danger">Supprimer</a>
            </div>
        </div>
    </div>

    <script>
        // Toggle sidebar on mobile
        function toggleSidebar() {
            const sidebar = document.querySelector('.dashboard-sidebar');
            sidebar.classList.toggle('active');
        }
        
        // Confirm product deletion
        function confirmDelete(productId, productName) {
            document.getElementById('deleteModalContent').innerHTML = `
                <p>Êtes-vous sûr de vouloir supprimer le produit "<strong>${productName}</strong>" ?</p>
                <p class="text-danger">Cette action est irréversible !</p>
            `;
            
            document.getElementById('confirmDeleteBtn').href = `?delete_product=${productId}`;
            openModal('deleteModal');
        }
        
        // Modal functions
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
        };
        
        // Image preview for file input
        document.addEventListener('DOMContentLoaded', function() {
            const imageInput = document.getElementById('product_image');
            if (imageInput) {
                imageInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            // Remove existing preview if any
                            let preview = document.querySelector('.image-preview');
                            if (!preview) {
                                preview = document.createElement('div');
                                preview.className = 'image-preview';
                                imageInput.parentNode.appendChild(preview);
                            }
                            
                            preview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
        });

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
    </script>
</body>
</html>