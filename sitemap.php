<?php
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/functions.php';

header('Content-Type: application/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url><loc><?= SITE_URL ?>/</loc><changefreq>weekly</changefreq><priority>1.0</priority></url>
  <?php foreach (getProduits() as $pr): ?>
  <url><loc><?= SITE_URL ?>/produit.php?slug=<?= urlencode($pr['slug']??'') ?></loc><changefreq>monthly</changefreq><priority>0.8</priority></url>
  <?php endforeach; ?>
</urlset>
