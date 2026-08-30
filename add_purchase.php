<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

$message = '';

// 1. CHARGEMENT DE LA LISTE DES MAGASINS / FOURNISSEURS
try {
    $fournisseurs = $pdo->query("SELECT id, nom_magasin FROM fournisseurs ORDER BY nom_magasin ASC")->fetchAll();
} catch (PDOException $e) {
    die("Erreur de chargement de la liste des magasins : " . htmlspecialchars($e->getMessage()));
}

// 2. TRAITEMENT DE LA SOUMISSION DU FORMULAIRE (la redirection s'effectue AVANT l'affichage HTML)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date_achat = $_POST['date_achat'] ?? date('Y-m-d');
    $fournisseur_id = !empty($_POST['fournisseur_id']) ? (int)$_POST['fournisseur_id'] : null;
    $montant = (float)str_replace(',', '.', $_POST['montant'] ?? 0);
    $remarques = trim($_POST['remarques'] ?? '');

    if (empty($date_achat)) {
        $message = '<div class="alert alert-danger">Veuillez indiquer la date d\'achat !</div>';
    } elseif ($montant == 0) {
        $message = '<div class="alert alert-danger">Le montant de l\'achat ne peut pas être égal à 0 !</div>';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO purchases (date_achat, fournisseur_id, montant, remarques) VALUES (:date_achat, :fournisseur_id, :montant, :remarques)");
            $stmt->execute([
                    ':date_achat' => $date_achat,
                    ':fournisseur_id' => $fournisseur_id,
                    ':montant' => $montant,
                    ':remarques' => $remarques
            ]);

            header("Location: purchases_list.php?updated=1");
            exit;
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Erreur lors de l\'enregistrement de l\'achat : ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// 3. Connexion de l'en-tête UNIQUEMENT APRES que toutes les redirections possibles aient eu lieu
require_once 'header.php';
?>

<title>Ajouter un achat — NumériqueAide</title>

<div class="container mt-5" style="max-width: 650px;">
    <div class="card shadow-sm">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="bi bi-bag-plus me-2"></i>Ajouter un achat de matériaux</h4>
            <a href="purchases_list.php" class="btn btn-sm btn-outline-light">Retour à la liste des achats</a>
        </div>
        <div class="card-body">
            <?= $message; ?>

            <form action="" method="POST">
                <div class="row">
                    <!-- Date d'achat -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Date d'achat *</label>
                        <input type="date" name="date_achat" class="form-control" required
                               value="<?= htmlspecialchars($_POST['date_achat'] ?? date('Y-m-d')); ?>">
                    </div>

                    <!-- Magasin / Fournisseur -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Magasin</label>
                        <select name="fournisseur_id" class="form-select">
                            <option value="">-- Choisir un magasin --</option>
                            <?php foreach ($fournisseurs as $f): ?>
                                <?php $selected = (($_POST['fournisseur_id'] ?? '') == $f['id']) ? 'selected' : ''; ?>
                                <option value="<?= $f['id']; ?>" <?= $selected; ?>>
                                    <?= htmlspecialchars($f['nom_magasin']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Montant -->
                <div class="mb-3">
                    <label class="form-label fw-bold">Montant (€) *</label>
                    <div class="input-group">
                        <input type="number" step="0.01" name="montant" class="form-control" placeholder="15.80" required
                               value="<?= htmlspecialchars($_POST['montant'] ?? ''); ?>">
                        <span class="input-group-text">€</span>
                    </div>
                    <div class="form-text">Pour un retour de marchandise, indiquez un montant négatif (par exemple : -14.54).</div>
                </div>

                <!-- Remarques -->
                <div class="mb-3">
                    <label class="form-label fw-bold">Remarques / Composition de l'achat</label>
                    <textarea name="remarques" class="form-control" rows="3"
                              placeholder="ex : Colle universelle, gants, fil, prise..."><?= htmlspecialchars($_POST['remarques'] ?? ''); ?></textarea>
                </div>

                <div class="d-grid gap-2 mt-4">
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="bi bi-check-circle me-1"></i> Enregistrer l'achat
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>