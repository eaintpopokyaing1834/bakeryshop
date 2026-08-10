<?php
// includes/footer.php — Pure footer partial
if (!function_exists('__')) {
    require_once __DIR__ . '/lang.php';
}
?>
<footer class="bg-pink-200 border-t border-stone-200/60 pt-16 pb-8 mt-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 w-full overflow-hidden">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 mb-12">

            <!-- Brand -->
            <div class="col-span-1 sm:col-span-2 lg:col-span-2">
                <div class="flex items-center gap-3 mb-4">
                    <img src="/sweetheaven/images/9102671.png" class="h-10 w-auto" alt="Sweet Heaven">
                    <span class="text-2xl font-bold text-stone-800">Sweet Heaven</span>
                </div>
                <p class="text-stone-500 leading-relaxed text-sm mb-6">
                    <?= __('footer_tagline') ?>
                </p>
                <div class="flex gap-3">
                    <a href="https://www.facebook.com/share/14nuHubYZhY/"
                        class="w-10 h-10 bg-stone-100 hover:bg-rose-100 text-stone-400 hover:text-rose-500 rounded-full flex items-center justify-center transition-colors">
                        
                        <img src="../images/facebook.png" class="w-6 h-6">
                    </a>
                    <a href="https://www.tiktok.com/@eaint_luv_eaint"
                        class="w-10 h-10 bg-stone-100 hover:bg-rose-100 text-stone-400 hover:text-rose-500 rounded-full flex items-center justify-center transition-colors">
                        <img src="../images/tiktok.png" class="w-6 h-6">
                    </a>
                    <a href="https://t.me/p2_iiv"
                        class="w-10 h-10 bg-stone-100 hover:bg-rose-100 text-stone-400 hover:text-rose-500 rounded-full flex items-center justify-center transition-colors">
                        <img src="../images/telegram.png" class="w-8 h-8">
                    </a>
                </div>
            </div>

            <!-- Quick Links -->
            <div>
                <h4 class="text-sm font-semibold text-stone-800 uppercase tracking-wider mb-5"><?= __('footer_quick_links') ?></h4>
                <ul class="space-y-3 text-stone-500 text-sm">
                    <li><a href="/sweetheaven/user/index.php" class="hover:text-rose-500 transition-colors"><?= __('nav_home') ?></a>
                    </li>
                    <li><a href="/sweetheaven/user/products.php"
                            class="hover:text-rose-500 transition-colors"><?= __('nav_products') ?></a></li>
                    <li><a href="/sweetheaven/user/cart.php" class="hover:text-rose-500 transition-colors"><?= __('footer_my_cart') ?></a>
                    </li>
                    <li><a href="/sweetheaven/user/profile.php" class="hover:text-rose-500 transition-colors"><?= __('footer_my_account') ?></a></li>
                    <li><a href="/sweetheaven/auth/register.php" class="hover:text-rose-500 transition-colors"><?= __('nav_signup') ?></a></li>
                </ul>
            </div>

            <!-- Contact -->
            <div>
                <h4 class="text-sm font-semibold text-stone-800 uppercase tracking-wider mb-5"><?= __('footer_contact_us') ?></h4>
                <ul class="space-y-3 text-stone-500 text-sm">
                    <li class="flex items-start gap-2 break-words">
                        <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span class="flex-1">Parcel Road, Hinthada, Myanmar</span>
                    </li>
                    <li class="flex items-center gap-2 break-words">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                        </svg>
                        <span class="flex-1">09 677996945</span>
                    </li>
                    <li class="flex items-center gap-2 break-all sm:break-words">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <span class="flex-1">hello@sweetheaven.com</span>
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="flex-1"><?= __('footer_hours') ?></span>
                    </li>
                </ul>
            </div>
        </div>

        <div
            class="border-t border-stone-200 pt-8 flex flex-col md:flex-row items-center justify-between gap-4 text-sm text-stone-400">
            <p><?= sprintf(__('footer_copyright'), date('Y')) ?></p>
            <div class="flex gap-6">
                <a href="#" class="hover:text-stone-600 transition-colors"><?= __('footer_privacy') ?></a>
                <a href="#" class="hover:text-stone-600 transition-colors"><?= __('footer_terms') ?></a>
            </div>
        </div>
    </div>
</footer>

<script>
window.formatPriceJS = function(amount) {
    let formatted = Number(amount).toLocaleString();
    const currentLang = '<?= currentLang() ?>';
    if (currentLang === 'my') {
        const en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        const my = ['၀', '၁', '၂', '၃', '၄', '၅', '၆', '၇', '၈', '၉'];
        for (let i = 0; i < 10; i++) {
            formatted = formatted.split(en[i]).join(my[i]);
        }
        return formatted + ' ကျပ်';
    }
    return formatted + ' MMK';
};
</script>