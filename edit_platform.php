<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

$message = '';
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: platforms_list.php');
    exit;
}

// 2. TRAITEMENT DE L'ENREGISTREMENT DES MODIFICATIONS (Placé en haut pour éviter les erreurs de redirection)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $default_impot_rate = (float)str_replace(',', '.', $_POST['default_impot_rate'] ?? 0);
    $default_epargne_rate = (float)str_replace(',', '.', $_POST['default_epargne_rate'] ?? 0);

    if (empty($name)) {
        $message = '<div class="alert alert-danger">Le nom de la plateforme est obligatoire !</div>';
    } else {
        try {
            $updateStmt = $pdo->prepare("UPDATE platforms SET name = :name, default_impot_rate = :impot, default_epargne_rate = :epargne WHERE id = :id");
            $updateStmt->execute([
                    ':name' => $name,
                    ':impot' => $default_impot_rate,
                    ':epargne' => $default_epargne_rate,
                    ':id' => $id
            ]);

            header("Location: platforms_list.php?updated=1");
            exit;
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Erreur de mise à jour : ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// 1. CHARGEMENT DES DONNÉES DE LA PLATEFORME
try {
    $stmt = $pdo->prepare("SELECT * FROM platforms WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $platform = $stmt->fetch();

    if (!$platform) {
        die("Plateforme introuvable.");
    }
} catch (PDOException $e) {
    die("Erreur de chargement : " . htmlspecialchars($e->getMessage()));
}

require_once 'header.php';
?>

<title>Modifier la plateforme — NumériqueAide</title>

<div class="container mt-5" style="max-width: 550px;">
    <div class="card shadow-sm">
        <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Modifier la plateforme #<?= $platform['id']; ?></h4>
            <a href="platforms_list.php" class="btn btn-sm btn-outline-dark">Retour à la liste</a>
        </div>
        <div class="card-body">
            <?= $message; ?>

            <form action="" method="POST">
                <div class="mb-3">
                    <label class="form-label font-weight-bold">Nom de la plateforme *</label>
                    <input type="text" name="name" class="form-control" required
                           value="<?= htmlspecialchars($_POST['name'] ?? $platform['name']); ?>">
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Taux de taxe (%)</label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0" max="100" name="default_impot_rate" class="form-control"
                                   value="<?= htmlspecialchars($_POST['default_impot_rate'] ?? $platform['default_impot_rate']); ?>">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Taux de cotisation (%)</label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0" max="100" name="default_epargne_rate" class="form-control"
                                   value="<?= htmlspecialchars($_POST['default_epargne_rate'] ?? $platform['default_epargne_rate']); ?>">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2 mt-3">
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