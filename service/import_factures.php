<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../db.php';

$csvFile = __DIR__ . '/Factures-Grid view.csv';

if (!file_exists($csvFile)) {
    $csvFile = __DIR__ . '/factures.csv';
    if (!file_exists($csvFile)) {
        die("Файл Factures-Grid view.csv не найден в " . htmlspecialchars(__DIR__));
    }
}

if (($handle = fopen($csvFile, "r")) !== FALSE) {
    $headers = fgetcsv($handle, 1000, ",", '"', "");

    $count = 0;

    // Поиск id клиента по имени
    $findClientStmt = $pdo->prepare("
        SELECT id FROM clients 
        WHERE TRIM(CONCAT(COALESCE(nom, ''), ' ', COALESCE(prenom, ''))) LIKE :name
           OR TRIM(nom) LIKE :name 
        LIMIT 1
    ");

    // Вставка с точными именами полей вашей BD
    $insertStmt = $pdo->prepare("
        INSERT INTO factures (facture_number, client_id, issue_date, total_amount, status)
        VALUES (:facture_number, :client_id, :issue_date, :total_amount, :status)
    ");

    while (($data = fgetcsv($handle, 1000, ",", '"', "")) !== FALSE) {
        $facture_number = trim($data[0] ?? '');
        $date_raw       = trim($data[1] ?? '');
        $client_name    = trim($data[2] ?? '');
        $montant_raw    = trim($data[3] ?? '');
        $paid_raw       = trim($data[4] ?? '');

        if (empty($facture_number)) {
            continue;
        }

        // Преобразование даты в YYYY-MM-DD
        $issue_date = null;
        if (!empty($date_raw)) {
            $timestamp = strtotime($date_raw);
            if ($timestamp) {
                $issue_date = date('Y-m-d', $timestamp);
            }
        }
        if (!$issue_date) {
            $issue_date = date('Y-m-d');
        }

        // Очистка суммы (€20.00 -> 20.00)
        $total_amount = (float)preg_replace('/[^\d.]/', '', str_replace(',', '.', $montant_raw));

        // Статус
        $status = ($paid_raw === 'checked') ? 'Payée' : 'En attente';

        // Поиск client_id
        $client_id = null;
        if (!empty($client_name)) {
            $findClientStmt->execute([':name' => '%' . $client_name . '%']);
            $client = $findClientStmt->fetch();
            if ($client) {
                $client_id = $client['id'];
            }
        }

        try {
            $insertStmt->execute([
                ':facture_number' => $facture_number,
                ':client_id'      => $client_id,
                ':issue_date'     => $issue_date,
                ':total_amount'   => $total_amount,
                ':status'         => $status
            ]);
            $count++;
        } catch (PDOException $e) {
            echo "Ошибка добавления счета $facture_number: " . htmlspecialchars($e->getMessage()) . "<br>";
        }
    }

    fclose($handle);
    echo "<h3>Импорт завершен! Успешно добавлено счетов: $count</h3>";
    echo '<a href="../factures_list.php">Перейти к списку счетов</a>';
}