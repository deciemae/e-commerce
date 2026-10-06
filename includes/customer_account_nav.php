<?php
$customerAccountPage = $customerAccountPage ?? 'dashboard';
$customerAccountLinks = [
    'dashboard' => ['customer_dashboard.php', 'Overview'],
    'profile' => ['customer_profile.php', 'Profile'],
    'addresses' => ['customer_addresses.php', 'Addresses'],
    'orders' => ['customer_orders.php', 'Orders'],
];
?>
<nav class="account-navigation" aria-label="Customer account">
    <div class="account-navigation-links">
        <?php foreach ($customerAccountLinks as $key => [$href, $label]): ?>
            <a href="<?= $href ?>" class="<?= $customerAccountPage === $key ? 'active' : '' ?>" <?= $customerAccountPage === $key ? 'aria-current="page"' : '' ?>><?= $label ?></a>
        <?php endforeach; ?>
    </div>
</nav>
