<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

$message = '';
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: fournisseurs_list.php');
    exit;
}

// 2. ENREGISTREMENT DES MODIFICATIONS (Placé en haut pour éviter les erreurs de redirection)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_magasin = trim($_POST['nom_magasin'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');

    if (empty($nom_magasin)) {
        $message = '<div class="alert alert-danger">Le nom du magasin est obligatoire !</div>';
    } else {
        try {
            $updateStmt = $pdo->prepare("UPDATE fournisseurs SET nom_magasin = :nom, adresse = :adresse WHERE id = :id");
            $updateStmt->execute([
                    ':nom' => $nom_magasin,
                    ':adresse' => !empty($adresse) ? $adresse : null,
                    ':id' => $id
            ]);

            header("Location: fournisseurs_list.php?updated=1");
            exit;
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Erreur de mise à jour : ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// 1. RÉCUPÉRATION DES DONNÉES DU FOURNISSEUR
try {
    $stmt = $pdo->prepare("SELECT * FROM fournisseurs WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $fournisseur = $stmt->fetch();

    if (!$fournisseur) {
        die("Fournisseur introuvable.");
    }
} catch (PDOException $e) {
    die("Erreur de chargement : " . htmlspecialchars($e->getMessage()));
}

require_once 'header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier le magasin — NumériqueAide</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">

<div class="container mt-5" style="max-width: 550px;">
    <div class="card shadow-sm">
        <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Modifier le magasin #<?= $fournisseur['id']; ?></h4>
            <a href="fournisseurs_list.php" class="btn btn-sm btn-outline-dark">Retour à la liste</a>
        </div>
        <div class="card-body">
            <?= $message; ?>

            <form action="" method="POST">
                <div class="mb-3">
                    <label class="form-label">Nom du magasin *</label>
                    <input type="text" name="nom_magasin" class="form-control" required
                           value="<?= htmlspecialchars($_POST['nom_magasin'] ?? $fournisseur['nom_magasin']); ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Adresse</label>
                    <textarea name="adresse" class="form-control" rows="3"><?= htmlspecialchars($_POST['adresse'] ?? $fournisseur['adresse'] ?? ''); ?></textarea>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-check-circle me-1"></i> Enregistrer les modifications
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>