<?php
/**
 * Plugin Name: Altus QR Code Generator
 * Description: Free lead-generation tool. Make scannable QR codes for links, text, email, phone, SMS or Wi-Fi — custom colours, PNG + SVG downloads, 100% client-side. Optional "email me my QR code" lead capture.
 * Version: 1.0.1
 * Author: Altus Web Solutions
 * Text Domain: altus-qr-code-generator
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * QR encoding engine: qrcode-generator 1.4.4 by Kazuhiko Arase (MIT licence),
 * bundled in assets/vendor/qrcode.min.js — see assets/vendor/ATTRIBUTION.txt.
 */

if (!defined('ABSPATH')) exit;

define('AQG_VERSION', '1.0.1');
define('AQG_PATH', plugin_dir_path(__FILE__));
define('AQG_URL', plugin_dir_url(__FILE__));
define('AQG_TABLE', 'altus_aqg_leads');

/* ------------------------------------------------------------------ */
/* Activation: create the leads table                                 */
/* ------------------------------------------------------------------ */
register_activation_hook(__FILE__, 'aqg_activate');
function aqg_activate() {
    global $wpdb;
    $table   = $wpdb->prefix . AQG_TABLE;
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

    if (get_option('aqg_settings') === false) {
        add_option('aqg_settings', array(
            'notify_email' => 'info@altuswebsolutions.com',
            'book_url'     => 'https://altuswebsolutions.com/schedule-free-consultation/',
            'from_name'    => 'Altus Web Solutions',
        ));
    }
}

function aqg_get_settings() {
    $defaults = array(
        'notify_email' => 'info@altuswebsolutions.com',
        'book_url'     => 'https://altuswebsolutions.com/schedule-free-consultation/',
        'from_name'    => 'Altus Web Solutions',
    );
    $saved = get_option('aqg_settings', array());
    return wp_parse_args(is_array($saved) ? $saved : array(), $defaults);
}

/* ------------------------------------------------------------------ */
/* Frontend assets (only loaded when the shortcode is present)        */
/* ------------------------------------------------------------------ */
add_action('wp_enqueue_scripts', 'aqg_enqueue');
function aqg_enqueue() {
    if (is_admin()) return;
    global $post;
    if (!($post instanceof WP_Post) || !has_shortcode($post->post_content, 'altus_qr_generator')) return;

    wp_enqueue_style('aqg-gen', AQG_URL . 'assets/generator.css', array(), AQG_VERSION);
    wp_enqueue_script('aqg-engine', AQG_URL . 'assets/vendor/qrcode.min.js', array(), '1.4.4', true);
    wp_enqueue_script('aqg-content', AQG_URL . 'assets/qr-content.js', array(), AQG_VERSION, true);
    wp_enqueue_script('aqg-gen', AQG_URL . 'assets/generator.js', array('aqg-engine', 'aqg-content'), AQG_VERSION, true);
    wp_localize_script('aqg-gen', 'AQG', array(
        'ajax'  => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('aqg_gen'),
    ));
}

/* ------------------------------------------------------------------ */
/* Shortcode: renders the generator UI                                */
/* ------------------------------------------------------------------ */
add_shortcode('altus_qr_generator', 'aqg_shortcode');
function aqg_shortcode() {
    ob_start();
    ?>
    <div id="aqg-root" class="aqg-root">
        <div class="aqg-card">
            <div class="aqg-badge">Free tool</div>
            <h2 class="aqg-title">Make your QR code</h2>
            <p class="aqg-sub">Pick what your QR code should do, style it in your colours, and download it as a PNG or SVG. Everything is generated right here in your browser — nothing is uploaded anywhere.</p>

            <div class="aqg-tabs" role="tablist" aria-label="QR code content type">
                <button type="button" class="aqg-tab active" data-aqg-type="url" role="tab" aria-selected="true">Website</button>
                <button type="button" class="aqg-tab" data-aqg-type="text" role="tab" aria-selected="false">Text</button>
                <button type="button" class="aqg-tab" data-aqg-type="email" role="tab" aria-selected="false">Email</button>
                <button type="button" class="aqg-tab" data-aqg-type="phone" role="tab" aria-selected="false">Phone</button>
                <button type="button" class="aqg-tab" data-aqg-type="sms" role="tab" aria-selected="false">SMS</button>
                <button type="button" class="aqg-tab" data-aqg-type="wifi" role="tab" aria-selected="false">Wi-Fi</button>
            </div>

            <div class="aqg-panels">
                <div class="aqg-panel" data-aqg-panel="url">
                    <label class="aqg-label" for="aqg-url">Website address</label>
                    <input type="text" id="aqg-url" class="aqg-input" placeholder="https://yourwebsite.co.uk" autocomplete="url" inputmode="url">
                    <p class="aqg-hint">Scanning opens this page on the visitor's phone. We'll add the https:// if you forget it.</p>
                </div>
                <div class="aqg-panel" data-aqg-panel="text" hidden>
                    <label class="aqg-label" for="aqg-text">Your text</label>
                    <textarea id="aqg-text" class="aqg-input aqg-textarea" rows="3" placeholder="Type or paste any text — a message, an address, a discount code…"></textarea>
                </div>
                <div class="aqg-panel" data-aqg-panel="email" hidden>
                    <label class="aqg-label" for="aqg-email-addr">Email address</label>
                    <input type="email" id="aqg-email-addr" class="aqg-input" placeholder="you@yourbusiness.co.uk" autocomplete="email">
                    <p class="aqg-hint">Scanning opens a new email addressed to you — perfect for menus, receipts and "contact us" cards.</p>
                </div>
                <div class="aqg-panel" data-aqg-panel="phone" hidden>
                    <label class="aqg-label" for="aqg-phone-num">Phone number</label>
                    <input type="tel" id="aqg-phone-num" class="aqg-input" placeholder="+44 20 3488 6786" autocomplete="tel">
                    <p class="aqg-hint">Scanning offers to call the number straight away.</p>
                </div>
                <div class="aqg-panel" data-aqg-panel="sms" hidden>
                    <div class="aqg-field">
                        <label class="aqg-label" for="aqg-sms-num">Mobile number</label>
                        <input type="tel" id="aqg-sms-num" class="aqg-input" placeholder="+44 7700 900000" autocomplete="tel">
                    </div>
                    <div class="aqg-field">
                        <label class="aqg-label" for="aqg-sms-msg">Message (optional)</label>
                        <input type="text" id="aqg-sms-msg" class="aqg-input" placeholder="Hi! I'd like to book…">
                        <p class="aqg-hint">Scanning opens a text to this number with your message pre-filled.</p>
                    </div>
                </div>
                <div class="aqg-panel" data-aqg-panel="wifi" hidden>
                    <div class="aqg-field">
                        <label class="aqg-label" for="aqg-wifi-ssid">Network name (SSID)</label>
                        <input type="text" id="aqg-wifi-ssid" class="aqg-input" placeholder="Cafe-Guest-WiFi" autocomplete="off">
                    </div>
                    <div class="aqg-field">
                        <label class="aqg-label" for="aqg-wifi-pass">Password</label>
                        <input type="text" id="aqg-wifi-pass" class="aqg-input" placeholder="Wi-Fi password" autocomplete="off">
                    </div>
                    <div class="aqg-field-row">
                        <div class="aqg-field">
                            <label class="aqg-label" for="aqg-wifi-sec">Security</label>
                            <select id="aqg-wifi-sec" class="aqg-input aqg-select">
                                <option value="WPA" selected>WPA / WPA2</option>
                                <option value="WEP">WEP</option>
                                <option value="nopass">None (open network)</option>
                            </select>
                        </div>
                        <div class="aqg-field aqg-check-field">
                            <label class="aqg-check"><input type="checkbox" id="aqg-wifi-hidden"> Hidden network</label>
                        </div>
                    </div>
                    <p class="aqg-hint">Guests scan and join instantly — no typing the password. Great for cafés, salons and waiting rooms.</p>
                </div>
            </div>

            <p class="aqg-error" id="aqg-error" role="alert" hidden></p>

            <div class="aqg-options">
                <div class="aqg-field">
                    <span class="aqg-label">QR colour</span>
                    <div class="aqg-color-row">
                        <input type="color" id="aqg-fg" value="#0f2033" aria-label="QR code colour">
                        <button type="button" class="aqg-swatch" data-aqg-fg="#0f2033" style="background:#0f2033" aria-label="Dark navy"></button>
                        <button type="button" class="aqg-swatch" data-aqg-fg="#000000" style="background:#000000" aria-label="Black"></button>
                        <button type="button" class="aqg-swatch" data-aqg-fg="#10b3fe" style="background:#10b3fe" aria-label="Altus blue"></button>
                        <button type="button" class="aqg-swatch" data-aqg-fg="#7c2d12" style="background:#7c2d12" aria-label="Dark brown"></button>
                    </div>
                </div>
                <div class="aqg-field">
                    <span class="aqg-label">Background colour</span>
                    <div class="aqg-color-row">
                        <input type="color" id="aqg-bg" value="#ffffff" aria-label="Background colour">
                        <button type="button" class="aqg-swatch" data-aqg-bg="#ffffff" style="background:#ffffff" aria-label="White"></button>
                        <button type="button" class="aqg-swatch" data-aqg-bg="#f1f4f8" style="background:#f1f4f8" aria-label="Light grey"></button>
                        <button type="button" class="aqg-swatch" data-aqg-bg="#0f2033" style="background:#0f2033" aria-label="Dark navy"></button>
                        <button type="button" class="aqg-swatch" data-aqg-bg="#000000" style="background:#000000" aria-label="Black"></button>
                    </div>
                </div>
                <div class="aqg-field">
                    <label class="aqg-label" for="aqg-size">Download size (PNG)</label>
                    <select id="aqg-size" class="aqg-input aqg-select">
                        <option value="512">512 px — web</option>
                        <option value="1024" selected>1024 px — crisp print</option>
                        <option value="2048">2048 px — large print</option>
                    </select>
                </div>
                <div class="aqg-field">
                    <label class="aqg-label" for="aqg-ec">Error correction</label>
                    <select id="aqg-ec" class="aqg-input aqg-select">
                        <option value="L">Low — smaller code</option>
                        <option value="M" selected>Medium — recommended</option>
                        <option value="Q">High — survives damage</option>
                        <option value="H">Maximum — for print wear</option>
                    </select>
                </div>
            </div>

            <label class="aqg-check aqg-quiet"><input type="checkbox" id="aqg-quiet" checked> Keep the quiet zone (the white border scanners need)</label>
            <p class="aqg-warn" id="aqg-contrast-warn" role="alert" hidden>⚠️ These two colours are very similar — phones may struggle to scan the code. Pick colours with more contrast.</p>

            <div class="aqg-preview">
                <canvas id="aqg-canvas" class="aqg-canvas" aria-label="QR code preview"></canvas>
                <div class="aqg-preview-side">
                    <p class="aqg-info" id="aqg-info"></p>
                    <div class="aqg-btn-row">
                        <button type="button" class="aqg-btn" id="aqg-dl-png">Download PNG</button>
                        <button type="button" class="aqg-btn aqg-btn-secondary" id="aqg-dl-svg">Download SVG</button>
                    </div>
                    <p class="aqg-hint">SVG stays sharp at any size — best for designers and printers. PNG works everywhere else.</p>
                </div>
            </div>

            <div class="aqg-gate">
                <form id="aqg-send-form" novalidate>
                    <h3>Want it in your inbox too?</h3>
                    <p>We'll email you the QR code as a crisp PNG — handy for your records or to hand to your designer. Plus one follow-up email from Altus. No spam, ever.</p>
                    <div class="aqg-field">
                        <label class="aqg-label" for="aqg-send-name">Your name</label>
                        <input type="text" id="aqg-send-name" class="aqg-input" autocomplete="name">
                        <p class="aqg-error" id="aqg-e-send-name" role="alert" hidden></p>
                    </div>
                    <div class="aqg-field">
                        <label class="aqg-label" for="aqg-send-email">Email address</label>
                        <input type="email" id="aqg-send-email" class="aqg-input" autocomplete="email">
                        <p class="aqg-error" id="aqg-e-send-email" role="alert" hidden></p>
                    </div>
                    <button type="submit" class="aqg-btn" id="aqg-send-btn">Email me my QR code</button>
                </form>
                <div class="aqg-gate-done" id="aqg-send-done" hidden>
                    <div class="aqg-tick">✓</div>
                    <h3>Sent!</h3>
                    <p>Check your inbox — your QR code is on its way. (Don't see it? Check spam, or <a href="mailto:info@altuswebsolutions.com" style="color:#fff">email us</a>.)</p>
                </div>
            </div>

            <p class="aqg-fineprint">Everything above runs entirely in your browser — your links and details never leave your device unless you ask us to email you the QR code.</p>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/* ------------------------------------------------------------------ */
/* AJAX: email the QR PNG + store lead                                 */
/* ------------------------------------------------------------------ */
add_action('wp_ajax_aqg_send_qr', 'aqg_send_qr');
add_action('wp_ajax_nopriv_aqg_send_qr', 'aqg_send_qr');
function aqg_send_qr() {
    if (!check_ajax_referer('aqg_gen', 'nonce', false)) {
        wp_send_json_error('Security check failed. Please reload the page and try again.');
    }

    $name  = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
    $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $png64 = isset($_POST['png']) ? wp_unslash($_POST['png']) : '';

    if (mb_strlen($name) < 2) wp_send_json_error('Please enter your name.');
    if (!is_email($email)) wp_send_json_error('Please enter a valid email address.');

    $png = base64_decode($png64, true);
    if ($png === false || strlen($png) < 100) wp_send_json_error('The QR image was invalid. Please use the download buttons instead.');
    if (strlen($png) > 2 * 1024 * 1024) wp_send_json_error('The image is too large to email. Please use the download buttons instead.');
    // Sanity: must be a PNG (magic bytes 89 50 4E 47 0D 0A 1A 0A)
    if (substr($png, 0, 8) !== "\x89PNG\r\n\x1a\n") wp_send_json_error('The QR image was invalid. Please use the download buttons instead.');

    $settings = aqg_get_settings();

    // Store the lead.
    global $wpdb;
    $wpdb->insert(
        $wpdb->prefix . AQG_TABLE,
        array(
            'name'  => $name,
            'email' => $email,
            'ip'    => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
        ),
        array('%s', '%s', '%s')
    );

    // Attach the PNG.
    $tmp = wp_tempnam('qr-code-') . '.png';
    file_put_contents($tmp, $png);

    $subject = 'Your QR code from Altus Web Solutions';
    $body    = "Hi " . $name . ",\n\n"
        . "Your QR code is attached as a crisp PNG — ready to print, share or hand to your designer.\n\n"
        . "Quick tip: always test-scan a printed QR code with your phone before a big print run. Colours with strong contrast scan most reliably.\n\n"
        . "Need a hand with your website? Book a free 15-minute consultation: " . $settings['book_url'] . "\n\n"
        . "— The Altus Web Solutions team";
    $headers = array('From: ' . $settings['from_name'] . ' <' . $settings['notify_email'] . '>');
    $sent = wp_mail($email, $subject, $body, $headers, array($tmp));

    // Notify Altus about the lead.
    $notify_body = "New QR Code Generator lead:\n\nName: {$name}\nEmail: {$email}\nTime: " . current_time('mysql') . "\n";
    wp_mail($settings['notify_email'], 'New QR code tool lead: ' . $name, $notify_body, $headers);

    @unlink($tmp);

    if (!$sent) wp_send_json_error('Sorry, that did not send. Please use the download buttons above instead.');
    wp_send_json_success();
}
