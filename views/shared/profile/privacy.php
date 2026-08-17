<?php
// views/shared/profile/privacy.php - partial, included by views/shared/profile/profile.php
?>
                    <!-- ========== PRIVACY NOTICE ========== -->
                    <div id="section-privacy">
                        <div class="flex items-center gap-2 mb-4">
                            <i class="fas fa-user-shield text-[#10A37F]"></i>
                            <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wider">Privacy Notice</h3>
                        </div>
                        <div class="legal-content">
                            <p><strong>Effective Date:</strong> [Insert Date]</p>
                            <p>Your privacy is critically important to us. This Privacy Notice outlines how the <?php echo htmlspecialchars($system_name); ?> Platform collects, uses, protects, and shares your personal information in strict compliance with the Philippine Data Privacy Act of 2012 (Republic Act No. 10173).</p>
                            <div>
                                <h4>1. Information We Collect</h4>
                                <p>To provide a secure and functional reporting environment, we collect the following data:</p>
                                <ul>
                                    <li><strong>Personal Identification Data:</strong> Full name, email address, and mobile number (collected during registration).</li>
                                    <li><strong>Geospatial Data:</strong> Exact GPS coordinates (latitude and longitude) captured via your device when submitting an environmental report.</li>
                                    <li><strong>Multimedia Data:</strong> Photographic evidence uploaded to support your environmental report.</li>
                                    <li><strong>System Data:</strong> IP addresses, browser types, and timestamp logs for audit and security purposes.</li>
                                </ul>
                            </div>
                            <div>
                                <h4>2. How We Use Your Information</h4>
                                <p>We use your data exclusively for civic and administrative purposes, specifically to:</p>
                                <ul>
                                    <li>Verify your identity and secure your account using mobile OTP.</li>
                                    <li>Process, validate, and route your environmental reports to the designated Barangay and MENRO officials.</li>
                                    <li>Communicate with you regarding the status of your reports (via email notifications) or system announcements.</li>
                                    <li>Generate municipal-level analytics, trend statistics, and spatial heatmaps (Note: Personal names are stripped from datasets used for municipal analytics to ensure reporter anonymity in public reports).</li>
                                </ul>
                            </div>
                            <div>
                                <h4>3. How We Share Your Information</h4>
                                <p>Your data is treated with strict confidentiality. We do not sell or rent your personal information. We only share data with:</p>
                                <ul>
                                    <li><strong>Authorized LGU Personnel:</strong> Barangay officials and MENRO administrators handling your specific report.</li>
                                    <li><strong>Third-Party Service Providers:</strong> We utilize secure APIs to maintain system functionality. Your mobile number is processed by our SMS gateway provider solely for OTP delivery, and your email address is processed by our email service provider strictly for routing automated system notifications.</li>
                                    <li><strong>Legal Compliance:</strong> We may disclose information if mandated by Philippine law or a valid court order.</li>
                                </ul>
                            </div>
                            <div>
                                <h4>4. Data Security and Retention</h4>
                                <p><?php echo htmlspecialchars($system_name); ?> implements industry-standard security measures, including encrypted passwords and secure database architecture, to protect your data against unauthorized access. Personal data will be retained only for as long as necessary to fulfill the purposes outlined in this policy or to comply with LGU archival regulations, after which it will be securely anonymized or deleted.</p>
                            </div>
                            <div>
                                <h4>5. Your Rights as a Data Subject</h4>
                                <p>Under R.A. 10173, you have the right to:</p>
                                <ul>
                                    <li>Be informed about how your data is processed.</li>
                                    <li>Access the personal information you have provided to us.</li>
                                    <li>Update or correct inaccuracies in your profile.</li>
                                    <li>Request the suspension, withdrawal, or removal of your personal data from our active databases, subject to LGU record-keeping laws.</li>
                                </ul>
                            </div>
                            <div>
                                <h4>6. Contact Us</h4>
                                <p>If you have any questions, concerns, or requests regarding this Privacy Notice or your personal data, please contact the <?php echo htmlspecialchars($system_name); ?> System Administrator or the designated Data Protection Officer (DPO) of the Municipality of San Isidro.</p>
                            </div>
                        </div>
                    </div>
