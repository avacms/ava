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
                <p>&copy; <?= date('Y') ?> <?= $ava->e($site['name']) ?>. Built with <a href="https://github.com/avacms/ava">Ava CMS</a>.</p>
            </div>
        </div>
    </footer>

    <script src="<?= $ava->asset('script.js') ?>" defer></script>
</body>
</html>
