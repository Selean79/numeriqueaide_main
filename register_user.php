<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'header.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $nom      = trim($_POST['nom'] ?? '');
    $prenom   = trim($_POST['prenom'] ?? '');
    $password = $_POST['password'] ?? '';
    $type     = $_POST['type'] ?? 'User';
    $status   = $_POST['status'] ?? 'Active';

    if (empty($username) || empty($password)) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");
            $stmt->execute([':username' => $username]);

            if ($stmt->fetch()) {
                $error = "Ce nom d'utilisateur est déjà pris.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $insertStmt = $pdo->prepare("INSERT INTO users (username, nom, prenom, password, type, status) VALUES (:username, :nom, :prenom, :password, :type, :status)");
                $insertStmt->execute([
                    ':username' => $username,
                    ':nom'      => $nom,
                    ':prenom'   => $prenom,
                    ':password' => $hashed_password,
                    ':type'     => $type,
                    ':status'   => $status
                ]);

                header("Location: users_list.php?added=1");
                exit;
            }
        } catch (PDOException $e) {
            $error = "Erreur de base de données : " . $e->getMessage();
        }
    }
}
?>

<title>Ajouter un utilisateur — NumériqueAide</title>

<div class="container mt-4" style="max-width: 600px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0"><i class="bi bi-person-plus me-2"></i>Ajouter un utilisateur</h3>
        <a href="users_list.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Retour à la liste
        </a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" autocomplete="off">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Username</label>
                    <input type="text" name="username" class="form-control" autocomplete="off" required>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Nom</label>
                        <input type="text" name="nom" class="form-control" autocomplete="off">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Prénom</label>
                        <input type="text" name="prenom" class="form-control" autocomplete="off">
                    </div>
                </div>

                <!-- Поле пароля с кнопкой-глазиком и защитой от автозаполнения -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">Mot de passe</label>
                    <div class="input-group">
                        <input type="password" name="password" id="passwordField" class="form-control" autocomplete="new-password" required>
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                            <i class="bi bi-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Type d'utilisateur</label>
                    <select name="type" class="form-select">
                        <option value="User">User</option>
                        <option value="PowerUser">PowerUser</option>
                        <option value="Admin">Admin</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">Statut</label>
                    <select name="status" class="form-select">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-lg me-1"></i> Enregistrer l'utilisateur
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Скрипт для переключения видимости пароля -->
<script>
    document.getElementById('togglePassword').addEventListener('click', function () {
        const passwordField = document.getElementById('passwordField');
        const toggleIcon = document.getElementById('toggleIcon');

        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            toggleIcon.classList.remove('bi-eye');
            toggleIcon.classList.add('bi-eye-slash');
        } else {
            passwordField.type = 'password';
            toggleIcon.classList.remove('bi-eye-slash');
            toggleIcon.classList.add('bi-eye');
        }
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>