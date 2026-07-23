<?php
/**
 * OrinHeberge — Footer partagé amélioré
 * Nécessite que inc/lang.php soit déjà chargé.
 */
?>
<footer class="w-full bg-[#05070d] text-gray-400 border-t border-white/5 font-sans relative overflow-hidden mt-auto">
    
    <!-- Effet de gradient lumineux en haut -->
    <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-sky-500/50 to-transparent shadow-[0_0_15px_rgba(56,189,248,0.5)]"></div>
    
    <!-- Section principale -->
    <div class="max-w-7xl mx-auto px-6 py-12">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-8 mb-10">

            <!-- Colonne 1 : À propos & Marque -->
            <div class="lg:col-span-2 flex flex-col gap-4">
                <div class="flex items-center gap-3 group cursor-default">
                    <div class="w-10 h-10 rounded-xl bg-sky-500/10 border border-sky-500/20 flex items-center justify-center group-hover:bg-sky-500/20 transition duration-300">
                        <i class="fas fa-server text-sky-400"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-black text-white tracking-tight">Orin<span class="text-sky-500">Heberge</span></h3>
                        <p class="text-[10px] text-gray-500 font-semibold uppercase tracking-wider">Infrastructure OrinStone</p>
                    </div>
                </div>
                <p class="text-sm text-gray-500 leading-relaxed max-w-md">
                    Solution d'hébergement haute performance pour vos serveurs de jeu et applications web. 
                    Infrastructure française fiable, sécurisée et optimisée.
                </p>
                
                <!-- Réseaux sociaux -->
                <div class="flex items-center gap-3 mt-2">
                    <a href="/discord/" target="_blank" class="w-9 h-9 rounded-lg bg-[#5865F2]/10 border border-[#5865F2]/20 flex items-center justify-center text-[#5865F2] hover:bg-[#5865F2] hover:text-white hover:border-[#5865F2] transition-all duration-300" title="Rejoindre le Discord">
                        <i class="fab fa-discord"></i>
                    </a>
                    <a href="https://x.com/orinheberge" target="_blank" class="w-9 h-9 rounded-lg bg-black/20 border border-white/10 flex items-center justify-center text-white hover:bg-white hover:text-black hover:border-white transition-all duration-300" title="Suivre sur X">
                        <i class="fab fa-x-twitter"></i>
                    </a>
                    <a href="https://www.tiktok.com/@orinheberge5" target="_blank" class="w-9 h-9 rounded-lg bg-black/20 border border-white/10 flex items-center justify-center text-white hover:bg-[#fe2c55] hover:border-[#fe2c55] transition-all duration-300" title="TikTok">
                        <i class="fab fa-tiktok"></i>
                    </a>
                    <a href="https://github.com/orinheberge" target="_blank" class="w-9 h-9 rounded-lg bg-white/5 border border-white/10 flex items-center justify-center text-gray-400 hover:bg-white hover:text-black hover:border-white transition-all duration-300" title="GitHub">
                        <i class="fab fa-github"></i>
                    </a>
                    <a href="mailto:deepstone@deepstone.fr" class="w-9 h-9 rounded-lg bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-400 hover:bg-rose-500 hover:text-white hover:border-rose-500 transition-all duration-300" title="Nous contacter">
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
                    <a href="/" class="hover:text-sky-400 transition flex items-center gap-3 group">
                        <i class="fas fa-home w-4 text-center text-gray-600 group-hover:text-sky-400 transition"></i>
                        <?php echo t('nav.home'); ?>
                    </a>
                    <a href="/client/servers/" class="hover:text-sky-400 transition flex items-center gap-3 group">
                        <i class="fas fa-server w-4 text-center text-gray-600 group-hover:text-sky-400 transition"></i>
                        <?php echo t('nav.servers'); ?>
                    </a>
                    <a href="/shop/" class="hover:text-sky-400 transition flex items-center gap-3 group">
                        <i class="fas fa-tags w-4 text-center text-gray-600 group-hover:text-sky-400 transition"></i>
                        <?php echo t('nav.offers'); ?>
                    </a>
                    <a href="/shop/cart/" class="hover:text-sky-400 transition flex items-center gap-3 group">
                        <i class="fas fa-shopping-cart w-4 text-center text-gray-600 group-hover:text-sky-400 transition"></i>
                        Mon panier
                    </a>
                    <a href="/support/" class="hover:text-sky-400 transition flex items-center gap-3 group">
                        <i class="fas fa-headset w-4 text-center text-gray-600 group-hover:text-sky-400 transition"></i>
                        <?php echo t('nav.support'); ?>
                    </a>
                </div>
            </div>

            <!-- Colonne 3 : Ressources & Liens Externes -->
            <div class="flex flex-col gap-4">
                <h3 class="text-white font-bold text-sm tracking-wide flex items-center gap-2">
                    <i class="fas fa-link text-sky-400 text-xs"></i>
                    <?php echo t('footer.network'); ?>
                </h3>
                <div class="flex flex-col gap-2.5 text-sm">
                    <a href="/discord/" target="_blank" class="hover:text-sky-400 transition flex items-center gap-3 group">
                        <i class="fab fa-discord w-4 text-center text-[#5865F2]"></i>
                        <?php echo t('footer.discord'); ?>
                    </a>
                    <a href="/status/" class="hover:text-sky-400 transition flex items-center gap-3 group">
                        <i class="fas fa-signal w-4 text-center text-emerald-400"></i>
                        <?php echo t('footer.status'); ?>
                    </a>
                    <a href="https://panel.orinstone.deepstone.fr" target="_blank" class="hover:text-sky-400 transition flex items-center gap-3 group">
                        <i class="fas fa-cogs w-4 text-center text-gray-600 group-hover:text-white transition"></i>
                        <?php echo t('nav.panel'); ?>
                        <i class="fas fa-external-link-alt text-[8px] text-gray-600 ml-auto opacity-50 group-hover:opacity-100 transition"></i>
                    </a>
                    <a href="https://php.orinstone.deepstone.fr" target="_blank" class="hover:text-sky-400 transition flex items-center gap-3 group">
                        <i class="fas fa-database w-4 text-center text-gray-600 group-hover:text-white transition"></i>
                        <?php echo t('nav.phpmyadmin'); ?>
                        <i class="fas fa-external-link-alt text-[8px] text-gray-600 ml-auto opacity-50 group-hover:opacity-100 transition"></i>
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
                    <a href="/mentions-legales/" class="hover:text-sky-400 transition flex items-center gap-3 group">
                        <i class="fas fa-file-contract w-4 text-center text-gray-600 group-hover:text-white transition"></i>
                        Mentions légales
                    </a>
                    <a href="/cgu/" class="hover:text-sky-400 transition flex items-center gap-3 group">
                        <i class="fas fa-file-alt w-4 text-center text-gray-600 group-hover:text-white transition"></i>
                        Conditions d'utilisation
                    </a>
                    <a href="/politique-confidentialite/" class="hover:text-sky-400 transition flex items-center gap-3 group">
                        <i class="fas fa-shield-alt w-4 text-center text-gray-600 group-hover:text-white transition"></i>
                        Confidentialité
                    </a>
                    <a href="/cookies/" class="hover:text-sky-400 transition flex items-center gap-3 group">
                        <i class="fas fa-cookie-bite w-4 text-center text-gray-600 group-hover:text-white transition"></i>
                        Politique cookies
                    </a>
                </div>
            </div>

        </div>

        <!-- Section paiements & Badges -->
        <div class="border-t border-white/5 pt-8 mt-8">
            <div class="flex flex-col xl:flex-row items-center justify-between gap-8">
                
                <!-- Moyens de paiement -->
                <div class="flex flex-col items-center xl:items-start gap-3 w-full xl:w-auto">
                    <span class="text-xs text-gray-500 font-semibold tracking-wider uppercase">
                        Paiements sécurisés
                    </span>
                    <div class="flex flex-wrap items-center justify-center xl:justify-start gap-3">
                        <!-- Fonction helper pour éviter la répétition de code HTML -->
                        <?php
                        $payment_methods = [
                            ['src' => 'https://azurhosts.com/assets/images/logos/psrl/card-icons/card_cb.svg', 'alt' => 'CB'],
                            ['src' => 'https://azurhosts.com/assets/images/logos/psrl/card-icons/card_visa.svg', 'alt' => 'Visa'],
                            ['src' => 'https://azurhosts.com/assets/images/logos/psrl/card-icons/card_mastercard.svg', 'alt' => 'Mastercard'],
                            ['src' => 'https://azurhosts.com/assets/images/logos/psrl/card-icons/card_paypal.svg', 'alt' => 'PayPal'],
                            ['src' => 'https://heberge.orinstone.deepstone.fr/img/moyen_pay/google_pay.png', 'alt' => 'Google Pay'],
                            ['src' => 'https://heberge.orinstone.deepstone.fr/img/moyen_pay/apple_pay.png', 'alt' => 'Apple Pay'],
                            ['src' => 'https://heberge.orinstone.deepstone.fr/img/moyen_pay/revolut_pay.png', 'alt' => 'Revolut']
                        ];
                        foreach($payment_methods as $pm): ?>
                            <div class="bg-white/[0.03] border border-white/5 rounded-lg p-2 h-10 flex items-center justify-center hover:bg-white/[0.08] hover:border-white/10 transition duration-300 grayscale hover:grayscale-0">
                                <img src="<?php echo $pm['src']; ?>" alt="<?php echo $pm['alt']; ?>" class="h-full object-contain" />
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Badges de confiance -->
                <div class="flex items-center gap-6">
                    <div class="flex flex-col items-center gap-1 group cursor-default">
                        <div class="w-10 h-10 rounded-full bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center group-hover:scale-110 transition duration-300">
                            <i class="fas fa-shield-check text-emerald-400"></i>
                        </div>
                        <span class="text-[10px] text-gray-500 font-semibold">SSL Sécurisé</span>
                    </div>
                    <div class="flex flex-col items-center gap-1 group cursor-default">
                        <div class="w-10 h-10 rounded-full bg-sky-500/10 border border-sky-500/20 flex items-center justify-center group-hover:scale-110 transition duration-300">
                            <i class="fas fa-bolt text-sky-400"></i>
                        </div>
                        <span class="text-[10px] text-gray-500 font-semibold">99.9% Uptime</span>
                    </div>
                    <div class="flex flex-col items-center gap-1 group cursor-default">
                        <div class="w-10 h-10 rounded-full bg-purple-500/10 border border-purple-500/20 flex items-center justify-center group-hover:scale-110 transition duration-300">
                            <i class="fas fa-headset text-purple-400"></i>
                        </div>
                        <span class="text-[10px] text-gray-500 font-semibold">Support 24/7</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Barre de copyright -->
    <div class="border-t border-white/5 bg-black/40 backdrop-blur-sm">
        <div class="max-w-7xl mx-auto px-6 py-4">
            <div class="flex flex-col md:flex-row items-center justify-between gap-4 text-xs text-gray-500">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-bold text-white">Orin<span class="text-sky-500">Heberge</span></span>
                    <span class="text-gray-600">·</span>
                    <span>&copy; <?php echo date('Y'); ?> <?php echo t('footer.copyright'); ?></span>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-gray-600 hidden sm:inline">Propulsé par</span>
                    <a href="https://porttfolio.deepstone.fr" target="_blank" class="text-sky-500/70 hover:text-sky-400 font-semibold transition flex items-center gap-1">
                        Orinstone Studio <i class="fas fa-external-link-alt text-[8px]"></i>
                    </a>
                    <span class="text-gray-600">·</span>
                    <span class="font-mono text-gray-600 bg-white/5 px-2 py-0.5 rounded">v2.0.0</span>
                </div>
            </div>
        </div>
    </div>
</footer>