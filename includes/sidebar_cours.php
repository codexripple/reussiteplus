<?php
// Affiche la liste des matières et niveaux pour la navigation dans les cours
$matieres = [];
try {
  $matieres = dbAll(
    "SELECT DISTINCT m.nom AS matiere 
     FROM archives a
     JOIN matieres m ON a.matiere_id = m.id
     ORDER BY m.nom"
  );
  $matieres = array_map(function($m) { return $m['matiere']; }, $matieres);
} catch (Exception $e) {
  // Si la colonne n'existe pas, on liste les dossiers dans cours/
  $coursDir = __DIR__ . '/../cours/';
  foreach (glob($coursDir . '*', GLOB_ONLYDIR) as $dir) {
    $matieres[] = basename($dir);
  }
}
$niveaux = ['Primaire','Secondaire','TENASOSP','ENAFEP','Examen d\'État'];
?>
<aside class="sidebar-cours" style="background:#f8f8f8;padding:24px 16px;border-radius:12px;min-width:220px;">
  <h3 style="margin-top:0;">Matières</h3>
  <ul style="list-style:none;padding:0;">
    <?php foreach ($matieres as $m): ?>
      <li><a href="?matiere=<?= urlencode($m) ?>" style="color:#007A5E;"> <?= e($m) ?> </a></li>
    <?php endforeach; ?>
  </ul>
  <h3>Niveaux</h3>
  <ul style="list-style:none;padding:0;">
    <?php foreach ($niveaux as $n): ?>
      <li><span style="color:#555;"> <?= e($n) ?> </span></li>
    <?php endforeach; ?>
  </ul>
</aside>
