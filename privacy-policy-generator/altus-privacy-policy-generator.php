<?php
/**
 * Plugin Name: Altus Privacy Policy Generator
 * Description: Free lead-generation tool. Generates a UK GDPR-style privacy policy from a short questionnaire — copy, download, or email it. Leads are stored and emailed to Altus. Draft template only, not legal advice.
 * Version: 1.0.0
 * Author: Altus Web Solutions
 * Text Domain: altus-privacy-policy-generator
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

define('APPG_VERSION', '1.0.0');
define('APPG_PATH', plugin_dir_path(__FILE__));
define('APPG_URL', plugin_dir_url(__FILE__));
define('APPG_TABLE', 'altus_ppg_leads');

/* ------------------------------------------------------------------ */
/* Activation: create the leads table                                 */
/* ------------------------------------------------------------------ */
register_activation_hook(__FILE__, 'appg_activate');
function appg_activate() {
    global $wpdb;
    $table   = $wpdb->prefix . APPG_TABLE;
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

    if (get_option('appg_settings') === false) {
        add_option('appg_settings', array(
            'notify_email' => 'support@altuswebsolutions.com',
            'book_url'     => 'https://altuswebsolutions.com/schedule-free-consultation/',
            'from_name'    => 'Altus Web Solutions',
        ));
    }
}

function appg_get_settings() {
    $defaults = array(
        'notify_email' => 'support@altuswebsolutions.com',
        'book_url'     => 'https://altuswebsolutions.com/schedule-free-consultation/',
        'from_name'    => 'Altus Web Solutions',
    );
    $saved = get_option('appg_settings', array());
    return wp_parse_args(is_array($saved) ? $saved : array(), $defaults);
}

/* ------------------------------------------------------------------ */
/* Frontend assets (only loaded when the shortcode is present)        */
/* ------------------------------------------------------------------ */
add_action('wp_enqueue_scripts', 'appg_enqueue');
function appg_enqueue() {
    if (is_admin()) return;
    global $post;
    if (!($post instanceof WP_Post) || !has_shortcode($post->post_content, 'altus_privacy_policy_generator')) return;

    wp_enqueue_style('appg-gen', APPG_URL . 'assets/generator.css', array(), APPG_VERSION);
    wp_enqueue_script('appg-builder', APPG_URL . 'assets/policy-builder.js', array(), APPG_VERSION, true);
    wp_enqueue_script('appg-gen', APPG_URL . 'assets/generator.js', array('appg-builder'), APPG_VERSION, true);
    wp_localize_script('appg-gen', 'APPG', array(
        'ajax'  => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('appg_gen'),
    ));
}

/* ------------------------------------------------------------------ */
/* Shortcode: renders the generator UI                                */
/* ------------------------------------------------------------------ */
add_shortcode('altus_privacy_policy_generator', 'appg_shortcode');
function appg_shortcode() {
    ob_start();
    ?>
    <div id="appg-root" class="appg-root">
        <div class="appg-progress"><div id="appg-progress-bar"></div></div>
        <div class="appg-dots"><span class="appg-dot active"></span><span class="appg-dot"></span><span class="appg-dot"></span><span class="appg-dot"></span><span class="appg-dot"></span></div>

        <!-- Step 1: about the business -->
        <div class="appg-card" id="appg-s1">
            <div class="appg-badge">Free tool</div>
            <h2 class="appg-title">About your business</h2>
            <p class="appg-sub">First, the basics — these go at the top of your policy so visitors know who is collecting their data.</p>
            <div class="appg-field">
                <label class="appg-label" for="appg-name">Business name</label>
                <input type="text" id="appg-name" class="appg-input" placeholder="e.g. Springfield Plumbing Ltd" autocomplete="organization">
                <p class="appg-error" id="appg-e-name" role="alert" hidden></p>
            </div>
            <div class="appg-field">
                <label class="appg-label" for="appg-type">Business type</label>
                <select id="appg-type" class="appg-select">
                    <option value="limited-company">Limited company</option>
                    <option value="sole-trader">Sole trader</option>
                    <option value="partnership">Partnership</option>
                    <option value="freelancer">Freelancer / independent consultant</option>
                    <option value="charity">Charity / not-for-profit</option>
                </select>
            </div>
            <div class="appg-field">
                <label class="appg-label" for="appg-url">Website address</label>
                <input type="url" id="appg-url" class="appg-input" placeholder="e.g. springfieldplumbing.co.uk" inputmode="url" autocomplete="url">
                <p class="appg-error" id="appg-e-url" role="alert" hidden></p>
            </div>
            <div class="appg-field">
                <label class="appg-label" for="appg-email">Contact email (shown in the policy)</label>
                <input type="email" id="appg-email" class="appg-input" placeholder="e.g. hello@springfieldplumbing.co.uk" autocomplete="email">
                <p class="appg-error" id="appg-e-email" role="alert" hidden></p>
            </div>
            <div class="appg-field">
                <label class="appg-label" for="appg-address">Business address <span class="appg-optional">(optional)</span></label>
                <input type="text" id="appg-address" class="appg-input" placeholder="e.g. 12 High Street, Leeds LS1 4DY" autocomplete="street-address">
            </div>
            <div class="appg-btn-row"><button type="button" class="appg-btn" id="appg-next-1">Continue →</button></div>
        </div>

        <!-- Step 2: data collected -->
        <div class="appg-card" id="appg-s2" hidden>
            <div class="appg-badge">Step 2 of 4</div>
            <h2 class="appg-title">What personal data do you collect?</h2>
            <p class="appg-sub">Tick everything your website or business collects from visitors and customers — be honest, it only affects your own policy.</p>
            <div class="appg-field">
                <div class="appg-checks">
                    <label class="appg-check"><input type="checkbox" name="appg-data" value="names"><span>Full names</span></label>
                    <label class="appg-check"><input type="checkbox" name="appg-data" value="emails"><span>Email addresses</span></label>
                    <label class="appg-check"><input type="checkbox" name="appg-data" value="phones"><span>Phone numbers</span></label>
                    <label class="appg-check"><input type="checkbox" name="appg-data" value="addresses"><span>Postal addresses</span></label>
                    <label class="appg-check"><input type="checkbox" name="appg-data" value="payments"><span>Payment details (cards, billing)</span></label>
                    <label class="appg-check"><input type="checkbox" name="appg-data" value="accounts"><span>Account login details</span></label>
                    <label class="appg-check"><input type="checkbox" name="appg-data" value="enquiries"><span>Messages from contact / enquiry forms</span></label>
                    <label class="appg-check"><input type="checkbox" name="appg-data" value="analytics"><span>Website usage data (pages visited, device)</span></label>
                    <label class="appg-check"><input type="checkbox" name="appg-data" value="marketing_prefs"><span>Marketing preferences (newsletter opt-ins)</span></label>
                </div>
                <p class="appg-error" id="appg-e-data" role="alert" hidden></p>
            </div>
            <div class="appg-btn-row">
                <button type="button" class="appg-btn appg-btn-secondary" id="appg-back-2">← Back</button>
                <button type="button" class="appg-btn" id="appg-next-2">Continue →</button>
            </div>
        </div>

        <!-- Step 3: third parties + cookies -->
        <div class="appg-card" id="appg-s3" hidden>
            <div class="appg-badge">Step 3 of 4</div>
            <h2 class="appg-title">Third parties &amp; cookies</h2>
            <p class="appg-sub">Which outside services touch your visitors' data? These are named in the "who we share data with" section.</p>
            <div class="appg-field">
                <label class="appg-label">Third-party services you use</label>
                <div class="appg-checks">
                    <label class="appg-check"><input type="checkbox" name="appg-tp" value="google-analytics"><span>Google Analytics</span></label>
                    <label class="appg-check"><input type="checkbox" name="appg-tp" value="meta"><span>Meta / Facebook (pixel, embeds)</span></label>
                    <label class="appg-check"><input type="checkbox" name="appg-tp" value="google-maps"><span>Google Maps embeds</span></label>
                    <label class="appg-check"><input type="checkbox" name="appg-tp" value="payment-processors"><span>Payment processors (Stripe, PayPal…)</span></label>
                    <label class="appg-check"><input type="checkbox" name="appg-tp" value="email-marketing"><span>Email marketing (Mailchimp…)</span></label>
                    <label class="appg-check"><input type="checkbox" name="appg-tp" value="hosting"><span>Website hosting provider</span></label>
                    <label class="appg-check"><input type="checkbox" name="appg-tp" value="chat"><span>Live chat / chatbot tool</span></label>
                    <label class="appg-check"><input type="checkbox" name="appg-tp" value="booking"><span>Booking / scheduling tool</span></label>
                    <label class="appg-check"><input type="checkbox" name="appg-tp" value="none"><span>None of these</span></label>
                </div>
            </div>
            <div class="appg-field">
                <label class="appg-label">Does your site set cookies?</label>
                <div class="appg-radio-row">
                    <label class="appg-radio"><input type="radio" name="appg-cookies" value="yes" checked><span>Yes</span></label>
                    <label class="appg-radio"><input type="radio" name="appg-cookies" value="no"><span>No</span></label>
                </div>
            </div>
            <div class="appg-field" id="appg-cookie-types-wrap">
                <label class="appg-label">Which types of cookies?</label>
                <div class="appg-checks">
                    <label class="appg-check"><input type="checkbox" name="appg-cookie-types" value="essential" checked><span>Strictly necessary (site won't work without them)</span></label>
                    <label class="appg-check"><input type="checkbox" name="appg-cookie-types" value="preferences"><span>Preference (remembering visitor choices)</span></label>
                    <label class="appg-check"><input type="checkbox" name="appg-cookie-types" value="analytics"><span>Analytics (measuring site usage)</span></label>
                    <label class="appg-check"><input type="checkbox" name="appg-cookie-types" value="marketing"><span>Marketing / advertising</span></label>
                </div>
            </div>
            <div class="appg-field">
                <label class="appg-label">Do you (or your providers) transfer data outside the UK?</label>
                <div class="appg-radio-row">
                    <label class="appg-radio"><input type="radio" name="appg-transfers" value="no" checked><span>No / not that I know of</span></label>
                    <label class="appg-radio"><input type="radio" name="appg-transfers" value="yes"><span>Yes</span></label>
                </div>
                <p class="appg-hint">Many US-based tools (analytics, email, hosting) process data outside the UK — if in doubt, ask your provider.</p>
            </div>
            <div class="appg-btn-row">
                <button type="button" class="appg-btn appg-btn-secondary" id="appg-back-3">← Back</button>
                <button type="button" class="appg-btn" id="appg-next-3">Continue →</button>
            </div>
        </div>

        <!-- Step 4: retention + children -->
        <div class="appg-card" id="appg-s4" hidden>
            <div class="appg-badge">Step 4 of 4</div>
            <h2 class="appg-title">Keeping data &amp; children's privacy</h2>
            <p class="appg-sub">How long do you hold on to personal data? UK law says no longer than necessary.</p>
            <div class="appg-field">
                <label class="appg-label" for="appg-retention">How long do you keep personal data?</label>
                <select id="appg-retention" class="appg-select">
                    <option value="active-only">Only while they are an active customer / client</option>
                    <option value="active-plus-6y">While active, plus 6 years for tax records (recommended if you take payments)</option>
                    <option value="enquiries-12m">Enquiries only — 12 months after last contact</option>
                    <option value="custom">Custom — I'll describe it</option>
                </select>
            </div>
            <div class="appg-field" id="appg-retention-custom-wrap" hidden>
                <label class="appg-label" for="appg-retention-custom">Describe your retention period</label>
                <textarea id="appg-retention-custom" class="appg-textarea" placeholder="e.g. We keep customer records for 3 years after the last order."></textarea>
            </div>
            <div class="appg-field">
                <label class="appg-label">Do you knowingly collect data from children under 13?</label>
                <div class="appg-radio-row">
                    <label class="appg-radio"><input type="radio" name="appg-children" value="no" checked><span>No</span></label>
                    <label class="appg-radio"><input type="radio" name="appg-children" value="yes"><span>Yes</span></label>
                </div>
            </div>
            <div class="appg-btn-row">
                <button type="button" class="appg-btn appg-btn-secondary" id="appg-back-4">← Back</button>
                <button type="button" class="appg-btn" id="appg-generate">Generate my privacy policy →</button>
            </div>
            <p class="appg-fineprint">The generator produces a <strong>draft template</strong> based on your answers — not legal advice. Always have it reviewed by a solicitor before publishing.</p>
        </div>

        <!-- Step 5: results -->
        <div class="appg-card" id="appg-result" hidden>
            <div class="appg-badge">Your draft is ready</div>
            <h2 class="appg-title">Your privacy policy</h2>
            <p class="appg-sub" id="appg-result-count"></p>
            <div class="appg-doc" id="appg-doc-preview"></div>
            <div class="appg-actions">
                <button type="button" class="appg-btn" id="appg-copy">Copy to clipboard</button>
                <button type="button" class="appg-btn appg-btn-secondary" id="appg-dl-txt">Download .txt</button>
                <button type="button" class="appg-btn appg-btn-secondary" id="appg-dl-html">Download .html</button>
                <button type="button" class="appg-btn appg-btn-secondary" id="appg-edit">← Edit my answers</button>
            </div>
            <div class="appg-gate">
                <form id="appg-send-form" novalidate>
                    <h3>Want a copy in your inbox?</h3>
                    <p>Enter your details and we'll email you this policy as a plain-text file you can keep, plus one follow-up email from Altus Web Solutions. No spam, ever.</p>
                    <div class="appg-field">
                        <label class="appg-label" for="appg-send-name">Your name</label>
                        <input type="text" id="appg-send-name" class="appg-input" autocomplete="name">
                        <p class="appg-error" id="appg-e-send-name" role="alert" hidden></p>
                    </div>
                    <div class="appg-field">
                        <label class="appg-label" for="appg-send-email">Email address</label>
                        <input type="email" id="appg-send-email" class="appg-input" autocomplete="email">
                        <p class="appg-error" id="appg-e-send-email" role="alert" hidden></p>
                    </div>
                    <button type="submit" class="appg-btn" id="appg-send-btn">Email me this policy</button>
                </form>
                <div class="appg-gate-done" id="appg-send-done" hidden>
                    <div class="appg-tick">✓</div>
                    <h3>Sent!</h3>
                    <p>Check your inbox — your privacy policy is on its way. (Don't see it? Check spam, or <a href="mailto:support@altuswebsolutions.com" style="color:#fff">email us</a>.)</p>
                </div>
            </div>
            <p class="appg-fineprint"><strong>Not legal advice.</strong> This is a draft template generated from your answers. Data protection law depends on your specific circumstances — have the policy reviewed by a solicitor before publishing it on your website.</p>
        </div>
    </div>
    <?php
}

/* ------------------------------------------------------------------ */
/* AJAX: email the generated policy + store the lead                   */
/* ------------------------------------------------------------------ */
add_action('wp_ajax_appg_send_policy', 'appg_send_policy');
add_action('wp_ajax_nopriv_appg_send_policy', 'appg_send_policy');

function appg_send_policy() {
    check_ajax_referer('appg_gen', 'nonce');

    $name      = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
    $email     = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $biz       = isset($_POST['business_name']) ? sanitize_text_field(wp_unslash($_POST['business_name'])) : '';
    $site_url  = isset($_POST['site_url']) ? esc_url_raw(wp_unslash($_POST['site_url'])) : '';
    $policy    = isset($_POST['policy']) ? wp_unslash($_POST['policy']) : '';

    if ($name === '' || !is_email($email)) {
        wp_send_json_error(array('message' => 'Please enter your name and a valid email address.'));
    }
    if (strlen($policy) < 200 || strlen($policy) > 120000) {
        wp_send_json_error(array('message' => 'The policy text looks invalid — please regenerate it.'));
    }
    // Strip any HTML the client may have sent; the policy is plain text.
    $policy = wp_strip_all_tags($policy);

    $settings = appg_get_settings();
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';

    global $wpdb;
    $table = $wpdb->prefix . APPG_TABLE;
    $wpdb->insert($table, array(
        'name'          => $name,
        'email'         => $email,
        'business_name' => $biz,
        'site_url'      => $site_url,
        'policy'        => $policy,
        'ip'            => $ip,
    ), array('%s', '%s', '%s', '%s', '%s', '%s'));

    $from_name = $settings['from_name'];
    $headers   = array('Content-Type: text/plain; charset=UTF-8', 'From: ' . $from_name . ' <support@altuswebsolutions.com>');

    // 1) Policy to the visitor.
    $subject_v = 'Your free privacy policy draft — ' . $biz;
    $body_v = "Hi {$name},\n\n"
        . "Here is the privacy policy draft you generated with the Altus Web Solutions free Privacy Policy Generator.\n\n"
        . "================================================================\n"
        . $policy . "\n"
        . "================================================================\n\n"
        . "IMPORTANT: this is a draft template, not legal advice. Please have it reviewed by a solicitor before publishing it on your website.\n\n"
        . "Need help with your website? Book a free 15-minute consultation:\n"
        . $settings['book_url'] . "\n\n"
        . "— The Altus Web Solutions team\nhttps://altuswebsolutions.com";
    $sent_visitor = wp_mail($email, $subject_v, $body_v, $headers);

    // 2) Lead notification to Altus.
    $subject_n = '[Privacy Policy Generator] New lead: ' . $name . ' (' . $biz . ')';
    $body_n = "New privacy policy lead\n\n"
        . "Name: {$name}\nEmail: {$email}\nBusiness: {$biz}\nWebsite: {$site_url}\n"
        . "Date: " . current_time('mysql') . "\nIP: {$ip}\n\n"
        . "The full policy text was emailed to the visitor" . ($sent_visitor ? '' : ' (VISITOR EMAIL FAILED TO SEND — follow up manually)') . ".\n"
        . "Lead stored in table: {$table}.";
    wp_mail($settings['notify_email'], $subject_n, $body_n, $headers);

    if (!$sent_visitor) {
        wp_send_json_error(array('message' => 'We could not send the email right now — please use the copy/download buttons instead.'));
    }
    wp_send_json_success();
}
