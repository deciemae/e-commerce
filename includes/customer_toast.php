<?php

function renderCustomerSuccessToast(string $message): void
{
    if (trim($message) === '') {
        return;
    }
    ?>
    <div class="auth-toast-region" aria-live="polite" aria-atomic="true">
        <div class="auth-toast auth-toast-success" role="status" data-customer-toast>
            <span class="auth-toast-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m5 12 4 4L19 6"></path>
                </svg>
            </span>
            <p><?= htmlspecialchars($message, ENT_QUOTES) ?></p>
            <button type="button" class="auth-toast-dismiss" aria-label="Dismiss notification" data-customer-toast-dismiss>
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    </div>
    <script>
        (function () {
            var toast = document.querySelector('[data-customer-toast]');
            var dismissButton = document.querySelector('[data-customer-toast-dismiss]');
            var dismissTimer = null;

            if (!toast || !dismissButton) {
                return;
            }

            function dismissToast() {
                window.clearTimeout(dismissTimer);
                toast.classList.add('is-hiding');
                window.setTimeout(function () {
                    toast.remove();
                }, 250);
            }

            function scheduleDismissal() {
                window.clearTimeout(dismissTimer);
                dismissTimer = window.setTimeout(dismissToast, 7000);
            }

            dismissButton.addEventListener('click', dismissToast);
            toast.addEventListener('mouseenter', function () {
                window.clearTimeout(dismissTimer);
            });
            toast.addEventListener('mouseleave', scheduleDismissal);
            toast.addEventListener('focusin', function () {
                window.clearTimeout(dismissTimer);
            });
            toast.addEventListener('focusout', scheduleDismissal);
            scheduleDismissal();
        }());
    </script>
    <?php
}
