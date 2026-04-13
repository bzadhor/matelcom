<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/auth.php';
requireAdmin();
gh_require_permission('temoignages','read');
$db = getDB();
$pageTitle  = 'Témoignages';
$activePage = 'temoignages';

if ($_SERVER['REQUEST_METHOD']==='POST' && gh_has_page_permission('temoignages','write')) {
    verify_csrf_or_fail('temoignage_form');
    $action = $_POST['action']??'';
    if ($action==='add' || $action==='edit') {
        $data = [
            'nom'     => limitString($_POST['nom']??'',100),
            'role'    => limitString($_POST['role']??'',100),
            'societe' => limitString($_POST['societe']??'',100),
            'texte'   => limitString($_POST['texte']??'',2000),
            'note'    => max(1,min(5,(int)($_POST['note']??5))),
            'statut'  => isset($_POST['statut'])?1:0,
            'ordre'   => (int)($_POST['ordre']??0),
        ];
        if (!empty($_FILES['photo']) && $_FILES['photo']['error']===UPLOAD_ERR_OK) {
            $up = uploadImage($_FILES['photo'],'temoignages');
            if (isset($up['success'])) $data['photo'] = $up['filename'];
        }
        if ($action==='edit') {
            $id = (int)($_POST['id']??0);
            if (!isset($data['photo'])) { $s=$db->prepare("SELECT photo FROM temoignages WHERE id=?"); $s->execute([$id]); $old=$s->fetchColumn(); $data['photo']=$old; }
            $cols = implode('=?,',array_keys($data)).'=?';
            $vals = array_values($data); $vals[]=$id;
            $db->prepare("UPDATE temoignages SET $cols WHERE id=?")->execute($vals);
        } else {
            if (!isset($data['photo'])) $data['photo']='';
            $cols = implode(',',array_keys($data));
            $phs = implode(',',array_fill(0,count($data),'?'));
            $db->prepare("INSERT INTO temoignages ($cols) VALUES ($phs)")->execute(array_values($data));
        }
        setFlash('success','Témoignage enregistré.');
    } elseif ($action==='delete') {
        $db->prepare("DELETE FROM temoignages WHERE id=?")->execute([(int)($_POST['id']??0)]);
        setFlash('success','Témoignage supprimé.');
    }
    header('Location: temoignages.php'); exit;
}

$temoignages = $db->query("SELECT * FROM temoignages ORDER BY ordre ASC")->fetchAll();
require '_top.php';
?>

<div style="display:grid;grid-template-columns:1.5fr 1fr;gap:22px">
  <div class="card">
    <div class="card-header"><h2>Témoignages</h2></div>
    <table>
      <thead><tr><th>Photo</th><th>Nom</th><th>Rôle</th><th>Note</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($temoignages as $t):
        $av=imgUrl($t['photo']??'');
      ?>
      <tr>
        <td><?php if($av): ?><img src="<?= e($av) ?>" class="img-prev" style="border-radius:50%"/><?php else: ?><div class="img-ph" style="border-radius:50%"><i class="fas fa-user"></i></div><?php endif; ?></td>
        <td><strong><?= e($t['nom']) ?></strong><br><small style="color:var(--muted)"><?= e($t['societe']??'') ?></small></td>
        <td><?= e($t['role']??'') ?></td>
        <td style="color:#F59E0B"><?= str_repeat('★',$t['note']) ?></td>
        <td><span class="badge <?= $t['statut']?'bd-success':'bd-secondary' ?>"><?= $t['statut']?'Actif':'Inactif' ?></span></td>
        <td>
          <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ?')">
            <?= csrf_input('temoignage_form') ?>
            <input type="hidden" name="action" value="delete"/><input type="hidden" name="id" value="<?= $t['id'] ?>"/>
            <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if(gh_has_page_permission('temoignages','write')): ?>
  <div class="card" style="padding:24px">
    <h3 style="font-family:'Poppins',sans-serif;font-weight:700;margin-bottom:18px"><i class="fas fa-plus" style="color:var(--red);margin-right:6px"></i> Ajouter</h3>
    <form method="POST" enctype="multipart/form-data">
      <?= csrf_input('temoignage_form') ?>
      <input type="hidden" name="action" value="add"/>
      <div class="fg" style="margin-bottom:12px"><label>Nom *</label><input type="text" name="nom" required/></div>
      <div class="fg" style="margin-bottom:12px"><label>Rôle</label><input type="text" name="role" placeholder="Ex: Directeur IT"/></div>
      <div class="fg" style="margin-bottom:12px"><label>Société</label><input type="text" name="societe"/></div>
      <div class="fg" style="margin-bottom:12px"><label>Témoignage *</label><textarea name="texte" required></textarea></div>
      <div class="fg" style="margin-bottom:12px"><label>Note (1-5)</label><input type="number" name="note" value="5" min="1" max="5"/></div>
      <div class="fg" style="margin-bottom:12px"><label>Photo</label><input type="file" name="photo" accept="image/*"/></div>
      <div class="fg" style="margin-bottom:12px"><label>Ordre</label><input type="number" name="ordre" value="0"/></div>
      <label style="display:flex;align-items:center;gap:6px;font-size:.85rem;margin-bottom:14px"><input type="checkbox" name="statut" checked/> Actif</label>
      <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Ajouter</button>
    </form>
  </div>
  <?php endif; ?>
</div>

<?php require '_bottom.php'; ?>
