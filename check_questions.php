<?php
require 'includes/config.php';
require 'includes/db.php';

$byStatus = dbAll('SELECT status, COUNT(*) as n FROM question_bank GROUP BY status');
echo "Questions par statut:\n";
foreach($byStatus as $r) echo '  ' . $r['status'] . ': ' . $r['n'] . "\n";
$total = dbRow('SELECT COUNT(*) as n FROM question_bank');
echo 'TOTAL: ' . $total['n'] . "\n\n";

$byMat = dbAll('SELECT m.nom, qb.status, COUNT(*) as n FROM question_bank qb LEFT JOIN matieres m ON qb.matiere_id=m.id GROUP BY m.nom, qb.status ORDER BY m.nom, qb.status');
echo "Par matiere+statut:\n";
foreach($byMat as $r) echo '  ' . ($r['nom']??'NULL') . ' [' . $r['status'] . ']: ' . $r['n'] . "\n";
