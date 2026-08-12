<?php if(count($reports) > 0): ?>
    <?php foreach($reports as $row): 
        $risk_level = isset($row['risk_level']) ? $row['risk_level'] : 'low';
        $risk_labels = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'];
        $risk_icons = ['low' => 'fa-seedling', 'medium' => 'fa-exclamation-triangle', 'high' => 'fa-fire', 'critical' => 'fa-skull-crossbones'];
    ?>
    <div class="report-card">
        <div class="report-card-header bg-gradient-to-r from-[#10A37F] to-[#0D8568] rounded-t-2xl p-4 md:p-5 text-white">
            <div class="flex flex-col sm:flex-row justify-between items-start gap-3">
                <div class="space-y-2">
                    <div class="flex items-center gap-2 text-[10px] uppercase tracking-[0.14em] opacity-90 font-semibold">
                        <div class="w-5 h-5 md:w-6 md:h-6 bg-white/20 rounded-lg flex items-center justify-center">
                            <i class="fas fa-file-alt text-white/70 text-[10px] md:text-xs"></i>
                        </div>
                        <span>Report Summary</span>
                    </div>
                    <h3 class="text-base md:text-lg font-bold leading-tight"><?php echo htmlspecialchars($row['title']); ?></h3>
                </div>
                <div class="text-right text-xs md:text-sm opacity-90">
                    <div>#<?php echo str_pad($row['id'], 6, '0', STR_PAD_LEFT); ?></div>
                    <div class="mt-1"><?php echo date('M d, Y', strtotime($row['created_at'])); ?></div>
                </div>
            </div>
            <div class="mt-4 flex flex-wrap gap-2 items-center">
                <span class="status-badge status-<?php echo $row['status']; ?> header-badge">
                    <i class="fas <?php echo $row['status'] == 'pending' ? 'fa-clock' : ($row['status'] == 'resolved' ? 'fa-check-circle' : 'fa-check'); ?> text-[10px]"></i>
                    <?php echo ucfirst($row['status']); ?>
                </span>
                <span class="risk-<?php echo $risk_level; ?> px-2 py-0.5 text-xs rounded-full font-medium flex items-center gap-1 header-badge">
                    <i class="fas <?php echo $risk_icons[$risk_level]; ?> text-[10px]"></i>
                    <?php echo $risk_labels[$risk_level]; ?>
                </span>
            </div>
        </div>
        
        <div class="p-4 md:p-5 border-t border-gray-100 rounded-b-2xl bg-white">
            <p class="text-gray-500 text-sm mb-3"><?php echo htmlspecialchars(substr($row['description'], 0, 80)); ?><?php echo strlen($row['description']) > 80 ? '...' : ''; ?></p>
            
            <div class="flex flex-wrap gap-2 md:gap-3 text-xs text-gray-400 mb-4">
                <span class="flex items-center gap-1"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($row['category_name']); ?></span>
                <span class="flex items-center gap-1"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($row['barangay_name']); ?></span>
                <span class="flex items-center gap-1"><i class="far fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($row['created_at'])); ?></span>
            </div>
            
            <div class="flex flex-wrap justify-between items-center gap-3 pt-3 border-t border-gray-100">
                <a href="<?php echo BASE_URL; ?>index.php?page=track-status&id=<?php echo IdGuard::enc((int)$row['id']); ?>" 
                   class="inline-flex items-center gap-2 text-sm font-semibold text-[#10A37F] hover:text-[#0D8568] transition">
                    <i class="fas fa-eye"></i> View Details
                </a>
                <?php if($row['status'] == 'pending'): ?>
                <form method="POST" action="<?php echo BASE_URL; ?>controllers/ReportController.php?action=delete" class="inline" onsubmit="return confirm('Delete this report?')">
                    <input type="hidden" name="report_id" value="<?php echo $row['id']; ?>">
                    <button type="submit" class="text-red-500 hover:text-red-700 transition text-sm flex items-center gap-1">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="col-span-full text-center py-12 bg-white rounded-2xl border border-emerald-50">
        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-inbox text-2xl text-gray-400"></i>
        </div>
        <p class="text-gray-400 text-base">No reports found</p>
        <p class="text-gray-400 text-sm mt-1">Try adjusting your filter criteria</p>
    </div>
<?php endif; ?>