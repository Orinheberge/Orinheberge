<?php
/**
 * OrinHeberge — Sélecteur de langue (partiel navbar)
 * Inclure là où se trouve le dropdown de langue, desktop ET mobile.
 * Nécessite que $lang soit déjà défini (via inc/lang.php).
 * 
 * v2 : Compatible mobile (toggle au clic) + desktop (hover)
 */

$_oh_langs = [
    'fr' => ['label' => 'FR', 'flag' => 'fr', 'name' => 'Français'],
    'en' => ['label' => 'EN', 'flag' => 'gb', 'name' => 'English'],
    'de' => ['label' => 'DE', 'flag' => 'de', 'name' => 'Deutsch'],
];
$_oh_current = $_oh_langs[$lang ?? 'fr'];
?>

<!-- 🌐 Sélecteur de langue -->
<div class="relative inline-block text-left lang-switcher" data-lang-switcher>
    <!-- Bouton principal -->
    <button type="button"
            class="lang-switcher-btn inline-flex items-center gap-2 bg-white/5 border border-white/10 hover:border-sky-500/50 rounded-xl px-4 py-2.5 md:py-2 text-sm font-semibold text-gray-200 transition focus:outline-none min-h-[44px] md:min-h-0"
            aria-haspopup="true" aria-expanded="false"
            data-lang-toggle>
        <img src="https://flagcdn.com/w40/<?php echo $_oh_current['flag']; ?>.png"
             alt="<?php echo htmlspecialchars($_oh_current['name']); ?>"
             class="w-6 h-auto md:w-5 rounded-sm object-contain">
        <span><?php echo $_oh_current['label']; ?></span>
        <i class="fas fa-chevron-down text-xs text-gray-400 group-hover:text-sky-400 transition duration-200"></i>
    </button>

    <!-- Dropdown -->
    <div class="lang-switcher-dropdown absolute right-0 mt-2 w-44 md:w-40 rounded-xl glass border border-white/10 shadow-xl
                opacity-0 invisible transition-all duration-200 z-50 overflow-hidden
                md:group-hover:opacity-100 md:group-hover:visible"
         data-lang-dropdown>
        <div class="py-1.5">
            <?php foreach ($_oh_langs as $_code => $_l): ?>
            <a href="?lang=<?php echo $_code; ?>"
               class="flex items-center gap-3 px-4 py-3 md:py-2 text-sm text-gray-300
                      hover:bg-sky-600/20 hover:text-white transition
                      <?php echo ($lang ?? 'fr') === $_code ? 'bg-sky-600/10 text-sky-400' : ''; ?>"
               data-lang-option>
                <img src="https://flagcdn.com/w40/<?php echo $_l['flag']; ?>.png"
                     alt="<?php echo htmlspecialchars($_l['name']); ?>"
                     class="w-6 h-auto md:w-5 rounded-sm">
                <span class="flex-1"><?php echo $_l['name']; ?></span>
                <?php if (($lang ?? 'fr') === $_code): ?>
                    <i class="fas fa-check text-sky-400 text-xs"></i>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- 📱 Script inline pour le toggle mobile -->
<script>
(function() {
    'use strict';

    // Initialiser tous les sélecteurs de langue
    const langSwitchers = document.querySelectorAll('[data-lang-switcher]');

    langSwitchers.forEach(function(switcher) {
        const btn = switcher.querySelector('[data-lang-toggle]');
        const dropdown = switcher.querySelector('[data-lang-dropdown]');
        const options = switcher.querySelectorAll('[data-lang-option]');

        if (!btn || !dropdown) return;

        let isOpen = false;

        // Toggle au clic (mobile + desktop)
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            isOpen = !isOpen;

            if (isOpen) {
                dropdown.classList.remove('opacity-0', 'invisible');
                dropdown.classList.add('opacity-100', 'visible');
                btn.setAttribute('aria-expanded', 'true');
            } else {
                dropdown.classList.add('opacity-0', 'invisible');
                dropdown.classList.remove('opacity-100', 'visible');
                btn.setAttribute('aria-expanded', 'false');
            }
        });

        // Fermer au clic sur une option
        options.forEach(function(option) {
            option.addEventListener('click', function() {
                isOpen = false;
                dropdown.classList.add('opacity-0', 'invisible');
                dropdown.classList.remove('opacity-100', 'visible');
                btn.setAttribute('aria-expanded', 'false');
            });
        });

        // Fermer au clic extérieur
        document.addEventListener('click', function(e) {
            if (isOpen && !switcher.contains(e.target)) {
                isOpen = false;
                dropdown.classList.add('opacity-0', 'invisible');
                dropdown.classList.remove('opacity-100', 'visible');
                btn.setAttribute('aria-expanded', 'false');
            }
        });

        // Fermer avec Échap
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && isOpen) {
                isOpen = false;
                dropdown.classList.add('opacity-0', 'invisible');
                dropdown.classList.remove('opacity-100', 'visible');
                btn.setAttribute('aria-expanded', 'false');
                btn.focus();
            }
        });
    });
})();
</script>