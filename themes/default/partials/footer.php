<?php
/**
 * Footer Partial
 * 
 * This partial closes the main content area and adds the site footer.
 * Include it at the end of every template with $ava->partial('footer').
 * 
 * @see https://ava.addy.zone/docs/theming
 */
?>
    </main>

    <footer class="site-footer">
        <div class="container">
            <div class="footer-content">
                <?php
                /**
                 * Dynamic Year
                 * 
                 * Using PHP's date() function ensures the copyright year
                 * is always current without manual updates.
                 */
                ?>
                <p>&copy; <?= date('Y') ?> <?= $ava->e($site['name']) ?>. Built with <a href="https://github.com/ava-cms/ava">Ava CMS</a>.</p>
            </div>
        </div>
    </footer>

    <?php
    /**
     * JavaScript
     * 
     * Inline JavaScript for the mobile navigation toggle. For larger
     * scripts, create a .js file in assets/ and link it with:
     * <script src="<?= $ava->asset('script.js') ?>"></script>
     */
    ?>
    <script>
    // Mobile nav toggle
    document.querySelector('.nav-toggle')?.addEventListener('click', function() {
        document.querySelector('.site-nav').classList.toggle('open');
        this.classList.toggle('open');
    });
    </script>
</body>
</html>
