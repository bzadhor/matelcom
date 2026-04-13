<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/auth.php';
requireAdmin();
gh_require_permission('produits','write');
$db = getDB();

$id = (int)($_GET['id']??0);
$prod = $id ? $db->prepare("SELECT * FROM produits WHERE id=?")->execute([$id]) ? $db->prepare("SELECT * FROM produits WHERE id=?")->fetch() : null : null;
if($id){ $s=$db->prepare("SELECT * FROM produits WHERE id=?"); $s->execute([$id]); $prod=$s->fetch(); }

$pageTitle  = $prod ? 'Modifier le produit' : 'Nouveau produit';
$activePage = 'produits';
$cats = getCategories();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf_or_fail('produit_form');
    $data = [
        'nom'              => limitString($_POST['nom']??'', 200),
        'modele'           => limitString($_POST['modele']??'', 100),
        'marque'           => limitString($_POST['marque']??'', 100),
        'description_courte' => limitString($_POST['description_courte']??'', 300),
        'description'      => $_POST['description']??'',
        'categories'       => implode(' ', $_POST['categories']??[]),
        'badge_texte'      => limitString($_POST['badge_texte']??'', 60),
        'badge_couleur'    => limitString($_POST['badge_couleur']??'#D32F2F', 30),
        'disponibilite'    => $_POST['disponibilite']??'en_stock',
        'statut'           => isset($_POST['statut']) ? 1 : 0,
        'en_vedette'       => isset($_POST['en_vedette']) ? 1 : 0,
        'ordre'            => (int)($_POST['ordre']??0),
        'slug'             => normalizeSlug($_POST['slug']??$_POST['nom']??''),
    ];

    // Specs JSON
    $specLabels = $_POST['spec_label']??[];
    $specValues = $_POST['spec_value']??[];
    $specs = [];
    foreach ($specLabels as $i=>$label) {
        $label = trim($label); $val = trim($specValues[$i]??'');
        if ($label && $val) $specs[] = ['label'=>$label,'value'=>$val];
    }
    $data['specifications'] = json_encode($specs, JSON_UNESCAPED_UNICODE);

    // Tags JSON
    $tagsRaw = trim($_POST['tags']??'');
    $tagsArr = array_filter(array_map('trim', explode(',', $tagsRaw)));
    $data['tags'] = json_encode(array_values($tagsArr), JSON_UNESCAPED_UNICODE);

    // Image
    if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $up = uploadImage($_FILES['image'], 'products');
        if (isset($up['success'])) $data['image'] = $up['filename'];
    }

    if ($prod) {
        if (!isset($data['image'])) $data['image'] = $prod['image'];
        $cols = implode('=?,', array_keys($data)).'=?';
        $vals = array_values($data); $vals[] = $id;
        $db->prepare("UPDATE produits SET $cols WHERE id=?")->execute($vals);
        setFlash('success','Produit mis à jour.');
    } else {
        $cols = implode(',', array_keys($data));
        $phs = implode(',', array_fill(0, count($data), '?'));
        $db->prepare("INSERT INTO produits ($cols) VALUES ($phs)")->execute(array_values($data));
        setFlash('success','Produit créé.');
    }
    header('Location: produits.php'); exit;
}

$specs = $prod ? jdecode($prod['specifications']) : [];
$tags  = $prod ? implode(', ', jdecode($prod['tags'])) : '';
$prodCats = $prod ? explode(' ', $prod['categories']??'') : [];

require '_top.php';
?>
<style>
.specs-builder{margin-top:8px;}
.spec-row{display:flex;gap:8px;margin-bottom:8px;align-items:center;}
.spec-row input{flex:1;}
.spec-row .remove-spec{background:none;border:none;color:#dc2626;cursor:pointer;font-size:1rem;padding:4px;}
</style>

<a href="produits.php" class="btn btn-secondary btn-sm" style="margin-bottom:18px"><i class="fas fa-arrow-left"></i> Retour</a>

<form method="POST" enctype="multipart/form-data">
  <?= csrf_input('produit_form') ?>
  <div style="display:grid;grid-template-columns:2fr 1fr;gap:22px">
    <div>
      <div class="card" style="padding:24px">
        <h3 style="font-family:'Poppins',sans-serif;font-size:.95rem;font-weight:700;margin-bottom:18px"><i class="fas fa-info-circle" style="color:var(--red);margin-right:6px"></i> Informations</h3>
        <div class="fgrid">
          <div class="fg"><label>Nom du produit *</label><input type="text" name="nom" value="<?= e($prod['nom']??'') ?>" required/></div>
          <div class="fg"><label>Modèle</label><input type="text" name="modele" value="<?= e($prod['modele']??'') ?>"/></div>
          <div class="fg"><label>Marque</label><input type="text" name="marque" value="<?= e($prod['marque']??'') ?>"/></div>
          <div class="fg"><label>Slug (URL)</label><input type="text" name="slug" value="<?= e($prod['slug']??'') ?>" placeholder="auto-généré"/></div>
        </div>
        <div class="fg" style="margin-top:16px"><label>Description courte</label><input type="text" name="description_courte" value="<?= e($prod['description_courte']??'') ?>" maxlength="300"/></div>
        <div class="fg" style="margin-top:16px"><label>Description complète</label><textarea name="description" rows="6"><?= e($prod['description']??'') ?></textarea></div>
      </div>

      <div class="card" style="padding:24px">
        <h3 style="font-family:'Poppins',sans-serif;font-size:.95rem;font-weight:700;margin-bottom:18px"><i class="fas fa-list" style="color:var(--red);margin-right:6px"></i> Caractéristiques techniques</h3>
        <div class="specs-builder" id="specsBuilder">
          <?php foreach ($specs as $spec): ?>
          <div class="spec-row">
            <input type="text" name="spec_label[]" value="<?= e($spec['label']??'') ?>" placeholder="Label (ex: Capacité)"/>
            <input type="text" name="spec_value[]" value="<?= e($spec['value']??'') ?>" placeholder="Valeur (ex: 16 Go)"/>
            <button type="button" class="remove-spec" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-secondary btn-sm" onclick="addSpec()" style="margin-top:8px"><i class="fas fa-plus"></i> Ajouter</button>
      </div>

      <div class="card" style="padding:24px">
        <h3 style="font-family:'Poppins',sans-serif;font-size:.95rem;font-weight:700;margin-bottom:18px"><i class="fas fa-tags" style="color:var(--red);margin-right:6px"></i> Tags</h3>
        <div class="fg"><label>Tags (séparés par des virgules)</label><input type="text" name="tags" value="<?= e($tags) ?>" placeholder="DDR4, 16Go, Kingston, Gaming"/></div>
      </div>
    </div>

    <div>
      <div class="card" style="padding:24px">
        <h3 style="font-family:'Poppins',sans-serif;font-size:.95rem;font-weight:700;margin-bottom:18px"><i class="fas fa-image" style="color:var(--red);margin-right:6px"></i> Image</h3>
        <?php $imgUrl=imgUrl($prod['image']??''); if($imgUrl): ?>
          <img src="<?= e($imgUrl) ?>" style="width:100%;max-height:180px;object-fit:contain;border-radius:10px;margin-bottom:12px;background:var(--soft)"/>
        <?php endif; ?>
        <div class="fg"><input type="file" name="image" accept="image/*"/><small>JPG, PNG, WebP (max 5 Mo)</small></div>
      </div>

      <div class="card" style="padding:24px">
        <h3 style="font-family:'Poppins',sans-serif;font-size:.95rem;font-weight:700;margin-bottom:18px"><i class="fas fa-cog" style="color:var(--red);margin-right:6px"></i> Options</h3>
        <div class="fg">
          <label>Catégories</label>
          <?php foreach ($cats as $cat): if($cat['slug']==='all') continue; ?>
            <label style="display:flex;align-items:center;gap:6px;font-size:.85rem;margin-bottom:4px;font-weight:400">
              <input type="checkbox" name="categories[]" value="<?= e($cat['slug']) ?>" <?= in_array($cat['slug'],$prodCats)?'checked':'' ?>/> <?= e($cat['nom']) ?>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="fg" style="margin-top:14px"><label>Disponibilité</label>
          <select name="disponibilite">
            <option value="en_stock" <?= ($prod['disponibilite']??'en_stock')==='en_stock'?'selected':'' ?>>En stock</option>
            <option value="sur_commande" <?= ($prod['disponibilite']??'')==='sur_commande'?'selected':'' ?>>Sur commande</option>
            <option value="rupture" <?= ($prod['disponibilite']??'')==='rupture'?'selected':'' ?>>Rupture</option>
          </select>
        </div>
        <div class="fg" style="margin-top:14px"><label>Badge texte</label><input type="text" name="badge_texte" value="<?= e($prod['badge_texte']??'') ?>" placeholder="Ex: Nouveau, Bestseller"/></div>
        <div class="fg" style="margin-top:14px"><label>Badge couleur</label><input type="color" name="badge_couleur" value="<?= e($prod['badge_couleur']??'#D32F2F') ?>" style="height:38px;padding:2px"/></div>
        <div class="fg" style="margin-top:14px"><label>Ordre d'affichage</label><input type="number" name="ordre" value="<?= e($prod['ordre']??0) ?>" min="0"/></div>
        <div style="display:flex;gap:24px;margin-top:14px">
          <label style="display:flex;align-items:center;gap:8px;font-size:.85rem">
            <input type="checkbox" name="statut" <?= ($prod['statut']??1)?'checked':'' ?>/> Actif
          </label>
          <label style="display:flex;align-items:center;gap:8px;font-size:.85rem">
            <input type="checkbox" name="en_vedette" <?= ($prod['en_vedette']??0)?'checked':'' ?>/> En vedette
          </label>
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;padding:14px;font-size:.95rem;margin-top:8px">
        <i class="fas fa-save"></i> <?= $prod ? 'Mettre à jour' : 'Créer le produit' ?>
      </button>
    </div>
  </div>
</form>

<script>
function addSpec(){
  var row = document.createElement('div'); row.className='spec-row';
  row.innerHTML='<input type="text" name="spec_label[]" placeholder="Label"/>'
    +'<input type="text" name="spec_value[]" placeholder="Valeur"/>'
    +'<button type="button" class="remove-spec" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>';
  document.getElementById('specsBuilder').appendChild(row);
}
</script>
<?php require '_bottom.php'; ?>
