<?php
if (!defined('BASE_PATH')) { http_response_code(404); exit; }
$search_labels = ['all'=>'All','reports'=>'Reports','announcements'=>'Announcements','notifications'=>'Notifications','users'=>'Users','reporters'=>'Reporters','audit'=>'Audit Logs'];
$search_icons = ['reports'=>'fa-file-alt','announcements'=>'fa-bullhorn','notifications'=>'fa-bell','users'=>'fa-users','reporters'=>'fa-user','audit'=>'fa-history'];
$escape_search = fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');
$search_context_params = $search_context_params ?? [];
$search_scope_label = $search_scope_label ?? '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Search · <?php echo $escape_search(SettingsHelper::get('system_name','SIERRA')); ?></title>
    <script src="<?php echo BASE_URL; ?>assets/js/theme.js?v=<?php echo filemtime(BASE_PATH.'assets/js/theme.js'); ?>"></script>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/tailwind.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/vendor/manrope/manrope.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/material-symbols.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/search.css?v=<?php echo filemtime(BASE_PATH.'assets/css/search.css'); ?>">
    <?php $search_logo = SettingsHelper::get('header_logo','') ?: SettingsHelper::get('lgu_logo',''); if ($search_logo): ?>
    <link rel="icon" href="<?php echo $escape_search(BASE_URL.$search_logo); ?>">
    <?php endif; ?>
    <script src="<?php echo BASE_URL; ?>assets/js/search.js?v=<?php echo filemtime(BASE_PATH.'assets/js/search.js'); ?>" defer></script>
</head>
<body class="search-page">
<?php require BASE_PATH.'views/layouts/sidebar.php'; ?>
<main id="main-content" class="lg:ml-72 min-h-screen" tabindex="-1">
    <div class="search-content">
        <section class="search-panel" aria-label="Search the system">
            <form id="systemSearchForm" action="<?php echo BASE_URL; ?>index.php" method="get" role="search">
                <input type="hidden" name="page" value="search">
                <input type="hidden" name="type" value="<?php echo $escape_search($search_type); ?>">
                <?php foreach ($search_context_params as $key=>$value): ?><input type="hidden" name="<?php echo $escape_search($key); ?>" value="<?php echo $escape_search($value); ?>"><?php endforeach; ?>
                <label for="systemSearchInput" class="search-title"><?php echo $search_scope_label ? 'Search '.$escape_search($search_scope_label) : 'Find what you need'; ?></label>
                <div class="search-entry">
                    <div class="search-input-wrap">
                        <input id="systemSearchInput" type="search" name="q" value="<?php echo $escape_search($search_query); ?>" placeholder="<?php echo $escape_search($search_scope_label ? 'Search '.$search_scope_label.'…' : 'Search reports, announcements, and updates…'); ?>" maxlength="160" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="searchSuggestions">
                        <div id="searchSuggestions" class="search-suggestions" role="listbox" aria-label="Suggested results" hidden></div>
                    </div>
                    <button type="submit" class="btn-primary"><i class="fas fa-search" aria-hidden="true"></i><span>Search</span></button>
                </div>
                <p class="search-hint">Search full descriptions. Try <span>drain*</span> or <span>dr?in</span> for partial words.</p>
                <div id="searchSuggestionStatus" class="search-live-status" role="status" aria-live="polite"></div>
            </form>
        </section>
        <nav class="search-tabs" aria-label="Search result types">
        <?php foreach ($search_context_params ? $search_types : array_merge(['all'],$search_types) as $type): ?>
            <a href="<?php echo $escape_search(BASE_URL.'index.php?'.http_build_query($search_context_params + ['page'=>'search','q'=>$search_query,'type'=>$type])); ?>" class="search-tab<?php echo $type === $search_type ? ' active' : ''; ?>" <?php if ($type === $search_type): ?>aria-current="page"<?php endif; ?>><?php echo $search_labels[$type]; ?></a>
        <?php endforeach; ?>
        </nav>
        <section class="search-results" aria-label="Search results">
        <?php if ($search_error): ?>
            <div class="search-empty" role="alert"><i class="fas fa-exclamation-circle" aria-hidden="true"></i><h2>Search unavailable</h2><p><?php echo $escape_search($search_error); ?></p><a class="btn-secondary" href="<?php echo $escape_search(BASE_URL.'index.php?'.http_build_query($search_context_params + ['page'=>'search','q'=>$search_query,'type'=>$search_type])); ?>">Try again</a></div>
        <?php elseif ($search_query === ''): ?>
            <div class="search-empty"><i class="fas fa-search" aria-hidden="true"></i><h2><?php echo $search_scope_label ? 'Search '.$escape_search($search_scope_label) : 'Your community, in one search'; ?></h2><p><?php echo $search_scope_label ? 'Only records from this page appear here.' : 'Find reports, community announcements, and your notifications.'; ?></p></div>
        <?php elseif (!$search_data['total']): ?>
            <div class="search-empty"><i class="fas fa-search" aria-hidden="true"></i><h2>No results found</h2><p>Try fewer words, another spelling, or a wildcard.</p></div>
        <?php else: ?>
            <p class="search-result-count"><?php echo (int)$search_data['total']; ?> result<?php echo $search_data['total'] === 1 ? '' : 's'; ?> for <strong>“<?php echo $escape_search($search_query); ?>”</strong></p>
            <?php foreach ($search_data['results'] as $result): ?>
            <article class="search-result-card">
                <div class="search-result-icon"><i class="fas <?php echo $search_icons[$result['type']]; ?>" aria-hidden="true"></i></div>
                <div class="search-result-copy">
                    <div class="search-result-meta"><span><?php echo $search_labels[$result['type']]; ?></span><?php if ($result['type'] === 'reports'): ?><span>#<?php echo str_pad((string)$result['id'],6,'0',STR_PAD_LEFT); ?></span><?php endif; ?><?php if ($result['location']): ?><span><?php echo $escape_search($result['location']); ?></span><?php endif; ?><time datetime="<?php echo $escape_search($result['created_at']); ?>"><?php echo date('M j, Y',strtotime($result['created_at'])); ?></time></div>
                    <h2><a href="<?php echo $escape_search($result['url']); ?>"><?php echo $escape_search($result['title']); ?></a></h2>
                    <p><?php echo $escape_search($result['excerpt']); ?></p>
                </div>
                <a class="search-result-open" href="<?php echo $escape_search($result['url']); ?>" aria-label="<?php echo $escape_search('Open '.$result['title']); ?>"><i class="fas fa-arrow-right" aria-hidden="true"></i></a>
            </article>
            <?php endforeach; ?>
            <?php if ($search_data['pages'] > 1): ?>
            <nav class="search-pagination" aria-label="Result pages">
                <?php if ($search_data['page'] > 1): ?><a class="btn-secondary" href="<?php echo $escape_search(BASE_URL.'index.php?'.http_build_query($search_context_params + ['page'=>'search','q'=>$search_query,'type'=>$search_type,'page_num'=>$search_data['page']-1])); ?>">Previous</a><?php endif; ?>
                <span>Page <?php echo (int)$search_data['page']; ?> of <?php echo (int)$search_data['pages']; ?></span>
                <?php if ($search_data['page'] < $search_data['pages']): ?><a class="btn-secondary" href="<?php echo $escape_search(BASE_URL.'index.php?'.http_build_query($search_context_params + ['page'=>'search','q'=>$search_query,'type'=>$search_type,'page_num'=>$search_data['page']+1])); ?>">Next</a><?php endif; ?>
            </nav>
            <?php endif; ?>
        <?php endif; ?>
        </section>
    </div>
</main>
</body>
</html>
