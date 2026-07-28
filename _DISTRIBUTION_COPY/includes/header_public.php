<?php
// User Stats & Newsletter Check
$is_logged = isset($_SESSION['user_id']);
$user_display = [];
$show_newsletter_alert = false;

if ($is_logged) {
    // Fetch detailed info - last_login will be added after DB migration
    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $uData = $stmt->fetch();

    $user_display['name'] = explode(' ', trim($uData['name']))[0]; // First Name
    $user_display['last_login'] = 'Agora'; // Placeholder until DB is updated

    // Check Newsletter Subscription
    if (!empty($uData['email']) && strpos($uData['email'], '@lead.fightarcade') === false) {
        try {
            $chkNews = $pdo->prepare("SELECT id FROM newsletter_subscribers WHERE email = ?");
            $chkNews->execute([$uData['email']]);
            if (!$chkNews->fetch()) {
                $show_newsletter_alert = true;
            }
        } catch (Exception $e) {
            // Table might not exist, ignore
        }
    }
}
?>

<?php if ($show_newsletter_alert): ?>
    <div id="newsletter-alert"
        style="background:#8e44ad; color:white; text-align:center; padding:8px; font-size:0.9rem; position:relative;">
        🚀 <b><?php echo $user_display['name']; ?></b>, receba ofertas exclusivas!
        <a href="profile.php#newsletter" style="color:yellow; font-weight:bold; text-decoration:underline;">Cadastrar na
            Newsletter</a>
        <span onclick="document.getElementById('newsletter-alert').style.display='none'"
            style="position:absolute; right:15px; top:8px; cursor:pointer;">&times;</span>
    </div>
<?php endif; ?>

<header>
    <div class="container nav-wrapper">
        <div class="logo">
            <a href="<?php echo BASE_URL; ?>/">
                <?php if (file_exists(__DIR__ . '/../assets/logo.png')): ?>
                    <img src="<?php echo BASE_URL; ?>/assets/logo.png?v=<?php echo time(); ?>" alt="Fight Arcade"
                        style="max-height:50px; width:auto; vertical-align:middle;">
                <?php else: ?>
                    Fight<span>Arcade</span>
                <?php endif; ?>
            </a>
        </div>
        <nav class="nav-links">
            <a href="<?php echo BASE_URL; ?>/">🎮 CATÁLOGO</a>
            <?php if ($is_logged): ?>

                <!-- USER INFO -->
                <div style="display:inline-block; text-align:right; margin-right:15px; font-size:0.85rem; line-height:1.2;">
                    <div style="color:var(--accent); font-weight:bold;">Olá,
                        <?php echo htmlspecialchars($user_display['name']); ?>!
                    </div>
                    <div style="color:var(--text-muted); font-size:0.7rem;">Login:
                        <?php echo $user_display['last_login']; ?>
                    </div>
                </div>

                <?php if (!empty($_SESSION['is_lead'])): ?>
                    <a href="<?php echo BASE_URL; ?>/register.php" style="background:var(--primary); color:#000;">👤
                        CADASTRAR</a>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>/my-orders.php">📦 MEUS PEDIDOS</a>
                    <a href="<?php echo BASE_URL; ?>/profile.php">👤 MINHA CONTA</a>
                <?php endif; ?>
                <a href="<?php echo BASE_URL; ?>/cart.php">🛒 CARRINHO</a>
                <a href="<?php echo BASE_URL; ?>/logout.php">🚪 SAIR</a>
            <?php else: ?>
                <a href="<?php echo BASE_URL; ?>/login.php">🔑 ENTRAR</a>
                <a href="<?php echo BASE_URL; ?>/register.php" style="background:var(--primary); color:#000;">👤
                    CADASTRAR</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<!-- Mobile Bottom Navigation Bar -->
<div class="mobile-bottom-nav">
    <a href="<?php echo BASE_URL; ?>/"
        class="bottom-nav-item <?php echo ($_SERVER['PHP_SELF'] == BASE_URL . '/index.php' || $_SERVER['PHP_SELF'] == '/index.php' || $_SERVER['PHP_SELF'] == '/') ? 'active' : ''; ?>">
        <i>🏠</i>
        <span>Início</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/cart.php"
        class="bottom-nav-item <?php echo (strpos($_SERVER['PHP_SELF'], 'cart.php') !== false) ? 'active' : ''; ?>">
        <i>🛒</i>
        <span>Carrinho</span>
    </a>
    <a href="https://wa.me/5511988121976" target="_blank" class="bottom-nav-item">
        <i>💬</i>
        <span>Suporte</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/profile.php"
        class="bottom-nav-item <?php echo (strpos($_SERVER['PHP_SELF'], 'profile.php') !== false || strpos($_SERVER['PHP_SELF'], 'login.php') !== false) ? 'active' : ''; ?>">
        <i>👤</i>
        <span>Conta</span>
    </a>
</div>