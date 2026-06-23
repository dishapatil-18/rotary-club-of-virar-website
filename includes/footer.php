<?php
// includes/footer.php
require_once __DIR__ . '/../config/club_settings.php';
?>

<!-- ================== ROTARY CLUB OF VIRAR - FOOTER ================== -->
<footer class="pt-16 fade-in-section" style="background-color: var(--rotary-blue);">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-white">
        <!-- Main Content: 3 Columns -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-12 pb-10 border-b border-indigo-700">
            
            <!-- Column 1: About Rotary Club -->
            <div>
                    <div class="flex items-center space-x-3 mb-4">
                        <div class="w-10 h-10 flex items-center justify-center" style="color: var(--rotary-yellow);">
                            <img src="assets/uploads/Logo/rotary-icon.png" alt="Rotary Logo" class="w-full h-full object-contain rounded-full shadow-lg border-2 border-blue-900">
                        </div>
                        <span class="text-xl font-extrabold tracking-tight" style="color: white;">Rotary Club of Virar</span>
                    </div>
                    <p class="text-indigo-200 mb-4 text-sm">Committed to service above self, we unite local leaders to create lasting change and foster goodwill in our community.</p>
                    <a href="aboutus.php" class="text-sm font-semibold hover:text-yellow-400 transition duration-300" style="color: var(--rotary-yellow);">Learn More &rarr; About Us</a>
                </div>

            <!-- Column 2: Quick Links -->
            <div>
                <h4 class="text-xl font-semibold mb-4 border-b border-yellow-500 inline-block pb-1">Quick Links</h4>
                <ul class="space-y-3 text-indigo-200">
                    <li><a href="index.php" class="hover:text-yellow-400 transition duration-150">Home</a></li>
                    <li><a href="aboutus.php" class="hover:text-yellow-400 transition duration-150">About Us</a></li>
                    <li><a href="activities.php" class="hover:text-yellow-400 transition duration-150">Our Activities</a></li>
                    <li><a href="mediaGallery.php" class="hover:text-yellow-400 transition duration-150">Media Gallery</a></li>
                    <li><a href="contact.php" class="hover:text-yellow-400 transition duration-150">Contact Us</a></li>
                    <li><a href="donate.php" target="_blank" class="hover:text-yellow-400 transition duration-150">Donate</a></li>
                </ul>
            </div>

            <!-- Column 3: Contact & Social Media -->
            <div>
                <h4 class="text-xl font-semibold mb-4 border-b border-yellow-500 inline-block pb-1">Get In Touch</h4>
                <ul class="space-y-3 text-indigo-200 mb-6 text-sm">
                    <li class="flex items-start">
                        <i class="fas fa-map-marker-alt mt-1 mr-3" style="color: var(--rotary-yellow);"></i>
                        <?= CLUB_NAME ?>, <?= CLUB_ADDRESS ?>
                    </li>
                    <li class="flex items-center">
                        <i class="fas fa-envelope mr-3" style="color: var(--rotary-yellow);"></i>
                        <?= CLUB_EMAIL ?>
                    </li>
                    <li class="flex items-center">
                        <i class="fas fa-phone mr-3" style="color: var(--rotary-yellow);"></i>
                        <?= CLUB_PHONE ?>
                    </li>
                </ul>
                
                <!-- Social Media Icons -->
                <div class="flex space-x-6 text-2xl">
                    <a href="<?= CLUB_FACEBOOK_URL ?>" target="_blank" rel="noopener noreferrer" class="social-icon hover:scale-[1.1]" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="<?= CLUB_INSTAGRAM_URL ?>" target="_blank" rel="noopener noreferrer" class="social-icon hover:scale-[1.1]" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Bar -->
    <div class="py-3 text-center text-sm font-medium" style="background-color: var(--rotary-yellow); color: var(--rotary-blue);">
        &copy; <?= date('Y') ?> Rotary Club of Virar | Service Above Self
    </div>
</footer>
<!-- ================== END FOOTER ================== -->
