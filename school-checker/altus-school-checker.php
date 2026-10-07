<?php
/**
 * Plugin Name: Altus School Website Compliance Checker
 * Description: Free lead-generation tool. Checks any UK school website against DfE/Ofsted publishing requirements, shows a free score + top 3 issues, and unlocks the full report by email. Leads are stored and emailed to Altus.
 * Version: 1.0.3
 * Author: Altus Web Solutions
 * Text Domain: altus-school-checker
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

define('ASC_VERSION', '1.0.3');
define('ASC_PATH', plugin_dir_path(__FILE__));
define('ASC_URL', plugin_dir_url(__FILE__));
define('ASC_TABLE', 'altus_sc_leads');

require_once ASC_PATH . 'includes/checker.php';
require_once ASC_PATH . 'includes/admin.php';

/* ------------------------------------------------------------------ */
/* Activation: create the leads table                                 */
/* ------------------------------------------------------------------ */
register_activation_hook(__FILE__, 'asc_activate');
function asc_activate() {
    global $wpdb;
    $table   = $wpdb->prefix . ASC_TABLE;
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        name VARCHAR(200) DEFAULT '',
        email VARCHAR(200) DEFAULT '',
        phone VARCHAR(50) DEFAULT '',
        school_name VARCHAR(250) DEFAULT '',
        school_url VARCHAR(500) DEFAULT '',
        score TINYINT UNSIGNED DEFAULT 0,
        grade CHAR(2) DEFAULT '',
        report LONGTEXT,
        ip VARCHAR(45) DEFAULT '',
        PRIMARY KEY (id),
        KEY email (email),
        KEY created_at (created_at)
    ) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    if (get_option('asc_settings') === false) {
        add_option('asc_settings', array(
            'notify_email' => 'support@altuswebsolutions.com',
            'book_url'     => 'https://altuswebsolutions.com/schedule-free-consultation/',
            'from_name'    => 'Altus Web Solutions',
            'max_pages'    => 15,
        ));
    }
    update_option('asc_db_version', ASC_VERSION);
}

/* Upgrade path for already-installed copies (e.g. 1.0.2 -> 1.0.3 adds the phone column). */
add_action('plugins_loaded', 'asc_maybe_upgrade');
function asc_maybe_upgrade() {
    $done = get_option('asc_db_version', '1.0.0');
    if (version_compare($done, ASC_VERSION, '>=')) return;
    global $wpdb;
    $table = $wpdb->prefix . ASC_TABLE;
    $col = $wpdb->get_results("SHOW COLUMNS FROM `$table` LIKE 'phone'");
    if (empty($col)) {
        $wpdb->query("ALTER TABLE `$table` ADD COLUMN phone VARCHAR(50) DEFAULT '' AFTER email");
    }
    update_option('asc_db_version', ASC_VERSION);
}

function asc_get_settings() {
    $defaults = array(
        'notify_email' => 'support@altuswebsolutions.com',
        'book_url'     => 'https://altuswebsolutions.com/schedule-free-consultation/',
        'from_name'    => 'Altus Web Solutions',
        'max_pages'    => 15,
    );
    $saved = get_option('asc_settings', array());
    return wp_parse_args(is_array($saved) ? $saved : array(), $defaults);
}

/* ------------------------------------------------------------------ */
/* Frontend assets (only loaded when the shortcode is present)        */
/* ------------------------------------------------------------------ */
add_action('wp_enqueue_scripts', 'asc_enqueue');
function asc_enqueue() {
    if (is_admin()) return;
    global $post;
    if (!($post instanceof WP_Post) || !has_shortcode($post->post_content, 'altus_school_checker')) return;

    wp_enqueue_style('asc-checker', ASC_URL . 'assets/checker.css', array(), ASC_VERSION);
    wp_enqueue_script('asc-checker', ASC_URL . 'assets/checker.js', array(), ASC_VERSION, true);
    wp_localize_script('asc-checker', 'ASC', array(
        'ajax'  => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('asc_checker'),
    ));
}

/* ------------------------------------------------------------------ */
/* Shortcode: renders the checker UI                                   */
/* ------------------------------------------------------------------ */
add_shortcode('altus_school_checker', 'asc_shortcode');
function asc_shortcode() {
    ob_start();
    ?>
    <div id="asc-root" class="asc-root">
        <!-- Step 1: URL entry -->
        <div class="asc-card" id="asc-step-url">
            <div class="asc-badge">Free tool</div>
            <h2 class="asc-title">Is your school website legally compliant?</h2>
            <p class="asc-sub">UK schools must publish specific information online under DfE and Ofsted rules. Enter your school website below and we will check it in under a minute.</p>
            <form id="asc-url-form" novalidate>
                <label class="asc-label" for="asc-url">School website address</label>
                <input type="url" id="asc-url" name="url" class="asc-input" placeholder="https://www.yourschool.sch.uk" required inputmode="url" autocomplete="url">
                <label class="asc-label" for="asc-school-name">School name <span class="asc-optional">(optional)</span></label>
                <input type="text" id="asc-school-name" name="school_name" class="asc-input" placeholder="e.g. Springfield Primary School" autocomplete="organization">
                <button type="submit" class="asc-btn" id="asc-run-btn"><span class="asc-btn-text">Run free check</span></button>
                <p class="asc-error" id="asc-url-error" role="alert" hidden></p>
            </form>
            <p class="asc-fineprint">We only fetch publicly available pages. No login or private data is accessed. This is an automated guide based on the DfE’s published guidance for maintained schools in England — not legal advice, and requirements can differ by school type.</p>
        </div>

        <!-- Step 2: scanning progress -->
        <div class="asc-card" id="asc-step-scan" hidden>
            <div class="asc-spinner" aria-hidden="true"></div>
            <h2 class="asc-title">Checking your website…</h2>
            <p class="asc-sub" id="asc-scan-status">Loading homepage…</p>
            <div class="asc-progress"><div class="asc-progress-bar" id="asc-progress-bar"></div></div>
            <p class="asc-fineprint">Scanning up to 15 pages against 22 DfE/Ofsted requirements.</p>
        </div>

        <!-- Step 3: free results (score + top 3 issues) -->
        <div class="asc-card" id="asc-step-result" hidden>
            <div class="asc-score-wrap">
                <div class="asc-score-ring" id="asc-score-ring"><span id="asc-score-num">0</span><span class="asc-score-pct">%</span></div>
                <div>
                    <div class="asc-grade" id="asc-grade">–</div>
                    <p class="asc-sub" id="asc-result-summary"></p>
                </div>
            </div>
            <h3 class="asc-h3">Top 3 issues found</h3>
            <ul class="asc-issues" id="asc-top-issues"></ul>
            <div class="asc-unlock">
                <h3 class="asc-h3">Unlock your full detailed report</h3>
                <p class="asc-sub">All 22 checks, plain-English explanations, and exactly what to fix — shown here and emailed to you.</p>
                <form id="asc-unlock-form" novalidate>
                    <label class="asc-label" for="asc-name">Your name</label>
                    <input type="text" id="asc-name" name="name" class="asc-input" required autocomplete="name" placeholder="Your full name">
                    <label class="asc-label" for="asc-email">Your email</label>
                    <input type="email" id="asc-email" name="email" class="asc-input" required autocomplete="email" placeholder="you@yourschool.sch.uk">
                    <label class="asc-label" for="asc-phone">Your phone number</label>
                    <input type="tel" id="asc-phone" name="phone" class="asc-input" required autocomplete="tel" inputmode="tel" placeholder="e.g. 07123 456789">
                    <button type="submit" class="asc-btn" id="asc-unlock-btn"><span class="asc-btn-text">Email me the full report</span></button>
                    <p class="asc-error" id="asc-unlock-error" role="alert" hidden></p>
                </form>
                <p class="asc-fineprint">We will only use your details to send this report and call you about the results. No spam, ever. See our <a href="https://altuswebsolutions.com/privacy-policy/" target="_blank" rel="noopener">privacy policy</a>.</p>
            </div>
        </div>

        <!-- Step 4: full report -->
        <div class="asc-card" id="asc-step-report" hidden>
            <div class="asc-report-head">
                <div class="asc-score-ring asc-score-ring-sm" id="asc-score-ring-2"><span id="asc-score-num-2">0</span><span class="asc-score-pct">%</span></div>
                <div>
                    <h2 class="asc-title" style="margin:0">Your full compliance report</h2>
                    <p class="asc-sub" id="asc-report-meta" style="margin:.25rem 0 0"></p>
                </div>
            </div>
            <p class="asc-sub" id="asc-report-note"></p>
            <div class="asc-table-wrap">
                <table class="asc-table" id="asc-report-table">
                    <thead><tr><th>Requirement</th><th>Status</th><th>Why it matters</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="asc-cta">
                <h3 class="asc-h3">Want these fixed?</h3>
                <p class="asc-sub">We build compliant, high-performing school websites. Book a free 15-minute consultation and we will talk through your report.</p>
                <a class="asc-btn asc-btn-link" id="asc-book-link" href="https://altuswebsolutions.com/schedule-free-consultation/" target="_blank" rel="noopener">Book a free 15-min consultation</a>
                <p class="asc-fineprint">By Altus Web Solutions — no obligation.</p>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/* ------------------------------------------------------------------ */
/* AJAX: run the check (step 1 -> step 3)                              */
/* ------------------------------------------------------------------ */
add_action('wp_ajax_asc_run', 'asc_ajax_run');
add_action('wp_ajax_nopriv_asc_run', 'asc_ajax_run');
function asc_ajax_run() {
    check_ajax_referer('asc_checker', 'nonce');

    // Rate limit: 5 checks per IP per hour.
    $ip  = asc_client_ip();
    $key = 'asc_rl_' . md5($ip);
    $hits = (int) get_transient($key);
    if ($hits >= 5) {
        wp_send_json_error(array('message' => 'Too many checks from this address. Please try again later.'));
    }
    set_transient($key, $hits + 1, HOUR_IN_SECONDS);

    $url         = isset($_POST['url']) ? trim(wp_unslash($_POST['url'])) : '';
    $school_name = isset($_POST['school_name']) ? sanitize_text_field(wp_unslash($_POST['school_name'])) : '';

    $url = asc_normalize_url($url);
    if (!$url) {
        wp_send_json_error(array('message' => 'Please enter a valid website address, e.g. https://www.yourschool.sch.uk'));
    }
    if (!asc_url_allowed($url)) {
        wp_send_json_error(array('message' => 'That address cannot be checked (private or invalid host). Please use the public school website.'));
    }

    $settings = asc_get_settings();
    @set_time_limit(120);

    $checker = new Altus_SC_Checker();
    $result  = $checker->run($url, (int) $settings['max_pages']);

    if (!empty($result['fatal'])) {
        wp_send_json_error(array('message' => $result['fatal']));
    }

    // Stash the full result for the unlock step (10 minutes).
    $token = wp_generate_password(32, false);
    set_transient('asc_result_' . $token, array(
        'url'         => $url,
        'school_name' => $school_name,
        'result'      => $result,
        'ip'          => $ip,
    ), 10 * MINUTE_IN_SECONDS);

    // Top 3 issues = first 3 non-FOUND items, worst first.
    $issues = array();
    foreach ($result['items'] as $item) {
        if ($item['status'] !== 'found') $issues[] = $item;
        if (count($issues) === 3) break;
    }

    wp_send_json_success(array(
        'token'   => $token,
        'score'   => $result['score'],
        'grade'   => $result['grade'],
        'summary' => $result['summary'],
        'issues'  => array_map(function ($i) {
            return array('label' => $i['label'], 'status' => $i['status'], 'why' => $i['why']);
        }, $issues),
    ));
}

/* ------------------------------------------------------------------ */
/* AJAX: unlock full report (step 3 -> step 4), save lead, send emails */
/* ------------------------------------------------------------------ */
add_action('wp_ajax_asc_unlock', 'asc_ajax_unlock');
add_action('wp_ajax_nopriv_asc_unlock', 'asc_ajax_unlock');
function asc_ajax_unlock() {
    check_ajax_referer('asc_checker', 'nonce');

    $token = isset($_POST['token']) ? sanitize_key($_POST['token']) : '';
    $data  = get_transient('asc_result_' . $token);
    if (!$data) {
        wp_send_json_error(array('message' => 'Your check expired. Please run the check again.'));
    }

    $name  = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
    $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $phone_raw = isset($_POST['phone']) ? wp_unslash($_POST['phone']) : '';
    $phone_digits = preg_replace('/\D+/', '', $phone_raw);
    $phone = sanitize_text_field($phone_raw);
    if ($name === '' || !is_email($email) || strlen($phone_digits) < 7) {
        wp_send_json_error(array('message' => 'Please enter your name, a valid email address, and a phone number we can reach you on.'));
    }

    $result   = $data['result'];
    $settings = asc_get_settings();

    // Save the lead.
    global $wpdb;
    $table = $wpdb->prefix . ASC_TABLE;
    $wpdb->insert($table, array(
        'name'        => $name,
        'email'       => $email,
        'phone'       => $phone,
        'school_name' => $data['school_name'],
        'school_url'  => $data['url'],
        'score'       => $result['score'],
        'grade'       => $result['grade'],
        'report'      => wp_json_encode($result['items']),
        'ip'          => $data['ip'],
    ), array('%s','%s','%s','%s','%s','%d','%s','%s','%s'));

    // Email the full report to the visitor.
    $report_html = asc_report_email_html($name, $data, $result, $settings);
    $headers = array('Content-Type: text/html; charset=UTF-8',
                     'From: ' . $settings['from_name'] . ' <' . $settings['notify_email'] . '>');
    wp_mail($email, 'Your school website compliance report — ' . $result['score'] . '%', $report_html, $headers);

    // Notify Altus of the new lead.
    $notify_body = sprintf(
        "<p><strong>New School Checker lead</strong></p><p>Name: %s<br>Phone: %s<br>Email: %s<br>School: %s<br>URL: %s<br>Score: %d%% (%s)<br>Date: %s</p><p><a href=\"%s\">Book a consultation</a></p>",
        esc_html($name), esc_html($phone), esc_html($email), esc_html($data['school_name']),
        esc_url($data['url']), (int) $result['score'], esc_html($result['grade']),
        esc_html(current_time('mysql')), esc_url($settings['book_url'])
    );
    wp_mail($settings['notify_email'], 'New lead: School Checker — ' . $name . ' (' . $result['score'] . '%)', $notify_body, $headers);

    delete_transient('asc_result_' . $token);

    wp_send_json_success(array(
        'items'    => $result['items'],
        'score'    => $result['score'],
        'grade'    => $result['grade'],
        'url'      => $data['url'],
        'note'     => $result['note'],
        'book_url' => $settings['book_url'],
    ));
}

/* ------------------------------------------------------------------ */
/* Helpers                                                            */
/* ------------------------------------------------------------------ */
function asc_client_ip() {
    foreach (array('HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR') as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = trim(explode(',', $_SERVER[$k])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}

function asc_normalize_url($url) {
    $url = trim($url);
    if ($url === '') return false;
    if (!preg_match('#^https?://#i', $url)) $url = 'https://' . $url;
    $parts = wp_parse_url($url);
    if (empty($parts['host'])) return false;
    // Rebuild cleanly to avoid userinfo / weird ports.
    $host = strtolower($parts['host']);
    $path = isset($parts['path']) ? $parts['path'] : '/';
    return 'https://' . $host . $path;
}

/** SSRF guard: block private/reserved IP ranges after DNS resolution. */
function asc_url_allowed($url) {
    $host = wp_parse_url($url, PHP_URL_HOST);
    if (!$host) return false;
    // Fast path: literal IP.
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return asc_ip_public($host);
    }
    $ip = gethostbyname($host);
    if ($ip === $host) return false; // DNS failed
    return asc_ip_public($ip);
}

function asc_ip_public($ip) {
    if (!filter_var($ip, FILTER_VALIDATE_IP)) return false;
    // Reject private, reserved, loopback, link-local, multicast.
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
}

/** Build the visitor's full-report email (HTML). */
function asc_report_email_html($name, $data, $result, $settings) {
    $rows = '';
    foreach ($result['items'] as $item) {
        $badge = $item['status'] === 'found' ? 'Found' : ($item['status'] === 'uncertain' ? 'Check manually' : 'Not found');
        $color = $item['status'] === 'found' ? '#17803d' : ($item['status'] === 'uncertain' ? '#b26a00' : '#c0392b');
        $tag = (!isset($item['statutory']) || $item['statutory']) ? '' : ' <span style="font-size:11px;color:#666;border:1px solid #ccc;border-radius:4px;padding:1px 6px;font-weight:normal">Recommended</span>';
        $rows .= sprintf(
            '<tr><td style="padding:8px;border-bottom:1px solid #eee">%s%s</td><td style="padding:8px;border-bottom:1px solid #eee;color:%s;font-weight:bold">%s</td><td style="padding:8px;border-bottom:1px solid #eee;color:#555">%s</td></tr>',
            esc_html($item['label']), $tag, $color, $badge, esc_html($item['why'])
        );
    }
    return sprintf(
        '<div style="font-family:Arial,sans-serif;max-width:640px;margin:0 auto;color:#1a1a1a">'
        . '<h2 style="color:#1450b8">Your school website compliance report</h2>'
        . '<p>Hi %s,</p><p>Here is the full breakdown for <strong>%s</strong> (%s).</p>'
        . '<p style="font-size:28px;font-weight:bold">Score: %d%% <span style="font-size:18px;color:#1450b8">(%s)</span></p>'
        . '<table style="width:100%%;border-collapse:collapse;font-size:14px"><thead><tr><th style="text-align:left;padding:8px;border-bottom:2px solid #1450b8">Requirement</th><th style="text-align:left;padding:8px;border-bottom:2px solid #1450b8">Status</th><th style="text-align:left;padding:8px;border-bottom:2px solid #1450b8">Why it matters</th></tr></thead><tbody>%s</tbody></table>'
        . '<p>%s</p>'
        . '<p><a href="%s" style="display:inline-block;background:#1450b8;color:#fff;padding:12px 24px;text-decoration:none;border-radius:6px;font-weight:bold">Book a free 15-min consultation</a></p>'
        . '<p style="color:#888;font-size:12px">Report by Altus Web Solutions. This report is an automated guide based on the DfE’s published guidance for maintained schools in England — it is not legal advice, and requirements can differ by school type. “Check manually” means our automated scan could not verify the item — please review it on your site directly. “Not found” means our scan could not find it in the pages and linked documents it could read. Items marked “Recommended” are DfE good practice rather than statutory requirements.</p></div>',
        esc_html($name), esc_html($data['school_name'] ?: $data['url']), esc_html($data['url']),
        (int) $result['score'], esc_html($result['grade']), $rows,
        esc_html($result['summary']), esc_url($settings['book_url'])
    );
}
