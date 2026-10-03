import os
import re

ADMIN_CONTROLLER_PATH = 'app/Http/Controllers/Api/AdminController.php'
API_ROUTES_PATH = 'routes/api.php'

def parse_php_methods(file_path):
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()

    # Find the class opening
    class_match = re.search(r'class\s+AdminController\s+extends\s+Controller\s*{', content)
    if not class_match:
        print("Could not find class definition")
        return None, None

    header = content[:class_match.end()]
    class_body_start = class_match.end()
    
    # We need to find the matching closing brace for the class
    brace_count = 1
    class_body_end = class_body_start
    
    methods = []
    
    # We'll use a regex to find all method signatures
    method_pattern = re.compile(r'(?:public|protected|private)\s+function\s+([a-zA-Z0-9_]+)\s*\([^)]*\)\s*{')
    
    # Actually, a simpler way is to just find each method by regex, and then count braces to get its body.
    # Since PHP syntax is well-formed, we can just iterate.
    
    pos = class_body_start
    while True:
        match = method_pattern.search(content, pos)
        if not match:
            break
            
        method_name = match.group(1)
        method_start = match.start()
        
        # Now find the end of the method by counting braces
        brace_count = 1
        i = match.end()
        while i < len(content) and brace_count > 0:
            if content[i] == '{':
                brace_count += 1
            elif content[i] == '}':
                brace_count -= 1
            i += 1
            
        method_body = content[method_start:i]
        methods.append({
            'name': method_name,
            'body': method_body
        })
        
        pos = i

    # Extract use statements from header
    use_statements = re.findall(r'^use\s+[\w\\]+;$', header, re.MULTILINE)
    
    return header, methods

header, methods = parse_php_methods(ADMIN_CONTROLLER_PATH)
if header:
    print(f"Found {len(methods)} methods in AdminController.")
    for m in methods:
        print(m['name'])
