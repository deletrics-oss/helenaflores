/**
 * EXTRATOR DE IMAGENS DO CATÁLOGO - Helena Flores
 * -------------------------------------------------
 * COMO USAR:
 * 1. Abra o catálogo da Helena Flores no WhatsApp Web (web.whatsapp.com).
 * 2. Abra o Console do navegador (F12 > aba "Console").
 * 3. Cole este script inteiro e aperte Enter.
 * 4. Aguarde: ele vai rolar a lista automaticamente até carregar TODOS os
 *    produtos, baixar cada imagem e juntar tudo em um arquivo .zip.
 * 5. No final ele mostra uma tabela no console comparando a ordem das
 *    imagens encontradas com a ordem esperada (do seu JSON). CONFIRA essa
 *    tabela antes de usar as imagens - se a quantidade não bater, alguma
 *    coisa não carregou e você deve rolar manualmente até o fim e rodar de novo.
 *
 * O zip final já vem com os nomes de arquivo EXATAMENTE iguais aos que
 * estão no seed_products_with_images.json, então depois de extrair o zip
 * dentro de:
 *   C:\Users\ILHA\Documents\GitHub\helenaflores\_catalogo referencia\assets\uploads
 * as imagens já batem com o campo "image" de cada produto no JSON.
 */

(async function () {
  // ORDEM ESPERADA - EXATAMENTE a mesma ordem/quantidade do seu JSON de produtos.
  // Se a ordem visual do catálogo no WhatsApp for diferente da ordem do JSON,
  // os nomes vão ficar trocados - nesse caso me avise que eu gero a versão
  // que casa por título em vez de por ordem.
  const ORDER_NAMES = ["001-buque-com-12-colombianas.jpg", "002-buque-com-rosas-colombianas.jpg", "003-buque-de-rosas-pink-colombiana.jpg", "004-cesta-com-chambinho-do-amor.jpg", "005-cesta-com-rosa-e-urso.jpg", "006-kit-dia-dos-namorados.jpg", "007-buque-com-15-rosas.jpg", "008-buque-com-15-rosas-amarelas.jpg", "009-buque-com-rosas-rose.jpg", "010-buque-lily.jpg", "011-buque-de-mix-de-flores.jpg", "012-buque-rosa.jpg", "013-cesta-de-cafe.jpg", "014-cesta-de-cafe-com-rosa.jpg", "015-cesta-de-cafe-premium.jpg", "016-arranjo-de-rosas-e-lirio.jpg", "017-arranjo-com-rosas-vermelhas.jpg", "018-arranjo-com-3-rosas-colombianas.jpg", "019-ferreiro-rocher-50g.jpg", "020-ferreiro-rocher-100g.jpg", "021-ferreiro-rocher-collection-77g.jpg", "022-buque-de-girassol.jpg", "023-buque-de-girassol-e-astromelias.jpg", "024-buque-com-rosas-e-girassois.jpg", "025-bujudinho-de-astromelias-coloridas.jpg", "026-arranjo-de-flores.jpg", "027-arranjo-de-flores-finas.jpg", "028-emoji-pelucia.jpg", "029-coracao-de-pelucia.jpg", "030-urso-p.jpg", "031-orquidea.jpg", "032-arranjo-com-3-orquideas-brancas.jpg", "033-orquidea-pink.jpg", "034-begonia.jpg", "035-lirio-rosa.jpg", "036-lirio-amarelo.jpg", "037-mini-orquidea-branca.jpg", "038-mini-orquidea-pink.jpg", "039-arranjo-com-mini-orquidea-brancas.jpg", "040-violeta-na-cesta.jpg", "041-kit-maternidade-classico.jpg", "042-kit-maternidade-premium.jpg", "043-arranjo-com-chocolate.jpg", "044-rosa-e-ferrero-50g.jpg", "045-girassol-solidario-com-ferreiro-collection.jpg", "046-buque-de-tulipas-rosa.jpg", "047-buque-com-tulipas-e-rosas-inglesas.jpg", "048-poinssetia.jpg", "049-kit-natalino.jpg", "050-kit-natal.jpg", "051-cesta-de-cafe-1.jpg", "052-cesta-de-cafe-com-girassol.jpg", "053-vinho-reservado-carmenere.jpg", "054-kit-maternidade.jpg", "055-kit-2-rosas-e-mini-ferreiro-rocher.jpg", "056-buque-com-24-rosas-nacionais.jpg", "057-box-com-girassol-e-chandon.jpg", "058-buque-com-rosa-e-astromelias.jpg", "059-buque-e-ferreiro-rocher.jpg", "060-cesta-com-rosas-e-chandon.jpg", "061-buque-com-3-lirios.jpg", "062-cesta-com-kalandiva.jpg", "063-box-de-flores.jpg", "064-buque-de-rosas-com-astromelias.jpg", "065-buque-de-gerberas-colorida.jpg", "066-buque-de-flores-silvestres.jpg", "067-buque-com-20-rosas-colombianas.jpg", "068-buque-amor-vibrante.jpg", "069-buque-com-24-rosas-importadas.jpg", "070-nutella-p.jpg", "071-buque-com-lirios-coloridos.jpg", "072-espumante-rose-monte-pascoal.jpg", "073-buque-flores-silvestre.jpg", "074-arranjo-grande-com-astromelias-rosas.jpg", "075-buque-de-rosas-manipuladas.jpg", "076-urso-grande.jpg", "077-arranjo-rosa.jpg", "078-box-com-12-rosas-nacionais.jpg", "079-vaso-de-vidro.jpg", "080-cesta.jpg", "081-buque-gerberas-e-rosas-brancas.jpg", "082-box-mae.jpg", "083-cesta-com-lirio-e-espumante.jpg", "084-orquidea-phale-media.jpg", "085-arranjo-com-rosas.jpg", "086-buque-angelica.jpg", "087-buque-com-40-rosas-colombianas.jpg", "088-buque-encanto-inesquecivel.jpg", "089-buque.jpg", "090-arranjo-statis.jpg", "091-buque-com-24-rosas-colombianas.jpg", "092-arranjo-com-2-rosas-colombiana.jpg", "093-arranjo-com-3-rosas-brancas.jpg", "094-arranjo-pink-de-rosas-e-astromelia.jpg", "095-arranjo-com-3-orquideas-pink.jpg", "096-buque-jasmine.jpg", "097-arranjo-de-rosa-colombiana.jpg", "098-kit-amor-perfeito.jpg", "099-buque-primeira.jpg", "100-buque-de-rosa-branca-nacional.jpg", "101-arranjo-no-vaso-de-vidro.jpg", "102-arranjo.jpg", "103-cesta-com-arranjo-e-chocolate.jpg", "104-buque-com-12-rosas-e-gypsophila.jpg", "105-arranjo-de-rosas.jpg", "106-buque-de-flores-finas.jpg", "107-buque-com-cravinas-coloridas.jpg", "108-buque-com-10-rosas-nacional.jpg", "109-arranjo-de-rosas-e-lirio-1.jpg", "110-bujudinho-de-rosa-e-girasol.jpg", "111-arranjo-rose.jpg", "112-buque-com-girassois.jpg", "113-buque-de-60-rosas-colombianas.jpg", "114-orquidea-branca-cascata.jpg", "115-buque-com-18-rosas-nacionais.jpg", "116-ferreiro-rocher-150g.jpg", "117-arranjo-branco-com-flores-finas.jpg", "118-arranjo-de-rosas-e-astromelia-branca.jpg"];

  const log = (...args) => console.log("%c[Helena Flores]", "color:#c0392b;font-weight:bold", ...args);

  // 1) Carrega a lib JSZip via CDN (só para empacotar tudo num único arquivo)
  async function loadJSZip() {
    if (window.JSZip) return window.JSZip;
    await new Promise((resolve, reject) => {
      const s = document.createElement("script");
      s.src = "https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js";
      s.onload = resolve;
      s.onerror = reject;
      document.head.appendChild(s);
    });
    return window.JSZip;
  }

  // 2) Encontra o painel do catálogo que rola (scroll) e rola até o fim,
  //    esperando novos itens carregarem, para forçar o carregamento total.
  function findScrollableCatalogPanel() {
    // Pega o maior container scrollável visível na tela (heurística genérica,
    // já que o WhatsApp muda os nomes das classes com frequência).
    const candidates = Array.from(document.querySelectorAll("div"))
      .filter((el) => {
        const style = getComputedStyle(el);
        return (
          (style.overflowY === "auto" || style.overflowY === "scroll") &&
          el.scrollHeight > el.clientHeight + 50 &&
          el.clientHeight > 200
        );
      })
      .sort((a, b) => b.scrollHeight - a.scrollHeight);
    return candidates[0] || document.scrollingElement;
  }

  async function autoScrollToLoadAll(panel, maxRounds = 400) {
    let lastHeight = -1;
    let stableRounds = 0;
    for (let i = 0; i < maxRounds; i++) {
      panel.scrollTop = panel.scrollHeight;
      await new Promise((r) => setTimeout(r, 350));
      const h = panel.scrollHeight;
      if (h === lastHeight) {
        stableRounds++;
        if (stableRounds >= 4) break; // parou de crescer -> chegou ao fim
      } else {
        stableRounds = 0;
      }
      lastHeight = h;
    }
  }

  // 3) Coleta as imagens dos produtos, em ordem visual (topo -> baixo),
  //    filtrando ícones pequenos (avatar, setas, etc.)
  function collectProductImages() {
    const imgs = Array.from(document.querySelectorAll("img"))
      .filter((img) => {
        const rect = img.getBoundingClientRect();
        const w = img.naturalWidth || rect.width;
        const h = img.naturalHeight || rect.height;
        return w > 55 && h > 55; // remove ícones pequenos
      })
      .filter((img) => img.src && (img.src.startsWith("blob:") || img.src.startsWith("http") || img.src.startsWith("data:")));

    // Ordena pela posição vertical na página (ordem visual real)
    imgs.sort((a, b) => {
      const ra = a.getBoundingClientRect();
      const rb = b.getBoundingClientRect();
      return ra.top - rb.top || ra.left - rb.left;
    });

    // Remove duplicados (mesmo elemento pode repetir por causa do sort)
    return Array.from(new Set(imgs));
  }

  async function imgToBlob(img) {
    try {
      if (img.src.startsWith("data:")) {
        const res = await fetch(img.src);
        return await res.blob();
      }
      const res = await fetch(img.src);
      return await res.blob();
    } catch (e) {
      // fallback: desenha em canvas (funciona para blob: e http mesmo com CORS simples)
      try {
        const canvas = document.createElement("canvas");
        canvas.width = img.naturalWidth;
        canvas.height = img.naturalHeight;
        const ctx = canvas.getContext("2d");
        ctx.drawImage(img, 0, 0);
        return await new Promise((resolve) => canvas.toBlob(resolve, "image/jpeg", 0.92));
      } catch (e2) {
        return null;
      }
    }
  }

  log("Iniciando... procurando o painel do catálogo.");
  const panel = findScrollableCatalogPanel();
  if (!panel) {
    log("ERRO: não encontrei o painel do catálogo na tela. Abra o catálogo da Helena Flores e tente de novo.");
    return;
  }

  log("Rolando a lista para carregar todos os produtos, aguarde...");
  await autoScrollToLoadAll(panel);

  log("Coletando imagens visíveis...");
  const images = collectProductImages();
  log(`Encontrei ${images.length} imagens. Esperado (do seu JSON): ${ORDER_NAMES.length}.`);

  if (images.length !== ORDER_NAMES.length) {
    log(
      "⚠️ ATENÇÃO: a quantidade de imagens encontradas é DIFERENTE da quantidade esperada.\n" +
      "Isso normalmente acontece quando: (a) nem tudo carregou ainda (role manualmente até o fim e rode de novo),\n" +
      "ou (b) a ordem do catálogo mudou desde que o JSON foi montado.\n" +
      "Vou continuar e nomear até onde der, mas CONFIRA a tabela final com cuidado antes de usar."
    );
  }

  const JSZip = await loadJSZip();
  const zip = new JSZip();
  const mapping = [];

  for (let i = 0; i < images.length; i++) {
    const name = ORDER_NAMES[i] || `999-extra-${i + 1}.jpg`;
    const blob = await imgToBlob(images[i]);
    if (blob) {
      zip.file(name, blob);
      mapping.push({ indice: i + 1, arquivo: name, origem: images[i].src.slice(0, 60) });
    } else {
      mapping.push({ indice: i + 1, arquivo: name, origem: "FALHOU AO BAIXAR" });
    }
  }

  console.table(mapping);
  log("Gerando o arquivo .zip, aguarde...");

  const content = await zip.generateAsync({ type: "blob" });
  const url = URL.createObjectURL(content);
  const a = document.createElement("a");
  a.href = url;
  a.download = "helena-flores-imagens.zip";
  document.body.appendChild(a);
  a.click();
  a.remove();

  log(
    "PRONTO! Baixei 'helena-flores-imagens.zip'.\n" +
    'Extraia o conteúdo dele dentro de:\n' +
    'C:\\Users\\ILHA\\Documents\\GitHub\\helenaflores\\_catalogo referencia\\assets\\uploads\n' +
    "Confira a tabela acima: cada linha mostra o índice, o nome do arquivo final e de onde a imagem veio.\n" +
    "Se algum item ficar 'FALHOU AO BAIXAR' ou a ordem parecer errada, me avise o índice específico."
  );
})();
