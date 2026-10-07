<?php
/**
 * Plugin Name: Altus Meta Description Checker
 * Description: Free lead-generation tool. Check any page's meta description — length, pixel-width fit, keyword use, call to action — with a live Google SERP preview. Fetch from URL or paste text.
 * Version: 1.0.0
 * Author: Altus Web Solutions
 * Text Domain: altus-meta-description-checker
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * No third-party libraries. Width estimates use Arial glyph metrics in JS.
 */

if (!defined('ABSPATH')) exit;

define('MDC_VERSION', '1.0.0');
define('MDC_PATH', plugin_dir_path(__FILE__));
define('MDC_URL', plugin_dir_url(__FILE__));
define('MDC_TABLE', 'altus_mdc_leads');

/* ------------------------------------------------------------------ */
/* Activation: create the leads table                                 */
/* ------------------------------------------------------------------ */
register_activation_hook(__FILE__, 'mdc_activate');
function mdc_activate() {
    global $wpdb;
    $table   = $wpdb->prefix . MDC_TABLE;
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        name VARCHAR(200) DEFAULT '',
        email VARCHAR(200) DEFAULT '',
        url VARCHAR(500) DEFAULT '',
        score SMALLINT DEFAULT 0,
        ip VARCHAR(45) DEFAULT '',
        PRIMARY KEY (id),
        KEY email (email),
        KEY created_at (created_at)
    ) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    if (get_option('mdc_settings') === false) {
        add_option('mdc_settings', array(
            'notify_email' => 'info@altuswebsolutions.com',
            'book_url'     => 'https://altuswebsolutions.com/schedule-free-consultation/',
            'from_name'    => 'Altus Web Solutions',
        ));
    }
}

function mdc_get_settings() {
    $defaults = array(
        'notify_email' => 'info@altuswebsolutions.com',
        'book_url'     => 'https://altuswebsolutions.com/schedule-free-consultation/',
        'from_name'    => 'Altus Web Solutions',
    );
    $saved = get_option('mdc_settings', array());
    return wp_parse_args(is_array($saved) ? $saved : array(), $defaults);
}

/* ------------------------------------------------------------------ */
/* Frontend assets (only loaded when the shortcode is present)        */
/* ------------------------------------------------------------------ */
add_action('wp_enqueue_scripts', 'mdc_enqueue');
function mdc_enqueue() {
    if (is_admin()) return;
    global $post;
    if (!($post instanceof WP_Post) || !has_shortcode($post->post_content, 'altus_meta_checker')) return;

    wp_enqueue_style('mdc-gen', MDC_URL . 'assets/generator.css', array(), MDC_VERSION);
    wp_enqueue_script('mdc-logic', MDC_URL . 'assets/meta-logic.js', array(), MDC_VERSION, true);
    wp_enqueue_script('mdc-gen', MDC_URL . 'assets/generator.js', array('mdc-logic'), MDC_VERSION, true);
    wp_localize_script('mdc-gen', 'MDC_AJAX', array(
        'ajax'  => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mdc_gen'),
    ));
}

/* ------------------------------------------------------------------ */
/* Shortcode: renders the checker UI                                  */
/* ------------------------------------------------------------------ */
add_shortcode('altus_meta_checker', 'mdc_shortcode');
function mdc_shortcode() {
    ob_start();
    ?>
    <div id="mdc-root" class="mdc-root">
        <div class="mdc-card">
            <div class="mdc-badge">Free tool</div>
            <h2 class="mdc-title">Check your meta description</h2>
            <p class="mdc-sub">Fetch a live page or paste your title and meta description — see exactly how Google would show it, and get a plain-English score with fixes.</p>

            <div class="mdc-tabs" role="tablist">
                <button type="button" class="mdc-tab mdc-tab-active" id="mdc-tab-url">Check a live URL</button>
                <button type="button" class="mdc-tab" id="mdc-tab-text">Write your own</button>
            </div>

            <div id="mdc-pane-url">
                <div class="mdc-field">
                    <label class="mdc-label" for="mdc-url">Page URL</label>
                    <div class="mdc-row">
                        <input type="url" id="mdc-url" class="mdc-input" placeholder="https://example.com/your-page" autocomplete="off" spellcheck="false">
                        <button type="button" class="mdc-btn mdc-btn-secondary" id="mdc-fetch">Fetch page</button>
                    </div>
                    <p class="mdc-fetch-msg" id="mdc-fetch-msg" hidden></p>
                </div>
            </div>
            <div id="mdc-pane-text" hidden>
                <p class="mdc-hint" style="margin-bottom:14px">Paste the title and meta description you are planning to use — the check runs on your text, nothing is fetched.</p>
            </div>

            <p class="mdc-error" id="mdc-error" role="alert" hidden></p>

            <div class="mdc-field">
                <label class="mdc-label" for="mdc-title">Page title</label>
                <input type="text" id="mdc-title" class="mdc-input" placeholder="e.g. Affordable Web Design for Small Businesses | Altus" autocomplete="off">
                <p class="mdc-count" id="mdc-title-count">0 characters</p>
            </div>

            <div class="mdc-field">
                <label class="mdc-label" for="mdc-desc">Meta description</label>
                <textarea id="mdc-desc" class="mdc-input" placeholder="e.g. We build fast, beautiful websites for UK small businesses that win customers. Book your free consultation today."></textarea>
                <p class="mdc-count" id="mdc-desc-count">0 / 155 characters</p>
            </div>

            <div class="mdc-field">
                <label class="mdc-label" for="mdc-keyword">Target keyword <span style="font-weight:400;color:#7b8ba0">(optional)</span></label>
                <input type="text" id="mdc-keyword" class="mdc-input" placeholder="e.g. web design" autocomplete="off">
            </div>

            <button type="button" class="mdc-btn" id="mdc-check">Check my snippet</button>

            <div id="mdc-results" hidden>
                <h3 class="mdc-checks-title" style="margin-top:28px">Your score</h3>
                <div class="mdc-score">
                    <div class="mdc-score-ring">
                        <div class="mdc-score-num" id="mdc-score-num">0</div>
                        <div class="mdc-score-max">/ 100</div>
                    </div>
                    <div class="mdc-score-meta">
                        <div class="mdc-grade" id="mdc-score-grade">–</div>
                        <div class="mdc-score-track"><div class="mdc-score-fill" id="mdc-score-bar"></div></div>
                    </div>
                </div>

                <div class="mdc-preview-head">
                    <h3>Google preview</h3>
                    <div class="mdc-tabs" style="margin:0">
                        <button type="button" class="mdc-tab mdc-tab-active" id="mdc-view-desktop">Desktop</button>
                        <button type="button" class="mdc-tab" id="mdc-view-mobile">Mobile</button>
                    </div>
                </div>
                <div class="mdc-serp" id="mdc-prev-desktop">
                    <div class="mdc-serp-url" id="mdc-prev-url">yourwebsite.com › your-page</div>
                    <div class="mdc-serp-title" id="mdc-prev-title"></div>
                    <p class="mdc-serp-desc" id="mdc-prev-desc"></p>
                </div>
                <div class="mdc-serp mdc-serp-mobile" id="mdc-prev-mobile" hidden>
                    <div class="mdc-serp-url" id="mdc-prev-url-m">yourwebsite.com › your-page</div>
                    <div class="mdc-serp-title" id="mdc-prev-title-m"></div>
                    <p class="mdc-serp-desc" id="mdc-prev-desc-m"></p>
                </div>
                <p class="mdc-prev-note" id="mdc-prev-note"></p>

                <h3 class="mdc-checks-title">Meta description checks</h3>
                <ul class="mdc-checks" id="mdc-checks-desc"></ul>

                <h3 class="mdc-checks-title">Title checks</h3>
                <ul class="mdc-checks" id="mdc-checks-title"></ul>

                <div class="mdc-btn-row">
                    <button type="button" class="mdc-btn mdc-btn-ghost" id="mdc-copy">Copy my report</button>
                    <button type="button" class="mdc-btn mdc-btn-ghost" id="mdc-download">Download report (.txt)</button>
                </div>
                <p class="mdc-copy-msg" id="mdc-copy-msg" hidden></p>

                <div class="mdc-gate">
                    <form id="mdc-send-form" novalidate>
                        <h3>Want the report in your inbox?</h3>
                        <p>We'll email you the full check — handy for your records or to hand to your designer. Plus one follow-up email from Altus. No spam, ever.</p>
                        <div class="mdc-field">
                            <label class="mdc-label" for="mdc-send-name">Your name</label>
                            <input type="text" id="mdc-send-name" class="mdc-input" autocomplete="name">
                            <p class="mdc-error" id="mdc-e-send-name" role="alert" hidden></p>
                        </div>
                        <div class="mdc-field">
                            <label class="mdc-label" for="mdc-send-email">Email address</label>
                            <input type="email" id="mdc-send-email" class="mdc-input" autocomplete="email">
                            <p class="mdc-error" id="mdc-e-send-email" role="alert" hidden></p>
                        </div>
                        <button type="submit" class="mdc-btn" id="mdc-send-btn">Email me my report</button>
                    </form>
                    <div class="mdc-gate-done" id="mdc-send-done" hidden>
                        <div class="mdc-tick">✓</div>
                        <h3>Sent!</h3>
                        <p>Check your inbox — your report is on its way. (Don't see it? Check spam, or <a href="mailto:info@altuswebsolutions.com" style="color:#10b3fe">email us</a>.)</p>
                    </div>
                </div>
            </div>

            <p class="mdc-fineprint">Pixel widths are estimates based on Arial glyph metrics — Google renders snippets dynamically and often rewrites descriptions, so treat the preview as a close approximation, not a guarantee.</p>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/* ------------------------------------------------------------------ */
/* Meta extraction (pure function — unit-tested)                      */
/* ------------------------------------------------------------------ */
function mdc_extract_meta($html) {
    $out = array('title' => '', 'description' => '', 'source' => 'none');
    if (!is_string($html) || $html === '') return $out;

    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
        $out['title'] = mdc_clean_text($m[1]);
    }

    // Collect meta tags; prefer name="description", fall back to og:description.
    $og = '';
    if (preg_match_all('/<meta\b[^>]*>/i', $html, $tags)) {
        foreach ($tags[0] as $tag) {
            $attrs = array();
            if (preg_match_all('/([\w:-]+)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/', $tag, $pairs, PREG_SET_ORDER)) {
                foreach ($pairs as $p) {
                    $attrs[strtolower($p[1])] = trim($p[2], "\"'");
                }
            }
            $key = isset($attrs['name']) ? strtolower($attrs['name'])
                 : (isset($attrs['property']) ? strtolower($attrs['property']) : '');
            if ($key === 'description' && isset($attrs['content']) && $out['description'] === '') {
                $out['description'] = mdc_clean_text($attrs['content']);
                $out['source'] = 'meta';
            } elseif ($key === 'og:description' && isset($attrs['content']) && $og === '') {
                $og = mdc_clean_text($attrs['content']);
            }
        }
    }
    if ($out['description'] === '' && $og !== '') {
        $out['description'] = $og;
        $out['source'] = 'og';
    }
    return $out;
}

function mdc_clean_text($s) {
    $s = strip_tags(html_entity_decode((string) $s, ENT_QUOTES, 'UTF-8'));
    $s = preg_replace('/\s+/u', ' ', $s);
    return trim($s);
}

/* ------------------------------------------------------------------ */
/* AJAX: fetch a page and extract title + meta description            */
/* ------------------------------------------------------------------ */
add_action('wp_ajax_mdc_fetch_page', 'mdc_fetch_page');
add_action('wp_ajax_nopriv_mdc_fetch_page', 'mdc_fetch_page');
function mdc_fetch_page() {
    if (!check_ajax_referer('mdc_gen', 'nonce', false)) {
        wp_send_json_error('Security check failed. Please reload the page and try again.');
    }

    $url = isset($_POST['url']) ? trim(wp_unslash($_POST['url'])) : '';
    $url = esc_url_raw($url, array('http', 'https'));
    if (!$url || !wp_http_validate_url($url)) {
        wp_send_json_error('That does not look like a valid URL.');
    }

    // Refuse non-public hosts (SSRF guard): no localhost, no IPs, no ports.
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    if (!$host || $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)
        || preg_match('/\.(local|test|internal|lan)$/', $host)
        || parse_url($url, PHP_URL_PORT)) {
        wp_send_json_error('Sorry, that address cannot be checked.');
    }

    $res = wp_remote_get($url, array(
        'timeout'             => 12,
        'redirection'         => 3,
        'limit_response_size' => 200000,
        'user-agent'          => 'AltusMetaChecker/1.0 (+https://altuswebsolutions.com/tools/meta-description-checker/)',
        'headers'             => array('Accept' => 'text/html'),
    ));
    if (is_wp_error($res)) {
        wp_send_json_error('Could not reach that page. Check the URL and try again.');
    }
    $code = (int) wp_remote_retrieve_response_code($res);
    if ($code < 200 || $code >= 400) {
        wp_send_json_error('That page returned an error (' . $code . '). Try another URL.');
    }
    $type = (string) wp_remote_retrieve_header($res, 'content-type');
    if ($type !== '' && stripos($type, 'html') === false) {
        wp_send_json_error('That URL is not a web page (it looks like a file download).');
    }

    $meta = mdc_extract_meta((string) wp_remote_retrieve_body($res));
    wp_send_json_success(array(
        'url'         => esc_url_raw($url),
        'title'       => $meta['title'],
        'description' => $meta['description'],
        'source'      => $meta['source'],
    ));
}

/* ------------------------------------------------------------------ */
/* AJAX: email the report + store lead                                */
/* ------------------------------------------------------------------ */
add_action('wp_ajax_mdc_send_report', 'mdc_send_report');
add_action('wp_ajax_nopriv_mdc_send_report', 'mdc_send_report');
function mdc_send_report() {
    if (!check_ajax_referer('mdc_gen', 'nonce', false)) {
        wp_send_json_error('Security check failed. Please reload the page and try again.');
    }

    $name   = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
    $email  = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $report = isset($_POST['report']) ? wp_unslash($_POST['report']) : '';

    if (mb_strlen($name) < 2) wp_send_json_error('Please enter your name.');
    if (!is_email($email)) wp_send_json_error('Please enter a valid email address.');

    // The report is plain text we generated client-side; strip tags + cap length.
    $report = substr(sanitize_textarea_field($report), 0, 20000);
    if (mb_strlen($report) < 20) wp_send_json_error('The report was empty. Please run the check first.');

    $page_url = '';
    if (preg_match('/^Page: (\S+)/m', $report, $m)) $page_url = esc_url_raw($m[1]);
    $score = 0;
    if (preg_match('/SCORE: (\d+)\/100/', $report, $m)) $score = max(0, min(100, (int) $m[1]));

    $settings = mdc_get_settings();

    global $wpdb;
    $wpdb->insert(
        $wpdb->prefix . MDC_TABLE,
        array(
            'name'  => $name,
            'email' => $email,
            'url'   => substr($page_url, 0, 500),
            'score' => $score,
            'ip'    => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
        ),
        array('%s', '%s', '%s', '%d', '%s')
    );

    $subject = 'Your meta description report from Altus Web Solutions';
    $body    = "Hi " . $name . ",\n\n"
        . "Here is your meta description check:\n\n"
        . "----------------------------------------\n"
        . $report . "\n"
        . "----------------------------------------\n\n"
        . "Want your whole site's SEO reviewed properly? Book a free 15-minute consultation: "
        . $settings['book_url'] . "\n\n"
        . "— The Altus Web Solutions team";
    $headers = array('From: ' . $settings['from_name'] . ' <' . $settings['notify_email'] . '>');
    $sent = wp_mail($email, $subject, $body, $headers);

    $notify_body = "New Meta Description Checker lead:\n\nName: {$name}\nEmail: {$email}\n"
        . "Checked URL: {$page_url}\nScore: {$score}/100\nTime: " . current_time('mysql') . "\n";
    wp_mail($settings['notify_email'], 'New meta description checker tool lead: ' . $name, $notify_body, $headers);

    if (!$sent) wp_send_json_error('Sorry, that did not send. Please use the download button instead.');
    wp_send_json_success();
}
