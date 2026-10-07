<?php
/**
 * Plugin Name: Altus Review Link Generator
 * Description: Free lead-generation tool. Turns a Google Place ID (or g.page review link) into a one-click review link plus a scannable QR code — copy, download, or have it emailed. Leads are stored and emailed to Altus.
 * Version: 1.0.0
 * Author: Altus Web Solutions
 * Text Domain: altus-review-link-generator
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

define('ARLG_VERSION', '1.0.0');
define('ARLG_PATH', plugin_dir_path(__FILE__));
define('ARLG_URL', plugin_dir_url(__FILE__));
define('ARLG_TABLE', 'altus_rlg_leads');

/* ------------------------------------------------------------------ */
/* Activation: create the leads table                                 */
/* ------------------------------------------------------------------ */
register_activation_hook(__FILE__, 'arlg_activate');
function arlg_activate() {
    global $wpdb;
    $table   = $wpdb->prefix . ARLG_TABLE;
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        name VARCHAR(200) DEFAULT '',
        email VARCHAR(200) DEFAULT '',
        business_name VARCHAR(250) DEFAULT '',
        review_link VARCHAR(1000) DEFAULT '',
        ip VARCHAR(45) DEFAULT '',
        PRIMARY KEY (id),
        KEY email (email),
        KEY created_at (created_at)
    ) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    if (get_option('arlg_settings') === false) {
        add_option('arlg_settings', array(
            'notify_email' => 'support@altuswebsolutions.com',
            'book_url'     => 'https://altuswebsolutions.com/schedule-free-consultation/',
            'from_name'    => 'Altus Web Solutions',
        ));
    }
}

function arlg_get_settings() {
    $defaults = array(
        'notify_email' => 'support@altuswebsolutions.com',
        'book_url'     => 'https://altuswebsolutions.com/schedule-free-consultation/',
        'from_name'    => 'Altus Web Solutions',
    );
    $saved = get_option('arlg_settings', array());
    return wp_parse_args(is_array($saved) ? $saved : array(), $defaults);
}

/* ------------------------------------------------------------------ */
/* Frontend assets (only loaded when the shortcode is present)        */
/* ------------------------------------------------------------------ */
add_action('wp_enqueue_scripts', 'arlg_enqueue');
function arlg_enqueue() {
    if (is_admin()) return;
    global $post;
    if (!($post instanceof WP_Post) || !has_shortcode($post->post_content, 'altus_review_link_generator')) return;

    wp_enqueue_style('arlg-gen', ARLG_URL . 'assets/generator.css', array(), ARLG_VERSION);
    wp_enqueue_script('arlg-qrcode', ARLG_URL . 'assets/qrcode.min.js', array(), '1.0.0', true);
    wp_enqueue_script('arlg-links', ARLG_URL . 'assets/review-links.js', array(), ARLG_VERSION, true);
    wp_enqueue_script('arlg-gen', ARLG_URL . 'assets/generator.js', array('arlg-qrcode', 'arlg-links'), ARLG_VERSION, true);
    wp_localize_script('arlg-gen', 'ARLG', array(
        'ajax'  => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('arlg_gen'),
    ));
}

/* ------------------------------------------------------------------ */
/* Shortcode: renders the generator UI                                */
/* ------------------------------------------------------------------ */
add_shortcode('altus_review_link_generator', 'arlg_shortcode');
function arlg_shortcode() {
    ob_start();
    ?>
    <div id="arlg-root" class="arlg-root">
        <!-- Step 1: input -->
        <div class="arlg-card" id="arlg-s1">
            <div class="arlg-badge">Free tool</div>
            <h2 class="arlg-title">Get your Google review link &amp; QR code</h2>
            <p class="arlg-sub">One link that opens your Google "write a review" box directly — plus a printable QR code for receipts, counters and packaging. Free, no account.</p>

            <div class="arlg-field">
                <label class="arlg-label" for="arlg-bizname">Your business name <span class="arlg-optional">(optional — labels your QR download)</span></label>
                <input type="text" id="arlg-bizname" class="arlg-input" placeholder="e.g. Springfield Plumbing Ltd" autocomplete="organization">
            </div>

            <div class="arlg-field">
                <label class="arlg-label" for="arlg-input">Your Google Place ID — or your existing review link</label>
                <input type="text" id="arlg-input" class="arlg-input" placeholder="e.g. ChIJN1t_tDeuEmsRUsoyG83frY4 — or https://g.page/r/…" autocomplete="off" spellcheck="false">
                <p class="arlg-hint">Paste either your <strong>Google Place ID</strong> or an existing <strong>g.page/r/… review short link</strong> from your Google Business Profile. A full <code>search.google.com/local/writereview?placeid=…</code> URL works too.</p>
                <p class="arlg-error" id="arlg-e-input" role="alert" hidden></p>
            </div>

            <div class="arlg-btn-row">
                <button type="button" class="arlg-btn" id="arlg-generate">Generate my review link →</button>
            </div>

            <div class="arlg-how">
                <button type="button" class="arlg-how-toggle" id="arlg-how-toggle" aria-expanded="false">Don't know your Place ID? Get it in 60 seconds ↓</button>
                <div class="arlg-how-body" id="arlg-how-body" hidden>
                    <ol class="arlg-steps">
                        <li>Open Google's official <a href="https://developers.google.com/maps/documentation/places/web-service/place-id" target="_blank" rel="noopener">Place ID Finder</a> in a new tab.</li>
                        <li>Search for your business name in the map box and click your listing.</li>
                        <li>Copy the <strong>Place ID</strong> shown under your business name — paste it above.</li>
                    </ol>
                </div>
            </div>
        </div>

        <!-- Step 2: results -->
        <div class="arlg-card" id="arlg-result" hidden>
            <div class="arlg-badge">Your review pack is ready</div>
            <h2 class="arlg-title">Share this with your customers</h2>
            <p class="arlg-note" id="arlg-mode-note" hidden></p>

            <p class="arlg-linklabel">One-click review link — opens your Google review box directly</p>
            <div class="arlg-linkbox"><a id="arlg-link-out" href="#" target="_blank" rel="noopener"></a></div>

            <div id="arlg-maps-wrap" hidden>
                <p class="arlg-linklabel">Backup Maps link — opens your business listing</p>
                <div class="arlg-linkbox"><a id="arlg-maps-out" href="#" target="_blank" rel="noopener"></a></div>
            </div>

            <div class="arlg-qr-row">
                <div class="arlg-qr-frame">
                    <div id="arlg-qr" role="img" aria-label="QR code linking to the Google review page"></div>
                    <p class="arlg-qr-caption">Scan to test it with your phone</p>
                </div>
                <div class="arlg-qr-opts">
                    <p class="arlg-label">Match the QR code to your branding</p>
                    <div class="arlg-color-row">
                        <label class="arlg-color-field">Code colour
                            <input type="color" id="arlg-qr-dark" value="#111111">
                        </label>
                        <label class="arlg-color-field">Background
                            <input type="color" id="arlg-qr-light" value="#ffffff">
                        </label>
                    </div>
                    <button type="button" class="arlg-btn arlg-btn-secondary" id="arlg-qr-reset">Reset colours</button>
                </div>
            </div>

            <div class="arlg-actions">
                <button type="button" class="arlg-btn" id="arlg-copy-link">Copy review link</button>
                <button type="button" class="arlg-btn arlg-btn-secondary" id="arlg-copy-maps">Copy maps link</button>
                <button type="button" class="arlg-btn arlg-btn-secondary" id="arlg-dl-qr">Download QR PNG</button>
                <button type="button" class="arlg-btn arlg-btn-secondary" id="arlg-start-over">← Start over</button>
            </div>

            <div class="arlg-gate">
                <form id="arlg-send-form" novalidate>
                    <h3>Want this in your inbox?</h3>
                    <p>We'll email you the review link plus your QR code as a printable PNG — handy for the till, packaging or your email signature. Plus one follow-up email from Altus. No spam, ever.</p>
                    <div class="arlg-field">
                        <label class="arlg-label" for="arlg-send-name">Your name</label>
                        <input type="text" id="arlg-send-name" class="arlg-input" autocomplete="name">
                        <p class="arlg-error" id="arlg-e-send-name" role="alert" hidden></p>
                    </div>
                    <div class="arlg-field">
                        <label class="arlg-label" for="arlg-send-email">Email address</label>
                        <input type="email" id="arlg-send-email" class="arlg-input" autocomplete="email">
                        <p class="arlg-error" id="arlg-e-send-email" role="alert" hidden></p>
                    </div>
                    <button type="submit" class="arlg-btn" id="arlg-send-btn">Email me my review pack</button>
                </form>
                <div class="arlg-gate-done" id="arlg-send-done" hidden>
                    <div class="arlg-tick">✓</div>
                    <h3>Sent!</h3>
                    <p>Check your inbox — your review link and QR code are on their way. (Don't see it? Check spam, or <a href="mailto:support@altuswebsolutions.com" style="color:#fff">email us</a>.)</p>
                </div>
            </div>

            <p class="arlg-fineprint">Your review link is generated entirely in your browser — we never see or store your Place ID unless you ask us to email you your pack.</p>
        </div>
    </div>
    <?php
}

/* ------------------------------------------------------------------ */
/* AJAX: email the review pack (link + QR PNG attachment) + store lead */
/* ------------------------------------------------------------------ */
add_action('wp_ajax_arlg_send_pack', 'arlg_send_pack');
add_action('wp_ajax_nopriv_arlg_send_pack', 'arlg_send_pack');

function arlg_send_pack() {
    check_ajax_referer('arlg_gen', 'nonce');

    $name      = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
    $email     = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $biz       = isset($_POST['business_name']) ? sanitize_text_field(wp_unslash($_POST['business_name'])) : '';
    $link      = isset($_POST['review_link']) ? esc_url_raw(wp_unslash($_POST['review_link'])) : '';
    $qr_data   = isset($_POST['qr_png']) ? wp_unslash($_POST['qr_png']) : '';

    if ($name === '' || !is_email($email)) {
        wp_send_json_error(array('message' => 'Please enter your name and a valid email address.'));
    }
    // The review link must be a Google review URL we would generate ourselves.
    if (!preg_match('#^https://(search\.google\.com/local/writereview\?placeid=|www\.google\.com/maps/[^\s]*|g\.page/r/)[A-Za-z0-9_%?&=\-_.:/]*$#', $link)) {
        wp_send_json_error(array('message' => 'The review link looks invalid — please regenerate it.'));
    }

    $settings = arlg_get_settings();
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';

    global $wpdb;
    $table = $wpdb->prefix . ARLG_TABLE;
    $wpdb->insert($table, array(
        'name'          => $name,
        'email'         => $email,
        'business_name' => $biz,
        'review_link'   => $link,
        'ip'            => $ip,
    ), array('%s', '%s', '%s', '%s', '%s'));

    // Decode the QR PNG the browser rendered; validate it is really a PNG.
    $attachments = array();
    $tmp_qr = '';
    if (preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', trim($qr_data), $m)) {
        $raw = base64_decode($m[1], true);
        if ($raw !== false && strlen($raw) > 100 && substr($raw, 0, 8) === "\x89PNG\r\n\x1a\n" && strlen($raw) < 2000000) {
            $tmp_qr = wp_tempnam('arlg-qr-') . '.png';
            if (@file_put_contents($tmp_qr, $raw) !== false) {
                $attachments[] = $tmp_qr;
            }
        }
    }

    $from_name = $settings['from_name'];
    $headers   = array('Content-Type: text/plain; charset=UTF-8', 'From: ' . $from_name . ' <support@altuswebsolutions.com>');

    // 1) Review pack to the visitor.
    $subject_v = 'Your Google review link + QR code' . ($biz ? ' — ' . $biz : '');
    $body_v = "Hi {$name},\n\n"
        . "Here is the Google review pack you generated with the Altus Web Solutions free Review Link Generator.\n\n"
        . "YOUR ONE-CLICK REVIEW LINK:\n{$link}\n\n"
        . "Share it in follow-up emails, text messages, your email signature or on social media —\n"
        . "it opens your Google \"write a review\" box directly.\n\n"
        . "YOUR QR CODE:\nAttached as a PNG (printable). Put it on receipts, packaging, table tents\n"
        . "or the counter — customers scan it with their phone camera and land straight on your review page.\n\n"
        . "A quick tip: the best moment to ask for a review is right after a happy customer says so.\n\n"
        . "Want more customers finding you on Google? Book a free 15-minute consultation:\n"
        . $settings['book_url'] . "\n\n"
        . "— The Altus Web Solutions team\nhttps://altuswebsolutions.com";
    $sent_visitor = wp_mail($email, $subject_v, $body_v, $headers, $attachments);

    // 2) Lead notification to Altus.
    $subject_n = '[Review Link Generator] New lead: ' . $name . ($biz ? ' (' . $biz . ')' : '');
    $body_n = "New review link lead\n\n"
        . "Name: {$name}\nEmail: {$email}\nBusiness: {$biz}\n"
        . "Review link: {$link}\nDate: " . current_time('mysql') . "\nIP: {$ip}\n\n"
        . "The review pack was emailed to the visitor" . ($sent_visitor ? '' : ' (VISITOR EMAIL FAILED TO SEND — follow up manually)') . ".\n"
        . "Lead stored in table: {$table}.";
    wp_mail($settings['notify_email'], $subject_n, $body_n, $headers);

    if ($tmp_qr !== '' && file_exists($tmp_qr)) {
        @unlink($tmp_qr);
    }

    if (!$sent_visitor) {
        wp_send_json_error(array('message' => 'We could not send the email right now — please use the copy/download buttons instead.'));
    }
    wp_send_json_success();
}
