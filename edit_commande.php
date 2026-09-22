<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: commandes_list.php");
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Даты принимаем как из обычного поля DD/MM/YYYY, так и из календаря HTML5 YYYY-MM-DD
    $date_commande_raw = trim($_POST['date_commande'] ?? '');
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_commande_raw)) {
        $date_commande = $date_commande_raw;
    } elseif (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date_commande_raw, $m)) {
        $date_commande = $m[3] . '-' . $m[2] . '-' . $m[1];
    } else {
        $date_commande = date('Y-m-d');
    }

    $date_paiement_raw = trim($_POST['date_paiement'] ?? '');
    $date_paiement = null;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_paiement_raw)) {
        $date_paiement = $date_paiement_raw;
    } elseif (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date_paiement_raw, $m)) {
        $date_paiement = $m[3] . '-' . $m[2] . '-' . $m[1];
    }

    $rdv_time           = trim($_POST['rdv_time'] ?? null);
    $client_id          = (int)($_POST['client_id'] ?? 0);
    $platform_id        = (int)($_POST['platform_id'] ?? 0);
    $payment_method_id  = (int)($_POST['payment_method_id'] ?? 0);
    $facture_id         = !empty($_POST['facture_id']) ? (int)$_POST['facture_id'] : null;
    $montant            = str_replace(',', '.', trim($_POST['montant'] ?? '0'));
    $statut             = trim($_POST['statut'] ?? 'Prévu');
    $notes              = trim($_POST['notes'] ?? '');
    $commentaire        = trim($_POST['commentaire'] ?? '');
    
    $calcul_impot       = isset($_POST['calcul_impot']) ? $_POST['calcul_impot'] : 0;
    $calcul_epargne     = isset($_POST['calcul_epargne']) ? $_POST['calcul_epargne'] : 0;
    $impot_paye         = isset($_POST['impot_paye']) ? 1 : 0;
    $epargne_paye       = isset($_POST['epargne_paye']) ? 1 : 0;

    if ($client_id <= 0 || empty($montant)) {
        $message = '<div class="alert alert-danger">Veuillez renseigner le client et le montant !</div>';
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE commandes SET 
                    date_commande = :date_commande, 
                    rdv_time = :rdv_time, 
                    client_id = :client_id, 
                    platform_id = :platform_id, 
                    payment_method_id = :payment_method_id, 
                    facture_id = :facture_id, 
                    montant = :montant, 
                    statut = :statut, 
                    date_paiement = :date_paiement, 
                    notes = :notes, 
                    commentaire = :commentaire, 
                    calcul_impot = :calcul_impot, 
                    calcul_epargne = :calcul_epargne, 
                    impot_paye = :impot_paye, 
                    epargne_paye = :epargne_paye
                WHERE id = :id
            ");
            
            $stmt->execute([
                ':date_commande'     => $date_commande,
                ':rdv_time'          => !empty($rdv_time) ? $rdv_time : null,
                ':client_id'         => $client_id,
                ':platform_id'       => $platform_id > 0 ? $platform_id : null,
                ':payment_method_id' => $payment_method_id > 0 ? $payment_method_id : null,
                ':facture_id'        => $facture_id,
                ':montant'           => $montant,
                ':statut'            => $statut,
                ':date_paiement'     => $date_paiement,
                ':notes'             => $notes,
                ':commentaire'       => $commentaire,
                ':calcul_impot'      => $calcul_impot,
                ':calcul_epargne'    => $calcul_epargne,
                ':impot_paye'        => $impot_paye,
                ':epargne_paye'      => $epargne_paye,
                ':id'                => $id
            ]);

            header("Location: commandes_list.php?updated=1#order-" . $id);
            exit;

        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Erreur de modification : ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM commandes WHERE id = :id");
$stmt->execute([':id' => $id]);
$order = $stmt->fetch();

if (!$order) {
    header("Location: commandes_list.php");
    exit;
}

// Переводим из YYYY-MM-DD (БД) в DD/MM/YYYY для отображения
$date_commande_formatted = !empty($order['date_commande']) ? date('d/m/Y', strtotime($order['date_commande'])) : '';
$date_paiement_formatted = !empty($order['date_paiement']) ? date('d/m/Y', strtotime($order['date_paiement'])) : '';

$clients = $pdo->query("SELECT id, nom, prenom FROM clients ORDER BY nom ASC")->fetchAll();
$platforms = $pdo->query("SELECT * FROM platforms")->fetchAll();
$payment_methods = $pdo->query("SELECT * FROM modes_de_paiement")->fetchAll();

$is_modal = isset($_GET['modal']);
if (!$is_modal) {
    require_once 'header.php';
}
?>

<title>Modifier la commande — NumériqueAide</title>


<style>
    .card {
        background-color: transparent !important;
        border: none !important;
    }
    .card-body.custom-card-body {
        background-color: #e9ecef !important; /* Серый фон внутри рамки */
        border-radius: 0.375rem;
    }
</style>

<div class="container mt-4 mb-5" style="max-width: 700px;">
    <?php if (!$is_modal): ?>
        <div class="d-flex align-items-center mb-4">
            <a href="commandes_list.php" class="btn btn-outline-secondary btn-sm me-3"><i class="bi bi-arrow-left"></i> Retour</a>
            <h3 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2"></i>Modifier la commande</h3>
        </div>
    <?php endif; ?>

    <?php echo $message; ?>

    <div class="card shadow-sm">
        <div class="card-body custom-card-body p-4">
            <form action="edit_commande.php?id=<?= $id; ?><?= $is_modal ? '&modal=1' : ''; ?>" method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Date de commande <span class="text-danger">*</span></label>
                        <input type="date" id="date_commande" name="date_commande" class="form-control bg-white" value="<?= htmlspecialchars($order['date_commande'] ?? ''); ?>" lang="fr-FR" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Heure RDV</label>
                        <input type="time" name="rdv_time" class="form-control bg-white" value="<?= htmlspecialchars($order['rdv_time'] ?? ''); ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Client <span class="text-danger">*</span></label>
                    <select name="client_id" class="form-select bg-white" required>
                        <option value="">Sélectionner un client</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= $c['id']; ?>" <?= ($order['client_id'] == $c['id']) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars(trim($c['nom'] . ' ' . $c['prenom'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Plateforme</label>
                        <select name="platform_id" class="form-select bg-white">
                            <option value="">Aucune</option>
                            <?php foreach ($platforms as $p): ?>
                                <option value="<?= $p['id']; ?>" <?= ($order['platform_id'] == $p['id']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($p['nom'] ?? $p['name'] ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Mode de paiement</label>
                        <select name="payment_method_id" class="form-select bg-white">
                            <option value="">Aucun</option>
                            <?php foreach ($payment_methods as $pm): ?>
                                <option value="<?= $pm['id']; ?>" <?= ($order['payment_method_id'] == $pm['id']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($pm['nom'] ?? $pm['name'] ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold">Montant (€) <span class="text-danger">*</span></label>
                        <input type="text" name="montant" class="form-control bg-white" value="<?= htmlspecialchars($order['montant']); ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold">Statut</label>
                        <select name="statut" class="form-select bg-white">
                            <option value="Prévu" <?= ($order['statut'] === 'Prévu') ? 'selected' : ''; ?>>Prévu</option>
                            <option value="En cours" <?= ($order['statut'] === 'En cours') ? 'selected' : ''; ?>>En cours</option>
                            <option value="Payé" <?= ($order['statut'] === 'Payé') ? 'selected' : ''; ?>>Payé</option>
                            <option value="Annulée" <?= ($order['statut'] === 'Annulée') ? 'selected' : ''; ?>>Annulée</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold">Date de paiement</label>
                        <input type="date" id="date_paiement" name="date_paiement" class="form-control bg-white" value="<?= htmlspecialchars($order['date_paiement'] ?? ''); ?>" lang="fr-FR">
                    </div>
                </div>

                <!-- Блок налогов и накоплений с текущими значениями -->
                <div class="card border p-3 mb-3 bg-white">
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <label class="form-label fw-semibold small">Calcul Taxe (Impôt 21.2%)</label>
                            <select name="calcul_impot" class="form-select form-select-sm">
                                <option value="1" <?= ($order['calcul_impot'] == 1) ? 'selected' : ''; ?>>Oui (21.2%)</option>
                                <option value="0" <?= ($order['calcul_impot'] == 0) ? 'selected' : ''; ?>>Non</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="form-label fw-semibold small">Calcul Cumul (Épargne 10%)</label>
                            <select name="calcul_epargne" class="form-select form-select-sm">
                                <option value="1" <?= ($order['calcul_epargne'] == 1) ? 'selected' : ''; ?>>Oui (10%)</option>
                                <option value="0" <?= ($order['calcul_epargne'] == 0) ? 'selected' : ''; ?>>Non</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mt-1">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="impot_paye" value="1" id="impotPayeCheck" <?= !empty($order['impot_paye']) ? 'checked' : ''; ?>>
                                <label class="form-check-label small" for="impotPayeCheck">Taxe payée</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="epargne_paye" value="1" id="epargnePayeCheck" <?= !empty($order['epargne_paye']) ? 'checked' : ''; ?>>
                                <label class="form-check-label small" for="epargnePayeCheck">Cumul transféré</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Commentaire</label>
                    <textarea name="commentaire" class="form-control bg-white" rows="2"><?= htmlspecialchars($order['commentaire'] ?? ''); ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Notes</label>
                    <textarea name="notes" class="form-control bg-white" rows="2"><?= htmlspecialchars($order['notes'] ?? ''); ?></textarea>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <?php if (!$is_modal): ?>
                        <a href="commandes_list.php" class="btn btn-secondary">Annuler</a>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Enregistrer les modifications</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>


<?php if (!$is_modal): ?>
    </body>
    </html>
<?php endif; ?>