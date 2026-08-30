<?php


// Si la fonction n'existe pas, nous la déclarons ici pour éviter une erreur
if (!function_exists('renderPlatformBadge')) {
    function renderPlatformBadge($name)
    {
        return htmlspecialchars($name);
    }
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'header.php';

$message = '';

// 1. NOTIFICATIONS
if (isset($_GET['updated'])) {
    $message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    <strong>Succès !</strong> Les données de la plateforme ont été enregistrées.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>';
}

// 2. TRAITEMENT DE LA SUPPRESSION D'UNE PLATEFORME
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM platforms WHERE id = :id");
        $stmt->execute([':id' => $delete_id]);
        $message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        Plateforme supprimée avec succès !
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>';
    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger">Erreur de suppression : ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// 3. RECHERCHE ET TRI
$search = trim($_GET['search'] ?? '');
$sort_column = $_GET['sort'] ?? 'name';
$allowed_columns = [
        'id' => 'id',
        'name' => 'name',
        'impot' => 'default_impot_rate',
        'epargne' => 'default_epargne_rate'
];

if (!array_key_exists($sort_column, $allowed_columns)) {
    $sort_column = 'name';
}

$sort_order = strtoupper($_GET['order'] ?? 'ASC');
if (!in_array($sort_order, ['ASC', 'DESC'])) {
    $sort_order = 'ASC';
}

function getSortUrl($col, $current_col, $current_order) {
    $queryParams = $_GET;
    $queryParams['sort'] = $col;
    $queryParams['order'] = ($col === $current_col && $current_order === 'ASC') ? 'DESC' : 'ASC';
    return basename($_SERVER['PHP_SELF']) . '?' . http_build_query($queryParams);
}

// 4. REQUÊTE SQL DYNAMIQUE
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "LOWER(name) LIKE :search";
    $params[':search'] = "%" . mb_strtolower($search, 'UTF-8') . "%";
}

$sql = "SELECT * FROM platforms";
if (count($conditions) > 0) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$db_sort_col = $allowed_columns[$sort_column];
$sql .= " ORDER BY {$db_sort_col} {$sort_order}";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $platforms = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Erreur d'exécution de la requête : " . htmlspecialchars($e->getMessage()));
}
?>

<title>Liste des plateformes — NumériqueAide</title>

<div class="container mt-4 mb-5" style="max-width: 900px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-diagram-3 me-2"></i>Liste des plateformes</h2>
        <a href="add_platform.php" class="btn btn-success">
            <i class="bi bi-plus-circle me-1"></i> Ajouter une plateforme
        </a>
    </div>

    <?= $message; ?>

    <!-- Recherche -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <input type="hidden" name="sort" value="<?= htmlspecialchars($sort_column); ?>">
                <input type="hidden" name="order" value="<?= htmlspecialchars($sort_order); ?>">

                <div class="col-md-9">
                    <input type="text" name="search" class="form-control"
                           placeholder="Rechercher par nom de plateforme..."
                           value="<?= htmlspecialchars($search); ?>">
                </div>

                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Rechercher
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="<?= basename($_SERVER['PHP_SELF']); ?>" class="btn btn-outline-secondary" title="Réinitialiser">
                            <i class="bi bi-x-circle"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Tableau des plateformes -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm table-custom-sm align-middle mb-0">
                    <thead class="table-dark">
                    <tr>
                        <th style="width: 80px;">
                            <a href="<?= getSortUrl('id', $sort_column, $sort_order); ?>" class="text-white text-decoration-none">
                                ID
                                <?php if ($sort_column === 'id'): ?>
                                    <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down'; ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?= getSortUrl('name', $sort_column, $sort_order); ?>" class="text-white text-decoration-none">
                                Nom de la plateforme
                                <?php if ($sort_column === 'name'): ?>
                                    <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down'; ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?= getSortUrl('impot', $sort_column, $sort_order); ?>" class="text-white text-decoration-none">
                                Taux de taxe (URSSAF)
                                <?php if ($sort_column === 'impot'): ?>
                                    <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down'; ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?= getSortUrl('epargne', $sort_column, $sort_order); ?>" class="text-white text-decoration-none">
                                Taux de cotisation
                                <?php if ($sort_column === 'epargne'): ?>
                                    <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down'; ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th class="text-end" style="width: 120px;">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (count($platforms) > 0): ?>
                        <?php foreach ($platforms as $p): ?>
                            <tr>
                                <td><strong><?= $p['id']; ?></strong></td>
                                <td><?= renderPlatformBadge($p['name']); ?></td>
                                <td><span class="badge bg-warning text-dark"><?= number_format($p['default_impot_rate'] ?? 0, 2, ',', ' '); ?> %</span></td>
                                <td><span class="badge bg-info text-white"><?= number_format($p['default_epargne_rate'] ?? 0, 2, ',', ' '); ?> %</span></td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="edit_platform.php?id=<?= $p['id']; ?>" class="btn btn-outline-primary" title="Modifier">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="<?= basename($_SERVER['PHP_SELF']); ?>?delete_id=<?= $p['id']; ?>"
                                           class="btn btn-outline-danger"
                                           title="Supprimer"
                                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer la plateforme « <?= htmlspecialchars($p['name']); ?> » ?');">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">Aucune plateforme trouvée.</td>
                        </tr>
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