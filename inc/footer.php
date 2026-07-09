<?php
/**
 * OrinHeberge — Footer partagé amélioré
 * Nécessite que inc/lang.php soit déjà chargé.
 */
?>
<footer class="w-full bg-[#05070d] text-gray-400 border-t border-white/5 font-sans relative overflow-hidden">
    
    <!-- Effet de gradient en haut -->
    <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-sky-500/50 to-transparent"></div>
    
    <!-- Section principale -->
    <div class="max-w-7xl mx-auto px-6 py-12">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-8 mb-10">

            <!-- Colonne 1 : À propos -->
            <div class="lg:col-span-2 flex flex-col gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-sky-500/10 border border-sky-500/20 flex items-center justify-center">
                        <i class="fas fa-server text-sky-400"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-black text-white tracking-tight">Orin<span class="text-sky-500">Heberge</span></h3>
                        <p class="text-[10px] text-gray-500 font-semibold uppercase tracking-wider">Infrastructure OrinStone</p>
                    </div>
                </div>
                <p class="text-sm text-gray-500 leading-relaxed">
                    Solution d'hébergement haute performance pour vos serveurs de jeu et applications. 
                    Infrastructure française fiable et sécurisée.
                </p>
                
                <!-- Réseaux sociaux -->
              <div class="flex items-center gap-2 mt-2">
                    <a href="/discord/" target="_blank" class="w-9 h-9 rounded-lg bg-[#5865F2]/10 border border-[#5865F2]/20 flex items-center justify-center text-[#5865F2] hover:bg-[#5865F2]/20 transition" title="Discord">
                        <i class="fab fa-discord"></i>
                    </a>
                    <a href="https://x.com/orinheberge" target="_blank" class="w-9 h-9 rounded-lg bg-black/10 border border-white/10 flex items-center justify-center text-white hover:bg-white/10 transition" title="X (Twitter)">
                      <i class="fab fa-twitter" style="font-size: 40px; color: black;"></i>
                    </a>
                    <a href="https://www.tiktok.com/@orinheberge5" target="_blank" class="w-9 h-9 rounded-lg bg-black/10 border border-white/10 flex items-center justify-center text-white hover:bg-white/10 transition" title="TikTok">
                        <i class="fab fa-tiktok"></i>
                    </a>
                    <a href="https://github.com/orinheberge" target="_blank" class="w-9 h-9 rounded-lg bg-white/5 border border-white/10 flex items-center justify-center text-gray-400 hover:bg-white/10 transition" title="GitHub">
                        <i class="fab fa-github"></i>
                    </a>
                    <a href="mailto:deepstone@deepstone.fr" class="w-9 h-9 rounded-lg bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-400 hover:bg-rose-500/20 transition" title="Email">
                        <i class="fas fa-envelope"></i>
                    </a>
</div>
            </div>

            <!-- Colonne 2 : Navigation -->
            <div class="flex flex-col gap-4">
                <h3 class="text-white font-bold text-sm tracking-wide flex items-center gap-2">
                    <i class="fas fa-compass text-sky-400 text-xs"></i>
                    <?php echo t('footer.nav'); ?>
                </h3>
                <div class="flex flex-col gap-2.5 text-sm">
                    <a href="/" class="hover:text-sky-400 transition flex items-center gap-2">
                        <i class="fas fa-home text-[10px] text-gray-600"></i>
                        <?php echo t('nav.home'); ?>
                    </a>
                    <a href="/client/servers/" class="hover:text-sky-400 transition flex items-center gap-2">
                        <i class="fas fa-server text-[10px] text-gray-600"></i>
                        <?php echo t('nav.servers'); ?>
                    </a>
                    <a href="/shop/" class="hover:text-sky-400 transition flex items-center gap-2">
                        <i class="fas fa-tags text-[10px] text-gray-600"></i>
                        <?php echo t('nav.offers'); ?>
                    </a>
                    <a href="/shop/cart/" class="hover:text-sky-400 transition flex items-center gap-2">
                        <i class="fas fa-shopping-cart text-[10px] text-gray-600"></i>
                        Mon panier
                    </a>
                    <a href="/support/" class="hover:text-sky-400 transition flex items-center gap-2">
                        <i class="fas fa-headset text-[10px] text-gray-600"></i>
                        <?php echo t('nav.support'); ?>
                    </a>
                </div>
            </div>

            <!-- Colonne 3 : Ressources -->
            <div class="flex flex-col gap-4">
                <h3 class="text-white font-bold text-sm tracking-wide flex items-center gap-2">
                    <i class="fas fa-link text-sky-400 text-xs"></i>
                    <?php echo t('footer.network'); ?>
                </h3>
                <div class="flex flex-col gap-2.5 text-sm">
                    <a href="/discord/" target="_blank" class="hover:text-sky-400 transition flex items-center gap-2">
                        <i class="fab fa-discord text-[10px] text-[#5865F2]"></i>
                        <?php echo t('footer.discord'); ?>
                    </a>
                    <a href="/status/" class="hover:text-sky-400 transition flex items-center gap-2">
                        <i class="fas fa-signal text-[10px] text-emerald-400"></i>
                        <?php echo t('footer.status'); ?>
                    </a>
                    <a href="https://panel.orinstone.deepstone.fr" target="_blank" class="hover:text-sky-400 transition flex items-center gap-2">
                        <i class="fas fa-cogs text-[10px] text-gray-600"></i>
                        <?php echo t('nav.panel'); ?>
                        <i class="fas fa-external-link-alt text-[8px] text-gray-600 ml-auto"></i>
                    </a>
                    <a href="https://php.orinstone.deepstone.fr" target="_blank" class="hover:text-sky-400 transition flex items-center gap-2">
                        <i class="fas fa-database text-[10px] text-gray-600"></i>
                        <?php echo t('nav.phpmyadmin'); ?>
                        <i class="fas fa-external-link-alt text-[8px] text-gray-600 ml-auto"></i>
                    </a>
                </div>
            </div>

            <!-- Colonne 4 : Légal -->
            <div class="flex flex-col gap-4">
                <h3 class="text-white font-bold text-sm tracking-wide flex items-center gap-2">
                    <i class="fas fa-gavel text-sky-400 text-xs"></i>
                    <?php echo t('footer.legal'); ?>
                </h3>
                <div class="flex flex-col gap-2.5 text-sm">
                    <a href="/mentions-legales/" class="hover:text-sky-400 transition flex items-center gap-2">
                        <i class="fas fa-file-contract text-[10px] text-gray-600"></i>
                        Mentions légales
                    </a>
                    <a href="/cgu/" class="hover:text-sky-400 transition flex items-center gap-2">
                        <i class="fas fa-file-alt text-[10px] text-gray-600"></i>
                        Conditions d'utilisation
                    </a>
                    <a href="/politique-confidentialite/" class="hover:text-sky-400 transition flex items-center gap-2">
                        <i class="fas fa-shield-alt text-[10px] text-gray-600"></i>
                        Confidentialité
                    </a>
                    <a href="/cookies/" class="hover:text-sky-400 transition flex items-center gap-2">
                        <i class="fas fa-cookie-bite text-[10px] text-gray-600"></i>
                        Politique cookies
                    </a>
                </div>
            </div>

        </div>

        <!-- Section paiements -->
        <div class="border-t border-white/5 pt-8 mt-8">
            <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                
                <!-- Moyens de paiement -->
                <div class="flex flex-col items-center md:items-start gap-3">
                    <span class="text-xs text-gray-500 font-semibold tracking-wider uppercase">
                        <?php echo t('footer.payments'); ?>
                    </span>
                    <div class="flex flex-wrap items-center justify-center gap-2">
                        <div class="bg-white/[0.03] border border-white/5 rounded-lg p-2 hover:bg-white/[0.05] transition">
                            <img src="https://azurhosts.com/assets/images/logos/psrl/card-icons/card_cb.svg" alt="CB" class="h-6 object-contain" />
                        </div>
                        <div class="bg-white/[0.03] border border-white/5 rounded-lg p-2 hover:bg-white/[0.05] transition">
                            <img src="https://azurhosts.com/assets/images/logos/psrl/card-icons/card_visa.svg" alt="Visa" class="h-6 object-contain" />
                        </div>
                        <div class="bg-white/[0.03] border border-white/5 rounded-lg p-2 hover:bg-white/[0.05] transition">
                            <img src="https://azurhosts.com/assets/images/logos/psrl/card-icons/card_mastercard.svg" alt="Mastercard" class="h-6 object-contain" />
                        </div>
                        <div class="bg-white/[0.03] border border-white/5 rounded-lg p-2 hover:bg-white/[0.05] transition">
                            <img src="https://azurhosts.com/assets/images/logos/psrl/card-icons/card_paypal.svg" alt="PayPal" class="h-6 object-contain" />
                        </div>
                        <div class="bg-white/[0.03] border border-white/5 rounded-lg p-2 hover:bg-white/[0.05] transition">
                            <img src="https://heberge.orinstone.deepstone.fr/img/moyen_pay/google_pay.png" alt="Google Pay" class="h-6 object-contain" />
                        </div>
                        <div class="bg-white/[0.03] border border-white/5 rounded-lg p-2 hover:bg-white/[0.05] transition">
                            <img src="https://heberge.orinstone.deepstone.fr/img/moyen_pay/apple_pay.png" alt="Apple Pay" class="h-6 object-contain" />
                        </div>
                        <div class="bg-white/[0.03] border border-white/5 rounded-lg p-2 hover:bg-white/[0.05] transition">
                            <img src="https://heberge.orinstone.deepstone.fr/img/moyen_pay/revolut_pay.png" alt="Revolut Pay" class="h-6 object-contain" />
                        </div>
                    </div>
                </div>

                <!-- Badges de confiance -->
                <div class="flex items-center gap-4">
                    <div class="flex flex-col items-center gap-1">
                        <div class="w-12 h-12 rounded-full bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center">
                            <i class="fas fa-shield-check text-emerald-400 text-lg"></i>
                        </div>
                        <span class="text-[10px] text-gray-500 font-semibold">Sécurisé</span>
                    </div>
                    <div class="flex flex-col items-center gap-1">
                        <div class="w-12 h-12 rounded-full bg-sky-500/10 border border-sky-500/20 flex items-center justify-center">
                            <i class="fas fa-bolt text-sky-400 text-lg"></i>
                        </div>
                        <span class="text-[10px] text-gray-500 font-semibold">99.9% Uptime</span>
                    </div>
                    <div class="flex flex-col items-center gap-1">
                        <div class="w-12 h-12 rounded-full bg-purple-500/10 border border-purple-500/20 flex items-center justify-center">
                            <i class="fas fa-headset text-purple-400 text-lg"></i>
                        </div>
                        <span class="text-[10px] text-gray-500 font-semibold">Support 24/7</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Barre de copyright -->
    <div class="border-t border-white/5 bg-black/20">
        <div class="max-w-7xl mx-auto px-6 py-4">
            <div class="flex flex-col md:flex-row items-center justify-between gap-4 text-xs text-gray-500">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-bold text-white">Orin<span class="text-sky-500">Heberge</span></span>
                    <span class="text-gray-600">·</span>
                    <span><?php echo t('footer.copyright'); ?></span>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-gray-600">Propulsé par</span>
                    <a href="https://porttfolio.deepstone.fr" target="_blank" class="text-sky-500/70 hover:text-sky-400 font-semibold transition">
                        Orinstone Studio
                    </a>
                    <span class="text-gray-600">·</span>
                    <span class="text-gray-600">v2.0.0</span>
                </div>
            </div>
        </div>
    </div>
</footer>