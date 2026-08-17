<?php
// views/maintenance.php - Site-wide maintenance splash
// Shown by the index.php router whenever the maintenance_mode kill switch is ON
// and the visitor is not a logged-in admin.

$system_name = SettingsHelper::get('system_name', 'Sierra');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php if (class_exists('SettingsHelper') && SettingsHelper::getLogoUrl()): ?>
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars(SettingsHelper::getLogoUrl()); ?>">
    <?php endif; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($system_name); ?> - Under Maintenance</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Manrope', sans-serif; }
        body { background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 50%, #f5fbf6 100%); }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6">
    <div class="max-w-md w-full">
        <div class="bg-white rounded-3xl shadow-xl border border-emerald-100 overflow-hidden">
            <div class="bg-gradient-to-r from-emerald-500 to-teal-600 p-8 text-center">
                <div class="mx-auto w-20 h-20 bg-white/20 rounded-2xl flex items-center justify-center mb-4">
                    <i class="fas fa-tools text-white text-3xl"></i>
                </div>
                <h1 class="text-2xl font-bold text-white">System Under Maintenance</h1>
                <p class="text-emerald-50 text-sm mt-1"><?php echo htmlspecialchars($system_name); ?> is temporarily offline</p>
            </div>
            <div class="p-8 text-center">
                <div class="flex items-center justify-center gap-2 text-amber-600 mb-4">
                    <i class="fas fa-exclamation-circle"></i>
                    <span class="text-sm font-semibold">We'll be right back</span>
                </div>
                <p class="text-gray-500 text-sm leading-relaxed">
                    Our team is performing routine maintenance or resolving an issue.
                    Please check back shortly. Thank you for your patience.
                </p>
                <div class="mt-6 flex items-center justify-center gap-3">
                    <a href="<?php echo BASE_URL; ?>index.php?page=login"
                       class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl transition">
                        <i class="fas fa-sign-in-alt"></i> Staff Login
                    </a>
                </div>
                <p class="text-xs text-gray-400 mt-6">Contact the MENRO office if you need immediate assistance.</p>
            </div>
        </div>
    </div>
</body>
</html>
<?php exit(); ?>