<?php

function normalizeImportHeader($value) {
    return strtolower(preg_replace('/[^a-z0-9]/', '', trim((string) $value)));
}

function normalizeImportRow($headers, $row) {
    $data = [];
    foreach ($headers as $index => $header) {
        if ($header !== '') {
            $data[$header] = trim((string) ($row[$index] ?? ''));
        }
    }
    return $data;
}

function importValue($data, $keys, $default = '') {
    foreach ($keys as $key) {
        $normalized = normalizeImportHeader($key);
        if (array_key_exists($normalized, $data) && $data[$normalized] !== '') {
            return $data[$normalized];
        }
    }
    return $default;
}

function normalizeImportDate($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    if (is_numeric($value)) {
        $timestamp = ((float) $value - 25569) * 86400;
        return gmdate('Y-m-d', (int) $timestamp);
    }

    $formats = ['Y-m-d', 'm/d/Y', 'd/m/Y', 'd-m-Y', 'm-d-Y'];
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $value);
        if ($date instanceof DateTime) {
            return $date->format('Y-m-d');
        }
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('Y-m-d', $timestamp) : null;
}

function normalizeMoneyValue($value) {
    return (float) preg_replace('/[^0-9.\-]/', '', (string) $value);
}

function readCsvRows($path) {
    $handle = fopen($path, 'r');
    if ($handle === false) {
        return [];
    }

    $rows = [];
    while (($row = fgetcsv($handle)) !== false) {
        $rows[] = $row;
    }
    fclose($handle);

    return $rows;
}

function readXlsxRows($path) {
    if (!class_exists('ZipArchive')) {
        return [];
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return [];
    }

    $sharedStrings = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $shared = simplexml_load_string($sharedXml);
        if ($shared) {
            foreach ($shared->si as $item) {
                $parts = [];
                if (isset($item->t)) {
                    $parts[] = (string) $item->t;
                }
                foreach ($item->r ?? [] as $run) {
                    $parts[] = (string) $run->t;
                }
                $sharedStrings[] = implode('', $parts);
            }
        }
    }

    $sheetPath = 'xl/worksheets/sheet1.xml';
    $sheetXml = $zip->getFromName($sheetPath);
    $zip->close();
    if ($sheetXml === false) {
        return [];
    }

    $sheet = simplexml_load_string($sheetXml);
    if (!$sheet || !isset($sheet->sheetData->row)) {
        return [];
    }

    $rows = [];
    foreach ($sheet->sheetData->row as $rowNode) {
        $row = [];
        foreach ($rowNode->c as $cell) {
            $ref = (string) $cell['r'];
            preg_match('/([A-Z]+)/', $ref, $matches);
            $column = $matches[1] ?? 'A';
            $index = 0;
            for ($i = 0; $i < strlen($column); $i++) {
                $index = $index * 26 + (ord($column[$i]) - 64);
            }
            $index--;

            $type = (string) $cell['t'];
            $value = isset($cell->v) ? (string) $cell->v : '';
            if ($type === 's') {
                $value = $sharedStrings[(int) $value] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = isset($cell->is->t) ? (string) $cell->is->t : '';
            }
            $row[$index] = $value;
        }

        if ($row) {
            ksort($row);
            $rows[] = $row;
        }
    }

    return $rows;
}

function readImportRows($path, $fileName) {
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if ($extension === 'xlsx') {
        return readXlsxRows($path);
    }
    return readCsvRows($path);
}

function importCustomersFromRows($rows, $pdo) {
    if (count($rows) < 2) {
        return ['imported' => 0, 'skipped' => 0];
    }

    $headers = array_map('normalizeImportHeader', array_shift($rows));
    $sql = 'INSERT INTO customers (customer_id, name, email, phone_no, date_of_entry, amount, plan, software, license_number, product_number, file_password, issue, payment_type, last4, assigned_agent_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            email = VALUES(email),
            phone_no = VALUES(phone_no),
            date_of_entry = VALUES(date_of_entry),
            amount = VALUES(amount),
            plan = VALUES(plan),
            software = VALUES(software),
            license_number = VALUES(license_number),
            product_number = VALUES(product_number),
            file_password = VALUES(file_password),
            issue = VALUES(issue),
            payment_type = VALUES(payment_type),
            last4 = VALUES(last4),
            assigned_agent_id = VALUES(assigned_agent_id)';
    $stmt = $pdo->prepare($sql);

    $imported = 0;
    $skipped = 0;
    foreach ($rows as $row) {
        $data = normalizeImportRow($headers, $row);
        $customerId = importValue($data, ['Customer ID', 'customer_id']);
        $name = importValue($data, ['Name']);
        $phoneNo = importValue($data, ['Phone No', 'Phone', 'Phone Number', 'phone_no']);

        if (!$customerId || !$name || !$phoneNo) {
            $skipped++;
            continue;
        }

        $stmt->execute([
            $customerId,
            $name,
            importValue($data, ['Email', 'Customer Email', 'email']),
            $phoneNo,
            normalizeImportDate(importValue($data, ['Date', 'Date Of Entry'])),
            normalizeMoneyValue(importValue($data, ['Amount'], 0)),
            importValue($data, ['Plan']),
            importValue($data, ['Software']),
            importValue($data, ['License Number', 'License']),
            importValue($data, ['Product Number', 'Product']),
            importValue($data, ['File Password', 'Password']),
            importValue($data, ['Issue', 'Notes']),
            importValue($data, ['Payment Type'], 'Cash'),
            substr(importValue($data, ['Last 4', 'Last4']), 0, 4),
            importValue($data, ['Assigned Agent ID', 'assigned_agent_id'], null) ?: null,
        ]);
        $imported++;
    }

    return ['imported' => $imported, 'skipped' => $skipped];
}
