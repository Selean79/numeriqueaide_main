<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

$message = '';

// 1. Сначала обрабатываем отправку формы (ДО подключения header.php!)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom       = trim($_POST['nom'] ?? '');
    $prenom    = trim($_POST['prenom'] ?? '');
    $adresse   = trim($_POST['adresse'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $notes     = trim($_POST['notes'] ?? '');
    $societe   = isset($_POST['societe']) ? 1 : 0;

    if (empty($nom) && empty($prenom)) {
        $message = '<div class="alert alert-danger">Заполните хотя бы Имя или Фамилию!</div>';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO clients (nom, prenom, adresse, telephone, email, notes, societe)
                VALUES (:nom, :prenom, :adresse, :telephone, :email, :notes, :societe)
            ");
            $stmt->execute([
                    ':nom'       => $nom,
                    ':prenom'    => $prenom,
                    ':adresse'   => $adresse,
                    ':telephone' => $telephone,
                    ':email'     => $email,
                    ':notes'     => $notes,
                    ':societe'   => $societe
            ]);

            header("Location: add_client.php?success=1");
            exit;
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Ошибка сохранения: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// Показываем сообщение об успехе после редиректа
if (isset($_GET['success'])) {
    $message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-1"></i> <strong>Клиент успешно добавлен!</strong> Форма очищена для следующего ввода.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>';
}

// 2. Только теперь подключаем хедер с вашим неизменным дизайном
require_once 'header.php';
?>

<title>Добавить клиента — NumériqueAide</title>

<div class="container mt-4 mb-5" style="max-width: 650px;">
    <div class="card shadow-sm">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="bi bi-person-plus me-2"></i>Добавить клиента</h4>
            <a href="clients_list.php" class="btn btn-sm btn-outline-light">К списку клиентов</a>
        </div>
        <div class="card-body">
            <?php echo $message; ?>

            <form action="" method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Фамилия (Nom)</label>
                        <input type="text" name="nom" class="form-control" placeholder="например: Dupont">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Имя (Prénom)</label>
                        <input type="text" name="prenom" class="form-control" placeholder="например: Jean">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Адрес (Adresse)</label>
                    <input type="text" name="adresse" class="form-control" placeholder="например: 15 Avenue France, 75001 Paris">
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Телефон (Téléphone)</label>
                        <input type="text" name="telephone" class="form-control" placeholder="+33 6 12 34 56 78">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Email</label>
                        <input type="email" name="email" class="form-control" placeholder="client@email.com">
                    </div>
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" name="societe" id="societe" class="form-check-input" value="1">
                    <label class="form-check-label fw-bold" for="societe">Юридическое лицо / Компания (Société)</label>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Заметки (Notes)</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Код домофона, этаж, особенности..."></textarea>
                </div>

                <div class="d-grid gap-2 mt-4">
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="bi bi-check-circle me-1"></i> Сохранить клиента
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>