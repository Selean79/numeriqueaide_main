<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../db.php';

$csvFile = __DIR__ . '/Factures-Grid view.csv';

if (!file_exists($csvFile)) {
    $csvFile = __DIR__ . '/factures.csv';
    if (!file_exists($csvFile)) {
        die("Файл Factures-Grid view.csv или factures.csv не найден.");
    }
}

if (($handle = fopen($csvFile, "r")) !== FALSE) {
    // Пропускаем заголовок CSV
    fgetcsv($handle, 1000, ",", '"', "");

    $updatedCount = 0;
    $notFoundCount = 0;

    // Запрос поиска ID клиента по имени или фамилии
    $findClientStmt = $pdo->prepare("
        SELECT id, nom, prenom FROM clients 
        WHERE LOWER(TRIM(CONCAT(COALESCE(nom, ''), ' ', COALESCE(prenom, '')))) LIKE LOWER(:name)
           OR LOWER(TRIM(nom)) LIKE LOWER(:name)
           OR LOWER(TRIM(prenom)) LIKE LOWER(:name)
        LIMIT 1
    ");

    // Запрос обновления client_id в таблице factures
    $updateFactureStmt = $pdo->prepare("
        UPDATE factures 
        SET client_id = :client_id 
        WHERE facture_number = :facture_number
    ");

    while (($data = fgetcsv($handle, 1000, ",", '"', "")) !== FALSE) {
        $facture_number = trim($data[0] ?? '');
        $client_name    = trim($data[2] ?? '');

        if (empty($facture_number) || empty($client_name)) {
            continue;
        }

        // Ищем клиента в БД
        $findClientStmt->execute([':name' => '%' . $client_name . '%']);
        $client = $findClientStmt->fetch();

        if ($client) {
            $updateFactureStmt->execute([
                ':client_id'      => $client['id'],
                ':facture_number' => $facture_number
            ]);
            echo "Счет <strong>$facture_number</strong> привязан к клиенту: <strong>" . htmlspecialchars($client['nom'] . ' ' . $client['prenom']) . "</strong> (ID: {$client['id']})<br>";
            $updatedCount++;
        } else {
            echo "<span style='color: red;'>Клиент «" . htmlspecialchars($client_name) . "» для счета $facture_number не найден в таблице clients</span><br>";
            $notFoundCount++;
        }
    }

    fclose($handle);

    echo "<hr><h3>Связывание завершено!</h3>";
    echo "<p>Успешно привязано счетов: <strong>$updatedCount</strong></p>";
    if ($notFoundCount > 0) {
        echo "<p>Не найдено клиентов: <strong>$notFoundCount</strong> (проверьте написание имен в базе)</p>";
    }
    echo '<a href="../factures_list.php">Перейти к списку счетов</a>';
}