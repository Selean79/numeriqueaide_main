<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

$message = '';
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: purchases_list.php');
    exit;
}

// 3. ОБРАБОТКА СОХРАНЕНИЯ ИЗМЕНЕНИЙ (Перенесена наверх, до вывода HTML)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date_achat = $_POST['date_achat'] ?? date('Y-m-d');
    $fournisseur_id = !empty($_POST['fournisseur_id']) ? (int)$_POST['fournisseur_id'] : null;
    $montant = (float)str_replace(',', '.', $_POST['montant'] ?? 0);
    $remarques = trim($_POST['remarques'] ?? '');

    if (empty($date_achat)) {
        $message = '<div class="alert alert-danger">Indiquez la date d\'achat !</div>';
    } elseif ($montant == 0) {
        $message = '<div class="alert alert-danger">Le montant de l\'achat ne peut pas être égal à 0 !</div>';
    } else {
        try {
            $updateStmt = $pdo->prepare("
                UPDATE purchases 
                SET date_achat = :date_achat, 
                    fournisseur_id = :fournisseur_id, 
                    montant = :montant, 
                    remarques = :remarques 
                WHERE id = :id
            ");

            $updateStmt->execute([
                    ':date_achat' => $date_achat,
                    ':fournisseur_id' => $fournisseur_id,
                    ':montant' => $montant,
                    ':remarques' => $remarques,
                    ':id' => $id
            ]);

            header("Location: purchases_list.php?updated=1");
            exit;
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Erreur lors de la mise à jour de l\'achat : ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// 1. ЗАГРУЗКА ДАННЫХ РЕДАКТИРУЕМОЙ ЗАКУПКИ
try {
    $stmt = $pdo->prepare("SELECT * FROM purchases WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $purchase = $stmt->fetch();

    if (!$purchase) {
        die("Enregistrement d'achat introuvable.");
    }
} catch (PDOException $e) {
    die("Erreur de chargement de l'achat : " . htmlspecialchars($e->getMessage()));
}

// 2. ЗАГРУЗКА СПИСКА МАГАЗИНОВ / ПОСТАВЩИКОВ
try {
    $fournisseurs = $pdo->query("SELECT id, nom_magasin FROM fournisseurs ORDER BY nom_magasin ASC")->fetchAll();
} catch (PDOException $e) {
    die("Erreur de chargement de la liste des magasins : " . htmlspecialchars($e->getMessage()));
}

require_once 'header.php';
?>

<title>Modifier l'achat — NumériqueAide</title>

<div class="container mt-5" style="max-width: 650px;">
    <div class="card shadow-sm">
        <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Modifier l'achat #<?= $purchase['id']; ?></h4>
            <a href="purchases_list.php" class="btn btn-sm btn-outline-dark">Retour à la liste des achats</a>
        </div>
        <div class="card-body">
            <?= $message; ?>

            <form action="" method="POST">
                <div class="row">
                    <!-- Дата закупки -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Date d'achat *</label>
                        <input type="date" name="date_achat" class="form-control" required
                               value="<?= htmlspecialchars($_POST['date_achat'] ?? $purchase['date_achat']); ?>">
                    </div>

                    <!-- Магазин / Поставщик -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Magasin</label>
                        <select name="fournisseur_id" class="form-select">
                            <option value="">-- Choisir un magasin --</option>
                            <?php foreach ($fournisseurs as $f): ?>
                                <?php
                                $selected = (($_POST['fournisseur_id'] ?? $purchase['fournisseur_id']) == $f['id']) ? 'selected' : '';
                                ?>
                                <option value="<?= $f['id']; ?>" <?= $selected; ?>>
                                    <?= htmlspecialchars($f['nom_magasin']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Сумма закупки -->
                <div class="mb-3">
                    <label class="form-label fw-bold">Montant (€) *</label>
                    <div class="input-group">
                        <input type="number" step="0.01" name="montant" class="form-control" placeholder="15.80" required
                               value="<?= htmlspecialchars($_POST['montant'] ?? $purchase['montant']); ?>">
                        <span class="input-group-text">€</span>
                    </div>
                    <div class="form-text">Pour un retour de marchandise, indiquez un montant négatif (par exemple : -14.54).</div>
                </div>

                <!-- Примечания / Название материалов -->
                <div class="mb-3">
                    <label class="form-label fw-bold">Remarques / Composition de l'achat</label>
                    <textarea name="remarques" class="form-control" rows="3"
                              placeholder="ex : Colle universelle, gants, fil, prise..."><?= htmlspecialchars($_POST['remarques'] ?? $purchase['remarques'] ?? ''); ?></textarea>
                </div>

                <div class="d-grid gap-2 mt-4">
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