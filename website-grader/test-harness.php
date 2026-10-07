<?php
/**
 * Test harness for Altus Website Grader engine.
 * Run: php test-harness.php
 * Modes: stub (fixture HTML) and live (real curl fetch against a real site).
 */
define('ALTUS_WG_TEST', true);
require __DIR__ . '/includes/grader.php';

$failures = 0;
function check($cond, $msg) {
    global $failures;
    if ($cond) { echo "PASS: $msg\n"; }
    else { echo "FAIL: $msg\n"; $failures++; }
}

/* ---------------- Mode 1: stub fetcher with fixture HTML ---------------- */

$fixture_html = <<<HTML
<!DOCTYPE html>
<html lang="en-GB">
<head>
<title>Springfield Plumbing — Plumbers in Springfield (way too long a title that goes on and on and on forever)</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta property="og:title" content="Springfield Plumbing">
<link rel="icon" href="/favicon.png">
</head>
<body>
<h1>Springfield Plumbing</h1>
<h1>Another H1</h1>
<img src="/a.jpg" alt="van">
<img src="/b.jpg">
<img src="/c.jpg" alt="">
<img src="/d.jpg" alt="workshop">
<p>Welcome to Springfield Plumbing.</p>
</body>
</html>
HTML;

$stub_fetch = function ($url, $timeout) use ($fixture_html) {
    if (strpos($url, 'robots.txt') !== false) {
        return array('code' => 200, 'body' => "User-agent: *\nDisallow:\nSitemap: https://example.test/sitemap.xml\n",
            'headers' => array(), 'final_url' => $url, 'ttfb' => 0.05, 'total' => 0.1,
            'size' => 60, 'cert_expire' => null, 'error' => '');
    }
    return array('code' => 200, 'body' => $fixture_html,
        'headers' => array('content-encoding' => 'gzip', 'strict-transport-security' => 'max-age=31536000'),
        'final_url' => 'https://example.test/', 'ttfb' => 0.42, 'total' => 0.9,
        'size' => strlen($fixture_html), 'cert_expire' => time() + 60 * 86400, 'error' => '');
};

$g = new Altus_WG_Checker($stub_fetch);
$r = $g->run('https://example.test/');

check(empty($r['fatal']), 'no fatal error on stub run');
check($r['score'] >= 0 && $r['score'] <= 100, 'score in range (got ' . $r['score'] . ')');
check(count($r['items']) === 15, 'exactly 15 checks (got ' . count($r['items']) . ')');

$by = array();
foreach ($r['items'] as $it) $by[$it['key']] = $it;

check($by['response_time']['status'] === 'good', 'ttfb 0.42s => good');
check($by['page_size']['status'] === 'good', 'small page => good');
check($by['compression']['status'] === 'good', 'gzip header => good');
check($by['https']['status'] === 'good', 'https final => good');
check($by['ssl_expiry']['status'] === 'good', 'cert 60 days => good');
check($by['hsts']['status'] === 'good', 'hsts header => good');
check($by['title']['status'] === 'improve', 'long title => improve (len=' . mb_strlen('Springfield Plumbing — Plumbers in Springfield (way too long a title that goes on and on and on forever)') . ')');
check($by['meta_desc']['status'] === 'fail', 'missing meta desc => fail');
check($by['h1']['status'] === 'improve', '2 H1s => improve');
check($by['img_alt']['status'] === 'improve', '2/4 imgs with alt (one empty) => improve');
check($by['lang']['status'] === 'good', 'lang=en-GB => good');
check($by['opengraph']['status'] === 'improve', 'og:title only => improve');
check($by['viewport']['status'] === 'good', 'viewport => good');
check($by['favicon']['status'] === 'good', 'link icon => good');
check($by['robots']['status'] === 'good', 'robots with sitemap => good');
check(strpos($by['title']['detail'], 'characters') !== false, 'detail strings are measured, not invented');

$score = $r['score'];
// 10 good (10), 4 improve (2), 1 fail (0) => 12/15 = 80
check($score === 80, 'score math correct (expected 80, got ' . $score . ')');
check($r['grade'] === 'B', 'grade B for 80 (got ' . $r['grade'] . ')');

// Expired-cert edge case
$stub_expired = function ($url, $timeout) use ($fixture_html) {
    $base = array('code' => 200, 'body' => $fixture_html, 'headers' => array(),
        'final_url' => 'https://example.test/', 'ttfb' => 3.1, 'total' => 3.5,
        'size' => 9000, 'cert_expire' => time() - 5 * 86400, 'error' => '');
    if (strpos($url, 'robots.txt') !== false) return array('code' => 404, 'body' => '', 'headers' => array(), 'final_url' => $url, 'ttfb' => 0.1, 'total' => 0.2, 'size' => 0, 'cert_expire' => null, 'error' => '');
    if (strpos($url, 'sitemap') !== false) return array('code' => 404, 'body' => '', 'headers' => array(), 'final_url' => $url, 'ttfb' => 0.1, 'total' => 0.2, 'size' => 0, 'cert_expire' => null, 'error' => '');
    if (strpos($url, 'favicon.ico') !== false) return array('code' => 404, 'body' => '', 'headers' => array(), 'final_url' => $url, 'ttfb' => 0.1, 'total' => 0.2, 'size' => 0, 'cert_expire' => null, 'error' => '');
    return $base;
};
$g2 = new Altus_WG_Checker($stub_expired);
$r2 = $g2->run('https://example.test/');
$by2 = array();
foreach ($r2['items'] as $it) $by2[$it['key']] = $it;
check($by2['ssl_expiry']['status'] === 'fail', 'expired cert => fail');
check($by2['response_time']['status'] === 'fail', 'ttfb 3.1s => fail');
check($by2['robots']['status'] === 'improve', 'no robots => improve (not fail)');
check(strpos($by2['ssl_expiry']['detail'], 'expired') !== false, 'expired cert detail mentions expiry');

// HTTP-only site edge case
$stub_http = function ($url, $timeout) {
    if (strpos($url, 'https://') === 0) return array('code' => 0, 'body' => '', 'headers' => array(), 'final_url' => $url, 'ttfb' => null, 'total' => null, 'size' => 0, 'cert_expire' => null, 'error' => 'conn refused');
    $h = '<html lang="en"><head><title>OK title of decent length here yes</title></head><body><h1>Hi</h1></body></html>';
    return array('code' => 200, 'body' => $h, 'headers' => array(), 'final_url' => 'http://example.test/', 'ttfb' => 0.5, 'total' => 0.7, 'size' => strlen($h), 'cert_expire' => null, 'error' => '');
};
$g3 = new Altus_WG_Checker($stub_http);
$r3 = $g3->run('https://example.test/');
$by3 = array();
foreach ($r3['items'] as $it) $by3[$it['key']] = $it;
check($by3['https']['status'] === 'fail', 'http-only site => https fail');
check($by3['ssl_expiry']['status'] === 'fail', 'http-only site => cert fail');

// Dead site => fatal, not a fake score
$stub_dead = function ($url, $timeout) {
    return array('code' => 0, 'body' => '', 'headers' => array(), 'final_url' => $url, 'ttfb' => null, 'total' => null, 'size' => 0, 'cert_expire' => null, 'error' => 'dns fail');
};
$g4 = new Altus_WG_Checker($stub_dead);
$r4 = $g4->run('https://nope.invalid/');
check(!empty($r4['fatal']), 'dead site => fatal message, not a score');

/* ---------------- Mode 2: live run with real curl ---------------- */
if (in_array('--live', $GLOBALS['argv'])) {
    $target = $GLOBALS['argv'][array_search('--live', $GLOBALS['argv']) + 1] ?? 'https://altuswebsolutions.com/';
    echo "\n--- LIVE run against $target ---\n";
    $g5 = new Altus_WG_Checker(); // real curl fetcher
    $r5 = $g5->run($target);
    if (!empty($r5['fatal'])) {
        echo "LIVE FATAL: " . $r5['fatal'] . "\n";
        $failures++;
    } else {
        echo "LIVE score: {$r5['score']}% Grade {$r5['grade']}\n";
        foreach ($r5['items'] as $it) {
            echo sprintf("  [%s] %s — %s\n", strtoupper($it['status']), $it['label'], $it['detail']);
        }
        check(count($r5['items']) === 15, 'live run produced 15 checks');
        check($r5['score'] > 0, 'live score is non-zero on a real site');
    }
}

echo $failures === 0 ? "\nALL TESTS PASSED\n" : "\n$failures TEST(S) FAILED\n";
exit($failures === 0 ? 0 : 1);
