<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

$message = '';

// 1. Сначала обрабатываем отправку формы (ДО подключения header.php!)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom        = trim($_POST['nom'] ?? '');
    $prenom     = trim($_POST['prenom'] ?? '');
    $adresse    = trim($_POST['adresse'] ?? '');
    $adresse_2  = trim($_POST['adresse_2'] ?? '');
    $telephone  = trim($_POST['telephone'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $notes      = trim($_POST['notes'] ?? '');
    $societe    = isset($_POST['societe']) ? 1 : 0;

    if (empty($nom) && empty($prenom)) {
        $message = '<div class="alert alert-danger">Veuillez renseigner au moins le Nom ou le Prénom !</div>';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO clients (nom, prenom, adresse, adresse_2, telephone, email, notes, societe)
                VALUES (:nom, :prenom, :adresse, :adresse_2, :telephone, :email, :notes, :societe)
            ");
            $stmt->execute([
                    ':nom'       => $nom,
                    ':prenom'    => $prenom,
                    ':adresse'   => $adresse,
                    ':adresse_2' => $adresse_2,
                    ':telephone' => $telephone,
                    ':email'     => $email,
                    ':notes'     => $notes,
                    ':societe'   => $societe
            ]);

            header("Location: add_client.php?success=1");
            exit;
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Erreur d\'enregistrement : ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// Показываем сообщение об успехе после редиректа
if (isset($_GET['success'])) {
    $message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-1"></i> <strong>Client ajouté avec succès !</strong> Le formulaire a été réinitialisé.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>';
}

// 2. Только теперь подключаем хедер с вашим неизменным дизайном
require_once 'header.php';
?>

<title>Ajouter un client — NumériqueAide</title>

<div class="container mt-4 mb-5" style="max-width: 650px;">
    <div class="card shadow-sm">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="bi bi-person-plus me-2"></i>Ajouter un client</h4>
            <a href="clients_list.php" class="btn btn-sm btn-outline-light">Liste des clients</a>
        </div>
        <div class="card-body">
            <?php echo $message; ?>

            <form action="" method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Nom</label>
                        <input type="text" name="nom" class="form-control" placeholder="ex: Dupont">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Prénom</label>
                        <input type="text" name="prenom" class="form-control" placeholder="ex: Jean">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Adresse</label>
                    <input type="text" name="adresse" class="form-control" placeholder="ex: 15 Avenue France, 75001 Paris">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Adresse complémentaire</label>
                    <textarea name="adresse_2" class="form-control" rows="2" placeholder="Bâtiment, appartement, étage..."></textarea>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Téléphone</label>
                        <input type="text" id="telephone" name="telephone" class="form-control" placeholder="+33 6 37 00 26 25">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Email</label>
                        <input type="email" name="email" class="form-control" placeholder="client@email.com">
                    </div>
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" name="societe" id="societe" class="form-check-input" value="1">
                    <label class="form-check-label fw-bold" for="societe">Entreprise / Société</label>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Notes</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Code d'accès, particularités..."></textarea>
                </div>

                <div class="d-grid gap-2 mt-4">
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="bi bi-check-circle me-1"></i> Enregistrer le client
                    </button>
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
        if (phoneInput) {
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