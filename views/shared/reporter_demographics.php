<div class="reporter-demographics">
    <div class="reporter-demographics-chart"><canvas id="demographicsChart" role="img" aria-label="Unique reporters by residency"></canvas><span><strong><?php echo (int)$demographicsTotal; ?></strong>reporters</span></div>
    <dl class="reporter-demographics-legend">
    <?php foreach (['resident'=>['Residents','#10A37F'], 'non_resident'=>['Non-residents','#F59E0B'], 'unknown'=>['Not recorded','#94a3b8']] as $demoKey=>$demoGroup): if ($demoKey === 'unknown' && empty($demographics[$demoKey])) continue; ?>
        <div><dt><i style="background:<?php echo $demoGroup[1]; ?>"></i><?php echo t($demoGroup[0]); ?></dt><dd><strong><?php echo (int)($demographics[$demoKey] ?? 0); ?></strong><span><?php echo $demographicsTotal ? round(100 * ($demographics[$demoKey] ?? 0) / $demographicsTotal,1) : 0; ?>%</span></dd></div>
    <?php endforeach; ?>
    </dl>
</div>
<p class="demographics-note">Each reporter is counted once, using their current residency profile.</p>
