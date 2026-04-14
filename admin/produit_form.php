<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/auth.php';
requireAdmin();
gh_require_permission('produits','write');
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
$prod = null;
if ($id) {
    $s = $db->prepare("SELECT * FROM produits WHERE id=?");
    $s->execute([$id]);
    $prod = $s->fetch();
}

$pageTitle = $prod ? 'Modifier le produit' : 'Nouveau produit';
$activePage = 'produits';
$cats = getCategories();
$galleryImages = [];
if ($prod) {
    $s = $db->prepare("SELECT id, image, ordre FROM produit_images WHERE produit_id=? ORDER BY ordre ASC, id ASC");
    $s->execute([$prod['id']]);
    $galleryImages = $s->fetchAll();
}
$formError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_fail('produit_form');

    $data = [
        'nom' => limitString($_POST['nom'] ?? '', 200),
        'modele' => limitString($_POST['modele'] ?? '', 100),
        'marque' => limitString($_POST['marque'] ?? '', 100),
        'description_courte' => limitString($_POST['description_courte'] ?? '', 300),
        'description' => $_POST['description'] ?? '',
        'categories' => implode(' ', $_POST['categories'] ?? []),
        'badge_texte' => limitString($_POST['badge_texte'] ?? '', 60),
        'badge_couleur' => limitString($_POST['badge_couleur'] ?? '#D32F2F', 30),
        'disponibilite' => $_POST['disponibilite'] ?? 'en_stock',
        'statut' => isset($_POST['statut']) ? 1 : 0,
        'en_vedette' => isset($_POST['en_vedette']) ? 1 : 0,
        'ordre' => (int)($_POST['ordre'] ?? 0),
        'slug' => normalizeSlug($_POST['slug'] ?? $_POST['nom'] ?? ''),
    ];

    $specLabels = $_POST['spec_label'] ?? [];
    $specValues = $_POST['spec_value'] ?? [];
    $specs = [];
    foreach ($specLabels as $i => $label) {
        $label = trim($label);
        $val = trim($specValues[$i] ?? '');
        if ($label && $val) {
            $specs[] = ['label' => $label, 'value' => $val];
        }
    }
    $data['specifications'] = json_encode($specs, JSON_UNESCAPED_UNICODE);

    $tagsRaw = trim($_POST['tags'] ?? '');
    $tagsArr = array_filter(array_map('trim', explode(',', $tagsRaw)));
    $data['tags'] = json_encode(array_values($tagsArr), JSON_UNESCAPED_UNICODE);

    if (!empty($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $up = uploadImage($_FILES['image'], 'products');
        if (isset($up['success'])) {
            $data['image'] = $up['filename'];
        } else {
            $formError = $up['error'] ?? 'Erreur lors de l\'upload de l\'image principale.';
        }
    }

    $keepGallery = array_map('intval', $_POST['keep_gallery'] ?? []);
    $newGalleryPaths = [];
    if (!$formError && !empty($_FILES['catalogue_images']['name']) && is_array($_FILES['catalogue_images']['name'])) {
        foreach ($_FILES['catalogue_images']['name'] as $i => $name) {
            $error = $_FILES['catalogue_images']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $file = [
                'name' => $name,
                'type' => $_FILES['catalogue_images']['type'][$i] ?? '',
                'tmp_name' => $_FILES['catalogue_images']['tmp_name'][$i] ?? '',
                'error' => $error,
                'size' => $_FILES['catalogue_images']['size'][$i] ?? 0,
            ];
            $up = uploadImage($file, 'products/catalogue');
            if (isset($up['success'])) {
                $newGalleryPaths[] = $up['filename'];
            } else {
                $formError = $up['error'] ?? 'Erreur lors de l\'upload des photos galerie.';
                break;
            }
        }
    }

    if (!$formError) {
        try {
            $db->beginTransaction();

            if ($prod) {
                if (!isset($data['image'])) {
                    $data['image'] = $prod['image'];
                }
                $cols = implode('=?,', array_keys($data)).'=?';
                $vals = array_values($data);
                $vals[] = $id;
                $db->prepare("UPDATE produits SET $cols WHERE id=?")->execute($vals);
                $productId = $id;
            } else {
                $cols = implode(',', array_keys($data));
                $phs = implode(',', array_fill(0, count($data), '?'));
                $db->prepare("INSERT INTO produits ($cols) VALUES ($phs)")->execute(array_values($data));
                $productId = (int)$db->lastInsertId();
            }

            if ($prod) {
                if ($keepGallery) {
                    $placeholders = implode(',', array_fill(0, count($keepGallery), '?'));
                    $params = array_merge([$productId], $keepGallery);
                    $db->prepare("DELETE FROM produit_images WHERE produit_id=? AND id NOT IN ($placeholders)")->execute($params);
                } else {
                    $db->prepare("DELETE FROM produit_images WHERE produit_id=?")->execute([$productId]);
                }
            }

            if ($newGalleryPaths) {
                $s = $db->prepare("SELECT COALESCE(MAX(ordre), -1) FROM produit_images WHERE produit_id=?");
                $s->execute([$productId]);
                $nextOrder = ((int)$s->fetchColumn()) + 1;
                $insertImage = $db->prepare("INSERT INTO produit_images (produit_id, image, ordre) VALUES (?, ?, ?)");
                foreach ($newGalleryPaths as $path) {
                    $insertImage->execute([$productId, $path, $nextOrder]);
                    $nextOrder++;
                }
            }

            $s = $db->prepare("SELECT image FROM produit_images WHERE produit_id=? ORDER BY ordre ASC, id ASC");
            $s->execute([$productId]);
            $galleryPaths = array_values(array_filter($s->fetchAll(PDO::FETCH_COLUMN)));
            $db->prepare("UPDATE produits SET images_galerie=? WHERE id=?")->execute([
                json_encode($galleryPaths, JSON_UNESCAPED_UNICODE),
                $productId,
            ]);

            if (empty($data['image']) && !empty($galleryPaths[0])) {
                $db->prepare("UPDATE produits SET image=? WHERE id=?")->execute([$galleryPaths[0], $productId]);
            }

            $db->commit();
            setFlash('success', $prod ? 'Produit mis a jour.' : 'Produit cree.');
            header('Location: produits.php');
            exit;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $formError = 'Erreur lors de l\'enregistrement du produit.';
        }
    }

    $prod = array_merge($prod ?: [], $data);

    if ($prod && $id) {
        $s = $db->prepare("SELECT id, image, ordre FROM produit_images WHERE produit_id=? ORDER BY ordre ASC, id ASC");
        $s->execute([$id]);
        $galleryImages = $s->fetchAll();
    }
}

$specs = $prod ? jdecode($prod['specifications']) : [];
$tags = $prod ? implode(', ', jdecode($prod['tags'])) : '';
$prodCats = $prod ? explode(' ', $prod['categories'] ?? '') : [];

require '_top.php';
?>
<style>
.specs-builder{margin-top:8px;}
.spec-row{display:flex;gap:8px;margin-bottom:8px;align-items:center;}
.spec-row input{flex:1;}
.spec-row .remove-spec{background:none;border:none;color:#dc2626;cursor:pointer;font-size:1rem;padding:4px;}
.catalogue-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:12px;margin-bottom:14px;}
.catalogue-item,.catalogue-preview{border:1px solid var(--border);border-radius:12px;padding:8px;background:#fff;}
.catalogue-item img,.catalogue-preview img{width:100%;height:95px;object-fit:contain;background:var(--soft);border-radius:8px;display:block;margin-bottom:8px;}
.catalogue-item label{display:flex;align-items:flex-start;gap:6px;font-size:.78rem;color:var(--muted);line-height:1.3;}
.catalogue-preview small{display:block;color:var(--muted);font-size:.76rem;word-break:break-word;}
.preview-title{font-size:.82rem;font-weight:700;margin:12px 0 10px;}
.catalogue-input-list{display:flex;flex-direction:column;gap:10px;margin-top:10px;}
.catalogue-input-row{display:flex;align-items:center;gap:10px;}
.catalogue-input-row input[type=file]{flex:1;}
.catalogue-input-row button{flex:0 0 auto;}
.product-form-sidebar{display:flex;flex-direction:column;gap:16px;position:sticky;top:18px;align-self:start;}
.category-card-panel{padding:24px;}
.category-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:10px;}
.category-card{position:relative;display:flex;align-items:flex-start;gap:10px;padding:12px 14px;border:1px solid var(--border);border-radius:12px;background:#fff;cursor:pointer;transition:border-color .2s,box-shadow .2s,transform .2s;}
.category-card:hover{border-color:var(--red);box-shadow:0 8px 18px rgba(0,0,0,.06);transform:translateY(-1px);}
.category-card input{position:absolute;opacity:0;pointer-events:none;}
.category-check{width:18px;height:18px;border:2px solid #cbd5e1;border-radius:6px;flex:0 0 18px;margin-top:2px;position:relative;transition:all .2s;}
.category-check::after{content:'';position:absolute;left:4px;top:0px;width:5px;height:10px;border:solid #fff;border-width:0 2px 2px 0;transform:rotate(45deg) scale(0);transition:transform .2s;}
.category-card-body{display:flex;flex-direction:column;gap:3px;min-width:0;}
.category-card-title{font-size:.9rem;font-weight:700;color:var(--text);}
.category-card-slug{font-size:.75rem;color:var(--muted);}
.category-card input:checked + .category-check{background:var(--red);border-color:var(--red);}
.category-card input:checked + .category-check::after{transform:rotate(45deg) scale(1);}
.category-card input:focus-visible + .category-check{box-shadow:0 0 0 3px rgba(211,47,47,.18);}
.category-card:has(input:checked) .category-card-title{color:var(--red);}
.category-card:has(input:checked){border-color:var(--red);background:rgba(211,47,47,.04);box-shadow:0 10px 24px rgba(211,47,47,.10);}
.category-help{display:block;margin-top:8px;color:var(--muted);font-size:.78rem;}
@media (max-width: 980px){
  .product-form-layout{grid-template-columns:1fr !important;}
  .product-form-sidebar{position:static;}
  .category-grid{grid-template-columns:1fr;}
}
</style>

<a href="produits.php" class="btn btn-secondary btn-sm" style="margin-bottom:18px"><i class="fas fa-arrow-left"></i> Retour</a>

<?php if ($formError): ?>
  <div class="alert alert-danger" style="margin-bottom:18px"><?= e($formError) ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
  <?= csrf_input('produit_form') ?>
  <div class="product-form-layout" style="display:grid;grid-template-columns:2fr 1fr;gap:22px">
    <div>
      <div class="card" style="padding:24px">
        <h3 style="font-family:'Poppins',sans-serif;font-size:.95rem;font-weight:700;margin-bottom:18px"><i class="fas fa-info-circle" style="color:var(--red);margin-right:6px"></i> Informations</h3>
        <div class="fgrid">
          <div class="fg"><label>Nom du produit *</label><input type="text" name="nom" value="<?= e($prod['nom'] ?? '') ?>" required/></div>
          <div class="fg"><label>Modele</label><input type="text" name="modele" value="<?= e($prod['modele'] ?? '') ?>"/></div>
          <div class="fg"><label>Marque</label><input type="text" name="marque" value="<?= e($prod['marque'] ?? '') ?>"/></div>
          <div class="fg"><label>Slug (URL)</label><input type="text" name="slug" value="<?= e($prod['slug'] ?? '') ?>" placeholder="auto-genere"/></div>
        </div>
        <div class="fg" style="margin-top:16px"><label>Description courte</label><input type="text" name="description_courte" value="<?= e($prod['description_courte'] ?? '') ?>" maxlength="300"/></div>
        <div class="fg" style="margin-top:16px"><label>Description complete</label><textarea name="description" rows="6"><?= e($prod['description'] ?? '') ?></textarea></div>
      </div>

      <div class="card" style="padding:24px">
        <h3 style="font-family:'Poppins',sans-serif;font-size:.95rem;font-weight:700;margin-bottom:18px"><i class="fas fa-list" style="color:var(--red);margin-right:6px"></i> Caracteristiques techniques</h3>
        <div class="specs-builder" id="specsBuilder">
          <?php foreach ($specs as $spec): ?>
          <div class="spec-row">
            <input type="text" name="spec_label[]" value="<?= e($spec['label'] ?? '') ?>" placeholder="Label (ex: Capacite)"/>
            <input type="text" name="spec_value[]" value="<?= e($spec['value'] ?? '') ?>" placeholder="Valeur (ex: 16 Go)"/>
            <button type="button" class="remove-spec" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-secondary btn-sm" onclick="addSpec()" style="margin-top:8px"><i class="fas fa-plus"></i> Ajouter</button>
      </div>

      <div class="card" style="padding:24px">
        <h3 style="font-family:'Poppins',sans-serif;font-size:.95rem;font-weight:700;margin-bottom:18px"><i class="fas fa-tags" style="color:var(--red);margin-right:6px"></i> Tags</h3>
        <div class="fg"><label>Tags (separes par des virgules)</label><input type="text" name="tags" value="<?= e($tags) ?>" placeholder="DDR4, 16Go, Kingston, Gaming"/></div>
      </div>

      <div class="card category-card-panel">
        <h3 style="font-family:'Poppins',sans-serif;font-size:.95rem;font-weight:700;margin-bottom:18px"><i class="fas fa-folder-open" style="color:var(--red);margin-right:6px"></i> Categories</h3>
        <div class="category-grid">
          <?php foreach ($cats as $cat): if ($cat['slug'] === 'all') continue; ?>
            <label class="category-card">
              <input type="checkbox" name="categories[]" value="<?= e($cat['slug']) ?>" <?= in_array($cat['slug'], $prodCats, true) ? 'checked' : '' ?>/>
              <span class="category-check" aria-hidden="true"></span>
              <span class="category-card-body">
                <span class="category-card-title"><?= e($cat['nom']) ?></span>
                <span class="category-card-slug"><?= e($cat['slug']) ?></span>
              </span>
            </label>
          <?php endforeach; ?>
        </div>
        <small class="category-help">Selectionnez une ou plusieurs categories pour le produit.</small>
      </div>
    </div>

    <div class="product-form-sidebar">
      <div class="card" style="padding:24px">
        <h3 style="font-family:'Poppins',sans-serif;font-size:.95rem;font-weight:700;margin-bottom:18px"><i class="fas fa-image" style="color:var(--red);margin-right:6px"></i> Images</h3>
        <?php $imgUrl = imgUrl($prod['image'] ?? ''); if ($imgUrl): ?>
          <img src="<?= e($imgUrl) ?>" style="width:100%;max-height:180px;object-fit:contain;border-radius:10px;margin-bottom:12px;background:var(--soft)"/>
        <?php endif; ?>
        <div class="fg" style="margin-bottom:14px">
          <input type="file" name="image" accept="image/*"/>
          <small>Image principale. JPG, PNG, WebP, GIF (max 5 Mo)</small>
        </div>

        <h4 style="font-size:.85rem;font-weight:700;margin:16px 0 10px">Photos galerie deja enregistrees</h4>
        <?php if ($galleryImages): ?>
          <div class="catalogue-grid">
            <?php foreach ($galleryImages as $galleryImage): $galleryUrl = imgUrl($galleryImage['image'] ?? ''); if (!$galleryUrl) continue; ?>
              <div class="catalogue-item">
                <img src="<?= e($galleryUrl) ?>" alt="Photo galerie"/>
                <label>
                  <input type="checkbox" name="keep_gallery[]" value="<?= (int)$galleryImage['id'] ?>" checked/>
                  <span>Conserver cette photo</span>
                </label>
              </div>
            <?php endforeach; ?>
          </div>
          <small style="display:block;margin-bottom:12px">Decochez une photo pour la supprimer de la galerie.</small>
        <?php else: ?>
          <small style="display:block;margin-bottom:12px">Aucune photo galerie enregistree pour ce produit.</small>
        <?php endif; ?>

        <div class="fg">
          <small>Ajoutez plusieurs photos une par une. Elles seront affichees juste en dessous avant enregistrement.</small>
          <div class="catalogue-input-list" id="catalogueInputList"></div>
          <button type="button" class="btn btn-secondary btn-sm" id="addCatalogueInput" style="margin-top:10px"><i class="fas fa-plus"></i> Ajouter une photo</button>
        </div>
        <div class="preview-title">Apercu des nouvelles photos</div>
        <div class="catalogue-grid" id="cataloguePreview"></div>
      </div>

      <div class="card" style="padding:24px">
        <h3 style="font-family:'Poppins',sans-serif;font-size:.95rem;font-weight:700;margin-bottom:18px"><i class="fas fa-cog" style="color:var(--red);margin-right:6px"></i> Options</h3>
        <div class="fg" style="margin-top:14px"><label>Disponibilite</label>
          <select name="disponibilite">
            <option value="en_stock" <?= ($prod['disponibilite'] ?? 'en_stock') === 'en_stock' ? 'selected' : '' ?>>En stock</option>
            <option value="sur_commande" <?= ($prod['disponibilite'] ?? '') === 'sur_commande' ? 'selected' : '' ?>>Sur commande</option>
            <option value="rupture" <?= ($prod['disponibilite'] ?? '') === 'rupture' ? 'selected' : '' ?>>Rupture</option>
          </select>
        </div>
        <div class="fg" style="margin-top:14px"><label>Badge texte</label><input type="text" name="badge_texte" value="<?= e($prod['badge_texte'] ?? '') ?>" placeholder="Ex: Nouveau, Bestseller"/></div>
        <div class="fg" style="margin-top:14px"><label>Badge couleur</label><input type="color" name="badge_couleur" value="<?= e($prod['badge_couleur'] ?? '#D32F2F') ?>" style="height:38px;padding:2px"/></div>
        <div class="fg" style="margin-top:14px"><label>Ordre d'affichage</label><input type="number" name="ordre" value="<?= e($prod['ordre'] ?? 0) ?>" min="0"/></div>
        <div style="display:flex;gap:24px;margin-top:14px">
          <label style="display:flex;align-items:center;gap:8px;font-size:.85rem">
            <input type="checkbox" name="statut" <?= ($prod['statut'] ?? 1) ? 'checked' : '' ?>/> Actif
          </label>
          <label style="display:flex;align-items:center;gap:8px;font-size:.85rem">
            <input type="checkbox" name="en_vedette" <?= ($prod['en_vedette'] ?? 0) ? 'checked' : '' ?>/> En vedette
          </label>
        </div>
        <div style="margin-top:20px;padding-top:18px;border-top:1px solid var(--border)">
          <button type="submit" class="btn btn-primary" style="width:100%;padding:14px;font-size:.95rem">
            <i class="fas fa-save"></i> <?= $prod ? 'Mettre a jour' : 'Creer le produit' ?>
          </button>
        </div>
      </div>
    </div>
  </div>
</form>

<script>
function addSpec() {
  var row = document.createElement('div');
  row.className = 'spec-row';
  row.innerHTML = '<input type="text" name="spec_label[]" placeholder="Label"/>'
    + '<input type="text" name="spec_value[]" placeholder="Valeur"/>'
    + '<button type="button" class="remove-spec" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>';
  document.getElementById('specsBuilder').appendChild(row);
}

var galleryInputList = document.getElementById('catalogueInputList');
var addCatalogueInputBtn = document.getElementById('addCatalogueInput');
var previewContainer = document.getElementById('cataloguePreview');

function refreshCataloguePreview() {
  if (!previewContainer || !galleryInputList) return;
  previewContainer.innerHTML = '';
  var index = 0;
  var inputs = galleryInputList.querySelectorAll('input[type=file]');
  inputs.forEach(function (input) {
    Array.prototype.forEach.call(input.files || [], function (file) {
      if (!file.type || file.type.indexOf('image/') !== 0) return;
      var currentIndex = index;
      index++;
      var reader = new FileReader();
      reader.onload = function (event) {
        var item = document.createElement('div');
        item.className = 'catalogue-preview';
        item.innerHTML = '<img src="' + event.target.result + '" alt="Apercu photo"/>'
          + '<small>Photo ' + (currentIndex + 1) + ': ' + file.name + '</small>';
        previewContainer.appendChild(item);
      };
      reader.readAsDataURL(file);
    });
  });
}

function addCatalogueInput() {
  if (!galleryInputList) return;
  var row = document.createElement('div');
  row.className = 'catalogue-input-row';
  row.innerHTML = '<input type="file" name="catalogue_images[]" accept="image/*"/>'
    + '<button type="button" class="btn btn-danger btn-sm"><i class="fas fa-times"></i></button>';
  var input = row.querySelector('input[type=file]');
  var removeBtn = row.querySelector('button');
  input.addEventListener('change', refreshCataloguePreview);
  removeBtn.addEventListener('click', function () {
    row.remove();
    refreshCataloguePreview();
  });
  galleryInputList.appendChild(row);
}

if (addCatalogueInputBtn && galleryInputList) {
  addCatalogueInputBtn.addEventListener('click', addCatalogueInput);
  addCatalogueInput();
}
</script>
<?php require '_bottom.php'; ?>
