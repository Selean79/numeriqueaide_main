<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'header.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_magasin = trim($_POST['nom_magasin'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');

    if (empty($nom_magasin)) {
        $message = '<div class="alert alert-danger">Название магазина обязательно!</div>';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO fournisseurs (nom_magasin, adresse) VALUES (:nom, :adresse)");
            $stmt->execute([
                ':nom' => $nom_magasin,
                ':adresse' => !empty($adresse) ? $adresse : null
            ]);

            header("Location: fournisseurs_list.php?updated=1");
            exit;
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Ошибка сохранения: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Добавить магазин — NumériqueAide</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">

<div class="container mt-5" style="max-width: 550px;">
    <div class="card shadow-sm">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="bi bi-shop me-2"></i>Добавить новый магазин</h4>
            <a href="fournisseurs_list.php" class="btn btn-sm btn-outline-light">К списку</a>
        </div>
        <div class="card-body">
            <?= $message; ?>

            <form action="" method="POST">
                <div class="mb-3">
                    <label class="form-label">Название магазина *</label>
                    <input type="text" name="nom_magasin" class="form-control" required
                           value="<?= htmlspecialchars($_POST['nom_magasin'] ?? ''); ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Адрес</label>
                    <textarea name="adresse" class="form-control" rows="3"><?= htmlspecialchars($_POST['adresse'] ?? ''); ?></textarea>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="bi bi-check-circle me-1"></i> Сохранить
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
