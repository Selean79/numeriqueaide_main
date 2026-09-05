<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

// Получаем дату из GET-запроса, по умолчанию сегодня
$input_date = $_GET['report_date'] ?? date('Y-m-d');
$export     = $_GET['export'] ?? '';

// Корректное преобразование формата даты из дд.мм.гггг (или гггг-мм-дд) в гггг-мм-дд для MySQL
$report_date = date('Y-m-d'); // дефолт
if (!empty($input_date)) {
    // Проверяем, если дата пришла в формате дд.мм.гггг
    $d = DateTime::createFromFormat('d.m.Y', $input_date);
    if ($d && $d->format('d.m.Y') === $input_date) {
        $report_date = $d->format('Y-m-d');
    } else {
        // Проверяем, вдруг она уже в формате гггг-мм-дд
        $d2 = DateTime::createFromFormat('Y-m-d', $input_date);
        if ($d2 && $d2->format('Y-m-d') === $input_date) {
            $report_date = $d2->format('Y-m-d');
        }
    }
}

// Détermination dynamique de la colonne du nom de la plateforme
$platCols = $pdo->query("SHOW COLUMNS FROM platforms")->fetchAll(PDO::FETCH_COLUMN);
$platColName = in_array('nom', $platCols) ? 'nom' : (in_array('name', $platCols) ? 'name' : $platCols[1]);

$sql = "
    SELECT 
        c.id_commande,
        c.date_commande,
        c.rdv_time,
        c.commentaire,
        c.notes AS order_notes,
        cl.nom,
        cl.prenom,
        cl.telephone,
        cl.adresse,
        cl.notes AS client_notes,
        p.`$platColName` AS platform_name
    FROM commandes c
    INNER JOIN clients cl ON c.client_id = cl.id
    LEFT JOIN platforms p ON c.platform_id = p.id
    WHERE c.date_commande = :report_date
    ORDER BY c.rdv_time ASC, c.id_commande ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':report_date' => $report_date]);
$daily_orders = $stmt->fetchAll();

if ($export === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="orders_' . $report_date . '.csv"');

    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF");

    fputcsv($output, ['ID', 'Heure', 'Client', 'Téléphone', 'Adresse', 'Plateforme', 'Description des travaux / Notes'], ';');

    foreach ($daily_orders as $row) {
        $clientName = trim(($row['nom'] ?? '') . ' ' . ($row['prenom'] ?? ''));
        $description = implode(' | ', array_filter([$row['commentaire'], $row['order_notes']]));
        $timeStr = !empty($row['rdv_time']) ? substr($row['rdv_time'], 0, 5) : '—';

        fputcsv($output, [
                '#' . $row['id_commande'],
                $timeStr,
                $clientName ?: '—',
                $row['telephone'] ?: '—',
                $row['adresse'] ?: '—',
                $row['platform_name'] ?: 'Privé',
                $description ?: '—'
        ], ';');
    }

    fclose($output);
    exit;
}

require_once 'header.php';
?>

<title>Commandes du <?= date('d.m.Y', strtotime($report_date)); ?> — NumériqueAide</title>

<!-- Подключение стилей Flatpickr -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<style>
    .rdv-time-badge {
        background-color: #e2e8f0;
        color: #1e293b;
        padding: 3px 8px;
        border-radius: 4px;
        font-weight: 600;
        display: inline-block;
        font-size: 0.9rem;
    }
</style>

<div class="container-fluid mt-4 px-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="d-flex align-items-center gap-2">
            <a href="reports.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Retour aux rapports</a>
            <h3 class="mb-0"><i class="bi bi-person-lines-fill me-2"></i>Commandes du jour ou de la date choisie</h3>
        </div>
        <div>
            <a href="?<?= http_build_query(array_merge($_GET, ['report_date' => date('d.m.Y', strtotime($report_date)), 'export' => 'csv'])); ?>" class="btn btn-outline-success me-2">
                <i class="bi bi-file-earmark-excel me-1"></i> Télécharger en Excel
            </a>
            <button onclick="window.print();" class="btn btn-outline-secondary">
                <i class="bi bi-printer me-1"></i> Imprimer
            </button>
        </div>
    </div>

    <!-- Селектор даты -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Sélectionner une date</label>
                    <input type="text" id="report_date_picker" name="report_date" class="form-control bg-white" value="<?= htmlspecialchars(date('d.m.Y', strtotime($report_date))); ?>" required readonly>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-calendar-check me-1"></i> Afficher pour cette date
                    </button>
                </div>
                <div class="col-md-2">
                    <a href="report_daily_clients.php" class="btn btn-outline-secondary w-100">Aujourd'hui</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Таблица результатов -->
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-3">
            <span class="fw-bold fs-6">
                <i class="bi bi-calendar3 me-2"></i>Liste des interventions du <?= date('d.m.Y', strtotime($report_date)); ?>
            </span>
            <span class="badge bg-primary fs-6">Commandes : <?= count($daily_orders); ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 w-100">
                    <thead class="table-light">
                    <tr>
                        <th class="text-nowrap" style="width: 80px;">ID</th>
                        <th class="text-nowrap" style="width: 100px;">Heure</th>
                        <th class="text-nowrap" style="width: 180px;">Client</th>
                        <th class="text-nowrap" style="width: 160px;">Téléphone</th>
                        <th class="text-nowrap" style="width: 320px;">Adresse</th>
                        <th class="text-nowrap" style="width: 130px;">Plateforme</th>
                        <th>Description des travaux / Notes</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($daily_orders)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="bi bi-info-circle me-1"></i> Aucune commande n'est planifiée pour la date sélectionnée.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($daily_orders as $row): ?>
                            <?php
                            $clientName = trim(($row['nom'] ?? '') . ' ' . ($row['prenom'] ?? ''));

                            $platformName = trim($row['platform_name'] ?? 'Privé');
                            $platBadgeClass = 'bg-danger';
                            if (strcasecmp($platformName, 'Yoojo') === 0) {
                                $platBadgeClass = 'bg-primary';
                            } elseif (strcasecmp($platformName, 'NeedHelp') === 0) {
                                $platBadgeClass = 'bg-success';
                            }
                            ?>
                            <tr>
                                <td class="fw-bold text-nowrap">#<?= $row['id_commande']; ?></td>
                                <td class="text-nowrap">
                                    <?php if (!empty($row['rdv_time'])): ?>
                                        <span class="rdv-time-badge">
                                            <i class="bi bi-clock me-1"></i><?= substr($row['rdv_time'], 0, 5); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-semibold text-nowrap">
                                    <?= !empty($clientName) ? htmlspecialchars($clientName) : '<span class="text-muted">—</span>'; ?>

                                    <?php if (!empty($row['client_notes'])): ?>
                                        <span class="text-danger ms-1" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= htmlspecialchars($row['client_notes']); ?>" style="cursor: pointer;">
                                            <i class="bi bi-exclamation-circle-fill"></i>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap">
                                    <?php if (!empty($row['telephone'])): ?>
                                        <a href="tel:<?= preg_replace('/[^\d+]/', '', $row['telephone']); ?>" class="text-decoration-none fw-semibold">
                                            <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($row['telephone']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap">
                                    <?php if (!empty($row['adresse'])): ?>
                                        <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($row['adresse']); ?>" target="_blank" class="text-decoration-none text-dark">
                                            <i class="bi bi-geo-alt text-danger me-1"></i><u><?= htmlspecialchars($row['adresse']); ?></u>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap"><span class="badge <?= $platBadgeClass; ?>"><?= htmlspecialchars($platformName); ?></span></td>
                                <td>
                                    <?php if (!empty($row['commentaire'])): ?>
                                        <div class="fw-semibold text-dark"><i class="bi bi-tools me-1 text-primary"></i><?= htmlspecialchars($row['commentaire']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($row['order_notes'])): ?>
                                        <div class="small text-muted"><i class="bi bi-journal-text me-1"></i><?= htmlspecialchars($row['order_notes']); ?></div>
                                    <?php endif; ?>
                                    <?php if (empty($row['commentaire']) && empty($row['order_notes'])): ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
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
<!-- Подключение Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Инициализация календаря
        flatpickr("#report_date_picker", {
            dateFormat: "d.m.Y",
            defaultDate: "<?= date('d.m.Y', strtotime($report_date)); ?>",
            locale: {
                firstDayOfWeek: 1,
                weekdays: {
                    shorthand: ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'],
                    longhand: ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi']
                },
                months: {
                    shorthand: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'],
                    longhand: ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre']
                },
            }
        });

        // Инициализация всплывающих подсказок (Tooltips) для заметок клиентов
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>
</body>
</html>