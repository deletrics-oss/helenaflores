# 💰 Manual de Venda e Instalação do Sistema (White Label)

Este documento explica como você pode vender e instalar este catálogo para novos clientes (tenants) de forma rápida e profissional.

## 1. Preparação dos Arquivos para o Cliente
Para cada novo cliente, você deve fornecer uma cópia limpa do sistema.

1.  **Copie todos os arquivos da pasta do projeto**, EXCETO:
    *   Arquivos `.git` ou `.rar/.zip` antigos.
    *   Pastas de backup.
    *   O arquivo `config.php` original (use o `config.sample.php` no lugar).
    *   Imagens em `assets/uploads/` (limpe tudo antes de enviar).

2.  **O que o cliente recebe:**
    *   A estrutura de pastas (`admin`, `includes`, `assets`, etc.).
    *   O arquivo `install_clean.sql` (Schema do banco de dados).
    *   O arquivo `config.sample.php`.

## 2. Passo a Passo de Instalação (5 Minutos)

### Passo 1: Criar o Banco de Dados
No painel de controle do servidor do cliente (ex: Hostinger cPanel):
1.  Crie um novo **Banco de Dados MySQL**.
2.  Crie um **Usuário do Banco** e anote a senha.
3.  Acesse o **phpMyAdmin**, selecione o banco novo e importe o arquivo `install_clean.sql`.

### Passo 2: Configurar os Arquivos
1.  Renomeie `config.sample.php` para `config.php`.
2.  Edite o `config.php` com os dados do banco que você criou no Passo 1.
3.  Ajuste a `BASE_URL`:
    *   Se for na raiz (ex: `cliente.com.br`), use `/`.
    *   Se for em pasta (ex: `seu-dominio.com.br/loja1`), use `/loja1`.

### Passo 3: Login e Configuração Inicial
1.  Acesse o painel admin: `sua-url.com.br/admin`.
2.  Use o login padrão:
    *   **Email:** `admin`
    *   **Senha:** `admin123`
3.  **IMPORTANTE:** Vá em "Equipe Admin" e mude a senha imediatamente.
4.  Vá em **Configurações/Site** para colocar o nome da loja do cliente e o WhatsApp dele.

---

## 🚀 O que este sistema já inclui:
Ao vender, você pode destacar estes diferenciais:
*   **Integração TikTok:** Exportação pronta para TikTok Shop.
*   **Multi-Atendimento:** Painel para gerenciar WhatsApp, Instagram e Facebook em um só lugar.
*   **B2B / Atacado:** Preços diferenciados para revendedores.
*   **SEO Avançado:** Pronto para busca no Google.
*   **Gestor de Banners:** Banner rotativo profissional.

*Dica: Você pode cobrar uma taxa de instalação e uma mensalidade pela manutenção/hospedagem.*
