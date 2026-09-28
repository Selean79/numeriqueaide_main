<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Обработка удаления пользователя (ДО подключения хедера!)
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];

    // Защита: нельзя удалить самого себя, если нужно (по желанию можно убрать)
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
        $stmt->execute([':id' => $delete_id]);

        header("Location: users_list.php?deleted=1");
        exit;
    } catch (PDOException $e) {
        $error_message = "Erreur lors de la suppression : " . $e->getMessage();
    }
}

// Сброс фильтра (если будет нужен)
if (isset($_GET['clear_filter'])) {
    header("Location: users_list.php");
    exit;
}

// 2. Теперь подключаем хедер (он же обновляет активность текущего пользователя)
require_once 'header.php';

// Сколько минут без действий считаем «en ligne»
$ONLINE_MINUTES = 5;

// Загружаем список пользователей
try {
    $stmt = $pdo->query("
        SELECT
            u.*,
            (u.last_activity IS NOT NULL AND u.last_activity >= NOW() - INTERVAL $ONLINE_MINUTES MINUTE) AS is_online,
            TIMESTAMPDIFF(MINUTE, u.last_activity, NOW()) AS minutes_ago
        FROM users u
        ORDER BY u.id DESC
    ");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    // колонки last_activity ещё нет — показываем список без индикатора
    try {
        $users = $pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll();
        $activity_missing = true;
    } catch (PDOException $e2) {
        die("Erreur de chargement : " . htmlspecialchars($e2->getMessage()));
    }
}

// Текст «vu il y a …»
function lastSeenLabel($minutes): string {
    if ($minutes === null) return 'jamais connecté';
    $minutes = (int)$minutes;
    if ($minutes < 60)   return 'vu il y a ' . max(1, $minutes) . ' min';
    if ($minutes < 1440) return 'vu il y a ' . floor($minutes / 60) . ' h';
    return 'vu il y a ' . floor($minutes / 1440) . ' j';
}
?>

<title>Liste des utilisateurs — NumériqueAide</title>

<style>
    .online-dot {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background-color: #dc3545;
        box-shadow: 0 0 0 0 rgba(220, 53, 69, .6);
        animation: onlinePulse 1.8s infinite;
        vertical-align: middle;
    }
    .offline-dot {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background-color: #d1d5db;
        vertical-align: middle;
    }
    @keyframes onlinePulse {
        0%   { box-shadow: 0 0 0 0 rgba(220, 53, 69, .6); }
        70%  { box-shadow: 0 0 0 7px rgba(220, 53, 69, 0); }
        100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
    }
    .last-seen { font-size: .78rem; color: #9ca3af; font-weight: 400; }
    .last-seen.is-online { color: #dc3545; font-weight: 600; }

    /* =========================================================
       МОБИЛЬНАЯ ВЕРСИЯ (экраны до 768px): таблица → карточки
       ========================================================= */
    @media (max-width: 767.98px) {
        h3 { font-size: 1.2rem; }

        .users-card { background: transparent !important; box-shadow: none !important; border: none !important; }
        .users-card .card-body { padding: 0 !important; }

        .users-table thead { display: none; }
        .users-table,
        .users-table tbody { display: block; width: 100%; background: transparent; }

        .users-table tr.user-row {
            display: grid;
            grid-template-columns: auto auto 1fr;
            grid-template-areas:
                "name name   actions"
                "full full   full"
                "type status .";
            gap: 4px 10px;
            position: relative;
            margin-bottom: 12px;
            padding: 12px 14px;
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,.12);
        }
        .users-table tr.user-row > td {
            display: block;
            padding: 0;
            border: none;
            background: transparent;
            text-align: left !important;
        }
        .users-table tr.user-row > td.cell-id      { display: none; }
        .users-table tr.user-row > td.cell-name    { grid-area: name; font-size: 1.1rem; }
        .users-table tr.user-row > td.cell-full    { grid-area: full; color: #4b5563; }
        .users-table tr.user-row > td.cell-type    { grid-area: type; margin-top: 4px; }
        .users-table tr.user-row > td.cell-status  { grid-area: status; margin-top: 4px; }
        .users-table tr.user-row > td.cell-actions { grid-area: actions; align-self: start; justify-self: end; }
        .users-table tr.user-row > td.cell-actions .btn { padding: 6px 12px; }

        .users-table tr.row-empty,
        .users-table tr.row-empty > td { display: block; background: #fff; border-radius: 10px; }
    }
</style>

<div class="container mt-3 mt-md-4 px-2 px-md-3" style="max-width: 900px;">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
        <h3 class="mb-0"><i class="bi bi-people me-2"></i>Liste des utilisateurs</h3>
        <a href="register_user.php" class="btn btn-success">
            <i class="bi bi-person-plus me-1"></i><span class="d-none d-sm-inline"> Ajouter un utilisateur</span><span class="d-sm-none"> Ajouter</span>
        </a>
    </div>

    <?php if (!empty($activity_missing)): ?>
        <div class="alert alert-info small">
            Pour afficher qui est en ligne, ajoutez la colonne <code>last_activity</code> à la table <code>users</code>.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['added'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Utilisateur ajouté avec succès !
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Utilisateur modifié avec succès !
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            Utilisateur supprimé avec succès !
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm users-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 users-table">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 60px;" class="text-center">#</th>
                            <th>Username</th>
                            <th>Nom &amp; Prénom</th>
                            <th>Type</th>
                            <th>Statut</th>
                            <th style="width: 120px;" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr class="row-empty">
                                <td colspan="6" class="text-center py-4 text-muted">Aucun utilisateur trouvé</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <?php
                                $hasActivity = array_key_exists('is_online', $user);
                                $isOnline = $hasActivity && (int)$user['is_online'] === 1;
                                ?>
                                <tr class="user-row">
                                    <td class="text-center fw-bold text-secondary cell-id"><?= (int)$user['id']; ?></td>
                                    <td class="fw-bold cell-name">
                                        <?php if ($hasActivity): ?>
                                            <span class="<?= $isOnline ? 'online-dot' : 'offline-dot'; ?> me-2" title="<?= $isOnline ? 'En ligne' : 'Hors ligne'; ?>"></span>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($user['username']); ?>
                                        <?php if ($hasActivity): ?>
                                            <div class="last-seen <?= $isOnline ? 'is-online' : ''; ?>" style="padding-left: 18px;">
                                                <?= $isOnline ? 'en ligne' : lastSeenLabel($user['minutes_ago']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="cell-full"><?= htmlspecialchars(trim(($user['nom'] ?? '') . ' ' . ($user['prenom'] ?? ''))); ?></td>
                                    <td class="cell-type">
                                        <?php
                                            $badgeClass = 'bg-secondary';
                                            if ($user['type'] === 'Admin') $badgeClass = 'bg-danger';
                                            elseif ($user['type'] === 'PowerUser') $badgeClass = 'bg-primary';
                                        ?>
                                        <span class="badge <?= $badgeClass; ?>"><?= htmlspecialchars($user['type']); ?></span>
                                    </td>
                                    <td class="cell-status">
                                        <?php if (($user['status'] ?? 'Active') === 'Active'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center text-nowrap cell-actions">
                                        <a href="user_edit.php?id=<?= (int)$user['id']; ?>" class="btn btn-sm btn-outline-primary me-1" title="Modifier">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="users_list.php?delete_id=<?= (int)$user['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?');" title="Supprimer">
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
<script>
    setTimeout(function () {
        const alerts = document.querySelectorAll('.alert-dismissible');
        alerts.forEach(function (alertElement) {
            const alert = new bootstrap.Alert(alertElement);
            alert.close();
        });
    }, 5000);

    // Обновляем страницу раз в минуту, чтобы индикатор «en ligne» был актуальным
    setTimeout(function () {
        window.location.href = 'users_list.php';
    }, 60000);
</script>
</body>
</html>