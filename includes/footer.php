<?php
/**
 * B L E N D — Footer Global
 */

declare(strict_types=1);
?>
</main>

<footer class="site-footer">
    <div class="site-footer__inner container">
        <div class="site-footer__brand">
            <span class="site-footer__name">B L E N D</span>
            <p class="site-footer__tagline">
                Brewed with intention. Served with character.
            </p>
        </div>

        <div class="site-footer__meta">
            <span class="site-footer__line">
                <i data-lucide="map-pin" aria-hidden="true"></i>
                Coffee Shop Internal System
            </span>
            <span class="site-footer__line">
                <i data-lucide="calendar-clock" aria-hidden="true"></i>
                <?= date('Y') ?> · Admin Dashboard
            </span>
        </div>
    </div>

    <div class="site-footer__bottom container">
        <span>&copy; <?= date('Y') ?> B L E N D Coffee Shop. All rights reserved.</span>
        <span>PHP 8.3 Native · MariaDB</span>
    </div>
</footer>

<script src="https://unpkg.com/lucide@latest"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
