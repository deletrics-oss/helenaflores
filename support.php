<?php
/**
 * support.php — Fight Arcade
 * Portal de Suporte Público — Baseado no Cérebro da IA
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

// --- 1. AUTO-HEAL: Garante que a tabela existe ---
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_knowledge (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT,
        category VARCHAR(50) DEFAULT 'Geral',
        image_url VARCHAR(255),
        link_url VARCHAR(255),
        video_url VARCHAR(255),
        tags TEXT,
        related_products TEXT,
        ai_instructions TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {
    // Silently continue or log
}

// Busca Categorias e Itens
$search = $_GET['q'] ?? '';
$cat    = $_GET['cat'] ?? '';

try {
    $query = "SELECT * FROM ai_knowledge WHERE 1=1";
    $params = [];

    if ($search) {
        $query .= " AND (title LIKE ? OR content LIKE ? OR tags LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($cat) {
        $query .= " AND category = ?";
        $params[] = $cat;
    }

    $query .= " ORDER BY created_at DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    // Pega categorias únicas para o filtro
    $categories = $pdo->query("SELECT DISTINCT category FROM ai_knowledge")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $items = [];
    $categories = [];
    // Em caso de erro real, mostramos algo amigável se for admin
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suporte Técnico — Fight Arcade</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --bg: #0f172a;
            --card: #1e293b;
            --primary: #7209b7;
            --accent: #4cc9f0;
            --text: #f8fafc;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background-color: var(--bg);
            color: var(--text);
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
        }

        .hero {
            background: linear-gradient(rgba(15, 23, 42, 0.8), rgba(15, 23, 42, 0.9)), url('https://images.unsplash.com/photo-1550745165-9bc0b252726f?auto=format&fit=crop&q=80&w=1000');
            background-size: cover;
            background-position: center;
            padding: 100px 20px;
            text-align: center;
            border-bottom: 2px solid var(--primary);
        }

        .hero h1 { font-size: 3rem; font-weight: 800; margin-bottom: 20px; text-transform: uppercase; letter-spacing: -1px; }
        .hero p { color: #94a3b8; font-size: 1.2rem; max-width: 600px; margin: 0 auto 30px; }

        .search-box {
            max-width: 700px;
            margin: -40px auto 50px;
            position: relative;
            z-index: 10;
            padding: 0 20px;
        }

        .search-box input {
            width: 100%;
            padding: 20px 60px 20px 30px;
            border-radius: 50px;
            border: none;
            background: var(--card);
            color: white;
            font-size: 1.1rem;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.3);
            outline: 2px solid transparent;
            transition: 0.3s;
        }

        .search-box input:focus { outline-color: var(--primary); box-shadow: 0 0 30px rgba(114, 9, 183, 0.3); }
        .search-box i { position: absolute; right: 50px; top: 50%; transform: translateY(-50%); color: #64748b; font-size: 1.2rem; }

        .container { max-width: 1200px; margin: 0 auto; padding: 40px 20px; }

        .filters { display: flex; gap: 15px; justify-content: center; margin-bottom: 50px; flex-wrap: wrap; }
        .filter-btn {
            padding: 10px 25px;
            border-radius: 30px;
            background: var(--card);
            color: #94a3b8;
            text-decoration: none;
            font-weight: 600;
            transition: 0.3s;
            border: 1px solid #334155;
        }

        .filter-btn:hover, .filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            transform: translateY(-3px);
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
        }

        .kb-card {
            background: var(--card);
            border-radius: 20px;
            padding: 30px;
            border: 1px solid #334155;
            transition: 0.4s;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
        }

        .kb-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 4px; height: 0;
            background: var(--primary);
            transition: 0.4s;
        }

        .kb-card:hover {
            transform: translateY(-10px);
            border-color: var(--primary);
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        }

        .kb-card:hover::before { height: 100%; }

        .kb-card .category {
            font-size: 0.7rem;
            text-transform: uppercase;
            font-weight: 800;
            color: var(--accent);
            margin-bottom: 10px;
            letter-spacing: 1px;
        }

        .kb-card h3 { font-size: 1.4rem; margin-bottom: 15px; color: white; }
        .kb-card p { color: #94a3b8; font-size: 0.95rem; margin-bottom: 25px; flex-grow: 1; }

        .card-actions { display: flex; gap: 10px; margin-top: auto; }
        .btn-card {
            flex: 1;
            padding: 12px;
            border-radius: 10px;
            text-align: center;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-download { background: #334155; color: white; }
        .btn-download:hover { background: #475569; }
        .btn-video { background: #ef4444; color: white; }
        .btn-video:hover { background: #dc2626; box-shadow: 0 0 15px rgba(239, 68, 68, 0.4); }

        .empty-state { text-align: center; padding: 100px 0; color: #64748b; }
        .empty-state i { font-size: 4rem; margin-bottom: 20px; opacity: 0.3; }

        .ai-float {
            position: fixed;
            bottom: 40px;
            right: 40px;
            background: #25d366;
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            box-shadow: 0 10px 30px rgba(37, 211, 102, 0.5);
            transition: 0.3s;
            z-index: 1000;
            text-decoration: none;
        }
        .ai-float:hover { transform: scale(1.1) rotate(10deg); }

        @media (max-width: 768px) {
            .hero h1 { font-size: 2rem; }
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <section class="hero">
        <div class="container">
            <h1>Centro de Suporte</h1>
            <p>Drivers, Manuais, Tutoriais e tudo o que você precisa para o seu Fight Arcade.</p>
        </div>
    </section>

    <div class="search-box">
        <form action="" method="GET">
            <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="O que você está procurando hoje?">
            <i class="fas fa-search"></i>
        </form>
    </div>

    <div class="container">
        <div class="filters">
            <a href="support.php" class="filter-btn <?php echo !$cat ? 'active' : ''; ?>">Tudo</a>
            <?php foreach($categories as $c): ?>
                <a href="?cat=<?php echo urlencode($c); ?>" class="filter-btn <?php echo $cat === $c ? 'active' : ''; ?>">
                    <?php echo ucfirst($c); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="grid">
            <?php foreach($items as $i): ?>
                <div class="kb-card">
                    <span class="category"><?php echo $i['category']; ?></span>
                    <h3><?php echo $i['title']; ?></h3>
                    <p><?php echo nl2br($i['content']); ?></p>
                    
                    <div class="card-actions">
                        <?php if($i['link_url']): ?>
                            <a href="<?php echo $i['link_url']; ?>" target="_blank" class="btn-card btn-download">
                                <i class="fas fa-download"></i> Manual/Link
                            </a>
                        <?php endif; ?>
                        
                        <?php if($i['video_url']): ?>
                            <a href="<?php echo $i['video_url']; ?>" target="_blank" class="btn-card btn-video">
                                <i class="fas fa-play"></i> Ver Vídeo
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if(empty($items)): ?>
                <div class="empty-state">
                    <i class="fas fa-ghost"></i>
                    <h2>Nenhum resultado encontrado</h2>
                    <p>Tente outros termos ou fale com nossa IA.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <a href="https://wa.me/5511984343166" class="ai-float" target="_blank">
        <i class="fab fa-whatsapp"></i>
    </a>

    <footer style="text-align: center; padding: 50px 20px; color: #475569; font-size: 0.9rem;">
        &copy; <?php echo date('Y'); ?> Fight Arcade. Todos os direitos reservados.
    </footer>

</body>
</html>
