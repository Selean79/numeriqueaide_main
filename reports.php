<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'header.php';
?>

<title>Centre de rapports — NumériqueAide</title>

<div class="container mt-4 mb-5" style="max-width: 1000px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0"><i class="bi bi-file-earmark-bar-graph me-2"></i>Rapports et analyses</h3>
        <span class="text-muted"><i class="bi bi-calendar3 me-1"></i>Aujourd'hui : <?= date('d.m.Y'); ?></span>
    </div>

    <div class="row g-4">
        <!-- Rapport 1 : Commandes / Clients du jour -->
        <div class="col-md-6">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-3 me-3">
                                <i class="bi bi-person-lines-fill fs-3"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-1 fw-bold">Commandes du jour ou de la date choisie</h5>
                                <span class="badge bg-success">Quotidien</span>
                            </div>
                        </div>
                        <p class="card-text text-muted">
                            Affiche une liste compacte des clients, leurs adresses, contacts, plateformes utilisées et une description détaillée des travaux pour aujourd'hui ou la date sélectionnée.
                        </p>
                    </div>
                    <div class="mt-3">
                        <a href="report_daily_clients.php" class="btn btn-primary w-100">
                            <i class="bi bi-arrow-right-circle me-1"></i> Ouvrir le rapport
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rapport 3 : Contrôle du paiement des taxes -->
        <div class="col-md-6 mt-3">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-danger bg-opacity-10 text-danger p-3 rounded-3 me-3">
                                <i class="bi bi-exclamation-triangle fs-3"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-1 fw-bold">Contrôle du paiement des taxes et cotisations</h5>
                                <span class="badge bg-danger">Suivi des impayés</span>
                            </div>
                        </div>
                        <p class="card-text text-muted">
                            Affiche la liste des commandes payées par les clients pour lesquelles la taxe URSSAF ou les cotisations ont été calculées mais ne sont pas encore marquées comme payées.
                        </p>
                    </div>
                    <div class="mt-3">
                        <a href="report_unpaid_taxes.php" class="btn btn-outline-danger w-100">
                            <i class="bi bi-arrow-right-circle me-1"></i> Ouvrir le rapport
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rapport 4 : Rapport mensuel (Rapport Mensuel) -->
        <div class="col-md-6 mt-3">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-3 me-3">
                                <i class="bi bi-calendar2-check fs-3"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-1 fw-bold">Rapport mensuel (Rapport Mensuel)</h5>
                                <span class="badge bg-primary">Résumé financier</span>
                            </div>
                        </div>
                        <p class="card-text text-muted">
                            Vue d'ensemble complète des revenus du mois avec sélection de la période, ventilation par types de paiement et calcul du solde net après paiement des taxes et achat de matériaux.
                        </p>
                    </div>
                    <div class="mt-3">
                        <a href="report_mensuel.php" class="btn btn-outline-primary w-100">
                            <i class="bi bi-arrow-right-circle me-1"></i> Ouvrir le rapport
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rapport 5 : Montants par statuts -->
        <div class="col-md-6 mt-3">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-3 me-3">
                            <i class="bi bi-toggles fs-3"></i>
                        </div>
                        <h5 class="card-title mb-0 fw-bold">Montants (En cours / Prévu)</h5>
                    </div>
                    <p class="card-text text-muted">Montant total des commandes actives pour le mois sélectionné.</p>
                    <a href="report_status_sum.php" class="btn btn-outline-warning w-100">Ouvrir le rapport</a>
                </div>
            </div>
        </div>

        <!-- Rapport : Graphique des commandes (Graphique du rapport) -->
        <div class="col-md-6 mt-3">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-danger bg-opacity-10 text-danger p-3 rounded-3 me-3">
                                <i class="bi bi-graph-up-arrow fs-3"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-1 fw-bold">Graphique des commandes (Commandes)</h5>
                                <span class="badge bg-danger">Analyse / Graphique</span>
                            </div>
                        </div>
                        <p class="card-text text-muted">
                            Graphique dynamique de l'évolution des commandes payées par mois et nombre total de transactions.
                        </p>
                    </div>
                    <div class="mt-3">
                        <a href="report_chart.php" class="btn btn-outline-danger w-100">
                            <i class="bi bi-arrow-right-circle me-1"></i> Ouvrir le rapport
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