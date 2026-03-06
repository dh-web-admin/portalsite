path = r'c:\xampp\htdocs\PortalSite\pages\equipments\parts.php'
with open(path, 'r', encoding='utf-8') as f:
    content = f.read()

idx = content.find("'supplierAddress' => isset(")
print("cardMakes section:")
print(repr(content[idx:idx+250]))
print()

idx2 = content.find('var saddr = firstItem')
print("First make edit section:")
print(repr(content[idx2:idx2+200]))
