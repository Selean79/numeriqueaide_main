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
        cl.adresse_2,
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
        $fullAddress = implode(', ', array_filter([
                trim($row['adresse'] ?? ''),
                trim(preg_replace('/\s+/', ' ', $row['adresse_2'] ?? ''))
        ]));

        fputcsv($output, [
                '#' . $row['id_commande'],
                $timeStr,
                $clientName ?: '—',
                $row['telephone'] ?: '—',
                $fullAddress ?: '—',
                $row['platform_name'] ?: 'Privé',
                $description ?: '—'
        ], ';');
    }

    fclose($output);
    exit;
}

// Предыдущий / следующий день (для быстрых кнопок)
$prev_date = date('d.m.Y', strtotime($report_date . ' -1 day'));
$next_date = date('d.m.Y', strtotime($report_date . ' +1 day'));

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

    /* Фирменный зелёный цвет — поменяйте здесь, и он изменится везде на странице */
    :root {
        --brand-green: #1f9d55;
        --brand-green-text: #ffffff;
    }
    .brand-header {
        background-color: var(--brand-green) !important;
        color: var(--brand-green-text) !important;
        border-bottom: none;
    }
    .brand-badge {
        background-color: #ffffff !important;
        color: var(--brand-green) !important;
    }
    @media print {
        .brand-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }

    /* Дополнительный адрес (здание, этаж, код...) */
    .address-extra {
        padding-left: 1.3rem;
        white-space: normal;
        line-height: 1.3;
    }
    @media (max-width: 767.98px) {
        .address-extra { padding-left: 0; }
    }

    /* Заметка клиента текстом — только на телефоне (там нет наведения мыши) */
    .client-note-mobile { display: none; }

    /* =========================================================
       МОБИЛЬНАЯ ВЕРСИЯ (экраны до 768px): таблица → карточки
       ========================================================= */
    @media (max-width: 767.98px) {
        h3 { font-size: 1.15rem; }

        .daily-table thead { display: none; }
        .daily-table,
        .daily-table tbody { display: block; width: 100%; }

        .daily-table tr.daily-row {
            display: block;
            position: relative;
            margin: 10px;
            background: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,.08);
            overflow: hidden;
        }
        .daily-table tr.daily-row > td {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 6px 12px;
            border: none;
            white-space: normal !important;
            background: transparent;
        }
        .daily-table tr.daily-row > td[data-label]::before {
            content: attr(data-label);
            font-weight: 600;
            color: #6b7280;
            min-width: 85px;
            flex-shrink: 0;
        }

        /* Верх карточки: номер заказа слева, время справа */
        .daily-table tr.daily-row > td.cell-id {
            font-size: 1.05rem;
            padding: 10px 12px 8px;
            background: #f1f5f9;
            border-bottom: 1px solid #e2e8f0;
        }
        .daily-table tr.daily-row > td.cell-time {
            position: absolute;
            top: 6px;
            right: 6px;
            padding: 0;
        }

        /* Клиент крупнее */
        .daily-table tr.daily-row > td.cell-client { font-size: 1.05rem; }

        /* Телефон — большая кнопка для звонка */
        .daily-table tr.daily-row > td.cell-phone a {
            display: inline-block;
            padding: 4px 10px;
            border: 1px solid #0d6efd;
            border-radius: 6px;
        }

        /* Описание работ — подпись сверху */
        .daily-table tr.daily-row > td.cell-desc {
            flex-direction: column;
            gap: 2px;
            border-top: 1px solid #f1f5f9;
            padding-bottom: 10px;
        }

        .client-note-mobile { display: block; }
        .client-note-icon { display: none; }

        .daily-table tr.row-empty, .daily-table tr.row-empty > td { display: block; }
    }

    /* Печать — всегда таблицей */
    @media print {
        .no-print { display: none !important; }
    }
</style>

<div class="container-fluid mt-3 mt-md-4 px-2 px-md-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
        <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center gap-2">
            <a href="reports.php" class="btn btn-outline-secondary btn-sm no-print"><i class="bi bi-arrow-left"></i> Retour aux rapports</a>
            <h3 class="mb-0"><i class="bi bi-person-lines-fill me-2"></i>Commandes du jour ou de la date choisie</h3>
        </div>
        <div class="d-flex gap-2 no-print">
            <a href="?<?= http_build_query(array_merge($_GET, ['report_date' => date('d.m.Y', strtotime($report_date)), 'export' => 'csv'])); ?>" class="btn btn-outline-success flex-fill">
                <i class="bi bi-file-earmark-excel me-1"></i> <span class="d-none d-sm-inline">Télécharger en </span>Excel
            </a>
            <button onclick="window.print();" class="btn btn-outline-secondary flex-fill">
                <i class="bi bi-printer me-1"></i> Imprimer
            </button>
        </div>
    </div>

    <!-- Селектор даты -->
    <div class="card shadow-sm mb-3 mb-md-4 no-print">
        <div class="card-body p-2 p-md-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold">Sélectionner une date</label>
                    <div class="input-group">
                        <a href="?report_date=<?= $prev_date; ?>" class="btn btn-outline-secondary" title="Jour précédent"><i class="bi bi-chevron-left"></i></a>
                        <input type="text" id="report_date_picker" name="report_date" class="form-control bg-white text-center" value="<?= htmlspecialchars(date('d.m.Y', strtotime($report_date))); ?>" required readonly>
                        <a href="?report_date=<?= $next_date; ?>" class="btn btn-outline-secondary" title="Jour suivant"><i class="bi bi-chevron-right"></i></a>
                    </div>
                </div>
                <div class="col-8 col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-calendar-check me-1"></i> Afficher<span class="d-none d-sm-inline"> pour cette date</span>
                    </button>
                </div>
                <div class="col-4 col-md-2">
                    <a href="report_daily_clients.php" class="btn btn-outline-secondary w-100">Aujourd'hui</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Таблица результатов -->
    <div class="card shadow-sm">
        <div class="card-header brand-header d-flex justify-content-between align-items-center py-2 py-md-3">
            <span class="fw-bold fs-6">
                <i class="bi bi-calendar3 me-2"></i><span class="d-none d-sm-inline">Liste des interventions du </span><?= date('d.m.Y', strtotime($report_date)); ?>
            </span>
            <span class="badge brand-badge fs-6">Commandes : <?= count($daily_orders); ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 w-100 daily-table">
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
                        <tr class="row-empty">
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
                            <tr class="daily-row">
                                <td class="fw-bold text-nowrap cell-id">#<?= htmlspecialchars($row['id_commande']); ?></td>
                                <td class="text-nowrap cell-time">
                                    <?php if (!empty($row['rdv_time'])): ?>
                                        <span class="rdv-time-badge">
                                            <i class="bi bi-clock me-1"></i><?= substr($row['rdv_time'], 0, 5); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-semibold text-nowrap cell-client" data-label="Client">
                                    <div>
                                        <?= !empty($clientName) ? htmlspecialchars($clientName) : '<span class="text-muted">—</span>'; ?>

                                        <?php if (!empty($row['client_notes'])): ?>
                                            <span class="text-danger ms-1 client-note-icon" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= htmlspecialchars($row['client_notes']); ?>" style="cursor: pointer;">
                                                <i class="bi bi-exclamation-circle-fill"></i>
                                            </span>
                                            <div class="client-note-mobile small text-danger fw-normal mt-1">
                                                <i class="bi bi-exclamation-circle-fill me-1"></i><?= htmlspecialchars($row['client_notes']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-nowrap cell-phone" data-label="Téléphone">
                                    <?php if (!empty($row['telephone'])): ?>
                                        <a href="tel:<?= preg_replace('/[^\d+]/', '', $row['telephone']); ?>" class="text-decoration-none fw-semibold">
                                            <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($row['telephone']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Adresse">
                                    <div>
                                        <?php if (!empty($row['adresse'])): ?>
                                            <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($row['adresse']); ?>" target="_blank" class="text-decoration-none text-dark text-nowrap">
                                                <i class="bi bi-geo-alt text-danger me-1"></i><u><?= htmlspecialchars($row['adresse']); ?></u>
                                            </a>
                                        <?php endif; ?>

                                        <?php if (!empty(trim($row['adresse_2'] ?? ''))): ?>
                                            <div class="address-extra small text-muted mt-1">
                                                <i class="bi bi-building me-1"></i><?= nl2br(htmlspecialchars(trim($row['adresse_2']))); ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (empty($row['adresse']) && empty(trim($row['adresse_2'] ?? ''))): ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-nowrap" data-label="Plateforme"><span class="badge <?= $platBadgeClass; ?>"><?= htmlspecialchars($platformName); ?></span></td>
                                <td class="cell-desc">
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
        // Инициализация календаря (при выборе даты страница обновляется сразу)
        flatpickr("#report_date_picker", {
            dateFormat: "d.m.Y",
            defaultDate: "<?= date('d.m.Y', strtotime($report_date)); ?>",
            disableMobile: true,
            onChange: function(selectedDates, dateStr, instance) {
                instance.input.form.submit();
            },
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
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>
</body>
</html>