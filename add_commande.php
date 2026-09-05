<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

$error = '';

// 1. Traitement de la soumission du formulaire de création de commande
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_commande         = trim($_POST['id_commande'] ?? '');

    // Преобразование даты из формата ДД/ММ/ГГГГ в формат MySQL (ГГГГ-ММ-ДД)
    $raw_date            = trim($_POST['date_commande'] ?? '');
    $date_commande       = !empty($raw_date) ? date('Y-m-d', strtotime(str_replace('/', '-', $raw_date))) : '';

    // Время рандеву (rdv_time)
    $rdv_time            = trim($_POST['rdv_time'] ?? '');
    $rdv_time            = !empty($rdv_time) ? $rdv_time : null;

    $client_id           = !empty($_POST['client_id']) ? (int)$_POST['client_id'] : null;
    $platform_id         = !empty($_POST['platform_id']) ? (int)$_POST['platform_id'] : null;
    $payment_method_id   = !empty($_POST['payment_method_id']) ? (int)$_POST['payment_method_id'] : null;
    $facture_id          = !empty($_POST['facture_id']) ? (int)$_POST['facture_id'] : null;

    $montant_raw         = trim($_POST['montant'] ?? '');
    $montant             = str_replace(',', '.', $montant_raw);

    $statut              = trim($_POST['statut'] ?? 'Prévu');
    $notes               = trim($_POST['notes'] ?? '');
    $commentaire         = trim($_POST['commentaire'] ?? '');

    $calcul_impot        = isset($_POST['calcul_impot']) ? 1 : 0;
    $calcul_epargne      = isset($_POST['calcul_epargne']) ? 1 : 0;
    $impot_paye          = isset($_POST['impot_paye']) ? 1 : 0;
    $epargne_paye        = isset($_POST['epargne_paye']) ? 1 : 0;

    // Validation des champs obligatoires et du format numérique en PHP
    if (empty($client_id)) {
        $error = "Veuillez sélectionner un client.";
    } elseif ($montant_raw === '') {
        $error = "Veuillez remplir le montant de la commande.";
    } elseif (!is_numeric($montant)) {
        $error = "Le montant doit être un nombre valide (ex: 45.50).";
    } elseif (empty($platform_id)) {
        $error = "Veuillez sélectionner une plateforme / service.";
    } elseif (empty($payment_method_id)) {
        $error = "Veuillez sélectionner un mode de paiement.";
    } elseif (!empty($date_commande)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO commandes (
                    id_commande, date_commande, rdv_time, client_id, platform_id, payment_method_id, 
                    facture_id, montant, statut, notes, commentaire, 
                    calcul_impot, calcul_epargne, impot_paye, epargne_paye
                ) VALUES (
                    :id_commande, :date_commande, :rdv_time, :client_id, :platform_id, :payment_method_id, 
                    :facture_id, :montant, :statut, :notes, :commentaire, 
                    :calcul_impot, :calcul_epargne, :impot_paye, :epargne_paye
                )
            ");
            $stmt->execute([
                    ':id_commande'       => $id_commande,
                    ':date_commande'     => $date_commande,
                    ':rdv_time'          => $rdv_time,
                    ':client_id'         => $client_id,
                    ':platform_id'       => $platform_id,
                    ':payment_method_id' => $payment_method_id,
                    ':facture_id'        => $facture_id,
                    ':montant'           => $montant,
                    ':statut'            => $statut,
                    ':notes'             => $notes,
                    ':commentaire'       => $commentaire,
                    ':calcul_impot'      => $calcul_impot,
                    ':calcul_epargne'    => $calcul_epargne,
                    ':impot_paye'        => $impot_paye,
                    ':epargne_paye'      => $epargne_paye
            ]);

            header("Location: commandes_list.php");
            exit;
        } catch (PDOException $e) {
            $error = "Erreur de base de données : " . $e->getMessage();
        }
    }
}

// Génération du numéro au format CMD-ANNÉE-NUMÉRO
$current_year = date('Y');
$next_order_id = 'CMD-' . $current_year . '-1';
try {
    $stmtMax = $pdo->query("SELECT MAX(CAST(SUBSTRING_INDEX(id_commande, '-', -1) AS UNSIGNED)) FROM commandes WHERE id_commande LIKE 'CMD-%'");
    $max_id = $stmtMax->fetchColumn();
    if ($max_id) {
        $next_num = $max_id + 1;
        $next_order_id = 'CMD-' . $current_year . '-' . $next_num;
    }
} catch (Exception $e) {}

// Détermination dynamique des colonnes pour les tables
$platCols = $pdo->query("SHOW COLUMNS FROM platforms")->fetchAll(PDO::FETCH_COLUMN);
$platColName = in_array('nom', $platCols) ? 'nom' : 'name';

$pmCols = $pdo->query("SHOW COLUMNS FROM modes_de_paiement")->fetchAll(PDO::FETCH_COLUMN);
$pmColName = in_array('nom', $pmCols) ? 'nom' : 'name';

// Récupération des listes pour les menus déroulants
$clients = $pdo->query("SELECT id, nom, prenom FROM clients ORDER BY nom ASC")->fetchAll();
$platforms = $pdo->query("SELECT id, `$platColName` AS name FROM platforms ORDER BY name ASC")->fetchAll();
$payment_methods = $pdo->query("SELECT id, `$pmColName` AS name FROM modes_de_paiement ORDER BY name ASC")->fetchAll();
$factures = [];
try {
    $factures = $pdo->query("SELECT id, facture_number FROM factures ORDER BY id DESC")->fetchAll();
} catch (Exception $e) {}

require_once 'header.php';
?>

<title>Créer une commande — NumériqueAide</title>

<!-- Подключение стилей Flatpickr для красивого календаря -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<style>
    body {
        background-color: #e9ecef !important;
    }
    .card {
        border: 1px solid #ced4da !important;
        box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.15) !important;
    }
</style>

<div class="container mt-4 mb-5" style="max-width: 800px;">
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="card border-0">
        <div class="card-header bg-success text-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class="bi bi-cart-plus me-2"></i>Créer une nouvelle commande</h5>
            <a href="commandes_list.php" class="btn btn-sm btn-light text-success fw-semibold">Liste des commandes</a>
        </div>
        <div class="card-body p-4 bg-white">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold small text-muted">Numéro de commande (ID) *</label>
                        <input type="text" name="id_commande" class="form-control" value="<?= htmlspecialchars($next_order_id); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold small text-muted">Client *</label>
                        <select name="client_id" class="form-select" required
                                oninvalid="this.setCustomValidity('Veuillez sélectionner un client.')"
                                onchange="this.setCustomValidity('')">
                            <option value="">-- Sélectionner un client --</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= $client['id']; ?>"><?= htmlspecialchars(trim($client['nom'] . ' ' . $client['prenom'])); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <!-- Поле выбора даты заказа -->
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold small text-muted">Date de commande</label>
                        <input type="text" name="date_commande" id="date_commande" class="form-control bg-white" required value="<?= date('d/m/Y'); ?>">
                    </div>

                    <!-- Выпадающий список времени рандеву с шагом 30 минут -->
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold small text-muted">Heure du rendez-vous</label>
                        <select name="rdv_time" class="form-select bg-white">
                            <option value="">-- Choisir l'heure --</option>
                            <?php
                            $start = strtotime('08:00');
                            $end = strtotime('20:00');
                            for ($time = $start; $time <= $end; $time += 1800) {
                                $timeFormatted = date('H:i', $time);
                                echo '<option value="' . $timeFormatted . '">' . $timeFormatted . '</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold small text-muted">Montant (€) *</label>
                        <div class="input-group">
                            <input type="text" name="montant" class="form-control" placeholder="0.00" required
                                   pattern="[0-9]+([.,][0-9]+)?"
                                   oninvalid="this.setCustomValidity('Veuillez saisir un nombre valide (ex: 45.50).')"
                                   oninput="this.setCustomValidity('')">
                            <span class="input-group-text">€</span>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold small text-muted">Statut</label>
                        <select name="statut" class="form-select">
                            <option value="Prévu">Prévu</option>
                            <option value="En cours">En cours</option>
                            <option value="Payé">Payé</option>
                            <option value="Annulée">Annulée</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold small text-muted">Plateforme / Service *</label>
                        <select name="platform_id" class="form-select" required
                                oninvalid="this.setCustomValidity('Veuillez sélectionner une plateforme.')"
                                onchange="this.setCustomValidity('')">
                            <option value="">-- Sélectionner une plateforme --</option>
                            <?php foreach ($platforms as $plat): ?>
                                <option value="<?= $plat['id']; ?>"><?= htmlspecialchars($plat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold small text-muted">Mode de paiement *</label>
                        <select name="payment_method_id" class="form-select" required
                                oninvalid="this.setCustomValidity('Veuillez sélectionner un mode de paiement.')"
                                onchange="this.setCustomValidity('')">
                            <option value="">-- Sélectionner --</option>
                            <?php foreach ($payment_methods as $pm): ?>
                                <option value="<?= $pm['id']; ?>"><?= htmlspecialchars($pm['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label fw-semibold small text-muted">Facture associée</label>
                        <select name="facture_id" class="form-select">
                            <option value="">- Pas de facture -</option>
                            <?php foreach ($factures as $fac): ?>
                                <option value="<?= $fac['id']; ?>"><?= htmlspecialchars($fac['facture_number']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Bloc des paramètres de calculs -->
                <div class="row bg-light p-3 rounded mb-3 border">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <label class="form-label fw-semibold small text-muted mb-1">Calcul taxe (€)</label>
                        <div class="form-check form-switch mt-1">
                            <input class="form-check-input" type="checkbox" name="calcul_impot" id="calcul_impot" value="1" checked>
                            <label class="form-check-label small" for="calcul_impot">Calculer</label>
                        </div>
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" name="impot_paye" id="impot_paye" value="1">
                            <label class="form-check-label small text-success fw-semibold" for="impot_paye">Taxe payée</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-muted mb-1">Cumul / Épargne (€)</label>
                        <div class="form-check form-switch mt-1">
                            <input class="form-check-input" type="checkbox" name="calcul_epargne" id="calcul_epargne" value="1" checked>
                            <label class="form-check-label small" for="calcul_epargne">Calculer</label>
                        </div>
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" name="epargne_paye" id="epargne_paye" value="1">
                            <label class="form-check-label small text-success fw-semibold" for="epargne_paye">Épargne transférée</label>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold small text-muted">Commentaire de la commande</label>
                    <textarea name="commentaire" class="form-control" rows="2" placeholder="Brève description du travail..."></textarea>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold small text-muted">Notes internes</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Notes de service..."></textarea>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-success w-100 py-2 fw-semibold">
                        <i class="bi bi-check-lg me-1"></i> Enregistrer et créer la commande
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Подключение скриптов Flatpickr и французской локализации -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js"></script>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        flatpickr("#date_commande", {
            dateFormat: "d/m/Y", // Формат отображения: 27/08/2026
            locale: "fr",        // Французский язык интерфейса календаря
            allowInput: true     // Возможность ручного ввода с клавиатуры
        });
    });
</script>
</body>
</html>