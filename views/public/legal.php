<?php
$is_privacy = $page === 'privacy-policy';
$title = $is_privacy ? 'Privacy Policy' : 'Terms of Service';
$system_name = SettingsHelper::get('system_name', 'SIERRA');
$content_file = BASE_PATH . 'views/shared/legal/' . ($is_privacy ? 'privacy.php' : 'terms.php');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title . ' - ' . $system_name); ?></title>
    <link href="<?php echo BASE_URL; ?>assets/vendor/manrope/manrope.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f5fbf6; color: #374151; font: 15px/1.7 'Manrope', sans-serif; }
        header { background: #fff; border-bottom: 1px solid #e5ece8; }
        .wrap { width: min(100% - 32px, 780px); margin: auto; }
        header .wrap { min-height: 68px; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .brand { color: #047857; font-weight: 800; text-decoration: none; }
        .back { color: #047857; font-weight: 700; text-decoration: none; }
        .back:hover, .brand:hover { text-decoration: underline; }
        main { padding: clamp(24px, 6vw, 56px) 0; }
        article { background: #fff; border: 1px solid #e5ece8; border-radius: 18px; padding: clamp(20px, 5vw, 40px); box-shadow: 0 12px 35px rgba(5, 60, 40, .06); }
        h1 { color: #173b2c; margin: 0 0 24px; font-size: clamp(1.75rem, 5vw, 2.4rem); line-height: 1.2; }
        h4 { color: #173b2c; margin: 24px 0 5px; font-size: 1.05rem; }
        p { margin: 0 0 12px; }
        ul { padding-left: 24px; margin: 8px 0 16px; }
        li { margin-bottom: 5px; }
        a:focus-visible { outline: 3px solid #10a37f; outline-offset: 3px; }
    </style>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/branded-dropdowns.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/branded-dropdowns.css'); ?>">
</head>
<body>
    <header><div class="wrap">
        <a class="brand" href="<?php echo BASE_URL; ?>index.php"><?php echo htmlspecialchars($system_name); ?></a>
        <a class="back" href="<?php echo BASE_URL; ?>index.php">Back to home</a>
    </div></header>
    <main class="wrap"><article>
        <h1><?php echo htmlspecialchars($title); ?></h1>
        <?php require $content_file; ?>
    </article></main>
</body>
</html>
