<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'header.php'; // Внутри header.php уже запускается session_start()

// Блокируем любые POST-запросы и попытки удаления для пользователей с типом User
if (isset($_SESSION['type']) && $_SESSION['type'] === 'User') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['delete_id'])) {
        header("Location: index.php?error=access_denied");
        exit;
    }
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$return_order = isset($_GET['return_order']) ? (int)$_GET['return_order'] : 0;

$client = null;
$error = '';

// Если передан ID, получаем данные клиента для редактирования
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $client = $stmt->fetch();
}

// Формируем URL для возврата обратно в список заказов на ту же строчку
$back_url = "commandes_list.php";
if ($return_order > 0) {
    $back_url .= "#order-" . $return_order;
}

// Обработка отправки формы (сохранение/обновление)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $adresse_2 = trim($_POST['adresse_2'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (!empty($nom)) {
        if ($id > 0) {
            // Обновление существующего клиента
            $stmt = $pdo->prepare("UPDATE clients SET nom = :nom, prenom = :prenom, telephone = :telephone, email = :email, adresse = :adresse, adresse_2 = :adresse_2, notes = :notes WHERE id = :id");
            $stmt->execute([
                    ':nom' => $nom,
                    ':prenom' => $prenom,
                    ':telephone' => $telephone,
                    ':email' => $email,
                    ':adresse' => $adresse,
                    ':adresse_2' => $adresse_2,
                    ':notes' => $notes,
                    ':id' => $id
            ]);
        } else {
            // Создание нового клиента
            $stmt = $pdo->prepare("INSERT INTO clients (nom, prenom, telephone, email, adresse, adresse_2, notes) VALUES (:nom, :prenom, :telephone, :email, :adresse, :adresse_2, :notes)");
            $stmt->execute([
                    ':nom' => $nom,
                    ':prenom' => $prenom,
                    ':telephone' => $telephone,
                    ':email' => $email,
                    ':adresse' => $adresse,
                    ':adresse_2' => $adresse_2,
                    ':notes' => $notes
            ]);
        }
        // Безопасный редирект обратно к заказу
        header("Location: " . $back_url);
        exit;
    } else {
        $error = "Le champ « Nom / Raison sociale » est obligatoire.";
    }
}
?>

<title><?= $id > 0 ? 'Modifier le client' : 'Ajouter un client'; ?> — NumériqueAide</title>

<style>
    body {
        background-color: #d3d1d1 !important;
    }
    .card-custom-bg {
        background-color: #ffffff;
        border-top: 4px solid #82e89e;
    }
</style>

<div class="container mt-4 mb-5" style="max-width: 600px;">
    <div class="d-flex align-items-center mb-4">
        <a href="<?= $back_url; ?>" class="btn btn-outline-secondary btn-sm me-3"><i class="bi bi-arrow-left"></i> Retour</a>
        <h3 class="mb-0 fw-bold"><i class="bi bi-person-badge me-2"></i><?= $id > 0 ? 'Modifier le client' : 'Ajouter un client'; ?></h3>
    </div>

    <?php if (isset($_SESSION['type']) && $_SESSION['type'] === 'User'): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> Accès refusé : les utilisateurs avec le profil "User" ne peuvent pas modifier ou ajouter des données.
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="card shadow card-custom-bg border-0">
        <div class="card-body p-4">
            <!-- Если User, то выводим поля заблокированными (disabled) либо вообще скрываем форму -->
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nom / Raison sociale <span class="text-danger">*</span></label>
                    <input type="text" name="nom" class="form-control" required value="<?= htmlspecialchars($client['nom'] ?? ''); ?>" <?= (isset($_SESSION['type']) && $_SESSION['type'] === 'User') ? 'disabled' : ''; ?>>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Prénom</label>
                    <input type="text" name="prenom" class="form-control" value="<?= htmlspecialchars($client['prenom'] ?? ''); ?>" <?= (isset($_SESSION['type']) && $_SESSION['type'] === 'User') ? 'disabled' : ''; ?>>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Téléphone</label>
                    <input type="text" id="telephone" name="telephone" class="form-control" placeholder="+33 6 37 00 26 25" value="<?= htmlspecialchars($client['telephone'] ?? ''); ?>" <?= (isset($_SESSION['type']) && $_SESSION['type'] === 'User') ? 'disabled' : ''; ?>>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($client['email'] ?? ''); ?>" <?= (isset($_SESSION['type']) && $_SESSION['type'] === 'User') ? 'disabled' : ''; ?>>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Adresse</label>
                    <textarea name="adresse" class="form-control" rows="2" <?= (isset($_SESSION['type']) && $_SESSION['type'] === 'User') ? 'disabled' : ''; ?>><?= htmlspecialchars($client['adresse'] ?? ''); ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Adresse complémentaire</label>
                    <textarea name="adresse_2" class="form-control" rows="2" <?= (isset($_SESSION['type']) && $_SESSION['type'] === 'User') ? 'disabled' : ''; ?>><?= htmlspecialchars($client['adresse_2'] ?? ''); ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Notes</label>
                    <textarea name="notes" class="form-control" rows="3" <?= (isset($_SESSION['type']) && $_SESSION['type'] === 'User') ? 'disabled' : ''; ?>><?= htmlspecialchars($client['notes'] ?? ''); ?></textarea>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="<?= $back_url; ?>" class="btn btn-secondary">Retour</a>
                    <?php if (!isset($_SESSION['type']) || $_SESSION['type'] !== 'User'): ?>
                        <button type="submit" class="btn btn-success"><i class="bi bi-save me-1"></i> Enregistrer</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Автоматическое форматирование ввода телефона в формат +33 X XX XX XX XX
    document.addEventListener('DOMContentLoaded', function() {
        const phoneInput = document.getElementById('telephone');
        if (phoneInput && !phoneInput.disabled) {
            phoneInput.addEventListener('input', function(e) {
                let x = e.target.value.replace(/\D/g, '').match(/(\d{0,2})(\d{0,1})(\d{0,2})(\d{0,2})(\d{0,2})(\d{0,2})/);

                if (!x) return;

                let formatted = '+33';
                if (x[2]) formatted += ' ' + x[2];
                if (x[3]) formatted += ' ' + x[3];
                if (x[4]) formatted += ' ' + x[4];
                if (x[5]) formatted += ' ' + x[5];
                if (x[6]) formatted += ' ' + x[6];

                if (e.target.value.trim() === '' || e.target.value === '+') {
                    e.target.value = '';
                } else {
                    e.target.value = formatted;
                }
            });
        }
    });
</script>
</body>
</html>