import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const UPLOADS_DIR = path.resolve(__dirname, '../assets/uploads');
const USER_DATA_DIR = path.resolve(__dirname, 'whatsapp_session');

if (!fs.existsSync(UPLOADS_DIR)) {
    fs.mkdirSync(UPLOADS_DIR, { recursive: true });
}

(async () => {
    console.log('\n==========================================================');
    console.log('   🌸 HELENA FLORES — ROBÔ AUTOMÁTICO DE CLONAGEM (PLAYWRIGHT)');
    console.log('==========================================================\n');

    console.log('🚀 Abrindo o WhatsApp Web com navegador automático...');
    const context = await chromium.launchPersistentContext(USER_DATA_DIR, {
        headless: false,
        viewport: { width: 1280, height: 800 },
        args: ['--disable-blink-features=AutomationControlled']
    });

    const page = await context.newPage();
    await page.goto('https://web.whatsapp.com');

    console.log('\n📌 INSTRUÇÃO:');
    console.log('1. Se o seu WhatsApp Web solicitar QR Code, escaneie no celular.');
    console.log('2. Abra o Catálogo da Helena Flores no WhatsApp.');
    console.log('3. Pressione ENTER neste terminal quando o catálogo estiver aberto na tela...\n');

    process.stdin.resume();
    await new Promise(resolve => process.stdin.once('data', resolve));

    console.log('\n🤖 Robô iniciando extração automática das fotos e produtos...');

    // Auto scroll para carregar todos os itens
    await page.evaluate(async () => {
        const panels = Array.from(document.querySelectorAll('div')).filter(el => {
            const style = getComputedStyle(el);
            return (style.overflowY === 'auto' || style.overflowY === 'scroll') && el.scrollHeight > 300;
        });
        const targetPanel = panels[0] || document.scrollingElement;
        
        for (let i = 0; i < 30; i++) {
            targetPanel.scrollTop = targetPanel.scrollHeight;
            await new Promise(r => setTimeout(r, 300));
        }
    });

    // Coleta todos os itens do catálogo
    const items = await page.$$('div[role="listitem"], div[tabindex="-1"]');
    console.log(`🌸 Encontrados ${items.length} itens no catálogo.`);

    let successCount = 0;

    for (let i = 0; i < items.length; i++) {
        try {
            const item = items[i];
            await item.click();
            await page.waitForTimeout(600);

            // Procura a imagem em alta resolução
            const imgHandle = await page.$('div[role="dialog"] img, div[tabindex="-1"] img');
            if (imgHandle) {
                const src = await imgHandle.getAttribute('src');
                const titleEl = await item.$('span[dir="auto"], h2');
                const titleText = titleEl ? (await titleEl.textContent()).trim() : `produto_${i+1}`;
                
                // Formata nome do arquivo
                const numStr = String(i + 1).padStart(3, '0');
                const cleanSlug = titleText.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
                const fileName = `${numStr}-${cleanSlug}.jpg`;
                const filePath = path.join(UPLOADS_DIR, fileName);

                if (src && (src.startsWith('blob:') || src.startsWith('http'))) {
                    // Baixa a imagem via buffer no navegador
                    const imageBuffer = await page.evaluate(async (imgSrc) => {
                        const res = await fetch(imgSrc);
                        const blob = await res.blob();
                        const reader = new FileReader();
                        return new Promise((resolve) => {
                            reader.onloadend = () => resolve(reader.result.split(',')[1]);
                            reader.readAsDataURL(blob);
                        });
                    }, src);

                    if (imageBuffer) {
                        fs.writeFileSync(filePath, Buffer.from(imageBuffer, 'base64'));
                        successCount++;
                        console.log(`✅ [${successCount}/${items.length}] Salvo com sucesso: ${fileName}`);
                    }
                }
            }

            // Fechar modal se aberto
            const closeBtn = await page.$('div[role="dialog"] button, span[data-icon="x"]');
            if (closeBtn) {
                await closeBtn.click();
                await page.waitForTimeout(300);
            }
        } catch (e) {
            console.log(`⚠️ Erro ao capturar item ${i + 1}: ${e.message}`);
        }
    }

    console.log(`\n🎉 SUCESSO! ${successCount} fotos foram extraídas e salvas na pasta:`);
    console.log(`👉 ${UPLOADS_DIR}\n`);

    await context.close();
    process.exit(0);
})();
