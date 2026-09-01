<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'header.php';

// Suppression d'un utilisateur
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
        $stmt->execute([':id' => $delete_id]);
        header("Location: users_list.php?deleted=1");
        exit;
    } catch (PDOException $e) {
        $error_message = "Erreur lors de la suppression de l'utilisateur : " . $e->getMessage();
    }
}

// Chargement de la liste de tous les utilisateurs
try {
    $stmt = $pdo->query("SELECT * FROM users ORDER BY id ASC");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Erreur de chargement des utilisateurs : " . htmlspecialchars($e->getMessage()));
}
?>

<title>Gestion des utilisateurs — NumériqueAide</title>

<div class="container-fluid mt-4 px-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0"><i class="bi bi-person-badge me-2"></i>Liste des utilisateurs</h3>
        <a href="register.php" class="btn btn-success">
            <i class="bi bi-person-plus me-1"></i> Ajouter un utilisateur
        </a>
    </div>

    <!-- Notification de suppression réussie -->
    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            Utilisateur supprimé avec succès.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Affichage des erreurs -->
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger" role="alert">
            <?= htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <!-- Tableau des utilisateurs -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="min-width: 800px;">
                    <thead class="table-dark">
                    <tr>
                        <th style="width: 60px;" class="text-center">ID</th>
                        <th>Nom d'utilisateur (Username)</th>
                        <th style="width: 140px;" class="text-center">Type</th>
                        <th style="width: 140px;" class="text-center">Statut</th>
                        <th style="width: 180px;">Date de création</th>
                        <th style="width: 100px;" class="text-center">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">Aucun utilisateur trouvé</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <?php
                            // Style du badge pour le type d'utilisateur
                            $typeVal = $user['type'] ?? 'User';
                            $typeBadge = 'bg-secondary';
                            if ($typeVal === 'Admin') $typeBadge = 'bg-danger';
                            elseif ($typeVal === 'PowerUser') $typeBadge = 'bg-primary';

                            // Style du badge pour le statut
                            $statusVal = $user['status'] ?? 'Active';
                            $statusBadge = ($statusVal === 'Active') ? 'bg-success' : 'bg-warning text-dark';
                            ?>
                            <tr>
                                <td class="text-center fw-bold text-secondary"><?= $user['id']; ?></td>

                                <!-- Nom d'utilisateur -->
                                <td class="fw-bold">
                                    <i class="bi bi-person-circle me-1 text-primary"></i>
                                    <?= htmlspecialchars($user['username']); ?>
                                </td>

                                <!-- Type -->
                                <td class="text-center">
                                    <span class="badge <?= $typeBadge; ?>"><?= htmlspecialchars($typeVal); ?></span>
                                </td>

                                <!-- Statut -->
                                <td class="text-center">
                                    <span class="badge <?= $statusBadge; ?>"><?= htmlspecialchars($statusVal); ?></span>
                                </td>

                                <!-- Date de création -->
                                <td class="text-nowrap text-muted">
                                    <?= !empty($user['created_at']) ? date('d.m.Y H:i', strtotime($user['created_at'])) : '—'; ?>
                                </td>

                                <!-- Actions -->
                                <td class="text-center text-nowrap">
                                    <a href="users_list.php?delete_id=<?= $user['id']; ?>"
                                       class="btn btn-sm btn-outline-danger"
                                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?');"
                                       title="Supprimer">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>