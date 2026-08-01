<?php
// includes/header_public.php — Helena Flores (Estilo Giuliana Flores com Login & Cadastro Integrados)
$is_logged = isset($_SESSION['user_id']);
$user_name = '';

if ($is_logged) {
    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $uData = $stmt->fetch();
    if ($uData) {
        $user_name = explode(' ', trim($uData['name']))[0];
    } else {
        $user_name = $_SESSION['user_name'] ?? 'Cliente';
    }
}

$searchQuery = isset($_GET['q']) ? trim($_GET['q']) : '';
$currentCat = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;

// Fetch categories for bar
$allCats = [];
try {
    $allCats = $pdo->query("SELECT * FROM categories ORDER BY sort_order ASC, name ASC")->fetchAll();
} catch (Exception $e) {}

$baseUrl = defined('BASE_URL') ? BASE_URL : '';
?>
<!-- Relative CSS Import -->
<link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/helena_theme.css?v=<?php echo time(); ?>">

<!-- Giuliana Top Announcement Bar -->
<div class="gf-top-announcement">
    <span>🚚 Entrega no Mesmo Dia em São Paulo & Jardins</span>
    <span>•</span>
    <span>📱 WhatsApp: <a href="https://wa.me/5511986727872" target="_blank">(11) 98672-7872</a></span>
</div>

<!-- Header Main Area -->
<header class="gf-header">
    <div class="gf-header-container">
        <!-- Logo -->
        <a href="<?php echo $baseUrl; ?>/" class="gf-logo">
            🌹 Helena <span>Flores</span>
        </a>

        <!-- Search Bar -->
        <form action="<?php echo $baseUrl; ?>/" method="GET" class="gf-search-form">
            <input type="text" name="q" class="gf-search-input" 
                   placeholder="O que você está procurando hoje? (ex: Rosas, Buquês, Cestas)..." 
                   value="<?php echo htmlspecialchars($searchQuery); ?>">
            <button type="submit" class="gf-search-btn" title="Buscar">🔍</button>
        </form>

        <!-- Right User Actions -->
        <nav style="display:flex; gap:12px; align-items:center;">
            <?php if ($is_logged): ?>
                <a href="<?php echo $baseUrl; ?>/my-orders.php" style="color:#333; font-weight:700; text-decoration:none; font-size:0.88rem; display:flex; align-items:center; gap:4px;">
                    👤 Olá, <?php echo htmlspecialchars($user_name); ?>
                </a>
                <a href="<?php echo $baseUrl; ?>/my-orders.php" style="color:var(--gf-magenta-dark); font-weight:700; text-decoration:none; font-size:0.88rem;">
                    📦 Meus Pedidos
                </a>
                <a href="<?php echo $baseUrl; ?>/cart.php" class="gf-btn-primary" style="padding:8px 18px; font-size:0.88rem;">
                    🛒 Carrinho
                </a>
                <a href="<?php echo $baseUrl; ?>/logout.php" style="color:#999; font-weight:600; text-decoration:none; font-size:0.82rem;">
                    Sair
                </a>
            <?php else: ?>
                <a href="<?php echo $baseUrl; ?>/login.php" style="color:#333; font-weight:700; text-decoration:none; font-size:0.88rem;">
                    🔑 Entrar
                </a>
                <a href="<?php echo $baseUrl; ?>/register.php" style="color:var(--gf-magenta-dark); font-weight:700; text-decoration:none; font-size:0.88rem;">
                    📝 Cadastrar-se
                </a>
                <a href="<?php echo $baseUrl; ?>/cart.php" class="gf-btn-primary" style="padding:8px 18px; font-size:0.88rem;">
                    🛒 Carrinho
                </a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<!-- Giuliana Style Category Pills Bar -->
<div class="gf-category-bar">
    <div class="gf-category-scroll">
        <a href="<?php echo $baseUrl; ?>/" class="gf-cat-pill <?php echo !$currentCat ? 'active' : ''; ?>">
            🌹 Todas as Flores
        </a>
        <?php foreach ($allCats as $c): ?>
            <a href="<?php echo $baseUrl; ?>/?cat=<?php echo $c['id']; ?>" 
               class="gf-cat-pill <?php echo $currentCat == $c['id'] ? 'active' : ''; ?>">
               🌸 <?php echo htmlspecialchars($c['name']); ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Mobile Bottom Nav Bar -->
<div class="mobile-bottom-nav">
    <a href="<?php echo $baseUrl; ?>/" class="bottom-nav-item active">
        <i>🌸</i>
        <span>Catálogo</span>
    </a>
    <a href="<?php echo $baseUrl; ?>/cart.php" class="bottom-nav-item">
        <i>🛒</i>
        <span>Carrinho</span>
    </a>
    <a href="https://wa.me/5511986727872?text=Ol%C3%A1!%20Gostaria%20de%20fazer%20um%20pedido" target="_blank" class="bottom-nav-item">
        <i>💬</i>
        <span>WhatsApp</span>
    </a>
    <a href="<?php echo $baseUrl; ?>/<?php echo $is_logged ? 'my-orders.php' : 'login.php'; ?>" class="bottom-nav-item">
        <i>👤</i>
        <span><?php echo $is_logged ? 'Pedidos' : 'Entrar'; ?></span>
    </a>
</div>

<!-- Floating WhatsApp Button -->
<a href="https://wa.me/5511986727872?text=Ol%C3%A1!%20Vim%20pelo%20site%20da%20Helena%20Flores%20e%20gostaria%20de%20atendimento" 
   target="_blank" class="gf-floating-wa" title="Falar com Atendente no WhatsApp">
    💬
</a>