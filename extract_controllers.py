import os
import re

ADMIN_CONTROLLER_PATH = r'd:\Level 2 webdevelopment\Buyer Project\shayan mart\shayan-server\app\Http\Controllers\Api\AdminController.php'

def parse_php_methods(file_path):
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()

    class_match = re.search(r'class\s+AdminController\s+extends\s+[\w]+\s*{', content)
    if not class_match:
        print("Class not found!")
        return None, None

    header = content[:class_match.start()] # get the namespace and uses
    class_body_start = class_match.end()
    
    # Regex to match method signature
    method_pattern = re.compile(r'((?:\/\*\*.*?\*\/\s*)?(?:public|protected|private)\s+function\s+([a-zA-Z0-9_]+)\s*\([^)]*\)\s*(?::\s*[\w?]+)?\s*{)', re.DOTALL)
    
    pos = class_body_start
    methods = {}
    
    while True:
        match = method_pattern.search(content, pos)
        if not match:
            break
            
        method_name = match.group(2)
        method_start = match.start(1)
        
        brace_count = 1
        i = match.end(1)
        while i < len(content) and brace_count > 0:
            if content[i] == '{':
                brace_count += 1
            elif content[i] == '}':
                brace_count -= 1
            i += 1
            
        method_body = content[method_start:i]
        methods[method_name] = method_body
        
        pos = i

    return header, methods

header, methods = parse_php_methods(ADMIN_CONTROLLER_PATH)

if header:
    def create_controller(name, method_names):
        file_path = rf'd:\Level 2 webdevelopment\Buyer Project\shayan mart\shayan-server\app\Http\Controllers\Api\{name}.php'
        body = ""
        for m in method_names:
            if m in methods:
                # the method_body might not have base indentation, let's just append it
                body += "    " + methods[m].replace('\n', '\n    ') + "\n\n"
            else:
                print(f"Method {m} not found for {name}")
        
        # fix indentation slightly
        body = re.sub(r'\n\s+\n', '\n\n', body)

        content = f"{header.strip()}\n\nclass {name} extends AdminController\n{{\n{body}}}\n"
        
        with open(file_path, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f"Created {name}.php")

    create_controller('AdminSettingController', ['getSettings', 'updateSettings'])
    create_controller('AdminFAQController', ['getFAQs', 'createFAQ', 'updateFAQ', 'deleteFAQ'])
    create_controller('AdminPaymentController', ['getPaymentGateways', 'updatePaymentGateway', 'getPaymentTransactions'])
