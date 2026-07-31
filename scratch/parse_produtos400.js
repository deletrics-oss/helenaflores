import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const CSV_PATH = path.resolve(__dirname, '../produtos400.csv');
const UPLOADS_DIR = path.resolve(__dirname, '../assets/uploads');

if (!fs.existsSync(UPLOADS_DIR)) {
    fs.mkdirSync(UPLOADS_DIR, { recursive: true });
}

// Simple robust CSV parser for multi-line fields and base64
function parseCSV(content) {
    const lines = [];
    let currentLine = '';
    let inQuotes = false;

    for (let i = 0; i < content.length; i++) {
        const char = content[i];
        if (char === '"') {
            inQuotes = !inQuotes;
            currentLine += char;
        } else if ((char === '\n' || char === '\r') && !inQuotes) {
            if (char === '\r' && content[i+1] === '\n') {
                i++;
            }
            if (currentLine.trim()) {
                lines.push(currentLine);
            }
            currentLine = '';
        } else {
            currentLine += char;
        }
    }
    if (currentLine.trim()) lines.push(currentLine);

    return lines.map(line => {
        const row = [];
        let field = '';
        let inQ = false;
        for (let i = 0; i < line.length; i++) {
            const c = line[i];
            if (c === '"') {
                if (inQ && line[i+1] === '"') {
                    field += '"';
                    i++;
                } else {
                    inQ = !inQ;
                }
            } else if (c === ',' && !inQ) {
                row.push(field.trim());
                field = '';
            } else {
                field += c;
            }
        }
        row.push(field.trim());
        return row;
    });
}

function createSlug(str) {
    return str
        .toLowerCase()
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');
}

function cleanPrice(priceStr) {
    if (!priceStr) return 0;
    let clean = String(priceStr).replace(/[^\d,\.]/g, '');
    if (clean.includes(',')) {
        clean = clean.replace(/\./g, '').replace(',', '.');
    }
    return parseFloat(clean) || 0;
}

function detectCategory(title) {
    const t = title.toLowerCase();
    if (t.includes('cesta') || t.includes('café') || t.includes('cafe')) return 'Cestas Personalizadas';
    if (t.includes('girassol')) return 'Girassóis & Flores';
    if (t.includes('orquídea') || t.includes('orquidea') || t.includes('plant')) return 'Orquídeas & Plantas';
    if (t.includes('tulipa') || t.includes('lírio') || t.includes('lirio')) return 'Arranjos & Vasos';
    if (t.includes('ferrero') || t.includes('kit') || t.includes('presente') || t.includes('pelúcia') || t.includes('pelucia') || t.includes('chandon') || t.includes('vinho')) return 'KITS & Presentes';
    if (t.includes('buquê') || t.includes('buque') || t.includes('rosa')) return 'Rosas Colombianas';
    return 'Rosas Colombianas';
}

const fileContent = fs.readFileSync(CSV_PATH, 'utf-8');
const rows = parseCSV(fileContent);

console.log(`Linhas totais parseadas do CSV: ${rows.length}`);

const header = rows[0];
console.log('Colunas:', header.join(' | '));

const products = [];
let savedImages = 0;

for (let i = 1; i < rows.length; i++) {
    const row = rows[i];
    const index = row[0];
    const name = row[1];
    if (!name || name === 'Helena Flores' && !row[3]) continue; // Skip store title header row if empty

    const priceRaw = row[2] || '';
    const priceVal = row[3] || '';
    const currency = row[4] || 'BRL';
    const description = row[5] || '';
    const productLink = row[6] || '';
    const imageUrl = row[7] || '';
    const imageData = row[8] || '';

    const price = cleanPrice(priceVal || priceRaw) || 150.00;
    const slug = createSlug(name);
    const numPrefix = String(i).padStart(3, '0');
    let imageName = `${numPrefix}-${slug}.jpg`;
    const imagePathPhysical = path.join(UPLOADS_DIR, imageName);

    // Save Base64 Image to disk if present
    if (imageData && imageData.includes('base64,')) {
        try {
            const base64Content = imageData.split('base64,')[1];
            fs.writeFileSync(imagePathPhysical, Buffer.from(base64Content, 'base64'));
            savedImages++;
        } catch (err) {
            console.error(`Erro ao salvar imagem para ${name}:`, err.message);
        }
    }

    products.push({
        id: i,
        name: name,
        slug: slug,
        price: price,
        description: description,
        image_path: imageName,
        category: detectCategory(name),
        image_url: imageUrl,
        has_base64: Boolean(imageData)
    });
}

console.log(`\n==========================================================`);
console.log(`🎉 PRODUTOS PROCESSADOS COM SUCESSO: ${products.length} itens!`);
console.log(`📸 FOTOS BASE64 EXPORTADAS PARA UPLOADS: ${savedImages} imagens!`);
console.log(`==========================================================\n`);

// Save processed json file
fs.writeFileSync(
    path.resolve(__dirname, '../scratch/produtos400_processed.json'),
    JSON.stringify(products, null, 2),
    'utf-8'
);

console.log('Salvo arquivo JSON processado em scratch/produtos400_processed.json');
