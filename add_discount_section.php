<?php
// Patch: improve Special Discounts banner layout in user/index.php
$file = __DIR__ . '/user/index.php';
$content = file_get_contents($file);

// ── Locate only the banner div (between "Big Pink Banner" and "Products Sub-header") ──
$bannerStart = strpos($content, '<!-- ── Big Pink Banner ── -->');
$bannerEnd = strpos($content, '<!-- ── Products Sub-header ── -->');

if ($bannerStart === false || $bannerEnd === false) {
    echo "ERROR: banner markers not found\n";
    exit(1);
}

$newBanner = <<<'HTML'
<!-- ── Big Pink Banner ── -->
            <div class="relative rounded-3xl overflow-hidden mb-12 shadow-2xl"
                style="background: linear-gradient(130deg, #ffe4ef 0%, #ffc2d9 45%, #ffaac8 100%); min-height: 320px;">

                <!-- Confetti / decorative specks -->
                <div class="absolute inset-0 pointer-events-none overflow-hidden">
                    <div class="absolute top-5  left-8   w-3 h-3 rounded-full bg-yellow-400 opacity-70"></div>
                    <div class="absolute top-14 left-24  w-2 h-2 rounded-full bg-rose-400   opacity-60"></div>
                    <div class="absolute top-9  left-44  w-5 h-1 rounded-full bg-pink-300   opacity-80"></div>
                    <div class="absolute bottom-10 left-20 w-2 h-2 rounded-full bg-amber-400 opacity-70"></div>
                    <div class="absolute bottom-20 left-36 w-4 h-1 rounded-full bg-rose-300  opacity-60"></div>
                    <div class="absolute top-5  right-8   w-3 h-3 rounded-full bg-amber-400  opacity-70"></div>
                    <div class="absolute top-16 right-32  w-2 h-2 rounded-full bg-pink-400   opacity-60"></div>
                    <div class="absolute bottom-12 right-20 w-5 h-1 rounded-full bg-yellow-300 opacity-80"></div>
                    <div class="absolute bottom-24 right-44 w-3 h-3 rounded-full bg-rose-400  opacity-50"></div>
                    <!-- ribbons -->
                    <div class="absolute top-0 left-1/3  w-px h-24 bg-amber-300/35 rotate-12"></div>
                    <div class="absolute top-0 right-1/3 w-px h-20 bg-rose-300/35 -rotate-12"></div>
                    <!-- large soft circle glow right -->
                    <div class="absolute -right-20 top-1/2 -translate-y-1/2 w-72 h-72 rounded-full opacity-10"
                        style="background:radial-gradient(circle,#fff,transparent);"></div>
                </div>

                <!--
                    4-column grid on desktop:
                      col-1 (5/12) : offer text
                      col-2 (3/12) : main cake (larger, shifted right via padding-left)
                      col-3 (2/12) : stacked accessory images
                      col-4 (2/12) : circle badge + button
                -->
                <div class="relative z-10 grid items-stretch
                            grid-cols-1
                            md:grid-cols-[5fr_3fr_2fr_2fr]
                            gap-0 min-h-[320px]">

                    <!-- ① Left: offer text ─────────────────────────────────── -->
                    <div class="flex flex-col justify-center p-8 md:pl-12 md:pr-6 md:py-10">

                        <!-- "Limited Time Offer" pill -->
                        <span class="inline-flex self-start items-center gap-1.5 mb-5 px-4 py-1.5 rounded-full
                                     text-xs font-extrabold uppercase tracking-widest text-white shadow"
                              style="background:#e8746a;">
                            🏷️ Limited Time Offer
                        </span>

                        <!-- Giant percentage -->
                        <div class="mb-3">
                            <span class="block text-gray-700 text-xl font-bold leading-none">Up to</span>
                            <span class="block font-black"
                                  style="font-size: clamp(4rem,8vw,6rem); color:#e8746a; line-height:1;">30%</span>
                            <span class="block text-gray-700 font-black tracking-tight"
                                  style="font-size: clamp(1.5rem,3vw,2rem); line-height:1.1;">OFF</span>
                        </div>

                        <p class="font-extrabold text-gray-600 uppercase tracking-widest text-xs mt-1 mb-6">
                            On Selected Cakes!
                        </p>

                        <!-- Feature micro-badges -->
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ([
                                ['🎂', 'Best Quality',   'Premium Ingredients'],
                                ['🚚', 'Fast Delivery',  'On time at your door'],
                                ['✅', '100% Fresh',     'Made with love'],
                            ] as $feat): ?>
                            <div class="flex items-center gap-2 bg-white/65 backdrop-blur-sm rounded-xl px-3 py-2 shadow-sm">
                                <span class="text-sm"><?= $feat[0] ?></span>
                                <div class="leading-none">
                                    <p class="text-[10px] font-bold text-gray-700"><?= $feat[1] ?></p>
                                    <p class="text-[9px]  text-gray-500 mt-0.5"><?= $feat[2] ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- ② Main cake image ──────────────────────────────────── -->
                    <!-- padding-left shifts the image to the right; items-end pushes it to bottom -->
                    <div class="hidden md:flex items-end justify-center pl-4 overflow-visible">
                        <img src="/sweetheaven/images/removepink.png"
                             alt="Featured Discount Cake"
                             class="w-auto drop-shadow-2xl"
                             style="max-height:360px; margin-bottom:-2px; object-fit:contain;">
                    </div>

                    <!-- ③ Stacked accessory images (shifted right via pl-2) ── -->
                    <div class="hidden md:flex flex-col justify-center gap-3 pl-3 pr-1 py-6">
                        <div class="rounded-2xl overflow-hidden shadow-md flex-1 flex items-center bg-white/20">
                            <img src="/sweetheaven/images/4accessorycake.png"
                                 alt="Cake accessory"
                                 class="w-full h-full object-cover drop-shadow-lg"
                                 style="max-height:160px;">
                        </div>
                        <div class=" overflow-hidden  flex items-center">
                            <img src="/sweetheaven/images/gitbox.png"
                                 alt="Gift box"
                                 class="w-full h-full object-cover drop-shadow-lg"
                                 style="max-height:160px;">
                        </div>
                    </div>

                    <!-- ④ Right: compact circle badge + CTA ────────────────── -->
                    <div class="flex flex-col items-center justify-center gap-5 p-6 md:pr-8">

                        <!-- Circle badge -->
                        <div class="relative flex items-center justify-center w-36 h-36 rounded-full shadow-xl flex-shrink-0"
                             style="background:#e8746a;">
                            <div class="absolute inset-2 rounded-full border-2 border-white/40"></div>
                            <div class="text-center text-white px-2 z-10 space-y-0.5">
                                <p class="text-[9px] font-bold uppercase tracking-wider leading-none">This Week Only!</p>
                                <p class="text-[10px] font-semibold leading-snug">Save Big on<br>Your Favorite</p>
                                <p class="serif text-xl font-black leading-none">Cakes!</p>
                                <span class="text-base">❤️</span>
                            </div>
                        </div>

                        <!-- Shop Now -->
                        <a href="#discounted-products"
                           class="inline-flex items-center gap-1.5 font-extrabold px-6 py-3 rounded-full text-xs shadow-lg
                                  hover:-translate-y-0.5 transition-all duration-200 uppercase tracking-widest whitespace-nowrap"
                           style="background:#e8746a; color:#fff;">
                            Shop Now
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    </div>

                </div>
            </div>
HTML;

$before = substr($content, 0, $bannerStart);
$after = substr($content, $bannerEnd);

$content = $before . $newBanner . '            ' . $after;

file_put_contents($file, $content);
echo "SUCCESS: Banner layout improved.\n";
