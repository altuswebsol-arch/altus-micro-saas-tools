<?php
/**
 * Plugin Name: Altus Cookie Policy Generator
 * Description: Free lead-generation tool. Drafts a UK-style cookie policy from a short questionnaire — copy, download, or email it. Leads are stored and emailed to Altus. Draft template only, not legal advice.
 * Version: 1.0.0
 * Author: Altus Web Solutions
 * Text Domain: altus-cookie-policy-generator
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

define('ACPG_VERSION', '1.0.0');
define('ACPG_PATH', plugin_dir_path(__FILE__));
define('ACPG_URL', plugin_dir_url(__FILE__));
define('ACPG_TABLE', 'altus_cpg_leads');

/* ------------------------------------------------------------------ */
/* Activation: create the leads table                                 */
/* ------------------------------------------------------------------ */
register_activation_hook(__FILE__, 'acpg_activate');
function acpg_activate() {
    global $wpdb;
    $table   = $wpdb->prefix . ACPG_TABLE;
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        name VARCHAR(200) DEFAULT '',
        email VARCHAR(200) DEFAULT '',
        business_name VARCHAR(250) DEFAULT '',
        site_url VARCHAR(500) DEFAULT '',
        policy LONGTEXT,
        ip VARCHAR(45) DEFAULT '',
        PRIMARY KEY (id),
        KEY email (email),
        KEY created_at (created_at)
    ) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    if (get_option('acpg_settings') === false) {
        add_option('acpg_settings', array(
            'notify_email' => 'support@altuswebsolutions.com',
            'book_url'     => 'https://altuswebsolutions.com/schedule-free-consultation/',
            'from_name'    => 'Altus Web Solutions',
        ));
    }
}

function acpg_get_settings() {
    $defaults = array(
        'notify_email' => 'support@altuswebsolutions.com',
        'book_url'     => 'https://altuswebsolutions.com/schedule-free-consultation/',
        'from_name'    => 'Altus Web Solutions',
    );
    $saved = get_option('acpg_settings', array());
    return wp_parse_args(is_array($saved) ? $saved : array(), $defaults);
}

/* Cookie categories — must match assets/cookie-builder.js CATEGORIES. */
function acpg_categories() {
    return array(
        array('key' => 'necessary', 'label' => 'Strictly necessary cookies',
              'desc' => 'Keep the site working — security, load balancing, remembering cookie choices.'),
        array('key' => 'functional', 'label' => 'Functional / preference cookies',
              'desc' => 'Remember choices like language or region for the next visit.'),
        array('key' => 'analytics', 'label' => 'Analytics / performance cookies',
              'desc' => 'Measure visits and traffic (e.g. Google Analytics) to improve the site.'),
        array('key' => 'marketing', 'label' => 'Marketing / advertising cookies',
              'desc' => 'Measure ad campaigns and show relevant ads on other sites.'),
        array('key' => 'embedded', 'label' => 'Embedded third-party content',
              'desc' => 'YouTube videos, maps or social feeds embedded in pages.'),
    );
}

/* ------------------------------------------------------------------ */
/* Frontend assets (only loaded when the shortcode is present)        */
/* ------------------------------------------------------------------ */
add_action('wp_enqueue_scripts', 'acpg_enqueue');
function acpg_enqueue() {
    if (is_admin()) return;
    global $post;
    if (!($post instanceof WP_Post) || !has_shortcode($post->post_content, 'altus_cookie_policy_generator')) return;

    wp_enqueue_style('acpg-gen', ACPG_URL . 'assets/generator.css', array(), ACPG_VERSION);
    wp_enqueue_script('acpg-builder', ACPG_URL . 'assets/cookie-builder.js', array(), ACPG_VERSION, true);
    wp_enqueue_script('acpg-gen', ACPG_URL . 'assets/generator.js', array('acpg-builder'), ACPG_VERSION, true);
    wp_localize_script('acpg-gen', 'ACPG', array(
        'ajax'  => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('acpg_gen'),
    ));
}

/* ------------------------------------------------------------------ */
/* Shortcode: renders the generator UI                                */
/* ------------------------------------------------------------------ */
add_shortcode('altus_cookie_policy_generator', 'acpg_shortcode');
function acpg_shortcode() {
    ob_start();
    ?>
    <div id="acpg-root" class="acpg-root">
        <div class="acpg-progress"><div id="acpg-progress-bar"></div></div>
        <div class="acpg-dots"><span class="acpg-dot active"></span><span class="acpg-dot"></span><span class="acpg-dot"></span></div>

        <!-- Step 1: about the business -->
        <div class="acpg-card" id="acpg-s1">
            <div class="acpg-badge">Free tool</div>
            <h2 class="acpg-title">About your business</h2>
            <p class="acpg-sub">First, the basics — these head up your policy so visitors know whose cookies they're reading about.</p>
            <div class="acpg-field">
                <label class="acpg-label" for="acpg-name">Business name</label>
                <input type="text" id="acpg-name" class="acpg-input" placeholder="e.g. Springfield Plumbing Ltd" autocomplete="organization">
                <p class="acpg-error" id="acpg-e-name" role="alert" hidden></p>
            </div>
            <div class="acpg-field">
                <label class="acpg-label" for="acpg-url">Website address</label>
                <input type="text" id="acpg-url" class="acpg-input" placeholder="e.g. example.co.uk" autocomplete="url" spellcheck="false">
                <p class="acpg-error" id="acpg-e-url" role="alert" hidden></p>
            </div>
            <div class="acpg-field">
                <label class="acpg-label" for="acpg-email">Contact email <span class="acpg-hint" style="display:inline;font-weight:400">— shown in the policy for cookie questions</span></label>
                <input type="email" id="acpg-email" class="acpg-input" placeholder="e.g. hello@example.co.uk" autocomplete="email">
                <p class="acpg-error" id="acpg-e-email" role="alert" hidden></p>
            </div>
            <div class="acpg-btn-row">
                <button type="button" class="acpg-btn" id="acpg-to-s2">Continue →</button>
            </div>
        </div>

        <!-- Step 2: cookies used + consent -->
        <div class="acpg-card" id="acpg-s2" hidden>
            <div class="acpg-badge">Free tool</div>
            <h2 class="acpg-title">What cookies does your site use?</h2>
            <p class="acpg-sub">Tick everything that applies. The policy will only describe what you tick — nothing invented.</p>
            <div class="acpg-check-grid">
                <?php foreach (acpg_categories() as $i => $cat) : ?>
                <label class="acpg-check-card" for="acpg-cat-<?php echo esc_attr($cat['key']); ?>">
                    <input type="checkbox" id="acpg-cat-<?php echo esc_attr($cat['key']); ?>"<?php checked($cat['key'] === 'necessary'); ?>>
                    <span><strong><?php echo esc_html($cat['label']); ?></strong><span><?php echo esc_html($cat['desc']); ?></span></span>
                </label>
                <?php endforeach; ?>
            </div>
            <p class="acpg-error" id="acpg-e-cats" role="alert" hidden></p>

            <div class="acpg-field" style="margin-top:18px">
                <label class="acpg-label" for="acpg-other">Any other named services that set cookies? <span class="acpg-hint" style="display:inline;font-weight:400">(optional)</span></label>
                <input type="text" id="acpg-other" class="acpg-input" placeholder="e.g. our booking system, live chat widget">
                <p class="acpg-hint">Named honestly in the third-party section.</p>
            </div>

            <div class="acpg-field">
                <span class="acpg-label">Do you show visitors a cookie consent banner or use a consent tool?</span>
                <div class="acpg-radio-row">
                    <label class="acpg-radio-card"><input type="radio" name="acpg-consent" value="yes"> Yes</label>
                    <label class="acpg-radio-card"><input type="radio" name="acpg-consent" value="no"> No</label>
                </div>
                <p class="acpg-error" id="acpg-e-consent" role="alert" hidden></p>
            </div>
            <div class="acpg-field">
                <label class="acpg-label" for="acpg-consent-name">Consent tool name <span class="acpg-hint" style="display:inline;font-weight:400">(optional — e.g. CookieYes, Complianz, our own banner)</span></label>
                <input type="text" id="acpg-consent-name" class="acpg-input" placeholder="e.g. CookieYes">
            </div>

            <div class="acpg-btn-row">
                <button type="button" class="acpg-btn acpg-btn-secondary" id="acpg-back-s1">← Back</button>
                <button type="button" class="acpg-btn" id="acpg-generate">Generate my cookie policy →</button>
            </div>
        </div>

        <!-- Step 3: result -->
        <div class="acpg-card" id="acpg-s3" hidden>
            <div class="acpg-badge">Your cookie policy is ready</div>
            <h2 class="acpg-title">Review it, then use it</h2>
            <p class="acpg-sub">Read it through and check every section matches reality. Then copy it onto a dedicated cookie policy page linked from your footer.</p>

            <div class="acpg-preview" id="acpg-preview" aria-live="polite"></div>

            <div class="acpg-actions">
                <button type="button" class="acpg-btn" id="acpg-copy">Copy policy text</button>
                <button type="button" class="acpg-btn acpg-btn-secondary" id="acpg-dl-txt">Download .txt</button>
                <button type="button" class="acpg-btn acpg-btn-secondary" id="acpg-dl-html">Download .html</button>
                <button type="button" class="acpg-btn acpg-btn-secondary" id="acpg-start-over">← Start over</button>
            </div>

            <div class="acpg-gate">
                <form id="acpg-send-form" novalidate>
                    <h3>Want this in your inbox?</h3>
                    <p>We'll email you the policy as a file you can keep — handy for your records or to hand to your web person. Plus one follow-up email from Altus. No spam, ever.</p>
                    <div class="acpg-field">
                        <label class="acpg-label" for="acpg-send-name">Your name</label>
                        <input type="text" id="acpg-send-name" class="acpg-input" autocomplete="name">
                        <p class="acpg-error" id="acpg-e-send-name" role="alert" hidden></p>
                    </div>
                    <div class="acpg-field">
                        <label class="acpg-label" for="acpg-send-email">Email address</label>
                        <input type="email" id="acpg-send-email" class="acpg-input" autocomplete="email">
                        <p class="acpg-error" id="acpg-e-send-email" role="alert" hidden></p>
                    </div>
                    <button type="submit" class="acpg-btn" id="acpg-send-btn">Email me my cookie policy</button>
                </form>
                <div class="acpg-gate-done" id="acpg-send-done" hidden>
                    <div class="acpg-tick">✓</div>
                    <h3>Sent!</h3>
                    <p>Check your inbox — your cookie policy is on its way. (Don't see it? Check spam, or <a href="mailto:support@altuswebsolutions.com" style="color:#fff">email us</a>.)</p>
                </div>
            </div>

            <p class="acpg-fineprint">Your answers are processed entirely in your browser — we never see or store them unless you ask us to email you the policy. This generator produces a draft template, not legal advice.</p>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/* ------------------------------------------------------------------ */
/* AJAX: email the cookie policy (.txt attachment) + store lead        */
/* ------------------------------------------------------------------ */
add_action('wp_ajax_acpg_send_policy', 'acpg_send_policy');
add_action('wp_ajax_nopriv_acpg_send_policy', 'acpg_send_policy');

function acpg_send_policy() {
    check_ajax_referer('acpg_gen', 'nonce');

    $name    = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
    $email   = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $biz     = isset($_POST['business_name']) ? sanitize_text_field(wp_unslash($_POST['business_name'])) : '';
    $site    = isset($_POST['site_url']) ? esc_url_raw(wp_unslash($_POST['site_url'])) : '';
    $policy  = isset($_POST['policy']) ? wp_unslash($_POST['policy']) : '';

    if ($name === '' || !is_email($email)) {
        wp_send_json_error(array('message' => 'Please enter your name and a valid email address.'));
    }
    $policy = trim(strip_tags($policy));
    if (strlen($policy) < 200 || strlen($policy) > 200000) {
        wp_send_json_error(array('message' => 'The policy text looks invalid — please regenerate it.'));
    }
    // The policy must look like our generator output, not arbitrary pasted text.
    if (stripos($policy, 'WHAT COOKIES ARE') === false) {
        wp_send_json_error(array('message' => 'The policy text looks invalid — please regenerate it.'));
    }

    $settings = acpg_get_settings();
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';

    global $wpdb;
    $table = $wpdb->prefix . ACPG_TABLE;
    $wpdb->insert($table, array(
        'name'          => $name,
        'email'         => $email,
        'business_name' => $biz,
        'site_url'      => $site,
        'policy'        => $policy,
        'ip'            => $ip,
    ), array('%s', '%s', '%s', '%s', '%s', '%s'));

    // Attach the policy as a .txt file.
    $slug = sanitize_file_name(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $biz)));
    $slug = trim($slug, '-');
    if ($slug === '') $slug = 'cookie-policy';
    $tmp_txt = wp_tempnam('acpg-policy-') . '.txt';
    $attachments = array();
    if (@file_put_contents($tmp_txt, $policy) !== false) {
        $attachments[] = $tmp_txt;
    }

    $from_name = $settings['from_name'];
    $headers   = array('Content-Type: text/plain; charset=UTF-8', 'From: ' . $from_name . ' <support@altuswebsolutions.com>');

    // 1) Policy to the visitor.
    $subject_v = 'Your cookie policy draft' . ($biz ? ' — ' . $biz : '');
    $body_v = "Hi {$name},\n\n"
        . "Here is the cookie policy draft you generated with the Altus Web Solutions free Cookie Policy Generator.\n\n"
        . "It is attached as a text file. Copy it onto a dedicated cookie policy page on your website,\n"
        . "linked from your footer — and consider having it reviewed before publishing, as it is a\n"
        . "draft template, not legal advice.\n\n"
        . "Want the rest of your website brought up to the same standard? Book a free 15-minute consultation:\n"
        . $settings['book_url'] . "\n\n"
        . "— The Altus Web Solutions team\nhttps://altuswebsolutions.com";
    $sent_visitor = wp_mail($email, $subject_v, $body_v, $headers, $attachments);

    // 2) Lead notification to Altus.
    $subject_n = '[Cookie Policy Generator] New lead: ' . $name . ($biz ? ' (' . $biz . ')' : '');
    $body_n = "New cookie policy lead\n\n"
        . "Name: {$name}\nEmail: {$email}\nBusiness: {$biz}\nSite: {$site}\nDate: " . current_time('mysql') . "\nIP: {$ip}\n\n"
        . "The policy draft was emailed to the visitor" . ($sent_visitor ? '' : ' (VISITOR EMAIL FAILED TO SEND — follow up manually)') . ".\n"
        . "Lead stored in table: {$table}.";
    wp_mail($settings['notify_email'], $subject_n, $body_n, $headers);

    if ($tmp_txt !== '' && file_exists($tmp_txt)) {
        @unlink($tmp_txt);
    }

    if (!$sent_visitor) {
        wp_send_json_error(array('message' => 'We could not send the email right now — please use the copy/download buttons instead.'));
    }
    wp_send_json_success();
}
