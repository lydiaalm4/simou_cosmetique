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

// Add new category
if (isset($_POST['add_category'])) {
    $name_fr = $_POST['name_fr'];
    $name_ar = $_POST['name_ar'];
    $description_fr = $_POST['description_fr'];
    $description_ar = $_POST['description_ar'];
    $parent_id = !empty($_POST['parent_id']) ? $_POST['parent_id'] : NULL;
    
    // Handle image upload
    $image_url = NULL;
    if (isset($_FILES['category_image']) && $_FILES['category_image']['error'] === 0) {
        $uploadDir = 'uploads/categories/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileName = time() . '_' . basename($_FILES['category_image']['name']);
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['category_image']['tmp_name'], $targetPath)) {
            $image_url = $targetPath;
        }
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO categories (name_fr, name_ar, description_fr, description_ar, image_url, parent_id) 
                              VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name_fr, $name_ar, $description_fr, $description_ar, $image_url, $parent_id]);
        
        $message = "Catégorie ajoutée avec succès!";
        $messageType = "success";
    } catch (PDOException $e) {
        $message = "Erreur lors de l'ajout de la catégorie: " . $e->getMessage();
        $messageType = "error";
    }
}

// Update category
if (isset($_POST['update_category'])) {
    $category_id = $_POST['category_id'];
    $name_fr = $_POST['name_fr'];
    $name_ar = $_POST['name_ar'];
    $description_fr = $_POST['description_fr'];
    $description_ar = $_POST['description_ar'];
    $parent_id = !empty($_POST['parent_id']) ? $_POST['parent_id'] : NULL;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Handle image upload
    $image_url = $_POST['current_image'];
    if (isset($_FILES['category_image']) && $_FILES['category_image']['error'] === 0) {
        $uploadDir = 'uploads/categories/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileName = time() . '_' . basename($_FILES['category_image']['name']);
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['category_image']['tmp_name'], $targetPath)) {
            // Delete old image if exists
            if ($image_url && file_exists($image_url)) {
                unlink($image_url);
            }
            $image_url = $targetPath;
        }
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE categories SET name_fr = ?, name_ar = ?, description_fr = ?, 
                              description_ar = ?, image_url = ?, parent_id = ?, is_active = ?, 
                              updated_at = CURRENT_TIMESTAMP WHERE category_id = ?");
        $stmt->execute([$name_fr, $name_ar, $description_fr, $description_ar, $image_url, $parent_id, $is_active, $category_id]);
        
        $message = "Catégorie mise à jour avec succès!";
        $messageType = "success";
    } catch (PDOException $e) {
        $message = "Erreur lors de la mise à jour de la catégorie: " . $e->getMessage();
        $messageType = "error";
    }
}

// Delete category
if (isset($_GET['delete_category'])) {
    $category_id = $_GET['delete_category'];
    
    try {
        // Check if category has products
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
        $stmt->execute([$category_id]);
        $productCount = $stmt->fetchColumn();
        
        // Check if category has subcategories
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE parent_id = ?");
        $stmt->execute([$category_id]);
        $subcategoryCount = $stmt->fetchColumn();
        
        if ($productCount > 0 || $subcategoryCount > 0) {
            $message = "Impossible de supprimer cette catégorie. Elle contient des produits ou des sous-catégories.";
            $messageType = "error";
        } else {
            // Get image path to delete
            $stmt = $pdo->prepare("SELECT image_url FROM categories WHERE category_id = ?");
            $stmt->execute([$category_id]);
            $category = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Delete category
            $stmt = $pdo->prepare("DELETE FROM categories WHERE category_id = ?");
            $stmt->execute([$category_id]);
            
            // Delete image file if exists
            if ($category['image_url'] && file_exists($category['image_url'])) {
                unlink($category['image_url']);
            }
            
            $message = "Catégorie supprimée avec succès!";
            $messageType = "success";
        }
    } catch (PDOException $e) {
        $message = "Erreur lors de la suppression de la catégorie: " . $e->getMessage();
        $messageType = "error";
    }
}

// Get all categories
try {
    $stmt = $pdo->query("SELECT c1.*, c2.name_fr as parent_name_fr, c2.name_ar as parent_name_ar 
                         FROM categories c1 
                         LEFT JOIN categories c2 ON c1.parent_id = c2.category_id 
                         ORDER BY c1.parent_id, c1.name_fr");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categories = [];
}

// Get main categories (for parent selection)
try {
    $stmt = $pdo->query("SELECT category_id, name_fr, name_ar FROM categories WHERE parent_id IS NULL");
    $mainCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mainCategories = [];
}

// Get category for editing
$editCategory = null;
if (isset($_GET['edit_category'])) {
    $category_id = $_GET['edit_category'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE category_id = ?");
        $stmt->execute([$category_id]);
        $editCategory = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $message = "Erreur lors du chargement de la catégorie: " . $e->getMessage();
        $messageType = "error";
    }
}

// Cancel edit
if (isset($_GET['cancel_edit'])) {
    $editCategory = null;
    header("Location: categories.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Catégories - Cosmetique SIMOU</title>
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
        
        .categories-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }
        
        .categories-table th {
            text-align: left;
            padding: 12px 15px;
            font-weight: 600;
            color: var(--secondary);
            border-bottom: 2px solid var(--accent);
        }
        
        .categories-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }
        
        .category-image {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
        }
        
        .category-name {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .category-description {
            font-size: 0.8rem;
            color: #888;
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .category-parent {
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
                <a href="categorie.php" class="menu-item active">
                    <div class="menu-icon"><i class="fas fa-tags"></i></div>
                    <div class="menu-text">Catégories</div>
                    <div class="menu-badge"><?php echo count($categories); ?></div>
                </a>
                <a href="addproduit.php" class="menu-item">
                    <div class="menu-icon"><i class="fas fa-box-open"></i></div>
                    <div class="menu-text">Produits</div>
                    <div class="menu-badge">15</div>
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
                        <h1 class="title">Gestion des Catégories</h1>
                    </div>
                </div>
                
                <!-- Message Display -->
                <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
                <?php endif; ?>
                
                <div class="dashboard-grid">
                    <!-- Add/Edit Category Form -->
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">
                                <?php echo $editCategory ? 'Modifier la Catégorie' : 'Ajouter une Catégorie'; ?>
                            </h2>
                        </div>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <?php if ($editCategory): ?>
                            <input type="hidden" name="category_id" value="<?php echo $editCategory['category_id']; ?>">
                            <input type="hidden" name="current_image" value="<?php echo $editCategory['image_url']; ?>">
                            <?php endif; ?>
                            
                            <div class="form-group">
                                <label class="form-label" for="name_fr">Nom (Français)</label>
                                <input type="text" class="form-control" id="name_fr" name="name_fr" 
                                       value="<?php echo $editCategory ? htmlspecialchars($editCategory['name_fr']) : ''; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="name_ar">Nom (Arabe)</label>
                                <input type="text" class="form-control" id="name_ar" name="name_ar" 
                                       value="<?php echo $editCategory ? htmlspecialchars($editCategory['name_ar']) : ''; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="description_fr">Description (Français)</label>
                                <textarea class="form-control" id="description_fr" name="description_fr"><?php echo $editCategory ? htmlspecialchars($editCategory['description_fr']) : ''; ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="description_ar">Description (Arabe)</label>
                                <textarea class="form-control" id="description_ar" name="description_ar"><?php echo $editCategory ? htmlspecialchars($editCategory['description_ar']) : ''; ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="parent_id">Catégorie Parente</label>
                                <select class="form-control" id="parent_id" name="parent_id">
                                    <option value="">Aucune (Catégorie Principale)</option>
                                    <?php foreach ($mainCategories as $cat): 
                                        if ($editCategory && $cat['category_id'] == $editCategory['category_id']) continue;
                                    ?>
                                    <option value="<?php echo $cat['category_id']; ?>" 
                                        <?php if ($editCategory && $editCategory['parent_id'] == $cat['category_id']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($cat['name_fr']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="category_image">Image de la Catégorie</label>
                                <input type="file" class="form-control" id="category_image" name="category_image" accept="image/*">
                                
                                <?php if ($editCategory && $editCategory['image_url']): ?>
                                <div class="image-preview">
                                    <img src="<?php echo $editCategory['image_url']; ?>" alt="Current Image">
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($editCategory): ?>
                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="is_active" name="is_active" 
                                           <?php if ($editCategory['is_active']) echo 'checked'; ?>>
                                    <label class="form-label" for="is_active">Catégorie Active</label>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="form-group">
                                <?php if ($editCategory): ?>
                                <button type="submit" class="btn btn-success" name="update_category">
                                    <i class="fas fa-save"></i> Mettre à jour
                                </button>
                                <a href="?cancel_edit" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Annuler
                                </a>
                                <?php else: ?>
                                <button type="submit" class="btn btn-primary" name="add_category">
                                    <i class="fas fa-plus"></i> Ajouter la Catégorie
                                </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Categories List -->
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Liste des Catégories</h2>
                            <span class="badge"><?php echo count($categories); ?> catégories</span>
                        </div>
                        
                        <?php if (count($categories) > 0): ?>
                        <div class="table-responsive">
                            <table class="categories-table">
                                <thead>
                                    <tr>
                                        <th>Image</th>
                                        <th>Nom</th>
                                        <th>Description</th>
                                        <th>Parent</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categories as $category): ?>
                                    <tr>
                                        <td>
                                            <?php if ($category['image_url']): ?>
                                            <img src="<?php echo $category['image_url']; ?>" alt="<?php echo htmlspecialchars($category['name_fr']); ?>" class="category-image">
                                            <?php else: ?>
                                            <div style="width: 60px; height: 60px; background: #f0f0f0; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-image" style="color: #ccc;"></i>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="category-name"><?php echo htmlspecialchars($category['name_fr']); ?></div>
                                            <div class="category-name" style="font-size: 0.8rem; color: #666;"><?php echo htmlspecialchars($category['name_ar']); ?></div>
                                        </td>
                                        <td>
                                            <div class="category-description"><?php echo htmlspecialchars($category['description_fr']); ?></div>
                                            <div class="category-description"><?php echo htmlspecialchars($category['description_ar']); ?></div>
                                        </td>
                                        <td>
                                            <div class="category-parent"><?php echo $category['parent_name_fr'] ? htmlspecialchars($category['parent_name_fr']) : '—'; ?></div>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo $category['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                                <?php echo $category['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="?edit_category=<?php echo $category['category_id']; ?>" class="btn btn-success btn-sm">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?php echo $category['category_id']; ?>, '<?php echo addslashes($category['name_fr']); ?>')">
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
                            <i class="fas fa-folder-open"></i>
                            <p>Aucune catégorie trouvée</p>
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
        
        // Confirm category deletion
        function confirmDelete(categoryId, categoryName) {
            document.getElementById('deleteModalContent').innerHTML = `
                <p>Êtes-vous sûr de vouloir supprimer la catégorie "<strong>${categoryName}</strong>" ?</p>
                <p class="text-danger">Cette action est irréversible !</p>
            `;
            
            document.getElementById('confirmDeleteBtn').href = `?delete_category=${categoryId}`;
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
            const imageInput = document.getElementById('category_image');
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