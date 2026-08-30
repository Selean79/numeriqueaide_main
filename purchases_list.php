<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

// Функция оформления названия магазина в виде цветного бейджа
function renderMagasinBadge($magasinName) {
    $name = trim($magasinName ?? '');
    if (empty($name)) {
        return '<span class="text-muted">—</span>';
    }

    $badgeClass = 'bg-secondary';
    $lower = mb_strtolower($name);

    if (strpos($lower, 'leroy') !== false || strpos($lower, 'merlin') !== false) {
        $badgeClass = 'bg-success';
    } elseif (strpos($lower, 'castorama') !== false) {
        $badgeClass = 'bg-primary';
    } elseif (strpos($lower, 'brico') !== false) {
        $badgeClass = 'bg-warning text-dark';
    } elseif (strpos($lower, 'amazon') !== false) {
        $badgeClass = 'bg-dark';
    } elseif (strpos($lower, 'action') !== false) {
        $badgeClass = 'bg-info text-dark';
    }

    return '<span class="badge ' . $badgeClass . '">' . htmlspecialchars($name) . '</span>';
}

// Suppression d'un achat
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM purchases WHERE id = :id");
        $stmt->execute([':id' => $delete_id]);
        header("Location: purchases_list.php?deleted=1");
        exit;
    } catch (PDOException $e) {
        $error_message = "Erreur lors de la suppression : " . $e->getMessage();
    }
}

// Paramètres de filtre par mois et année
$month = isset($_GET['month']) && $_GET['month'] !== '' ? (int)$_GET['month'] : '';
$year = isset($_GET['year']) && $_GET['year'] !== '' ? (int)$_GET['year'] : '';
$search = trim($_GET['search'] ?? '');

// Détermination dynamique du nom de la colonne dans la table fournisseurs
$fournCols = $pdo->query("SHOW COLUMNS FROM fournisseurs")->fetchAll(PDO::FETCH_COLUMN);
$fournColName = in_array('nom', $fournCols) ? 'nom' : (in_array('name', $fournCols) ? 'name' : $fournCols[1]);
$fournNameCol = "f.`$fournColName`";

// Requête de la liste des achats avec filtres
$sql = "
    SELECT 
        p.*,
        $fournNameCol AS fournisseur_nom
    FROM purchases p
    LEFT JOIN fournisseurs f ON p.fournisseur_id = f.id
    WHERE 1=1
";

$params = [];

if (!empty($month) && !empty($year)) {
    $sql .= " AND MONTH(p.date_achat) = :month AND YEAR(p.date_achat) = :year";
    $params[':month'] = $month;
    $params[':year'] = $year;
} elseif (!empty($year)) {
    $sql .= " AND YEAR(p.date_achat) = :year";
    $params[':year'] = $year;
}

if (!empty($search)) {
    $sql .= " AND (p.remarques LIKE :search OR $fournNameCol LIKE :search)";
    $params[':search'] = "%$search%";
}

$sql .= " ORDER BY p.date_achat DESC, p.id DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $purchases = $stmt->fetchAll();

    // Подсчет суммы отфильтрованных закупок для карточки
    $totalAmount = 0;
    foreach ($purchases as $item) {
        $totalAmount += (float)$item['montant'];
    }
} catch (PDOException $e) {
    die("Erreur de chargement des achats : " . htmlspecialchars($e->getMessage()));
}

$french_months = [1=>'Janvier', 2=>'Février', 3=>'Mars', 4=>'Avril', 5=>'Mai', 6=>'Juin', 7=>'Juillet', 8=>'Août', 9=>'Septembre', 10=>'Octobre', 11=>'Novembre', 12=>'Décembre'];

require_once 'header.php';
?>

<title>Achats de matériaux — NumériqueAide</title>

<div class="container-fluid mt-4 px-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0"><i class="bi bi-bag-check me-2"></i>Achats de matériaux (Purchases)</h3>
        <a href="add_purchase.php" class="btn btn-success">
            <i class="bi bi-plus-circle me-1"></i> Ajouter un achat
        </a>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            L'enregistrement de l'achat a été supprimé avec succès.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Панель фильтров и итогов -->
    <div class="card shadow-sm p-3 mb-4 border-0">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Mois</label>
                <select name="month" class="form-select shadow-sm" onchange="this.form.submit()">
                    <option value="">-- Tous les mois --</option>
                    <?php foreach($french_months as $m => $name): ?>
                        <option value="<?=$m?>" <?=$month==$m?'selected':''?>><?=$name?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Année</label>
                <select name="year" class="form-select shadow-sm" onchange="this.form.submit()">
                    <option value="">-- Toutes --</option>
                    <?php for($y=2025; $y<=2027; $y++): ?>
                        <option value="<?=$y?>" <?=$year==$y?'selected':''?>><?=$y?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Recherche</label>
                <input type="text" name="search" class="form-control" placeholder="Magasin, remarque..." value="<?= htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i> Filtrer</button>
            </div>
            <div class="col-md-2">
                <a href="purchases_list.php" class="btn btn-outline-secondary w-100">Réinitialiser</a>
            </div>
        </form>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-12">
            <div class="card bg-secondary text-white shadow-sm">
                <div class="card-body p-3 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-white-50 small">Total des dépenses d'achats (période sélectionnée)</div>
                        <div class="fs-3 fw-bold"><?= number_format($totalAmount, 2, ',', ' '); ?> €</div>
                    </div>
                    <i class="bi bi-cart3 fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Tableau des achats -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                    <tr>
                        <th style="width: 140px;">Date d'achat <i class="bi bi-arrow-down-short"></i></th>
                        <th style="width: 120px;" class="text-end">Montant</th>
                        <th style="width: 200px;">Magasin</th>
                        <th>Remarques</th>
                        <th style="width: 100px;" class="text-center">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($purchases)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">Aucun achat trouvé</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($purchases as $item): ?>
                            <?php
                            $magasinDisplay = !empty($item['fournisseur_nom']) ? $item['fournisseur_nom'] : ($item['magasin'] ?? '');
                            $montantVal = (float)$item['montant'];
                            ?>
                            <tr>
                                <!-- Date -->
                                <td class="text-nowrap"><?= date('d.m.Y', strtotime($item['date_achat'])); ?></td>

                                <!-- Montant (красный цвет для отрицательных значений) -->
                                <td class="text-end fw-bold <?= ($montantVal < 0) ? 'text-danger' : ''; ?>">
                                    <?= number_format($montantVal, 2, ',', ' '); ?> €
                                </td>

                                <!-- Magasin -->
                                <td><?= renderMagasinBadge($magasinDisplay); ?></td>

                                <!-- Remarques -->
                                <td><?= htmlspecialchars($item['remarques'] ?? '—'); ?></td>

                                <!-- Actions -->
                                <td class="text-center text-nowrap">
                                    <a href="edit_purchase.php?id=<?= $item['id']; ?>" class="btn btn-sm btn-outline-primary me-1" title="Modifier">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="purchases_list.php?delete_id=<?= $item['id']; ?>"
                                       class="btn btn-sm btn-outline-danger"
                                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet achat ?');"
                                       title="Supprimer">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>