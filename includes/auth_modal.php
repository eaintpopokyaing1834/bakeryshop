<?php
// includes/auth_modal.php — Shared auth modal for guest users
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/lang.php';
?>
<!-- Auth Modal -->
<div id="authModal" class="fixed inset-0 z-[999] flex items-center justify-center p-4 hidden" role="dialog"
    aria-modal="true" aria-label="Authentication">
    <!-- Backdrop -->
    <div id="authBackdrop" class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeAuthModal()"></div>

    <!-- Card -->
    <div class="relative w-full max-w-md overflow-hidden bg-[#fff9f9] rounded-[28px] shadow-[0_24px_64px_rgba(180,80,80,.14),0_4px_16px_rgba(200,100,100,.08)] border border-[#f5dede]"
        style="animation: modalSlideIn 0.38s cubic-bezier(0.34,1.46,0.64,1) both">

        <!-- Close button -->
        <button onclick="closeAuthModal()" id="authCloseBtn" class="absolute top-[18px] right-[18px] w-8 h-8 rounded-full bg-[#fce8e8] text-[#b87070] border-none cursor-pointer flex items-center justify-center transition-all duration-200 hover:bg-[#f9d4d4] hover:text-[#9a4f4f] hover:scale-110 z-10" aria-label="Close">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                <path d="M1 1l12 12M13 1L1 13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            </svg>
        </button>

        <!-- Brand header -->
        <div class="flex items-center gap-[14px] px-[30px] pt-[30px] pb-0">
            <div class="w-[52px] h-[52px] rounded-2xl shrink-0 bg-gradient-to-br from-[#fce8e8] to-[#fdf0f0] border border-[#f5d5d5] flex items-center justify-center">
                <img src="/sweetheaven/images/cake.png" class="w-5 h-5">
            </div>
            <div>
                <h2 id="authModalTitle" class="text-xl font-bold text-[#3d2020] leading-[1.3] m-0">Welcome back</h2>
                <p id="authModalSubtitle" class="text-[.78rem] text-[#b08080] mt-[3px] mb-0 mx-0">Sign in to your Sweet Heaven account</p>
            </div>
        </div>

        <div class="px-[30px] pt-[22px] pb-[28px]">

            <!-- LOGIN PANEL -->
            <div id="loginPanel">
                <div id="loginError" class="flex items-start gap-[9px] px-3.5 py-[11px] rounded-xl text-[.8rem] leading-[1.45] mb-2.5 bg-[#fff0f0] border border-[#f5c8c8] text-[#a85050] hidden">
                    <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor" class="shrink-0">
                        <path fill-rule="evenodd"
                            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                            clip-rule="evenodd" />
                    </svg>
                    <span id="loginErrorMsg"></span>
                </div>

                <form id="modalLoginForm" class="flex flex-col gap-3 mt-1" onsubmit="submitLogin(event)">
                    <div class="relative flex items-center">
                        <span class="absolute left-[13px] text-[#d4a0a0] pointer-events-none flex transition-colors duration-200 peer-focus:text-[#c97878]">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </span>
                        <input type="email" id="modalEmail" name="email" required autocomplete="email"
                            placeholder="Email address" class="w-full py-3 pl-10 pr-3.5 rounded-[14px] border-[1.5px] border-[#f0d8d8] bg-white text-[.855rem] text-[#3d2020] outline-none font-inherit transition-all duration-200 placeholder:text-[#d4adad] focus:border-[#e8a0a0] focus:shadow-[0_0_0_3.5px_rgba(220,130,130,.14)] focus:bg-[#fffbfb] peer">
                    </div>
                    <div class="relative flex items-center">
                        <span class="absolute left-[13px] text-[#d4a0a0] pointer-events-none flex transition-colors duration-200 peer-focus:text-[#c97878]">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </span>
                        <input type="password" id="modalPassword" name="password" required
                            autocomplete="current-password" placeholder="Password" class="w-full py-3 pl-10 pr-[42px] rounded-[14px] border-[1.5px] border-[#f0d8d8] bg-white text-[.855rem] text-[#3d2020] outline-none font-inherit transition-all duration-200 placeholder:text-[#d4adad] focus:border-[#e8a0a0] focus:shadow-[0_0_0_3.5px_rgba(220,130,130,.14)] focus:bg-[#fffbfb] peer">
                        <button type="button" onclick="toggleModalPassword('modalPassword',this)"
                            class="absolute right-[13px] bg-transparent border-none cursor-pointer text-[#d4a0a0] p-0.5 flex transition-colors duration-200 hover:text-[#c97878]" tabindex="-1">
                            <svg width="17" height="17" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>
                    <button type="submit" id="loginSubmitBtn" class="w-full py-3 px-5 rounded-[14px] bg-gradient-to-br from-[#e8918a] to-[#d97070] text-white text-[.9rem] font-semibold border-none cursor-pointer flex items-center justify-center gap-2 mt-1 font-inherit tracking-[.01em] transition-all duration-200 shadow-[0_4px_16px_rgba(210,100,100,.25)] hover:opacity-[.92] hover:-translate-y-px hover:shadow-[0_8px_22px_rgba(210,100,100,.3)] active:scale-[.98] disabled:opacity-[.65] disabled:cursor-not-allowed disabled:transform-none">
                        <span id="loginBtnText">Sign In</span>
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                    </button>
                </form>

                <p class="text-center text-[.8rem] text-[#b08080] mt-[18px]">
                    Don't have an account?
                    <button onclick="switchTab('register')" class="bg-transparent border-none cursor-pointer font-bold text-[#d97070] text-inherit font-inherit p-0 ml-[3px] transition-colors duration-200 hover:text-[#b85555] hover:underline">Create one free</button>
                </p>
            </div>

            <!-- REGISTER PANEL -->
            <div id="registerPanel" class="hidden">
                <div id="registerError" class="flex items-start gap-[9px] px-3.5 py-[11px] rounded-xl text-[.8rem] leading-[1.45] mb-2.5 bg-[#fff0f0] border border-[#f5c8c8] text-[#a85050] hidden">
                    <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor" class="shrink-0">
                        <path fill-rule="evenodd"
                            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                            clip-rule="evenodd" />
                    </svg>
                    <span id="registerErrorMsg"></span>
                </div>
                <div id="registerSuccess" class="flex items-start gap-[9px] px-3.5 py-[11px] rounded-xl text-[.8rem] leading-[1.45] mb-2.5 bg-[#f0faf4] border border-[#b8e6c8] text-[#3a7a55] hidden">
                    <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor" class="shrink-0">
                        <path fill-rule="evenodd"
                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                            clip-rule="evenodd" />
                    </svg>
                    <span id="registerSuccessMsg"></span>
                </div>

                <form id="modalRegisterForm" class="flex flex-col gap-3 mt-1" onsubmit="submitRegister(event)">
                    <div class="relative flex items-center">
                        <span class="absolute left-[13px] text-[#d4a0a0] pointer-events-none flex transition-colors duration-200 peer-focus:text-[#c97878]">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </span>
                        <input type="text" id="regName" name="name" required autocomplete="name"
                            placeholder="Full name" class="w-full py-3 pl-10 pr-3.5 rounded-[14px] border-[1.5px] border-[#f0d8d8] bg-white text-[.855rem] text-[#3d2020] outline-none font-inherit transition-all duration-200 placeholder:text-[#d4adad] focus:border-[#e8a0a0] focus:shadow-[0_0_0_3.5px_rgba(220,130,130,.14)] focus:bg-[#fffbfb] peer">
                    </div>
                    <div class="relative flex items-center">
                        <span class="absolute left-[13px] text-[#d4a0a0] pointer-events-none flex transition-colors duration-200 peer-focus:text-[#c97878]">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </span>
                        <input type="email" id="regEmail" name="email" required autocomplete="email"
                            placeholder="Email address" class="w-full py-3 pl-10 pr-3.5 rounded-[14px] border-[1.5px] border-[#f0d8d8] bg-white text-[.855rem] text-[#3d2020] outline-none font-inherit transition-all duration-200 placeholder:text-[#d4adad] focus:border-[#e8a0a0] focus:shadow-[0_0_0_3.5px_rgba(220,130,130,.14)] focus:bg-[#fffbfb] peer">
                    </div>
                    <div class="relative flex items-center">
                        <span class="absolute left-[13px] text-[#d4a0a0] pointer-events-none flex transition-colors duration-200 peer-focus:text-[#c97878]">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </span>
                        <input type="password" id="regPassword" name="password" required autocomplete="new-password"
                            placeholder="Password (min 6 chars)" class="w-full py-3 pl-10 pr-[42px] rounded-[14px] border-[1.5px] border-[#f0d8d8] bg-white text-[.855rem] text-[#3d2020] outline-none font-inherit transition-all duration-200 placeholder:text-[#d4adad] focus:border-[#e8a0a0] focus:shadow-[0_0_0_3.5px_rgba(220,130,130,.14)] focus:bg-[#fffbfb] peer">
                        <button type="button" onclick="toggleModalPassword('regPassword',this)" class="absolute right-[13px] bg-transparent border-none cursor-pointer text-[#d4a0a0] p-0.5 flex transition-colors duration-200 hover:text-[#c97878]" tabindex="-1">
                            <svg width="17" height="17" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>
                    <div class="relative flex items-center">
                        <span class="absolute left-[13px] text-[#d4a0a0] pointer-events-none flex transition-colors duration-200 peer-focus:text-[#c97878]">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </span>
                        <input type="password" id="regConfirm" name="confirm_password" required
                            autocomplete="new-password" placeholder="Confirm password" class="w-full py-3 pl-10 pr-3.5 rounded-[14px] border-[1.5px] border-[#f0d8d8] bg-white text-[.855rem] text-[#3d2020] outline-none font-inherit transition-all duration-200 placeholder:text-[#d4adad] focus:border-[#e8a0a0] focus:shadow-[0_0_0_3.5px_rgba(220,130,130,.14)] focus:bg-[#fffbfb] peer">
                    </div>
                    <button type="submit" id="registerSubmitBtn" class="w-full py-3 px-5 rounded-[14px] bg-gradient-to-br from-[#e8918a] to-[#d97070] text-white text-[.9rem] font-semibold border-none cursor-pointer flex items-center justify-center gap-2 mt-1 font-inherit tracking-[.01em] transition-all duration-200 shadow-[0_4px_16px_rgba(210,100,100,.25)] hover:opacity-[.92] hover:-translate-y-px hover:shadow-[0_8px_22px_rgba(210,100,100,.3)] active:scale-[.98] disabled:opacity-[.65] disabled:cursor-not-allowed disabled:transform-none">
                        <span id="registerBtnText">Create Account</span>
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M5 13l4 4L19 7" />
                        </svg>
                    </button>
                </form>

                <p class="text-center text-[.8rem] text-[#b08080] mt-[18px]">
                    Already have an account?
                    <button onclick="switchTab('login')" class="bg-transparent border-none cursor-pointer font-bold text-[#d97070] text-inherit font-inherit p-0 ml-[3px] transition-colors duration-200 hover:text-[#b85555] hover:underline">Sign in</button>
                </p>
            </div>

        </div>
    </div>
</div>

<style>
@keyframes modalSlideIn {
    from { opacity: 0; transform: translateY(32px) scale(0.96); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
</style>

<script>
/* ── Pending Action State ──────────────────────────────────────────────
   Stores the cart/wishlist action a guest attempted so it can be
   executed automatically after they log in or register.
─────────────────────────────────────────────────────────────────── */
let _pendingAction = null;

function setPendingAction(action) {
    _pendingAction = action;
}

function clearPendingAction() {
    _pendingAction = null;
}

function executePendingAction() {
    if (!_pendingAction) return Promise.resolve();
    const action = _pendingAction;
    clearPendingAction();

    if (action.type === 'cart') {
        const qty = action.qty || 1;
        return fetch('/sweetheaven/api/cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=add&product_id=${action.productId}&qty=${qty}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (typeof showToast === 'function') showToast(`${action.productName} added to cart!`);
                const badge = document.getElementById('cartBadge');
                if (badge) { badge.textContent = data.cart_count; badge.classList.remove('hidden'); }
            }
        });

    } else if (action.type === 'wishlist') {
        return fetch('/sweetheaven/api/wishlist.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `product_id=${action.productId}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const btn = action.btn;
                if (btn && document.body.contains(btn)) {
                    const svg = btn.querySelector('svg');
                    btn.classList.toggle('bg-rose-500', data.is_wishlisted);
                    btn.classList.toggle('bg-white/90', !data.is_wishlisted);
                    btn.classList.toggle('text-white', data.is_wishlisted);
                    btn.classList.toggle('text-gray-400', !data.is_wishlisted);
                    if (svg) svg.setAttribute('fill', data.is_wishlisted ? 'currentColor' : 'none');
                }
                if (typeof showToast === 'function') {
                    showToast(data.is_wishlisted
                        ? '❤️ ' + (data.message || 'Added to wishlist')
                        : '💔 Removed from wishlist');
                }
                if (typeof updateWishlistBadge === 'function') updateWishlistBadge(data.wishlist_count);
            }
        });
    }

    return Promise.resolve();
}

/* ── Cart ──────────────────────────────────────────────────────────── */
function addToCart(productId, productName, qty) {
    qty = qty || 1;
    fetch('/sweetheaven/api/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=add&product_id=${productId}&qty=${qty}`
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (typeof showToast === 'function') showToast(`${productName} added to cart!`);
                const badge = document.getElementById('cartBadge');
                if (badge) { badge.textContent = data.cart_count; badge.classList.remove('hidden'); }
            } else if (data.redirect) {
                setPendingAction({ type: 'cart', productId, productName, qty });
                openAuthModal('login');
            }
        });
}

/* ── Wishlist ───────────────────────────────────────────────────────── */
function toggleWishlist(productId, btn) {
    fetch('/sweetheaven/api/wishlist.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `product_id=${productId}`
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const svg = btn.querySelector('svg');
                btn.classList.toggle('bg-rose-500', data.is_wishlisted);
                btn.classList.toggle('bg-white/90', !data.is_wishlisted);
                btn.classList.toggle('text-white', data.is_wishlisted);
                btn.classList.toggle('text-gray-400', !data.is_wishlisted);
                svg.setAttribute('fill', data.is_wishlisted ? 'currentColor' : 'none');
                if (typeof showToast === 'function') {
                    showToast(data.is_wishlisted ? '❤️ ' + (data.message || 'Added to wishlist') : '💔 Removed from wishlist');
                }
                if (typeof updateWishlistBadge === 'function') updateWishlistBadge(data.wishlist_count);
            } else if (data.redirect) {
                setPendingAction({ type: 'wishlist', productId, btn });
                openAuthModal('login');
            }
        });
}

/* ── Auth Modal ── */
function openAuthModal(tab = 'login') {
    switchTab(tab);
    document.getElementById('authModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    setTimeout(() => {
        const el = tab === 'login'
            ? document.getElementById('modalEmail')
            : document.getElementById('regName');
        el && el.focus();
    }, 100);
}

function closeAuthModal() {
    document.getElementById('authModal').classList.add('hidden');
    document.body.style.overflow = '';
    setLoginError('');
    setRegisterError('');
    document.getElementById('registerSuccess').classList.add('hidden');
    document.getElementById('modalLoginForm').reset();
    document.getElementById('modalRegisterForm').reset();
    clearPendingAction();
}

function switchTab(tab) {
    const isLogin = tab === 'login';
    document.getElementById('loginPanel').classList.toggle('hidden', !isLogin);
    document.getElementById('registerPanel').classList.toggle('hidden', isLogin);
    const title    = document.getElementById('authModalTitle');
    const subtitle = document.getElementById('authModalSubtitle');
    if (title)    title.textContent    = isLogin ? 'Welcome back'     : 'Create an account';
    if (subtitle) subtitle.textContent = isLogin
        ? 'Sign in to your Sweet Heaven account'
        : 'Join us and enjoy exclusive treats';
    setLoginError('');
    setRegisterError('');
    document.getElementById('registerSuccess').classList.add('hidden');
}

function setLoginError(msg) {
    const el = document.getElementById('loginError');
    document.getElementById('loginErrorMsg').textContent = msg || '';
    el.classList.toggle('hidden', !msg);
}

function setRegisterError(msg) {
    const el = document.getElementById('registerError');
    document.getElementById('registerErrorMsg').textContent = msg || '';
    el.classList.toggle('hidden', !msg);
}

function setBtnLoading(btnId, textId, loading, defaultText) {
    const btn = document.getElementById(btnId);
    const txt = document.getElementById(textId);
    btn.disabled = loading;
    btn.style.opacity = loading ? '0.7' : '1';
    txt.textContent = loading ? 'Please wait…' : defaultText;
}

function submitLogin(e) {
    e.preventDefault();
    setLoginError('');
    setBtnLoading('loginSubmitBtn', 'loginBtnText', true, 'Sign In');

    const body = new URLSearchParams({
        action: 'login',
        email: document.getElementById('modalEmail').value,
        password: document.getElementById('modalPassword').value,
    });

    fetch('/sweetheaven/api/auth_modal.php', { method: 'POST', body })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (_pendingAction) {
                    setBtnLoading('loginSubmitBtn', 'loginBtnText', false, 'Sign In');
                    executePendingAction().then(() => {
                        closeAuthModal();
                        if (data.redirect && data.redirect.includes('admin')) {
                            window.location.href = data.redirect;
                        } else {
                            window.location.reload();
                        }
                    });
                } else {
                    window.location.href = data.redirect;
                }
            } else {
                setLoginError(data.error);
                setBtnLoading('loginSubmitBtn', 'loginBtnText', false, 'Sign In');
            }
        })
        .catch(() => {
            setLoginError('Network error. Please try again.');
            setBtnLoading('loginSubmitBtn', 'loginBtnText', false, 'Sign In');
        });
}

function submitRegister(e) {
    e.preventDefault();
    setRegisterError('');
    document.getElementById('registerSuccess').classList.add('hidden');
    setBtnLoading('registerSubmitBtn', 'registerBtnText', true, 'Create Account');

    const body = new URLSearchParams({
        action: 'register',
        name: document.getElementById('regName').value,
        email: document.getElementById('regEmail').value,
        password: document.getElementById('regPassword').value,
        confirm_password: document.getElementById('regConfirm').value,
    });

    fetch('/sweetheaven/api/auth_modal.php', { method: 'POST', body })
        .then(r => r.json())
        .then(data => {
            setBtnLoading('registerSubmitBtn', 'registerBtnText', false, 'Create Account');
            if (data.success) {
                document.getElementById('modalRegisterForm').reset();
                const successEl = document.getElementById('registerSuccess');
                document.getElementById('registerSuccessMsg').textContent = data.message + ' You can now sign in.';
                successEl.classList.remove('hidden');
                setTimeout(() => switchTab('login'), 2000);
            } else {
                setRegisterError(data.error);
            }
        })
        .catch(() => {
            setRegisterError('Network error. Please try again.');
            setBtnLoading('registerSubmitBtn', 'registerBtnText', false, 'Create Account');
        });
}

function toggleModalPassword(inputId, btn) {
    const input = document.getElementById(inputId);
    input.type = input.type === 'password' ? 'text' : 'password';
}

// Close on Escape key
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeAuthModal();
});
</script>
