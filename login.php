<?php
session_start();

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

// Check if user is already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

// Handle login form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        try {
            // Check if user exists and is admin
            $stmt = $pdo->prepare("
                SELECT user_id, email, password_hash, first_name, last_name, is_admin, avatar_url 
                FROM users 
                WHERE email = :email
            ");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Verify password
                if (password_verify($password, $user['password_hash'])) {
                    // Check if user is admin
                    if ($user['is_admin'] == 1) {
                        // Set session variables
                        $_SESSION['admin_logged_in'] = true;
                        $_SESSION['admin_id'] = $user['user_id'];
                        $_SESSION['admin_email'] = $user['email'];
                        $_SESSION['admin_name'] = $user['first_name'] . ' ' . $user['last_name'];
                        $_SESSION['admin_avatar'] = $user['avatar_url'];
                        
                        // Set cookie if "Remember me" is checked (30 days)
                        if ($remember) {
                            $cookie_value = base64_encode($user['user_id'] . ':' . hash('sha256', $user['password_hash']));
                            setcookie('admin_remember', $cookie_value, time() + (30 * 24 * 60 * 60), '/', '', false, true);
                        }
                        
                        // Redirect to dashboard
                        header('Location: admin_dashboard.php');
                        exit;
                    } else {
                        $error = 'Vous n\'avez pas les droits d\'administration.';
                    }
                } else {
                    $error = 'Email ou mot de passe incorrect.';
                }
            } else {
                $error = 'Email ou mot de passe incorrect.';
            }
        } catch (PDOException $e) {
            $error = 'Erreur lors de la connexion. Veuillez réessayer.';
        }
    }
}

// Check for remember me cookie
if (isset($_COOKIE['admin_remember']) && !isset($_SESSION['admin_logged_in'])) {
    try {
        $cookie_data = base64_decode($_COOKIE['admin_remember']);
        list($user_id, $token) = explode(':', $cookie_data);
        
        $stmt = $pdo->prepare("
            SELECT user_id, email, password_hash, first_name, last_name, is_admin, avatar_url 
            FROM users 
            WHERE user_id = :user_id AND is_admin = 1
        ");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && hash('sha256', $user['password_hash']) === $token) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $user['user_id'];
            $_SESSION['admin_email'] = $user['email'];
            $_SESSION['admin_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['admin_avatar'] = $user['avatar_url'];
            
            header('Location: admin_dashboard.php');
            exit;
        }
    } catch (Exception $e) {
        // Invalid cookie, continue to login page
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Admin - Cosmetique SIMOU</title>
    <link rel="icon" href="simou.jpg" type="image/jpg" sizes="512x512">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Montserrat:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #e83e8c;
            --secondary: #333333;
            --accent: #fedae6;
            --light: #fedae6;
            --dark: #343a40;
            --success: #28a745;
            --danger: #dc3545;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Montserrat', sans-serif;
            background: linear-gradient(135deg, #f5f5f5 0%, #fff5f9 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: var(--secondary);
        }
        
        .login-container {
            display: flex;
            max-width: 1000px;
            width: 100%;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 50px rgba(232, 62, 140, 0.15);
        }
        
        /* Left Panel - Brand/Info */
        .login-left {
            flex: 1;
            background: linear-gradient(135deg, var(--primary) 0%, #ff6b9e 100%);
            color: white;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .login-left::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 50px 50px;
            opacity: 0.3;
        }
        
        .brand-logo {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .logo-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid rgba(255,255,255,0.2);
            margin-bottom: 20px;
        }
        
        .brand-name {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .brand-tagline {
            font-size: 1rem;
            opacity: 0.9;
        }
        
        .login-features {
            margin-top: 40px;
        }
        
        .feature {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .feature-icon {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1.2rem;
        }
        
        .feature-text h4 {
            font-size: 1rem;
            margin-bottom: 5px;
        }
        
        .feature-text p {
            font-size: 0.8rem;
            opacity: 0.9;
        }
        
        /* Right Panel - Login Form */
        .login-right {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-title {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .login-subtitle {
            color: #666;
            font-size: 0.9rem;
        }
        
        /* Alerts */
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: var(--danger);
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: var(--success);
            border: 1px solid #c3e6cb;
        }
        
        .alert-icon {
            margin-right: 10px;
            font-size: 1.2rem;
        }
        
        /* Login Form */
        .login-form {
            width: 100%;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--secondary);
        }
        
        .input-group {
            position: relative;
        }
        
        .form-input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-family: 'Montserrat', sans-serif;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .form-input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(232, 62, 140, 0.1);
        }
        
        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 1.1rem;
        }
        
        .show-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            font-size: 1.1rem;
        }
        
        .show-password:hover {
            color: var(--primary);
        }
        
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            font-size: 0.9rem;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
        }
        
        .remember-check {
            margin-right: 8px;
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
        }
        
        .forgot-password {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }
        
        .forgot-password:hover {
            text-decoration: underline;
        }
        
        .login-button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--primary) 0%, #ff6b9e 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-family: 'Montserrat', sans-serif;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .login-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(232, 62, 140, 0.3);
        }
        
        .login-button:active {
            transform: translateY(0);
        }
        
        .login-footer {
            margin-top: 30px;
            text-align: center;
            font-size: 0.9rem;
            color: #666;
        }
        
        .back-to-site {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            margin-left: 5px;
        }
        
        .back-to-site:hover {
            text-decoration: underline;
        }
        
        /* Language Toggle */
        .language-toggle {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            background: rgba(255,255,255,0.2);
            border-radius: 20px;
            overflow: hidden;
        }
        
        .language-btn {
            background: none;
            border: none;
            padding: 8px 15px;
            color: white;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        
        .language-btn.active {
            background: white;
            color: var(--primary);
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
            }
            
            .login-left {
                padding: 30px;
            }
            
            .login-right {
                padding: 30px;
            }
            
            .brand-name {
                font-size: 2rem;
            }
            
            .login-title {
                font-size: 1.8rem;
            }
        }
        
        @media (max-width: 480px) {
            .login-left, .login-right {
                padding: 20px;
            }
            
            .brand-name {
                font-size: 1.8rem;
            }
            
            .login-title {
                font-size: 1.5rem;
            }
            
            .form-options {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .language-toggle {
                top: 10px;
                right: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Left Panel - Brand/Info -->
        <div class="login-left">
            <div class="language-toggle">
                <button class="language-btn active" onclick="changeLanguage('fr')">FR</button>
                <button class="language-btn" onclick="changeLanguage('ar')">AR</button>
            </div>
            
            <div class="brand-logo">
                <img src="https://scontent.falg7-1.fna.fbcdn.net/v/t39.30808-1/439901533_122139769340229532_6761088317019887005_n.jpg?stp=dst-jpg_s160x160_tt6&_nc_cat=105&ccb=1-7&_nc_sid=2d3e12&_nc_ohc=Fxb7aCvHXSEQ7kNvwFpfQBq&_nc_oc=AdmrbULiYNwMvE3foUArFV1d5RuEMRq15JyDfx5hs2kh0IMOtrwJEV12mKhEtffAZC8&_nc_zt=24&_nc_ht=scontent.falg7-1.fna&_nc_gid=UBb_oZYU8rv1dGl0gjUs2w&oh=00_AfTLEPFmCyMqmWwphOPmIHvU8z7BZ8_q9R3ZhzFPdZ3nAQ&oe=6874ABD5" 
                     alt="SIMOU Logo" class="logo-image">
                <h1 class="brand-name">COSMETIQUE SIMOU</h1>
                <p class="brand-tagline" data-fr="Administration - Portail de gestion" data-ar="الإدارة - بوابة التحكم">Administration - Portail de gestion</p>
            </div>
            
            <div class="login-features">
                <div class="feature">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="feature-text">
                        <h4 data-fr="Analyses en temps réel" data-ar="تحليلات في الوقت الحقيقي">Analyses en temps réel</h4>
                        <p data-fr="Suivez vos performances commerciales" data-ar="تابع أداء عملك">Suivez vos performances commerciales</p>
                    </div>
                </div>
                
                <div class="feature">
                    <div class="feature-icon">
                        <i class="fas fa-box-open"></i>
                    </div>
                    <div class="feature-text">
                        <h4 data-fr="Gestion des produits" data-ar="إدارة المنتجات">Gestion des produits</h4>
                        <p data-fr="Gérez votre catalogue facilement" data-ar="ادر كتالوج منتجاتك بسهولة">Gérez votre catalogue facilement</p>
                    </div>
                </div>
                
                <div class="feature">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="feature-text">
                        <h4 data-fr="Sécurité renforcée" data-ar="أمان معزز">Sécurité renforcée</h4>
                        <p data-fr="Protection des données et accès sécurisé" data-ar="حماية البيانات ووصول آمن">Protection des données et accès sécurisé</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Panel - Login Form -->
        <div class="login-right">
            <div class="login-header">
                <h2 class="login-title" data-fr="Connexion Admin" data-ar="تسجيل دخول المدير">Connexion Admin</h2>
                <p class="login-subtitle" data-fr="Accédez à votre tableau de bord d'administration" data-ar="الوصول إلى لوحة تحكم الإدارة">Accédez à votre tableau de bord d'administration</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle alert-icon"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle alert-icon"></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="login-form" id="loginForm">
                <div class="form-group">
                    <label for="email" class="form-label" data-fr="Adresse Email" data-ar="البريد الإلكتروني">Adresse Email</label>
                    <div class="input-group">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="form-input" 
                               placeholder="admin@example.com"
                               data-fr-placeholder="admin@example.com"
                               data-ar-placeholder="admin@example.com"
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                               required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label" data-fr="Mot de Passe" data-ar="كلمة المرور">Mot de Passe</label>
                    <div class="input-group">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="form-input" 
                               placeholder="••••••••"
                               data-fr-placeholder="••••••••"
                               data-ar-placeholder="••••••••"
                               required>
                        <button type="button" class="show-password" onclick="togglePassword()">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-options">
                    <div class="remember-me">
                        <input type="checkbox" 
                               id="remember" 
                               name="remember" 
                               class="remember-check"
                               <?php echo isset($_POST['remember']) ? 'checked' : ''; ?>>
                        <label for="remember" data-fr="Se souvenir de moi" data-ar="تذكرني">Se souvenir de moi</label>
                    </div>
                    <a href="#" class="forgot-password" data-fr="Mot de passe oublié ?" data-ar="نسيت كلمة المرور؟">Mot de passe oublié ?</a>
                </div>
                
                <button type="submit" class="login-button">
                    <i class="fas fa-sign-in-alt"></i>
                    <span data-fr="Se connecter" data-ar="تسجيل الدخول">Se connecter</span>
                </button>
            </form>
            
            <div class="login-footer">
                <p data-fr="Vous êtes un client ?" data-ar="هل أنت عميل ؟">
                    Vous êtes un client ?
                    <a href="../index.php" class="back-to-site" data-fr="Retour au site" data-ar="العودة للموقع">Retour au site</a>
                </p>
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
        }
        
        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = event.currentTarget.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        }
        
        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            
            if (!email || !password) {
                e.preventDefault();
                alert('Veuillez remplir tous les champs.');
                return false;
            }
            
            // Simple email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Veuillez entrer une adresse email valide.');
                return false;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('.login-button');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Connexion en cours...</span>';
            submitBtn.disabled = true;
            
            return true;
        });
        
        // Auto-focus email field on page load
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('email').focus();
            
            // Set default language to French
            changeLanguage('fr');
            
            // Check URL parameters for messages
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('logout')) {
                showToast('Déconnexion réussie.', 'success');
            }
            if (urlParams.has('expired')) {
                showToast('Session expirée. Veuillez vous reconnecter.', 'warning');
            }
        });
        
        // Simple toast notification function
        function showToast(message, type) {
            const toast = document.createElement('div');
            toast.className = `alert alert-${type}`;
            toast.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} alert-icon"></i>
                <span>${message}</span>
            `;
            toast.style.position = 'fixed';
            toast.style.top = '20px';
            toast.style.right = '20px';
            toast.style.zIndex = '10000';
            toast.style.maxWidth = '300px';
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 5000);
        }
        
        // Forgot password handler (placeholder)
        document.querySelector('.forgot-password').addEventListener('click', function(e) {
            e.preventDefault();
            const email = prompt('Entrez votre adresse email pour réinitialiser votre mot de passe:');
            if (email) {
                // In a real application, you would send a reset link here
                alert('Un lien de réinitialisation a été envoyé à ' + email);
            }
        });
    </script>
</body>
</html>