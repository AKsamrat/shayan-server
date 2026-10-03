const fs = require('fs');
const path = require('path');

const ADMIN_CONTROLLER_PATH = 'app/Http/Controllers/Api/AdminController.php';

function parsePhpMethods(filePath) {
    const content = fs.readFileSync(filePath, 'utf-8');
    
    // Find the class opening
    const classMatch = content.match(/class\s+AdminController\s+extends\s+Controller\s*{/);
    if (!classMatch) {
        console.log("Could not find class definition");
        return;
    }

    const header = content.substring(0, classMatch.index + classMatch[0].length);
    const classBodyStart = classMatch.index + classMatch[0].length;
    
    const methods = [];
    const methodPattern = /(?:public|protected|private)\s+function\s+([a-zA-Z0-9_]+)\s*\([^)]*\)\s*{/g;
    
    let match;
    while ((match = methodPattern.exec(content)) !== null) {
        if (match.index < classBodyStart) continue; // Skip if before class body

        const methodName = match[1];
        const methodStart = match.index;
        
        let braceCount = 1;
        let i = match.index + match[0].length;
        while (i < content.length && braceCount > 0) {
            if (content[i] === '{') braceCount++;
            else if (content[i] === '}') braceCount--;
            i++;
        }
        
        methods.push({
            name: methodName,
            body: content.substring(methodStart, i)
        });
        
        // Reset regex index to the end of this method body
        methodPattern.lastIndex = i;
    }

    return { header, methods };
}

const result = parsePhpMethods(ADMIN_CONTROLLER_PATH);
if (result) {
    console.log(`Found ${result.methods.length} methods in AdminController.`);
    result.methods.forEach(m => console.log(m.name));
}
