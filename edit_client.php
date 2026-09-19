<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: clients_list.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom       = trim($_POST['nom'] ?? '');
    $prenom    = trim($_POST['prenom'] ?? '');
    $adresse   = trim($_POST['adresse'] ?? '');
    $adresse_2 = trim($_POST['adresse_2'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $societe   = isset($_POST['societe']) ? 1 : 0;
    $notes     = trim($_POST['notes'] ?? '');

    if (empty($nom)) {
        $error = "Le champ Nom est obligatoire.";
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE clients SET 
                    nom = :nom, 
                    prenom = :prenom, 
                    adresse = :adresse, 
                    adresse_2 = :adresse_2, 
                    telephone = :telephone, 
                    email = :email, 
                    societe = :societe, 
                    notes = :notes 
                WHERE id = :id
            ");
            $stmt->execute([
                ':nom'       => $nom,
                ':prenom'    => $prenom,
                ':adresse'   => $adresse,
                ':adresse_2' => $adresse_2,
                ':telephone' => $telephone,
                ':email'     => $email,
                ':societe'   => $societe,
                ':notes'     => $notes,
                ':id'        => $id
            ]);

            header("Location: clients_list.php?updated=1#client-" . $id);
            exit;
        } catch (PDOException $e) {
            $error = "Erreur de base de données : " . $e->getMessage();
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = :id");
$stmt->execute([':id' => $id]);
$client = $stmt->fetch();

if (!$client) {
    header("Location: clients_list.php");
    exit;
}

$is_modal = isset($_GET['modal']);
if (!$is_modal) {
    require_once 'header.php';
}
?>

<title>Modifier le client — NumériqueAide</title>

<div class="container mt-4 mb-5" style="max-width: 650px;">
    <?php if (!$is_modal): ?>
        <div class="d-flex align-items-center mb-4">
            <a href="clients_list.php" class="btn btn-outline-secondary btn-sm me-3"><i class="bi bi-arrow-left"></i> Retour</a>
            <h3 class="mb-0 fw-bold"><i class="bi bi-person-gear me-2"></i>Modifier le client</h3>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger" role="alert">
            <?= htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <form action="edit_client.php?id=<?= $id; ?>" method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Nom</label>
                        <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($client['nom']); ?>" placeholder="ex: Dupont" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Prénom</label>
                        <input type="text" name="prenom" class="form-control" value="<?= htmlspecialchars($client['prenom'] ?? ''); ?>" placeholder="ex: Jean">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Adresse</label>
                    <input type="text" name="adresse" class="form-control" value="<?= htmlspecialchars($client['adresse'] ?? ''); ?>" placeholder="ex: 15 Avenue France, 75001 Paris">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Adresse complémentaire</label>
                    <textarea name="adresse_2" class="form-control" rows="2" placeholder="Bâtiment, appartement, étage..."><?= htmlspecialchars($client['adresse_2'] ?? ''); ?></textarea>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Téléphone</label>
                        <input type="text" name="telephone" id="telephoneInput" class="form-control" value="<?= htmlspecialchars($client['telephone'] ?? '+33 '); ?>" placeholder="+33 6 37 00 26 25" maxlength="17" autocomplete="off">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($client['email'] ?? ''); ?>" placeholder="client@email.com">
                    </div>
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" name="societe" value="1" class="form-check-input" id="societeCheck" <?= !empty($client['societe']) ? 'checked' : ''; ?>>
                    <label class="form-check-label fw-semibold" for="societeCheck">Entreprise / Société</label>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Notes</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Code d'accès, particularités..."><?= htmlspecialchars($client['notes'] ?? ''); ?></textarea>
                </div>

                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary py-2 fw-semibold">
                        <i class="bi bi-save me-1"></i> Enregistrer les modifications
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const phoneInput = document.getElementById('telephoneInput');
    if (phoneInput) {
        phoneInput.addEventListener('focus', function() {
            if (!this.value.startsWith('+33')) {
                this.value = '+33 ' + this.value;
            }
        });

        // Автоматическая расстановка пробелов формата +33 X XX XX XX XX
        phoneInput.addEventListener('input', function(e) {
            let digits = this.value.replace(/\D/g, '');
            if (digits.startsWith('33')) {
                digits = digits.slice(2);
            }
            digits = digits.slice(0, 9);

            let formatted = '+33';
            if (digits.length > 0) {
                formatted += ' ' + digits.substring(0, 1);
            }
            if (digits.length > 1) {
                formatted += ' ' + digits.substring(1, 3);
            }
            if (digits.length > 3) {
                formatted += ' ' + digits.substring(3, 5);
            }
            if (digits.length > 5) {
                formatted += ' ' + digits.substring(5, 7);
            }
            if (digits.length > 7) {
                formatted += ' ' + digits.substring(7, 9);
            }
            this.value = formatted;
        });

        phoneInput.addEventListener('blur', function() {
            if (this.value.trim() === '+33' || this.value.trim() === '+33 ') {
                this.value = '';
            }
        });
    }
</script>

<?php if (!$is_modal): ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
<?php endif; ?>