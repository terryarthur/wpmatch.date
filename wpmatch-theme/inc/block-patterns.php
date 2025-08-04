<?php
/**
 * Block patterns for WPMatch Dating Theme
 *
 * @package WPMatch_Theme
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register block patterns
 */
function wpmatch_theme_register_patterns() {
    // Dating Hero Section
    register_block_pattern(
        'wpmatch/hero-dating',
        array(
            'title'       => __('Dating Hero Section', 'wpmatch-theme'),
            'description' => __('A hero section designed for dating sites with call-to-action buttons.', 'wpmatch-theme'),
            'categories'  => array('wpmatch'),
            'content'     => '<!-- wp:cover {"url":"","dimRatio":40,"overlayColor":"dark","minHeight":70,"minHeightUnit":"vh","contentPosition":"center center","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}}} -->
<div class="wp-block-cover" style="padding-top:var(--wp--preset--spacing--80);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--80);padding-left:var(--wp--preset--spacing--50);min-height:70vh"><span aria-hidden="true" class="wp-block-cover__background has-dark-background-color has-background-dim-40 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:heading {"textAlign":"center","level":1,"style":{"typography":{"fontStyle":"normal","fontWeight":"700"}},"textColor":"white","fontSize":"huge"} -->
<h1 class="wp-block-heading has-text-align-center has-white-color has-text-color has-huge-font-size" style="font-style:normal;font-weight:700">Find Your Perfect Match</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","style":{"spacing":{"margin":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|60"}}},"textColor":"white","fontSize":"large"} -->
<p class="has-text-align-center has-white-color has-text-color has-large-font-size" style="margin-top:var(--wp--preset--spacing--50);margin-bottom:var(--wp--preset--spacing--60)">Connect with like-minded singles in your area. Start your journey to lasting love today.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button {"backgroundColor":"primary","textColor":"white","style":{"border":{"radius":"8px"},"spacing":{"padding":{"left":"var:preset|spacing|70","right":"var:preset|spacing|70","top":"var:preset|spacing|40","bottom":"var:preset|spacing|40"}}},"fontSize":"large","className":"is-style-dating-primary"} -->
<div class="wp-block-button has-custom-font-size is-style-dating-primary has-large-font-size"><a class="wp-block-button__link has-white-color has-primary-background-color has-text-color has-background wp-element-button" href="/register/" style="border-radius:8px;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--70)">Join Free Now</a></div>
<!-- /wp:button -->

<!-- wp:button {"textColor":"white","style":{"border":{"radius":"8px","width":"2px"},"spacing":{"padding":{"left":"var:preset|spacing|70","right":"var:preset|spacing|70","top":"var:preset|spacing|40","bottom":"var:preset|spacing|40"}}},"borderColor":"white","fontSize":"large","className":"is-style-outline"} -->
<div class="wp-block-button has-custom-font-size is-style-outline has-large-font-size"><a class="wp-block-button__link has-white-color has-text-color has-white-border-color has-border-color wp-element-button" href="/search/" style="border-width:2px;border-radius:8px;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--70)">Browse Profiles</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div></div>
<!-- /wp:cover -->',
        )
    );

    // Profile Grid Section
    register_block_pattern(
        'wpmatch/profile-grid',
        array(
            'title'       => __('Profile Grid Section', 'wpmatch-theme'),
            'description' => __('A grid layout for displaying dating profiles.', 'wpmatch-theme'),
            'categories'  => array('wpmatch'),
            'content'     => '<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}},"backgroundColor":"off-white","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-off-white-background-color has-background" style="padding-top:var(--wp--preset--spacing--80);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--80);padding-left:var(--wp--preset--spacing--50)"><!-- wp:heading {"textAlign":"center","level":2,"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|60"}}},"textColor":"dark","fontSize":"xxx-large"} -->
<h2 class="wp-block-heading has-text-align-center has-dark-color has-text-color has-xxx-large-font-size" style="margin-bottom:var(--wp--preset--spacing--60)">Featured Members</h2>
<!-- /wp:heading -->

<!-- wp:columns {"align":"wide"} -->
<div class="wp-block-columns alignwide"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"border":{"radius":"12px"}},"backgroundColor":"white","className":"is-style-profile-card","layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-profile-card has-white-background-color has-background" style="border-radius:12px;padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--40)"><!-- wp:image {"align":"center","width":200,"height":250,"sizeSlug":"full","linkDestination":"none","style":{"border":{"radius":"8px"}}} -->
<figure class="wp-block-image aligncenter size-full is-resized" style="border-radius:8px"><img src="" alt="" width="200" height="250"/></figure>
<!-- /wp:image -->

<!-- wp:heading {"textAlign":"center","level":3,"style":{"spacing":{"margin":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|20"}}},"textColor":"dark","fontSize":"large"} -->
<h3 class="wp-block-heading has-text-align-center has-dark-color has-text-color has-large-font-size" style="margin-top:var(--wp--preset--spacing--40);margin-bottom:var(--wp--preset--spacing--20)">Sarah, 28</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","style":{"spacing":{"margin":{"bottom":"var:preset|spacing|30"}}},"textColor":"medium-gray","fontSize":"small"} -->
<p class="has-text-align-center has-medium-gray-color has-text-color has-small-font-size" style="margin-bottom:var(--wp--preset--spacing--30)">New York, NY • 5 km away</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button {"backgroundColor":"primary","textColor":"white","style":{"border":{"radius":"6px"},"spacing":{"padding":{"left":"var:preset|spacing|50","right":"var:preset|spacing|50","top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}},"fontSize":"small"} -->
<div class="wp-block-button has-custom-font-size has-small-font-size"><a class="wp-block-button__link has-white-color has-primary-background-color has-text-color has-background wp-element-button" style="border-radius:6px;padding-top:var(--wp--preset--spacing--30);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--30);padding-left:var(--wp--preset--spacing--50)">View Profile</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"border":{"radius":"12px"}},"backgroundColor":"white","className":"is-style-profile-card","layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-profile-card has-white-background-color has-background" style="border-radius:12px;padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--40)"><!-- wp:image {"align":"center","width":200,"height":250,"sizeSlug":"full","linkDestination":"none","style":{"border":{"radius":"8px"}}} -->
<figure class="wp-block-image aligncenter size-full is-resized" style="border-radius:8px"><img src="" alt="" width="200" height="250"/></figure>
<!-- /wp:image -->

<!-- wp:heading {"textAlign":"center","level":3,"style":{"spacing":{"margin":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|20"}}},"textColor":"dark","fontSize":"large"} -->
<h3 class="wp-block-heading has-text-align-center has-dark-color has-text-color has-large-font-size" style="margin-top:var(--wp--preset--spacing--40);margin-bottom:var(--wp--preset--spacing--20)">Mike, 32</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","style":{"spacing":{"margin":{"bottom":"var:preset|spacing|30"}}},"textColor":"medium-gray","fontSize":"small"} -->
<p class="has-text-align-center has-medium-gray-color has-text-color has-small-font-size" style="margin-bottom:var(--wp--preset--spacing--30)">Brooklyn, NY • 3 km away</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button {"backgroundColor":"primary","textColor":"white","style":{"border":{"radius":"6px"},"spacing":{"padding":{"left":"var:preset|spacing|50","right":"var:preset|spacing|50","top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}},"fontSize":"small"} -->
<div class="wp-block-button has-custom-font-size has-small-font-size"><a class="wp-block-button__link has-white-color has-primary-background-color has-text-color has-background wp-element-button" style="border-radius:6px;padding-top:var(--wp--preset--spacing--30);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--30);padding-left:var(--wp--preset--spacing--50)">View Profile</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"border":{"radius":"12px"}},"backgroundColor":"white","className":"is-style-profile-card","layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-profile-card has-white-background-color has-background" style="border-radius:12px;padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--40)"><!-- wp:image {"align":"center","width":200,"height":250,"sizeSlug":"full","linkDestination":"none","style":{"border":{"radius":"8px"}}} -->
<figure class="wp-block-image aligncenter size-full is-resized" style="border-radius:8px"><img src="" alt="" width="200" height="250"/></figure>
<!-- /wp:image -->

<!-- wp:heading {"textAlign":"center","level":3,"style":{"spacing":{"margin":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|20"}}},"textColor":"dark","fontSize":"large"} -->
<h3 class="wp-block-heading has-text-align-center has-dark-color has-text-color has-large-font-size" style="margin-top:var(--wp--preset--spacing--40);margin-bottom:var(--wp--preset--spacing--20)">Emily, 26</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","style":{"spacing":{"margin":{"bottom":"var:preset|spacing|30"}}},"textColor":"medium-gray","fontSize":"small"} -->
<p class="has-text-align-center has-medium-gray-color has-text-color has-small-font-size" style="margin-bottom:var(--wp--preset--spacing--30)">Manhattan, NY • 2 km away</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button {"backgroundColor":"primary","textColor":"white","style":{"border":{"radius":"6px"},"spacing":{"padding":{"left":"var:preset|spacing|50","right":"var:preset|spacing|50","top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}},"fontSize":"small"} -->
<div class="wp-block-button has-custom-font-size has-small-font-size"><a class="wp-block-button__link has-white-color has-primary-background-color has-text-color has-background wp-element-button" style="border-radius:6px;padding-top:var(--wp--preset--spacing--30);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--30);padding-left:var(--wp--preset--spacing--50)">View Profile</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"},"style":{"spacing":{"margin":{"top":"var:preset|spacing|60"}}}} -->
<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--60)"><!-- wp:button {"textColor":"primary","style":{"border":{"width":"1px","radius":"6px"},"spacing":{"padding":{"left":"var:preset|spacing|60","right":"var:preset|spacing|60","top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}},"borderColor":"primary","className":"is-style-outline","fontSize":"large"} -->
<div class="wp-block-button has-custom-font-size is-style-outline has-large-font-size"><a class="wp-block-button__link has-primary-color has-text-color has-primary-border-color has-border-color wp-element-button" href="/search/" style="border-width:1px;border-radius:6px;padding-top:var(--wp--preset--spacing--30);padding-right:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--30);padding-left:var(--wp--preset--spacing--60)">View All Profiles</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->',
        )
    );

    // Success Story Pattern
    register_block_pattern(
        'wpmatch/success-story',
        array(
            'title'       => __('Success Story Card', 'wpmatch-theme'),
            'description' => __('A card layout for displaying dating success stories.', 'wpmatch-theme'),
            'categories'  => array('wpmatch'),
            'content'     => '<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}},"border":{"radius":"12px"}},"backgroundColor":"white","layout":{"type":"constrained"}} -->
<div class="wp-block-group has-white-background-color has-background" style="border-radius:12px;padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)"><!-- wp:columns {"verticalAlignment":"center"} -->
<div class="wp-block-columns are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center","width":"120px"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:120px"><!-- wp:image {"width":100,"height":100,"sizeSlug":"full","linkDestination":"none","style":{"border":{"radius":"50%"}}} -->
<figure class="wp-block-image size-full is-resized" style="border-radius:50%"><img src="" alt="" width="100" height="100"/></figure>
<!-- /wp:image --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center"} -->
<div class="wp-block-column is-vertically-aligned-center"><!-- wp:heading {"level":3,"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|20"}}},"textColor":"dark","fontSize":"large"} -->
<h3 class="wp-block-heading has-dark-color has-text-color has-large-font-size" style="margin-bottom:var(--wp--preset--spacing--20)">Sarah & John</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|20"}}},"textColor":"medium-gray","fontSize":"small"} -->
<p class="has-medium-gray-color has-text-color has-small-font-size" style="margin-bottom:var(--wp--preset--spacing--20)">Married after 2 years</p>
<!-- /wp:paragraph -->

<!-- wp:quote {"style":{"border":{"left":{"color":"var:preset|color|primary","style":"solid","width":"4px"}},"spacing":{"padding":{"left":"var:preset|spacing|40"}}}} -->
<blockquote class="wp-block-quote" style="border-left-color:var(--wp--preset--color--primary);border-left-style:solid;border-left-width:4px;padding-left:var(--wp--preset--spacing--40)"><!-- wp:paragraph {"style":{"typography":{"fontStyle":"italic"}},"textColor":"dark"} -->
<p class="has-dark-color has-text-color" style="font-style:italic">"We met on this platform and instantly connected. Two years later, we\'re happily married with a beautiful family!"</p>
<!-- /wp:paragraph --></blockquote>
<!-- /wp:quote --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->',
        )
    );

    // Dating CTA Section
    register_block_pattern(
        'wpmatch/cta-section',
        array(
            'title'       => __('Dating CTA Section', 'wpmatch-theme'),
            'description' => __('A call-to-action section encouraging user registration.', 'wpmatch-theme'),
            'categories'  => array('wpmatch'),
            'content'     => '<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}},"gradient":"primary-secondary","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-primary-secondary-gradient-background has-background" style="padding-top:var(--wp--preset--spacing--80);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--80);padding-left:var(--wp--preset--spacing--50)"><!-- wp:heading {"textAlign":"center","level":2,"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|40"}}},"textColor":"white","fontSize":"xxx-large"} -->
<h2 class="wp-block-heading has-text-align-center has-white-color has-text-color has-xxx-large-font-size" style="margin-bottom:var(--wp--preset--spacing--40)">Ready to Find Love?</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","style":{"spacing":{"margin":{"bottom":"var:preset|spacing|60"}}},"textColor":"white","fontSize":"large"} -->
<p class="has-text-align-center has-white-color has-text-color has-large-font-size" style="margin-bottom:var(--wp--preset--spacing--60)">Join thousands of singles who have found their perfect match. Your love story starts here.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button {"backgroundColor":"white","textColor":"primary","style":{"border":{"radius":"8px"},"spacing":{"padding":{"left":"var:preset|spacing|70","right":"var:preset|spacing|70","top":"var:preset|spacing|40","bottom":"var:preset|spacing|40"}}},"fontSize":"large"} -->
<div class="wp-block-button has-custom-font-size has-large-font-size"><a class="wp-block-button__link has-primary-color has-white-background-color has-text-color has-background wp-element-button" href="/register/" style="border-radius:8px;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--70)">Create Free Account</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->',
        )
    );

    // Features Section
    register_block_pattern(
        'wpmatch/features-section',
        array(
            'title'       => __('Dating Features Section', 'wpmatch-theme'),
            'description' => __('A section highlighting key dating platform features.', 'wpmatch-theme'),
            'categories'  => array('wpmatch'),
            'content'     => '<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--80);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--80);padding-left:var(--wp--preset--spacing--50)"><!-- wp:heading {"textAlign":"center","level":2,"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|60"}}},"textColor":"dark","fontSize":"xxx-large"} -->
<h2 class="wp-block-heading has-text-align-center has-dark-color has-text-color has-xxx-large-font-size" style="margin-bottom:var(--wp--preset--spacing--60)">Why Choose Our Platform?</h2>
<!-- /wp:heading -->

<!-- wp:columns {"align":"wide"} -->
<div class="wp-block-columns alignwide"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"border":{"radius":"12px"}},"backgroundColor":"off-white","layout":{"type":"constrained"}} -->
<div class="wp-block-group has-off-white-background-color has-background" style="border-radius:12px;padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--40)"><!-- wp:heading {"textAlign":"center","level":3,"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|30"}}},"textColor":"primary","fontSize":"xx-large"} -->
<h3 class="wp-block-heading has-text-align-center has-primary-color has-text-color has-xx-large-font-size" style="margin-bottom:var(--wp--preset--spacing--30)">🔒</h3>
<!-- /wp:heading -->

<!-- wp:heading {"textAlign":"center","level":4,"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|30"}}},"textColor":"dark","fontSize":"large"} -->
<h4 class="wp-block-heading has-text-align-center has-dark-color has-text-color has-large-font-size" style="margin-bottom:var(--wp--preset--spacing--30)">Safe & Secure</h4>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","textColor":"medium-gray"} -->
<p class="has-text-align-center has-medium-gray-color has-text-color">Your privacy and security are our top priorities. All profiles are verified and monitored.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"border":{"radius":"12px"}},"backgroundColor":"off-white","layout":{"type":"constrained"}} -->
<div class="wp-block-group has-off-white-background-color has-background" style="border-radius:12px;padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--40)"><!-- wp:heading {"textAlign":"center","level":3,"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|30"}}},"textColor":"primary","fontSize":"xx-large"} -->
<h3 class="wp-block-heading has-text-align-center has-primary-color has-text-color has-xx-large-font-size" style="margin-bottom:var(--wp--preset--spacing--30)">🎯</h3>
<!-- /wp:heading -->

<!-- wp:heading {"textAlign":"center","level":4,"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|30"}}},"textColor":"dark","fontSize":"large"} -->
<h4 class="wp-block-heading has-text-align-center has-dark-color has-text-color has-large-font-size" style="margin-bottom:var(--wp--preset--spacing--30)">Smart Matching</h4>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","textColor":"medium-gray"} -->
<p class="has-text-align-center has-medium-gray-color has-text-color">Our advanced algorithm matches you with compatible partners based on your preferences and interests.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"border":{"radius":"12px"}},"backgroundColor":"off-white","layout":{"type":"constrained"}} -->
<div class="wp-block-group has-off-white-background-color has-background" style="border-radius:12px;padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--40)"><!-- wp:heading {"textAlign":"center","level":3,"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|30"}}},"textColor":"primary","fontSize":"xx-large"} -->
<h3 class="wp-block-heading has-text-align-center has-primary-color has-text-color has-xx-large-font-size" style="margin-bottom:var(--wp--preset--spacing--30)">💬</h3>
<!-- /wp:heading -->

<!-- wp:heading {"textAlign":"center","level":4,"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|30"}}},"textColor":"dark","fontSize":"large"} -->
<h4 class="wp-block-heading has-text-align-center has-dark-color has-text-color has-large-font-size" style="margin-bottom:var(--wp--preset--spacing--30)">Easy Communication</h4>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","textColor":"medium-gray"} -->
<p class="has-text-align-center has-medium-gray-color has-text-color">Connect instantly with our intuitive messaging system. Start meaningful conversations that lead to lasting relationships.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->',
        )
    );
}
add_action('init', 'wpmatch_theme_register_patterns');