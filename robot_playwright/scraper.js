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

// 118 exact filenames matching catalog order
const EXACT_NAMES = [
  "001-buque-com-12-colombianas.jpg", "002-buque-com-rosas-colombianas.jpg", "003-buque-de-rosas-pink-colombiana.jpg", 
  "004-cesta-com-chambinho-do-amor.jpg", "005-cesta-com-rosa-e-urso.jpg", "006-kit-dia-dos-namorados.jpg", 
  "007-buque-com-15-rosas.jpg", "008-buque-com-15-rosas-amarelas.jpg", "009-buque-com-rosas-rose.jpg", 
  "010-buque-lily.jpg", "011-buque-de-mix-de-flores.jpg", "012-buque-rosa.jpg", "013-cesta-de-cafe.jpg", 
  "014-cesta-de-cafe-com-rosa.jpg", "015-cesta-de-cafe-premium.jpg", "016-arranjo-de-rosas-e-lirio.jpg", 
  "017-arranjo-com-rosas-vermelhas.jpg", "018-arranjo-com-3-rosas-colombianas.jpg", "019-ferreiro-rocher-50g.jpg", 
  "020-ferreiro-rocher-100g.jpg", "021-ferreiro-rocher-collection-77g.jpg", "022-buque-de-girassol.jpg", 
  "023-buque-de-girassol-e-astromelias.jpg", "024-buque-com-rosas-e-girassois.jpg", "025-bujudinho-de-astromelias-coloridas.jpg", 
  "026-arranjo-de-flores.jpg", "027-arranjo-de-flores-finas.jpg", "028-emoji-pelucia.jpg", "029-coracao-de-pelucia.jpg", 
  "030-urso-p.jpg", "031-orquidea.jpg", "032-arranjo-com-3-orquideas-brancas.jpg", "033-orquidea-pink.jpg", 
  "034-begonia.jpg", "035-lirio-rosa.jpg", "036-lirio-amarelo.jpg", "037-mini-orquidea-branca.jpg", 
  "038-mini-orquidea-pink.jpg", "039-arranjo-com-mini-orquidea-brancas.jpg", "040-violeta-na-cesta.jpg", 
  "041-kit-maternidade-classico.jpg", "042-kit-maternidade-premium.jpg", "043-arranjo-com-chocolate.jpg", 
  "044-rosa-e-ferrero-50g.jpg", "045-girassol-solidario-com-ferreiro-collection.jpg", "046-buque-de-tulipas-rosa.jpg", 
  "047-buque-com-tulipas-e-rosas-inglesas.jpg", "048-poinssetia.jpg", "049-kit-natalino.jpg", "050-kit-natal.jpg", 
  "051-cesta-de-cafe-1.jpg", "052-cesta-de-cafe-com-girassol.jpg", "053-vinho-reservado-carmenere.jpg", 
  "054-kit-maternidade.jpg", "055-kit-2-rosas-e-mini-ferreiro-rocher.jpg", "056-buque-com-24-rosas-nacionais.jpg", 
  "057-box-com-girassol-e-chandon.jpg", "058-buque-com-rosa-e-astromelias.jpg", "059-buque-e-ferreiro-rocher.jpg", 
  "060-cesta-com-rosas-e-chandon.jpg", "061-buque-com-3-lirios.jpg", "062-cesta-com-kalandiva.jpg", 
  "063-box-de-flores.jpg", "064-buque-de-rosas-com-astromelias.jpg", "065-buque-de-gerberas-colorida.jpg", 
  "066-buque-de-flores-silvestres.jpg", "067-buque-com-20-rosas-colombianas.jpg", "068-buque-amor-vibrante.jpg", 
  "069-buque-com-24-rosas-importadas.jpg", "070-nutella-p.jpg", "071-buque-com-lirios-coloridos.jpg", 
  "072-espumante-rose-monte-pascoal.jpg", "073-buque-flores-silvestre.jpg", "074-arranjo-grande-com-astromelias-rosas.jpg", 
  "075-buque-de-rosas-manipuladas.jpg", "076-urso-grande.jpg", "077-arranjo-rosa.jpg", "078-box-com-12-rosas-nacionais.jpg", 
  "079-vaso-de-vidro.jpg", "080-cesta.jpg", "081-buque-gerberas-e-rosas-brancas.jpg", "082-box-mae.jpg", 
  "083-cesta-com-lirio-e-espumante.jpg", "084-orquidea-phale-media.jpg", "085-arranjo-com-rosas.jpg", 
  "086-buque-angelica.jpg", "087-buque-com-40-rosas-colombianas.jpg", "088-buque-encanto-inesquecivel.jpg", 
  "089-buque.jpg", "090-arranjo-statis.jpg", "091-buque-com-24-rosas-colombianas.jpg", "092-arranjo-com-2-rosas-colombiana.jpg", 
  "093-arranjo-com-3-rosas-brancas.jpg", "094-arranjo-pink-de-rosas-e-astromelia.jpg", "095-arranjo-com-3-orquideas-pink.jpg", 
  "096-buque-jasmine.jpg", "097-arranjo-de-rosa-colombiana.jpg", "098-kit-amor-perfeito.jpg", "099-buque-primeira.jpg", 
  "100-buque-de-rosa-branca-nacional.jpg", "101-arranjo-no-vaso-de-vidro.jpg", "102-arranjo.jpg", 
  "103-cesta-com-arranjo-e-chocolate.jpg", "104-buque-com-12-rosas-e-gypsophila.jpg", "105-arranjo-de-rosas.jpg", 
  "106-buque-de-flores-finas.jpg", "107-buque-com-cravinas-coloridas.jpg", "108-buque-com-10-rosas-nacional.jpg", 
  "109-arranjo-de-rosas-e-lirio-1.jpg", "110-bujudinho-de-rosa-e-girasol.jpg", "111-arranjo-rose.jpg", 
  "112-buque-com-girassois.jpg", "113-buque-de-60-rosas-colombianas.jpg", "114-orquidea-branca-cascata.jpg", 
  "115-buque-com-18-rosas-nacionais.jpg", "116-ferreiro-rocher-150g.jpg", "117-arranjo-branco-com-flores-finas.jpg", 
  "118-arranjo-de-rosas-e-astromelia-branca.jpg"
];

(async () => {
    try {
        console.log('\n==========================================================');
        console.log('   🌸 HELENA FLORES — ROBÔ AUTOMÁTICO ESTILO MAKERLIST');
        console.log('   (Extração Foto a Foto 1-por-1 do WhatsApp Web)');
        console.log('==========================================================\n');

        console.log('🚀 Abrindo o navegador do WhatsApp Web...');
        const context = await chromium.launchPersistentContext(USER_DATA_DIR, {
            headless: false,
            viewport: { width: 1366, height: 768 },
            args: ['--disable-blink-features=AutomationControlled']
        });

        const page = await context.newPage();
        await page.goto('https://web.whatsapp.com');

        console.log('⏳ Por favor, escaneie o QR Code (se necessário) e ABRIR O CATÁLOGO DO CLIENTE.');
        console.log('👉 O robô vai aguardar até você abrir o catálogo...\n');

        // Wait until catalog panel is detected
        let catalogReady = false;
        for (let attempts = 0; attempts < 60; attempts++) {
            const hasCatalog = await page.evaluate(() => {
                const bodyText = document.body.innerText || '';
                return bodyText.includes('Catálogo') || bodyText.includes('Detalhes') || document.querySelectorAll('img[src*="blob"]').length > 0;
            });

            if (hasCatalog) {
                catalogReady = true;
                console.log('✅ Catálogo detectado na tela! Iniciando captura foto a foto...');
                break;
            }
            await page.waitForTimeout(2000);
        }

        if (!catalogReady) {
            console.log('⚠️ Tempo esgotado aguardando o catálogo. O robô irá tentar extrair as imagens visíveis...');
        }

        // Scroll to load list items
        await page.evaluate(async () => {
            const scrollable = Array.from(document.querySelectorAll('div')).find(el => el.scrollHeight > 500);
            if (scrollable) {
                for (let i = 0; i < 30; i++) {
                    scrollable.scrollTop += 300;
                    await new Promise(r => setTimeout(r, 150));
                }
                scrollable.scrollTop = 0;
            }
        });

        await page.waitForTimeout(1000);

        // Extract all image blobs directly from browser DOM
        const imagesInfo = await page.evaluate(async () => {
            const imgs = Array.from(document.querySelectorAll('img'));
            const results = [];

            for (const img of imgs) {
                const src = img.src || '';
                if (src.startsWith('blob:') || (src.startsWith('http') && src.includes('whatsapp'))) {
                    let title = '';
                    let parent = img.closest('div[role="listitem"]') || img.closest('div[role="row"]') || img.parentElement?.parentElement;
                    if (parent) {
                        title = parent.innerText.split('\n')[0] || '';
                    }

                    results.push({ src, title });
                }
            }
            return results;
        });

        console.log(`🔎 Encontradas ${imagesInfo.length} fotos de produtos na tela.`);

        let savedCount = 0;

        for (let i = 0; i < imagesInfo.length; i++) {
            try {
                const imgItem = imagesInfo[i];
                const targetFilename = EXACT_NAMES[i] || `produto-${String(i+1).padStart(3, '0')}.jpg`;
                const savePath = path.join(UPLOADS_DIR, targetFilename);

                const base64Data = await page.evaluate(async (url) => {
                    try {
                        const res = await fetch(url);
                        const blob = await res.blob();
                        return new Promise((resolve) => {
                            const reader = new FileReader();
                            reader.onloadend = () => resolve(reader.result.split(',')[1]);
                            reader.readAsDataURL(blob);
                        });
                    } catch (e) {
                        return null;
                    }
                }, imgItem.src);

                if (base64Data) {
                    fs.writeFileSync(savePath, Buffer.from(base64Data, 'base64'));
                    savedCount++;
                    console.log(`📸 [${savedCount}/${imagesInfo.length}] Foto salva com sucesso: ${targetFilename}`);
                }
            } catch (err) {
                console.log(`⚠️ Erro ao salvar imagem ${i+1}: ${err.message}`);
            }
        }

        console.log(`\n==========================================================`);
        console.log(`🎉 SUCESSO! ${savedCount} fotos foram copiadas e salvas em:`);
        console.log(`👉 ${UPLOADS_DIR}`);
        console.log(`==========================================================\n`);

        await page.waitForTimeout(3000);
        await context.close();
        process.exit(0);
    } catch (mainErr) {
        console.error('\n❌ ERRO NA EXECUÇÃO DO ROBÔ:');
        console.error(mainErr);
        process.exit(1);
    }
})();
