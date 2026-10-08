<?php
// Shared list row; each page retains its existing permission checks and actions.
$item = $listReport;
$itemId = (int)$item['id'];
$itemStatus = $item['status'] ?? 'pending';
$itemRisk = $item['risk_level'] ?? 'low';
$itemUrl = BASE_URL . 'index.php?page=' . ($reportListContext === 'verify' ? 'manage-report' : 'track-status') . '&id=' . rawurlencode(IdGuard::enc($itemId));
$itemEsc = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
?>
<article class="report-feed-item<?php echo empty($item['cover_image']) ? ' report-feed-no-photo' : ''; ?>" data-report-id="<?php echo $itemId; ?>">
    <div class="report-feed-photo" aria-hidden="true">
        <?php if (!empty($item['cover_image'])): ?><img src="<?php echo $itemEsc(BASE_URL . $item['cover_image']); ?>" alt="" loading="lazy"><?php else: ?><i class="fas fa-leaf"></i><?php endif; ?>
    </div>
    <div class="report-feed-copy">
        <div class="report-feed-meta"><span>#<?php echo str_pad($itemId, 6, '0', STR_PAD_LEFT); ?></span><time datetime="<?php echo $itemEsc($item['created_at']); ?>"><?php echo date('M d, Y · g:i A', strtotime($item['created_at'])); ?></time></div>
        <h3><?php echo $itemEsc($item['title']); ?></h3>
        <p><?php echo $itemEsc($item['description'] ?? ''); ?></p>
        <div class="report-feed-badges">
            <span class="status-badge status-<?php echo $itemEsc($itemStatus); ?>"><?php echo t(ucwords(str_replace('_', ' ', $itemStatus))); ?></span>
            <?php if (!in_array($itemStatus, ['cancelled', 'rejected'])): ?><span class="risk-badge risk-<?php echo $itemEsc($itemRisk); ?>"><?php echo t(ucfirst($itemRisk)); ?></span><?php endif; ?>
            <?php if (!empty($item['decision_classification']) && !in_array($itemStatus, ['cancelled', 'rejected'])): ?><span class="severity-badge severity-<?php echo $itemEsc(strtolower($item['decision_pin'] ?? 'green')); ?>"><?php echo $itemEsc($item['decision_classification']); ?></span><?php endif; ?>
        </div>
        <div class="report-feed-meta"><span><i class="fas fa-tag"></i> <?php echo $itemEsc($item['category_name'] ?? 'Uncategorized'); ?></span><span><i class="fas fa-map-marker-alt"></i> <?php echo $itemEsc($item['barangay_name'] ?? ''); ?></span><?php if ($reportListContext === 'verify'): ?><span><i class="fas fa-user"></i> <?php echo $itemEsc($item['user_name'] ?? 'Unavailable account'); ?></span><?php else: ?><span><i class="fas fa-thumbs-up"></i> <?php echo (int)($item['verification_count'] ?? 0); ?> supporters</span><?php endif; ?></div>
    </div>
    <div class="report-feed-actions">
    <?php if ($reportListContext === 'verify'): ?>
        <?php if (in_array($itemStatus, ['pending', 'under_review', 'escalated_pending'])): ?><span><i class="fas fa-circle-exclamation"></i> Needs attention</span><?php endif; ?>
        <?php if ($itemStatus === 'resolved'): ?><a href="<?php echo $itemEsc($itemUrl); ?>" class="btn-manage"><i class="fas fa-eye"></i> View</a>
        <?php elseif (PermissionHelper::canManageReport($item)): ?><a href="<?php echo $itemEsc($itemUrl); ?>" class="btn-manage" data-report-status="<?php echo $itemEsc($itemStatus); ?>" data-report-id="<?php echo str_pad($itemId,6,'0',STR_PAD_LEFT); ?>" data-report-title="<?php echo $itemEsc($item['title']); ?>" onclick="return confirmUnderReview(event,this)"><i class="fas fa-pen-to-square"></i> Manage</a>
        <?php else: ?><span aria-disabled="true"><i class="fas fa-lock"></i> Manage</span><?php endif; ?>
    <?php else: ?>
        <?php if (($active_tab ?? '') === 'supported'): ?><span><i class="fas fa-heart"></i> You supported this</span>
        <?php elseif ((int)($item['user_id'] ?? 0) === (int)$_SESSION['user_id']): ?><span class="own-report-label"><i class="fas fa-user"></i> Your report</span>
        <?php elseif (!empty($item['is_verified_by_user'])): ?><span><i class="fas fa-check-circle"></i> Supported</span>
        <?php elseif (!in_array($itemStatus,['resolved','rejected','cancelled'])): ?><button type="button" class="verify-btn" onclick="verifyReport(<?php echo $itemId; ?>,this)"><i class="fas fa-thumbs-up"></i> Support</button><?php endif; ?>
        <a href="<?php echo $itemEsc($itemUrl); ?>"><i class="fas fa-arrow-right"></i> Track report</a>
    <?php endif; ?>
    </div>
</article>
