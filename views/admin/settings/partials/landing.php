<?php
// views/admin/settings/partials/landing.php - Landing Page content editor
// Edits the public homepage copy shown in views/index.php
// All fields are stored in system_settings under the lp_* prefix.

// Guard: this partial is always included inside the settings page where
// config.php and SettingsHelper are already loaded.

if (!function_exists('landing_val')) {
    function landing_val($key, $default = '') {
        $value = SettingsHelper::get($key, $default);
        return ($value === null || $value === '') ? $default : $value;
    }
}

$csrf_token = InputSanitizer::generateCsrfToken();
?>
<!-- NOTE: The main "Save Landing Page" form opens BEFORE the "How It Works"
     section, right after the hero media gallery below. The hero fields at the
     top of this section (badge, background type, media URLs) point back to it
     with the form="landingSettingsForm" attribute, and the gallery uses its own
     standalone forms — so no <form> is ever nested inside another <form>. -->

    <!-- ============================================ -->
    <!-- HERO SECTION -->
    <!-- ============================================ -->
    <div class="landing-section">
        <div class="landing-section-title">
            <i class="fas fa-rocket text-[#10A37F]"></i>
            <div>
                <h4>Hero Section</h4>
                <p>The headline area at the top of the homepage</p>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="lp_hero_badge">Badge Text</label>
            <input type="text" name="lp_hero_badge" id="lp_hero_badge" form="landingSettingsForm" class="form-input"
                   value="<?php echo htmlspecialchars(landing_val('lp_hero_badge', 'Working together for a cleaner community')); ?>"
                   placeholder="Working together for a cleaner community">
        </div>

        <!-- ===== HERO BACKGROUND MEDIA ===== -->
        <div class="landing-stat-box mb-4">
            <p class="landing-stat-label"><i class="fas fa-image text-[#10A37F] mr-1"></i> Hero Background (Image or Video)</p>

            <div class="grid md:grid-cols-3 gap-4">
                <div class="form-group">
                    <label class="form-label" for="lp_hero_bg_type">Background Type</label>
                    <select name="lp_hero_bg_type" id="lp_hero_bg_type" form="landingSettingsForm" class="form-input">
                        <?php $hero_bg_type = landing_val('lp_hero_bg_type', 'image'); ?>
                        <option value="image" <?php echo $hero_bg_type === 'image' ? 'selected' : ''; ?>>Image Background</option>
                        <option value="video" <?php echo $hero_bg_type === 'video' ? 'selected' : ''; ?>>Video Background</option>
                        <option value="none" <?php echo $hero_bg_type === 'none' ? 'selected' : ''; ?>>Gradient Only (No Media)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="lp_hero_bg_image">Background Image URL</label>
                    <input type="text" name="lp_hero_bg_image" id="lp_hero_bg_image" form="landingSettingsForm" class="form-input"
                           value="<?php echo htmlspecialchars(landing_val('lp_hero_bg_image')); ?>"
                           placeholder="https://... or uploads/hero/my-image.webp">
                    <p class="text-xs text-gray-400 mt-1">External https URL or a file stored under <code>uploads/</code>. Falls back to the default mountain photo if left empty on an Image setting.</p>
                </div>

                <div class="form-group">
                    <label class="form-label" for="lp_hero_bg_video">Background Video URL</label>
                    <input type="text" name="lp_hero_bg_video" id="lp_hero_bg_video" form="landingSettingsForm" class="form-input"
                           value="<?php echo htmlspecialchars(landing_val('lp_hero_bg_video')); ?>"
                           placeholder="https://.../background.mp4">
                    <p class="text-xs text-gray-400 mt-1">MP4 / WEBM / MOV. Plays muted, looping, and fullscreen on the hero. Leave empty to fall back to the image/gradient.</p>
                </div>
            </div>

            <p class="text-xs text-gray-400 mt-1"><i class="fas fa-info-circle mr-1"></i> The image loads instantly; videos (mp4/webm) autoplay silently and loop as the hero backdrop.</p>
        </div>

        <!-- ===== HERO MEDIA GALLERY ===== -->
        <?php
        // Scan the hero gallery directory for uploaded images/videos
        $hero_gallery_dir = $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/uploads/settings/hero/';
        $gallery_img_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $gallery_vid_exts = ['mp4', 'webm', 'mov', 'm4v', 'ogg'];
        $gallery = [];
        if (is_dir($hero_gallery_dir)) {
            foreach (glob($hero_gallery_dir . '*.*') as $gfile) {
                $gext = strtolower(pathinfo($gfile, PATHINFO_EXTENSION));
                if (in_array($gext, $gallery_img_exts, true) || in_array($gext, $gallery_vid_exts, true)) {
                    $gallery[] = [
                        'file'     => basename($gfile),
                        'ext'      => $gext,
                        'is_video' => in_array($gext, $gallery_vid_exts, true),
                        'mtime'    => filemtime($gfile),
                    ];
                }
            }
            usort($gallery, function($a, $b) { return $b['mtime'] <=> $a['mtime']; });
        }
        $current_bg_image = landing_val('lp_hero_bg_image', '');
        $current_bg_video = landing_val('lp_hero_bg_video', '');
        ?>

        <div class="landing-stat-box mb-4">
            <p class="landing-stat-label"><i class="fas fa-photo-video text-[#10A37F] mr-1"></i> Media Gallery — choose the background from uploaded files</p>

            <!-- Upload form -->
            <form method="POST" enctype="multipart/form-data"
                  action="<?php echo BASE_URL; ?>index.php?page=settings&tab=landing"
                  class="mb-4" id="heroGalleryUploadForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="sub_action" value="hero_gallery_upload">
                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                    <input type="file" name="hero_media" id="hero_media" class="hidden"
                           accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm,video/quicktime,video/x-m4v,video/ogg">
                    <button type="button" id="heroGalleryChooseBtn" class="btn-secondary flex items-center gap-2 text-sm">
                        <i class="fas fa-folder-open"></i> <span id="heroMediaLabel">Choose from Gallery</span>
                    </button>
                    <p class="text-xs text-gray-400" id="heroMediaHint">Images ≤ 5MB · Videos ≤ 50MB — picking a file uploads it AND sets it as the hero background right away.</p>
                </div>
            </form>

            <?php if (empty($gallery)): ?>
                <p class="text-xs text-gray-400 text-center py-4"><i class="fas fa-images mr-1"></i> No media yet. Upload an image or video above.</p>
            <?php else: ?>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                    <?php foreach ($gallery as $item):
                        $url = BASE_URL . 'uploads/settings/hero/' . rawurlencode($item['file']);
                        $is_active = $item['is_video']
                            ? ($current_bg_video === 'uploads/settings/hero/' . $item['file'])
                            : ($current_bg_image === 'uploads/settings/hero/' . $item['file']);
                    ?>
                    <div class="relative rounded-xl overflow-hidden border bg-gray-900 <?php echo $is_active ? 'ring-2 ring-[#10A37F]' : 'border-gray-200'; ?>" style="aspect-ratio: 16/9;">
                        <?php if ($item['is_video']): ?>
                            <video src="<?php echo htmlspecialchars($url); ?>" muted playsinline preload="metadata" class="w-full h-full object-cover"></video>
                            <span class="absolute top-2 left-2 px-2 py-0.5 rounded-full bg-black/60 text-white text-[10px] font-medium"><i class="fas fa-video mr-1"></i>Video</span>
                        <?php else: ?>
                            <img src="<?php echo htmlspecialchars($url); ?>" alt="<?php echo htmlspecialchars($item['file']); ?>" class="w-full h-full object-cover">
                        <?php endif; ?>

                        <?php if ($is_active): ?>
                            <span class="absolute top-2 right-2 px-2 py-0.5 rounded-full bg-[#10A37F] text-white text-[10px] font-semibold">Active</span>
                        <?php endif; ?>

                        <div class="absolute bottom-0 inset-x-0 flex gap-1 p-1.5 bg-gradient-to-t from-black/80 to-transparent">
                            <form method="POST" action="<?php echo BASE_URL; ?>index.php?page=settings&tab=landing" class="flex-1">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <input type="hidden" name="sub_action" value="hero_gallery_select">
                                <input type="hidden" name="hero_file" value="<?php echo htmlspecialchars($item['file']); ?>">
                                <input type="hidden" name="hero_type" value="<?php echo $item['is_video'] ? 'video' : 'image'; ?>">
                                <button type="submit" class="w-full py-1 rounded-md bg-white/90 text-gray-800 hover:bg-white text-[11px] font-semibold">
                                    <?php echo $item['is_video'] ? 'Set as Video BG' : 'Set as Image BG'; ?>
                                </button>
                            </form>
                            <form method="POST" action="<?php echo BASE_URL; ?>index.php?page=settings&tab=landing"
                                  onsubmit="return confirm('Delete this item from the gallery?');">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <input type="hidden" name="sub_action" value="hero_gallery_delete">
                                <input type="hidden" name="hero_file" value="<?php echo htmlspecialchars($item['file']); ?>">
                                <button type="submit" title="Delete" class="px-2 py-1 rounded-md bg-red-500/90 text-white hover:bg-red-500 text-[11px]"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <p class="text-xs text-gray-400 mt-2"><i class="fas fa-info-circle mr-1"></i> Click "Set as Image BG" or "Set as Video BG" to activate immediately — no need to press Save.</p>
            <?php endif; ?>
        </div>

        <form method="POST" action="<?php echo BASE_URL; ?>index.php?page=settings&tab=landing" id="landingSettingsForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

        <div class="grid sm:grid-cols-2 gap-4">
            <div class="form-group">
                <label class="form-label" for="lp_hero_headline_1">Headline — Line 1 <span class="text-xs font-normal text-gray-400">(regular color)</span></label>
                <input type="text" name="lp_hero_headline_1" id="lp_hero_headline_1" class="form-input"
                       value="<?php echo htmlspecialchars(landing_val('lp_hero_headline_1', 'Sama-sama nating')); ?>"
                       placeholder="Sama-sama nating">
            </div>
            <div class="form-group">
                <label class="form-label" for="lp_hero_headline_2">Headline — Line 2 <span class="text-xs font-normal text-gray-400">(emerald highlight)</span></label>
                <textarea name="lp_hero_headline_2" id="lp_hero_headline_2" class="form-input" rows="2"
                          placeholder="pangalagaan ang&#10;San Isidro."><?php echo htmlspecialchars(landing_val('lp_hero_headline_2', "pangalagaan ang\nSan Isidro.")); ?></textarea>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="lp_hero_subtitle_guest">Subtitle — Guests <span class="text-xs font-normal text-gray-400">(logged out / unregistered visitors)</span></label>
            <textarea name="lp_hero_subtitle_guest" id="lp_hero_subtitle_guest" class="form-input" rows="3"><?php echo htmlspecialchars(landing_val('lp_hero_subtitle_guest', "See something wrong in your neighborhood? Illegal dumping, clogged canals, or air pollution?\nReport it here, and your barangay will take action. It's free, fast, and easy.")); ?></textarea>
        </div>

        <div class="form-group">
            <label class="form-label" for="lp_hero_subtitle_staff">Subtitle — Staff <span class="text-xs font-normal text-gray-400">(use <code>{role}</code> as placeholder)</span></label>
            <textarea name="lp_hero_subtitle_staff" id="lp_hero_subtitle_staff" class="form-input" rows="3"><?php echo htmlspecialchars(landing_val('lp_hero_subtitle_staff', "As a {role}, you can review, verify, and manage environmental reports from your community.\nTake action on pending reports and help resolve issues faster.")); ?></textarea>
        </div>

        <div class="form-group">
            <label class="form-label" for="lp_hero_subtitle_user">Subtitle — Citizens <span class="text-xs font-normal text-gray-400">(logged in residents)</span></label>
            <textarea name="lp_hero_subtitle_user" id="lp_hero_subtitle_user" class="form-input" rows="3"><?php echo htmlspecialchars(landing_val('lp_hero_subtitle_user', "Your voice matters. Report environmental issues like illegal dumping, flooding, or pollution —\nand we'll help track them until they're resolved.")); ?></textarea>
        </div>

        <div class="form-group">
            <label class="form-label" for="lp_hero_stats_caption">Stats Panel Caption <span class="text-xs font-normal text-gray-400">(text under "San Isidro at a Glance")</span></label>
            <input type="text" name="lp_hero_stats_caption" id="lp_hero_stats_caption" class="form-input"
                   value="<?php echo htmlspecialchars(landing_val('lp_hero_stats_caption', 'Community impact in real time.')); ?>"
                   placeholder="Community impact in real time.">
        </div>
    </div>

    <!-- ============================================ -->
    <!-- HOW IT WORKS SECTION -->
    <!-- ============================================ -->
    <div class="landing-section">
        <div class="landing-section-title">
            <i class="fas fa-list-ol text-[#10A37F]"></i>
            <div>
                <h4>How It Works</h4>
                <p>The three-step guide section</p>
            </div>
        </div>

        <div class="grid sm:grid-cols-3 gap-4">
            <div class="form-group">
                <label class="form-label" for="lp_how_kicker">Section Kicker</label>
                <input type="text" name="lp_how_kicker" id="lp_how_kicker" class="form-input"
                       value="<?php echo htmlspecialchars(landing_val('lp_how_kicker', 'How It Works')); ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="lp_how_heading">Section Heading</label>
                <input type="text" name="lp_how_heading" id="lp_how_heading" class="form-input"
                       value="<?php echo htmlspecialchars(landing_val('lp_how_heading', 'Three simple steps')); ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="lp_how_intro">Intro Paragraph</label>
                <input type="text" name="lp_how_intro" id="lp_how_intro" class="form-input"
                       value="<?php echo htmlspecialchars(landing_val('lp_how_intro', "You don't need to be an expert. Anyone can report an environmental issue in their neighborhood.")); ?>">
            </div>
        </div>

        <?php for ($i = 1; $i <= 3; $i++): ?>
        <div class="grid sm:grid-cols-2 gap-4 landing-sub-block">
            <div class="form-group">
                <label class="form-label" for="lp_how_step<?php echo $i; ?>_title">Step <?php echo $i; ?> Title</label>
                <input type="text" name="lp_how_step<?php echo $i; ?>_title" id="lp_how_step<?php echo $i; ?>_title" class="form-input"
                       value="<?php echo htmlspecialchars(landing_val('lp_how_step' . $i . '_title')); ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="lp_how_step<?php echo $i; ?>_desc">Step <?php echo $i; ?> Description</label>
                <textarea name="lp_how_step<?php echo $i; ?>_desc" id="lp_how_step<?php echo $i; ?>_desc" class="form-input" rows="2"><?php echo htmlspecialchars(landing_val('lp_how_step' . $i . '_desc')); ?></textarea>
            </div>
        </div>
        <?php endfor; ?>
    </div>

    <!-- ============================================ -->
    <!-- LIVE MAP SECTION -->
    <!-- ============================================ -->
    <div class="landing-section">
        <div class="landing-section-title">
            <i class="fas fa-map-marked-alt text-[#10A37F]"></i>
            <div>
                <h4>Live Map</h4>
                <p>The map section heading and intro</p>
            </div>
        </div>

        <div class="grid sm:grid-cols-3 gap-4">
            <div class="form-group">
                <label class="form-label" for="lp_map_kicker">Section Kicker</label>
                <input type="text" name="lp_map_kicker" id="lp_map_kicker" class="form-input"
                       value="<?php echo htmlspecialchars(landing_val('lp_map_kicker', 'Live Map')); ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="lp_map_heading">Section Heading</label>
                <input type="text" name="lp_map_heading" id="lp_map_heading" class="form-input"
                       value="<?php echo htmlspecialchars(landing_val('lp_map_heading', 'Environmental Reports Map')); ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="lp_map_intro">Intro Paragraph</label>
                <input type="text" name="lp_map_intro" id="lp_map_intro" class="form-input"
                       value="<?php echo htmlspecialchars(landing_val('lp_map_intro', 'See where environmental issues are being reported across San Isidro.')); ?>">
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- COMMUNITY STATS SECTION -->
    <!-- ============================================ -->
    <div class="landing-section">
        <div class="landing-section-title">
            <i class="fas fa-chart-line text-[#10A37F]"></i>
            <div>
                <h4>Community Stats</h4>
                <p>The "San Isidro Statistics" section — numbers, labels, and captions</p>
            </div>
        </div>

        <div class="grid sm:grid-cols-3 gap-4">
            <div class="form-group">
                <label class="form-label" for="lp_stats_kicker">Section Kicker</label>
                <input type="text" name="lp_stats_kicker" id="lp_stats_kicker" class="form-input"
                       value="<?php echo htmlspecialchars(landing_val('lp_stats_kicker', 'Community Impact')); ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="lp_stats_heading">Section Heading</label>
                <input type="text" name="lp_stats_heading" id="lp_stats_heading" class="form-input"
                       value="<?php echo htmlspecialchars(landing_val('lp_stats_heading', 'San Isidro Statistics')); ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="lp_stats_intro">Intro Paragraph</label>
                <input type="text" name="lp_stats_intro" id="lp_stats_intro" class="form-input"
                       value="<?php echo htmlspecialchars(landing_val('lp_stats_intro', "Together, we're making a difference in our community.")); ?>">
            </div>
        </div>

        <?php
        $stat_cards = [
            'barangays'  => 'Barangays',
            'population' => 'Population',
            'households' => 'Households',
            'reports'    => 'Reports Submitted',
        ];
        ?>

        <div class="grid md:grid-cols-2 gap-4">
            <?php foreach ($stat_cards as $stat_key => $label_hint): ?>
            <div class="landing-stat-box">
                <p class="landing-stat-label"><?php echo htmlspecialchars($label_hint); ?> Card</p>
                <div class="grid grid-cols-3 gap-3">
                    <?php if ($stat_key === 'reports'): ?>
                        <div class="flex items-end">
                            <p class="text-xs text-gray-400 pb-2"><i class="fas fa-info-circle mr-1"></i>Value is live from reports</p>
                        </div>
                    <?php else: ?>
                        <div class="form-group">
                            <label class="form-label" for="lp_stat_<?php echo $stat_key; ?>">Value</label>
                            <input type="number" name="lp_stat_<?php echo $stat_key; ?>" id="lp_stat_<?php echo $stat_key; ?>"
                                   class="form-input" min="0"
                                   value="<?php echo (int)landing_val('lp_stat_' . $stat_key, 0); ?>">
                        </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label class="form-label" for="lp_stat_<?php echo $stat_key; ?>_label">Label</label>
                        <input type="text" name="lp_stat_<?php echo $stat_key; ?>_label" id="lp_stat_<?php echo $stat_key; ?>_label"
                               class="form-input"
                               value="<?php echo htmlspecialchars(landing_val('lp_stat_' . $stat_key . '_label', $label_hint)); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="lp_stat_<?php echo $stat_key; ?>_sub">Caption</label>
                        <input type="text" name="lp_stat_<?php echo $stat_key; ?>_sub" id="lp_stat_<?php echo $stat_key; ?>_sub"
                               class="form-input"
                               value="<?php echo htmlspecialchars(landing_val('lp_stat_' . $stat_key . '_sub')); ?>">
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- ABOUT LGU SECTION -->
    <!-- ============================================ -->
    <div class="landing-section">
        <div class="landing-section-title">
            <i class="fas fa-building text-[#10A37F]"></i>
            <div>
                <h4>About LGU</h4>
                <p>The Vision and About MENRO cards at the bottom of the page</p>
            </div>
        </div>

        <div class="grid sm:grid-cols-3 gap-4">
            <div class="form-group">
                <label class="form-label" for="lp_about_kicker">Section Kicker</label>
                <input type="text" name="lp_about_kicker" id="lp_about_kicker" class="form-input"
                       value="<?php echo htmlspecialchars(landing_val('lp_about_kicker', 'About LGU')); ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="lp_about_heading">Section Heading</label>
                <input type="text" name="lp_about_heading" id="lp_about_heading" class="form-input"
                       value="<?php echo htmlspecialchars(landing_val('lp_about_heading', 'Municipal Environment & Natural Resources Office')); ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="lp_about_subtitle">Section Subtitle</label>
                <input type="text" name="lp_about_subtitle" id="lp_about_subtitle" class="form-input"
                       value="<?php echo htmlspecialchars(landing_val('lp_about_subtitle', "Committed to protecting and preserving San Isidro's environment for future generations.")); ?>">
            </div>
        </div>

        <div class="grid lg:grid-cols-2 gap-5">
            <!-- Vision card fields -->
            <div class="landing-card-block">
                <p class="landing-card-label"><i class="fas fa-eye text-emerald-600 mr-1"></i> Vision Card</p>
                <div class="grid grid-cols-2 gap-3">
                    <div class="form-group">
                        <label class="form-label" for="lp_vision_title">Card Title</label>
                        <input type="text" name="lp_vision_title" id="lp_vision_title" class="form-input"
                               value="<?php echo htmlspecialchars(landing_val('lp_vision_title', 'Our Vision')); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="lp_vision_tagline">Tagline</label>
                        <input type="text" name="lp_vision_tagline" id="lp_vision_tagline" class="form-input"
                               value="<?php echo htmlspecialchars(landing_val('lp_vision_tagline', 'A greener, cleaner San Isidro')); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="lp_vision_body">Vision Text</label>
                    <textarea name="lp_vision_body" id="lp_vision_body" class="form-input" rows="4"><?php echo htmlspecialchars(landing_val('lp_vision_body')); ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label" for="lp_vision_label">Footer Label</label>
                    <input type="text" name="lp_vision_label" id="lp_vision_label" class="form-input"
                           value="<?php echo htmlspecialchars(landing_val('lp_vision_label', 'Vision 2030')); ?>">
                </div>

                <!-- Designated Vision photo upload -->
                <?php $vision_img = landing_val('lp_vision_image', ''); ?>
                <div class="landing-sub-block">
                    <p class="landing-stat-label"><i class="fas fa-image text-[#10A37F] mr-1"></i> Vision Photo <span class="font-normal normal-case text-gray-400">(shown on the right side of the Vision card)</span></p>
                    <?php if ($vision_img): ?>
                        <div class="relative rounded-xl overflow-hidden border border-gray-200 mb-3" style="max-width: 260px; aspect-ratio: 16/10;">
                            <img src="<?php echo htmlspecialchars((preg_match('#^uploads/#i', $vision_img) ? BASE_URL : '') . $vision_img); ?>" alt="Vision photo" class="w-full h-full object-cover">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="slot_vision_file" id="about_media_vision" class="hidden"
                           accept="image/jpeg,image/png,image/gif,image/webp">
                    <button type="button" id="visionChooseBtn" class="btn-secondary flex items-center gap-2 text-sm">
                        <i class="fas fa-folder-open"></i> <span id="visionMediaLabel"><?php echo $vision_img ? 'Replace photo' : 'Choose photo'; ?></span>
                    </button>
                    <p class="text-xs text-gray-400 mt-1.5">Images ≤ 5MB. Picking a file uploads and sets it right away.</p>
                    <?php if ($vision_img): ?>
                        <button type="button" class="about-slot-clear text-xs text-red-500 hover:text-red-600 font-medium mt-1.5" data-slot="vision_main">
                            <i class="fas fa-trash-alt mr-1"></i> Remove photo
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- About MENRO card fields -->
            <div class="landing-card-block">
                <p class="landing-card-label"><i class="fas fa-building text-[#10A37F] mr-1"></i> About MENRO Card</p>
                <div class="grid grid-cols-2 gap-3">
                    <div class="form-group">
                        <label class="form-label" for="lp_about_title">Card Title</label>
                        <input type="text" name="lp_about_title" id="lp_about_title" class="form-input"
                               value="<?php echo htmlspecialchars(landing_val('lp_about_title', 'About MENRO')); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="lp_about_tagline">Tagline</label>
                        <input type="text" name="lp_about_tagline" id="lp_about_tagline" class="form-input"
                               value="<?php echo htmlspecialchars(landing_val('lp_about_tagline', 'Municipal Environment Office')); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="lp_about_body">About Text</label>
                    <textarea name="lp_about_body" id="lp_about_body" class="form-input" rows="4"><?php echo htmlspecialchars(landing_val('lp_about_body')); ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label" for="lp_mission_body">Mission Text <span class="text-xs font-normal text-gray-400">(shown under "Our Mission")</span></label>
                    <textarea name="lp_mission_body" id="lp_mission_body" class="form-input" rows="4"><?php echo htmlspecialchars(landing_val('lp_mission_body')); ?></textarea>
                </div>

                <!-- Designated Mission photo upload -->
                <?php $mission_img = landing_val('lp_mission_image_main', ''); ?>
                <div class="landing-sub-block">
                    <p class="landing-stat-label"><i class="fas fa-image text-[#10A37F] mr-1"></i> Mission Photo <span class="font-normal normal-case text-gray-400">(main image on the Mission card)</span></p>
                    <?php if ($mission_img): ?>
                        <div class="relative rounded-xl overflow-hidden border border-gray-200 mb-3" style="max-width: 260px; aspect-ratio: 16/10;">
                            <img src="<?php echo htmlspecialchars((preg_match('#^uploads/#i', $mission_img) ? BASE_URL : '') . $mission_img); ?>" alt="Mission photo" class="w-full h-full object-cover">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="slot_mission_file" id="about_media_mission" class="hidden"
                           accept="image/jpeg,image/png,image/gif,image/webp">
                    <button type="button" id="missionChooseBtn" class="btn-secondary flex items-center gap-2 text-sm">
                        <i class="fas fa-folder-open"></i> <span id="missionMediaLabel"><?php echo $mission_img ? 'Replace photo' : 'Choose photo'; ?></span>
                    </button>
                    <p class="text-xs text-gray-400 mt-1.5">Images ≤ 5MB. Picking a file uploads and sets it right away.</p>
                    <?php if ($mission_img): ?>
                        <button type="button" class="about-slot-clear text-xs text-red-500 hover:text-red-600 font-medium mt-1.5" data-slot="mission_main">
                            <i class="fas fa-trash-alt mr-1"></i> Remove photo
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Designated Mission Inset photo upload -->
                <?php $mission_inset_img = landing_val('lp_mission_image_inset', ''); ?>
                <div class="landing-sub-block">
                    <p class="landing-stat-label"><i class="fas fa-image text-[#10A37F] mr-1"></i> Mission Inset Photo <span class="font-normal normal-case text-gray-400">(small overlay photo on the Mission card)</span></p>
                    <?php if ($mission_inset_img): ?>
                        <div class="relative rounded-xl overflow-hidden border border-gray-200 mb-3" style="max-width: 160px; aspect-ratio: 1/1;">
                            <img src="<?php echo htmlspecialchars((preg_match('#^uploads/#i', $mission_inset_img) ? BASE_URL : '') . $mission_inset_img); ?>" alt="Mission inset photo" class="w-full h-full object-cover">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="slot_inset_file" id="about_media_inset" class="hidden"
                           accept="image/jpeg,image/png,image/gif,image/webp">
                    <button type="button" id="insetChooseBtn" class="btn-secondary flex items-center gap-2 text-sm">
                        <i class="fas fa-folder-open"></i> <span id="insetMediaLabel"><?php echo $mission_inset_img ? 'Replace photo' : 'Choose photo'; ?></span>
                    </button>
                    <p class="text-xs text-gray-400 mt-1.5">Images ≤ 5MB. Picking a file uploads and sets it right away.</p>
                    <?php if ($mission_inset_img): ?>
                        <button type="button" class="about-slot-clear text-xs text-red-500 hover:text-red-600 font-medium mt-1.5" data-slot="mission_inset">
                            <i class="fas fa-trash-alt mr-1"></i> Remove photo
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="landing-section">
        <div class="landing-section-title">
            <i class="fas fa-hand-holding-heart text-[#10A37F]"></i>
            <div>
                <h4>Core Values</h4>
                <p>The four value tiles below the About cards</p>
            </div>
        </div>

        <?php
        $core_values = [
            'protection'     => 'Protection',
            'service'        => 'Service',
            'sustainability' => 'Sustainability',
            'partnership'    => 'Partnership',
        ];
        ?>
        <div class="grid grid-cols-2 gap-4">
            <?php foreach ($core_values as $core_key => $label_hint): ?>
            <div class="landing-stat-box">
                <p class="landing-stat-label"><?php echo htmlspecialchars($label_hint); ?> Tile</p>
                <div class="grid grid-cols-2 gap-3">
                    <div class="form-group">
                        <label class="form-label" for="lp_core_<?php echo $core_key; ?>_title">Title</label>
                        <input type="text" name="lp_core_<?php echo $core_key; ?>_title" id="lp_core_<?php echo $core_key; ?>_title"
                               class="form-input"
                               value="<?php echo htmlspecialchars(landing_val('lp_core_' . $core_key . '_title', $label_hint)); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="lp_core_<?php echo $core_key; ?>_desc">Description</label>
                        <input type="text" name="lp_core_<?php echo $core_key; ?>_desc" id="lp_core_<?php echo $core_key; ?>_desc"
                               class="form-input"
                               value="<?php echo htmlspecialchars(landing_val('lp_core_' . $core_key . '_desc')); ?>">
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="landing-section">
        <div class="landing-section-title">
            <i class="fas fa-fill-drip text-[#10A37F]"></i>
            <div>
                <h4>Footer</h4>
                <p>Footer text and address</p>
            </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div class="form-group">
                <label class="form-label" for="lp_footer_about">Footer About Text</label>
                <textarea name="lp_footer_about" id="lp_footer_about" class="form-input" rows="3"><?php echo htmlspecialchars(landing_val('lp_footer_about')); ?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label" for="lp_footer_address">Address Line</label>
                <input type="text" name="lp_footer_address" id="lp_footer_address" class="form-input"
                       value="<?php echo htmlspecialchars(landing_val('lp_footer_address', 'San Isidro, Nueva Ecija')); ?>">
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- FORM ACTIONS -->
    <!-- ============================================ -->
    <div class="flex flex-wrap gap-3 justify-end pt-2 border-t border-gray-100">
        <button type="button" onclick="resetLandingForm()" class="btn-secondary flex items-center gap-2">
            <i class="fas fa-undo"></i> Reset
        </button>
        <button type="submit" class="btn-primary flex items-center gap-2">
            <i class="fas fa-save"></i> Save Landing Page
        </button>
    </div>
    <p class="text-xs text-gray-400 mt-3 text-center">
        <i class="fas fa-info-circle mr-1"></i>
        Changes take effect immediately on the public homepage after saving.
    </p>
</form>

<script>
(function() {
    'use strict';
    const form = document.getElementById('landingSettingsForm');
    window.resetLandingForm = function() {
        if (confirm('Reset all fields to their saved values? Unsaved changes will be lost.')) {
            location.reload();
        }
    };
    let formChanged = false;
    form.addEventListener('input', function() { formChanged = true; });
    form.addEventListener('submit', function() { formChanged = false; });
    // The hero media fields above the gallery live outside the <form> element
    // (linked via the form attribute), so watch them here too.
    ['lp_hero_badge', 'lp_hero_bg_type', 'lp_hero_bg_image', 'lp_hero_bg_video'].forEach(function(id) {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', function() { formChanged = true; });
    });
    window.addEventListener('beforeunload', function(e) {
        if (formChanged) {
            e.preventDefault();
            e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
        }
    });

    // Hero media gallery upload: clicking "Choose from Gallery" opens the OS
    // file picker, and picking a file uploads it right away — no more empty
    // "Upload to Gallery" submissions that show "No file selected for upload."
    const heroMediaInput = document.getElementById('hero_media');
    const heroChooseBtn = document.getElementById('heroGalleryChooseBtn');
    if (heroMediaInput && heroChooseBtn) {
        heroChooseBtn.addEventListener('click', function(e) {
            e.preventDefault();
            heroMediaInput.click();
        });
        heroMediaInput.addEventListener('change', function() {
            if (heroMediaInput.files && heroMediaInput.files.length > 0) {
                const label = document.getElementById('heroMediaLabel');
                if (label) label.textContent = heroMediaInput.files[0].name;
                const hint = document.getElementById('heroMediaHint');
                if (hint) hint.textContent = 'Uploading…';
                document.getElementById('heroGalleryUploadForm').submit();
            }
        });
    }

    // About (Mission & Vision) designated photo uploads: each card has its own
    // hidden file input; picking a file uploads and assigns it to that slot.
    [
        ['about_media_vision', 'visionChooseBtn', 'visionMediaLabel', 'vision_main'],
        ['about_media_mission', 'missionChooseBtn', 'missionMediaLabel', 'mission_main'],
        ['about_media_inset', 'insetChooseBtn', 'insetMediaLabel', 'mission_inset'],
    ].forEach(function(cfg) {
        const input = document.getElementById(cfg[0]);
        const btn = document.getElementById(cfg[1]);
        if (input && btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                input.click();
            });
            input.addEventListener('change', function() {
                if (input.files && input.files.length > 0) {
                    const label = document.getElementById(cfg[2]);
                    if (label) label.textContent = input.files[0].name;
                    const fd = new FormData();
                    fd.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
                    fd.append('sub_action', 'about_slot_upload');
                    fd.append('about_slot', cfg[3]);
                    fd.append('about_media', input.files[0]);
                    fetch('<?php echo BASE_URL; ?>index.php?page=settings&tab=landing', { method: 'POST', body: fd })
                        .then(function() { window.location.reload(); })
                        .catch(function() { window.location.reload(); });
                }
            });
        }
    });

    // Remove a designated about photo without a nested form.
    document.querySelectorAll('.about-slot-clear').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (!confirm('Remove this photo?')) return;
            const fd = new FormData();
            fd.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
            fd.append('sub_action', 'about_slot_clear');
            fd.append('about_slot', btn.getAttribute('data-slot'));
            fetch('<?php echo BASE_URL; ?>index.php?page=settings&tab=landing', { method: 'POST', body: fd })
                .then(function() { window.location.reload(); })
                .catch(function() { window.location.reload(); });
        });
    });
})();
</script>

<style>
    .landing-section {
        border: 1px solid rgba(16, 163, 127, 0.12);
        border-radius: 0.9rem;
        padding: 1.25rem;
        margin-bottom: 1.5rem;
        background: #fbfdfc;
    }
    .landing-section-title {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1.25rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px dashed #e2e8f0;
    }
    .landing-section-title i {
        width: 2.25rem;
        height: 2.25rem;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f0fdf4;
        border-radius: 0.6rem;
        font-size: 0.9rem;
        flex-shrink: 0;
    }
    .landing-section-title h4 {
        font-weight: 700;
        color: #1f2937;
        font-size: 0.95rem;
    }
    .landing-section-title p {
        font-size: 0.75rem;
        color: #6b7280;
        margin-top: 0.1rem;
    }
    .landing-stat-box {
        border: 1px solid #e5ece8;
        border-radius: 0.75rem;
        padding: 0.9rem;
        background: white;
    }
    .landing-stat-label {
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #6b7280;
        margin-bottom: 0.6rem;
    }
    .landing-card-block {
        border: 1px solid #e5ece8;
        border-radius: 0.75rem;
        padding: 0.9rem;
        background: white;
    }
    .landing-card-label {
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #6b7280;
        margin-bottom: 0.6rem;
    }
    .landing-sub-block {
        padding: 0.5rem 0;
    }
</style>