<?php
/**
 * saas_installer.php 
 * v2.0 - Provisionador Automatizado SaaS Híbrido
 * Cria a pasta, o banco de dados e injeta as configurações.
 */

// PROTEÇÃO OBRIGATÓRIA - Mude em produção!
define('MASTER_KEY', 'catalogo_master_2025');

if (!isset($_GET['super_secret_key']) || $_GET['super_secret_key'] !== MASTER_KEY) {
    die("<h1>⛔ Acesso Negado</h1>");
}

$msg = "";
$baseDir = __DIR__;

// Configurações do MySQL Root para criar novos bancos
// Em produção, esses dados devem ficar em um config fora da public_html
$dbHost = 'localhost';
$dbUserRoot = 'root'; // Ajuste conforme seu servidor
$dbPassRoot = '';     // Ajuste conforme seu servidor

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientSlug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $_POST['slug']));
    $clientName = $_POST['client_name'];
    $adminEmail = filter_var($_POST['admin_email'], FILTER_SANITIZE_EMAIL);
    $adminPass = $_POST['admin_pass'];

    // Configs de Banco de Dados Root via form (opcional para maior segurança)
    if (isset($_POST['db_root_user']) && !empty($_POST['db_root_user']))
        $dbUserRoot = $_POST['db_root_user'];
    if (isset($_POST['db_root_pass']))
        $dbPassRoot = $_POST['db_root_pass']; // pode ser vazia no localhost

    $targetDir = $baseDir . '/' . $clientSlug;
    $newDbName = 'tenant_' . $clientSlug; // Ex: tenant_joaoarcade

    if (is_dir($targetDir)) {
        $msg = "<div class='alert error'>❌ A pasta '$clientSlug' já existe! Escolha outro slug.</div>";
    } else {
        try {
            // ==========================================
            // 1. CRIAR PASTA E COPIAR ARQUIVOS
            // ==========================================
            recurseCopy($baseDir, $targetDir);

            // ==========================================
            // 2. CRIAR O BANCO DE DADOS
            // ==========================================
            $pdoRoot = new PDO("mysql:host=$dbHost;charset=utf8mb4", $dbUserRoot, $dbPassRoot, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            // Cria o banco de dados para o cliente
            $pdoRoot->exec("CREATE DATABASE IF NOT EXISTS `$newDbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            // Conecta no novo banco recém criado
            $pdoTenant = new PDO("mysql:host=$dbHost;dbname=$newDbName;charset=utf8mb4", $dbUserRoot, $dbPassRoot, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            // ==========================================
            // 3. IMPORTAR DADOS (install_clean.sql)
            // ==========================================
            $sqlFile = $baseDir . '/install_clean.sql';
            if (file_exists($sqlFile)) {
                $sqlContent = file_get_contents($sqlFile);
                if (!empty($sqlContent)) {
                    $pdoTenant->exec($sqlContent);
                }
            } else {
                throw new Exception("Arquivo install_clean.sql não encontrado!");
            }

            // ==========================================
            // 4. CONFIGURAR DADOS DO CLIENTE NO BANCO
            // ==========================================
            // Atualiza Nome do Site e Email (tabela settings)
            $stmt = $pdoTenant->prepare("UPDATE settings SET site_name = ?, admin_email = ? WHERE id = 1");
            $stmt->execute([$clientName, $adminEmail]);

            // Atualiza ou Insere o Usuário Admin
            $hashedPass = password_hash($adminPass, PASSWORD_DEFAULT);
            $stmtAdmin = $pdoTenant->prepare("SELECT id FROM users WHERE email = 'admin' OR role = 'admin' LIMIT 1");
            $stmtAdmin->execute();
            if ($stmtAdmin->rowCount() > 0) {
                // Atualiza o existente
                $updateAdmin = $pdoTenant->prepare("UPDATE users SET email = ?, password = ?, name = ? WHERE id = ?");
                $updateAdmin->execute([$adminEmail, $hashedPass, 'Administrador', $stmtAdmin->fetchColumn()]);
            } else {
                // Insere novo se não existir
                $insAdmin = $pdoTenant->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'admin')");
                $insAdmin->execute(['Administrador', $adminEmail, '00000000000', $hashedPass]);
            }

            // ==========================================
            // 5. INJETAR CONFIGURAÇÕES NO ARQUIVO PHP
            // ==========================================
            $sampleConfig = $targetDir . '/config.sample.php';
            $finalConfig = $targetDir . '/config.php';

            if (file_exists($sampleConfig)) {
                $configContent = file_get_contents($sampleConfig);

                // Determina a Base URL correta dinamicamente
                $folderPath = dirname($_SERVER['SCRIPT_NAME']);
                if ($folderPath === '/' || $folderPath === '\\')
                    $folderPath = '';
                $newBaseUrl = $folderPath . '/' . $clientSlug . '/';

                $replacements = [
                    '{{DB_HOST}}' => $dbHost,
                    '{{DB_USER}}' => $dbUserRoot, // Ideal em prod: criar user especifico por bd
                    '{{DB_PASS}}' => $dbPassRoot, // Ideal em prod: gerar senha unica
                    '{{DB_NAME}}' => $newDbName,
                    '{{BASE_URL}}' => $newBaseUrl,
                    '{{SITE_NAME}}' => $clientName,
                    '{{WHATSAPP_ADMIN}}' => '' // Cliente preenche depois
                ];

                $configContent = str_replace(array_keys($replacements), array_values($replacements), $configContent);
                file_put_contents($finalConfig, $configContent);
                unlink($sampleConfig); // Remove o sample da nova loja
            }

            // ==========================================
            // 6. LIMPEZA DA CÓPIA (Sanitização)
            // ==========================================
            array_map('unlink', glob("$targetDir/assets/uploads/*.*"));
            if (is_dir("$targetDir/fabrica")) {
                rename("$targetDir/fabrica", "$targetDir/fabrica_disabled");
            }
            if (file_exists("$targetDir/saas_installer.php"))
                unlink("$targetDir/saas_installer.php");
            if (file_exists("$targetDir/install_clean.sql"))
                unlink("$targetDir/install_clean.sql");

            // Sucesso!
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $storeLink = $protocol . "://" . $_SERVER['HTTP_HOST'] . $newBaseUrl;

            $msg = "<div class='alert success'>
                <h2>✅ Instalação Concluída em Segundos!</h2>
                <p>A loja <strong>$clientName</strong> está pronta para uso.</p>
                <div class='credentials-box'>
                    <p><strong>🔗 Link da Loja:</strong> <br><a href='$storeLink' target='_blank'>$storeLink</a></p>
                    <p><strong>👤 Painel Admin:</strong> <br><a href='{$storeLink}admin' target='_blank'>{$storeLink}admin</a></p>
                    <hr style='border-color: #444; margin:15px 0;'>
                    <p><strong>Email Admin:</strong> $adminEmail</p>
                    <p><strong>Senha:</strong> $adminPass</p>
                    <p><strong>Banco Criado:</strong> $newDbName</p>
                </div>
            </div>";

        } catch (Exception $e) {
            // Em caso de erro tenta limpar a pasta criada
            if (is_dir($targetDir)) { /* deleteDir($targetDir) */
            }
            $msg = "<div class='alert error'>❌ Erro na Instalação:<br>" . $e->getMessage() . "</div>";
        }
    }
}

// Cópia Recursiva Base
function recurseCopy($src, $dst)
{
    $dir = opendir($src);
    @mkdir($dst);
    while (false !== ($file = readdir($dir))) {
        // Ignora arquivos do sistema, repositórios e a pasta de destino
        $ignores = ['.', '..', 'saas_installer.php', '.git', 'backup_full_v5.zip', basename($dst)];
        if (!in_array($file, $ignores)) {
            if (is_dir($src . '/' . $file)) {
                recurseCopy($src . '/' . $file, $dst . '/' . $file);
            } else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚡ Instalador SaaS Automatizado</title>
    <style>
        body {
            background: #0a0a0a;
            color: #f0f0f0;
            font-family: 'Segoe UI', system-ui, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }

        .container {
            background: #1a1a1a;
            padding: 30px;
            border-radius: 12px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.8);
            border: 1px solid #333;
        }

        h1 {
            text-align: center;
            color: #00e676;
            margin-top: 0;
            font-size: 1.8rem;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            color: #bbb;
            font-weight: 500;
            font-size: 0.9rem;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            background: #222;
            border: 1px solid #444;
            color: #fff;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #00e676;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        button {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #00e676, #00c853);
            border: none;
            font-weight: bold;
            cursor: pointer;
            border-radius: 6px;
            color: #000;
            font-size: 1.1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: transform 0.1s, box-shadow 0.3s;
            margin-top: 10px;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 230, 118, 0.4);
        }

        button:active {
            transform: translateY(0);
        }

        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }

        .success {
            background: rgba(0, 230, 118, 0.1);
            border-color: #00e676;
            color: #00e676;
        }

        .error {
            background: rgba(255, 61, 0, 0.1);
            border-color: #ff3d00;
            color: #ff3d00;
        }

        .brand-text {
            color: #fff;
            font-size: 0.8rem;
            text-align: center;
            margin-top: 20px;
            opacity: 0.5;
        }

        .credentials-box {
            background: #000;
            padding: 15px;
            border-radius: 6px;
            margin-top: 15px;
            font-family: monospace;
            border: 1px dashed #444;
        }

        .credentials-box a {
            color: #5cff9a;
            text-decoration: none;
        }

        .credentials-box a:hover {
            text-decoration: underline;
        }

        details {
            background: #222;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            border: 1px solid #333;
        }

        summary {
            cursor: pointer;
            color: #888;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>🚀 Saas Deployment Tool</h1>

        <?php echo $msg; ?>

        <form method="POST" id="installForm">
            <div class="form-group">
                <label>Nome Fantasia do Cliente</label>
                <input type="text" name="client_name" required placeholder="Ex: Arcade 1000" autocomplete="off">
            </div>

            <div class="form-group">
                <label>Subdiretório / URL Slug (sem espaços)</label>
                <input type="text" name="slug" required placeholder="Ex: arcade1000" pattern="[a-zA-Z0-9]+"
                    title="Apenas letras e números">
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label>E-mail do Administrador</label>
                    <input type="email" name="admin_email" required placeholder="admin@loja.com">
                </div>
                <div class="form-group">
                    <label>Senha Provisória</label>
                    <input type="text" name="admin_pass" required value="mudar123">
                </div>
            </div>

            <details>
                <summary>⚙️ Configurações de Banco (Opcional)</summary>
                <p style="color:#777; margin-top:10px;">Preencha se a senha root do MySQL for diferente do padrão.</p>
                <div class="form-group">
                    <label>Usuário MySQL Root</label>
                    <input type="text" name="db_root_user" value="root">
                </div>
                <div class="form-group">
                    <label>Senha MySQL Root</label>
                    <input type="password" name="db_root_pass" placeholder="Vazio se localhost">
                </div>
            </details>

            <button type="submit" onclick="this.innerHTML='PROVISIONANDO... ⏳';">CRIAR LOJA AGORA ⚡</button>
        </form>
        <div class="brand-text">Sistema Híbrido Multi-Tenant</div>
    </div>
</body>

</html>