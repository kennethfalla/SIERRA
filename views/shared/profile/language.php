<?php
// views/shared/profile/language.php - Language preference section.
// Included by views/shared/profile/profile.php. Selecting a language saves
// a cookie and reloads the page so the whole app switches instantly.
$lang = app_lang();
?>
                    <!-- ========== LANGUAGE SETTINGS ========== -->
                    <div id="section-language-settings">
                        <div class="flex items-center gap-2 mb-4">
                            <i class="fas fa-globe text-[#10A37F]"></i>
                            <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wider"><?php echo t('Language Settings'); ?></h3>
                        </div>
                        <p class="text-sm text-gray-500 mb-4"><?php echo t('Choose the language used across the application. Changes apply immediately.'); ?></p>

                        <div class="lang-list">
                            <button type="button" data-lang-opt="en" class="lang-list-option <?php echo $lang === 'en' ? 'active' : ''; ?>" aria-pressed="<?php echo $lang === 'en' ? 'true' : 'false'; ?>">
                                <span class="flex-1 min-w-0 text-left">
                                    <span class="flex items-center gap-2">
                                        <span class="font-semibold text-gray-800 no-translate">English</span>
                                        <span class="lang-code">EN</span>
                                    </span>
                                    <span class="block text-xs text-gray-500 mt-0.5">The default language of the application.</span>
                                </span>
                                <span class="lang-radio" aria-hidden="true"><i class="fas fa-check"></i></span>
                            </button>

                            <button type="button" data-lang-opt="fil" class="lang-list-option <?php echo $lang === 'fil' ? 'active' : ''; ?>" aria-pressed="<?php echo $lang === 'fil' ? 'true' : 'false'; ?>">
                                <span class="flex-1 min-w-0 text-left">
                                    <span class="flex items-center gap-2">
                                        <span class="font-semibold text-gray-800 no-translate">Filipino</span>
                                        <span class="lang-code">FIL</span>
                                    </span>
                                    <span class="block text-xs text-gray-500 mt-0.5">Use technical Filipino words across the application.</span>
                                </span>
                                <span class="lang-radio" aria-hidden="true"><i class="fas fa-check"></i></span>
                            </button>
                        </div>
                    </div>