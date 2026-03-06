path = r'c:\xampp\htdocs\PortalSite\pages\equipments\parts.php'
with open(path, 'r', encoding='utf-8') as f:
    content = f.read()

changes = 0

# 1. cardMakes array: add supplierPartNumber after supplierAddress, before supplierPrice
old1 = "'supplierAddress' => isset($mk['supplier_address']) ? $mk['supplier_address'] : '',\n\t\t\t\t\t\t\t\t'supplierPrice'"
new1 = "'supplierAddress' => isset($mk['supplier_address']) ? $mk['supplier_address'] : '',\n\t\t\t\t\t\t\t\t'supplierPartNumber' => isset($mk['supplier_part_number']) ? $mk['supplier_part_number'] : '',\n\t\t\t\t\t\t\t\t'supplierPrice'"
if old1 in content:
    content = content.replace(old1, new1, 1)
    changes += 1
    print("1: OK - cardMakes supplierPartNumber added")
else:
    print("1: FAILED - cardMakes old string not found")

# 2. First make edit populate: add sspn var + if (sspn) between saddr and sprice
old2 = "var saddr = firstItem.querySelector('.make-supplier-address');\n\t\t\t\t\t\t\tvar sprice = firstItem.querySelector('.make-supplier-price');"
new2 = "var saddr = firstItem.querySelector('.make-supplier-address');\n\t\t\t\t\t\t\tvar sspn = firstItem.querySelector('.make-supplier-part-number');\n\t\t\t\t\t\t\tvar sprice = firstItem.querySelector('.make-supplier-price');"
if old2 in content:
    content = content.replace(old2, new2, 1)
    changes += 1
    print("2: OK - first make sspn var added")
else:
    print("2: FAILED - first make var block not found")

old3 = "if (saddr) saddr.value = first.supplierAddress || '';\n\t\t\t\t\t\t\tif (sprice) sprice.value = first.supplierPrice || '';"
new3 = "if (saddr) saddr.value = first.supplierAddress || '';\n\t\t\t\t\t\t\tif (sspn) sspn.value = first.supplierPartNumber || '';\n\t\t\t\t\t\t\tif (sprice) sprice.value = first.supplierPrice || '';"
if old3 in content:
    content = content.replace(old3, new3, 1)
    changes += 1
    print("3: OK - first make sspn populate added")
else:
    print("3: FAILED - first make if-block not found")

# 3. Remaining makes edit populate
old4 = "var saddr2 = last.querySelector('.make-supplier-address');\n\t\t\t\t\t\t\tvar sprice2 = last.querySelector('.make-supplier-price');"
new4 = "var saddr2 = last.querySelector('.make-supplier-address');\n\t\t\t\t\t\t\tvar sspn2 = last.querySelector('.make-supplier-part-number');\n\t\t\t\t\t\t\tvar sprice2 = last.querySelector('.make-supplier-price');"
if old4 in content:
    content = content.replace(old4, new4, 1)
    changes += 1
    print("4: OK - remaining make sspn2 var added")
else:
    print("4: FAILED - remaining make var block not found")

old5 = "if (saddr2) saddr2.value = m2.supplierAddress || '';\n\t\t\t\t\t\t\tif (sprice2) sprice2.value = m2.supplierPrice || '';"
new5 = "if (saddr2) saddr2.value = m2.supplierAddress || '';\n\t\t\t\t\t\t\tif (sspn2) sspn2.value = m2.supplierPartNumber || '';\n\t\t\t\t\t\t\tif (sprice2) sprice2.value = m2.supplierPrice || '';"
if old5 in content:
    content = content.replace(old5, new5, 1)
    changes += 1
    print("5: OK - remaining make sspn2 populate added")
else:
    print("5: FAILED - remaining make if-block not found")

with open(path, 'w', encoding='utf-8') as f:
    f.write(content)

print(f"\nTotal changes: {changes}/5")
