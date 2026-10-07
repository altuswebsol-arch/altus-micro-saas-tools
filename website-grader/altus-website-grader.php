<?php
/**
 * Plugin Name: Altus Website Grader
 * Description: Free lead-generation tool. Grades any small-business website on speed, SEO, mobile and trust signals, shows a free score + top 3 issues, and unlocks the full report by email. Leads are stored and emailed to Altus.
 * Version: 1.0.0
 * Author: Altus Web Solutions
 * Text Domain: altus-website-grader
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

define('AWG_VERSION', '1.0.0');
define('AWG_PATH', plugin_dir_path(__FILE__));
define('AWG_URL', plugin_dir_url(__FILE__));
define('AWG_TABLE', 'altus_wg_leads');

require_once AWG_PATH . 'includes/grader.php';

/* ------------------------------------------------------------------ */
/* Activation: create the leads table                                 */
/* ------------------------------------------------------------------ */
register_activation_hook(__FILE__, 'awg_activate');
function awg_activate() {
    global $wpdb;
    $table   = $wpdb->prefix . AWG_TABLE;
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        name VARCHAR(200) DEFAULT '',
        email VARCHAR(200) DEFAULT '',
        business_name VARCHAR(250) DEFAULT '',
        site_url VARCHAR(500) DEFAULT '',
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

    if (get_option('awg_settings') === false) {
        add_option('awg_settings', array(
            'notify_email' => 'support@altuswebsolutions.com',
            'book_url'     => 'https://altuswebsolutions.com/schedule-free-consultation/',
            'from_name'    => 'Altus Web Solutions',
        ));
    }
}

function awg_get_settings() {
    $defaults = array(
        'notify_email' => 'support@altuswebsolutions.com',
        'book_url'     => 'https://altuswebsolutions.com/schedule-free-consultation/',
        'from_name'    => 'Altus Web Solutions',
    );
    $saved = get_option('awg_settings', array());
    return wp_parse_args(is_array($saved) ? $saved : array(), $defaults);
}

/* ------------------------------------------------------------------ */
/* Frontend assets (only loaded when the shortcode is present)        */
/* ------------------------------------------------------------------ */
add_action('wp_enqueue_scripts', 'awg_enqueue');
function awg_enqueue() {
    if (is_admin()) return;
    global $post;
    if (!($post instanceof WP_Post) || !has_shortcode($post->post_content, 'altus_website_grader')) return;

    wp_enqueue_style('awg-grader', AWG_URL . 'assets/grader.css', array(), AWG_VERSION);
    wp_enqueue_script('awg-grader', AWG_URL . 'assets/grader.js', array(), AWG_VERSION, true);
    wp_localize_script('awg-grader', 'AWG', array(
        'ajax'  => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('awg_grader'),
    ));
}

/* ------------------------------------------------------------------ */
/* Shortcode: renders the grader UI                                   */
/* ------------------------------------------------------------------ */
add_shortcode('altus_website_grader', 'awg_shortcode');
function awg_shortcode() {
    ob_start();
    ?>
    <div id="awg-root" class="awg-root">
        <!-- Step 1: URL entry -->
        <div class="awg-card" id="awg-step-url">
            <div class="awg-badge">Free tool</div>
            <h2 class="awg-title">How does your website score?</h2>
            <p class="awg-sub">Enter your website address below and we will grade your homepage on speed, SEO, mobile-friendliness and trust signals — in under a minute.</p>
            <form id="awg-url-form" novalidate>
                <label class="awg-label" for="awg-url">Your website address</label>
                <input type="url" id="awg-url" name="url" class="awg-input" placeholder="https://www.yourbusiness.co.uk" required inputmode="url" autocomplete="url">
                <label class="awg-label" for="awg-business-name">Business name <span class="awg-optional">(optional)</span></label>
                <input type="text" id="awg-business-name" name="business_name" class="awg-input" placeholder="e.g. Springfield Plumbing" autocomplete="organization">
                <button type="submit" class="awg-btn" id="awg-run-btn"><span class="awg-btn-text">Grade my website</span></button>
                <p class="awg-error" id="awg-url-error" role="alert" hidden></p>
            </form>
            <p class="awg-fineprint">We only fetch your public homepage. No login or private data is accessed. This is an automated homepage-based grading — not a full Google PageSpeed audit.</p>
        </div>

        <!-- Step 2: scanning progress -->
        <div class="awg-card" id="awg-step-scan" hidden>
            <div class="awg-spinner" aria-hidden="true"></div>
            <h2 class="awg-title">Grading your website…</h2>
            <p class="awg-sub" id="awg-scan-status">Loading homepage…</p>
            <div class="awg-progress"><div class="awg-progress-bar" id="awg-progress-bar"></div></div>
            <p class="awg-fineprint">Measuring 15 speed, SEO, mobile and trust signals.</p>
        </div>

        <!-- Step 3: free results (score + top 3 issues) -->
        <div class="awg-card" id="awg-step-result" hidden>
            <div class="awg-score-wrap">
                <div class="awg-score-ring" id="awg-score-ring"><span id="awg-score-num">0</span><span class="awg-score-pct">%</span></div>
                <div>
                    <div class="awg-grade" id="awg-grade">–</div>
                    <p class="awg-sub" id="awg-result-summary"></p>
                </div>
            </div>
            <h3 class="awg-h3">Top 3 issues to fix</h3>
            <ul class="awg-issues" id="awg-top-issues"></ul>
            <div class="awg-unlock">
                <h3 class="awg-h3">Unlock your full detailed report</h3>
                <p class="awg-sub">All 15 checks with plain-English explanations and exactly what to fix — shown here and emailed to you.</p>
                <form id="awg-unlock-form" novalidate>
                    <label class="awg-label" for="awg-name">Your name</label>
                    <input type="text" id="awg-name" name="name" class="awg-input" required autocomplete="name" placeholder="Your full name">
                    <label class="awg-label" for="awg-email">Your email</label>
                    <input type="email" id="awg-email" name="email" class="awg-input" required autocomplete="email" placeholder="you@yourbusiness.co.uk">
                    <button type="submit" class="awg-btn" id="awg-unlock-btn"><span class="awg-btn-text">Email me the full report</span></button>
                    <p class="awg-error" id="awg-unlock-error" role="alert" hidden></p>
                </form>
                <p class="awg-fineprint">We will only use your email to send this report and follow up once. No spam, ever. See our <a href="https://altuswebsolutions.com/privacy-policy/" target="_blank" rel="noopener">privacy policy</a>.</p>
            </div>
        </div>

        <!-- Step 4: full report -->
        <div class="awg-card" id="awg-step-report" hidden>
            <div class="awg-report-head">
                <div class="awg-score-ring awg-score-ring-sm" id="awg-score-ring-2"><span id="awg-score-num-2">0</span><span class="awg-score-pct">%</span></div>
                <div>
                    <h2 class="awg-title" style="margin:0">Your full website report</h2>
                    <p class="awg-sub" id="awg-report-meta" style="margin:.25rem 0 0"></p>
                </div>
            </div>
            <p class="awg-sub" id="awg-report-note"></p>
            <div class="awg-table-wrap">
                <table class="awg-table" id="awg-report-table">
                    <thead><tr><th>Check</th><th>Result</th><th>What we found</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="awg-cta">
                <h3 class="awg-h3">Want these fixed?</h3>
                <p class="awg-sub">We build fast, high-ranking websites for UK small businesses. Book a free 15-minute consultation and we will talk through your report.</p>
                <a class="awg-btn awg-btn-link" id="awg-book-link" href="https://altuswebsolutions.com/schedule-free-consultation/" target="_blank" rel="noopener">Book a free 15-min consultation</a>
                <p class="awg-fineprint">By Altus Web Solutions — no obligation.</p>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/* ------------------------------------------------------------------ */
/* AJAX: run the grade (step 1 -> step 3)                             */
/* ------------------------------------------------------------------ */
add_action('wp_ajax_awg_run', 'awg_ajax_run');
add_action('wp_ajax_nopriv_awg_run', 'awg_ajax_run');
function awg_ajax_run() {
    check_ajax_referer('awg_grader', 'nonce');

    // Rate limit: 5 checks per IP per hour.
    $ip  = awg_client_ip();
    $key = 'awg_rl_' . md5($ip);
    $hits = (int) get_transient($key);
    if ($hits >= 5) {
        wp_send_json_error(array('message' => 'Too many checks from this address. Please try again later.'));
    }
    set_transient($key, $hits + 1, HOUR_IN_SECONDS);

    $url           = isset($_POST['url']) ? trim(wp_unslash($_POST['url'])) : '';
    $business_name = isset($_POST['business_name']) ? sanitize_text_field(wp_unslash($_POST['business_name'])) : '';

    $url = awg_normalize_url($url);
    if (!$url) {
        wp_send_json_error(array('message' => 'Please enter a valid website address, e.g. https://www.yourbusiness.co.uk'));
    }
    if (!awg_url_allowed($url)) {
        wp_send_json_error(array('message' => 'That address cannot be checked (private or invalid host). Please use the public website.'));
    }

    @set_time_limit(120);

    $checker = new Altus_WG_Checker();
    $result  = $checker->run($url);

    if (!empty($result['fatal'])) {
        wp_send_json_error(array('message' => $result['fatal']));
    }

    // Stash the full result for the unlock step (10 minutes).
    $token = wp_generate_password(32, false);
    set_transient('awg_result_' . $token, array(
        'url'           => $url,
        'business_name' => $business_name,
        'result'        => $result,
        'ip'            => $ip,
    ), 10 * MINUTE_IN_SECONDS);

    // Top 3 issues = worst first (fail before improve), non-good only.
    $issues = array();
    $sorted = $result['items'];
    usort($sorted, function ($a, $b) {
        $w = array('fail' => 0, 'improve' => 1, 'good' => 2);
        return $w[$a['status']] - $w[$b['status']];
    });
    foreach ($sorted as $item) {
        if ($item['status'] !== 'good') $issues[] = $item;
        if (count($issues) === 3) break;
    }

    wp_send_json_success(array(
        'token'   => $token,
        'score'   => $result['score'],
        'grade'   => $result['grade'],
        'summary' => $result['summary'],
        'issues'  => array_map(function ($i) {
            return array('label' => $i['label'], 'status' => $i['status'],
                         'why' => $i['why'], 'detail' => $i['detail']);
        }, $issues),
    ));
}

/* ------------------------------------------------------------------ */
/* AJAX: unlock full report (step 3 -> step 4), save lead, send emails */
/* ------------------------------------------------------------------ */
add_action('wp_ajax_awg_unlock', 'awg_ajax_unlock');
add_action('wp_ajax_nopriv_awg_unlock', 'awg_ajax_unlock');
function awg_ajax_unlock() {
    check_ajax_referer('awg_grader', 'nonce');

    $token = isset($_POST['token']) ? sanitize_key($_POST['token']) : '';
    $data  = get_transient('awg_result_' . $token);
    if (!$data) {
        wp_send_json_error(array('message' => 'Your check expired. Please run the check again.'));
    }

    $name  = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
    $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    if ($name === '' || !is_email($email)) {
        wp_send_json_error(array('message' => 'Please enter your name and a valid email address.'));
    }

    $result   = $data['result'];
    $settings = awg_get_settings();

    // Save the lead.
    global $wpdb;
    $table = $wpdb->prefix . AWG_TABLE;
    $wpdb->insert($table, array(
        'name'          => $name,
        'email'         => $email,
        'business_name' => $data['business_name'],
        'site_url'      => $data['url'],
        'score'         => $result['score'],
        'grade'         => $result['grade'],
        'report'        => wp_json_encode($result['items']),
        'ip'            => $data['ip'],
    ), array('%s','%s','%s','%s','%d','%s','%s','%s'));

    // Email the full report to the visitor.
    $report_html = awg_report_email_html($name, $data, $result, $settings);
    $headers = array('Content-Type: text/html; charset=UTF-8',
                     'From: ' . $settings['from_name'] . ' <' . $settings['notify_email'] . '>');
    wp_mail($email, 'Your website grading report — ' . $result['score'] . '% (Grade ' . $result['grade'] . ')', $report_html, $headers);

    // Notify Altus of the new lead.
    $notify_body = sprintf(
        "<p><strong>New Website Grader lead</strong></p><p>Name: %s<br>Email: %s<br>Business: %s<br>URL: %s<br>Score: %d%% (Grade %s)<br>Date: %s</p><p><a href=\"%s\">Book a consultation</a></p>",
        esc_html($name), esc_html($email), esc_html($data['business_name']),
        esc_url($data['url']), (int) $result['score'], esc_html($result['grade']),
        esc_html(current_time('mysql')), esc_url($settings['book_url'])
    );
    wp_mail($settings['notify_email'], 'New lead: Website Grader — ' . $name . ' (' . $result['score'] . '%)', $notify_body, $headers);

    delete_transient('awg_result_' . $token);

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
function awg_client_ip() {
    foreach (array('HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR') as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = trim(explode(',', $_SERVER[$k])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}

function awg_normalize_url($url) {
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
function awg_url_allowed($url) {
    $host = wp_parse_url($url, PHP_URL_HOST);
    if (!$host) return false;
    // Fast path: literal IP.
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return awg_ip_public($host);
    }
    $ip = gethostbyname($host);
    if ($ip === $host) return false; // DNS failed
    return awg_ip_public($ip);
}

function awg_ip_public($ip) {
    if (!filter_var($ip, FILTER_VALIDATE_IP)) return false;
    // Reject private, reserved, loopback, link-local, multicast.
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
}

/** Build the visitor's full-report email (HTML). */
function awg_report_email_html($name, $data, $result, $settings) {
    $badge = array(
        'good'    => array('Good', '#17803d'),
        'improve' => array('Could improve', '#b26a00'),
        'fail'    => array('Needs work', '#c0392b'),
    );
    $rows = '';
    foreach ($result['items'] as $item) {
        $b = $badge[$item['status']];
        $rows .= sprintf(
            '<tr><td style="padding:8px;border-bottom:1px solid #eee">%s</td><td style="padding:8px;border-bottom:1px solid #eee;color:%s;font-weight:bold">%s</td><td style="padding:8px;border-bottom:1px solid #eee;color:#555"><strong>%s</strong><br>%s</td></tr>',
            esc_html($item['label']), $b[1], $b[0],
            esc_html($item['detail']), esc_html($item['why'])
        );
    }
    return sprintf(
        '<div style="font-family:Arial,sans-serif;max-width:640px;margin:0 auto;color:#1a1a1a">'
        . '<h2 style="color:#1450b8">Your website grading report</h2>'
        . '<p>Hi %s,</p><p>Here is the full breakdown for <strong>%s</strong> (%s).</p>'
        . '<p style="font-size:28px;font-weight:bold">Score: %d%% <span style="font-size:18px;color:#1450b8">(Grade %s)</span></p>'
        . '<table style="width:100%%;border-collapse:collapse;font-size:14px"><thead><tr><th style="text-align:left;padding:8px;border-bottom:2px solid #1450b8">Check</th><th style="text-align:left;padding:8px;border-bottom:2px solid #1450b8">Result</th><th style="text-align:left;padding:8px;border-bottom:2px solid #1450b8">What we found</th></tr></thead><tbody>%s</tbody></table>'
        . '<p>%s</p>'
        . '<p><a href="%s" style="display:inline-block;background:#1450b8;color:#fff;padding:12px 24px;text-decoration:none;border-radius:6px;font-weight:bold">Book a free 15-min consultation</a></p>'
        . '<p style="color:#888;font-size:12px">Report by Altus Web Solutions. This is an automated homepage-based grading — not a full Google PageSpeed/Lighthouse audit. “Could improve” means worth attention, not necessarily broken; “Needs work” means it should be fixed.</p></div>',
        esc_html($name), esc_html($data['business_name'] ?: $data['url']), esc_html($data['url']),
        (int) $result['score'], esc_html($result['grade']), $rows,
        esc_html($result['summary']), esc_url($settings['book_url'])
    );
}
