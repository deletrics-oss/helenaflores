<?php
// saas_installer.php
// v1.0 - Clonador de Lojas para SaaS (Modelo Híbrido)

// PROTEÇÃO BÁSICA
if (!isset($_GET['super_secret_key']) || $_GET['super_secret_key'] !== 'fightarcade_master_2025') {
    die("<h1>⛔ Acesso Negado</h1>");
}

$msg = "";
$baseDir = __DIR__;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientSlug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $_POST['slug']));
    $clientName = $_POST['client_name'];
    $adminEmail = $_POST['admin_email'];
    $adminPass = $_POST['admin_pass'];

    $targetDir = $baseDir . '/' . $clientSlug;

    if (is_dir($targetDir)) {
        $msg = "<div style='color:red'>❌ A pasta '$clientSlug' já existe! Escolha outra.</div>";
    } else {
        // 1. COPIAR ARQUIVOS
        recurseCopy($baseDir, $targetDir);

        // 2. CONFIGURAR DADOS (Aqui, em produção, criaria banco novo. 
        // No MVP, vamos criar um arquivo de config específico ou instruir configuração manual)
        // Para simplificar: Vamos criar um 'setup_lock' e um 'site_settings.json' limpo

        // Resetar configurações da nova loja
        $newSettings = [
            "phone" => "(11) 99999-9999",
            "whatsapp" => "5511999999999",
            "email" => $adminEmail,
            "footer_text" => "Loja criada com FightArcade SaaS"
        ];
        file_put_contents($targetDir . '/includes/site_settings.json', json_encode($newSettings, JSON_PRETTY_PRINT));

        // Criar usuário admin inicial (Isso exige conexão com banco, vamos fazer um script de 'first_run.php' na pasta nova?)
        // Melhor: Vamos criar o arquivo config.php da nova loja apontando para o MESMO banco mas com prefixo? 
        // OU instruir o usuário.
        // O PROCESSO MAIS SEGURO PRA AGORA É: Clona os arquivos. O banco, por enquanto, é manual ou compartilhado.

        // Limpar uploads da nova loja
        array_map('unlink', glob("$targetDir/assets/uploads/*.*"));

        // Remover pasta fabrica da nova loja (GARANTIA DE SAAS LIMPO)
        if (is_dir("$targetDir/fabrica")) {
            // deleteDirectory("$targetDir/fabrica"); // Função complexa, vamos renomear pra garantir
            rename("$targetDir/fabrica", "$targetDir/fabrica_disabled");
        }

        $link = "http://" . $_SERVER['HTTP_HOST'] . "/FightArcadeCatalogo/" . $clientSlug; // Ajustar conforme ambiente real

        $msg = "<div style='color:green; border:2px solid green; padding:20px;'>
            <h2>✅ Loja '$clientName' Criada!</h2>
            <p>1. Pasta: <code>/$clientSlug</code></p>
            <p>2. Link: <a href='$link' target='_blank'>$link</a></p>
            <p><strong>Próximo Passo:</strong> Configure o arquivo <code>config.php</code> dentro da nova pasta para usar um banco de dados novo.</p>
        </div>";
    }
}

// FUNÇÃO DE CÓPIA RECURSIVA (IGNORANDO ARQUIVOS DE SISTEMA)
function recurseCopy($src, $dst)
{
    $dir = opendir($src);
    @mkdir($dst);
    while (false !== ($file = readdir($dir))) {
        if (($file != '.') && ($file != '..') && ($file != 'saas_installer.php') && ($file != '.git') && ($file != 'backup_full_v5.zip') && ($file != $dst)) {
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
    <title>Instalador SaaS - Fight Arcade</title>
    <style>
        body {
            background: #111;
            color: #fff;
            font-family: sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .box {
            background: #222;
            padding: 2rem;
            border-radius: 10px;
            width: 400px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
        }

        input {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            background: #333;
            border: 1px solid #444;
            color: #fff;
            border-radius: 5px;
            box-sizing: border-box;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #00e676;
            border: none;
            font-weight: bold;
            cursor: pointer;
            border-radius: 5px;
            color: #000;
            font-size: 1.1rem;
        }

        button:hover {
            background: #00c853;
        }

        h1 {
            text-align: center;
            color: #00e676;
            margin-top: 0;
        }
    </style>
</head>

<body>
    <div class="box">
        <h1>🚀 Criar Nova Loja v1.0</h1>
        <?php echo $msg; ?>
        <form method="POST">
            <label>Nome do Cliente</label>
            <input type="text" name="client_name" required placeholder="Ex: Arcade do João">

            <label>Slug (Nome da Pasta)</label>
            <input type="text" name="slug" required placeholder="Ex: arcadejoao">

            <label>Email do Admin</label>
            <input type="email" name="admin_email" required placeholder="joao@email.com">

            <label>Senha Provisória</label>
            <input type="text" name="admin_pass" required value="mudar123">

            <button type="submit">CLONAR LOJA AGORA ⚡</button>
        </form>
    </div>
</body>

</html>