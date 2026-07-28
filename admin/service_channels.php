<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/user_auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/notifications.php';
isAdmin();

// ==========================================
// UPGRADE: SDR AUTOMATIZADO E HISTÓRICO WHATSAPP
// ==========================================
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS last_contacted_at DATETIME DEFAULT NULL");
    // Robô vem pausado por padrão (Opt-in)
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS sdr_bot_status ENUM('active','paused') DEFAULT 'paused'");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS sdr_followup_days INT DEFAULT 3");
    
    // Tabela para guardar o histórico real das conversas
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS whatsapp_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            remote_jid VARCHAR(50) NOT NULL,
            from_me TINYINT(1) DEFAULT 0,
            message_text TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_jid (remote_jid),
            INDEX idx_created (created_at)
        )
    ");
} catch(Exception $e) {}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Canais de Atendimento (Omnichannel) | Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        .channel-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .channel-tab {
            padding: 10px 20px;
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 4px;
            cursor: pointer;
            color: #888;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
        }

        .channel-tab.active {
            background: var(--primary);
            color: #000;
            border-color: var(--primary);
        }

        .channel-content {
            display: none;
            background: #111b21;
            border: 1px solid #2a3942;
            position: relative;
            overflow: hidden;
        }

        .channel-content.active {
            display: block;
        }

        .chat-iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        .demo-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            background: rgba(0, 0, 0, 0.7);
            color: #fff;
        }

        .meta-logo {
            font-size: 3rem;
            margin-bottom: 20px;
        }
    </style>
</head>

<body style="margin: 0; padding: 0; display: flex; flex-direction: column; height: 100vh; overflow: hidden; background:#0b141a;">
    <?php include 'header.php'; ?>
    <div style="flex: 1; display: flex; flex-direction: column; min-height: 0;">
        <div id="whatsapp" class="channel-content active" style="flex: 1; border: none; box-shadow: none; margin: 0; border-radius: 0; display: flex; flex-direction: column; min-height: 0;">
            <div style="display:grid; grid-template-columns: 320px 1fr; flex: 1; min-height: 0;">
                <!-- Sidebar: Chats -->
                <div style="border-right:1px solid #202c33; display:flex; flex-direction:column; background:#111b21; overflow:hidden;">
                    <!-- Cabeçalho da Sidebar -->
                    <div style="padding:15px; background:#202c33; border-bottom:1px solid #2a3942; display:flex; align-items:center; justify-content:space-between;">
                        <img src="https://ui-avatars.com/api/?name=Admin&background=202c33&color=aebac1" style="width:40px; height:40px; border-radius:50%; object-fit:cover;" title="Seu Perfil">
                        <div style="display:flex; gap:15px; color:#aebac1; font-size:1.2rem;">
                            <i class="fas fa-users" title="Comunidades"></i>
                            <i class="fas fa-circle-notch" title="Status"></i>
                            <i class="fas fa-comment-alt" title="Nova Conversa"></i>
                        </div>
                    </div>
                    
                    <div id="chat-list" style="flex:1; overflow-y:auto; overflow-x:hidden;">
                        <?php
                        $notifCheck = new NotificationService($pdo);
                        $c = $notifCheck->getConfig();
                        if (empty($c['notif_waapi_url']) || empty($c['notif_waapi_key'])): ?>
                            <div style="padding:40px 20px; text-align:center; color:#888;">
                                <i class="fas fa-exclamation-circle" style="font-size:2rem; color:#e74c3c; margin-bottom:10px;"></i><br>
                                <strong>API não configurada</strong><br>
                                <p style="font-size:0.8rem; margin:10px 0;">Configure sua Evolution API para ver os chats.</p>
                                <a href="notifications.php" class="btn btn-sm" style="background:#e74c3c; color:#fff;">Configurar Agora</a>
                            </div>
                        <?php else: ?>
                            <div style="padding:20px; text-align:center; color:#888;">
                                <i class="fas fa-spinner fa-spin"></i> Carregando conversas...
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Main: Messages -->
                <div id="chat-window" style="display:flex; flex-direction:column; background:#0d1117; height:100%; max-height:100%; overflow:hidden;">
                    <div style="flex:1; display:flex; align-items:center; justify-content:center; color:#5a6478; flex-direction:column;">
                        <i class="fab fa-whatsapp" style="font-size:4rem; margin-bottom:1rem; opacity:0.3;"></i>
                        <p>Selecione uma conversa para começar</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Facebook & Instagram remain as mockups for now -->
        <div id="facebook" class="channel-content">
             <div class="demo-overlay">
                <i class="fab fa-facebook meta-logo"></i>
                <h3>Facebook Messenger</h3>
                <p>Integração em breve via Meta API.</p>
             </div>
        </div>

        <div id="instagram" class="channel-content">
             <div class="demo-overlay">
                <i class="fab fa-instagram meta-logo"></i>
                <h3>Instagram Direct</h3>
                <p>Integração em breve via Meta API.</p>
             </div>
        </div>
    </div>

    <script>
        let currentJid = '';
        const chatList = document.getElementById('chat-list');
        const chatWindow = document.getElementById('chat-window');

        function switchTab(channel, tabElement) {
            document.querySelectorAll('.channel-tab').forEach(t => t.classList.remove('active'));
            tabElement.classList.add('active');
            document.querySelectorAll('.channel-content').forEach(c => c.classList.remove('active'));
            document.getElementById(channel).classList.add('active');
        }

        async function loadChats() {
            try {
                const r = await fetch('api_chat.php?action=list_chats');
                const data = await r.json();
                
                if (data.error) {
                    let errHtml = `<div style="padding:20px; color:#e74c3c; word-break: break-all; font-size:0.85rem;">
                                      <i class="fas fa-exclamation-triangle"></i> <strong>Erro API: ${data.error}</strong><br>
                                      <span style="color:#aaa;">Código HTTP: ${data.code}</span><br>
                                      <div style="background:#000; padding:10px; margin-top:10px; border-radius:5px; border:1px solid #333; color:#ff4d4d; font-family:monospace; white-space:pre-wrap;">${typeof data.details === 'string' ? data.details.replace(/</g, '&lt;') : JSON.stringify(data.details)}</div>
                                   </div>`;
                    chatList.innerHTML = errHtml;
                    return;
                }
                
                let chatsArray = Array.isArray(data) ? data : (data.chats || data.data || []);
                
                if (!chatsArray || chatsArray.length === 0) {
                    chatList.innerHTML = '<div style="padding:20px; text-align:center; color:#888;">Nenhuma conversa encontrada.</div>';
                    return;
                }

                chatList.innerHTML = chatsArray.map(chat => {
                    const name = chat.pushName || chat.name || chat.remoteJid.split('@')[0];
                    const activeClass = chat.remoteJid === currentJid ? 'background:#2a3942;' : '';
                    const isKnown = chat.userId > 0;
                    
                    let statusBadge = '';
                    if (chat.botStatus === 'paused') {
                        statusBadge = '<span style="font-size:0.6rem; background:#e74c3c; color:#fff; padding:2px 4px; border-radius:4px; margin-left:5px;">SDR PAUSADO</span>';
                    }
                    if (chat.hasOrders) {
                        statusBadge += '<span style="font-size:0.6rem; background:#2980b9; color:#fff; padding:2px 4px; border-radius:4px; margin-left:5px;" title="Cliente possui Pedidos">📦</span>';
                    }
                    if (chat.hasRma) {
                        statusBadge += '<span style="font-size:0.6rem; background:#9b59b6; color:#fff; padding:2px 4px; border-radius:4px; margin-left:5px;" title="Cliente possui Histórico de Suporte">🛠️</span>';
                    }
                    if (chat.hasDebt) {
                        statusBadge += '<span style="font-size:0.6rem; background:#e74c3c; color:#fff; padding:2px 4px; border-radius:4px; margin-left:5px;" title="Cliente possui Débitos Pendentes">⚠️ DEVEDOR</span>';
                    }

                    // Label de origem (Facebook, Indicação, etc.)
                    const labelColors = {facebook:'#1877f2', indicacao:'#e67e22', marketplace:'#8e44ad', instagram:'#e1306c', site:'#2ecc71', novo:'#555'};
                    const labelEmojis = {facebook:'📘', indicacao:'🤝', marketplace:'🛒', instagram:'📸', site:'🌐', novo:'🆕'};
                    if (isKnown) {
                        if (chat.botStatus === 'active') {
                            statusBadge = '<span style="background:#27ae60; color:#fff; font-size:0.6rem; padding:2px 5px; border-radius:4px; margin-left:8px;">SDR ATIVO</span>';
                        } else {
                            statusBadge = '<span style="background:#e74c3c; color:#fff; font-size:0.6rem; padding:2px 5px; border-radius:4px; margin-left:8px;">SDR PAUSADO</span>';
                        }
                    }
                    
                    let labelBadge = '';
                    if (chat.contactLabel) {
                        const colors = {
                            facebook: '#3b5998', marketplace: '#e67e22', instagram: '#e1306c', indicacao: '#f39c12', site: '#2980b9'
                        };
                        const lColor = colors[chat.contactLabel] || '#888';
                        labelBadge = `<span style="background:${lColor}; color:#fff; font-size:0.6rem; padding:2px 5px; border-radius:4px; margin-left:5px; text-transform:uppercase;">${chat.contactLabel}</span>`;
                    }
                    
                    let debtBadge = chat.hasDebt ? `<span style="background:#c0392b; color:#fff; font-size:0.6rem; padding:2px 5px; border-radius:4px; margin-left:5px; border:1px solid #e74c3c;">⚠️ DEVEDOR</span>` : '';
                    let orderBadge = chat.hasOrders ? `<span style="background:#2980b9; color:#fff; font-size:0.6rem; padding:2px 5px; border-radius:4px; margin-left:5px;">📦</span>` : '';
                    let rmaBadge = chat.hasRma ? `<span style="background:#8e44ad; color:#fff; font-size:0.6rem; padding:2px 5px; border-radius:4px; margin-left:5px;">🛠️</span>` : '';

                    return `
                        <div onclick="openChat('${chat.remoteJid}', '${name}', ${chat.userId}, '${chat.botStatus}', ${chat.hasOrders}, ${chat.hasRma}, ${chat.hasDebt}, '${chat.contactLabel}')" 
                             style="display:flex; align-items:center; padding:0 15px; cursor:pointer; transition:0.1s; ${activeClass}"
                             onmouseover="this.style.background='#202c33'" onmouseout="this.style.background='${chat.remoteJid === currentJid ? '#2a3942' : 'transparent'}'">
                            
                            <img src="https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=random&size=49" style="width:49px; height:49px; border-radius:50%; object-fit:cover; margin-right:15px;">
                            
                            <div style="flex:1; border-bottom:1px solid #202c33; padding:15px 0; display:flex; flex-direction:column; justify-content:center;">
                                <strong style="color:#e9edef; font-size:1.05rem; font-weight:normal; margin-bottom:3px; display:flex; align-items:center; flex-wrap:wrap;">${name} ${statusBadge}</strong>
                                <div style="display:flex; gap:4px; align-items:center;">
                                    <small style="color:#8696a0; font-size:0.85rem;">${chat.remoteJid.split('@')[0]}</small>
                                    ${orderBadge} ${rmaBadge} ${debtBadge} ${labelBadge}
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');
            } catch(e) {
                console.error('loadChats error:', e);
                const chatList = document.getElementById('chat-list');
                if(chatList) chatList.innerHTML = '<div style="padding:20px; color:#e74c3c;">Erro interno ao processar conversas. Conexão recusada ou API offline.</div>';
            }
        }

        async function openChat(jid, name, userId, botStatus, hasOrders, hasRma, hasDebt, contactLabel) {
            currentJid = jid;
            currentUserId = userId;
            const numberOnly = jid.split('@')[0];
            
            let botBtn = '';
            if (userId) {
                if (botStatus === 'active') {
                    botBtn = `<button class="btn btn-sm" style="background:#27ae60; color:#fff;" onclick="toggleBot(${userId}, 'paused')" title="O robô está respondendo este lead. Clique para PAUSAR.">🤖 Robô Ativo</button>`;
                } else {
                    botBtn = `<button class="btn btn-sm" style="background:#e74c3c; color:#fff;" onclick="toggleBot(${userId}, 'active')" title="O robô está pausado. Clique para REATIVAR.">⏸️ Robô Pausado</button>`;
                }
            }

            let topBadges = '';
            if (userId) {
                topBadges += `<span style="background:#1f2d38; color:#3498db; border:1px solid #2980b9; padding:3px 8px; border-radius:6px; font-size:0.75rem; font-weight:bold;">🌐 Origem: Loja Virtual</span>`;
            } else {
                topBadges += `<span style="background:#1f2d38; color:#2ecc71; border:1px solid #27ae60; padding:3px 8px; border-radius:6px; font-size:0.75rem; font-weight:bold;">📲 Origem: Contato WhatsApp</span>`;
            }
            if (hasOrders) {
                topBadges += `<span style="background:#2980b9; color:#fff; padding:3px 8px; border-radius:6px; font-size:0.75rem; font-weight:bold;">📦 Fez Pedidos</span>`;
            }
            if (hasRma) {
                topBadges += `<span style="background:#8e44ad; color:#fff; padding:3px 8px; border-radius:6px; font-size:0.75rem; font-weight:bold;">🛠️ Em Suporte/RMA</span>`;
            }
            if (hasDebt) {
                topBadges += `<span style="background:#c0392b; color:#fff; padding:3px 8px; border-radius:6px; font-size:0.75rem; font-weight:bold; border:1px solid #e74c3c;">⚠️ DEVEDOR</span>`;
            }

            // Seletor de label/etiqueta
            const labelOpts = [
                {val:'', txt:'Sem Etiqueta'},
                {val:'facebook', txt:'📘 Facebook'},
                {val:'marketplace', txt:'🛒 Marketplace'},
                {val:'instagram', txt:'📸 Instagram'},
                {val:'indicacao', txt:'🤝 Indicação'},
                {val:'site', txt:'🌐 Site'}
            ];
            const labelSelect = labelOpts.map(o => `<option value="${o.val}" ${contactLabel === o.val ? 'selected' : ''}>${o.txt}</option>`).join('');

            chatWindow.innerHTML = `
                <div style="padding:10px 16px; background:#202c33; border-bottom:1px solid #2a3942; display:flex; justify-content:space-between; align-items:center; z-index:10; flex-wrap:wrap; gap:10px;">
                    <div style="display:flex; align-items:center; gap:15px;">
                        <img id="chat-avatar" src="https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=random&size=40" 
                             style="width:40px; height:40px; border-radius:50%; object-fit:cover; cursor:pointer;" 
                             title="Clique para ver a foto em alta resolução"
                             onclick="window.open(this.src, '_blank')">
                        <div style="display:flex; flex-direction:column;">
                            <strong style="color:#e9edef; font-size:1.1rem; ${userId ? 'cursor:pointer; transition:0.2s;' : ''}" 
                                    onmouseover="this.style.color='${userId ? '#3498db' : '#e9edef'}'" 
                                    onmouseout="this.style.color='#e9edef'"
                                    ${userId ? `onclick="window.open('customer-details.php?id=${userId}', '_blank')" title="Ver Ficha e Financeiro do Cliente"` : ''}>
                                ${name} ${userId ? '<i class="fas fa-external-link-alt" style="font-size:0.8rem; margin-left:5px; color:#888;"></i>' : ''}
                            </strong>
                            <div style="display:flex; gap:6px; margin-top:3px; align-items:center; flex-wrap:wrap;">
                                ${topBadges}
                                <select onchange="setContactLabel('${jid}', this.value)" style="background:#1a252c; color:#8696a0; border:1px solid #2a3942; border-radius:4px; padding:2px 6px; font-size:0.7rem; cursor:pointer;">${labelSelect}</select>
                            </div>
                        </div>
                    </div>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        ${!userId ? `<button class="btn btn-sm" style="background:#00a884; color:#fff; font-weight:bold;" onclick="registerContact('${numberOnly}')" title="Cadastrar este contato como Lead no sistema">➕ Cadastrar Lead</button>` : ''}
                        ${botBtn}
                        <button class="btn btn-sm" style="background:#005c4b; color:#fff;" onclick="openAdvancedModal('catalogo')" title="Enviar Catálogo">🛍️ Catálogo</button>
                        <button class="btn btn-sm" style="background:#e67e22; color:#fff;" onclick="openAdvancedModal('pedidos')" title="Status de Pedidos">📦 Pedidos</button>
                        <button class="btn btn-sm" style="background:#9b59b6; color:#fff;" onclick="openAdvancedModal('rma')" title="Status RMA">🛠️ RMA</button>
                        <button class="btn btn-sm" style="background:#f1c40f; color:#000;" onclick="openAdvancedModal('financeiro')" title="Extrato Financeiro">💰 Financeiro</button>
                        <button class="btn btn-sm" style="background:#2980b9; color:#fff;" onclick="openAdvancedModal('suporte')" title="Base de Conhecimento / Suporte">📚 Suporte</button>
                        <button class="btn btn-sm" style="background:transparent; color:#00a884; border:1px solid #00a884;" onclick="window.open('https://wa.me/${numberOnly}', '_blank')" title="Abrir no WhatsApp Oficial"><i class="fas fa-external-link-alt"></i> Web</button>
                    </div>
                </div>
                <div id="messages-container" style="flex:1; overflow-y:auto; padding:20px 5%; display:flex; flex-direction:column; gap:8px; background-color: #0b141a; background-image: url('https://user-images.githubusercontent.com/15075759/28719144-86dc0f70-73b1-11e7-911d-60d70fcded21.png'); background-attachment: fixed;">
                    <div style="text-align:center; color:#888; background:rgba(32,44,51,0.8); padding:5px 15px; border-radius:10px; width:fit-content; margin:0 auto;"><i class="fas fa-spinner fa-spin"></i> Sincronizando mensagens...</div>
                </div>
                <div style="padding:15px 16px; background:#202c33; display:flex; gap:12px; align-items:center;">
                    <i class="far fa-smile" style="color:#aebac1; font-size:1.5rem; cursor:pointer;" title="Emoji"></i>
                    <i class="fas fa-paperclip" style="color:#aebac1; font-size:1.5rem; cursor:pointer;" title="Anexar"></i>
                    <input type="text" id="msg-input" placeholder="Digite uma mensagem" 
                           style="flex:1; padding:15px 20px; background:#2a3942; border:none; color:#d1d7db; border-radius:8px; font-size:1rem; outline:none;"
                           onkeypress="if(event.key==='Enter') sendMessage()">
                    <button onclick="sendMessage()" class="btn" style="background:#005c4b; color:#fff; border-radius:8px; padding:12px 20px; font-weight:bold; display:flex; align-items:center; gap:8px;">
                        <i class="fas fa-paper-plane"></i> ENVIAR
                    </button>
                </div>
            `;
            
            loadMessages(jid);
            fetchProfilePic(jid); // Tenta puxar avatar real do WhatsApp
            loadChats(); // Refresh sidebar active state
        }
        
        async function toggleBot(userId, status) {
            try {
                const r = await fetch(`api_chat.php?action=toggle_bot&uid=${userId}&status=${status}`);
                const data = await r.json();
                if (data.success) {
                    loadChats(); // recarrega a barra lateral para atualizar o badge e o botão
                }
            } catch(e) {
                alert("Erro ao alterar o status do robô.");
            }
        }

        async function loadMessages(jid) {
            try {
                const r = await fetch(`api_chat.php?action=get_messages&remoteJid=${jid}`);
                const data = await r.json();
                const container = document.getElementById('messages-container');
                
                let msgs = Array.isArray(data) ? data : (data.messages || data.data || []);
                
                if (!msgs || msgs.length === 0) {
                    container.innerHTML = '<div style="text-align:center; color:#e9edef; background:rgba(32,44,51,0.9); padding:10px 20px; border-radius:10px; width:fit-content; margin:20px auto; font-size:0.9rem;"><i class="fas fa-lock" style="color:#8696a0; margin-right:5px;"></i> O histórico de mensagens anteriores não está disponível na memória da API.<br><br><b>Você pode iniciar a conversa enviando uma nova mensagem abaixo!</b></div>';
                    return;
                }

                // Evolution often returns messages from newest to oldest
                container.innerHTML = msgs.reverse().map(m => {
                    const fromMe = m.key ? m.key.fromMe : (m.fromMe === true);
                    const text = m.message?.conversation || m.message?.extendedTextMessage?.text || m.textMessage?.text || '<i>[Mídia/Arquivo enviado]</i> 📷';
                    
                    // Cores idênticas ao WhatsApp Web Dark Mode
                    const bg = fromMe ? '#005c4b' : '#202c33';
                    const color = '#e9edef';
                    const align = fromMe ? 'align-self: flex-end' : 'align-self: flex-start';
                    const borderRadius = fromMe ? '8px 0px 8px 8px' : '0px 8px 8px 8px';
                    
                    // Horário (timestamp)
                    let timeStr = '';
                    if (m.messageTimestamp) {
                        const date = new Date(m.messageTimestamp * 1000);
                        timeStr = date.getHours().toString().padStart(2, '0') + ':' + date.getMinutes().toString().padStart(2, '0');
                    }
                    
                    return `
                        <div style="${align}; background:${bg}; color:${color}; padding:6px 7px 8px 9px; border-radius:${borderRadius}; max-width:65%; font-size:0.95rem; box-shadow:0 1px 0.5px rgba(11,20,26,.13); display:flex; flex-direction:column; position:relative;">
                            <span style="word-wrap:break-word; line-height:19px; margin-bottom:4px;">${text}</span>
                            <span style="font-size:0.68rem; color:rgba(255,255,255,0.6); align-self:flex-end; margin-top:-5px; margin-right:2px;">${timeStr} ${fromMe ? '<i class="fas fa-check-double" style="color:#53bdeb; margin-left:3px;"></i>' : ''}</span>
                        </div>
                    `;
                }).join('');
                container.scrollTop = container.scrollHeight;
            } catch(e) {
                console.error('loadMessages error:', e);
                const container = document.getElementById('messages-container');
                if(container) container.innerHTML = '<div style="text-align:center; color:#e9edef; background:rgba(32,44,51,0.9); padding:10px 20px; border-radius:10px; width:fit-content; margin:20px auto; font-size:0.9rem;"><i class="fas fa-lock" style="color:#8696a0; margin-right:5px;"></i> O histórico de mensagens anteriores não está disponível na memória da API.<br><br><b>Você pode iniciar a conversa enviando uma nova mensagem abaixo!</b></div>';
            }
        }

        async function sendMessage() {
            const input = document.getElementById('msg-input');
            const text = input.value.trim();
            if (!text || !currentJid) return;

            input.value = '';
            const number = currentJid.split('@')[0];

            // AUTO-PAUSA O ROBÔ se o administrador entrar na conversa manualmente
            if (currentUserId) {
                toggleBot(currentUserId, 'paused');
            }

            try {
                await fetch('api_chat.php?action=send_message', {
                    method: 'POST',
                    body: JSON.stringify({ number, text })
                });
                loadMessages(currentJid);
            } catch(e) {
                alert('Erro ao enviar mensagem.');
            }
        }

        // Initial Load
        loadChats();
        setInterval(loadChats, 30000); // Auto refresh list every 30s
        
        async function setContactLabel(jid, label) {
            try {
                await fetch(`api_chat.php?action=set_label&jid=${encodeURIComponent(jid)}&label=${encodeURIComponent(label)}`);
                loadChats(); // Recarrega lista para atualizar o badge
            } catch(e) {
                console.error('Erro ao salvar label:', e);
            }
        }

        function registerContact(phone) {
            // Remove o 55 do início se tiver, para manter o padrão DDD+Número
            let cleanPhone = phone;
            if (cleanPhone.startsWith('55') && cleanPhone.length > 11) {
                cleanPhone = cleanPhone.substring(2);
            }
            window.open(`customer-create.php?phone=${cleanPhone}&source=whatsapp&is_lead=1`, '_blank');
        }

        // Tenta buscar a foto de perfil do WhatsApp via Evolution API
        async function fetchProfilePic(jid) {
            try {
                const r = await fetch(`api_chat.php?action=get_profile_pic&jid=${encodeURIComponent(jid)}`);
                const data = await r.json();
                if (data.profilePictureUrl) {
                    const avatar = document.getElementById('chat-avatar');
                    if (avatar) avatar.src = data.profilePictureUrl;
                }
            } catch(e) {}
        }
        
        // --- LÓGICA DO MODAL AVANÇADO ---
        let currentModalType = '';
        async function openAdvancedModal(type) {
            if (!currentJid) {
                alert("Selecione uma conversa primeiro!");
                return;
            }
            currentModalType = type;
            const number = currentJid.split('@')[0];
            const modalTitle = document.getElementById('adv-modal-title');
            const modalBody = document.getElementById('adv-modal-body');
            
            let titles = {
                'catalogo': '🛍️ Enviar Produto',
                'pedidos': '📦 Histórico de Pedidos',
                'rma': '🛠️ Status de RMA',
                'financeiro': '💰 Extrato de Pedidos/Pagamentos',
                'suporte': '📚 Base de Conhecimento'
            };
            modalTitle.innerText = titles[type] || 'Ação Avançada';
            modalBody.innerHTML = '<div style="text-align:center; padding:30px;"><i class="fas fa-spinner fa-spin fa-2x"></i><br>Buscando dados no sistema...</div>';
            
            document.getElementById('adv-modal-overlay').style.display = 'flex';
            
            try {
                // currentUser in chat list has .userId but we might not have it in memory here
                // let's just pass the phone to API so it finds the user by phone
                const r = await fetch(`api_chat.php?action=get_quick_data&type=${type}&phone=${number}`);
                const data = await r.json();
                
                if(!data.success) {
                    modalBody.innerHTML = `<div style="color:red; text-align:center;">Erro: ${data.error}</div>`;
                    return;
                }
                
                if(!data.data || data.data.length === 0) {
                    modalBody.innerHTML = `<div style="text-align:center; padding:20px; color:#aaa;">Nenhum registro encontrado para este cliente.</div>`;
                    return;
                }
                
                let html = '<div style="display:flex; flex-direction:column; gap:10px;">';
                if(type === 'financeiro') {
                    html += `
                    <div style="display:flex; justify-content:space-between; margin-bottom:15px; background:#1a252c; padding:10px; border-radius:8px; align-items:center;">
                        <div style="display:flex; gap:10px;">
                            <button onclick="document.querySelectorAll('.fin-checkbox').forEach(c => c.checked=true)" class="btn-sm" style="background:#444; color:#fff; border:none; cursor:pointer;">Todos</button>
                            <button onclick="let cbs = document.querySelectorAll('.fin-checkbox'); cbs.forEach(c=>c.checked=false); for(let i=0;i<3 && i<cbs.length;i++) cbs[i].checked=true;" class="btn-sm" style="background:#2980b9; color:#fff; border:none; cursor:pointer;">Últimos 3</button>
                        </div>
                        <button onclick="sendSelectedFinanceiro()" class="btn-sm" style="background:#00a884; color:#fff; border:none; cursor:pointer; font-weight:bold;">Extrato Selecionados <i class="fas fa-paper-plane"></i></button>
                    </div>`;
                }

                data.data.forEach(item => {
                    if(type === 'catalogo') {
                        html += `
                        <div style="background:#2a3942; padding:15px; border-radius:8px; display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <strong>${item.name}</strong><br>
                                <span style="color:#00a884;">R$ ${parseFloat(item.price).toFixed(2).replace('.', ',')}</span>
                            </div>
                            <button class="btn btn-sm" style="background:#005c4b; color:#fff;" onclick="selectAdvItem('catalogo', '${item.name}', '${item.slug}')">Enviar <i class="fas fa-paper-plane"></i></button>
                        </div>`;
                    } else if (type === 'pedidos') {
                        html += `
                        <div style="background:#2a3942; padding:15px; border-radius:8px; display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <strong>Pedido #${item.id}</strong> - ${item.status.toUpperCase()}<br>
                                <small style="color:#aaa;">R$ ${parseFloat(item.total_amount).toFixed(2).replace('.', ',')} | Rastreio: ${item.tracking_code || 'N/A'}</small>
                            </div>
                            <button class="btn btn-sm" style="background:#e67e22; color:#fff;" onclick="selectAdvItem('pedidos', '${item.id}', '${item.status}', '${item.tracking_code}')">Enviar <i class="fas fa-paper-plane"></i></button>
                        </div>`;
                    } else if (type === 'rma') {
                        html += `
                        <div style="background:#2a3942; padding:15px; border-radius:8px; display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <strong>RMA #${item.id}</strong> - ${item.status.toUpperCase()}<br>
                                <small style="color:#aaa;">${item.problem_description ? item.problem_description.substring(0,40) : 'Garantia/Manutenção'}... | Rastreio: ${item.tracking_code || 'N/A'}</small>
                            </div>
                            <button class="btn btn-sm" style="background:#9b59b6; color:#fff;" onclick="selectAdvItem('rma', '${item.id}', '${item.status}', '${item.tracking_code}')">Enviar <i class="fas fa-paper-plane"></i></button>
                        </div>`;
                    } else if (type === 'financeiro') {
                        let fStatus = item.payment_status === 'paid' ? 'PAGO ✅' : 'PENDENTE ⏳';
                        html += `
                        <div style="background:#2a3942; padding:15px; border-radius:8px; display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                            <div style="display:flex; align-items:center; gap:12px;">
                                <input type="checkbox" class="fin-checkbox" value="${item.id}" data-status="${item.payment_status}" data-val="${item.total_amount}" style="width:18px; height:18px; cursor:pointer;">
                                <div>
                                    <strong>Pedido #${item.id}</strong><br>
                                    <span style="color:#aaa;">Status:</span> <strong style="color:${item.payment_status === 'paid' ? '#00a884' : '#e74c3c'};">${fStatus}</strong><br>
                                    <small style="color:#aaa;">Valor: R$ ${parseFloat(item.total_amount).toFixed(2).replace('.', ',')}</small>
                                </div>
                            </div>
                            <button class="btn btn-sm" style="background:#f1c40f; color:#000;" onclick="selectAdvItem('financeiro', '${item.id}', '${item.payment_status}', '${item.total_amount}')">Cobrar <i class="fas fa-paper-plane"></i></button>
                        </div>`;
                    } else if (type === 'suporte') {
                        html += `
                        <div style="background:#2a3942; padding:15px; border-radius:8px; display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <strong>${item.title}</strong><br>
                            </div>
                            <button class="btn btn-sm" style="background:#2980b9; color:#fff;" onclick="selectAdvItem('suporte', '${item.title}', '${item.video_url || item.link_url}')">Enviar <i class="fas fa-paper-plane"></i></button>
                        </div>`;
                    }
                });
                html += '</div>';
                modalBody.innerHTML = html;
                
            } catch(e) {
                modalBody.innerHTML = `<div style="color:red; text-align:center;">Falha na comunicação com servidor.</div>`;
            }
        }
        
        function closeAdvancedModal() {
            document.getElementById('adv-modal-overlay').style.display = 'none';
        }
        
        function selectAdvItem(type, arg1, arg2, arg3) {
            const input = document.getElementById('msg-input');
            const baseUrl = window.location.origin + '/catalogo';
            
            if (type === 'catalogo') {
                input.value = `Olá! Confira o nosso produto *${arg1}*! Acesse o link para ver todos os detalhes e fazer o seu pedido:\n${baseUrl}/product.php?slug=${arg2}`;
            } else if (type === 'pedidos') {
                input.value = `Olá! Tudo bem? Passando para te atualizar sobre o seu *Pedido #${arg1}*.\nStatus Atual: *${arg2.toUpperCase()}*\n${arg3 && arg3 !== 'null' && arg3 !== 'undefined' ? '📦 Código de Rastreio: ' + arg3 + '\n🔍 Acompanhe a entrega aqui: https://www.melhorrastreio.com.br/rastreio/' + arg3 : ''}`;
            } else if (type === 'rma') {
                input.value = `Olá! Passando para te atualizar sobre a sua solicitação de suporte técnico (RMA #${arg1}).\nStatus Atual da Manutenção: *${arg2.toUpperCase()}*\n${arg3 && arg3 !== 'null' && arg3 !== 'undefined' ? '📦 Rastreio de Retorno: ' + arg3 + '\n🔍 Acompanhe aqui: https://www.melhorrastreio.com.br/rastreio/' + arg3 : ''}`;
            } else if (type === 'financeiro') {
                let statusDisplay = arg2.toLowerCase() === 'paid' ? 'PAGO ✅' : 'PENDENTE ⏳';
                input.value = `Olá! Viemos atualizar sobre a parte financeira do seu Pedido #${arg1}.\nStatus de Pagamento: *${statusDisplay}*\nValor do Pedido: R$ ${parseFloat(arg3).toFixed(2).replace('.', ',')}`;
            } else if (type === 'suporte') {
                input.value = `Olá! Vi que você tem dúvidas. Separei este material da nossa Base de Conhecimento que pode te ajudar:\n*${arg1}*\nLink: ${arg2}`;
            }
            
            closeAdvancedModal();
            input.focus();
        }

        function sendSelectedFinanceiro() {
            const checkboxes = document.querySelectorAll('.fin-checkbox:checked');
            if(checkboxes.length === 0) { alert('Selecione pelo menos um pedido.'); return; }
            
            let total = 0;
            let orderLines = [];
            checkboxes.forEach(cb => {
                let id = cb.value;
                let status = cb.dataset.status.toLowerCase() === 'paid' ? 'PAGO ✅' : 'PENDENTE ⏳';
                let val = parseFloat(cb.dataset.val);
                total += val;
                orderLines.push(`• Pedido #${id} - ${status} - R$ ${val.toFixed(2).replace('.', ',')}`);
            });
            
            const input = document.getElementById('msg-input');
            input.value = `Olá! Viemos atualizar sobre a parte financeira dos seus pedidos.\n\n📦 *Resumo dos Pedidos:*\n${orderLines.join('\n')}\n\n💰 *Total Selecionado:* R$ ${total.toFixed(2).replace('.', ',')}`;
            closeAdvancedModal();
            input.focus();
        }
    </script>
    
    <!-- Modal Avançado HTML -->
    <div id="adv-modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.8); z-index:999999; align-items:center; justify-content:center;">
        <div style="background:#111b21; width:500px; max-width:90%; border-radius:12px; border:1px solid #2a3942; display:flex; flex-direction:column; max-height:80vh;">
            <div style="padding:20px; background:#202c33; border-bottom:1px solid #2a3942; border-radius:12px 12px 0 0; display:flex; justify-content:space-between; align-items:center;">
                <h3 id="adv-modal-title" style="margin:0; color:#e9edef; font-size:1.2rem;">Ação Rápida</h3>
                <button onclick="closeAdvancedModal()" style="background:#e74c3c; color:#fff; border:none; padding:6px 14px; border-radius:6px; cursor:pointer; font-weight:bold;">X Fechar</button>
            </div>
            <div id="adv-modal-body" style="padding:20px; overflow-y:auto; flex:1; color:#e9edef;">
            </div>
        </div>
    </div>

</body>
</html>