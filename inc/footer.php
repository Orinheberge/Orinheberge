<?php
/**
 * OrinHeberge — Footer partagé
 * Nécessite que inc/lang.php soit déjà chargé.
 */
?>
<footer class="w-full bg-[#05070d] text-gray-400 py-12 px-6 border-t border-white/5 font-sans">
    <div class="max-w-7xl mx-auto">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-10">

            <div class="flex flex-col gap-4">
                <h3 class="text-white font-bold text-base tracking-wide"><?php echo t('footer.nav'); ?></h3>
                <div class="flex flex-col gap-2.5 text-sm">
                    <a href="/" class="hover:text-sky-400 transition"><?php echo t('nav.home'); ?></a>
                    <a href="/client/servers/" class="hover:text-sky-400 transition"><?php echo t('nav.servers'); ?></a>
                    <a href="/shop/" class="hover:text-sky-400 transition"><?php echo t('nav.offers'); ?></a>
                    <a href="/support/" class="hover:text-sky-400 transition"><?php echo t('nav.support'); ?></a>
                </div>
            </div>

            <div class="flex flex-col gap-4">
                <h3 class="text-white font-bold text-base tracking-wide"><?php echo t('footer.network'); ?></h3>
                <div class="flex flex-col gap-2.5 text-sm">
                    <a href="/discord/" class="hover:text-sky-400 transition"><?php echo t('footer.discord'); ?></a>
                    <a href="https://status.deepstone.fr/" class="hover:text-sky-400 transition"><?php echo t('footer.status'); ?></a>
                </div>
            </div>

            <div class="flex flex-col gap-4">
                <h3 class="text-white font-bold text-base tracking-wide"><?php echo t('footer.links'); ?></h3>
                <div class="flex flex-col gap-2.5 text-sm">
                    <a href="https://php.orinstone.deepstone.fr" class="hover:text-sky-400 transition"><?php echo t('nav.phpmyadmin'); ?></a>
                    <a href="https://panel.orinstone.deepstone.fr" class="hover:text-sky-400 transition"><?php echo t('nav.panel'); ?></a>
                </div>
            </div>

            <div class="flex flex-col justify-end gap-3 items-start md:items-end">
                <span class="text-xs text-gray-400 font-semibold tracking-wider uppercase"><?php echo t('footer.payments'); ?></span>
                <div class="flex items-center gap-3 bg-white/[0.02] border border-white/5 p-3 rounded-xl">
                    <img src="https://azurhosts.com/assets/images/logos/psrl/card-icons/card_cb.svg"         alt="CB"         class="h-8 object-contain" />
                    <img src="https://azurhosts.com/assets/images/logos/psrl/card-icons/card_visa.svg"       alt="Visa"       class="h-8 object-contain" />
                    <img src="https://azurhosts.com/assets/images/logos/psrl/card-icons/card_mastercard.svg" alt="Mastercard" class="h-8 object-contain" />
                    <img src="https://azurhosts.com/assets/images/logos/psrl/card-icons/card_paypal.svg"     alt="PayPal"     class="h-8 object-contain" />
                    <img src="https://heberge.orinstone.deepstone.fr/img/moyen_pay/google_pay.png"       alt="Google Pay"       class="h-8 object-contain" />
                    <img src="https://heberge.orinstone.deepstone.fr/img/moyen_pay/revolut_pay.png" alt="Revolut Pay" class="h-8 object-contain" />
                    <img src="https://heberge.orinstone.deepstone.fr/img/moyen_pay/apple_pay.png"     alt="Apple Pay"     class="h-8 object-contain" />
                </div>
            </div>
        </div>

        <hr class="border-white/10 mb-8">

        <div class="flex flex-col md:flex-row items-start justify-between gap-6 text-xs text-gray-500">
            <div class="flex items-center gap-2">
                <span class="text-2xl font-black tracking-tighter text-white">Orin<span class="text-sky-500">Heberge</span></span>
            </div>
            <div class="flex flex-col gap-2 md:text-left">
                <div class="flex flex-wrap gap-x-4 gap-y-1 text-gray-400 font-medium">
                    <a href="/mentions-legales/"         class="hover:text-sky-400 transition"><?php echo t('footer.legal'); ?></a>
                    <span class="text-white/10">|</span>
                    <a href="/cgu/"                      class="hover:text-sky-400 transition"><?php echo t('footer.cgu'); ?></a>
                    <span class="text-white/10">|</span>
                    <a href="/politique-confidentialite/" class="hover:text-sky-400 transition"><?php echo t('footer.privacy'); ?></a>
                </div>
                <div class="flex flex-col gap-0.5">
                    <div><?php echo t('footer.copyright'); ?></div>
                    <div class="text-[10px] text-gray-600 mt-1">
                        <?php echo t('footer.powered'); ?> <span class="text-sky-500/70 font-medium hover:text-sky-400 transition">Orinstone Studio</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>
