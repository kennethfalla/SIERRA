<?php
// Both detail pages use the same milestones; dates come only from stored timestamps.
$progressStatus = $report['status'] ?? 'pending';
$progressTerminal = in_array($progressStatus, ['rejected', 'cancelled'], true);
$progressEscalated = in_array($progressStatus, ['escalated_pending', 'escalated'], true)
    || !empty($report['escalated_at']);
$progressSteps = [
    ['key' => 'pending', 'label' => 'Submitted', 'date' => $report['created_at'] ?? null],
];
if (!$progressTerminal || !empty($report['viewed_at'])) {
    $progressSteps[] = ['key'=>'under_review', 'label'=>'Under Review', 'date'=>$report['viewed_at'] ?? null];
}
if (!$progressTerminal || !empty($report['verified_at'])) {
    $progressSteps[] = ['key'=>'in_progress', 'label'=>'In Progress', 'date'=>$report['verified_at'] ?? null];
}
if ($progressEscalated) {
    $progressSteps[] = ['key'=>'escalated_pending', 'label'=>'Escalation', 'date'=>$report['escalated_at'] ?? null];
    if (!$progressTerminal || !empty($report['approved_at'])) {
        $progressSteps[] = ['key'=>'escalated', 'label'=>'With MENRO', 'date'=>$report['approved_at'] ?? null];
    }
}
$progressSteps[] = $progressTerminal
    ? ['key'=>$progressStatus, 'label'=>ucfirst($progressStatus), 'date'=>$report[$progressStatus . '_at'] ?? null]
    : ['key'=>'resolved', 'label'=>'Resolved', 'date'=>$report['resolved_at'] ?? null];
$progressKeys = array_column($progressSteps, 'key');
$progressLookup = $progressStatus === 'verified' ? 'in_progress' : ($progressStatus === 'closed' ? 'resolved' : $progressStatus);
$progressIndex = array_search($progressLookup, $progressKeys, true);
$progressIndex = $progressIndex === false ? 0 : $progressIndex;
$progressFinished = in_array($progressStatus, ['resolved', 'closed', 'rejected', 'cancelled'], true);
$progressAwaitingConfirmation = ($reportProgressContext ?? '') === 'citizen'
    && $progressStatus === 'resolved' && empty($report['resolution_confirmed']);
$progressTranslate = static fn($text) => function_exists('t') ? t($text) : $text;
?>
<?php if (($reportProgressMode ?? 'summary') === 'history'): ?>
<section class="report-history" aria-label="<?php echo $progressTranslate('Report Timeline'); ?>">
    <h3 class="card-header"><i class="fas fa-clock-rotate-left" aria-hidden="true"></i> <?php echo $progressTranslate('Report Timeline'); ?></h3>
    <ol class="report-history-list">
    <?php foreach ($progressSteps as $progressStepIndex => $progressStep):
        $progressState = $progressStepIndex < $progressIndex || ($progressStepIndex === $progressIndex && $progressFinished) ? 'completed' : ($progressStepIndex === $progressIndex ? 'current' : 'upcoming');
    ?>
        <li class="report-history-event <?php echo $progressState; ?><?php echo $progressTerminal && $progressStepIndex === $progressIndex ? ' terminal-' . $progressStatus : ''; ?>" <?php if ($progressStepIndex === $progressIndex): ?>aria-current="step"<?php endif; ?>>
            <span class="report-history-dot" aria-hidden="true"><?php if ($progressState === 'completed'): ?><i class="fas <?php echo $progressTerminal && $progressStepIndex === $progressIndex ? 'fa-xmark' : 'fa-check'; ?>"></i><?php endif; ?></span>
            <div class="report-history-copy"><h4><?php echo $progressTranslate($progressStep['label']); ?></h4><p><?php echo $progressTranslate($progressStepIndex === $progressIndex && $progressAwaitingConfirmation ? 'Awaiting Confirmation' : ($progressState === 'current' ? 'Current stage' : ($progressState === 'upcoming' ? 'Upcoming' : 'Completed'))); ?></p></div>
            <?php if ($progressStepIndex <= $progressIndex && !empty($progressStep['date']) && strtotime($progressStep['date']) !== false): ?>
            <time datetime="<?php echo date('c', strtotime($progressStep['date'])); ?>"><?php echo date('M d, Y', strtotime($progressStep['date'])); ?><span><?php echo date('g:i A', strtotime($progressStep['date'])); ?></span></time>
            <?php else: ?>
            <span class="report-history-date-empty"><?php echo $progressTranslate($progressState === 'upcoming' ? 'Not yet reached' : 'Date not recorded'); ?></span>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
    </ol>
</section>
<?php else: ?>
<nav class="report-progress-summary" aria-label="<?php echo $progressTranslate('Report Progress'); ?>">
    <ol class="report-progress-segments" style="--progress-count:<?php echo count($progressSteps); ?>">
    <?php foreach ($progressSteps as $progressStepIndex => $progressStep): ?>
        <li class="<?php echo $progressStepIndex <= $progressIndex ? 'reached' : ''; ?><?php echo $progressTerminal && $progressStepIndex === $progressIndex ? ' terminal-' . $progressStatus : ''; ?>" <?php if ($progressStepIndex === $progressIndex): ?>aria-current="step"<?php endif; ?>><span><?php echo $progressTranslate($progressStep['label']); ?></span></li>
    <?php endforeach; ?>
    </ol>
</nav>
<?php endif; ?>
