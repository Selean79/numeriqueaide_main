<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../db.php';

$csvFile = __DIR__ . '/Purchases-Grid view.csv';

if (!file_exists($csvFile)) {
    $csvFile = __DIR__ . '/purchases.csv';
    if (!file_exists($csvFile)) {
        die("Файл Purchases-Grid view.csv не найден.");
    }
}

// 1. Определяем таблицу закупок
$tableName = 'purchases';
try {
    $pdo->query("SELECT 1 FROM purchases LIMIT 1");
} catch (PDOException $e) {
    $tableName = 'achats';
}

// 2. Получаем имена колонок таблицы закупок
$stmtCols = $pdo->query("SHOW COLUMNS FROM `$tableName`");
$columnsPurchases = $stmtCols->fetchAll(PDO::FETCH_COLUMN);

$storeIdCol = 'fournisseur_id';
if (in_array('magasin_id', $columnsPurchases)) {
    $storeIdCol = 'magasin_id';
} elseif (in_array('store_id', $columnsPurchases)) {
    $storeIdCol = 'store_id';
}

// 3. Получаем имена колонок таблицы поставщиков
$stmtSuppCols = $pdo->query("SHOW COLUMNS FROM `fournisseurs`");
$supplierCols = $stmtSuppCols->fetchAll(PDO::FETCH_COLUMN);

// Ищем поле названия поставщика среди существующих
$possibleNames = ['nom', 'name', 'magasin', 'nom_magasin', 'nom_fournisseur', 'raison_sociale', 'libelle'];
$supplierNameCol = null;

foreach ($possibleNames as $possible) {
    if (in_array($possible, $supplierCols)) {
        $supplierNameCol = $possible;
        break;
    }
}

if (!$supplierNameCol) {
    // Если ни одно не подошло, берём вторую колонку в таблице (после id)
    $supplierNameCol = $supplierCols[1] ?? 'id';
}

// Очищаем таблицу закупок перед повторным импортом
$pdo->exec("TRUNCATE TABLE `$tableName`");

if (($handle = fopen($csvFile, "r")) !== FALSE) {
    fgetcsv($handle, 1000, ",", '"', ""); // Пропуск заголовка

    $count = 0;

    $findSupplierStmt = $pdo->prepare("
        SELECT id FROM fournisseurs 
        WHERE LOWER(TRIM(`$supplierNameCol`)) = LOWER(TRIM(:nom)) 
           OR LOWER(TRIM(`$supplierNameCol`)) LIKE LOWER(CONCAT('%', :nom, '%')) 
        LIMIT 1
    ");

    $insertStmt = $pdo->prepare("
        INSERT INTO `$tableName` (date_achat, montant, `$storeIdCol`, remarques)
        VALUES (:date_achat, :montant, :store_id, :remarques)
    ");

    while (($data = fgetcsv($handle, 1000, ",", '"', "")) !== FALSE) {
        $date_raw   = trim($data[0] ?? '');
        $amount_raw = trim($data[1] ?? '');
        $magasin    = trim($data[2] ?? '');
        $remarques  = trim($data[3] ?? '');

        if (empty($date_raw) && empty($amount_raw)) {
            continue;
        }

        // Преобразование даты
        $date_achat = null;
        if (!empty($date_raw)) {
            $timestamp = strtotime($date_raw);
            if ($timestamp) {
                $date_achat = date('Y-m-d', $timestamp);
            }
        }
        if (!$date_achat) {
            $date_achat = date('Y-m-d');
        }

        // Преобразование суммы
        $clean_amount = str_replace(['€', ' ', ','], ['', '', '.'], $amount_raw);
        $montant = (float)$clean_amount;

        // Поиск ID магазина
        $store_id = null;
        if (!empty($magasin)) {
            $findSupplierStmt->execute([':nom' => $magasin]);
            $supplier = $findSupplierStmt->fetch();
            if ($supplier) {
                $store_id = $supplier['id'];
            }
        }

        try {
            $insertStmt->execute([
                ':date_achat' => $date_achat,
                ':montant'    => $montant,
                ':store_id'   => $store_id,
                ':remarques'  => $remarques
            ]);
            $count++;
        } catch (PDOException $e) {
            echo "Ошибка записи ($date_raw): " . htmlspecialchars($e->getMessage()) . "<br>";
        }
    }

    fclose($handle);
    echo "<h3>Импорт завершен! Заново успешно загружено закупок с магазинами: $count</h3>";
    echo '<a href="../purchases_list.php">Перейти к списку закупок</a>';
}