<?php
/**
 * Altus Website Grader — engine.
 *
 * Grades any public website's homepage on 15 speed, SEO, mobile and
 * trust/security signals. Uses curl timing (TTFB, transfer size) and
 * parses the homepage HTML — no Core Web Vitals lab data is claimed.
 *
 * Honesty rule: every item is good / improve / fail with a measured detail
 * string. 'improve' means "worth attention, not necessarily broken" and
 * 'fail' means "needs fixing". We never guess or invent findings.
 *
 * The HTTP layer is injectable (constructor arg) so the logic can be tested
 * outside WordPress.
 */

if (!defined('ABSPATH') && !defined('ALTUS_WG_TEST')) exit;

class Altus_WG_Checker {

    private $fetcher;      // callable(string $url, int $timeout): array(...)
    private $budget_start;
    private $budget_secs = 40;

    public function __construct($fetcher = null) {
        $this->fetcher = $fetcher ? $fetcher : array($this, 'curl_fetch');
    }

    /**
     * Run the full grade. Returns:
     * [score=>int, grade=>string, summary=>string, note=>string,
     *  items=>[...], fatal=>string|null]
     */
    public function run($url) {
        $this->budget_start = microtime(true);

        // Always try HTTPS first; fall back to HTTP and flag HTTPS as missing.
        $https_ok = true;
        $home = call_user_func($this->fetcher, $url, 12);
        if ($home['code'] < 200 || $home['code'] >= 400 || $home['body'] === '') {
            $http_url = preg_replace('#^https://#i', 'http://', $url);
            $home = call_user_func($this->fetcher, $http_url, 12);
            $https_ok = false;
            if ($home['code'] < 200 || $home['code'] >= 400 || $home['body'] === '') {
                return array('fatal' => 'We could not load that website. Please check the address and try again — it may be blocking automated checks or be temporarily down.');
            }
        }

        $final_url = !empty($home['final_url']) ? $home['final_url'] : $url;
        $https_final = (stripos($final_url, 'https://') === 0);
        $host = parse_url($final_url, PHP_URL_HOST);

        $html = $home['body'];
        $headers = isset($home['headers']) ? $home['headers'] : array();
        $items = $this->check_all($html, $headers, $home, $final_url, $host, $https_ok && $https_final);

        $scores = array(); $good = 0;
        foreach ($items as $it) {
            if ($it['status'] === 'good') { $scores[] = 1; $good++; }
            elseif ($it['status'] === 'improve') { $scores[] = 0.5; }
            else { $scores[] = 0; }
        }
        $score = (int) round(array_sum($scores) / count($scores) * 100);
        $grade = $score >= 85 ? 'A' : ($score >= 70 ? 'B' : ($score >= 55 ? 'C' : ($score >= 40 ? 'D' : 'F')));

        $attention = array();
        foreach ($items as $it) if ($it['status'] !== 'good') $attention[] = $it['label'];

        $summary = sprintf(
            'We graded %d speed, SEO, mobile and trust signals on your homepage. %d passed, %d need attention.',
            count($items), $good, count($attention)
        );

        $note = 'This is an automated homepage-based grading — it is not a full Google PageSpeed/Lighthouse audit, '
              . 'which also measures images, scripts and real-user experience. '
              . '“Could improve” means worth attention, not necessarily broken. “Needs work” means it should be fixed.';

        return array(
            'score' => $score, 'grade' => $grade, 'summary' => $summary, 'note' => $note,
            'items' => $items, 'fatal' => null,
        );
    }

    /* ---------------- HTTP ---------------- */

    /**
     * Fetch with curl so we get real timings. Returns:
     * [code, body, headers=>array(lowercase name=>value), final_url,
     *  ttfb=>float|null, total=>float|null, size=>int, cert_expire=>int|null, error]
     */
    private function curl_fetch($url, $timeout) {
        if (!function_exists('curl_init')) {
            return $this->wp_fetch($url, $timeout);
        }
        $resp_headers = array();
        $ch = curl_init($url);
        $max_bytes = 12 * 1024 * 1024;
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER     => true,
            CURLOPT_FOLLOWLOCATION     => true,
            CURLOPT_MAXREDIRS          => 3,
            CURLOPT_TIMEOUT            => $timeout,
            CURLOPT_CONNECTTIMEOUT     => min(6, $timeout),
            CURLOPT_USERAGENT          => 'AltusWebsiteGrader/1.0 (+https://altuswebsolutions.com/)',
            CURLOPT_ENCODING           => '', // ask for compressed, decode automatically
            CURLOPT_SSL_VERIFYPEER     => true,
            CURLOPT_SSL_VERIFYHOST     => 2,
            CURLOPT_CERTINFO           => true,
            CURLOPT_HEADERFUNCTION     => function ($ch, $line) use (&$resp_headers) {
                $len = strlen($line);
                if (strpos($line, ':') !== false) {
                    list($k, $v) = explode(':', $line, 2);
                    $resp_headers[strtolower(trim($k))] = trim($v);
                }
                return $len;
            },
            CURLOPT_NOPROGRESS         => false,
            CURLOPT_PROGRESSFUNCTION   => function () use ($max_bytes) {
                // Can't easily see bytes here portably; handled post-hoc.
                return 0;
            },
        ));
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $info = curl_getinfo($ch);
        $certinfo = curl_getinfo($ch, CURLINFO_CERTINFO);
        curl_close($ch);

        if ($errno !== 0 || $body === false) {
            return array('code' => 0, 'body' => '', 'headers' => array(),
                         'final_url' => $url, 'ttfb' => null, 'total' => null,
                         'size' => 0, 'cert_expire' => null, 'error' => 'curl error ' . $errno);
        }

        $size = strlen($body);
        $truncated = false;
        if ($size > $max_bytes) {
            $body = substr($body, 0, $max_bytes);
            $truncated = true;
        }

        return array(
            'code'        => (int) $info['http_code'],
            'body'        => (string) $body,
            'headers'     => $resp_headers,
            'final_url'   => !empty($info['url']) ? $info['url'] : $url,
            'ttfb'        => isset($info['starttransfer_time']) ? (float) $info['starttransfer_time'] : null,
            'total'       => isset($info['total_time']) ? (float) $info['total_time'] : null,
            'size'        => $truncated ? $max_bytes : $size,
            'truncated'   => $truncated,
            'cert_expire' => $this->cert_expiry_from_info($certinfo),
            'error'       => '',
        );
    }

    /** Extract leaf-cert expiry from curl certinfo. */
    private function cert_expiry_from_info($certinfo) {
        if (!is_array($certinfo) || empty($certinfo)) return null;
        $first = $certinfo[0];
        if (isset($first['Expire date'])) {
            $t = strtotime($first['Expire date']);
            return $t ? (int) $t : null;
        }
        if (isset($first['Expire_Date'])) {
            $t = strtotime($first['Expire_Date']);
            return $t ? (int) $t : null;
        }
        return null;
    }

    private function wp_fetch($url, $timeout) {
        if (!function_exists('wp_remote_get')) {
            return array('code' => 0, 'body' => '', 'headers' => array(),
                         'final_url' => $url, 'ttfb' => null, 'total' => null,
                         'size' => 0, 'cert_expire' => null, 'error' => 'no HTTP layer');
        }
        $r = wp_remote_get($url, array(
            'timeout'     => $timeout,
            'redirection' => 3,
            'sslverify'   => true,
            'headers'     => array('User-Agent' => 'AltusWebsiteGrader/1.0 (+https://altuswebsolutions.com/)'),
        ));
        if (is_wp_error($r)) {
            return array('code' => 0, 'body' => '', 'headers' => array(),
                         'final_url' => $url, 'ttfb' => null, 'total' => null,
                         'size' => 0, 'cert_expire' => null, 'error' => $r->get_error_message());
        }
        $body = (string) wp_remote_retrieve_body($r);
        return array(
            'code'        => (int) wp_remote_retrieve_response_code($r),
            'body'        => $body,
            'headers'     => array_change_key_case(wp_remote_retrieve_headers($r)->getAll(), CASE_LOWER),
            'final_url'   => $url,
            'ttfb'        => null,
            'total'       => null,
            'size'        => strlen($body),
            'cert_expire' => null,
            'error'       => '',
        );
    }

    private function over_budget() {
        return (microtime(true) - $this->budget_start) > $this->budget_secs;
    }

    /* ---------------- The 15 checks ---------------- */

    private function check_all($html, $headers, $home, $final_url, $host, $https_final) {
        $items = array();

        /* --- Speed --- */
        $ttfb = isset($home['ttfb']) ? $home['ttfb'] : null;
        if ($ttfb === null) {
            $items[] = $this->item('response_time', 'Server response time', 'improve',
                'We could not measure response time automatically — check it with Google PageSpeed Insights.',
                'Timing data unavailable.');
        } elseif ($ttfb <= 1.0) {
            $items[] = $this->item('response_time', 'Server response time', 'good',
                'Fast server response keeps every page feeling snappy.',
                sprintf('Server responded in %.2fs.', $ttfb));
        } elseif ($ttfb <= 2.5) {
            $items[] = $this->item('response_time', 'Server response time', 'improve',
                'A faster server response improves rankings and keeps visitors. Good hosting or caching usually fixes this.',
                sprintf('Server responded in %.2fs — aim for under 1s.', $ttfb));
        } else {
            $items[] = $this->item('response_time', 'Server response time', 'fail',
                'A slow server hurts Google rankings and makes visitors leave. This usually needs better hosting or caching.',
                sprintf('Server took %.2fs to respond.', $ttfb));
        }

        $kb = $home['size'] / 1024;
        if (!empty($home['truncated']) || $kb > 1536) {
            $items[] = $this->item('page_size', 'Homepage size', 'fail',
                'A heavy homepage loads slowly on mobile connections. Compress images and trim unused scripts and plugins.',
                sprintf('Homepage HTML is over %s.', $this->fmt_kb($kb)));
        } elseif ($kb > 512) {
            $items[] = $this->item('page_size', 'Homepage size', 'improve',
                'A leaner homepage loads faster, especially on phones. Review large images and unused scripts.',
                sprintf('Homepage HTML is %s.', $this->fmt_kb($kb)));
        } else {
            $items[] = $this->item('page_size', 'Homepage size', 'good',
                'A lean homepage — good for load times.',
                sprintf('Homepage HTML is %s.', $this->fmt_kb($kb)));
        }

        $enc = isset($headers['content-encoding']) ? strtolower($headers['content-encoding']) : '';
        if (strpos($enc, 'gzip') !== false || strpos($enc, 'br') !== false || strpos($enc, 'deflate') !== false || strpos($enc, 'zstd') !== false) {
            $items[] = $this->item('compression', 'Compression enabled', 'good',
                'Compressed pages transfer faster for every visitor.',
                'Content encoding: ' . $enc . '.');
        } else {
            $items[] = $this->item('compression', 'Compression enabled', 'improve',
                'Enabling gzip or Brotli compression shrinks every page transfer — usually a one-line server or plugin setting.',
                'No compression header detected.');
        }

        /* --- Trust / security --- */
        if ($https_final) {
            $items[] = $this->item('https', 'Secure HTTPS connection', 'good',
                'HTTPS protects visitors and is a Google ranking signal.',
                'Site loads over HTTPS.');
        } else {
            $items[] = $this->item('https', 'Secure HTTPS connection', 'fail',
                'Browsers flag non-HTTPS sites as “Not secure”, which scares visitors and hurts rankings. A free SSL certificate fixes this.',
                'Site is served over plain HTTP.');
        }

        $exp = isset($home['cert_expire']) ? $home['cert_expire'] : null;
        if (!$https_final) {
            $items[] = $this->item('ssl_expiry', 'SSL certificate validity', 'fail',
                'No HTTPS means no certificate — fix HTTPS first.',
                'No certificate (HTTP site).');
        } elseif ($exp === null) {
            $items[] = $this->item('ssl_expiry', 'SSL certificate validity', 'improve',
                'We could not read the certificate expiry automatically — check it manually before it lapses.',
                'Expiry date unavailable.');
        } else {
            $days = (int) floor(($exp - time()) / 86400);
            if ($days > 1000) {
                // Implausibly long validity (e.g. intercepting proxy cert) — don't claim it as real.
                $items[] = $this->item('ssl_expiry', 'SSL certificate validity', 'improve',
                    'We could not verify the real certificate expiry automatically — check it manually before it lapses.',
                    'Certificate expiry could not be reliably read.');
            } elseif ($days < 0) {
                $items[] = $this->item('ssl_expiry', 'SSL certificate validity', 'fail',
                    'An expired certificate makes browsers show a full-page warning. Renew it immediately.',
                    'Certificate expired ' . abs($days) . ' day(s) ago.');
            } elseif ($days <= 30) {
                $items[] = $this->item('ssl_expiry', 'SSL certificate validity', 'improve',
                    'Renew the certificate soon so visitors never see a warning.',
                    'Certificate expires in ' . $days . ' day(s).');
            } else {
                $items[] = $this->item('ssl_expiry', 'SSL certificate validity', 'good',
                    'A valid certificate keeps the padlock showing.',
                    'Certificate valid for ' . $days . ' more day(s).');
            }
        }

        if (isset($headers['strict-transport-security'])) {
            $items[] = $this->item('hsts', 'HSTS security header', 'good',
                'HSTS forces browsers to always use the secure connection.',
                'Strict-Transport-Security header present.');
        } else {
            $items[] = $this->item('hsts', 'HSTS security header', 'improve',
                'HSTS is a small hardening win once HTTPS is in place.',
                'Strict-Transport-Security header not detected.');
        }

        /* --- SEO --- */
        $title = '';
        if (preg_match('#<title[^>]*>(.*?)</title>#is', $html, $m)) {
            $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
        }
        $tlen = mb_strlen($title);
        if ($tlen === 0) {
            $items[] = $this->item('title', 'Page title', 'fail',
                'Every page needs a descriptive title — it is what Google shows in search results.',
                'No <title> tag found.');
        } elseif ($tlen >= 30 && $tlen <= 60) {
            $items[] = $this->item('title', 'Page title', 'good',
                'A clear title helps Google understand and rank the page.',
                'Title is ' . $tlen . ' characters.');
        } else {
            $items[] = $this->item('title', 'Page title', 'improve',
                'Aim for 30–60 characters so titles display fully in Google results.',
                'Title is ' . $tlen . ' characters (“' . mb_substr($title, 0, 70) . '”).');
        }

        $desc = '';
        if (preg_match('#<meta[^>]+name=["\']description["\'][^>]*>#i', $html, $m)) {
            if (preg_match('#content=["\']([^"\']*)#i', $m[0], $c)) {
                $desc = trim(html_entity_decode($c[1], ENT_QUOTES, 'UTF-8'));
            }
        }
        $dlen = mb_strlen($desc);
        if ($dlen === 0) {
            $items[] = $this->item('meta_desc', 'Meta description', 'fail',
                'The meta description controls the snippet Google shows — without one, you don’t control the pitch.',
                'No meta description found.');
        } elseif ($dlen >= 50 && $dlen <= 160) {
            $items[] = $this->item('meta_desc', 'Meta description', 'good',
                'A good description improves click-through from Google.',
                'Description is ' . $dlen . ' characters.');
        } else {
            $items[] = $this->item('meta_desc', 'Meta description', 'improve',
                'Aim for 50–160 characters so the snippet displays well in Google.',
                'Description is ' . $dlen . ' characters.');
        }

        preg_match_all('#<h1\b[^>]*>#i', $html, $hm);
        $h1s = count($hm[0]);
        if ($h1s === 1) {
            $items[] = $this->item('h1', 'Single H1 heading', 'good',
                'One clear main heading helps Google and screen readers understand the page.',
                'Exactly 1 H1 found.');
        } elseif ($h1s === 0) {
            $items[] = $this->item('h1', 'Single H1 heading', 'fail',
                'Every page should have one main (H1) heading describing its topic.',
                'No H1 heading found.');
        } else {
            $items[] = $this->item('h1', 'Single H1 heading', 'improve',
                'Use one H1 per page; make the rest H2/H3 for a clean structure.',
                $h1s . ' H1 headings found.');
        }

        preg_match_all('#<img\b[^>]*>#i', $html, $im);
        $img_total = count($im[0]); $img_alt = 0;
        foreach ($im[0] as $tag) {
            if (preg_match('#alt=["\']([^"\']*)#i', $tag, $am) && trim($am[1]) !== '') $img_alt++;
        }
        if ($img_total === 0) {
            $items[] = $this->item('img_alt', 'Image alt text', 'improve',
                'Alt text helps Google understand images and makes the site accessible. Add it to every image.',
                'No images detected on the homepage.');
        } elseif ($img_alt === $img_total) {
            $items[] = $this->item('img_alt', 'Image alt text', 'good',
                'Good alt text helps image search and accessibility.',
                $img_alt . ' of ' . $img_total . ' images have alt text.');
        } elseif ($img_alt / $img_total >= 0.5) {
            $items[] = $this->item('img_alt', 'Image alt text', 'improve',
                'Add descriptive alt text to the remaining images for SEO and accessibility.',
                $img_alt . ' of ' . $img_total . ' images have alt text.');
        } else {
            $items[] = $this->item('img_alt', 'Image alt text', 'fail',
                'Missing alt text hurts image search and accessibility — describe every image.',
                $img_alt . ' of ' . $img_total . ' images have alt text.');
        }

        if (preg_match('#<html[^>]+lang=["\']([a-zA-Z-]+)#i', $html, $lm)) {
            $items[] = $this->item('lang', 'Language attribute', 'good',
                'The lang attribute helps search engines and screen readers.',
                'Language set to “' . $lm[1] . '”.');
        } else {
            $items[] = $this->item('lang', 'Language attribute', 'improve',
                'Add lang="en" (or your language) to the <html> tag for accessibility and SEO.',
                'No lang attribute on <html>.');
        }

        $has_og_title = (bool) preg_match('#<meta[^>]+property=["\']og:title["\']#i', $html);
        $has_og_image = (bool) preg_match('#<meta[^>]+property=["\']og:image["\']#i', $html);
        if ($has_og_title && $has_og_image) {
            $items[] = $this->item('opengraph', 'Social sharing tags', 'good',
                'Open Graph tags control how the page looks when shared on Facebook, LinkedIn and WhatsApp.',
                'og:title and og:image present.');
        } elseif ($has_og_title || $has_og_image) {
            $items[] = $this->item('opengraph', 'Social sharing tags', 'improve',
                'Add both og:title and og:image so shares always look professional.',
                'Only one of og:title / og:image found.');
        } else {
            $items[] = $this->item('opengraph', 'Social sharing tags', 'fail',
                'Without Open Graph tags, shares of your site look plain or broken on social media.',
                'No Open Graph tags found.');
        }

        /* --- Mobile --- */
        if (preg_match('#<meta[^>]+name=["\']viewport["\']#i', $html)) {
            $items[] = $this->item('viewport', 'Mobile-friendly viewport', 'good',
                'The viewport tag lets the page adapt to phone screens — most visitors browse on mobile.',
                'Viewport meta tag present.');
        } else {
            $items[] = $this->item('viewport', 'Mobile-friendly viewport', 'fail',
                'Without a viewport tag the site will look tiny or broken on phones.',
                'No viewport meta tag.');
        }

        /* --- Findability extras --- */
        $favicon_ok = (bool) preg_match('#<link[^>]+rel=["\'](?:shortcut )?icon["\']#i', $html);
        if (!$favicon_ok && !$this->over_budget() && $host) {
            $fav = call_user_func($this->fetcher, 'https://' . $host . '/favicon.ico', 6);
            $favicon_ok = ($fav['code'] === 200 && $fav['size'] > 100);
        }
        if ($favicon_ok) {
            $items[] = $this->item('favicon', 'Favicon', 'good',
                'A favicon makes the site recognisable in browser tabs and bookmarks.',
                'Favicon detected.');
        } else {
            $items[] = $this->item('favicon', 'Favicon', 'improve',
                'Add a favicon so your brand shows in browser tabs and Google results.',
                'No favicon detected.');
        }

        $robots = $this->robots_status($host);
        if ($robots === 'good') {
            $items[] = $this->item('robots', 'Robots.txt + sitemap', 'good',
                'A robots.txt with a sitemap reference helps Google discover every page.',
                'robots.txt found with sitemap reference.');
        } elseif ($robots === 'partial') {
            $items[] = $this->item('robots', 'Robots.txt + sitemap', 'improve',
                'Point your robots.txt (or Google Search Console) at your XML sitemap so nothing is missed.',
                'robots.txt found but no sitemap reference.');
        } else {
            $items[] = $this->item('robots', 'Robots.txt + sitemap', 'improve',
                'A robots.txt and XML sitemap help Google find and index every page.',
                'No robots.txt detected.');
        }

        return $items;
    }

    private function item($key, $label, $status, $why, $detail) {
        return array('key' => $key, 'label' => $label, 'status' => $status,
                     'why' => $why, 'detail' => $detail);
    }

    private function fmt_kb($kb) {
        return $kb >= 1024 ? sprintf('%.1f MB', $kb / 1024) : sprintf('%d KB', (int) round($kb));
    }

    /** 'good' = robots.txt with sitemap ref, 'partial' = robots.txt only, 'none' = missing. */
    private function robots_status($host) {
        if (!$host || $this->over_budget()) return 'none';
        $base = 'https://' . $host;
        $r = call_user_func($this->fetcher, $base . '/robots.txt', 6);
        if ($r['code'] === 200 && $r['body'] !== ''
            && preg_match('#(user-agent|disallow)#i', $r['body'])) {
            if (preg_match('#sitemap:#i', $r['body'])) return 'good';
            // Sitemap referenced elsewhere (common WordPress locations).
            foreach (array('/sitemap.xml', '/wp-sitemap.xml') as $p) {
                if ($this->over_budget()) break;
                $s = call_user_func($this->fetcher, $base . $p, 6);
                if ($s['code'] === 200 && preg_match('#<(urlset|sitemapindex)#i', $s['body'])) {
                    return 'good';
                }
            }
            return 'partial';
        }
        return 'none';
    }
}
