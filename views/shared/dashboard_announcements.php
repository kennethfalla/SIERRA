<?php
// Match the announcement page's audience rules for every dashboard.
$dashAnnRole = $_SESSION['user_role'] ?? 'citizen';
$dashAnnBarangay = $_SESSION['barangay_id'] ?? null;
$dashAnnResident = (int)($_SESSION['is_resident'] ?? ($dashAnnBarangay !== null ? 1 : 0));
$dashAnnWhere = 'a.is_active = 1 AND a.is_archived = 0 AND (a.expires_at IS NULL OR a.expires_at > NOW())';
$dashAnnParams = [];
if ($dashAnnRole === 'barangay_official') {
    $dashAnnWhere .= " AND (a.broadcast_type IN ('global_public', 'internal_global')
        OR (a.broadcast_type = 'localized_public' AND a.barangay_id = ?)
        OR (a.broadcast_type = 'internal_direct' AND (a.target_admin_id = ? OR (a.barangay_id = ? AND a.target_admin_id IS NULL))))";
    $dashAnnParams = [$dashAnnBarangay, $_SESSION['user_id'], $dashAnnBarangay];
} elseif ($dashAnnRole !== 'admin') {
    $dashAnnWhere .= $dashAnnResident
        ? " AND (a.broadcast_type = 'global_public' OR (a.broadcast_type = 'localized_public' AND a.barangay_id = ?))"
        : " AND a.broadcast_type = 'global_public'";
    if ($dashAnnResident) $dashAnnParams[] = $dashAnnBarangay;
}
$dashAnnStmt = $db->prepare("SELECT a.id, a.title, a.content, a.created_at FROM announcements a WHERE $dashAnnWhere ORDER BY a.created_at DESC LIMIT 8");
$dashAnnStmt->execute($dashAnnParams);
$dashAnnouncements = $dashAnnStmt->fetchAll(PDO::FETCH_ASSOC);
$dashAnnImages = [];
if ($dashAnnouncements) {
    $dashAnnIds = array_column($dashAnnouncements, 'id');
    $dashAnnImageStmt = $db->prepare('SELECT announcement_id, image_path FROM announcement_images WHERE announcement_id IN (' . implode(',', array_fill(0, count($dashAnnIds), '?')) . ') ORDER BY id');
    $dashAnnImageStmt->execute($dashAnnIds);
    foreach ($dashAnnImageStmt->fetchAll(PDO::FETCH_ASSOC) as $dashAnnImage) {
        $dashAnnImages[$dashAnnImage['announcement_id']][] = $dashAnnImage['image_path'];
    }
}
$dashAnnSlides = [];
foreach ($dashAnnouncements as $dashAnn) {
    $dashAnnPhotos = $dashAnnImages[$dashAnn['id']] ?? [];
    $dashAnnSlides[] = ['post' => $dashAnn, 'photo' => $dashAnnPhotos[0] ?? ''];
}
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/dashboard-announcements.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/dashboard-announcements.css'); ?>">
<section class="announce-carousel" aria-label="Community announcements" aria-roledescription="carousel" tabindex="0">
    <div class="announce-carousel-heading">
        <span><i class="fas fa-bullhorn" aria-hidden="true"></i> <?php echo t($dashAnnHeading ?? 'Announcements'); ?></span>
        <a href="<?php echo BASE_URL; ?>index.php?page=announcements"><?php echo t('View all'); ?> <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
    </div>
    <div class="announce-slides">
        <?php foreach ($dashAnnSlides as $dashAnnIndex => $dashAnnSlide): $dashAnn = $dashAnnSlide['post']; ?>
        <article class="announce-slide" <?php echo $dashAnnIndex ? 'hidden' : ''; ?> aria-label="<?php echo ($dashAnnIndex + 1) . ' / ' . count($dashAnnSlides); ?>">
            <?php if ($dashAnnSlide['photo']): ?>
                <img class="announce-photo" src="<?php echo htmlspecialchars(BASE_URL . $dashAnnSlide['photo'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($dashAnn['title'], ENT_QUOTES, 'UTF-8'); ?>" loading="lazy">
            <?php else: ?>
                <div class="announce-photo announce-photo-empty" aria-hidden="true"><i class="fas fa-bullhorn"></i></div>
            <?php endif; ?>
            <div class="announce-slide-copy">
                <time datetime="<?php echo date('Y-m-d', strtotime($dashAnn['created_at'])); ?>"><?php echo date('M d, Y', strtotime($dashAnn['created_at'])); ?></time>
                <h2><?php echo htmlspecialchars($dashAnn['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
                <p><?php echo htmlspecialchars(html_entity_decode(strip_tags($dashAnn['content']), ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </article>
        <?php endforeach; ?>
        <?php if (!$dashAnnSlides): ?><p class="announce-empty"><?php echo t('No announcements yet.'); ?></p><?php endif; ?>
    </div>
    <?php if (count($dashAnnSlides) > 1): ?>
    <div class="announce-controls">
        <button type="button" data-ann-prev aria-label="Previous announcement"><i class="fas fa-chevron-left" aria-hidden="true"></i></button>
        <span data-ann-count aria-live="off"><?php echo count($dashAnnSlides); ?> announcements</span>
        <button type="button" data-ann-next aria-label="Next announcement"><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
    </div>
    <?php endif; ?>
</section>
<script src="<?php echo BASE_URL; ?>assets/js/dashboard-announcements.js?v=<?php echo filemtime(BASE_PATH . 'assets/js/dashboard-announcements.js'); ?>" defer></script>
