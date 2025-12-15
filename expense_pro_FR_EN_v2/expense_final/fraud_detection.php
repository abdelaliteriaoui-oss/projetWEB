<?php
require_once 'includes/config.php';
require_once 'includes/languages.php';
requireLogin();

if ($_SESSION['role'] !== 'manager') { header('Location: dashboard.php'); exit; }

$pageTitle = __('nav_fraud_detection');
$suspiciousExpenses = [];
$riskScores = ['high' => 0, 'medium' => 0, 'low' => 0];

$teamQuery = $pdo->prepare("SELECT id FROM users WHERE manager_id = ?");
$teamQuery->execute([$_SESSION['user_id']]);
$teamIds = array_column($teamQuery->fetchAll(), 'id');

if (!empty($teamIds)) {
    $ph = implode(',', array_fill(0, count($teamIds), '?'));
    
    $stmt = $pdo->prepare("
        SELECT d.id, d.user_id, d.objet, d.montant_total, d.created_at as date_dem, d.statut,
               u.prenom, u.nom, DAYOFWEEK(d.created_at) as dow,
               COUNT(df.id) as nb_details,
               SUM(CASE WHEN df.justificatif IS NOT NULL AND df.justificatif != '' THEN 1 ELSE 0 END) as nb_avec_justif
        FROM demandes d 
        JOIN users u ON d.user_id = u.id
        LEFT JOIN details_frais df ON d.id = df.demande_id
        WHERE d.user_id IN ($ph) 
        AND d.statut NOT IN ('rejetee_manager', 'rejetee_admin')
        AND d.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
        GROUP BY d.id, d.user_id, d.objet, d.montant_total, d.created_at, d.statut, u.prenom, u.nom
        ORDER BY d.montant_total DESC
    ");
    $stmt->execute($teamIds);
    $allExpenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($allExpenses as $exp) {
        $amount = floatval($exp['montant_total']);
        $employee = $exp['prenom'] . ' ' . $exp['nom'];
        $date = $exp['date_dem'];
        $hasReceipt = $exp['nb_avec_justif'] > 0;
        $isWeekend = in_array($exp['dow'], [1,7]);
        
        // Détermine la sévérité principale basée UNIQUEMENT sur le montant
        $mainSeverity = 'low';
        $mainTitle = '';
        $mainDescription = '';
        $mainIcon = 'fa-money-bill-wave';
        $mainColor = '#3B82F6';
        
        // Classification stricte par montant
        if ($amount >= 10000) {
            $mainSeverity = 'high';
            $mainTitle = '🚨 Montant CRITIQUE';
            $mainDescription = 'Montant ≥ 10 000 DH - Vérification URGENTE et justification obligatoire';
            $mainColor = '#DC2626';
            $riskScores['high']++;
        } elseif ($amount >= 5000) {
            $mainSeverity = 'high';
            $mainTitle = 'Montant Très Élevé';
            $mainDescription = 'Montant entre 5 000 et 10 000 DH - Vérification urgente requise';
            $mainColor = '#EF4444';
            $riskScores['high']++;
        } elseif ($amount >= 3000) {
            $mainSeverity = 'medium';
            $mainTitle = 'Montant Élevé';
            $mainDescription = 'Montant entre 3 000 et 5 000 DH - Vérification recommandée';
            $mainColor = '#F59E0B';
            $riskScores['medium']++;
        } elseif ($amount >= 1500) {
            $mainSeverity = 'low';
            $mainTitle = 'Montant Significatif';
            $mainDescription = 'Montant entre 1 500 et 3 000 DH - À surveiller';
            $mainColor = '#3B82F6';
            $riskScores['low']++;
        } else {
            // Montant < 1500 DH - Vérifier autres critères uniquement
            if (!$hasReceipt && $amount > 1000) {
                $mainSeverity = 'medium';
                $mainTitle = 'Sans Justificatif Important';
                $mainDescription = 'Montant > 1 000 DH sans aucune pièce jointe - Justificatif requis';
                $mainIcon = 'fa-file-invoice';
                $mainColor = '#F59E0B';
                $riskScores['medium']++;
            } elseif (!$hasReceipt && $amount > 500) {
                $mainSeverity = 'low';
                $mainTitle = 'Sans Justificatif';
                $mainDescription = 'Montant > 500 DH sans pièce jointe - Vérifier si justificatif nécessaire';
                $mainIcon = 'fa-file-invoice';
                $mainColor = '#6366F1';
                $riskScores['low']++;
            } elseif ($isWeekend && $amount > 800) {
                $mainSeverity = 'low';
                $mainTitle = 'Dépense Week-end Inhabituelle';
                $mainDescription = 'Dépense de ' . formatMoney($amount) . ' effectuée un ' . ($exp['dow']==1?'Dimanche':'Samedi');
                $mainIcon = 'fa-calendar-times';
                $mainColor = '#8B5CF6';
                $riskScores['low']++;
            } else {
                continue; // Ne pas afficher les petites dépenses normales
            }
        }
        
        // Construction des badges additionnels
        $badges = [];
        
        // Badge sans justificatif pour montants élevés
        if (!$hasReceipt && $amount >= 1500) {
            $badges[] = ['text' => '⚠️ Sans justificatif', 'color' => '#EF4444'];
        } elseif (!$hasReceipt && $amount >= 500) {
            $badges[] = ['text' => 'Sans justificatif', 'color' => '#F59E0B'];
        }
        
        // Badge week-end
        if ($isWeekend && $amount >= 1000) {
            $badges[] = ['text' => '📅 Week-end', 'color' => '#8B5CF6'];
        }
        
        // Badge montant rond suspect
        if ($amount >= 5000 && fmod($amount, 5000) == 0) {
            $badges[] = ['text' => '💰 Montant très rond', 'color' => '#DC2626'];
        } elseif ($amount >= 1000 && fmod($amount, 1000) == 0) {
            $badges[] = ['text' => 'Montant rond', 'color' => '#10B981'];
        }
        
        $suspiciousExpenses[] = [
            'type' => 'expense',
            'severity' => $mainSeverity,
            'icon' => $mainIcon,
            'color' => $mainColor,
            'title' => $mainTitle,
            'description' => $mainDescription,
            'expense_id' => $exp['id'],
            'amount' => $amount,
            'employee' => $employee,
            'date' => $date,
            'badges' => $badges
        ];
    }
}

// Tri par sévérité puis montant
usort($suspiciousExpenses, function($a,$b) {
    $order = ['high'=>0,'medium'=>1,'low'=>2];
    $diff = ($order[$a['severity']]??3) - ($order[$b['severity']]??3);
    return $diff!==0 ? $diff : $b['amount'] - $a['amount'];
});

$total = $riskScores['high'] + $riskScores['medium'] + $riskScores['low'];
$globalRisk = min(100, $riskScores['high']*30 + $riskScores['medium']*15 + $riskScores['low']*5);
$level = $globalRisk>=70?'critical':($globalRisk>=40?'elevated':($globalRisk>=15?'moderate':'low'));

include 'includes/header.php';
?>

<div class="page-header">
    <div class="page-header-content">
        <h2><i class="fas fa-shield-alt"></i> Détection de Fraude</h2>
        <p>Analyse des dépenses de votre équipe</p>
    </div>
</div>

<div class="row" style="gap:20px;margin-bottom:30px">
    <div class="col-lg-3">
        <div class="card" style="background:linear-gradient(135deg,<?= $level==='critical'?'#DC2626,#991B1B':($level==='elevated'?'#F59E0B,#D97706':($level==='moderate'?'#3B82F6,#2563EB':'#10B981,#059669')) ?>);color:#fff;border:none;box-shadow:0 10px 30px rgba(0,0,0,0.2)">
            <div class="card-body" style="text-align:center;padding:30px">
                <div style="font-size:64px;font-weight:800;line-height:1"><?= $globalRisk ?></div>
                <div style="font-size:14px;opacity:.9;margin-top:8px">Score de Risque Global</div>
                <div style="margin-top:16px;padding:8px 16px;background:rgba(255,255,255,.25);border-radius:20px;display:inline-block;font-size:13px;font-weight:600">
                    <?= $level==='critical'?'⚠️ CRITIQUE':($level==='elevated'?'⚡ ÉLEVÉ':($level==='moderate'?'📊 MODÉRÉ':'✅ FAIBLE')) ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-9">
        <div class="row" style="gap:16px">
            <div class="col">
                <div class="card" style="border-left:4px solid #EF4444">
                    <div class="card-body" style="text-align:center;padding:20px">
                        <div style="width:54px;height:54px;background:#FEE2E2;border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px">
                            <i class="fas fa-exclamation-triangle" style="color:#EF4444;font-size:24px"></i>
                        </div>
                        <div style="font-size:32px;font-weight:700;color:#EF4444"><?= $riskScores['high'] ?></div>
                        <div style="font-size:13px;color:var(--gray-600);margin-top:4px">Risque Élevé</div>
                    </div>
                </div>
            </div>
            
            <div class="col">
                <div class="card" style="border-left:4px solid #F59E0B">
                    <div class="card-body" style="text-align:center;padding:20px">
                        <div style="width:54px;height:54px;background:#FEF3C7;border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px">
                            <i class="fas fa-exclamation-circle" style="color:#F59E0B;font-size:24px"></i>
                        </div>
                        <div style="font-size:32px;font-weight:700;color:#F59E0B"><?= $riskScores['medium'] ?></div>
                        <div style="font-size:13px;color:var(--gray-600);margin-top:4px">Risque Moyen</div>
                    </div>
                </div>
            </div>
            
            <div class="col">
                <div class="card" style="border-left:4px solid #3B82F6">
                    <div class="card-body" style="text-align:center;padding:20px">
                        <div style="width:54px;height:54px;background:#DBEAFE;border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px">
                            <i class="fas fa-info-circle" style="color:#3B82F6;font-size:24px"></i>
                        </div>
                        <div style="font-size:32px;font-weight:700;color:#3B82F6"><?= $riskScores['low'] ?></div>
                        <div style="font-size:13px;color:var(--gray-600);margin-top:4px">À Vérifier</div>
                    </div>
                </div>
            </div>
            
            <div class="col">
                <div class="card" style="border-left:4px solid #8B5CF6">
                    <div class="card-body" style="text-align:center;padding:20px">
                        <div style="width:54px;height:54px;background:#EDE9FE;border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px">
                            <i class="fas fa-list-check" style="color:#8B5CF6;font-size:24px"></i>
                        </div>
                        <div style="font-size:32px;font-weight:700;color:#8B5CF6"><?= $total ?></div>
                        <div style="font-size:13px;color:var(--gray-600);margin-top:4px">Total Alertes</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header" style="background:var(--gray-50);border-bottom:2px solid var(--border-color)">
        <h3 class="card-title" style="font-size:18px">
            <i class="fas fa-search"></i> Anomalies Détectées (<?= count($suspiciousExpenses) ?>)
        </h3>
    </div>
    <div class="card-body" style="padding:0">
        <?php if (empty($suspiciousExpenses)): ?>
        <div style="padding:80px 20px;text-align:center">
            <div style="width:80px;height:80px;background:#D1FAE5;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px">
                <i class="fas fa-check-circle" style="font-size:40px;color:#10B981"></i>
            </div>
            <h3 style="color:#10B981;margin-bottom:8px;font-size:24px">Aucune anomalie détectée</h3>
            <p style="color:var(--gray-600);font-size:15px">Toutes les dépenses de votre équipe semblent conformes</p>
        </div>
        <?php else: ?>
        <div style="display:flex;flex-direction:column">
            <?php foreach ($suspiciousExpenses as $e): ?>
            <div style="display:flex;align-items:flex-start;gap:16px;padding:20px;border-bottom:1px solid var(--border-color);transition:all 0.2s" onmouseover="this.style.backgroundColor='var(--gray-50)'" onmouseout="this.style.backgroundColor='transparent'">
                <div style="width:50px;height:50px;background:<?= $e['color'] ?>15;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;border:2px solid <?= $e['color'] ?>30">
                    <i class="fas <?= $e['icon'] ?>" style="color:<?= $e['color'] ?>;font-size:20px"></i>
                </div>
                
                <div style="flex:1;min-width:0">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;flex-wrap:wrap">
                        <span style="font-weight:700;font-size:15px;color:var(--gray-900)"><?= htmlspecialchars($e['title']) ?></span>
                        <span style="padding:4px 10px;border-radius:6px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;background:<?= $e['color'] ?>;color:#fff">
                            <?= $e['severity']==='high'?'ÉLEVÉ':($e['severity']==='medium'?'MOYEN':'FAIBLE') ?>
                        </span>
                        <?php foreach ($e['badges'] as $badge): ?>
                        <span style="padding:4px 10px;border-radius:6px;font-size:10px;font-weight:600;background:<?= $badge['color'] ?>20;color:<?= $badge['color'] ?>">
                            <?= htmlspecialchars($badge['text']) ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    
                    <div style="color:var(--gray-600);font-size:14px;margin-bottom:10px;line-height:1.5">
                        <?= htmlspecialchars($e['description']) ?>
                    </div>
                    
                    <div style="display:flex;gap:20px;font-size:13px;color:var(--gray-500);flex-wrap:wrap">
                        <span style="display:flex;align-items:center;gap:6px">
                            <i class="fas fa-user" style="width:14px"></i>
                            <strong><?= htmlspecialchars($e['employee']) ?></strong>
                        </span>
                        <span style="display:flex;align-items:center;gap:6px">
                            <i class="fas fa-money-bill-wave" style="width:14px"></i>
                            <strong style="color:<?= $e['color'] ?>"><?= formatMoney($e['amount']) ?></strong>
                        </span>
                        <span style="display:flex;align-items:center;gap:6px">
                            <i class="fas fa-calendar" style="width:14px"></i>
                            <?= formatDate($e['date']) ?>
                        </span>
                    </div>
                </div>
                
                <a href="voir_demande.php?id=<?= $e['expense_id'] ?>" class="btn btn-primary btn-sm" style="flex-shrink:0;display:flex;align-items:center;gap:6px">
                    <i class="fas fa-eye"></i> Voir
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>