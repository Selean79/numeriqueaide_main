<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

$id = (int)($_GET['id'] ?? 0);
$return_page = $_GET['return'] ?? $_POST['return'] ?? 'commandes_list.php';

if ($id <= 0) {
    header('Location: commandes_list.php');
    exit;
}

// Загрузка заказа строго по ID
$stmt = $pdo->prepare("SELECT * FROM commandes WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$order = $stmt->fetch();

if (!$order) {
    die("Commande introuvable.");
}

$error = '';

// Значения формы
$form = [
        'id_commande'        => $order['id_commande'] ?? '',
        'client_id'          => $order['client_id'] ?? '',
        'platform_id'        => $order['platform_id'] ?? '',
        'payment_method_id' => $order['payment_method_id'] ?? '',
        'facture_id'         => $order['facture_id'] ?? '',
        'date_commande'      => !empty($order['date_commande'])
                ? date('d/m/Y', strtotime($order['date_commande']))
                : '',
        'date_paiement'      => !empty($order['date_paiement'])
                ? date('d/m/Y', strtotime($order['date_paiement']))
                : '',
        'montant'            => $order['montant'] ?? '',
        'statut'             => $order['statut'] ?? 'Prévu',
        'commentaire'        => $order['commentaire'] ?? '',
        'notes'              => $order['notes'] ?? '',
        'calcul_impot'       => isset($order['calcul_impot']) && (float)$order['calcul_impot'] > 0,
        'calcul_epargne'     => isset($order['calcul_epargne']) && (float)$order['calcul_epargne'] > 0,
        'impot_paye'         => !empty($order['impot_paye']),
        'epargne_paye'       => !empty($order['epargne_paye'])
];

// ======================================================
// ОБРАБОТКА ФОРМЫ
// ======================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $return_page = $_POST['return'] ?? 'commandes_list.php';

    $form['id_commande'] = trim($_POST['id_commande'] ?? '');
    $form['client_id'] = !empty($_POST['client_id']) ? (int)$_POST['client_id'] : '';
    $form['platform_id'] = !empty($_POST['platform_id']) ? (int)$_POST['platform_id'] : '';
    $form['payment_method_id'] = !empty($_POST['payment_method_id']) ? (int)$_POST['payment_method_id'] : '';
    $form['facture_id'] = !empty($_POST['facture_id']) ? (int)$_POST['facture_id'] : '';

    $form['date_commande'] = trim($_POST['date_commande'] ?? '');
    $form['date_paiement'] = trim($_POST['date_paiement'] ?? '');
    $form['montant'] = trim($_POST['montant'] ?? '');
    $form['statut'] = $_POST['statut'] ?? 'Prévu';
    $form['commentaire'] = trim($_POST['commentaire'] ?? '');
    $form['notes'] = trim($_POST['notes'] ?? '');

    $form['calcul_impot'] = isset($_POST['calcul_impot']);
    $form['calcul_epargne'] = isset($_POST['calcul_epargne']);
    $form['impot_paye'] = isset($_POST['impot_paye']);
    $form['epargne_paye'] = isset($_POST['epargne_paye']);

    // Конвертация даты заказа
    $date_commande = date('Y-m-d');
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $form['date_commande'], $matches)) {
        $day = (int)$matches[1];
        $month = (int)$matches[2];
        $year = (int)$matches[3];
        if (checkdate($month, $day, $year)) {
            $date_commande = sprintf('%04d-%02d-%02d', $year, $month, $day);
        }
    }

    // Конвертация даты оплаты
    $date_paiement = null;
    if ($form['date_paiement'] !== '') {
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $form['date_paiement'], $matches)) {
            $day = (int)$matches[1];
            $month = (int)$matches[2];
            $year = (int)$matches[3];
            if (checkdate($month, $day, $year)) {
                $date_paiement = sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }
    }

    $montant = (float)str_replace(',', '.', $form['montant']);

    // Расчет налогов
    $calcul_impot = $form['calcul_impot'] ? ($montant * 0.212) : 0;
    $calcul_epargne = $form['calcul_epargne'] ? ($montant * 0.10) : 0;
    $impot_paye = $form['impot_paye'] ? 1 : 0;
    $epargne_paye = $form['epargne_paye'] ? 1 : 0;

    try {
        $updateSql = "
            UPDATE commandes 
            SET
                id_commande        = :id_commande,
                client_id          = :client_id,
                platform_id        = :platform_id,
                payment_method_id = :payment_method_id,
                facture_id         = :facture_id,
                date_commande      = :date_commande,
                date_paiement      = :date_paiement,
                montant            = :montant,
                statut             = :statut,
                calcul_impot       = :calcul_impot,
                calcul_epargne     = :calcul_epargne,
                impot_paye         = :impot_paye,
                epargne_paye       = :epargne_paye,
                commentaire        = :commentaire,
                notes              = :notes
            WHERE id = :id
        ";

        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute([
                ':id_commande'        => $form['id_commande'],
                ':client_id'          => $form['client_id'],
                ':platform_id'        => $form['platform_id'],
                ':payment_method_id' => $form['payment_method_id'],
                ':facture_id'         => $form['facture_id'] ?: null,
                ':date_commande'      => $date_commande,
                ':date_paiement'      => $date_paiement,
                ':montant'            => $montant,
                ':statut'             => $form['statut'],
                ':calcul_impot'       => $calcul_impot,
                ':calcul_epargne'     => $calcul_epargne,
                ':impot_paye'         => $impot_paye,
                ':epargne_paye'       => $epargne_paye,
                ':commentaire'        => $form['commentaire'],
                ':notes'              => $form['notes'],
                ':id'                 => $order['id']
        ]);

        header("Location: " . $return_page);
        exit;

    } catch (PDOException $e) {
        $error = "Erreur lors de la mise à jour de la commande : " . $e->getMessage();
    }
}

// Загрузка справочников
$clients = $pdo->query("SELECT id, nom, prenom FROM clients ORDER BY nom ASC, prenom ASC")->fetchAll();

$platCols = $pdo->query("SHOW COLUMNS FROM platforms")->fetchAll(PDO::FETCH_COLUMN);
$platColName = in_array('nom', $platCols) ? 'nom' : (in_array('name', $platCols) ? 'name' : $platCols[1]);
$platforms = $pdo->query("SELECT id, `$platColName` AS name FROM platforms ORDER BY `$platColName` ASC")->fetchAll();

$pmCols = $pdo->query("SHOW COLUMNS FROM modes_de_paiement")->fetchAll(PDO::FETCH_COLUMN);
$pmColName = in_array('nom', $pmCols) ? 'nom' : (in_array('name', $pmCols) ? 'name' : $pmCols[1]);
$payment_methods = $pdo->query("SELECT id, `$pmColName` AS name FROM modes_de_paiement ORDER BY `$pmColName` ASC")->fetchAll();

$facturesCols = $pdo->query("SHOW COLUMNS FROM factures")->fetchAll(PDO::FETCH_COLUMN);
$factureNumCol = in_array('facture_number', $facturesCols) ? 'facture_number' : (in_array('numero', $facturesCols) ? 'numero' : $facturesCols[1]);
$factures = $pdo->query("SELECT id, `$factureNumCol` AS facture_num FROM factures ORDER BY id DESC")->fetchAll();

require_once 'header.php';
?>

<title>Modifier la commande #<?= htmlspecialchars($order['id_commande']); ?> — NumériqueAide</title>

<!-- Flatpickr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<style>
    body {
        background-color: #cbd5e1 !important;
    }
    .required-star {
        color: #dc3545;
        font-weight: bold;
    }
</style>

<div class="container mt-4 mb-5" style="max-width: 800px;">
    <div class="card shadow border-0">
        <div class="card-header bg-warning bg-gradient text-dark d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold">
                <i class="bi bi-pencil-square me-2"></i>Modifier la commande #<?= htmlspecialchars($order['id_commande']); ?>
            </h5>
            <a href="<?= htmlspecialchars($return_page); ?>" class="btn btn-sm btn-outline-dark">Retour</a>
        </div>
        <div class="card-body p-4">

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="commandeForm">
                <input type="hidden" name="return" value="<?= htmlspecialchars($return_page); ?>">

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            Numéro de commande (ID) <span class="required-star">*</span>
                        </label>
                        <input type="text" name="id_commande" class="form-control" value="<?= htmlspecialchars($form['id_commande']); ?>" required oninvalid="this.setCustomValidity('Veuillez remplir ce champ.')" oninput="this.setCustomValidity('')">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            Client <span class="required-star">*</span>
                        </label>
                        <select name="client_id" class="form-select" required oninvalid="this.setCustomValidity('Veuillez sélectionner un client.')" oninput="this.setCustomValidity('')">
                            <option value="">-- Choisir un client --</option>
                            <?php foreach ($clients as $client): ?>
                                <?php $clientName = trim(($client['nom'] ?? '') . ' ' . ($client['prenom'] ?? '')); ?>
                                <option value="<?= $client['id']; ?>" <?= ((string)$client['id'] === (string)$form['client_id']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($clientName); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">
                            Date de commande <span class="required-star">*</span>
                        </label>
                        <input type="text" name="date_commande" class="form-control js-date-picker" placeholder="jj/mm/aaaa" value="<?= htmlspecialchars($form['date_commande']); ?>" required oninvalid="this.setCustomValidity('Veuillez indiquer la date de commande.')" oninput="this.setCustomValidity('')">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">
                            Montant (€) <span class="required-star">*</span>
                        </label>
                        <input type="number" step="0.01" min="0.01" name="montant" class="form-control" value="<?= htmlspecialchars($form['montant']); ?>" required oninvalid="this.setCustomValidity('Veuillez saisir un montant supérieur à 0.')" oninput="this.setCustomValidity('')">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Statut</label>
                        <?php $st = mb_strtolower(trim($form['statut'] ?? '')); ?>
                        <select name="statut" class="form-select">
                            <option value="Prévu" <?= ($st === 'prévu' || $st === 'prevu') ? 'selected' : ''; ?>>Prévu</option>
                            <option value="En cours" <?= ($st === 'en cours' || $st === 'en_cours' || $st === 'en travail' || $st === 'в работе') ? 'selected' : ''; ?>>En cours</option>
                            <option value="Payé" <?= ($st === 'payé' || $st === 'paye' || $st === 'terminee') ? 'selected' : ''; ?>>Payé</option>
                            <option value="Annulée" <?= ($st === 'annulée' || $st === 'annulee') ? 'selected' : ''; ?>>Annulée</option>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">
                            Plateforme / Service <span class="required-star">*</span>
                        </label>
                        <select name="platform_id" class="form-select" required oninvalid="this.setCustomValidity('Veuillez sélectionner une plateforme ou un service.')" oninput="this.setCustomValidity('')">
                            <option value="">-- Non spécifiée --</option>
                            <?php foreach ($platforms as $p): ?>
                                <option value="<?= $p['id']; ?>" <?= ((string)$p['id'] === (string)$form['platform_id']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($p['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold">
                            Mode de paiement <span class="required-star">*</span>
                        </label>
                        <select name="payment_method_id" class="form-select" required oninvalid="this.setCustomValidity('Veuillez sélectionner un mode de paiement.')" oninput="this.setCustomValidity('')">
                            <option value="">-- Non spécifié --</option>
                            <?php foreach ($payment_methods as $pm): ?>
                                <option value="<?= $pm['id']; ?>" <?= ((string)$pm['id'] === (string)$form['payment_method_id']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($pm['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold text-primary">
                            <i class="bi bi-file-earmark-text me-1"></i>Facture
                        </label>
                        <select name="facture_id" class="form-select border-primary">
                            <option value="">-- Sans facture --</option>
                            <?php foreach ($factures as $fac): ?>
                                <option value="<?= $fac['id']; ?>" <?= ((string)$fac['id'] === (string)$form['facture_id']) ? 'selected' : ''; ?>>
                                    Facture #<?= htmlspecialchars($fac['facture_num']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold text-success">Date de paiement</label>
                        <input type="text" name="date_paiement" class="form-control js-date-picker" placeholder="jj/mm/aaaa" value="<?= htmlspecialchars($form['date_paiement']); ?>">
                    </div>
                </div>

                <div class="card bg-light border-0 mb-3">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="bi bi-calculator me-1"></i>Calcul et paiement des taxes / cotisations</h6>

                        <div class="row g-3 mb-2">
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="calcul_impot" id="calculImpot" value="1" <?= $form['calcul_impot'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold" for="calculImpot">Calculer la taxe URSSAF (21.2%)</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="calcul_epargne" id="calculEpargne" value="1" <?= $form['calcul_epargne'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold" for="calculEpargne">Calculer l'épargne (10%)</label>
                                </div>
                            </div>
                        </div>

                        <hr class="my-2">

                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="impot_paye" id="impotPaye" value="1" <?= $form['impot_paye'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label text-success fw-bold" for="impotPaye"><i class="bi bi-check-circle me-1"></i>Taxe URSSAF PAYÉE</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="epargne_paye" id="epargnePaye" value="1" <?= $form['epargne_paye'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label text-success fw-bold" for="epargnePaye"><i class="bi bi-check-circle me-1"></i>Épargne VERSÉE</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Commentaire de la commande</label>
                    <textarea name="commentaire" class="form-control" rows="2" placeholder="Brève description du travail..."><?= htmlspecialchars($form['commentaire']); ?></textarea>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">Notes internes</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Notes de service..."><?= htmlspecialchars($form['notes']); ?></textarea>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-check-circle me-1"></i> Enregistrer les modifications
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        flatpickr(".js-date-picker", {
            dateFormat: "d/m/Y",
            allowInput: true
        });
    });
</script>

</body>
</html>