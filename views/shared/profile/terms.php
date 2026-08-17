<?php
// views/shared/profile/terms.php - partial, included by views/shared/profile/profile.php
?>
                    <!-- ========== TERMS OF SERVICE ========== -->
                    <div id="section-terms">
                        <div class="flex items-center gap-2 mb-4">
                            <i class="fas fa-file-contract text-[#10A37F]"></i>
                            <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wider">Terms of Service</h3>
                        </div>
                        <div class="legal-content">
                            <p>Welcome to <?php echo htmlspecialchars($system_name); ?> (Web-Based Environmental Reporting Application). By registering an account and using this platform, you agree to comply with and be bound by the following Terms of Service. If you do not agree to these terms, please do not use the application.</p>
                            <div>
                                <h4>1. Description of Service</h4>
                                <p><?php echo htmlspecialchars($system_name); ?> is a civic technology platform designed to facilitate the reporting, tracking, and management of environmental hazards within the Municipality of San Isidro. The system allows users to submit geotagged reports and photographic evidence to the relevant Barangay and the Municipal Environment and Natural Resources Office (MENRO) for appropriate action.</p>
                            </div>
                            <div>
                                <h4>2. User Accounts and Security</h4>
                                <ul>
                                    <li><strong>Eligibility:</strong> You must be at least 18 years old to register. The platform is open to both residents and non-residents of San Isidro.</li>
                                    <li><strong>Verification:</strong> Upon registration, you are required to verify your identity via a One-Time Password (OTP) sent to your registered mobile number.</li>
                                    <li><strong>Accountability:</strong> You are entirely responsible for maintaining the confidentiality of your login credentials. You agree to notify the administrators immediately of any unauthorized use of your account.</li>
                                </ul>
                            </div>
                            <div>
                                <h4>3. Acceptable Use and User Conduct</h4>
                                <p>By using <?php echo htmlspecialchars($system_name); ?>, you agree that you will NOT:</p>
                                <ul>
                                    <li>Submit false, misleading, or malicious environmental reports.</li>
                                    <li>Upload photos or content that are unlawful, defamatory, obscene, or completely unrelated to environmental hazards.</li>
                                    <li>Attempt to bypass system security, manipulate GPS coordinates (location spoofing), or disrupt the normal operations of the platform.</li>
                                    <li>Use the application for any commercial or non-civic purposes.</li>
                                </ul>
                                <p class="mt-2">The administrators reserve the right to suspend or permanently ban accounts found to be submitting spam, fraudulent reports, or violating any of these terms.</p>
                            </div>
                            <div>
                                <h4>4. Content Ownership and Licensing</h4>
                                <p>By uploading photographic evidence and submitting text descriptions to <?php echo htmlspecialchars($system_name); ?>, you grant the Municipality of San Isidro, the respective Barangays, and MENRO a perpetual, non-exclusive, royalty-free license to use, reproduce, and distribute the content for the purpose of environmental monitoring, investigation, analytics, and public records.</p>
                            </div>
                            <div>
                                <h4>5. Limitation of Liability</h4>
                                <p>The <?php echo htmlspecialchars($system_name); ?> platform is provided "as is." While the system ensures that reports are routed to the proper local government authorities, the developers and the Municipality of San Isidro do not guarantee immediate physical resolution of every submitted report. The system is not a substitute for emergency services (such as 911 or the local fire department) in the event of immediate threats to life or property.</p>
                            </div>
                            <div>
                                <h4>6. Governing Law</h4>
                                <p>These Terms shall be governed by and construed in accordance with the laws of the Republic of the Philippines.</p>
                            </div>
                        </div>
                    </div>
