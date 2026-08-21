const fs = require('fs');

const mermaid = fs.readFileSync('erd.txt', 'utf-8');
const lines = mermaid.split('\n');

let sql = '';
let currentTable = '';

for (const line of lines) {
    const trimmed = line.trim();
    if (trimmed.startsWith('erDiagram') || trimmed === '' || trimmed.includes('}o--||') || trimmed.startsWith('%%')) {
        // Skip
        continue;
    }

    if (trimmed.endsWith('{')) {
        currentTable = trimmed.replace('{', '').trim();
        sql += `CREATE TABLE ${currentTable} (\n`;
        continue;
    }

    if (trimmed === '}') {
        // Remove trailing comma
        sql = sql.replace(/,\n$/, '\n');
        sql += `);\n\n`;
        continue;
    }

    // Inside table
    // Format: type name [modifiers]
    let parts = trimmed.split(' ');
    if (parts.length >= 2) {
        let type = parts[0];
        let name = parts[1];
        
        let sqlLine = `    ${name} ${type}`;
        if (parts.includes('PK') || name === 'id' || name.endsWith('_id') && type==='id') {
            sqlLine += ' PRIMARY KEY';
        }
        sqlLine += ',\n';
        sql += sqlLine;
    }
}

// Draw.io handles foreign keys through CREATE TABLE syntax or ALTER TABLE.
// Actually, draw.io's SQL parser automatically connects tables if they have identical column names (like `user_id` in users and `user_id` in another table) or if we use ALTER TABLE.
// Let's add ALTER TABLE for foreign keys
for (const line of lines) {
    const trimmed = line.trim();
    if (trimmed.includes('}o--||')) {
        // users }o--|| roles : "role_id"
        let parts = trimmed.split('}o--||');
        let left = parts[0].trim();
        let rightPart = parts[1].split(':');
        let right = rightPart[0].trim();
        let col = rightPart[1].replace(/"/g, '').trim();

        // left is the child (many), right is the parent (one)
        // Alter table left add foreign key (col) references right(id)
        sql += `ALTER TABLE ${left} ADD CONSTRAINT fk_${left}_${col} FOREIGN KEY (${col}) REFERENCES ${right}(id);\n`;
    }
}

fs.writeFileSync('drawio.sql', sql);
