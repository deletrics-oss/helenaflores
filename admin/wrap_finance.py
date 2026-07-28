import re

files = [
    r'C:\Users\FightArcade-1\Documents\GitHub\FightArcadeCatalogo\admin\dashboard.php',
    r'C:\Users\FightArcade-1\Documents\GitHub\FightArcadeCatalogo\admin\orders.php'
]

for file_path in files:
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()

    # Pattern to match R$ followed by a PHP echo of number_format
    # 1. R$ <?= ... ?>
    content = re.sub(r'(R\$\s*<\?=\s*number_format\([^;]+\)\s*\?>)', r'<span class="finance-value">\1</span>', content)
    
    # 2. R$ <?php echo ... ?>
    content = re.sub(r'(R\$\s*<\?php\s*echo\s*number_format\([^;]+;\s*\?>)', r'<span class="finance-value">\1</span>', content)

    # 3. R$  in JS template literals
    content = re.sub(r'(R\$\s*\$\{price\.toFixed\(2\)\})', r'<span class="finance-value">\1</span>', content)

    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(content)
print('Done!')
