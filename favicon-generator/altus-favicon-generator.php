<?php
/**
 * Plugin Name: Altus Favicon Generator
 * Description: Free lead-generation tool. Upload a logo and get every favicon size your site needs — PNGs, a real multi-size .ico, install snippets, all generated 100% client-side. Optional "email me the files" lead capture.
 * Version: 1.0.0
 * Author: Altus Web Solutions
 * Text Domain: altus-favicon-generator
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

define('AFCG_VERSION', '1.0.0');
define('AFCG_PATH', plugin_dir_path(__FILE__));
define('AFCG_URL', plugin_dir_url(__FILE__));
define('AFCG_TABLE', 'altus_cfg_leads');

/* ------------------------------------------------------------------ */
/* Activation: create the leads table                                 */
/* ------------------------------------------------------------------ */
register_activation_hook(__FILE__, 'afcg_activate');
function afcg_activate() {
    global $wpdb;
    $table   = $wpdb->prefix . AFCG_TABLE;
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

    if (get_option('afcg_settings') === false) {
        add_option('afcg_settings', array(
            'notify_email' => 'support@altuswebsolutions.com',
            'book_url'     => 'https://altuswebsolutions.com/schedule-free-consultation/',
            'from_name'    => 'Altus Web Solutions',
        ));
    }
}

function afcg_get_settings() {
    $defaults = array(
        'notify_email' => 'support@altuswebsolutions.com',
        'book_url'     => 'https://altuswebsolutions.com/schedule-free-consultation/',
        'from_name'    => 'Altus Web Solutions',
    );
    $saved = get_option('afcg_settings', array());
    return wp_parse_args(is_array($saved) ? $saved : array(), $defaults);
}

/* ------------------------------------------------------------------ */
/* Frontend assets (only loaded when the shortcode is present)        */
/* ------------------------------------------------------------------ */
add_action('wp_enqueue_scripts', 'afcg_enqueue');
function afcg_enqueue() {
    if (is_admin()) return;
    global $post;
    if (!($post instanceof WP_Post) || !has_shortcode($post->post_content, 'altus_favicon_generator')) return;

    wp_enqueue_style('afcg-gen', AFCG_URL . 'assets/generator.css', array(), AFCG_VERSION);
    wp_enqueue_script('afcg-logic', AFCG_URL . 'assets/favicon-logic.js', array(), AFCG_VERSION, true);
    wp_enqueue_script('afcg-gen', AFCG_URL . 'assets/generator.js', array('afcg-logic'), AFCG_VERSION, true);
    wp_localize_script('afcg-gen', 'AFCG', array(
        'ajax'  => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('afcg_gen'),
    ));
}

/* ------------------------------------------------------------------ */
/* Shortcode: renders the generator UI                                */
/* ------------------------------------------------------------------ */
add_shortcode('altus_favicon_generator', 'afcg_shortcode');
function afcg_shortcode() {
    ob_start();
    ?>
    <div id="afcg-root" class="afcg-root">
        <div class="afcg-progress"><div id="afcg-progress-bar"></div></div>

        <!-- Step 1: upload -->
        <div class="afcg-card" id="afcg-s1">
            <div class="afcg-badge">Free tool</div>
            <h2 class="afcg-title">Upload your logo</h2>
            <p class="afcg-sub">Pick the image you want as your favicon — your logo works best. Everything is generated right here in your browser; your image is never uploaded to our servers.</p>
            <div class="afcg-drop" id="afcg-drop" role="button" tabindex="0" aria-label="Upload an image">
                <span class="afcg-drop-icon">🖼️</span>
                <strong>Drop your image here, or click to choose</strong>
                <span>PNG or JPG, ideally 512×512 or larger. Max 10&nbsp;MB.</span>
            </div>
            <input type="file" id="afcg-file" accept="image/*" hidden>
            <p class="afcg-error" id="afcg-e-file" role="alert" hidden></p>
        </div>

        <!-- Step 2: options + preview -->
        <div class="afcg-card" id="afcg-s2" hidden>
            <div class="afcg-badge">Free tool</div>
            <h2 class="afcg-title">Style it, preview it</h2>
            <p class="afcg-sub">Tune the background and padding, then check the live previews — including how it looks in a real browser tab.</p>
            <div id="afcg-warnings"></div>

            <div class="afcg-field">
                <span class="afcg-label">Background</span>
                <div class="afcg-opt-grid">
                    <label class="afcg-opt-card checked" data-afcg-bg-card="transparent">
                        <input type="radio" name="afcg-bg" value="transparent" checked>
                        <span class="afcg-swatch afcg-swatch-trans"></span>Transparent
                    </label>
                    <label class="afcg-opt-card" data-afcg-bg-card="white">
                        <input type="radio" name="afcg-bg" value="white">
                        <span class="afcg-swatch" style="background:#fff"></span>White
                    </label>
                    <label class="afcg-opt-card" data-afcg-bg-card="navy">
                        <input type="radio" name="afcg-bg" value="navy">
                        <span class="afcg-swatch" style="background:#0f2033"></span>Dark navy
                    </label>
                    <label class="afcg-opt-card" data-afcg-bg-card="blue">
                        <input type="radio" name="afcg-bg" value="blue">
                        <span class="afcg-swatch" style="background:#10b3fe"></span>Altus blue
                    </label>
                </div>
                <p class="afcg-hint">Heads-up: transparent backgrounds show as black on iPhone home screens. Pick white or a colour if your logo needs a background.</p>
            </div>

            <div class="afcg-field">
                <span class="afcg-label">Padding</span>
                <div class="afcg-opt-grid">
                    <label class="afcg-opt-card" data-afcg-pad-card="0">
                        <input type="radio" name="afcg-pad" value="0">None
                    </label>
                    <label class="afcg-opt-card checked" data-afcg-pad-card="0.08">
                        <input type="radio" name="afcg-pad" value="0.08" checked>Small
                    </label>
                    <label class="afcg-opt-card" data-afcg-pad-card="0.18">
                        <input type="radio" name="afcg-pad" value="0.18">Large
                    </label>
                </div>
            </div>

            <div class="afcg-preview-row">
                <div>
                    <div class="afcg-tabmock">
                        <div class="afcg-tab">
                            <canvas id="afcg-tab-icon"></canvas>
                            <span>Your page title</span>
                            <span class="afcg-x">×</span>
                        </div>
                    </div>
                    <p class="afcg-tabcap">Browser tab preview (real 16×16)</p>
                </div>
                <div class="afcg-size-previews" id="afcg-size-previews"></div>
            </div>

            <div class="afcg-btn-row">
                <button type="button" class="afcg-btn afcg-btn-secondary" id="afcg-back-s1">← Back</button>
                <button type="button" class="afcg-btn" id="afcg-generate">Generate my favicons →</button>
            </div>
        </div>

        <!-- Step 3: results -->
        <div class="afcg-card" id="afcg-s3" hidden>
            <div class="afcg-badge">Your favicons are ready</div>
            <h2 class="afcg-title">Download and install</h2>
            <p class="afcg-sub">Every file your site needs. Upload them to your website's main folder, then paste the snippet into your site's <code>&lt;head&gt;</code>.</p>

            <h3 class="afcg-h3">Your files</h3>
            <div class="afcg-file-list" id="afcg-file-list"></div>
            <div class="afcg-actions">
                <button type="button" class="afcg-btn" id="afcg-dl-zip">Download everything (.zip)</button>
                <button type="button" class="afcg-btn afcg-btn-secondary" id="afcg-start-over">← Start over</button>
            </div>
            <p class="afcg-error" id="afcg-e-zip" role="alert" hidden></p>

            <h3 class="afcg-h3">1. Paste this into your site's &lt;head&gt;</h3>
            <div class="afcg-snippet" id="afcg-snippet-html"></div>
            <div class="afcg-actions">
                <button type="button" class="afcg-btn afcg-btn-secondary" id="afcg-copy-html">Copy snippet</button>
            </div>

            <h3 class="afcg-h3">2. Save this as manifest.webmanifest (for Android / PWA)</h3>
            <div class="afcg-snippet" id="afcg-snippet-manifest"></div>
            <div class="afcg-actions">
                <button type="button" class="afcg-btn afcg-btn-secondary" id="afcg-copy-manifest">Copy</button>
                <button type="button" class="afcg-btn afcg-btn-secondary" id="afcg-dl-manifest">Download file</button>
            </div>

            <div class="afcg-gate">
                <form id="afcg-send-form" novalidate>
                    <h3>Want the files in your inbox?</h3>
                    <p>We'll email you the complete favicon package as a zip — handy for your records or to hand to your web person. Plus one follow-up email from Altus. No spam, ever.</p>
                    <div class="afcg-field">
                        <label class="afcg-label" for="afcg-send-name">Your name</label>
                        <input type="text" id="afcg-send-name" class="afcg-input" autocomplete="name">
                        <p class="afcg-error" id="afcg-e-send-name" role="alert" hidden></p>
                    </div>
                    <div class="afcg-field">
                        <label class="afcg-label" for="afcg-send-email">Email address</label>
                        <input type="email" id="afcg-send-email" class="afcg-input" autocomplete="email">
                        <p class="afcg-error" id="afcg-e-send-email" role="alert" hidden></p>
                    </div>
                    <button type="submit" class="afcg-btn" id="afcg-send-btn">Email me the files</button>
                </form>
                <div class="afcg-gate-done" id="afcg-send-done" hidden>
                    <div class="afcg-tick">✓</div>
                    <h3>Sent!</h3>
                    <p>Check your inbox — your favicon package is on its way. (Don't see it? Check spam, or <a href="mailto:support@altuswebsolutions.com" style="color:#fff">email us</a>.)</p>
                </div>
            </div>

            <p class="afcg-fineprint">Everything above runs entirely in your browser — your logo never leaves your device unless you ask us to email you the files.</p>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/* ------------------------------------------------------------------ */
/* AJAX: email the favicon zip + store lead                            */
/* ------------------------------------------------------------------ */
add_action('wp_ajax_afcg_send_files', 'afcg_send_files');
add_action('wp_ajax_nopriv_afcg_send_files', 'afcg_send_files');
function afcg_send_files() {
    if (!check_ajax_referer('afcg_gen', 'nonce', false)) {
        wp_send_json_error('Security check failed. Please reload the page and try again.');
    }

    $name  = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
    $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $zip64 = isset($_POST['zip']) ? wp_unslash($_POST['zip']) : '';

    if (mb_strlen($name) < 2) wp_send_json_error('Please enter your name.');
    if (!is_email($email)) wp_send_json_error('Please enter a valid email address.');

    $zip = base64_decode($zip64, true);
    if ($zip === false || strlen($zip) < 100) wp_send_json_error('The file package was invalid. Please use the download buttons instead.');
    if (strlen($zip) > 2 * 1024 * 1024) wp_send_json_error('The package is too large to email. Please use the download buttons instead.');
    // Sanity: must be a ZIP (PK magic)
    if (substr($zip, 0, 2) !== 'PK') wp_send_json_error('The file package was invalid. Please use the download buttons instead.');

    $settings = afcg_get_settings();

    // Store the lead.
    global $wpdb;
    $wpdb->insert(
        $wpdb->prefix . AFCG_TABLE,
        array(
            'name'  => $name,
            'email' => $email,
            'ip'    => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
        ),
        array('%s', '%s', '%s')
    );

    // Attach the zip.
    $tmp = wp_tempnam('favicon-package-') . '.zip';
    file_put_contents($tmp, $zip);

    $subject = 'Your favicon package from Altus Web Solutions';
    $body    = "Hi " . $name . ",\n\n"
        . "Your favicon package is attached — every size your site needs (favicon.ico, PNGs for browsers, Apple touch icon and Android icons), plus the install snippets.\n\n"
        . "Quick install: upload the files to your website's main folder, then paste the <link> tags from the tool page into your site's <head>.\n\n"
        . "Need a hand? Book a free 15-minute consultation: " . $settings['book_url'] . "\n\n"
        . "— The Altus Web Solutions team";
    $headers = array('From: ' . $settings['from_name'] . ' <' . $settings['notify_email'] . '>');
    $sent = wp_mail($email, $subject, $body, $headers, array($tmp));

    // Notify Altus about the lead.
    $notify_body = "New Favicon Generator lead:\n\nName: {$name}\nEmail: {$email}\nTime: " . current_time('mysql') . "\n";
    wp_mail($settings['notify_email'], 'New favicon tool lead: ' . $name, $notify_body, $headers);

    @unlink($tmp);

    if (!$sent) wp_send_json_error('Sorry, that did not send. Please use the download buttons above instead.');
    wp_send_json_success();
}
