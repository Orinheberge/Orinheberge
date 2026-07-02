<?php
/**
 * OrinHeberge — Sélecteur de langue (partiel navbar)
 * Inclure là où se trouve le dropdown de langue, desktop ET mobile.
 * Nécessite que $lang soit déjà défini (via inc/lang.php).
 */

$_oh_langs = [
    'fr' => ['label' => 'FR', 'flag' => 'fr', 'name' => 'Français'],
    'en' => ['label' => 'EN', 'flag' => 'gb', 'name' => 'English'],
];
$_oh_current = $_oh_langs[$lang ?? 'fr'];
?>
<div class="relative inline-block text-left group">
    <button type="button"
            class="inline-flex items-center gap-2 bg-white/5 border border-white/10 hover:border-sky-500/50 rounded-xl px-4 py-2.5 text-sm font-semibold text-gray-200 transition focus:outline-none"
            aria-haspopup="true" aria-expanded="false">
        <img src="https://flagcdn.com/w20/<?php echo $_oh_current['flag']; ?>.png"
             alt="<?php echo htmlspecialchars($_oh_current['name']); ?>"
             class="w-5 h-auto rounded-sm object-contain">
        <span><?php echo $_oh_current['label']; ?></span>
        <i class="fas fa-chevron-down text-xs text-gray-400 group-hover:text-sky-400 transition duration-200"></i>
    </button>

    <div class="absolute right-0 mt-2 w-40 rounded-xl glass border border-white/10 shadow-xl
                opacity-0 invisible group-hover:opacity-100 group-hover:visible
                transition-all duration-200 z-50 overflow-hidden">
        <div class="py-1">
            <?php foreach ($_oh_langs as $_code => $_l): ?>
            <a href="?lang=<?php echo $_code; ?>"
               class="flex items-center gap-3 px-4 py-2 text-sm text-gray-300
                      hover:bg-sky-600/20 hover:text-white transition
                      <?php echo ($lang ?? 'fr') === $_code ? 'bg-sky-600/10 text-sky-400' : ''; ?>">
                <img src="https://flagcdn.com/w20/<?php echo $_l['flag']; ?>.png"
                     alt="<?php echo htmlspecialchars($_l['name']); ?>"
                     class="w-5 h-auto rounded-sm">
                <span><?php echo $_l['name']; ?></span>
                <?php if (($lang ?? 'fr') === $_code): ?>
                    <i class="fas fa-check text-sky-400 ml-auto text-xs"></i>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
