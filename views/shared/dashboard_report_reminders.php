<?php
// Scoped to the authenticated staff account, never to editable dashboard filters.
$reportFollowups = ['enabled' => false, 'total' => 0, 'days' => 3, 'pending' => 0, 'escalated' => 0, 'reports' => []];
try {
    ReportReminder::tick($db, (int)$_SESSION['user_id']);
    $reportFollowups = (new ReportReminder($db))->summaryForUser((int)$_SESSION['user_id']);
} catch (Throwable $error) { error_log('[Dashboard report reminders] ' . $error->getMessage()); }
?>
<section id="reportFollowups" class="report-followups" aria-labelledby="reportFollowupsTitle" <?php if (!$reportFollowups['enabled']) echo 'hidden'; ?>>
    <header class="report-followups-heading">
        <span class="report-followups-icon"><i class="fas fa-clock" aria-hidden="true"></i></span>
        <div><h2 id="reportFollowupsTitle">Reports needing follow-up <span data-followup-total><?php echo (int)$reportFollowups['total']; ?></span></h2>
        <p>Waiting <span data-followup-days><?php echo (int)$reportFollowups['days']; ?></span> days or longer</p></div>
        <div class="report-followups-counts"><span>Pending <b data-followup-pending><?php echo (int)$reportFollowups['pending']; ?></b></span><span>Escalated <b data-followup-escalated><?php echo (int)$reportFollowups['escalated']; ?></b></span></div>
    </header>
    <div class="report-followups-list" data-followup-list>
    <?php foreach ($reportFollowups['reports'] as $followup): ?>
        <a class="report-followups-row" href="<?php echo htmlspecialchars(BASE_URL . $followup['url'], ENT_QUOTES, 'UTF-8'); ?>">
            <span><strong><?php echo htmlspecialchars($followup['title'], ENT_QUOTES, 'UTF-8'); ?></strong><small><?php echo htmlspecialchars($followup['barangay_name'] ?: 'San Isidro', ENT_QUOTES, 'UTF-8'); ?> · <?php echo $followup['status'] === 'pending' ? 'Pending review' : 'MENRO follow-up'; ?></small></span>
            <span class="report-followups-age"><?php echo (int)$followup['age_days']; ?>d waiting <i class="fas fa-arrow-right" aria-hidden="true"></i></span>
        </a>
    <?php endforeach; ?>
    </div>
    <p class="report-followups-empty" data-followup-empty <?php if ($reportFollowups['total']) echo 'hidden'; ?>><i class="fas fa-check-circle" aria-hidden="true"></i> No overdue reports to follow up.</p>
    <small class="report-followups-more" data-followup-more <?php if ($reportFollowups['total'] <= 5) echo 'hidden'; ?>>Showing the 5 longest-waiting reports.</small>
</section>
<script src="<?php echo BASE_URL; ?>assets/js/report-reminders.js?v=<?php echo filemtime(BASE_PATH . 'assets/js/report-reminders.js'); ?>"></script>
