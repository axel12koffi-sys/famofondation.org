<?php
/* Fondation FAMO — tableau de bord du suivi des dons */
declare(strict_types=1);
require __DIR__ . '/auth.php';

$msg = '';

/* ---------- Actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'login') {
    csrf_verifier();
    if (!tenter_connexion($_POST['email'] ?? '', $_POST['password'] ?? '')) {
      $msg = 'Identifiants incorrects.';
    }
  }
  elseif ($action === 'logout') {
    csrf_verifier();
    deconnexion();
    header('Location: index.php'); exit;
  }
  elseif ($action === 'ajouter_don' && est_connecte()) {
    csrf_verifier();
    $montant = (float)str_replace(',', '.', (string)($_POST['montant'] ?? '0'));
    if ($montant > 0) {
      $did = donateur_id($_POST['email'] ?? null, $_POST['nom'] ?? null, $_POST['telephone'] ?? null);
      enregistrer_don([
        'donateur_id' => $did,
        'montant'     => $montant,
        'devise'      => $_POST['devise'] ?: famo_config()['devise_defaut'],
        'frequence'   => in_array($_POST['frequence'] ?? '', ['unique','mensuel'], true) ? $_POST['frequence'] : 'unique',
        'source'      => in_array($_POST['source'] ?? '', ['stripe','paypal','zeffy','especes','cheque','autre'], true) ? $_POST['source'] : 'autre',
        'note'        => trim((string)($_POST['note'] ?? '')) ?: null,
        'don_le'      => ($_POST['don_le'] ?? '') ? str_replace('T', ' ', $_POST['don_le']) . ':00' : date('Y-m-d H:i:s'),
      ]);
      $msg = 'Don enregistré.';
    } else {
      $msg = 'Montant invalide.';
    }
  }
}

/* ---------- Export CSV ---------- */
if (est_connecte() && isset($_GET['export'])) {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="dons-famo-' . date('Y-m-d') . '.csv"');
  $out = fopen('php://output', 'w');
  fputs($out, "\xEF\xBB\xBF"); // BOM UTF-8 pour Excel
  fputcsv($out, ['Date','Montant','Devise','Fréquence','Source','Statut','Donateur','Email','Référence','Note'], ';');
  $rows = db()->query(
    'SELECT d.don_le, d.montant, d.devise, d.frequence, d.source, d.statut,
            dn.nom, dn.email, d.reference, d.note
     FROM dons d LEFT JOIN donateurs dn ON dn.id = d.donateur_id
     ORDER BY d.don_le DESC'
  );
  foreach ($rows as $r) fputcsv($out, array_values($r), ';');
  exit;
}

/* ---------- Données du tableau de bord ---------- */
$stats = null; $parMois = []; $dons = []; $fSource = $_GET['source'] ?? ''; $fMois = $_GET['mois'] ?? '';
if (est_connecte()) {
  $pdo = db();
  $stats = $pdo->query(
    "SELECT COALESCE(SUM(montant),0) total,
            COALESCE(SUM(CASE WHEN don_le >= DATE_FORMAT(NOW(), '%Y-%m-01') THEN montant END),0) mois,
            COUNT(*) nb,
            COALESCE(AVG(montant),0) moyen,
            COUNT(DISTINCT donateur_id) donateurs
     FROM dons WHERE statut = 'recu'"
  )->fetch();

  $parMois = $pdo->query(
    "SELECT DATE_FORMAT(don_le, '%Y-%m') m, SUM(montant) t
     FROM dons WHERE statut = 'recu' AND don_le >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
     GROUP BY m ORDER BY m"
  )->fetchAll();

  $sql = 'SELECT d.*, dn.nom, dn.email
          FROM dons d LEFT JOIN donateurs dn ON dn.id = d.donateur_id WHERE 1=1';
  $args = [];
  if ($fSource !== '') { $sql .= ' AND d.source = ?'; $args[] = $fSource; }
  if ($fMois !== '')   { $sql .= " AND DATE_FORMAT(d.don_le, '%Y-%m') = ?"; $args[] = $fMois; }
  $sql .= ' ORDER BY d.don_le DESC LIMIT 200';
  $q = $pdo->prepare($sql); $q->execute($args); $dons = $q->fetchAll();
}

function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$maxMois = 0; foreach ($parMois as $r) $maxMois = max($maxMois, (float)$r['t']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Suivi des dons — Fondation FAMO</title>
<style>
:root{--red:#821611;--red2:#c22822;--orange:#f18323;--cream:#faf6f1;--ink:#241211;--soft:#5c4744;}
*{box-sizing:border-box;} body{margin:0;font-family:system-ui,sans-serif;background:var(--cream);color:var(--ink);}
.top{background:var(--red);color:#fff;padding:16px 24px;display:flex;justify-content:space-between;align-items:center;}
.top h1{font-size:1.1rem;margin:0;font-weight:600;}
.wrap{max-width:1100px;margin:0 auto;padding:24px;}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:24px;}
.card{background:#fff;border-radius:14px;padding:18px;box-shadow:0 10px 24px -18px rgba(36,18,17,.35);}
.card .n{font-size:1.6rem;font-weight:700;color:var(--red);}
.card .l{font-size:.82rem;color:var(--soft);margin-top:4px;}
.panel{background:#fff;border-radius:14px;padding:22px;margin-bottom:24px;box-shadow:0 10px 24px -18px rgba(36,18,17,.35);}
.panel h2{font-size:1rem;margin:0 0 14px;}
table{width:100%;border-collapse:collapse;font-size:.88rem;}
th,td{text-align:left;padding:9px 10px;border-bottom:1px solid #f0e7e0;}
th{color:var(--soft);font-weight:600;font-size:.78rem;text-transform:none;}
.badge{display:inline-block;padding:3px 10px;border-radius:100px;font-size:.75rem;font-weight:600;background:#f4ece6;}
.badge.stripe{background:#e8f0fb;color:#1768ad;} .badge.paypal{background:#fdf1e3;color:#b06010;}
.badge.zeffy{background:#e9f5ee;color:#076633;} .badge.especes,.badge.cheque{background:#f3ebf6;color:#732282;}
.bars{display:flex;align-items:flex-end;gap:6px;height:120px;margin-top:8px;}
.bar{flex:1;background:var(--red2);border-radius:6px 6px 0 0;min-height:3px;position:relative;}
.bar span{position:absolute;bottom:-20px;left:50%;transform:translateX(-50%);font-size:.65rem;color:var(--soft);white-space:nowrap;}
form.inline{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;align-items:end;}
label{font-size:.78rem;font-weight:600;display:block;margin-bottom:4px;color:var(--soft);}
input,select{width:100%;padding:9px 11px;border:1.5px solid #e5d8ce;border-radius:9px;font:inherit;background:#fff;}
input:focus,select:focus{border-color:var(--orange);outline:none;}
.btn{background:var(--orange);color:#fff;border:none;border-radius:100px;padding:10px 20px;font:inherit;font-weight:600;cursor:pointer;}
.btn.secondary{background:transparent;color:var(--red);border:1.5px solid var(--red);}
.msg{background:#e9f5ee;color:#076633;padding:10px 14px;border-radius:9px;margin-bottom:16px;font-size:.88rem;}
.login{max-width:380px;margin:10vh auto;}
.filters{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;align-items:end;}
.filters > div{min-width:150px;}
</style>
</head>
<body>

<?php if (!est_connecte()): ?>
  <div class="login">
    <div class="panel">
      <h2>Suivi des dons — Fondation FAMO</h2>
      <?php if ($msg): ?><div class="msg"><?= e($msg) ?></div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="action" value="login">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <p><label>E-mail</label><input type="email" name="email" required></p>
        <p><label>Mot de passe</label><input type="password" name="password" required></p>
        <button class="btn" type="submit">Se connecter</button>
      </form>
    </div>
  </div>

<?php else: ?>
  <div class="top">
    <h1>Suivi des dons — Fondation FAMO</h1>
    <form method="post" style="margin:0;">
      <input type="hidden" name="action" value="logout">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <button class="btn secondary" style="color:#fff;border-color:#fff;" type="submit">Se déconnecter</button>
    </form>
  </div>

  <div class="wrap">
    <?php if ($msg): ?><div class="msg"><?= e($msg) ?></div><?php endif; ?>

    <div class="cards">
      <div class="card"><div class="n"><?= number_format((float)$stats['total'], 2, ',', ' ') ?> $</div><div class="l">Total collecté</div></div>
      <div class="card"><div class="n"><?= number_format((float)$stats['mois'], 2, ',', ' ') ?> $</div><div class="l">Ce mois-ci</div></div>
      <div class="card"><div class="n"><?= (int)$stats['nb'] ?></div><div class="l">Dons reçus</div></div>
      <div class="card"><div class="n"><?= (int)$stats['donateurs'] ?></div><div class="l">Donateurs</div></div>
      <div class="card"><div class="n"><?= number_format((float)$stats['moyen'], 2, ',', ' ') ?> $</div><div class="l">Don moyen</div></div>
    </div>

    <?php if ($parMois): ?>
    <div class="panel">
      <h2>12 derniers mois</h2>
      <div class="bars" style="margin-bottom:26px;">
        <?php foreach ($parMois as $r): ?>
          <div class="bar" style="height:<?= $maxMois > 0 ? max(3, round((float)$r['t'] / $maxMois * 100)) : 3 ?>%;"
               title="<?= e($r['m']) ?> : <?= number_format((float)$r['t'], 2, ',', ' ') ?> $">
            <span><?= e(substr($r['m'], 5)) ?>/<?= e(substr($r['m'], 2, 2)) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="panel">
      <h2>Ajouter un don (hors ligne : espèces, chèque, Zeffy…)</h2>
      <form method="post" class="inline">
        <input type="hidden" name="action" value="ajouter_don">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <div><label>Montant *</label><input name="montant" type="number" step="0.01" min="0.01" required></div>
        <div><label>Devise</label>
          <select name="devise"><option>USD</option><option>CAD</option><option>XOF</option><option>EUR</option></select></div>
        <div><label>Fréquence</label>
          <select name="frequence"><option value="unique">Unique</option><option value="mensuel">Mensuel</option></select></div>
        <div><label>Source</label>
          <select name="source">
            <option value="especes">Espèces</option><option value="cheque">Chèque</option>
            <option value="zeffy">Zeffy</option><option value="stripe">Stripe</option>
            <option value="paypal">PayPal</option><option value="autre">Autre</option>
          </select></div>
        <div><label>Date</label><input name="don_le" type="datetime-local"></div>
        <div><label>Nom du donateur</label><input name="nom" type="text"></div>
        <div><label>E-mail</label><input name="email" type="email"></div>
        <div><label>Téléphone</label><input name="telephone" type="text"></div>
        <div><label>Note</label><input name="note" type="text"></div>
        <div><button class="btn" type="submit">Enregistrer</button></div>
      </form>
    </div>

    <div class="panel">
      <h2>Derniers dons</h2>
      <form method="get" class="filters">
        <div><label>Source</label>
          <select name="source">
            <option value="">Toutes</option>
            <?php foreach (['stripe','paypal','zeffy','especes','cheque','autre'] as $s): ?>
              <option value="<?= $s ?>" <?= $fSource === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div><label>Mois (AAAA-MM)</label><input name="mois" type="month" value="<?= e($fMois) ?>"></div>
        <div><button class="btn secondary" type="submit">Filtrer</button></div>
        <div><a class="btn" style="text-decoration:none;display:inline-block;" href="?export=1">Exporter CSV</a></div>
      </form>
      <table>
        <tr><th>Date</th><th>Montant</th><th>Fréquence</th><th>Source</th><th>Donateur</th><th>Statut</th><th>Note</th></tr>
        <?php foreach ($dons as $d): ?>
        <tr>
          <td><?= e(date('d/m/Y H:i', strtotime($d['don_le']))) ?></td>
          <td><strong><?= number_format((float)$d['montant'], 2, ',', ' ') ?> <?= e($d['devise']) ?></strong></td>
          <td><?= e($d['frequence']) ?></td>
          <td><span class="badge <?= e($d['source']) ?>"><?= e($d['source']) ?></span></td>
          <td><?= e($d['nom'] ?: '—') ?><?= $d['email'] ? '<br><small>' . e($d['email']) . '</small>' : '' ?></td>
          <td><?= e($d['statut']) ?></td>
          <td><?= e($d['note'] ?: '') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$dons): ?><tr><td colspan="7">Aucun don pour ces critères.</td></tr><?php endif; ?>
      </table>
    </div>
  </div>
<?php endif; ?>

</body>
</html>
