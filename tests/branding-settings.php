<?php
// Test the real helper without its application/database initialization footer.
define('BASE_URL', 'https://example.test/');
$source = file_get_contents(dirname(__DIR__) . '/helpers/SettingsHelper.php');
$start = strpos($source, 'class SettingsHelper');
$end = strpos($source, '// INITIALIZE DEFAULTS ON FIRST RUN');
eval(substr($source, $start, $end - $start));
$property = new ReflectionProperty(SettingsHelper::class, 'settings');
$property->setValue(null, ['lgu_logo'=>'uploads/settings/default.png']);
function expectLogo($actual, $expected) { if ($actual !== $expected) throw new RuntimeException('Unexpected branding URL: ' . $actual); }
expectLogo(SettingsHelper::getLogoUrl('sidebar'), BASE_URL . 'uploads/settings/default.png');
expectLogo(SettingsHelper::getLogoUrl('header'), BASE_URL . 'uploads/settings/default.png');
$property->setValue(null, ['lgu_logo'=>'uploads/settings/default.png','sidebar_logo'=>'uploads/settings/sidebar.png','header_logo'=>'uploads/settings/header.png']);
expectLogo(SettingsHelper::getLogoUrl('sidebar'), BASE_URL . 'uploads/settings/sidebar.png');
expectLogo(SettingsHelper::getLogoUrl('header'), BASE_URL . 'uploads/settings/header.png');
expectLogo(SettingsHelper::getLogoUrl(), BASE_URL . 'uploads/settings/default.png');
$property->setValue(null, ['lgu_logo'=>'','sidebar_logo'=>'']);
expectLogo(SettingsHelper::getLogoUrl('sidebar'), '');
expectLogo(SettingsHelper::getLogoUrl('unknown'), '');
$property->setValue(null, ['auth_photo'=>'uploads/settings/auth_photo.jpg','lp_hero_bg_image'=>'https://example.test/landscape.jpg']);
expectLogo(SettingsHelper::getAuthPhotoUrl(), BASE_URL . 'uploads/settings/auth_photo.jpg');
$property->setValue(null, ['auth_photo'=>'','lp_hero_bg_image'=>'https://example.test/landscape.jpg']);
expectLogo(SettingsHelper::getAuthPhotoUrl(), 'https://example.test/landscape.jpg');
echo "PASS separate sidebar/header logos, LGU fallback and reset behavior\n";
