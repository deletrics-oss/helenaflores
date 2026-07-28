<?php
/**
 * includes/ai_sdr.php — Fight Arcade
 * Engine Gemini 2.5 Flash — VERSÃO FINAL (MEMÓRIA PERMANENTE)
 */

class AIService {

    private $pdo;
    private $apiKey;
    private $isActive;
    private $allSettings = [];
    private $customLogic  = '';

    // Modelo validado com SUCESSO no teste_real.php
    private const MODEL         = 'gemini-2.5-flash';
    private const HISTORY_LIMIT = 10;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->loadConfig();
        $this->ensureSchema();
    }

    private function loadConfig() {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT is_active, settings_json FROM module_settings WHERE module_key = 'ai_sdr'"
            );
            $stmt->execute();
            $mod = $stmt->fetch();
            if ($mod) {
                $this->isActive    = (bool) $mod['is_active'];
                $this->allSettings = json_decode($mod['settings_json'], true) ?? [];
                $this->apiKey      = $this->allSettings['gemini_key'] ?? '';
                $this->customLogic = $this->allSettings['custom_logic'] ?? '';
            } else {
                $this->isActive = false;
            }
        } catch (Exception $e) { $this->isActive = false; }
    }

    public function isActive(): bool {
        return $this->isActive && !empty($this->apiKey);
    }

    private function ensureSchema() {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS ai_logs (
                    id         INT AUTO_INCREMENT PRIMARY KEY,
                    sender     VARCHAR(30)  NOT NULL,
                    role       VARCHAR(15)  NOT NULL DEFAULT 'user',
                    message    TEXT,
                    response   TEXT,
                    type       VARCHAR(20)  DEFAULT 'sdr',
                    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_sender (sender),
                    INDEX idx_created (created_at)
                )
            ");
        } catch (Exception $e) {}
    }

    public function getSystemContext(bool $isAdmin = false, ?string $instanceName = null, ?string $senderJid = null): string {
        // Verifica se é a instância da fábrica
        $isFactoryInstance = false;
        try {
            $factoryInst = $this->pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'notif_factory_waapi_instance'")->fetchColumn();
            if ($factoryInst && $instanceName && strcasecmp($factoryInst, $instanceName) === 0) {
                $isFactoryInstance = true;
            }
        } catch(Exception $e) {}

        $hideStore = false;
        if (!$isAdmin && $senderJid) {
            if (strpos($senderJid, 'cron_sdr_') === 0) {
                $uid = (int)str_replace('cron_sdr_', '', $senderJid);
                try {
                    $hideStore = (bool) $this->pdo->query("SELECT hide_store_name FROM users WHERE id = $uid")->fetchColumn();
                } catch (\Throwable $e) {}
            } else {
                $senderPhone = preg_replace('/\D/', '', explode('@', $senderJid)[0]);
                if (strlen($senderPhone) >= 8) {
                    try {
                        $hideStore = (bool) $this->pdo->query("SELECT hide_store_name FROM users WHERE phone LIKE '%$senderPhone%' LIMIT 1")->fetchColumn();
                    } catch (\Throwable $e) {}
                }
            }
        }

        if ($isFactoryInstance) {
            // Busca Produtos B2B da Fábrica
            $factoryProducts = $this->pdo->query("
                SELECT id, name, price, price_wholesale, sku, description
                FROM   factory_products
                ORDER BY id DESC LIMIT 50
            ")->fetchAll(PDO::FETCH_ASSOC);

            if ($isAdmin) {
                $totalProds = count($factoryProducts);
                
                // Tarefas Fábrica
                $tasks = $this->pdo->query("SELECT task_text FROM factory_tasks WHERE is_completed = 0 LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);
                $taskList = $tasks ? "\n📝 TAREFAS DA FÁBRICA:\n• " . implode("\n• ", $tasks) : "\nSem tarefas pendentes na fábrica.";

                // Devedores B2B
                $debtorList = "";
                try {
                    $debtors = $this->pdo->query("
                        SELECT fc.name, COALESCE(SUM(fs.total_amount), 0) as current_debt 
                        FROM factory_clients fc 
                        JOIN factory_sales fs ON fc.id = fs.client_id 
                        WHERE fs.status = 'pending' 
                        GROUP BY fc.id 
                        ORDER BY current_debt DESC 
                        LIMIT 5
                    ")->fetchAll(PDO::FETCH_ASSOC);
                    if ($debtors) {
                        $debtorList = "\n💸 DEVEDORES B2B:\n";
                        foreach($debtors as $d) {
                            $debtorList .= "• {$d['name']}: R$ " . number_format($d['current_debt'], 2, ',', '.') . "\n";
                        }
                    }
                } catch(Exception $e) {}

                // OP Ativas
                $opsList = "";
                try {
                    $activeOps = $this->pdo->query("
                        SELECT po.id, p.name as product_name, po.status 
                        FROM factory_production_orders po
                        JOIN factory_products p ON po.product_id = p.id
                        WHERE po.status != 'completed'
                        LIMIT 5
                    ")->fetchAll(PDO::FETCH_ASSOC);
                    if ($activeOps) {
                        $opsList = "\n⚙️ ORDENS DE PRODUÇÃO ATIVAS:\n";
                        foreach($activeOps as $op) {
                            $opsList .= "• OP #{$op['id']} - {$op['product_name']} ({$op['status']})\n";
                        }
                    }
                } catch(Exception $e) {}

                return "VOCÊ É O GERENTE ESTRATÉGICO DA FÁBRICA ERP (B2B).\n" .
                       "Status: ONLINE | Catálogo: {$totalProds} produtos B2B\n" .
                       $taskList . $debtorList . $opsList . "\n\n" .
                       "DIRETRIZES ADMIN FÁBRICA:\n" .
                       "- Responda a equipe com informações precisas sobre produção, entregas, produtos e compras de insumos.\n" .
                       "- Se ele pedir para anotar uma tarefa: [[TASK_ADD: texto]]\n" .
                       "- Se ele disser que concluiu uma tarefa da fábrica: [[TASK_DONE: texto]]\n" .
                       "Responda de forma direta, clara e profissional.";
            }

            // Cliente B2B
            $catalog = '';
            foreach ($factoryProducts as $p) {
                $sku = $p['sku'] ? " [SKU: {$p['sku']}]" : "";
                $desc = $p['description'] ? "\n   INFO: " . mb_strimwidth(strip_tags($p['description']), 0, 300, "...") : "";
                $catalog .= "• {$p['name']}{$sku} - Preço Varejo B2B: R$" . number_format((float) $p['price'], 2, ',', '.') . " | Preço Atacado: R$" . number_format((float) $p['price_wholesale'], 2, ',', '.') . "{$desc}\n";
            }

            $ctx = "VOCÊ É O ASSISTENTE COMERCIAL DA FÁBRICA FIGHT ARCADE (ATENDIMENTO B2B).\n";
            $ctx .= "Seu objetivo é auxiliar clientes comerciais (B2B) com preços de atacado, dúvidas de peças de fliperama e andamento de ordens de produção.\n\n";
            $ctx .= "CATÁLOGO DE PRODUTOS DA FÁBRICA B2B:\n{$catalog}\n";
            $ctx .= "REGRAS DE ATENDIMENTO FÁBRICA B2B:\n";
            $ctx .= "1. Seja extremamente profissional e focado no atacado.\n";
            $ctx .= "2. Se o cliente solicitar orçamento ou cotação, passe as informações e links correspondentes.\n";
            $ctx .= "3. Para defeitos/problemas com peças recebidas da fábrica, instrua o cliente a enviar uma foto aqui contendo a palavra 'defeito' ou 'problema' na legenda para abertura automática do ticket de suporte.\n";
            $ctx .= "4. Se o cliente solicitar falar com um humano, use a tag [[SDR_PAUSE]].\n";
            $ctx .= "5. Envie links puros sem formatação markdown para cliques fáceis.";
            
            return $ctx;
        }

        $settingsFile = __DIR__ . '/site_settings.json';
        $siteData     = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [];
        
        // Links Absolutos
        $baseUrl    = 'https://fightarcade.com.br/catalogo';
        $portalUrl  = "{$baseUrl}/support.php";  
        $requestUrl = "{$baseUrl}/suporte.php";  

        // 1. Busca Catálogo Ultra-Completo (SKU, EAN, Descrição)
        $products = $this->pdo->query("
            SELECT p.id, p.name, p.slug, p.price, p.price_wholesale, p.sku, p.ean, p.description, c.name AS category
            FROM   products p
            LEFT   JOIN categories c ON p.category_id = c.id
            WHERE  p.active = 1 AND p.show_on_site = 1
            ORDER BY p.id DESC LIMIT 50
        ")->fetchAll(PDO::FETCH_ASSOC);

        if ($isAdmin) {
            $totalProds = count($products);
            $kbCount = $this->pdo->query("SELECT COUNT(*) FROM ai_knowledge")->fetchColumn();
            
            // 1. Tarefas
            $tasks = $this->pdo->query("SELECT task_text FROM admin_tasks WHERE is_completed = 0 LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);
            $taskList = $tasks ? "\n📝 TAREFAS:\n• " . implode("\n• ", $tasks) : "\nSem tarefas pendentes.";

            // 2. Financeiro / Devedores
            $debtors = $this->pdo->query("SELECT name, current_debt FROM users WHERE current_debt > 0 ORDER BY current_debt DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
            $debtorList = "";
            if ($debtors) {
                $debtorList = "\n💸 DEVEDORES:\n";
                foreach($debtors as $d) $debtorList .= "• {$d['name']}: R$ " . number_format($d['current_debt'], 2, ',', '.') . "\n";
            }

            // 3. Clientes Inativos (> 7 dias)
            $inactive = $this->pdo->query("
                SELECT u.name, MAX(o.created_at) as last_order 
                FROM users u 
                JOIN orders o ON u.id = o.user_id 
                GROUP BY u.id 
                HAVING last_order < DATE_SUB(NOW(), INTERVAL 7 DAY)
                LIMIT 5
            ")->fetchAll(PDO::FETCH_ASSOC);
            $inactiveList = "";
            if ($inactive) {
                $inactiveList = "\n⚠️ INATIVOS (>7 dias):\n";
                foreach($inactive as $i) $inactiveList .= "• {$i['name']} (Última: " . date('d/m', strtotime($i['last_order'])) . ")\n";
            }

            // 4. Fornecedores
            $suppliers = $this->pdo->query("SELECT name, contact_info FROM suppliers LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
            $supList = "";
            if ($suppliers) {
                $supList = "\n📦 FORNECEDORES:\n";
                foreach($suppliers as $s) $supList .= "• {$s['name']} ({$s['contact_info']})\n";
            }

            $storeName = $siteData['store_name'] ?? 'Loja';
            return "VOCÊ É O ESTRATEGISTA-CHEFE DA {$storeName}.\n" .
                   "Status: ONLINE | Catálogo: {$totalProds} itens | KB: {$kbCount}\n" .
                   $taskList . $debtorList . $inactiveList . $supList . "\n\n" .
                   "DIRETRIZES ADMIN:\n" .
                   "- Se o dono perguntar de um cliente, use seus dados para dar: Total de Pedidos, Lucro Estimado e Itens Comprados.\n" .
                   "- Se ele pedir para anotar algo: [[TASK_ADD: texto]]\n" .
                   "- Se ele disser que concluiu algo: [[TASK_DONE: texto]]\n" .
                   "- Ao extrair dados de RMA: CEPs (8 dígitos) NUNCA são telefones. Valide o formato (11) 9XXXX-XXXX.\n" .
                   "Responda de forma executiva e direta.";
        }

        $catalog = '';
        foreach ($products as $p) {
            $link      = " | Link: {$baseUrl}/product.php?slug={$p['slug']}";
            $sku       = $p['sku'] ? " [SKU: {$p['sku']}]" : "";
            $desc      = $p['description'] ? "\n   INFO: " . mb_strimwidth(strip_tags($p['description']), 0, 300, "...") : "";
            $catalog  .= "• {$p['name']} ({$p['category']}){$sku} - R$" . number_format((float) $p['price'], 2, ',', '.') . "{$link}{$desc}\n";
        }

        // 2. Busca Base de Conhecimento (Filtrada por Persona se necessário)
        $kbData = '';
        try {
            // Se for admin, pega tudo. Se for cliente, podemos passar o papel (suporte/vendas)
            $roleFilter = $isAdmin ? "'geral','suporte','vendas'" : "'geral','suporte'"; // Default para cliente é suporte? 
            // Na verdade, vamos deixar flexível.
            
            $kbItems = $this->pdo->query("SELECT title, content, image_url, link_url, video_url, tags, ai_instructions FROM ai_knowledge WHERE bot_role IN ($roleFilter) LIMIT 40")->fetchAll(PDO::FETCH_ASSOC);
            if ($kbItems) {
                $kbData = "\nBASE DE CONHECIMENTO TÉCNICO E SUPORTE:\n";
                foreach ($kbItems as $kb) {
                    $kbData .= "• {$kb['title']}: {$kb['content']}";
                    if ($kb['tags'])      $kbData .= " (Tags: {$kb['tags']})";
                    if ($kb['link_url'])  $kbData .= " | Link: {$kb['link_url']}";
                    if ($kb['video_url']) $kbData .= " | Vídeo: {$kb['video_url']}";
                    if ($kb['ai_instructions']) $kbData .= " {DICA: {$kb['ai_instructions']}}";
                    $kbData .= "\n";
                }
            }
        } catch (Exception $e) {}

        $storeName = $siteData['store_name'] ?? 'Fight Arcade';
        if ($hideStore) {
            $storeName = 'Catálogo de Games';
        }
        $logic     = $this->customLogic;

        $ctx  = "VOCÊ É O VENDEDOR E ESPECIALISTA EM SUPORTE TÉCNICO DA {$storeName}.\n";
        $ctx .= "Seu tom de voz deve ser extremamente humano, acolhedor, profissional e natural, como se fosse um atendente real.\n";
        $ctx .= "Você conhece tudo sobre o site, e-commerce e peças/montagem de fliperamas (comandos, botões, placas zero delay, chicotes, caixas, etc.).\n";
        $ctx .= "Seu objetivo é vender produtos do catálogo com base em detalhes técnicos e ajudar os clientes com dúvidas de suporte e montagem.\n\n";
        $ctx .= "DIRETRIZES DE SUPORTE:\n";
        $ctx .= "- DÚVIDAS TÉCNICAS/VÍDEOS: {$portalUrl}\n";
        $ctx .= "- GARANTIA/RMA/PEÇAS: {$requestUrl}\n\n";
        $ctx .= "DIRETRIZES DO DONO:\n{$logic}\n\n";
        $ctx .= "CATÁLOGO TÉCNICO (USE ESTES DETALHES PARA CONVENCER O CLIENTE):\n{$catalog}\n";
        if ($kbData) $ctx .= $kbData . "\n";
        
        $ctx .= "REGRAS DE ATENDIMENTO (AGILIDADE, HUMANIZAÇÃO E COMPATIBILIDADE):\n";
        $ctx .= "1. LINKS PUROS: NUNCA use o formato [Texto](Link). O WhatsApp não suporta. Envie sempre o LINK PURO in uma linha separada para que seja clicável.\n";
        $ctx .= "2. SEJA DIRETO E PRESTATIVO: Se o cliente citou um produto ou dúvida, envie as informações relevantes e o LINK IMEDIATAMENTE.\n";
        $ctx .= "3. EVITE EMOTICONS/EMOJIS EM EXCESSO: NÃO fique usando emojis em todas as frases. Use-os de forma muito sutil, no máximo um emoji por mensagem e apenas se for muito pertinente. Mantenha a resposta limpa e natural.\n";
        $ctx .= "4. TOM HUMANO E NATURAL: Escreva de forma fluida, sem respostas engessadas, robóticas ou saudações repetitivas. Use termos comuns de quem entende de fliperama no Brasil, mantendo a cordialidade.\n";
        $ctx .= "5. SUPORTE: Portal de Vídeos ({$portalUrl}) | Peças e RMA ({$requestUrl}).\n";
        $ctx .= "6. NUNCA diga que é um assistente de IA, robô ou modelo de linguagem. Você é o atendente técnico oficial da {$storeName}. Responda em Português do Brasil.\n";
        $ctx .= "7. INTERVENÇÃO HUMANA: Se o cliente pedir para falar com um humano, ficar irritado, ou pedir algo que você não sabe, você DEVE adicionar a tag secreta [[SDR_PAUSE]] no final da sua resposta. Isso desativará a IA e chamará o vendedor real. Exemplo: 'Um momento, vou chamar um especialista para te ajudar! [[SDR_PAUSE]]'";

        if ($hideStore) {
            $ctx = str_ireplace('Fight Arcade', 'Catálogo de Games', $ctx);
            if (!empty($siteData['store_name'])) {
                $ctx = str_ireplace($siteData['store_name'], 'Catálogo de Games', $ctx);
            }
        }

        return $ctx;
    }

    private function getHistory(string $sender): array {
        try {
            $limit = self::HISTORY_LIMIT;
            $stmt  = $this->pdo->prepare("
                SELECT message, response
                FROM   ai_logs
                WHERE  sender  = ?
                  AND  type    IN ('sdr', 'admin')
                  AND  response IS NOT NULL
                  AND  response NOT LIKE 'ERRO%'
                ORDER  BY created_at DESC
                LIMIT  {$limit}
            ");
            $stmt->execute([$sender]);

            $rows    = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
            $history = [];
            foreach ($rows as $row) {
                $history[] = ['role' => 'user',  'parts' => [['text' => $row['message']]]];
                $history[] = ['role' => 'model', 'parts' => [['text' => $row['response']]]];
            }
            return $history;
        } catch (Exception $e) { return []; }
    }

    public function generateResponse(string $userMessage, string $senderId, bool $isAdmin = false, ?string $instanceName = null, ?string $customPromptOverride = null): ?string {
        if (!$this->isActive()) return null;

        // Check if this sender has stealth mode active
        $hideStore = false;
        if (!$isAdmin && $senderId) {
            if (strpos($senderId, 'cron_sdr_') === 0) {
                $uid = (int)str_replace('cron_sdr_', '', $senderId);
                try {
                    $hideStore = (bool) $this->pdo->query("SELECT hide_store_name FROM users WHERE id = $uid")->fetchColumn();
                } catch (\Throwable $e) {}
            } else {
                $senderPhone = preg_replace('/\D/', '', explode('@', $senderId)[0]);
                if (strlen($senderPhone) >= 8) {
                    try {
                        $hideStore = (bool) $this->pdo->query("SELECT hide_store_name FROM users WHERE phone LIKE '%$senderPhone%' LIMIT 1")->fetchColumn();
                    } catch (\Throwable $e) {}
                }
            }
        }

        $systemPrompt = $customPromptOverride !== null ? $customPromptOverride : $this->getSystemContext($isAdmin, $instanceName, $senderId);
        $history      = $this->getHistory($senderId);

        // Sanitize history to prevent leakage of the store name from previous messages
        if ($hideStore && !empty($history)) {
            $settingsFile = __DIR__ . '/site_settings.json';
            $siteData     = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [];
            $storeName    = $siteData['store_name'] ?? 'Fight Arcade';

            foreach ($history as &$h) {
                if (isset($h['parts'][0]['text'])) {
                    $h['parts'][0]['text'] = str_ireplace(['Fight Arcade', $storeName], 'Catálogo de Games', $h['parts'][0]['text']);
                }
            }
        }
        
        $history[]    = ['role' => 'user', 'parts' => [['text' => $userMessage]]];

        // Estrutura Oficial com systemInstruction para Memória Permanente
        $payload = [
            'systemInstruction' => [
                'parts' => [['text' => $systemPrompt]]
            ],
            'contents'         => $history,
            'generationConfig' => [
                'temperature'     => 0.75,
                'maxOutputTokens' => 800,
                'topP'            => 0.9,
            ],
        ];

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . self::MODEL . ':generateContent?key=' . $this->apiKey;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data   = json_decode($response, true);
        $aiText = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (!$aiText) {
            $errorMsg = $data['error']['message'] ?? "Status {$httpCode}";
            try {
                $this->pdo->prepare(
                    "INSERT INTO ai_logs (sender, role, message, response, type) VALUES (?, 'user', ?, ?, 'error')"
                )->execute([$senderId, substr($userMessage, 0, 100), "ERRO Gemini: $errorMsg"]);
            } catch (Exception $e) {}
        }

        return $aiText;
    }

    /**
     * Extração Inteligente de Dados (RMA/Cadastro)
     * Ignora metadados do WhatsApp e foca nos dados reais.
     */
    public function extractCustomerData(string $text): array {
        if (!$this->isActive()) return [];

        $prompt = "AJA COMO UM EXTRATOR DE DADOS DE ALTA PRECISÃO.
        Sua tarefa é extrair dados de um texto (geralmente copiado do WhatsApp) e retornar APENAS um JSON válido.

        REGRAS CRÍTICAS:
        1. IGNORAR CABEÇALHOS DE WHATSAPP: Ex: '[17:34, 11/05/2026] +55 18 99700-6575:'. NUNCA use esses dados como Nome ou Telefone do cliente.
        2. DIFERENCIAR CAMPOS:
           - CEP: Deve ter 8 dígitos (pode ter hífen). Ex: 16350-120.
           - TELEFONE: Deve ter o DDD. Ex: 18988285790.
           - PIX: Se for um número de telefone, extraia-o para o campo 'phone' se o campo estiver vazio.
           - NOME: Procure pelo nome real da pessoa (geralmente após o endereço ou próximo ao Pix).
        3. ENDEREÇO: Separe em rua, numero, bairro, cidade e uf.

        FORMATO DE SAÍDA (JSON):
        {
          \"name\": \"\",
          \"document\": \"\",
          \"phone\": \"\",
          \"zipcode\": \"\",
          \"address\": \"\",
          \"number\": \"\",
          \"complement\": \"\",
          \"neighborhood\": \"\",
          \"city\": \"\",
          \"state\": \"\",
          \"email\": \"\"
        }

        TEXTO PARA EXTRAIR:
        $text";

        $payload = [
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => [
                'temperature' => 0.1, // Baixa temperatura para maior precisão
                'responseMimeType' => 'application/json'
            ]
        ];

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . self::MODEL . ':generateContent?key=' . $this->apiKey;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        $jsonStr = $data['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
        
        // Limpeza básica se o modelo retornar markdown
        $jsonStr = trim(str_replace(['```json', '```'], '', $jsonStr));
        
        return json_decode($jsonStr, true) ?? [];
    }

    /**
     * Extração de Produtos (Preenchimento de Cadastro)
     */
    public function extractProductData(string $text): array {
        if (!$this->isActive()) return [];

        $prompt = "AJA COMO UM ESPECIALISTA EM E-COMMERCE.
        Sua tarefa é extrair os dados técnicos de um produto a partir de uma descrição ou mensagem e retornar um JSON válido.

        CAMPOS NECESSÁRIOS:
        {
          \"name\": \"Título otimizado (60 chars)\",
          \"sku\": \"Sugestão de SKU\",
          \"ean\": \"EAN-13 fictício ou real se houver\",
          \"ncm\": \"NCM (8 dígitos)\",
          \"price\": 0.0,
          \"price_wholesale\": 0.0,
          \"weight_kg\": 0.000,
          \"length_cm\": 0,
          \"width_cm\": 0,
          \"height_cm\": 0,
          \"description\": \"Descrição persuasiva\",
          \"brand\": \"Marca\",
          \"seo_title\": \"Título otimizado para SEO/Google (máx 70 chars)\",
          \"seo_description\": \"Meta description otimizada para SEO (máx 160 chars)\",
          \"video_url\": \"Link de vídeo do Youtube se houver, senão vazio\"
        }

        TEXTO DO PRODUTO:
        $text";

        $payload = [
            'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
            'generationConfig' => ['temperature' => 0.2, 'responseMimeType' => 'application/json']
        ];

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . self::MODEL . ':generateContent?key=' . $this->apiKey;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        $jsonStr = $data['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
        $jsonStr = trim(str_replace(['```json', '```'], '', $jsonStr));
        
        return json_decode($jsonStr, true) ?? [];
    }

    /**
     * Extração de Produtos por Imagem (Cadastro Inteligente via Foto)
     */
    public function extractProductDataFromImage(string $base64Data, string $mimeType, string $text = ''): array {
        if (!$this->isActive()) return [];

        $prompt = "AJA COMO UM ESPECIALISTA EM E-COMMERCE E CADASTRO DE PRODUTOS.
        Sua tarefa é analisar a imagem fornecida (e a descrição de texto opcional, se houver) do produto e extrair seus dados técnicos e cadastrais.
        Retorne APENAS um JSON válido.

        CAMPOS NECESSÁRIOS NO JSON:
        {
          \"name\": \"Título comercial do produto otimizado para vendas (máx 60 chars)\",
          \"sku\": \"Sugestão de SKU baseada na marca/modelo/atributos (ex: PLACA-ZERO-DELAY)\",
          \"ean\": \"EAN-13 real se estiver legível na foto, caso contrário gere um EAN-13 válido fictício compatível com o Brasil (geralmente começando com 789)\",
          \"ncm\": \"O código NCM (8 dígitos) correto ou o mais provável para a categoria do item\",
          \"price\": 0.0,
          \"price_wholesale\": 0.0,
          \"weight_kg\": 0.000,
          \"length_cm\": 0,
          \"width_cm\": 0,
          \"height_cm\": 0,
          \"description\": \"Descrição técnica e comercial altamente detalhada e persuasiva do item. Adicione quebras de linha com \\n se necessário.\",
          \"brand\": \"Marca do produto (se não souber, use genérico ou tente deduzir)\",
          \"seo_title\": \"Título otimizado para SEO/Google (máx 70 chars)\",
          \"seo_description\": \"Meta description otimizada para SEO (máx 160 chars)\",
          \"video_url\": \"\"
        }

        Recomendações extras baseadas na foto:
        - Estime o peso (weight_kg) e as dimensões (length_cm, width_cm, height_cm) da embalagem do produto de forma realista.
        - Se o usuário forneceu algum texto de contexto, incorpore os dados fornecidos por ele (ex: preços específicos ou marcas).

        " . (!empty($text) ? "Texto adicional do usuário para guiar o cadastro:\n" . $text : "");

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        [
                            'inlineData' => [
                                'mimeType' => $mimeType,
                                'data' => $base64Data
                            ]
                        ],
                        [
                            'text' => $prompt
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                'responseMimeType' => 'application/json'
            ]
        ];

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . self::MODEL . ':generateContent?key=' . $this->apiKey;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 35,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        $jsonStr = $data['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
        $jsonStr = trim(str_replace(['```json', '```'], '', $jsonStr));
        
        return json_decode($jsonStr, true) ?? [];
    }

    /**
     * Extração de Fornecedores
     */
    public function extractSupplierData(string $text): array {
        if (!$this->isActive()) return [];

        $prompt = "AJA COMO UM ESPECIALISTA EM LOGÍSTICA.
        Extraia os dados dos fornecedores em um ARRAY de objetos JSON.

        CAMPOS POR OBJETO:
        \"name\", \"contact_name\", \"phone\", \"email\", \"address\", \"city\", \"state\", \"zipcode\", \"notes\".

        TEXTO:
        $text";

        $payload = [
            'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
            'generationConfig' => ['temperature' => 0.1, 'responseMimeType' => 'application/json']
        ];

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . self::MODEL . ':generateContent?key=' . $this->apiKey;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        $jsonStr = $data['candidates'][0]['content']['parts'][0]['text'] ?? '[]';
        $jsonStr = trim(str_replace(['```json', '```'], '', $jsonStr));
        
        return json_decode($jsonStr, true) ?? [];
    }
}
