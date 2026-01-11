<?php
/**
 * 404 Error Template
 * 
 * This template is displayed when a URL doesn't match any content or route.
 * Customize this page to help users find what they're looking for.
 * 
 * Tips for a good 404 page:
 *   - Keep it friendly and helpful
 *   - Provide a link back to the homepage
 *   - Consider adding a search form or popular links
 * 
 * Available variables:
 *   $request - The HTTP request object (contains the attempted URL)
 *   $ava     - Template helper
 *   $site    - Site configuration array
 * 
 * @see https://ava.addy.zone/docs/theming
 */
?>
<?= $ava->partial('header', ['request' => $request, 'pageTitle' => 'Page Not Found - ' . $site['name']]) ?>

        <div class="container">
            <div class="error-page">
                <h1>404</h1>
                <p>Sorry, the page you're looking for doesn't exist.</p>
                <p><a href="/" class="btn btn-primary">Return Home</a></p>
            </div>
        </div>

<?= $ava->partial('footer') ?>
