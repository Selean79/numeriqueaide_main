<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../db.php';

$csvFile = '/var/www/html/service/clients.csv';

if (!file_exists($csvFile)) {
    die("Файл clients.csv не найден в /var/www/html/service/");
}

if (($handle = fopen($csvFile, "r")) !== FALSE) {
    // Пропускаем заголовки CSV (Nom, Prenom, Adresse, Telephone, Email, Notes, Societe...)
    $headers = fgetcsv($handle, 1000, ",");

    $count = 0;

    $stmt = $pdo->prepare("
        INSERT INTO clients (nom, prenom, adresse, telephone, email, notes, societe)
        VALUES (:nom, :prenom, :adresse, :telephone, :email, :notes, :societe)
    ");

    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $nom       = trim($data[0] ?? '');
        $prenom    = trim($data[1] ?? '');
        $adresse   = trim($data[3] ?? '');
        $telephone = trim($data[4] ?? '');
        $email     = trim($data[5] ?? '');
        $notes     = trim($data[6] ?? '');
        $societe   = (!empty($data[7]) && strtolower($data[7]) !== 'false') ? 1 : 0;

        if (empty($nom) && empty($prenom)) {
            continue; // Пропускаем пустые строки
        }

        try {
            $stmt->execute([
                ':nom'       => $nom,
                ':prenom'    => $prenom,
                ':adresse'   => $adresse,
                ':telephone' => $telephone,
                ':email'     => $email,
                ':notes'     => $notes,
                ':societe'   => $societe
            ]);
            $count++;
        } catch (PDOException $e) {
            echo "Ошибка импорта строки ($nom $prenom): " . $e->getMessage() . "<br>";
        }
    }

    fclose($handle);
    echo "<h3>Импорт завершен! Успешно добавлено клиентов: $count</h3>";
    echo '<a href="clients_list.php">Перейти к списку клиентов</a>';
}