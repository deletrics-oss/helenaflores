<?php
ini_set('display_errors', 0); // Desabilitar erros para produção
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Política de Privacidade | Fight Arcade</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css?v=2.2">
    <!-- Adicionar favicon se existir -->
    <link rel="icon" href="<?php echo BASE_URL; ?>/assets/favicon.ico" type="image/x-icon">
</head>

<body>

    <?php
    // Incluir header da raiz (apenas o conteúdo do header, não <head>)
    require_once __DIR__ . '/includes/header_public.php';
    ?>

    <div class="container" style="padding: 4rem 1rem; max-width: 900px; margin: 0 auto;">
        <h1 style="text-align:center; font-size: 2.5rem; margin-bottom: 2rem; color: #4cc9f0;">POLÍTICA DE PRIVACIDADE
        </h1>

        <div style="color: #ccc; line-height: 1.8;">
            <p>A <strong>Fight Arcade</strong> preza pela segurança e privacidade de seus clientes. Esta política
                explica
                como coletamos, usamos e protegemos seus dados, em conformidade com a LGPD (Lei Geral de Proteção de
                Dados)
                e as diretrizes de desenvolvedores do Facebook/Meta.</p>

            <h3 style="color: #fff; margin-top: 2rem;">1. Coleta de Dados</h3>
            <p>Coletamos apenas as informações estritamente necessárias para processar seu pedido e entregar seus
                produtos:
            </p>
            <ul>
                <li><strong>Dados de Identificação:</strong> Nome completo, CPF e data de nascimento (para emissão de
                    Nota
                    Fiscal).</li>
                <li><strong>Dados de Contato:</strong> Endereço de entrega, e-mail e telefone (para atualizações sobre o
                    pedido).</li>
                <li><strong>Dados de Navegação:</strong> Informações de dispositivo e IP para segurança e prevenção de
                    fraudes.</li>
            </ul>

            <h3 style="color: #fff; margin-top: 2rem;">2. Login Social (Facebook/Google)</h3>
            <p>Ao utilizar o login social (Facebook ou Google), coletamos apenas seu nome público, e-mail e foto de
                perfil
                para criar sua conta de forma rápida. Não temos acesso à sua senha dessas redes sociais e não publicamos
                nada em seu nome sem sua permissão expressa.</p>

            <h3 style="color: #fff; margin-top: 2rem;">3. Segurança no Pagamento</h3>
            <p>A Fight Arcade <strong>NÃO</strong> armazena dados sensíveis de cartão de crédito. Todo o processamento
                de
                pagamento é realizado por gateways seguros e criptografados (Mercado Pago, PagSeguro, etc.), que apenas
                nos
                informam se a transação foi aprovada ou recusada.</p>

            <h3 style="color: #fff; margin-top: 2rem;">4. Uso das Informações</h3>
            <p>Seus dados são utilizados exclusivamente para:</p>
            <ul>
                <li>Processamento e envio de pedidos.</li>
                <li>Comunicação sobre status de entrega.</li>
                <li>Atendimento ao cliente e suporte pós-venda.</li>
                <li>Melhoria da experiência de navegação no site.</li>
            </ul>
            <p>Jamais vendemos ou cedemos seus dados para terceiros para fins de marketing não autorizado.</p>

            <h3 style="color: #fff; margin-top: 2rem;">5. Cookies</h3>
            <p>Utilizamos cookies apenas para melhorar sua experiência de navegação, como manter itens no seu carrinho,
                lembrar seu login e personalizar ofertas.</p>

            <h3 style="color: #fff; margin-top: 2rem;">6. Exclusão de Dados (Data Deletion)</h3>
            <p>Você tem o direito de solicitar a exclusão completa dos seus dados pessoais de nossa base, conforme
                previsto
                na LGPD e nas regras de Plataforma do Facebook.</p>
            <p>Para solicitar a exclusão:</p>
            <ol>
                <li>Entre em contato conosco pelo e-mail: <strong>contato@fightarcade.com.br</strong> ou pelo WhatsApp
                    disponível no site.</li>
                <li>Informe o e-mail cadastrado que deseja excluir.</li>
                <li>Nossa equipe processará a exclusão em até 72 horas úteis, mantendo apenas os dados obrigatórios por
                    lei
                    para fins fiscais (como notas fiscais emitidas).</li>
            </ol>
            <p>Se você utilizou o Login com Facebook, você também pode remover o acesso do aplicativo através das
                configurações de "Apps e Sites" na sua conta do Facebook.</p>

            <hr style="border-color:#333; margin: 3rem 0;">
            <p style="font-size: 0.9rem; text-align: center;">Última atualização:
                <?php echo date('d/m/Y'); ?>
            </p>

        </div>
    </div>

    <?php
    require_once __DIR__ . '/includes/footer_public.php';
    ?>