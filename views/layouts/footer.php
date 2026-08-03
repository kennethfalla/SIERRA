<!-- views/layouts/footer.php - UPDATED WITH DYNAMIC SYSTEM SETTINGS -->
<?php
// Include SettingsHelper for dynamic branding
require_once BASE_PATH . 'helpers/SettingsHelper.php';

// Load dynamic settings
$system_name = SettingsHelper::get('system_name', 'EnviroTrack');
$contact_email = SettingsHelper::get('contact_email', 'menro@sanisidro.gov.ph');
$emergency_hotline = SettingsHelper::get('emergency_hotline', '0917-123-4567');
$lgu_logo = SettingsHelper::get('lgu_logo', '');
$logo_url = $lgu_logo ? BASE_URL . $lgu_logo : '';
?>

<!-- ============================================ -->
<!-- FOOTER - UPDATED WITH DYNAMIC SETTINGS -->
<!-- ============================================ -->
<footer class="bg-gray-900 text-white">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            <!-- Brand Column -->
            <div>
                <div class="flex items-center gap-2 mb-4">
                    <?php if ($logo_url): ?>
                        <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="<?php echo htmlspecialchars($system_name); ?> Logo" class="h-10 w-auto object-contain">
                    <?php else: ?>
                        <div class="w-10 h-10 bg-emerald-500 rounded-lg flex items-center justify-center">
                            <i class="fas fa-leaf text-white text-lg"></i>
                        </div>
                    <?php endif; ?>
                    <span class="text-xl font-bold text-white"><?php echo htmlspecialchars($system_name); ?></span>
                </div>
                <p class="text-gray-400 text-sm leading-relaxed">
                    Environmental reporting system for San Isidro, Nueva Ecija. 
                    Working together for a cleaner, greener community.
                </p>
                <div class="mt-4 space-y-2 text-sm text-gray-400">
                    <p class="flex items-center gap-2">
                        <i class="fas fa-envelope text-emerald-400 w-5"></i>
                        <a href="mailto:<?php echo htmlspecialchars($contact_email); ?>" class="hover:text-emerald-400 transition">
                            <?php echo htmlspecialchars($contact_email); ?>
                        </a>
                    </p>
                    <p class="flex items-center gap-2">
                        <i class="fas fa-phone text-emerald-400 w-5"></i>
                        <a href="tel:<?php echo htmlspecialchars($emergency_hotline); ?>" class="hover:text-emerald-400 transition">
                            <?php echo htmlspecialchars($emergency_hotline); ?>
                        </a>
                    </p>
                    <p class="flex items-center gap-2">
                        <i class="fas fa-map-marker-alt text-emerald-400 w-5"></i>
                        <span>San Isidro, Nueva Ecija</span>
                    </p>
                </div>
            </div>

            <!-- Quick Links -->
            <div>
                <h4 class="text-white font-semibold mb-4">Quick Links</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="<?php echo BASE_URL; ?>index.php" class="text-gray-400 hover:text-emerald-400 transition">Home</a></li>
                    <li><a href="<?php echo BASE_URL; ?>index.php#features" class="text-gray-400 hover:text-emerald-400 transition">How It Works</a></li>
                    <li><a href="<?php echo BASE_URL; ?>index.php#map-section" class="text-gray-400 hover:text-emerald-400 transition">Live Map</a></li>
                    <li><a href="<?php echo BASE_URL; ?>index.php#stats" class="text-gray-400 hover:text-emerald-400 transition">Community Stats</a></li>
                    <li><a href="<?php echo BASE_URL; ?>index.php#about" class="text-gray-400 hover:text-emerald-400 transition">About LGU</a></li>
                </ul>
            </div>

            <!-- Support -->
            <div>
                <h4 class="text-white font-semibold mb-4">Support</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="#" class="text-gray-400 hover:text-emerald-400 transition">FAQ</a></li>
                    <li><a href="#" class="text-gray-400 hover:text-emerald-400 transition">Privacy Policy</a></li>
                    <li><a href="#" class="text-gray-400 hover:text-emerald-400 transition">Terms of Service</a></li>
                    <li><a href="mailto:<?php echo htmlspecialchars($contact_email); ?>" class="text-gray-400 hover:text-emerald-400 transition">Contact Us</a></li>
                </ul>
            </div>

            <!-- Connect -->
            <div>
                <h4 class="text-white font-semibold mb-4">Connect</h4>
                <div class="flex gap-3">
                    <a href="#" class="w-10 h-10 bg-gray-800 rounded-lg flex items-center justify-center hover:bg-emerald-600 transition-colors">
                        <i class="fab fa-facebook-f text-gray-400 hover:text-white transition-colors"></i>
                    </a>
                    <a href="#" class="w-10 h-10 bg-gray-800 rounded-lg flex items-center justify-center hover:bg-emerald-600 transition-colors">
                        <i class="fab fa-twitter text-gray-400 hover:text-white transition-colors"></i>
                    </a>
                    <a href="#" class="w-10 h-10 bg-gray-800 rounded-lg flex items-center justify-center hover:bg-emerald-600 transition-colors">
                        <i class="fab fa-instagram text-gray-400 hover:text-white transition-colors"></i>
                    </a>
                    <a href="#" class="w-10 h-10 bg-gray-800 rounded-lg flex items-center justify-center hover:bg-emerald-600 transition-colors">
                        <i class="fab fa-youtube text-gray-400 hover:text-white transition-colors"></i>
                    </a>
                </div>
                <div class="mt-4">
                    <p class="text-sm text-gray-400">
                        <i class="fas fa-clock mr-2 text-emerald-400"></i>
                        Available 24/7
                    </p>
                </div>
            </div>
        </div>

        <!-- Bottom Bar -->
        <div class="border-t border-gray-800 mt-8 pt-6 flex flex-col sm:flex-row justify-between items-center gap-4">
            <p class="text-sm text-gray-500">
                &copy; <?php echo date('Y'); ?> 
                <span class="text-emerald-400 font-semibold"><?php echo htmlspecialchars($system_name); ?></span> 
                - San Isidro Environmental Reporting System. All rights reserved.
            </p>
            <div class="flex items-center gap-4 text-xs text-gray-500">
                <span>v2.0</span>
                <span class="w-px h-4 bg-gray-700"></span>
                <span>Made with <i class="fas fa-heart text-emerald-400 mx-0.5"></i> for San Isidro</span>
            </div>
        </div>
    </div>
</footer>

<!-- ============================================ -->
<!-- SCRIPTS - Auto-hide flash messages -->
<!-- ============================================ -->
<?php if(isset($_SESSION['success']) || isset($_SESSION['error']) || isset($_SESSION['errors'])): ?>
<script>
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert-message, .flash-message, .notification-toast');
        alerts.forEach(alert => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => {
                if (alert.parentNode) alert.style.display = 'none';
            }, 500);
        });
    }, 5000);
</script>
<?php 
unset($_SESSION['success']);
unset($_SESSION['error']);
unset($_SESSION['errors']);
endif; ?>

</body>
</html>