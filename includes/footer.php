<?php
require_once __DIR__ . '/../config/club_settings.php';
require_once __DIR__ . '/website_settings.php';

if (!function_exists('fe')) {
    function fe($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('footerImg')) {
    function footerImg($path) {
        if (!$path) return '';
        if (str_starts_with($path, '../')) return substr($path, 3);
        return $path;
    }
}

$footerContent = [];
if (isset($conn)) {
    $r = $conn->query("SELECT section, content FROM site_content WHERE page = 'footer'");
    if ($r) {
        while ($row = $r->fetch_assoc()) $footerContent[$row['section']] = $row['content'];
    }
}

// Contact & Social Links (centralized source)
$contactSocial = [];
if (isset($conn)) {
    $r = $conn->query("SELECT section, content FROM site_content WHERE page = 'contact_social'");
    if ($r) {
        while ($row = $r->fetch_assoc()) $contactSocial[$row['section']] = $row['content'];
    }
}
$csMap = ['email' => 'footer_email', 'phone' => 'footer_phone', 'address' => 'footer_address', 'facebook_url' => 'footer_facebook_url', 'instagram_url' => 'footer_instagram_url'];
foreach ($csMap as $csKey => $footerKey) {
    if (!empty($contactSocial[$csKey])) $footerContent[$footerKey] = $contactSocial[$csKey];
}
$csMapUrl = ['map_url' => 'footer_map_url', 'map_embed_url' => 'footer_map_embed_url'];
foreach ($csMapUrl as $csKey => $footerKey) {
    if (!empty($contactSocial[$csKey])) $footerContent[$footerKey] = $contactSocial[$csKey];
}

$ws = getWebsiteSettings($conn ?? null);
$footerLogo = footerImg($footerContent['footer_logo'] ?? '') ?: $ws['website_logo'];
$footerClubName = ($footerContent['footer_club_name'] ?? '') ?: $ws['website_name'];
$footerDesc = $footerContent['footer_description'] ?? 'Committed to service above self, we unite local leaders to create lasting change and foster goodwill in our community.';
$footerCopyright = $footerContent['footer_copyright_text'] ?? 'Rotary Club of Virar | Service Above Self';
$footerDeveloperCredit = $footerContent['footer_developer_credit'] ?? '';

$footerPhone = ($footerContent['footer_phone'] ?? '') ?: CLUB_PHONE;
$footerEmail = ($footerContent['footer_email'] ?? '') ?: CLUB_EMAIL;
$footerAddress = ($footerContent['footer_address'] ?? '') ?: CLUB_ADDRESS;

$footerFB = ($footerContent['footer_facebook_url'] ?? '') ?: CLUB_FACEBOOK_URL;
$footerIG = ($footerContent['footer_instagram_url'] ?? '') ?: CLUB_INSTAGRAM_URL;

$socialPlatforms = [];
$socialCfg = [
    ['url' => $footerFB, 'icon' => 'fab fa-facebook-f', 'label' => 'Facebook'],
    ['url' => $footerIG, 'icon' => 'fab fa-instagram', 'label' => 'Instagram'],
    ['url' => $footerContent['footer_youtube_url'] ?? '', 'icon' => 'fab fa-youtube', 'label' => 'YouTube'],
    ['url' => $footerContent['footer_linkedin_url'] ?? '', 'icon' => 'fab fa-linkedin-in', 'label' => 'LinkedIn'],
    ['url' => $footerContent['footer_twitter_url'] ?? '', 'icon' => 'fab fa-twitter', 'label' => 'Twitter'],
];
foreach ($socialCfg as $s) {
    if ($s['url']) $socialPlatforms[] = $s;
}

$footerLinks = [];
for ($i = 1; $i <= 7; $i++) {
    $t = $footerContent['footer_link_' . $i . '_text'] ?? '';
    $u = $footerContent['footer_link_' . $i . '_url'] ?? '';
    if ($t && $u) {
        $footerLinks[] = ['text' => $t, 'url' => $u];
    }
}
if (empty($footerLinks)) {
    $footerLinks = [
        ['text' => 'Home', 'url' => 'index.php'],
        ['text' => 'About Us', 'url' => 'aboutus.php'],
        ['text' => 'Our Activities', 'url' => 'activities.php'],
        ['text' => 'Our Team', 'url' => 'team.php'],
        ['text' => 'Media Gallery', 'url' => 'mediaGallery.php'],
        ['text' => 'Contact Us', 'url' => 'contact.php'],
        ['text' => 'Donate', 'url' => 'donate.php'],
    ];
}
?>

<footer class="pt-16" style="background-color: var(--rotary-blue);">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-white">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-12 pb-10 border-b border-indigo-700">
            <div>
                <div class="flex items-center space-x-3 mb-4">
                    <div class="w-10 h-10 flex items-center justify-center" style="color: var(--rotary-yellow);">
                        <img src="<?= fe($footerLogo ?: 'assets/uploads/Logo/rotary-icon.png') ?>" alt="Rotary Logo" class="w-full h-full object-contain rounded-full shadow-lg border-2 border-blue-900">
                    </div>
                    <span class="text-xl font-extrabold tracking-tight" style="color: white;"><?= fe($footerClubName) ?></span>
                </div>
                <p class="text-indigo-200 mb-4 text-sm"><?= fe($footerDesc) ?></p>
                <a href="aboutus.php" class="text-sm font-semibold hover:text-yellow-400 transition duration-300" style="color: var(--rotary-yellow);">Learn More &rarr; About Us</a>
            </div>
            <div>
                <h4 class="text-xl font-semibold mb-4 border-b border-yellow-500 inline-block pb-1">Quick Links</h4>
                <ul class="space-y-3 text-indigo-200">
                    <?php foreach ($footerLinks as $fl): ?>
                    <li><a href="<?= fe($fl['url']) ?>" class="hover:text-yellow-400 transition duration-150"><?= fe($fl['text']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div>
                <h4 class="text-xl font-semibold mb-4 border-b border-yellow-500 inline-block pb-1">Get In Touch</h4>
                <ul class="space-y-3 text-indigo-200 mb-6 text-sm">
                    <li class="flex items-start">
                        <i class="fas fa-map-marker-alt mt-1 mr-3" style="color: var(--rotary-yellow);"></i>
                        <?= fe($footerAddress) ?>
                    </li>
                    <li class="flex items-center">
                        <i class="fas fa-envelope mr-3" style="color: var(--rotary-yellow);"></i>
                        <?= fe($footerEmail) ?>
                    </li>
                    <li class="flex items-center">
                        <i class="fas fa-phone mr-3" style="color: var(--rotary-yellow);"></i>
                        <?= fe($footerPhone) ?>
                    </li>
                </ul>
                <?php if (!empty($socialPlatforms)): ?>
                <div class="flex space-x-6 text-2xl">
                    <?php foreach ($socialPlatforms as $sp): ?>
                    <a href="<?= fe($sp['url']) ?>" target="_blank" rel="noopener noreferrer" class="social-icon hover:scale-[1.1]" aria-label="<?= fe($sp['label']) ?>"><i class="<?= fe($sp['icon']) ?>"></i></a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="py-3 text-center text-sm font-medium" style="background-color: var(--rotary-yellow); color: var(--rotary-blue);">
        &copy; <?= htmlspecialchars($ws['copyright_year']) ?> <?= fe($footerCopyright) ?>
        <?php if ($footerDeveloperCredit): ?> | <?= fe($footerDeveloperCredit) ?><?php endif; ?>
    </div>
</footer>
