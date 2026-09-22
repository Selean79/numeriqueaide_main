<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Конвертируем дату заказа из DD/MM/YYYY в YYYY-MM-DD для MySQL
    $date_commande_raw = trim($_POST['date_commande'] ?? '');
    if (!empty($date_commande_raw) && strpos($date_commande_raw, '/') !== false) {
        $parts = explode('/', $date_commande_raw);
        if (count($parts) === 3) {
            $date_commande = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
        } else {
            $date_commande = date('Y-m-d');
        }
    } else {
        $date_commande = !empty($date_commande_raw) ? $date_commande_raw : date('Y-m-d');
    }

    // Конвертируем дату оплаты из DD/MM/YYYY в YYYY-MM-DD
    $date_paiement_raw = trim($_POST['date_paiement'] ?? '');
    $date_paiement = null;
    if (!empty($date_paiement_raw) && strpos($date_paiement_raw, '/') !== false) {
        $parts = explode('/', $date_paiement_raw);
        if (count($parts) === 3) {
            $date_paiement = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
        }
    } elseif (!empty($date_paiement_raw)) {
        $date_paiement = $date_paiement_raw;
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
            $current_year = date('Y', strtotime($date_commande));
            $next_order_id = 'CMD-' . $current_year . '-1';

            $stmtMax = $pdo->query("
                SELECT MAX(
                    CAST(
                        SUBSTRING_INDEX(id_commande, '-', -1)
                        AS UNSIGNED
                    )
                )
                FROM commandes
                WHERE id_commande LIKE 'CMD-%'
            ");
            $max_id = $stmtMax->fetchColumn();

            if ($max_id) {
                $next_num = (int)$max_id + 1;
                $next_order_id = 'CMD-' . $current_year . '-' . $next_num;
            }

            $stmt = $pdo->prepare("
                INSERT INTO commandes (
                    id_commande, date_commande, rdv_time, client_id, platform_id, 
                    payment_method_id, facture_id, montant, statut, date_paiement, 
                    notes, commentaire, calcul_impot, calcul_epargne, impot_paye, epargne_paye
                )
                VALUES (
                    :id_commande, :date_commande, :rdv_time, :client_id, :platform_id, 
                    :payment_method_id, :facture_id, :montant, :statut, :date_paiement, 
                    :notes, :commentaire, :calcul_impot, :calcul_epargne, :impot_paye, :epargne_paye
                )
            ");
            
            $stmt->execute([
                ':id_commande'       => $next_order_id,
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
                ':epargne_paye'      => $epargne_paye
            ]);

            $new_id = $pdo->lastInsertId();

            header("Location: commandes_list.php?added=1#order-" . $new_id);
            exit;

        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Erreur d\'enregistrement : ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

$clients = $pdo->query("SELECT id, nom, prenom FROM clients ORDER BY nom ASC")->fetchAll();
$platforms = $pdo->query("SELECT * FROM platforms")->fetchAll();
$payment_methods = $pdo->query("SELECT * FROM modes_de_paiement")->fetchAll();

$is_modal = isset($_GET['modal']);
if (!$is_modal) {
    require_once 'header.php';
}
?>

<title>Créer une commande — NumériqueAide</title>

<!-- Подключаем стили Flatpickr -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<style>
    .card {
        background-color: transparent !important;
        border: none !important;
    }
    .card-body.custom-card-body {
        background-color: #e9ecef !important;
        border-radius: 0.375rem;
    }
    .flatpickr-calendar {
        z-index: 99999 !important;
    }
</style>

<div class="container mt-4 mb-5" style="max-width: 700px;">
    <?php if (!$is_modal): ?>
        <div class="d-flex align-items-center mb-4">
            <a href="commandes_list.php" class="btn btn-outline-secondary btn-sm me-3"><i class="bi bi-arrow-left"></i> Retour</a>
            <h3 class="mb-0 fw-bold"><i class="bi bi-plus-circle me-2"></i>Créer une commande</h3>
        </div>
    <?php endif; ?>

    <?php echo $message; ?>

    <div class="card shadow-sm">
        <div class="card-body custom-card-body p-4">
            <form action="add_commande.php" method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Date de commande <span class="text-danger">*</span></label>
                        <input type="text" id="date_commande" name="date_commande" class="form-control bg-white" placeholder="jj/mm/aaaa" value="<?= date('d/m/Y'); ?>" required onclick="initFP(this)" onfocus="initFP(this)">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Heure RDV</label>
                        <input type="text" name="rdv_time" id="rdv_time" class="form-control bg-white" placeholder="--:--" onclick="initTimeFP(this)" onfocus="initTimeFP(this)">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Client <span class="text-danger">*</span></label>
                    <select name="client_id" class="form-select bg-white" required>
                        <option value="">Sélectionner un client</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= $c['id']; ?>"><?= htmlspecialchars(trim($c['nom'] . ' ' . $c['prenom'])); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Plateforme</label>
                        <select name="platform_id" class="form-select bg-white">
                            <option value="">Aucune</option>
                            <?php foreach ($platforms as $p): ?>
                                <option value="<?= $p['id']; ?>"><?= htmlspecialchars($p['nom'] ?? $p['name'] ?? ''); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Mode de paiement</label>
                        <select name="payment_method_id" class="form-select bg-white">
                            <option value="">Aucun</option>
                            <?php foreach ($payment_methods as $pm): ?>
                                <option value="<?= $pm['id']; ?>"><?= htmlspecialchars($pm['nom'] ?? $pm['name'] ?? ''); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold">Montant (€) <span class="text-danger">*</span></label>
                        <input type="text" name="montant" class="form-control bg-white" placeholder="0.00" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold">Statut</label>
                        <select name="statut" class="form-select bg-white">
                            <option value="Prévu">Prévu</option>
                            <option value="En cours">En cours</option>
                            <option value="Payé">Payé</option>
                            <option value="Annulée">Annulée</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold">Date de paiement</label>
                        <input type="text" id="date_paiement" name="date_paiement" class="form-control bg-white" placeholder="jj/mm/aaaa" onclick="initFP(this)" onfocus="initFP(this)">
                    </div>
                </div>

                <!-- Блок налогов и накоплений -->
                <div class="card border p-3 mb-3 bg-white">
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <label class="form-label fw-semibold small">Calcul Taxe (Impôt 21.2%)</label>
                            <select name="calcul_impot" class="form-select form-select-sm">
                                <option value="1">Oui (21.2%)</option>
                                <option value="0">Non</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="form-label fw-semibold small">Calcul Cumul (Épargne 10%)</label>
                            <select name="calcul_epargne" class="form-select form-select-sm">
                                <option value="1">Oui (10%)</option>
                                <option value="0">Non</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mt-1">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="impot_paye" value="1" id="impotPayeCheck">
                                <label class="form-check-label small" for="impotPayeCheck">Taxe payée</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="epargne_paye" value="1" id="epargnePayeCheck">
                                <label class="form-check-label small" for="epargnePayeCheck">Cumul transféré</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Commentaire</label>
                    <textarea name="commentaire" class="form-control bg-white" rows="2"></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Notes</label>
                    <textarea name="notes" class="form-control bg-white" rows="2"></textarea>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <?php if (!$is_modal): ?>
                        <a href="commandes_list.php" class="btn btn-secondary">Annuler</a>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-circle me-1"></i> Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js"></script>
<script>
    function initFP(el) {
        if (typeof flatpickr !== 'undefined' && !el._flatpickr) {
            flatpickr(el, {
                dateFormat: "d/m/Y",
                allowInput: true,
                locale: "fr"
            }).open();
        } else if (el._flatpickr) {
            el._flatpickr.open();
        }
    }

    function initTimeFP(el) {
        if (typeof flatpickr !== 'undefined' && !el._flatpickr) {
            flatpickr(el, {
                enableTime: true,
                noCalendar: true,
                dateFormat: "H:i",
                time_24hr: true,
                allowInput: true,
                locale: "fr"
            }).open();
        } else if (el._flatpickr) {
            el._flatpickr.open();
        }
    }
</script>

<?php if (!$is_modal): ?>
    </body>
    </html>
<?php endif; ?>