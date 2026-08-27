<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../db.php';

$csvFile = __DIR__ . '/Commandes-Accueil.csv';

if (!file_exists($csvFile)) {
    $csvFile = __DIR__ . '/commandes.csv';
    if (!file_exists($csvFile)) {
        die("Файл Commandes-Accueil.csv не найден.");
    }
}

// 1. Очищаем таблицу перед перезагрузкой
$pdo->exec("TRUNCATE TABLE `commandes`");

function getPlatformId($pdo, $platformName) {
    if (empty($platformName)) return null;
    $cols = $pdo->query("SHOW COLUMNS FROM `platforms`")->fetchAll(PDO::FETCH_COLUMN);
    $nameCol = in_array('nom', $cols) ? 'nom' : (in_array('name', $cols) ? 'name' : $cols[1]);

    $stmt = $pdo->prepare("SELECT id FROM platforms WHERE LOWER(TRIM(`$nameCol`)) = LOWER(TRIM(:nom)) LIMIT 1");
    $stmt->execute([':nom' => $platformName]);
    $row = $stmt->fetch();
    if ($row) return $row['id'];

    $insert = $pdo->prepare("INSERT INTO platforms (`$nameCol`) VALUES (:nom)");
    $insert->execute([':nom' => $platformName]);
    return $pdo->lastInsertId();
}

function getPaymentMethodId($pdo, $methodName) {
    if (empty($methodName)) return null;
    $cols = $pdo->query("SHOW COLUMNS FROM `modes_de_paiement`")->fetchAll(PDO::FETCH_COLUMN);
    $nameCol = in_array('nom', $cols) ? 'nom' : (in_array('name', $cols) ? 'name' : $cols[1]);

    $stmt = $pdo->prepare("SELECT id FROM modes_de_paiement WHERE LOWER(TRIM(`$nameCol`)) = LOWER(TRIM(:nom)) LIMIT 1");
    $stmt->execute([':nom' => $methodName]);
    $row = $stmt->fetch();
    if ($row) return $row['id'];

    $insert = $pdo->prepare("INSERT INTO modes_de_paiement (`$nameCol`) VALUES (:nom)");
    $insert->execute([':nom' => $methodName]);
    return $pdo->lastInsertId();
}

function getDefaultClientId($pdo) {
    $stmt = $pdo->query("SELECT id FROM clients LIMIT 1");
    $row = $stmt->fetch();
    if ($row) return $row['id'];

    $pdo->exec("INSERT INTO clients (nom, prenom) VALUES ('Не указан', 'Клиент')");
    return $pdo->lastInsertId();
}

$defaultClientId = getDefaultClientId($pdo);

$findClientStmt = $pdo->prepare("
    SELECT id FROM clients 
    WHERE (telephone IS NOT NULL AND telephone != '' AND REPLACE(REPLACE(REPLACE(telephone, ' ', ''), '+', ''), '-', '') LIKE :phone)
       OR (TRIM(CONCAT(COALESCE(nom, ''), ' ', COALESCE(prenom, ''))) LIKE :name)
       OR (TRIM(nom) LIKE :name)
    LIMIT 1
");

$insertStmt = $pdo->prepare("
    INSERT INTO commandes 
    (id_commande, date_commande, client_id, platform_id, payment_method_id, montant, statut, date_paiement, calcul_impot, calcul_epargne, commentaire, notes)
    VALUES 
    (:id_commande, :date_commande, :client_id, :platform_id, :payment_method_id, :montant, :statut, :date_paiement, :calcul_impot, :calcul_epargne, :commentaire, :notes)
");

if (($handle = fopen($csvFile, "r")) !== FALSE) {
    fgetcsv($handle, 2000, ",", '"', "");

    $count = 0;

    while (($data = fgetcsv($handle, 2000, ",", '"', "")) !== FALSE) {
        $id_commande       = (int)trim($data[0] ?? 0);
        $date_cmd_raw      = trim($data[1] ?? '');
        $platform_raw      = trim($data[2] ?? '');
        $montant_raw       = trim($data[3] ?? '');
        $impot_raw         = trim($data[4] ?? ''); // Сумма налога из CSV
        $epargne_raw       = trim($data[5] ?? ''); // Сумма отчислений из CSV
        $statut            = trim($data[7] ?? '');
        $date_pay_raw      = trim($data[8] ?? '');
        $method_raw        = trim($data[12] ?? '');
        $commentaire       = trim($data[14] ?? '');
        $client_name_csv   = trim($data[15] ?? ($data[13] ?? ''));
        $phone_csv         = trim($data[17] ?? '');
        $notes             = trim($data[18] ?? '');

        if ($id_commande <= 0) continue;

        // Даты
        $date_commande = null;
        if (!empty($date_cmd_raw)) {
            $ts = strtotime($date_cmd_raw);
            if ($ts) $date_commande = date('Y-m-d', $ts);
        }
        if (!$date_commande) $date_commande = date('Y-m-d');

        $date_paiement = null;
        if (!empty($date_pay_raw)) {
            $ts = strtotime($date_pay_raw);
            if ($ts) $date_paiement = date('Y-m-d', $ts);
        }

        // Преобразование сумм в числа
        $montant       = (float)str_replace(['€', ' ', ','], ['', '', '.'], $montant_raw);
        $calcul_impot   = (float)str_replace(['€', ' ', ','], ['', '', '.'], $impot_raw);
        $calcul_epargne = (float)str_replace(['€', ' ', ','], ['', '', '.'], $epargne_raw);

        // Внешние ключи
        $platform_id       = getPlatformId($pdo, $platform_raw);
        $payment_method_id = getPaymentMethodId($pdo, $method_raw);

        // Поиск клиента
        $client_id = null;
        $clean_phone = preg_replace('/[^\d]/', '', $phone_csv);
        if (!empty($clean_phone) && strlen($clean_phone) >= 6) {
            $findClientStmt->execute([
                ':phone' => '%' . substr($clean_phone, -8) . '%',
                ':name'  => '%' . $client_name_csv . '%'
            ]);
            $client = $findClientStmt->fetch();
            if ($client) $client_id = $client['id'];
        }

        if (!$client_id && !empty($client_name_csv)) {
            $findClientStmt->execute([
                ':phone' => 'NOMATCH',
                ':name'  => '%' . $client_name_csv . '%'
            ]);
            $client = $findClientStmt->fetch();
            if ($client) $client_id = $client['id'];
        }

        if (!$client_id) {
            $client_id = $defaultClientId;
        }

        try {
            $insertStmt->execute([
                ':id_commande'       => $id_commande,
                ':date_commande'    => $date_commande,
                ':client_id'        => $client_id,
                ':platform_id'      => $platform_id,
                ':payment_method_id' => $payment_method_id,
                ':montant'          => $montant,
                ':statut'           => $statut,
                ':date_paiement'    => $date_paiement,
                ':calcul_impot'     => $calcul_impot,
                ':calcul_epargne'   => $calcul_epargne,
                ':commentaire'      => $commentaire,
                ':notes'            => $notes
            ]);
            $count++;
        } catch (PDOException $e) {
            echo "Ошибка импорта заказа #$id_commande: " . htmlspecialchars($e->getMessage()) . "<br>";
        }
    }

    fclose($handle);
    echo "<h3>Перезагрузка завершена! Импортировано заказов: $count</h3>";
    echo '<a href="../commandes_list.php">Перейти к списку заказов</a>';
}