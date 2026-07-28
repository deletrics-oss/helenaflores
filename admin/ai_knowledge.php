<?php
/**
 * admin/ai_knowledge.php — Fight Arcade
 * Gestão da Base de Conhecimento do SDR
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();
try {

$success = '';
$error   = '';

// 1. Processa Cadastro/Edição
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action'] ?? '';
    $id       = $_POST['id'] ?? null;
    $title    = $_POST['title'] ?? '';
    $content  = $_POST['content'] ?? '';
    $category = $_POST['category'] ?? 'suporte';
    $imgUrl   = $_POST['image_url'] ?? '';
    $linkUrl  = $_POST['link_url'] ?? '';
    $videoUrl = $_POST['video_url'] ?? '';
    $tags     = $_POST['tags'] ?? '';
    $related  = $_POST['related_products'] ?? '';
    $aiInst   = $_POST['ai_instructions'] ?? '';
    $botRole  = $_POST['bot_role'] ?? 'geral';

    if ($action === 'save') {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE ai_knowledge SET title=?, content=?, category=?, bot_role=?, image_url=?, link_url=?, video_url=?, tags=?, related_products=?, ai_instructions=? WHERE id=?");
            $stmt->execute([$title, $content, $category, $botRole, $imgUrl, $linkUrl, $videoUrl, $tags, $related, $aiInst, $id]);
            $success = "Conhecimento atualizado!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO ai_knowledge (title, content, category, bot_role, image_url, link_url, video_url, tags, related_products, ai_instructions) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $content, $category, $botRole, $imgUrl, $linkUrl, $videoUrl, $tags, $related, $aiInst]);
            $success = "Novo conhecimento cadastrado!";
        }
    }
 elseif ($action === 'delete' && $id) {
        $stmt = $pdo->prepare("DELETE FROM ai_knowledge WHERE id=?");
        $stmt->execute([$id]);
        $success = "Removido com sucesso!";
    }
}

// 2. Busca Base Atual
$items = $pdo->query("SELECT * FROM ai_knowledge ORDER BY category, title")->fetchAll();

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Cérebro do SDR — Fight Arcade</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --primary: #7209b7; --secondary: #3f37c9; --accent: #4cc9f0; --dark: #0f172a; }
        body { background: var(--dark); color: #f8fafc; font-family: 'Inter', sans-serif; }
        .container { max-width: 1100px; margin: 40px auto; padding: 0 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .card { background: #1e293b; border-radius: 16px; padding: 25px; border: 1px solid #334155; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; color: #94a3b8; font-size: 0.9rem; }
        input, textarea, select { width: 100%; padding: 12px; background: #0f172a; border: 1px solid #334155; border-radius: 8px; color: white; transition: 0.3s; }
        input:focus { border-color: var(--accent); outline: none; }
        .btn { padding: 12px 24px; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; transition: 0.3s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(114, 9, 183, 0.4); }
        .kb-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 30px; }
        .kb-item { background: #1e293b; border-radius: 12px; padding: 20px; border: 1px solid #334155; position: relative; }
        .kb-item h3 { margin: 0 0 10px 0; color: var(--accent); font-size: 1.1rem; }
        .kb-item p { font-size: 0.9rem; color: #cbd5e1; line-height: 1.5; margin-bottom: 15px; }
        .kb-meta { font-size: 0.75rem; color: #64748b; display: flex; gap: 10px; }
        .kb-actions { position: absolute; top: 15px; right: 15px; display: flex; gap: 8px; }
        .action-btn { background: none; border: none; color: #94a3b8; cursor: pointer; transition: 0.3s; }
        .action-btn:hover { color: white; }
        .tag { padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: bold; text-transform: uppercase; background: #334155; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
        .alert-success { background: #065f46; color: #34d399; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-brain"></i> Cérebro do SDR</h1>
            <a href="index.php" style="color: #94a3b8; text-decoration: none;"><i class="fas fa-arrow-left"></i> Voltar</a>
        </div>

        <?php if($success): ?> <div class="alert alert-success"><?php echo $success; ?></div> <?php endif; ?>

        <div class="card" style="margin-bottom:30px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <h2 id="form-title" style="margin:0;"><i class="fas fa-plus-circle"></i> Treinar Novo Conhecimento</h2>
                <div style="position:relative; width:300px;">
                    <i class="fas fa-search" style="position:absolute; left:12px; top:13px; color:#64748b;"></i>
                    <input type="text" id="kb-search" placeholder="Buscar no Cérebro..." style="padding-left:35px; background:#0f172a;">
                </div>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="item-id" value="">
                
                <div class="form-group">
                    <label>Título / Gatilho (Ex: Driver Placa Zero Delay)</label>
                    <input type="text" name="title" id="item-title" required placeholder="O que o cliente vai perguntar?">
                </div>

                <div class="form-group">
                    <label>Conteúdo da Resposta (Explicação técnica)</label>
                    <textarea name="content" id="item-content" rows="4" required placeholder="O que o robô deve responder?"></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Categoria</label>
                        <select name="category" id="item-category">
                            <option value="suporte">🛠️ Suporte Técnico</option>
                            <option value="pos-venda">🤝 Pós-Venda</option>
                            <option value="venda">💰 Dica de Venda</option>
                            <option value="drivers">💾 Drivers / Links</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Persona do Bot (Filtragem)</label>
                        <select name="bot_role" id="item-bot-role">
                            <option value="geral">🌐 Geral (Ambos)</option>
                            <option value="suporte">🛠️ Especialista Suporte</option>
                            <option value="vendas">💰 Especialista Vendas</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Link Externo (Manual/Download)</label>
                        <input type="text" name="link_url" id="item-link" placeholder="https://...">
                    </div>
                    <div class="form-group">
                        <label>Link de Vídeo (YouTube/Tutorial)</label>
                        <input type="text" name="video_url" id="item-video" placeholder="https://youtube.com/...">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Palavras-Chave / Tags (Separadas por vírgula)</label>
                        <input type="text" name="tags" id="item-tags" placeholder="ex: placa, zero delay, configurar">
                    </div>
                    <div class="form-group">
                        <label>Produtos Relacionados (Nomes)</label>
                        <input type="text" name="related_products" id="item-related" placeholder="ex: Arcade Pro, Kit Sanwa">
                    </div>
                </div>

                    <label>Instruções Secretas para a IA (Como ela deve agir neste caso?)</label>
                    <textarea name="ai_instructions" id="item-instructions" rows="2" placeholder="ex: Seja muito paciente e ofereça o produto X como upgrade ao final."></textarea>
                </div>

                <!-- AI GENERATOR TOOL -->
                <div style="background:rgba(114, 9, 183, 0.1); border:1px dashed var(--primary); padding:20px; border-radius:12px; margin-bottom:20px;">
                    <h4 style="margin:0 0 10px 0; color:var(--accent);"><i class="fas fa-magic"></i> Assistente de Treinamento IA</h4>
                    <p style="font-size:0.8rem; color:#94a3b8; margin-bottom:10px;">Cole um texto bruto do WhatsApp (conversa/pergunta) e a IA preencherá os campos acima para você.</p>
                    <textarea id="raw-ai-text" rows="3" placeholder="Cole aqui o texto do WhatsApp..." style="margin-bottom:10px;"></textarea>
                    <button type="button" onclick="generateWithAI()" id="btn-ai-gen" class="btn" style="background:var(--primary); color:white; width:100%;">
                        <i class="fas fa-robot"></i> Estruturar Conhecimento com IA
                    </button>
                </div>

                <div style="text-align: right;">
                    <button type="button" onclick="resetForm()" class="btn" style="background: #334155; color: white;">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar no Cérebro</button>
                </div>
            </form>
        </div>

        <div class="kb-grid">
            <?php foreach($items as $i): ?>
            <div class="kb-item">
                <div class="kb-actions">
                    <button onclick="editItem(<?php echo htmlspecialchars(json_encode($i)); ?>)" class="action-btn"><i class="fas fa-edit"></i></button>
                    <form action="" method="POST" style="display:inline;" onsubmit="return confirm('Apagar este conhecimento?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo $i['id']; ?>">
                        <button type="submit" class="action-btn" style="color: #ef4444;"><i class="fas fa-trash"></i></button>
                    </form>
                </div>
                <span class="tag"><?php echo $i['category']; ?></span>
                <span class="tag" style="background:<?php echo $i['bot_role'] == 'vendas' ? '#e67e22' : ($i['bot_role'] == 'suporte' ? '#3498db' : '#334155'); ?>;">
                    <?php echo strtoupper($i['bot_role'] ?? 'GERAL'); ?>
                </span>
                <h3><?php echo $i['title']; ?></h3>
                <p><?php echo nl2br(substr($i['content'], 0, 150)); ?>...</p>
                <div class="kb-meta">
                    <?php if($i['image_url']): ?> <span><i class="fas fa-image"></i> Imagem</span> <?php endif; ?>
                    <?php if($i['link_url']): ?> <span><i class="fas fa-link"></i> Link</span> <?php endif; ?>
                    <?php if($i['video_url']): ?> <span><i class="fas fa-video"></i> Vídeo</span> <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        function editItem(item) {
            document.getElementById('form-title').innerText = "Editar Conhecimento";
            document.getElementById('item-id').value = item.id;
            document.getElementById('item-title').value = item.title;
            document.getElementById('item-content').value = item.content;
            document.getElementById('item-category').value = item.category;
            document.getElementById('item-image').value = item.image_url;
            document.getElementById('item-link').value = item.link_url;
            document.getElementById('item-video').value = item.video_url;
            document.getElementById('item-tags').value = item.tags || "";
            document.getElementById('item-related').value = item.related_products || "";
            document.getElementById('item-instructions').value = item.ai_instructions || "";
            document.getElementById('item-bot-role').value = item.bot_role || "geral";
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function generateWithAI() {
            const raw = document.getElementById('raw-ai-text').value;
            if(!raw) { alert('Cole algum texto primeiro!'); return; }

            const btn = document.getElementById('btn-ai-gen');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Analisando com IA...';

            const formData = new FormData();
            formData.append('raw_text', raw);

            fetch('ajax_ai_knowledge_gen.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                if(res.success) {
                    const d = res.data;
                    document.getElementById('item-title').value = d.title || '';
                    document.getElementById('item-content').value = d.content || '';
                    document.getElementById('item-category').value = d.category || 'suporte';
                    document.getElementById('item-bot-role').value = d.bot_role || 'geral';
                    document.getElementById('item-tags').value = d.tags || '';
                    document.getElementById('item-instructions').value = d.ai_instructions || '';
                    
                    document.getElementById('raw-ai-text').value = '';
                    alert('✨ Conhecimento estruturado com sucesso! Revise e clique em Salvar.');
                } else {
                    alert('❌ Erro: ' + res.error);
                }
            })
            .catch(err => alert('Erro de conexão: ' + err))
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-robot"></i> Estruturar Conhecimento com IA';
            });
        }

        function resetForm() {
            document.getElementById('form-title').innerHTML = "<i class='fas fa-plus-circle'></i> Treinar Novo Conhecimento";
            document.getElementById('item-id').value = "";
            document.getElementById('item-title').value = "";
            document.getElementById('item-content').value = "";
            document.getElementById('item-image').value = "";
            document.getElementById('item-link').value = "";
            document.getElementById('item-video').value = "";
            document.getElementById('item-tags').value = "";
            document.getElementById('item-related').value = "";
            document.getElementById('item-instructions').value = "";
        }

        // Live Search Logic
        document.getElementById('kb-search').addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase();
            document.querySelectorAll('.kb-item').forEach(item => {
                const text = item.innerText.toLowerCase();
                item.style.display = text.includes(term) ? 'block' : 'none';
            });
        });
    </script>
</body>
</html>
<?php
} catch (Throwable $e) {
    header("Location: emergency_fix.php?fatal_error=" . urlencode($e->getMessage()) . "&file=ai_knowledge.php");
    exit;
}
?>
