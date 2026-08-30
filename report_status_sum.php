<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'header.php';

// Paramètres
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');

$start_date = sprintf('%04d-%02d-01', $year, $month);
$end_date   = date('Y-m-t', strtotime($start_date));

// Requête des montants par statuts
$sql = "SELECT statut, SUM(montant) as total FROM commandes 
        WHERE date_commande BETWEEN :start AND :end 
        GROUP BY statut";
$stmt = $pdo->prepare($sql);
$stmt->execute([':start' => $start_date, ':end' => $end_date]);
$stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$sum_en_cours = $stats['En cours'] ?? 0;
$sum_prevu    = $stats['Prévu'] ?? 0;

$french_months = [1=>'Janvier', 2=>'Février', 3=>'Mars', 4=>'Avril', 5=>'Mai', 6=>'Juin', 7=>'Juillet', 8=>'Août', 9=>'Septembre', 10=>'Octobre', 11=>'Novembre', 12=>'Décembre'];
?>

<title>Montants par statuts — NumériqueAide</title>

<div class="container-fluid mt-4 px-4">
    <!-- Bouton de retour aux rapports et titre -->
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="reports.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Retour aux rapports</a>
        <h3 class="mb-0 fw-bold"><i class="bi bi-graph-up-arrow me-2"></i>Montants par statuts (En cours / Prévu)</h3>
    </div>

    <!-- Formulaire de sélection de la période -->
    <div class="card shadow-sm p-4 mb-4 border-0">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Mois</label>
                <select name="month" class="form-select shadow-sm" onchange="this.form.submit()">
                    <?php foreach($french_months as $m => $name): ?>
                        <option value="<?=$m?>" <?=$month==$m?'selected':''?>><?=$name?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Année</label>
                <select name="year" class="form-select shadow-sm" onchange="this.form.submit()">
                    <?php for($y=2025; $y<=2027; $y++): ?>
                        <option value="<?=$y?>" <?=$year==$y?'selected':''?>><?=$y?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </form>
    </div>

    <!-- Cartes de résultats -->
    <div class="row g-4">
        <div class="col-md-6">
            <div class="card bg-warning text-dark shadow-sm border-0">
                <div class="card-body text-center p-4">
                    <h5 class="card-title fw-bold">En cours</h5>
                    <div class="fs-2 fw-bold"><?= number_format($sum_en_cours, 2, ',', ' '); ?> €</div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card bg-success text-white shadow-sm border-0">
                <div class="card-body text-center p-4">
                    <h5 class="card-title fw-bold">Prévu</h5>
                    <div class="fs-2 fw-bold"><?= number_format($sum_prevu, 2, ',', ' '); ?> €</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>