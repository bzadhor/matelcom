<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/auth.php';
requireAdmin();
if (!gh_is_super_admin()) { http_response_code(403); exit('Accès refusé'); }
$db = getDB();
$pageTitle  = 'Utilisateurs';
$activePage = 'users';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf_or_fail('users_form');
    $action = $_POST['action']??'';
    if ($action==='add') {
        $u = limitString($_POST['username']??'',100);
        $e = limitString($_POST['email']??'',150);
        $pw = $_POST['password']??'';
        $role = (int)($_POST['role_id']??2);
        if ($u && $pw && strlen($pw)>=6) {
            $hash = password_hash($pw, PASSWORD_DEFAULT);
            $db->prepare("INSERT INTO admins(username,password,email,role_id) VALUES(?,?,?,?)")->execute([$u,$hash,$e,$role]);
            setFlash('success',"Utilisateur $u créé.");
        }
    } elseif ($action==='delete') {
        $id = (int)($_POST['id']??0);
        if ($id !== (int)$_SESSION['admin_id']) {
            $db->prepare("DELETE FROM admins WHERE id=? AND is_super_admin=0")->execute([$id]);
            setFlash('success','Utilisateur supprimé.');
        }
    } elseif ($action==='toggle') {
        $id = (int)($_POST['id']??0);
        $db->prepare("UPDATE admins SET is_active=NOT is_active WHERE id=? AND is_super_admin=0")->execute([$id]);
    }
    header('Location: users.php'); exit;
}

$users = $db->query("SELECT a.*, r.name as role_name FROM admins a LEFT JOIN admin_roles r ON a.role_id=r.id ORDER BY a.id")->fetchAll();
$roles = $db->query("SELECT * FROM admin_roles WHERE is_active=1")->fetchAll();
require '_top.php';
?>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:22px">
  <div class="card">
    <div class="card-header"><h2>Utilisateurs</h2></div>
    <table>
      <thead><tr><th>Utilisateur</th><th>Email</th><th>Rôle</th><th>Statut</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
      <tr>
        <td><strong><?= e($u['username']) ?></strong><?= $u['is_super_admin']?' <span class="badge bd-red">Super</span>':'' ?></td>
        <td><?= e($u['email']) ?></td>
        <td><?= e($u['role_name']??'-') ?></td>
        <td><span class="badge <?= $u['is_active']?'bd-success':'bd-secondary' ?>"><?= $u['is_active']?'Actif':'Inactif' ?></span></td>
        <td>
          <?php if(!$u['is_super_admin']): ?>
          <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ?')">
            <?= csrf_input('users_form') ?>
            <input type="hidden" name="action" value="delete"/><input type="hidden" name="id" value="<?= $u['id'] ?>"/>
            <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card" style="padding:24px">
    <h3 style="font-family:'Poppins',sans-serif;font-weight:700;margin-bottom:18px"><i class="fas fa-user-plus" style="color:var(--red);margin-right:6px"></i> Ajouter</h3>
    <form method="POST">
      <?= csrf_input('users_form') ?>
      <input type="hidden" name="action" value="add"/>
      <div class="fg" style="margin-bottom:12px"><label>Username</label><input type="text" name="username" required/></div>
      <div class="fg" style="margin-bottom:12px"><label>Email</label><input type="email" name="email"/></div>
      <div class="fg" style="margin-bottom:12px"><label>Mot de passe</label><input type="password" name="password" required minlength="6"/></div>
      <div class="fg" style="margin-bottom:12px"><label>Rôle</label>
        <select name="role_id">
          <?php foreach ($roles as $r): ?><option value="<?= $r['id'] ?>"><?= e($r['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Créer</button>
    </form>
  </div>
</div>

<?php require '_bottom.php'; ?>
