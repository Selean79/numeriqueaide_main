<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

$message = '';

// Traitement du formulaire (la redirection s'effectue AVANT l'affichage HTML)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_magasin = trim($_POST['nom_magasin'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');

    if (empty($nom_magasin)) {
        $message = '<div class="alert alert-danger">Le nom du magasin est obligatoire !</div>';
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
            $message = '<div class="alert alert-danger">Erreur d\'enregistrement : ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

require_once 'header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un magasin — NumériqueAide</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">

<div class="container mt-5" style="max-width: 550px;">
    <div class="card shadow-sm">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="bi bi-shop me-2"></i>Ajouter un nouveau magasin</h4>
            <a href="fournisseurs_list.php" class="btn btn-sm btn-outline-light">Retour à la liste</a>
        </div>
        <div class="card-body">
            <?= $message; ?>

            <form action="" method="POST">
                <div class="mb-3">
                    <label class="form-label">Nom du magasin *</label>
                    <input type="text" name="nom_magasin" class="form-control" required
                           value="<?= htmlspecialchars($_POST['nom_magasin'] ?? ''); ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Adresse</label>
                    <textarea name="adresse" class="form-control" rows="3"><?= htmlspecialchars($_POST['adresse'] ?? ''); ?></textarea>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="bi bi-check-circle me-1"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>