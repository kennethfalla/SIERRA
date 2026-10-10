<?php
$reasonSuggestions = $reasonSuggestions ?? [
    'This issue is already covered by an existing report.',
    'The location or details could not be verified.',
    'The evidence does not support the reported issue.',
    'The issue is outside this office’s jurisdiction.',
];
$reasonPickerIndex = ($reasonPickerIndex ?? 0) + 1;
$reasonInputId = 'report-custom-reason-' . $reasonPickerIndex;
?>
<fieldset class="report-reason-picker">
    <legend class="font-semibold text-sm mb-3">Choose a reason</legend>
    <div class="report-reason-options">
        <?php foreach (array_merge($reasonSuggestions, ['Other']) as $reason): ?>
        <label class="report-reason-option"><input type="radio" name="reason_choice" value="<?php echo htmlspecialchars($reason, ENT_QUOTES, 'UTF-8'); ?>" required onchange="syncReportReason(this)"><span><?php echo htmlspecialchars($reason); ?></span></label>
        <?php endforeach; ?>
    </div>
    <div class="report-custom-reason" hidden>
    <label for="<?php echo $reasonInputId; ?>" class="block text-sm font-semibold mt-4 mb-2">Other reason</label>
    <textarea id="<?php echo $reasonInputId; ?>" name="custom_reason" disabled rows="3" minlength="5" maxlength="1000" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm" placeholder="Write your own reason…" oninput="syncReportReason(this)"></textarea>
    </div>
    <input type="hidden" name="rejection_reason" value="">
</fieldset>
