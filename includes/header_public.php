<?php
// includes/header_public.php — Helena Flores
$is_logged = isset($_SESSION['user_id']);
$user_display = [];

if ($is_logged) {
    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $uData = $stmt->fetch();
    $user_display['name'] = explode(' ', trim($uData['name'] ?? 'Cliente'))[0];
}
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/helena_theme.css?v=<?php echo time(); ?>">

<!-- Top Bar -->
<div class="top-bar">
    📍 Alameda Jaú, 1777, Jardim Paulista, SP | 📱 WhatsApp: <a href="https://wa.me/5511986727872" target="_blank">(11) 98672-7872</a>
</div>

<header>
    <div class="container nav-wrapper" style="display:flex; justify-content:space-between; align-items:center; padding:12px 0;">
        <div class="logo">
            <a href="<?php echo BASE_URL; ?>/" class="logo-text">
                🌹 Helena <span>Flores</span>
            </a>
        </div>
        <nav class="nav-links" style="display:flex; gap:12px; align-items:center;">
            <a href="<?php echo BASE_URL; ?>/">🌸 FLORES & PRESENTES</a>
            <?php if ($is_logged): ?>
                <a href="<?php echo BASE_URL; ?>/my-orders.php">📦 MEUS PEDIDOS</a>
                <a href="<?php echo BASE_URL; ?>/profile.php">👤 MINHA CONTA</a>
                <a href="<?php echo BASE_URL; ?>/cart.php" class="btn-cart">🛒 CARRINHO</a>
                <a href="<?php echo BASE_URL; ?>/logout.php" style="color:var(--hf-rose);">🚪 SAIR</a>
            <?php else: ?>
                <a href="<?php echo BASE_URL; ?>/login.php">🔑 ENTRAR</a>
                <a href="<?php echo BASE_URL; ?>/cart.php" class="btn-cart">🛒 CARRINHO</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<!-- Mobile Bottom Navigation Bar -->
<div class="mobile-bottom-nav">
    <a href="<?php echo BASE_URL; ?>/" class="bottom-nav-item active">
        <i>🌸</i>
        <span>Catálogo</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/cart.php" class="bottom-nav-item">
        <i>🛒</i>
        <span>Carrinho</span>
    </a>
    <a href="https://wa.me/5511986727872?text=Ol%C3%A1!%20Gostaria%20de%20fazer%20um%20pedido" target="_blank" class="bottom-nav-item">
        <i>💬</i>
        <span>WhatsApp</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/profile.php" class="bottom-nav-item">
        <i>👤</i>
        <span>Conta</span>
    </a>
</div>

<!-- Floating WhatsApp Button -->
<a href="https://wa.me/5511986727872?text=Ol%C3%A1!%20Vim%20pelo%20site%20da%20Helena%20Flores%20e%20gostaria%20de%20atendimento" 
   target="_blank" 
   class="whatsapp-float-helena" 
   title="Falar no WhatsApp">
   💬
</a>