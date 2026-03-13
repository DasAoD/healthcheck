<?php
/**
 * templates/admin_nav.php
 * Admin sub-navigation tabs.
 *
 * Expected variable:
 *   $adminPage  string  – active page key: 'checks' | 'sites' | 'users' | 'settings'
 */

declare(strict_types=1);

$adminPage = $adminPage ?? '';

$tabs = [
    'checks'   => ['label' => 'Checks',       'href' => '/admin/checks.php'],
    'sites'    => ['label' => 'Standorte',     'href' => '/admin/sites.php'],
    'users'    => ['label' => 'Benutzer',      'href' => '/admin/users.php'],
    'settings' => ['label' => 'Einstellungen', 'href' => '/admin/settings.php'],
];
?>
<nav style="background:var(--color-surface);border-bottom:1px solid var(--color-border);
            padding:0 24px;display:flex;gap:0;margin-bottom:28px;">
    <?php foreach ($tabs as $key => $tab): ?>
        <?php $active = ($adminPage === $key); ?>
        <a href="<?= htmlspecialchars($tab['href']) ?>"
           style="display:inline-block;padding:12px 16px;font-size:13px;font-weight:<?= $active ? '600' : '400' ?>;
                  text-decoration:none;color:<?= $active ? 'var(--color-primary)' : 'var(--color-muted)' ?>;
                  border-bottom:2px solid <?= $active ? 'var(--color-primary)' : 'transparent' ?>;
                  transition:color .15s;">
            <?= htmlspecialchars($tab['label']) ?>
        </a>
    <?php endforeach ?>
</nav>
