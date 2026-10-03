const fs = require('fs');

const adminControllerPath = 'app/Http/Controllers/Api/AdminController.php';
const content = fs.readFileSync(adminControllerPath, 'utf8');

const classMatch = content.match(/class\s+AdminController\s+extends\s+[\w]+\s*{/);
if (!classMatch) {
    console.error("Class not found!");
    process.exit(1);
}

const header = content.substring(0, classMatch.index).trim();
const classBodyStart = classMatch.index + classMatch[0].length;

const methodPattern = /((?:\/\*\*[\s\S]*?\*\/\s*)?(?:public|protected|private)\s+function\s+([a-zA-Z0-9_]+)\s*\([^)]*\)\s*(?::\s*[\w?]+)?\s*{)/g;

let methods = {};
let pos = classBodyStart;
let match;

methodPattern.lastIndex = pos;

while ((match = methodPattern.exec(content)) !== null) {
    const methodName = match[2];
    const methodStart = match.index;
    
    let braceCount = 1;
    let i = methodStart + match[1].length;
    
    while (i < content.length && braceCount > 0) {
        if (content[i] === '{') braceCount++;
        else if (content[i] === '}') braceCount--;
        i++;
    }
    
    const methodBody = content.substring(methodStart, i);
    methods[methodName] = methodBody;
    
    methodPattern.lastIndex = i;
}

function createController(name, methodNames) {
    const filePath = `app/Http/Controllers/Api/${name}.php`;
    let body = "";
    
    for (const m of methodNames) {
        if (methods[m]) {
            body += "    " + methods[m].replace(/\n/g, '\n    ') + "\n\n";
        } else {
            console.error(`Method ${m} not found for ${name}`);
        }
    }
    
    body = body.replace(/\n\s+\n/g, '\n\n');
    
    const output = `${header}\n\nclass ${name} extends AdminController\n{\n${body}}\n`;
    fs.writeFileSync(filePath, output, 'utf8');
    console.log(`Created ${name}.php`);
}

createController('AdminSettingController', ['getSettings', 'updateSettings']);
createController('AdminFAQController', ['getFAQs', 'createFAQ', 'updateFAQ', 'deleteFAQ']);
createController('AdminPaymentController', ['getPaymentGateways', 'updatePaymentGateway', 'getPaymentTransactions']);
