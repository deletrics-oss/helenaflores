<?php
// catalogo/fabrica/chat.php
require_once __DIR__ . '/header.php';
?>

<style>
    /* Estilos customizados para emular o WhatsApp Web Dark Mode integrado ao Fábrica ERP */
    .chat-layout {
        display: grid;
        grid-template-columns: 320px 1fr;
        height: calc(100vh - 160px);
        background: #111b21;
        border: 1px solid var(--border);
        border-radius: 12px;
        overflow: hidden;
    }
    
    .chat-sidebar {
        border-right: 1px solid #202c33;
        display: flex;
        flex-direction: column;
        background: #111b21;
        overflow: hidden;
    }
    
    .chat-sidebar-header {
        padding: 15px;
        background: #202c33;
        border-bottom: 1px solid #2a3942;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .chat-list {
        flex: 1;
        overflow-y: auto;
        overflow-x: hidden;
    }
    
    .chat-window {
        display: flex;
        flex-direction: column;
        background: #0b141a;
        height: 100%;
        max-height: 100%;
        overflow: hidden;
    }
    
    .chat-window-header {
        padding: 10px 16px;
        background: #202c33;
        border-bottom: 1px solid #2a3942;
        display: flex;
        justify-content: space-between;
        align-items: center;
        z-index: 10;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .messages-container {
        flex: 1;
        overflow-y: auto;
        padding: 20px 5%;
        display: flex;
        flex-direction: column;
        gap: 8px;
        background-color: #0b141a;
        background-image: url('https://user-images.githubusercontent.com/15075759/28719144-86dc0f70-73b1-11e7-911d-60d70fcded21.png');
        background-attachment: fixed;
    }
    
    .chat-input-area {
        padding: 15px 16px;
        background: #202c33;
        display: flex;
        gap: 12px;
        align-items: center;
    }
</style>

<div class="chat-layout">
    
    <!-- Sidebar: Contatos B2B -->
    <div class="chat-sidebar">
        <div class="chat-sidebar-header">
            <strong style="color:#e9edef;"><i class="fab fa-whatsapp" style="color:var(--primary); font-size:1.2rem;"></i> B2B Chats</strong>
            <span style="font-size:0.75rem; color:var(--text-muted);">Evolution API</span>
        </div>
        
        <div id="chat-list" class="chat-list">
            <div style="padding:20px; text-align:center; color:#888;">
                <i class="fas fa-spinner fa-spin"></i> Carregando conversas da fábrica...
            </div>
        </div>
    </div>
    
    <!-- Main Pane: Mensagens -->
    <div id="chat-window" class="chat-window">
        <div style="flex:1; display:flex; align-items:center; justify-content:center; color:#5a6478; flex-direction:column; background:#0d1117;">
            <i class="fab fa-whatsapp" style="font-size:4.5rem; margin-bottom:1rem; opacity:0.15; color:var(--primary);"></i>
            <h3 style="color:#e2e8f0; font-weight:600; margin-bottom:5px;">Central de Atendimento da Fábrica</h3>
            <p style="color:var(--text-muted); font-size:0.9rem;">Selecione um cliente B2B na barra lateral para iniciar o atendimento.</p>
        </div>
    </div>

</div>

<!-- Modal de Atalhos Avançados -->
<div id="adv-modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.85); z-index:999999; align-items:center; justify-content:center;">
    <div style="background:#111b21; width:550px; max-width:90%; border-radius:12px; border:1px solid #2a3942; display:flex; flex-direction:column; max-height:80vh; overflow:hidden;">
        <div style="padding:20px; background:#202c33; border-bottom:1px solid #2a3942; display:flex; justify-content:space-between; align-items:center;">
            <h3 id="adv-modal-title" style="margin:0; color:#e9edef; font-size:1.15rem;">Ação Rápida</h3>
            <button onclick="closeAdvancedModal()" style="background:#ef4444; color:#fff; border:none; padding:6px 14px; border-radius:6px; cursor:pointer; font-weight:bold;">Fechar</button>
        </div>
        <div id="adv-modal-body" style="padding:20px; overflow-y:auto; flex:1; color:#e9edef;">
        </div>
    </div>
</div>

<script>
    let currentJid = '';
    let currentUserId = 0;
    const chatList = document.getElementById('chat-list');
    const chatWindow = document.getElementById('chat-window');

    async function loadChats() {
        try {
            const r = await fetch('api_chat.php?action=list_chats');
            const data = await r.json();
            
            if (data.error) {
                chatList.innerHTML = `<div style="padding:20px; color:var(--danger); font-size:0.85rem; text-align:center;">
                                        <i class="fas fa-exclamation-triangle"></i> <strong>Erro na API de Chat</strong><br>
                                        <span style="color:#aaa;">${data.error}</span>
                                     </div>`;
                return;
            }
            
            let chatsArray = Array.isArray(data) ? data : [];
            if (chatsArray.length === 0) {
                chatList.innerHTML = '<div style="padding:20px; text-align:center; color:#888;">Nenhuma conversa ativa na fábrica.</div>';
                return;
            }

            chatList.innerHTML = chatsArray.map(chat => {
                const name = chat.pushName || chat.remoteJid.split('@')[0];
                const activeClass = chat.remoteJid === currentJid ? 'background:#2a3942;' : '';
                
                let badges = '';
                if (chat.hasOrders) {
                    badges += '<span style="font-size:0.6rem; background:#3b82f6; color:#fff; padding:2px 4px; border-radius:4px; margin-left:3px;" title="Possui Vendas B2B">📦</span>';
                }
                if (chat.hasRma) {
                    badges += '<span style="font-size:0.6rem; background:#ef4444; color:#fff; padding:2px 4px; border-radius:4px; margin-left:3px;" title="Possui Defeito Relatado">⚠️ Defeito</span>';
                }
                if (chat.hasDebt) {
                    badges += '<span style="font-size:0.6rem; background:#f1c40f; color:#000; padding:2px 4px; border-radius:4px; margin-left:3px; font-weight:bold;">PENDENTE</span>';
                }
                
                let labelBadge = '';
                if (chat.contactLabel) {
                    const colors = {
                        facebook: '#3b5998', marketplace: '#e67e22', instagram: '#e1306c', indicacao: '#f39c12', site: '#2980b9'
                    };
                    const lColor = colors[chat.contactLabel] || '#64748b';
                    labelBadge = `<span style="background:${lColor}; color:#fff; font-size:0.6rem; padding:2px 5px; border-radius:4px; margin-left:5px; text-transform:uppercase;">${chat.contactLabel}</span>`;
                }

                return `
                    <div onclick="openChat('${chat.remoteJid}', '${name.replace(/'/g, "\\'")}', ${chat.userId}, ${chat.hasOrders}, ${chat.hasRma}, ${chat.hasDebt}, '${chat.contactLabel}')" 
                         style="display:flex; align-items:center; padding:10px 15px; cursor:pointer; transition:0.1s; border-bottom:1px solid #202c33; ${activeClass}">
                        
                        <img src="https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=random&size=45" style="width:45px; height:45px; border-radius:50%; margin-right:12px;">
                        
                        <div style="flex:1; display:flex; flex-direction:column; justify-content:center; min-width:0;">
                            <strong style="color:#e9edef; font-size:0.95rem; text-overflow:ellipsis; overflow:hidden; white-space:nowrap; display:flex; align-items:center;">
                                ${name}
                            </strong>
                            <div style="display:flex; gap:4px; align-items:center; margin-top:3px; overflow:hidden;">
                                <small style="color:#8696a0; font-size:0.8rem;">${chat.remoteJid.split('@')[0]}</small>
                                ${badges} ${labelBadge}
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        } catch(e) {
            chatList.innerHTML = '<div style="padding:20px; color:#ef4444; text-align:center;">Erro ao comunicar com a API.</div>';
        }
    }

    async function openChat(jid, name, userId, hasOrders, hasRma, hasDebt, contactLabel) {
        currentJid = jid;
        currentUserId = userId;
        const numberOnly = jid.split('@')[0];
        
        let topBadges = '';
        if (userId) {
            topBadges += `<span style="background:rgba(0,230,118,0.1); color:var(--primary); border:1px solid var(--primary); padding:2px 6px; border-radius:4px; font-size:0.7rem; font-weight:bold;">Cliente B2B</span>`;
        } else {
            topBadges += `<span style="background:rgba(239,68,68,0.1); color:var(--danger); border:1px solid var(--danger); padding:2px 6px; border-radius:4px; font-size:0.7rem; font-weight:bold;">Desconhecido</span>`;
        }
        if (hasOrders) {
            topBadges += `<span style="background:#2b3952; color:#3b82f6; padding:2px 6px; border-radius:4px; font-size:0.7rem; font-weight:bold;">📦 Vendas</span>`;
        }
        if (hasRma) {
            topBadges += `<span style="background:#2b3952; color:#ef4444; padding:2px 6px; border-radius:4px; font-size:0.7rem; font-weight:bold;">⚠️ Defeito</span>`;
        }

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
            <div class="chat-window-header">
                <div style="display:flex; align-items:center; gap:12px;">
                    <img id="chat-avatar" src="https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=random&size=40" 
                         style="width:40px; height:40px; border-radius:50%; cursor:pointer;" 
                         onclick="window.open(this.src, '_blank')">
                    <div style="display:flex; flex-direction:column;">
                        <strong style="color:#e9edef; font-size:1rem; ${userId ? 'cursor:pointer;' : ''}"
                                ${userId ? `onclick="window.open('client-details.php?id=${userId}', '_blank')" title="Ver Extrato e Histórico B2B"` : ''}>
                            ${name} ${userId ? '<i class="fas fa-external-link-alt" style="font-size:0.75rem; margin-left:3px; color:var(--primary);"></i>' : ''}
                        </strong>
                        <div style="display:flex; gap:6px; margin-top:2px; align-items:center; flex-wrap:wrap;">
                            ${topBadges}
                            <select onchange="setContactLabel('${jid}', this.value)" style="background:#1a252c; color:#8696a0; border:1px solid #2a3942; border-radius:4px; padding:1px 4px; font-size:0.65rem; cursor:pointer;">${labelSelect}</select>
                        </div>
                    </div>
                </div>
                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                    <button class="btn btn-sm btn-primary" onclick="openAdvancedModal('catalogo')" title="Enviar Catálogo"><i class="fas fa-tags"></i> Produtos</button>
                    <button class="btn btn-sm btn-secondary" onclick="openAdvancedModal('pedidos')" title="Status de Pedidos"><i class="fas fa-box-open"></i> Pedidos</button>
                    <button class="btn btn-sm btn-secondary" style="background:#8e44ad; color:#fff;" onclick="openAdvancedModal('rma')" title="Defeitos Relatados"><i class="fas fa-exclamation-triangle"></i> Defeitos</button>
                    <button class="btn btn-sm btn-secondary" style="background:#f1c40f; color:#000;" onclick="openAdvancedModal('financeiro')" title="Resumo de Cobrança"><i class="fas fa-file-invoice-dollar"></i> Financeiro</button>
                    <button class="btn btn-sm" style="background:transparent; color:var(--primary); border:1px solid var(--primary);" onclick="window.open('https://wa.me/${numberOnly}', '_blank')"><i class="fab fa-whatsapp"></i> Abrir Web</button>
                </div>
            </div>
            
            <div id="messages-container" class="messages-container">
                <div style="text-align:center; color:#888; background:rgba(32,44,51,0.8); padding:5px 15px; border-radius:10px; width:fit-content; margin:0 auto;"><i class="fas fa-spinner fa-spin"></i> Sincronizando histórico...</div>
            </div>
            
            <div class="chat-input-area">
                <input type="text" id="msg-input" placeholder="Escreva uma mensagem..." 
                       style="flex:1; padding:12px 16px; background:#2a3942; border:none; color:#d1d7db; border-radius:8px; font-size:0.95rem; outline:none;"
                       onkeypress="if(event.key==='Enter') sendMessage()">
                <button onclick="sendMessage()" class="btn btn-primary" style="border-radius:8px; padding:10px 18px; font-weight:bold;">
                    <i class="fas fa-paper-plane"></i> Enviar
                </button>
            </div>
        `;
        
        loadMessages(jid);
        fetchProfilePic(jid);
        loadChats();
    }

    async function loadMessages(jid) {
        try {
            const r = await fetch(`api_chat.php?action=get_messages&remoteJid=${jid}`);
            const data = await r.json();
            const container = document.getElementById('messages-container');
            
            let msgs = Array.isArray(data) ? data : [];
            if (msgs.length === 0) {
                container.innerHTML = '<div style="text-align:center; color:#e9edef; background:rgba(32,44,51,0.9); padding:10px 20px; border-radius:10px; width:fit-content; margin:20px auto; font-size:0.85rem;"><i class="fas fa-info-circle"></i> Comece a conversa enviando uma mensagem abaixo!</div>';
                return;
            }

            container.innerHTML = msgs.reverse().map(m => {
                const fromMe = m.fromMe === true;
                const text = m.textMessage?.text || '<i>[Mídia/Arquivo]</i> 📷';
                
                const bg = fromMe ? '#005c4b' : '#202c33';
                const color = '#e9edef';
                const align = fromMe ? 'align-self: flex-end' : 'align-self: flex-start';
                const borderRadius = fromMe ? '8px 0px 8px 8px' : '0px 8px 8px 8px';
                
                let timeStr = '';
                if (m.messageTimestamp) {
                    const date = new Date(m.messageTimestamp * 1000);
                    timeStr = date.getHours().toString().padStart(2, '0') + ':' + date.getMinutes().toString().padStart(2, '0');
                }
                
                return `
                    <div style="${align}; background:${bg}; color:${color}; padding:6px 9px; border-radius:${borderRadius}; max-width:65%; font-size:0.9rem; box-shadow:0 1px 0.5px rgba(0,0,0,0.15); display:flex; flex-direction:column;">
                        <span style="word-wrap:break-word; line-height:1.4; margin-bottom:3px;">${text}</span>
                        <span style="font-size:0.65rem; color:rgba(255,255,255,0.55); align-self:flex-end;">${timeStr} ${fromMe ? '<i class="fas fa-check-double" style="color:#53bdeb; margin-left:3px;"></i>' : ''}</span>
                    </div>
                `;
            }).join('');
            container.scrollTop = container.scrollHeight;
        } catch(e) {
            const container = document.getElementById('messages-container');
            if(container) container.innerHTML = '<div style="text-align:center; color:#888;">Erro ao carregar histórico.</div>';
        }
    }

    async function sendMessage() {
        const input = document.getElementById('msg-input');
        const text = input.value.trim();
        if (!text || !currentJid) return;

        input.value = '';
        const number = currentJid.split('@')[0];

        try {
            await fetch('api_chat.php?action=send_message', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ number, text })
            });
            loadMessages(currentJid);
        } catch(e) {
            alert('Falha ao enviar mensagem.');
        }
    }

    async function setContactLabel(jid, label) {
        try {
            await fetch(`api_chat.php?action=set_label&jid=${encodeURIComponent(jid)}&label=${encodeURIComponent(label)}`);
            loadChats();
        } catch(e) {}
    }

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

    // Modal Avançado de Ações da Fábrica
    let currentModalType = '';
    async function openAdvancedModal(type) {
        if (!currentJid) return;
        currentModalType = type;
        const number = currentJid.split('@')[0];
        const modalTitle = document.getElementById('adv-modal-title');
        const modalBody = document.getElementById('adv-modal-body');
        
        let titles = {
            'catalogo': '🛍️ Selecionar Produto da Fábrica',
            'pedidos': '📦 Pedidos de Venda B2B',
            'rma': '⚠️ Tickets de Defeito Relatados',
            'financeiro': '💰 Extrato Financeiro B2B'
        };
        modalTitle.innerText = titles[type] || 'Ação Rápida';
        modalBody.innerHTML = '<div style="text-align:center; padding:30px;"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';
        
        document.getElementById('adv-modal-overlay').style.display = 'flex';
        
        try {
            const r = await fetch(`api_chat.php?action=get_quick_data&type=${type}&phone=${number}&uid=${currentUserId}`);
            const data = await r.json();
            
            if(!data.success) {
                modalBody.innerHTML = `<div style="color:red; text-align:center;">Erro: ${data.error}</div>`;
                return;
            }
            if(!data.data || data.data.length === 0) {
                modalBody.innerHTML = '<div style="text-align:center; padding:20px; color:#aaa;">Nenhum registro encontrado no banco de dados.</div>';
                return;
            }
            
            let html = '<div style="display:flex; flex-direction:column; gap:10px;">';
            
            if(type === 'financeiro') {
                html += `
                <div style="display:flex; justify-content:space-between; margin-bottom:15px; background:#202c33; padding:10px; border-radius:8px; align-items:center;">
                    <button onclick="sendSelectedFinanceiro()" class="btn btn-sm btn-primary" style="width:100%;"><i class="fas fa-paper-plane"></i> Gerar e Enviar Extrato Selecionado</button>
                </div>`;
            }

            data.data.forEach(item => {
                if(type === 'catalogo') {
                    html += `
                    <div style="background:#202c33; padding:12px; border-radius:8px; display:flex; justify-content:space-between; align-items:center; border:1px solid var(--border);">
                        <div>
                            <strong>${item.name}</strong><br>
                            <small style="color:var(--primary);">R$ ${parseFloat(item.price).toFixed(2).replace('.', ',')}</small> | <small style="font-family:monospace; color:var(--text-muted);">${item.sku || 'N/A'}</small>
                        </div>
                        <button class="btn btn-sm btn-primary" onclick="selectAdvItem('catalogo', '${item.name}', '${item.price}', '${item.sku}')">Selecionar</button>
                    </div>`;
                } else if (type === 'pedidos') {
                    html += `
                    <div style="background:#202c33; padding:12px; border-radius:8px; display:flex; justify-content:space-between; align-items:center; border:1px solid var(--border);">
                        <div>
                            <strong>Venda #${item.id}</strong> - <span class="badge badge-info">${item.status.toUpperCase()}</span><br>
                            <small style="color:var(--text-muted);">Total: R$ ${parseFloat(item.total_amount).toFixed(2).replace('.', ',')} | Rastreio: ${item.tracking_code || 'Não enviado'}</small>
                        </div>
                        <button class="btn btn-sm btn-primary" onclick="selectAdvItem('pedidos', '${item.id}', '${item.status}', '${item.tracking_code}')">Selecionar</button>
                    </div>`;
                } else if (type === 'rma') {
                    html += `
                    <div style="background:#202c33; padding:12px; border-radius:8px; display:flex; justify-content:space-between; align-items:center; border:1px solid var(--border);">
                        <div>
                            <strong>Defeito #${item.id}</strong> - <span class="badge badge-danger">${item.status.toUpperCase()}</span><br>
                            <small style="color:var(--text-muted);">${item.problem_description.substring(0,60)}...</small>
                        </div>
                        <button class="btn btn-sm btn-primary" onclick="selectAdvItem('rma', '${item.id}', '${item.status}', '')">Selecionar</button>
                    </div>`;
                } else if (type === 'financeiro') {
                    html += `
                    <div style="background:#202c33; padding:12px; border-radius:8px; display:flex; justify-content:space-between; align-items:center; border:1px solid var(--border);">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <input type="checkbox" class="fin-checkbox" value="${item.id}" data-val="${item.total_amount}" data-status="${item.payment_status}" style="width:16px; height:16px;">
                            <div>
                                <strong>Pedido #${item.id}</strong> - <span style="font-weight:bold; color:${item.payment_status === 'paid' ? '#00e676' : '#ef4444'};">${item.payment_status === 'paid' ? 'Pago' : 'Pendente'}</span><br>
                                <small style="color:var(--text-muted);">R$ ${parseFloat(item.total_amount).toFixed(2).replace('.', ',')}</small>
                            </div>
                        </div>
                        <button class="btn btn-sm btn-primary" onclick="selectAdvItem('financeiro', '${item.id}', '${item.payment_status}', '${item.total_amount}')">Selecionar</button>
                    </div>`;
                }
            });
            
            html += '</div>';
            modalBody.innerHTML = html;
        } catch(e) {
            modalBody.innerHTML = '<div style="color:#ef4444; text-align:center;">Erro ao carregar dados.</div>';
        }
    }

    function closeAdvancedModal() {
        document.getElementById('adv-modal-overlay').style.display = 'none';
    }

    function selectAdvItem(type, arg1, arg2, arg3) {
        const input = document.getElementById('msg-input');
        
        if (type === 'catalogo') {
            input.value = `Olá! Indico o nosso produto da fábrica: *${arg1}*.\nPreço Unitário B2B: R$ ${parseFloat(arg2).toFixed(2).replace('.', ',')}\nSKU: ${arg3 || 'N/A'}\n\nDeseja fechar um lote deste item?`;
        } else if (type === 'pedidos') {
            input.value = `Olá! Passando para atualizar sobre o seu Pedido de Venda B2B #${arg1}.\nStatus Atual: *${arg2.toUpperCase()}*\n${arg3 && arg3 !== 'null' && arg3 !== 'undefined' && arg3 !== '' ? '📦 Código de Rastreio: ' + arg3 + '\n🔍 Link de Rastreio: https://www.melhorrastreio.com.br/rastreio/' + arg3 : 'Aguardando envio do código de postagem.'}`;
        } else if (type === 'rma') {
            input.value = `Olá! A respeito do Defeito Relatado #${arg1}, o status atual do ticket é: *${arg2.toUpperCase()}*.\nA equipe técnica está trabalhando para resolver.`;
        } else if (type === 'financeiro') {
            let status = arg2 === 'paid' ? 'PAGO ✅' : 'PENDENTE DE PAGAMENTO ⏳';
            input.value = `Olá! Passando o status financeiro do Pedido #${arg1}.\nValor: R$ ${parseFloat(arg3).toFixed(2).replace('.', ',')}\nStatus: *${status}*`;
        }
        
        closeAdvancedModal();
        input.focus();
    }

    function sendSelectedFinanceiro() {
        const checkboxes = document.querySelectorAll('.fin-checkbox:checked');
        if(checkboxes.length === 0) { alert('Selecione pelo menos uma fatura.'); return; }
        
        let total = 0;
        let lines = [];
        checkboxes.forEach(cb => {
            let val = parseFloat(cb.dataset.val);
            total += val;
            let status = cb.dataset.status === 'paid' ? 'Pago ✅' : 'Pendente ⏳';
            lines.push(`• Pedido #${cb.value} - R$ ${val.toFixed(2).replace('.', ',')} (${status})`);
        });
        
        const input = document.getElementById('msg-input');
        input.value = `Olá! Segue o extrato financeiro dos seus pedidos de fábrica:\n\n${lines.join('\n')}\n\n💰 *Total Geral:* R$ ${total.toFixed(2).replace('.', ',')}\nPor favor, confirme se há algum comprovante pendente. Obrigado!`;
        closeAdvancedModal();
        input.focus();
    }

    // Auto-carrega chats e inicia loops de monitoramento
    loadChats();
    setInterval(loadChats, 15000); // 15 segundos
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
