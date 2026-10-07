<?php
/**
 * Plugin Name: Altus Image Compressor
 * Description: Free lead-generation tool. Shrink JPEG, PNG and WebP images right in the browser — quality + resize controls, before/after savings, download. No uploads, 100% client-side. Optional "email me my image" lead capture.
 * Version: 1.0.0
 * Author: Altus Web Solutions
 * Text Domain: altus-image-compressor
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * No third-party libraries: compression uses the browser's native Canvas API.
 */

if (!defined('ABSPATH')) exit;

define('AIC_VERSION', '1.0.0');
define('AIC_PATH', plugin_dir_path(__FILE__));
define('AIC_URL', plugin_dir_url(__FILE__));
define('AIC_TABLE', 'altus_aic_leads');

/* ------------------------------------------------------------------ */
/* Activation: create the leads table                                 */
/* ------------------------------------------------------------------ */
register_activation_hook(__FILE__, 'aic_activate');
function aic_activate() {
    global $wpdb;
    $table   = $wpdb->prefix . AIC_TABLE;
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        name VARCHAR(200) DEFAULT '',
        email VARCHAR(200) DEFAULT '',
        ip VARCHAR(45) DEFAULT '',
        PRIMARY KEY (id),
        KEY email (email),
        KEY created_at (created_at)
    ) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    if (get_option('aic_settings') === false) {
        add_option('aic_settings', array(
            'notify_email' => 'info@altuswebsolutions.com',
            'book_url'     => 'https://altuswebsolutions.com/schedule-free-consultation/',
            'from_name'    => 'Altus Web Solutions',
        ));
    }
}

function aic_get_settings() {
    $defaults = array(
        'notify_email' => 'info@altuswebsolutions.com',
        'book_url'     => 'https://altuswebsolutions.com/schedule-free-consultation/',
        'from_name'    => 'Altus Web Solutions',
    );
    $saved = get_option('aic_settings', array());
    return wp_parse_args(is_array($saved) ? $saved : array(), $defaults);
}

/* ------------------------------------------------------------------ */
/* Frontend assets (only loaded when the shortcode is present)        */
/* ------------------------------------------------------------------ */
add_action('wp_enqueue_scripts', 'aic_enqueue');
function aic_enqueue() {
    if (is_admin()) return;
    global $post;
    if (!($post instanceof WP_Post) || !has_shortcode($post->post_content, 'altus_image_compressor')) return;

    wp_enqueue_style('aic-gen', AIC_URL . 'assets/generator.css', array(), AIC_VERSION);
    wp_enqueue_script('aic-logic', AIC_URL . 'assets/compressor-logic.js', array(), AIC_VERSION, true);
    wp_enqueue_script('aic-gen', AIC_URL . 'assets/generator.js', array('aic-logic'), AIC_VERSION, true);
    wp_localize_script('aic-gen', 'AIC', array(
        'ajax'  => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('aic_gen'),
    ));
}

/* ------------------------------------------------------------------ */
/* Shortcode: renders the compressor UI                               */
/* ------------------------------------------------------------------ */
add_shortcode('altus_image_compressor', 'aic_shortcode');
function aic_shortcode() {
    ob_start();
    ?>
    <div id="aic-root" class="aic-root">
        <div class="aic-card">
            <div class="aic-badge">Free tool</div>
            <h2 class="aic-title">Shrink your image</h2>
            <p class="aic-sub">Drop in a JPEG, PNG or WebP and make it web-ready in seconds — pick a format, drag the quality slider, optionally resize. Your image never leaves your browser.</p>

            <div id="aic-drop" class="aic-drop" role="button" tabindex="0" aria-label="Choose an image to compress">
                <span class="aic-drop-icon" aria-hidden="true">🖼️</span>
                <p><strong>Drag &amp; drop your image here</strong><br>or</p>
                <button type="button" class="aic-btn aic-btn-secondary" id="aic-choose">Choose a file</button>
                <input type="file" id="aic-file" accept="image/jpeg,image/png,image/webp" hidden>
                <p class="aic-hint" style="margin-top:10px">JPEG, PNG or WebP · up to 25 MB</p>
            </div>

            <p class="aic-error" id="aic-error" role="alert" hidden></p>

            <div id="aic-controls" hidden>
                <div class="aic-file-summary">
                    <img id="aic-orig-thumb" alt="Original image preview">
                    <div>
                        <p class="aic-file-name" id="aic-orig-name"></p>
                        <p class="aic-file-meta" id="aic-orig-meta"></p>
                    </div>
                </div>

                <div class="aic-field-row">
                    <div class="aic-field">
                        <label class="aic-label" for="aic-format">Output format</label>
                        <select id="aic-format" class="aic-input aic-select">
                            <option value="original" selected>Same as original</option>
                            <option value="image/jpeg">JPEG — smallest for photos</option>
                            <option value="image/webp">WebP — modern, sharp + small</option>
                            <option value="image/png">PNG — lossless (stays bigger)</option>
                        </select>
                        <p class="aic-hint" id="aic-format-advice" hidden></p>
                    </div>
                    <div class="aic-field">
                        <label class="aic-label" for="aic-maxwidth">Resize (max width)</label>
                        <select id="aic-maxwidth" class="aic-input aic-select">
                            <option value="0" selected>Keep original size</option>
                            <option value="640">640 px — thumbnails</option>
                            <option value="1024">1024 px — blog images</option>
                            <option value="1600">1600 px — hero banners</option>
                            <option value="2560">2560 px — large screens</option>
                        </select>
                    </div>
                </div>

                <div class="aic-field" id="aic-quality-wrap">
                    <label class="aic-label" for="aic-quality">Quality</label>
                    <div class="aic-range-row">
                        <input type="range" id="aic-quality" min="10" max="100" value="80" step="5">
                        <span class="aic-range-val" id="aic-quality-val">80%</span>
                    </div>
                    <p class="aic-hint">Around 80% looks identical to the original on screen but is dramatically smaller. Drop to 60–70% for thumbnails.</p>
                </div>

                <p class="aic-warn" id="aic-trans-warn" role="alert" hidden>⚠️ JPEG cannot keep transparency — any see-through areas of your PNG will become white.</p>

                <button type="button" class="aic-btn" id="aic-compress">Compress image</button>
            </div>

            <div id="aic-results" hidden>
                <h3>Your results</h3>
                <div class="aic-result-grid">
                    <div class="aic-stat">
                        <div class="aic-stat-label">Original</div>
                        <div class="aic-stat-num" id="aic-orig-size">–</div>
                    </div>
                    <div class="aic-stat">
                        <div class="aic-stat-label">Compressed</div>
                        <div class="aic-stat-num" id="aic-out-size">–</div>
                    </div>
                    <div class="aic-stat">
                        <div class="aic-stat-label">Smaller by</div>
                        <div class="aic-stat-num" id="aic-savings">–</div>
                    </div>
                </div>
                <p class="aic-verdict" id="aic-verdict"></p>
                <div class="aic-compare">
                    <figure>
                        <img id="aic-orig-thumb2" alt="Original image">
                        <figcaption id="aic-dims">–</figcaption>
                    </figure>
                    <figure>
                        <img id="aic-out-thumb" alt="Compressed image preview">
                        <figcaption id="aic-out-dims">–</figcaption>
                    </figure>
                </div>
                <p class="aic-warn" id="aic-big-warn" role="alert" hidden>⚠️ The "compressed" file is actually bigger than the original — this happens with already-optimised images. Try converting to WebP or lowering the quality.</p>
                <div class="aic-btn-row">
                    <a class="aic-btn" id="aic-download" href="#" download>Download compressed image</a>
                </div>

                <div class="aic-gate">
                    <form id="aic-send-form" novalidate>
                        <h3>Want it in your inbox too?</h3>
                        <p>We'll email you the compressed image — handy for your records or to hand to your designer. Plus one follow-up email from Altus. No spam, ever.</p>
                        <div class="aic-field">
                            <label class="aic-label" for="aic-send-name">Your name</label>
                            <input type="text" id="aic-send-name" class="aic-input" autocomplete="name">
                            <p class="aic-error" id="aic-e-send-name" role="alert" hidden></p>
                        </div>
                        <div class="aic-field">
                            <label class="aic-label" for="aic-send-email">Email address</label>
                            <input type="email" id="aic-send-email" class="aic-input" autocomplete="email">
                            <p class="aic-error" id="aic-e-send-email" role="alert" hidden></p>
                        </div>
                        <button type="submit" class="aic-btn" id="aic-send-btn">Email me my compressed image</button>
                    </form>
                    <div class="aic-gate-done" id="aic-send-done" hidden>
                        <div class="aic-tick">✓</div>
                        <h3>Sent!</h3>
                        <p>Check your inbox — your compressed image is on its way. (Don't see it? Check spam, or <a href="mailto:info@altuswebsolutions.com" style="color:#fff">email us</a>.)</p>
                    </div>
                </div>
            </div>

            <p class="aic-fineprint">Everything above runs entirely in your browser — your images never leave your device unless you ask us to email you the result.</p>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/* ------------------------------------------------------------------ */
/* AJAX: email the compressed image + store lead                       */
/* ------------------------------------------------------------------ */
add_action('wp_ajax_aic_send_image', 'aic_send_image');
add_action('wp_ajax_nopriv_aic_send_image', 'aic_send_image');
function aic_send_image() {
    if (!check_ajax_referer('aic_gen', 'nonce', false)) {
        wp_send_json_error('Security check failed. Please reload the page and try again.');
    }

    $name  = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
    $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $img64 = isset($_POST['img']) ? wp_unslash($_POST['img']) : '';
    $mime  = isset($_POST['mime']) ? sanitize_text_field(wp_unslash($_POST['mime'])) : '';
    $fname = isset($_POST['filename']) ? sanitize_file_name(wp_unslash($_POST['filename'])) : 'compressed-image';

    if (mb_strlen($name) < 2) wp_send_json_error('Please enter your name.');
    if (!is_email($email)) wp_send_json_error('Please enter a valid email address.');
    if (!in_array($mime, array('image/jpeg', 'image/png', 'image/webp'), true)) {
        wp_send_json_error('The image format was invalid. Please use the download button instead.');
    }

    $img = base64_decode($img64, true);
    if ($img === false || strlen($img) < 100) wp_send_json_error('The image was invalid. Please use the download button instead.');
    if (strlen($img) > 2 * 1024 * 1024) wp_send_json_error('The image is too large to email. Please use the download button instead.');

    // Sanity: check magic bytes match the claimed format.
    $ok = false;
    if ($mime === 'image/jpeg' && substr($img, 0, 3) === "\xFF\xD8\xFF") $ok = true;
    if ($mime === 'image/png' && substr($img, 0, 8) === "\x89PNG\r\n\x1a\n") $ok = true;
    if ($mime === 'image/webp' && substr($img, 0, 4) === 'RIFF' && substr($img, 8, 4) === 'WEBP') $ok = true;
    if (!$ok) wp_send_json_error('The image was invalid. Please use the download button instead.');

    $ext = ($mime === 'image/jpeg') ? '.jpg' : (($mime === 'image/png') ? '.png' : '.webp');
    $tmp = wp_tempnam('compressed-') . $ext;
    file_put_contents($tmp, $img);

    $settings = aic_get_settings();

    // Store the lead.
    global $wpdb;
    $wpdb->insert(
        $wpdb->prefix . AIC_TABLE,
        array(
            'name'  => $name,
            'email' => $email,
            'ip'    => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
        ),
        array('%s', '%s', '%s')
    );

    $subject = 'Your compressed image from Altus Web Solutions';
    $body    = "Hi " . $name . ",\n\n"
        . "Your compressed image is attached — ready to upload to your website.\n\n"
        . "Quick tip: always keep the original full-size image as a backup. Compress copies for the web, never the only original.\n\n"
        . "Need a hand with your website? Book a free 15-minute consultation: " . $settings['book_url'] . "\n\n"
        . "— The Altus Web Solutions team";
    $headers = array('From: ' . $settings['from_name'] . ' <' . $settings['notify_email'] . '>');
    $sent = wp_mail($email, $subject, $body, $headers, array($tmp));

    // Notify Altus about the lead.
    $notify_body = "New Image Compressor lead:\n\nName: {$name}\nEmail: {$email}\nTime: " . current_time('mysql') . "\n";
    wp_mail($settings['notify_email'], 'New image compressor tool lead: ' . $name, $notify_body, $headers);

    @unlink($tmp);

    if (!$sent) wp_send_json_error('Sorry, that did not send. Please use the download button above instead.');
    wp_send_json_success();
}
