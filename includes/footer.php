<?php
// includes/footer.php — Pure footer partial
if (!function_exists('__')) {
    require_once __DIR__ . '/lang.php';
}
?>
<footer class="bg-pink-200 border-t border-stone-200/60 pt-16 pb-8 mt-16">
    <div class="max-w-7xl mx-auto px-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-10 mb-12">

            <!-- Brand -->
            <div class="col-span-1 md:col-span-2">
                <div class="flex items-center gap-3 mb-4">
                    <img src="/sweetheaven/images/9102671.png" class="h-10 w-auto" alt="Sweet Heaven">
                    <span class="text-2xl font-bold text-stone-800">Sweet Heaven</span>
                </div>
                <p class="text-stone-500 leading-relaxed text-sm mb-6">
                    <?= __('footer_tagline') ?>
                </p>
                <div class="flex gap-3">
                    <a href="#"
                        class="w-10 h-10 bg-stone-100 hover:bg-rose-100 text-stone-400 hover:text-rose-500 rounded-full flex items-center justify-center transition-colors">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                        </svg>
                    </a>
                    <a href="#"
                        class="w-10 h-10 bg-stone-100 hover:bg-rose-100 text-stone-400 hover:text-rose-500 rounded-full flex items-center justify-center transition-colors">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M12.315 2c2.43 0 2.784.013 3.808.06 3.897.165 5.76 2.033 5.925 5.925.045 1.026.06 1.379.06 3.808 0 2.43-.013 2.784-.06 3.808-.165 3.893-2.028 5.76-5.925 5.925-1.026.045-1.378.06-3.808.06-2.43 0-2.784-.013-3.808-.06-3.893-.165-5.76-2.032-5.925-5.925C2.013 14.784 2 14.431 2 12c0-2.43.013-2.784.06-3.808C2.225 4.033 4.092 2.165 7.99 2c1.024-.045 1.378-.06 4.325-.06zm0-2.316c-2.474 0-2.785.013-3.757.058-4.964.219-7.71 2.962-7.929 7.929C.559 8.785.546 9.096.546 12c0 2.904.013 3.215.058 4.186.219 4.967 2.962 7.71 7.929 7.929.972.044 1.283.058 3.757.058 2.474 0 2.785-.013 3.757-.058 4.964-.219 7.71-2.962 7.929-7.929.044-.972.058-1.283.058-3.757 0-2.474-.013-2.785-.058-3.757-.219-4.967-2.962-7.71-7.929-7.929C15.1-.302 14.789-.316 12.315-.316zm0 5.535a6.781 6.781 0 100 13.562 6.781 6.781 0 000-13.562zm0 11.188a4.406 4.406 0 110-8.813 4.406 4.406 0 010 8.813zm8.646-11.455a1.585 1.585 0 11-3.17 0 1.585 1.585 0 013.17 0z" />
                        </svg>
                    </a>
                    <a href="#"
                        class="w-10 h-10 bg-stone-100 hover:bg-rose-100 text-stone-400 hover:text-rose-500 rounded-full flex items-center justify-center transition-colors">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M23.495 6.205a3.007 3.007 0 00-2.088-2.088c-1.87-.501-9.396-.501-9.396-.501s-7.507-.01-9.396.501A3.007 3.007 0 00.527 6.205a31.247 31.247 0 00-.522 5.805 31.247 31.247 0 00.522 5.783 3.007 3.007 0 002.088 2.088c1.868.502 9.396.502 9.396.502s7.506 0 9.396-.502a3.007 3.007 0 002.088-2.088 31.247 31.247 0 00.5-5.783 31.247 31.247 0 00-.5-5.805zM9.609 15.601V8.408l6.264 3.602z" />
                        </svg>
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
                    <li class="flex items-start gap-2">
                        <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        123 Bakery Lane, Yangon, Myanmar
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                        </svg>
                        09 4500 12345
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        hello@sweetheaven.com
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <?= __('footer_hours') ?>
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