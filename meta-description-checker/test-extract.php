<?php
/* PHP unit test for mdc_extract_meta() / mdc_clean_text().
 * Stubs the handful of WP functions the plugin file touches at load time,
 * then includes it and exercises the pure extraction function.
 */
error_reporting(E_ALL);

define('ABSPATH', '/tmp/');

function register_activation_hook($f, $cb) {}
function add_action($h, $cb, $p = 10, $a = 1) {}
function add_shortcode($t, $cb) {}
function plugin_dir_path($f) { return dirname($f) . '/'; }
function plugin_dir_url($f) { return 'https://example.com/wp-content/plugins/x/'; }

require '/home/hatch/workspace/altus-bot/meta-description-checker/altus-meta-description-checker.php';

$pass = 0; $fail = 0;
function ok($name, $cond, $extra = '') {
    global $pass, $fail;
    if ($cond) { $pass++; }
    else { $fail++; echo "  FAIL  $name" . ($extra ? " — $extra" : "") . "\n"; }
}

echo "php extraction:\n";

$html = '<html><head><title>My Page Title | Brand</title>'
    . '<meta name="description" content="A great description here.">'
    . '</head><body></body></html>';
$r = mdc_extract_meta($html);
ok('title extracted', $r['title'] === 'My Page Title | Brand', $r['title']);
ok('description extracted', $r['description'] === 'A great description here.', $r['description']);
ok('source meta', $r['source'] === 'meta');

$html = '<html><head><title>T</title>'
    . '<meta property="og:description" content="OG fallback text.">'
    . '</head></html>';
$r = mdc_extract_meta($html);
ok('og:description fallback', $r['description'] === 'OG fallback text.' && $r['source'] === 'og', $r['description']);

$html = '<html><head><title>T</title>'
    . "<meta name='description' content='Single quoted &amp; &quot;encoded&quot; text.'>"
    . '</head></html>';
$r = mdc_extract_meta($html);
ok('single quotes + entities', $r['description'] === 'Single quoted & "encoded" text.', $r['description']);

$html = '<html><head><title>No meta here</title></head><body><p>hi</p></body></html>';
$r = mdc_extract_meta($html);
ok('missing desc empty', $r['description'] === '' && $r['source'] === 'none');
ok('title still found', $r['title'] === 'No meta here');

$html = '<html><head><TITLE>  Spaced   Title
</TITLE><META NAME="Description" CONTENT="Case insensitive attrs."></head></html>';
$r = mdc_extract_meta($html);
ok('case-insensitive + whitespace collapsed', $r['title'] === 'Spaced Title' && $r['description'] === 'Case insensitive attrs.', $r['title'] . ' / ' . $r['description']);

$r = mdc_extract_meta('');
ok('empty input safe', $r['title'] === '' && $r['description'] === '');

$r = mdc_extract_meta(null);
ok('null input safe', $r['title'] === '');

$html = '<head><meta charset="utf-8"><meta name="viewport" content="width=device-width">'
    . '<meta name="description" content="First wins."><meta name="description" content="Second ignored.">'
    . '<title>Dup</title></head>';
$r = mdc_extract_meta($html);
ok('first description wins', $r['description'] === 'First wins.', $r['description']);

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
