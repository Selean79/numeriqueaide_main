<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'header.php';
?>

<title>Центр отчетов — NumériqueAide</title>

<div class="container mt-4 mb-5" style="max-width: 1000px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0"><i class="bi bi-file-earmark-bar-graph me-2"></i>Отчеты и аналитика</h3>
        <span class="text-muted"><i class="bi bi-calendar3 me-1"></i>Сегодня: <?= date('d.m.Y'); ?></span>
    </div>

    <div class="row g-4">
        <!-- Отчет 1: Заказы / Клиенты на текущий день -->
        <div class="col-md-6">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-3 me-3">
                                <i class="bi bi-person-lines-fill fs-3"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-1 fw-bold">Заказы на текущий день или выбранный день</h5>
                                <span class="badge bg-success">Ежедневный</span>
                            </div>
                        </div>
                        <p class="card-text text-muted">
                            Выводит компактный список клиентов, их адресов, контактов, используемых платформ и подробное описание работ на сегодня или выбранную дату.
                        </p>
                    </div>
                    <div class="mt-3">
                        <a href="report_daily_clients.php" class="btn btn-primary w-100">
                            <i class="bi bi-arrow-right-circle me-1"></i> Открыть отчет
                        </a>
                    </div>
                </div>
            </div>
        </div>

<!--         Отчет 2: Финансовый отчет по интервалу -->
<!--        <div class="col-md-6">-->
<!--            <div class="card h-100 shadow-sm border-0">-->
<!--                <div class="card-body p-4 d-flex flex-column justify-content-between">-->
<!--                    <div>-->
<!--                        <div class="d-flex align-items-center mb-3">-->
<!--                            <div class="bg-success bg-opacity-10 text-success p-3 rounded-3 me-3">-->
<!--                                <i class="bi bi-calculator fs-3"></i>-->
<!--                            </div>-->
<!--                            <div>-->
<!--                                <h5 class="card-title mb-1 fw-bold">Финансовый отчет за период</h5>-->
<!--                                <span class="badge bg-info text-dark">Период / Месяц</span>-->
<!--                            </div>-->
<!--                        </div>-->
<!--                        <p class="card-text text-muted">-->
<!--                            Сводка доходов, отчислений и налогов URSSAF (21.2%) за любой выбранный период времени с возможностью выгрузки в Excel.-->
<!--                        </p>-->
<!--                    </div>-->
<!--                    <div class="mt-3">-->
<!--                        <a href="report_commandes.php" class="btn btn-outline-success w-100">-->
<!--                            <i class="bi bi-arrow-right-circle me-1"></i> Открыть отчет-->
<!--                        </a>-->
<!--                    </div>-->
<!--                </div>-->
<!--            </div>-->
<!--        </div>-->

        <!-- Отчет 3: Контроль уплаты налогов -->
        <div class="col-md-6 mt-3">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-danger bg-opacity-10 text-danger p-3 rounded-3 me-3">
                                <i class="bi bi-exclamation-triangle fs-3"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-1 fw-bold">Контроль уплаты налогов и накоплений</h5>
                                <span class="badge bg-danger">Контроль задолженности</span>
                            </div>
                        </div>
                        <p class="card-text text-muted">
                            Показывает список оплаченных клиентами заказов, у которых рассчитаны налог URSSAF или отчисления, но еще не стоит галочка уплаты.
                        </p>
                    </div>
                    <div class="mt-3">
                        <a href="report_unpaid_taxes.php" class="btn btn-outline-danger w-100">
                            <i class="bi bi-arrow-right-circle me-1"></i> Открыть отчет
                        </a>
                    </div>
                </div>
            </div>
        </div>


        <!-- Отчет 4: Ежемесячный финансовый отчет (Rapport Mensuel) -->
        <div class="col-md-6 mt-3">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-3 me-3">
                                <i class="bi bi-calendar2-check fs-3"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-1 fw-bold">Ежемесячный отчёт (Rapport Mensuel)</h5>
                                <span class="badge bg-primary">Финансовая сводка</span>
                            </div>
                        </div>
                        <p class="card-text text-muted">
                            Полный обзор доходов за месяц с выбором периода, детализацией по типам оплаты и расчётом чистого остатка после уплаты налогов и закупки материалов.
                        </p>
                    </div>
                    <div class="mt-3">
                        <a href="report_mensuel.php" class="btn btn-outline-primary w-100">
                            <i class="bi bi-arrow-right-circle me-1"></i> Открыть отчет
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Отчет 5: Суммы по статусам -->
        <div class="col-md-6 mt-3">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-3 me-3">
                            <i class="bi bi-toggles fs-3"></i>
                        </div>
                        <h5 class="card-title mb-0 fw-bold">Суммы (En cours / Prévu)</h5>
                    </div>
                    <p class="card-text text-muted">Сводная сумма активных заказов по выбранному месяцу.</p>
                    <a href="report_status_sum.php" class="btn btn-outline-warning w-100">Открыть отчет</a>
                </div>
            </div>
        </div>

        <!-- Отчет: График заказов (Graphique du rapport) -->
        <div class="col-md-6 mt-3">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-danger bg-opacity-10 text-danger p-3 rounded-3 me-3">
                                <i class="bi bi-graph-up-arrow fs-3"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-1 fw-bold">График заказов (Commandes)</h5>
                                <span class="badge bg-danger">Аналитика / График</span>
                            </div>
                        </div>
                        <p class="card-text text-muted">
                            Динамический график динамики оплаченных заказов по месяцам и общее количество сделок.
                        </p>
                    </div>
                    <div class="mt-3">
                        <a href="report_chart.php" class="btn btn-outline-danger w-100">
                            <i class="bi bi-arrow-right-circle me-1"></i> Открыть отчет
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>