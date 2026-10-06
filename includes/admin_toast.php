<?php

require_once __DIR__ . '/admin_security.php';

function renderAdminToast(?array $flash): void
{
    if (!$flash || empty($flash['message'])) {
        return;
    }

    $type = in_array($flash['type'] ?? '', ['success', 'error', 'info'], true)
        ? $flash['type']
        : 'info';
    $role = $type === 'error' ? 'alert' : 'status';
    ?>
    <div class="admin-toast admin-toast--<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>"
         role="<?= $role ?>" aria-live="<?= $type === 'error' ? 'assertive' : 'polite' ?>" aria-atomic="true" data-admin-toast>
        <span class="admin-toast-indicator" aria-hidden="true"></span>
        <p><?= htmlspecialchars((string)$flash['message'], ENT_QUOTES, 'UTF-8') ?></p>
        <button type="button" class="admin-toast-close" aria-label="Dismiss notification" data-admin-toast-close>&times;</button>
    </div>
    <script>
    (function () {
        var toast = document.querySelector('[data-admin-toast]');
        if (!toast) return;
        var closeButton = toast.querySelector('[data-admin-toast-close]');
        var dismiss = function () {
            toast.classList.add('is-leaving');
            window.setTimeout(function () { toast.remove(); }, 180);
        };
        closeButton.addEventListener('click', dismiss);
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && document.body.contains(toast)) dismiss();
        });
        <?php if ($type !== 'error'): ?>
        window.setTimeout(dismiss, 5500);
        <?php endif; ?>
    })();
    </script>
    <?php
}
