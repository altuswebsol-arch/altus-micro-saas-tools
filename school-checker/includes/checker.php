<?php
/**
 * Altus School Website Compliance Checker — engine.
 *
 * Crawls a school website (homepage + up to N internal pages) and checks it
 * against the DfE "what maintained schools must publish online" requirements
 * plus basic Ofsted expectations and technical SEO basics.
 *
 * Honesty rule: every item is FOUND, MISSING or UNCERTAIN. UNCERTAIN means
 * "we could not verify this automatically — check it manually". MISSING means
 * "not found in the pages and documents we could read". We never
 * guess or invent findings.
 *
 * Document awareness: schools publish most policies as PDFs, so linked
 * documents (PDF/DOC/...) are matched by file name + link text as evidence.
 *
 * The HTTP layer is injectable (constructor arg) so the logic can be tested
 * outside WordPress.
 */

if (!defined('ABSPATH') && !defined('ALTUS_SC_TEST')) exit;

class Altus_SC_Checker {

    private $fetcher;      // callable(string $url, int $timeout): array{code,body,error}
    private $budget_start; // microtime float
    private $budget_secs = 50;

    public function __construct($fetcher = null) {
        $this->fetcher = $fetcher ? $fetcher : array($this, 'wp_fetch');
    }

    /**
     * Run the full check. Returns:
     * [score=>int, grade=>string, summary=>string, note=>string,
     *  items=>[...], pages_scanned=>int, pages_failed=>int, fatal=>string|null]
     */
    public function run($url, $max_pages = 15) {
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

        $host = parse_url($url, PHP_URL_HOST);
        $pages = array(); // url => ['text'=>..., 'title'=>...]
        $pages[$url] = $this->page_data($home['body']);

        // Discover internal links from the homepage, prioritised by relevance.
        $disc = $this->discover_links($home['body'], $url, $host);
        $links = $disc['pages'];
        $docs = $disc['docs'];
        $fetched = 1; $failed = 0;
        foreach ($links as $link) {
            if ($fetched >= $max_pages) break;
            if ($this->over_budget()) break;
            if (isset($pages[$link])) continue;
            $r = call_user_func($this->fetcher, $link, 8);
            if ($r['code'] >= 200 && $r['code'] < 400 && $r['body'] !== '') {
                $pages[$link] = $this->page_data($r['body']);
                $fetched++;
            } else {
                $failed++;
            }
        }

        // Build one searchable corpus, but remember which page each hit came from.
        $items = $this->check_all($pages, $docs, $https_ok, $home['body']);

        $scores = array(); $found = 0;
        foreach ($items as $it) {
            if ($it['status'] === 'found') { $scores[] = 1; $found++; }
            elseif ($it['status'] === 'uncertain') { $scores[] = 0.5; }
            else { $scores[] = 0; }
        }
        $score = (int) round(array_sum($scores) / count($scores) * 100);
        $grade = $score >= 85 ? 'A' : ($score >= 70 ? 'B' : ($score >= 55 ? 'C' : ($score >= 40 ? 'D' : 'F')));

        $missing = array();
        foreach ($items as $it) if ($it['status'] !== 'found') $missing[] = $it['label'];

        $summary = sprintf(
            'We checked %d requirements across %d page%s. %d passed, %d need attention.',
            count($items), $fetched, $fetched === 1 ? '' : 's', $found, count($missing)
        );

        $note_parts = array();
        if ($failed > 0) $note_parts[] = sprintf('%d page%s could not be loaded, so some items are marked "check manually".', $failed, $failed === 1 ? '' : 's');
        if ($this->over_budget()) $note_parts[] = 'The scan hit its time budget, so a few pages may not have been checked.';
        if (count($docs) > 0) $note_parts[] = sprintf('We also checked %d linked document%s (PDFs etc.) by file name and link text.', count($docs), count($docs) === 1 ? '' : 's');
        $note_parts[] = 'This is an automated guide based on the DfE’s published guidance for maintained schools in England — it is not legal advice, and requirements can differ by school type. “Check manually” means please verify the item on your site directly, and “not found” means we could not find it in the pages and documents we could read.';
        $note = implode(' ', $note_parts);

        return array(
            'score' => $score, 'grade' => $grade, 'summary' => $summary, 'note' => $note,
            'items' => $items, 'pages_scanned' => $fetched, 'pages_failed' => $failed,
            'fatal' => null,
        );
    }

    /* ---------------- HTTP ---------------- */

    private function wp_fetch($url, $timeout) {
        if (!function_exists('wp_remote_get')) {
            return array('code' => 0, 'body' => '', 'error' => 'wp_remote_get unavailable');
        }
        $r = wp_remote_get($url, array(
            'timeout'     => $timeout,
            'redirection' => 3,
            'sslverify'   => true,
            'headers'     => array('User-Agent' => 'AltusSchoolChecker/1.0 (+https://altuswebsolutions.com/)'),
        ));
        if (is_wp_error($r)) {
            return array('code' => 0, 'body' => '', 'error' => $r->get_error_message());
        }
        return array(
            'code'  => (int) wp_remote_retrieve_response_code($r),
            'body'  => (string) wp_remote_retrieve_body($r),
            'error' => '',
        );
    }

    private function over_budget() {
        return (microtime(true) - $this->budget_start) > $this->budget_secs;
    }

    /* ---------------- HTML parsing ---------------- */

    /** Extract visible text + <title> from HTML. */
    private function page_data($html) {
        $title = '';
        if (preg_match('#<title[^>]*>(.*?)</title>#is', $html, $m)) {
            $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
        }
        $text = preg_replace('#<(script|style|noscript)[^>]*>.*?</\1>#is', ' ', $html);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);
        return array('title' => mb_substr($title, 0, 200), 'text' => mb_substr(trim($text), 0, 120000));
    }

    /**
     * Find internal links on the homepage, scored by how likely they are to
     * hold compliance content. Returns array('pages'=>[...], 'docs'=>[...]).
     * Document links (PDF/DOC/...) are NOT skipped: schools publish most
     * policies as documents, so their URLs + link text are kept as evidence.
     */
    private function discover_links($html, $base_url, $host) {
        $out = array(); $docs = array();
        if (!preg_match_all('#<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is', $html, $m, PREG_SET_ORDER)) {
            return array('pages' => array(), 'docs' => array());
        }
        $signals = array('contact','admission','curriculum','polic','ofsted','send','premium','governor',
                         'term','access','safe','complain','behaviour','behavior','equality','value','ethos',
                         'vision','about','parent','key-information','statutory');
        foreach ($m as $a) {
            $href = trim($a[1]);
            $anchor = strtolower(strip_tags($a[2]));
            if ($href === '' || $href[0] === '#' ) continue;
            if (preg_match('#^(mailto|tel|javascript):#i', $href)) continue;
            $abs = $this->resolve_url($href, $base_url);
            if (!$abs) continue;
            $h = strtolower(parse_url($abs, PHP_URL_HOST));
            $want = strtolower($host);
            // Treat www/non-www as same site.
            if (preg_replace('#^www\.#', '', $h) !== preg_replace('#^www\.#', '', $want)) continue;
            $abs = strtok($abs, '#'); // drop fragment
            // Document links become searchable evidence (URL + link text),
            // not crawled pages.
            if (preg_match('#\.(pdf|docx?|xlsx?|pptx?|csv)(\?|$)#i', $href)) {
                $docs[] = array('url' => $abs, 'anchor' => $anchor, 'page' => $base_url);
                continue;
            }
            if (preg_match('#\.(jpg|jpeg|png|gif|webp|svg|mp4|zip)(\?|#|$)#i', $href)) continue;
            if (isset($out[$abs])) continue;
            $score = 0;
            $hay = strtolower($abs) . ' ' . $anchor;
            foreach ($signals as $s) if (strpos($hay, $s) !== false) $score++;
            $out[$abs] = $score;
        }
        arsort($out);
        return array('pages' => array_keys($out), 'docs' => $docs);
    }

    private function resolve_url($href, $base) {
        if (preg_match('#^https?://#i', $href)) return $href;
        if (strpos($href, '//') === 0) return 'https:' . $href;
        $p = parse_url($base);
        $scheme = $p['scheme']; $host = $p['host'];
        if ($href[0] === '/') return $scheme . '://' . $host . $href;
        $path = isset($p['path']) ? $p['path'] : '/';
        $dir = substr($path, -1) === '/' ? $path : dirname($path) . '/';
        return $scheme . '://' . $host . $dir . $href;
    }

    /* ---------------- The 20 checks ---------------- */

    private function definitions() {
        // 'statutory' => true means the DfE lists the item under "must publish"
        // (School Information (England) Regulations and related legislation).
        // false = DfE "should publish" or good practice — still checked, but labelled Recommended.
        return array(
            array('key'=>'contact','label'=>'Contact details','statutory'=>true,
                'strong'=>array('contact us','contact details','our address','find us','how to find us'),
                'weak'=>array('telephone','phone number','email us'),
                'why'=>'Schools must publish their address, phone number and a contact for parent queries.'),
            array('key'=>'admissions','label'=>'Admission arrangements','statutory'=>true,
                'strong'=>array('admission arrangements','admissions policy','admission policy'),
                'weak'=>array('admissions'),
                'why'=>'Schools must publish admission arrangements, or a link to them where the local authority manages admissions.'),
            array('key'=>'curriculum','label'=>'Curriculum information','statutory'=>true,
                'strong'=>array('curriculum intent','curriculum overview','our curriculum'),
                'weak'=>array('curriculum','schemes of work'),
                'why'=>'DfE requires the curriculum content for each year and subject to be published.'),
            array('key'=>'behaviour','label'=>'Behaviour policy','statutory'=>true,
                'strong'=>array('behaviour policy','behavior policy','positive behaviour policy',
                                'behaviour management policy','behavior management policy','behaviour for learning'),
                'weak'=>array('behaviour','behavior'),
                'why'=>'Schools must publish their behaviour policy (Education and Inspections Act 2006).'),
            array('key'=>'charging','label'=>'Charging & remissions policies','statutory'=>true,
                'strong'=>array('charging policy','remissions policy','charges and remissions','charging and remissions'),
                'weak'=>array('remissions','charging for school activities'),
                'why'=>'Schools must publish charging and remissions policies (Education Act 1996).'),
            array('key'=>'complaints','label'=>'Complaints procedure','statutory'=>true,
                'strong'=>array('complaints procedure','complaints policy'),
                'weak'=>array('complaints'),
                'why'=>'Schools must publish their complaints policy (Education Act 2002).'),
            array('key'=>'governors','label'=>'Governors’ details','statutory'=>true,
                'strong'=>array('governing body','board of governors','our governors','meet the governors'),
                'weak'=>array('governor'),
                'why'=>'DfE requires published governance information, and recommends governor names, roles, interests and attendance.'),
            array('key'=>'ofsted','label'=>'Ofsted report','statutory'=>true,
                'strong'=>array('ofsted report','ofsted inspection'),
                'weak'=>array('ofsted'),
                'why'=>'Your latest Ofsted report must be published or linked.'),
            array('key'=>'performance','label'=>'Performance tables link','statutory'=>true,
                'strong'=>array('performance tables','compare school performance','compare-school-performance'),
                'weak'=>array('performance data'),
                'why'=>'DfE requires a link to the school performance tables service.'),
            array('key'=>'send','label'=>'SEND information report','statutory'=>true,
                'strong'=>array('send information report','special educational needs','send policy'),
                'weak'=>array('sendco','senco'),
                'why'=>'The SEND information report is legally required (Children and Families Act 2014).'),
            array('key'=>'pupil_premium','label'=>'Pupil Premium strategy','statutory'=>true,
                'strong'=>array('pupil premium strategy','pupil premium'),
                'weak'=>array(),
                'why'=>'You must publish how pupil premium funding is spent and its impact.'),
            array('key'=>'pe_premium','label'=>'PE & sport premium','statutory'=>true,
                'strong'=>array('pe and sport premium','pe & sport premium','sports premium','sport premium'),
                'weak'=>array('pe premium'),
                'why'=>'Schools must report PE and sport premium spending and impact.'),
            array('key'=>'equality','label'=>'Equality objectives','statutory'=>true,
                'strong'=>array('equality objectives','equality policy'),
                'weak'=>array('equality'),
                'why'=>'Public bodies must publish equality objectives (Equality Act 2010).'),
            array('key'=>'financial','label'=>'Financial information','statutory'=>true,
                'strong'=>array('financial benchmarking','schools financial benchmarking','benchmarking service'),
                'weak'=>array('£100,000','£100000','100k salaries'),
                'why'=>'Schools must publish high-earner salary bands and a link to the DfE benchmarking service.'),
            array('key'=>'accessibility','label'=>'Accessibility statement','statutory'=>true,
                'strong'=>array('accessibility statement'),
                'weak'=>array('accessibility'),
                'why'=>'Public sector websites legally need an accessibility statement.'),
            array('key'=>'safeguarding','label'=>'Safeguarding / child protection','statutory'=>true,
                'strong'=>array('safeguarding policy','child protection policy','safeguarding'),
                'weak'=>array('designated safeguarding'),
                'why'=>'Safeguarding policies are required and must be easy for parents to find.'),
            array('key'=>'values','label'=>'Values & ethos','statutory'=>false,
                'strong'=>array('values and ethos','our ethos','our values','school vision'),
                'weak'=>array('ethos','vision statement','british values'),
                'why'=>'DfE recommends publishing your ethos and values — good practice for parents.'),
            array('key'=>'term_dates','label'=>'Term dates','statutory'=>false,
                'strong'=>array('term dates','term time'),
                'weak'=>array('holiday dates'),
                'why'=>'Term dates are not a DfE publishing requirement, but parents expect them.'),
        );
    }

    private function check_all($pages, $docs, $https_ok, $home_html) {
        $items = array();
        // Pre-build searchable evidence strings for linked documents
        // (anchor text + file URL), e.g. "Behaviour Policy .../behaviour-policy-2026.pdf".
        $doc_evidence = array();
        foreach ($docs as $dc) {
            $doc_evidence[] = array(
                'text' => mb_strtolower(trim($dc['anchor'] . ' ' . $dc['url'] . ' ' . $dc['page'])),
                'url'  => $dc['url'],
            );
        }
        foreach ($this->definitions() as $def) {
            $found_on = null; $weak_on = null; $via_doc = false;
            foreach ($pages as $url => $pd) {
                $text = mb_strtolower($pd['text'] . ' ' . $pd['title']);
                if ($this->match_any($text, $def['strong'])) { $found_on = $url; break; }
                if ($weak_on === null && $this->match_any($text, $def['weak'])) { $weak_on = $url; }
            }
            // If page text did not confirm it, check linked documents
            // (schools publish most policies as PDFs).
            if (!$found_on) {
                foreach ($doc_evidence as $de) {
                    if ($this->match_any($de['text'], $def['strong'])) { $found_on = $de['url']; $via_doc = true; break; }
                    if ($weak_on === null && $this->match_any($de['text'], $def['weak'])) { $weak_on = $de['url']; $via_doc = true; }
                }
            }
            if ($found_on)      { $status = 'found'; }
            elseif ($weak_on)   { $status = 'uncertain'; $found_on = $weak_on; }
            else                { $status = 'missing'; }
            $items[] = array(
                'key' => $def['key'], 'label' => $def['label'], 'status' => $status,
                'why' => $def['why'], 'found_on' => $found_on, 'via_doc' => $via_doc,
                'statutory' => isset($def['statutory']) ? (bool) $def['statutory'] : true,
            );
        }

        // Technical checks (homepage HTML only) — not DfE requirements.
        $items[] = array(
            'key' => 'https', 'label' => 'Secure HTTPS connection', 'found_on' => null,
            'status' => $https_ok ? 'found' : 'missing', 'statutory' => false,
            'why' => 'HTTPS protects visitors, and Google ranks secure sites higher.',
        );
        $items[] = array(
            'key' => 'viewport', 'label' => 'Mobile-friendly (viewport)',
            'status' => preg_match('#<meta[^>]+name=["\']viewport["\']#i', $home_html) ? 'found' : 'missing',
            'found_on' => null, 'statutory' => false,
            'why' => 'Most parents browse on phones — your site must work on mobile.',
        );
        $title_ok = preg_match('#<title[^>]*>(.*?)</title>#is', $home_html, $tm) && mb_strlen(trim(strip_tags($tm[1]))) >= 10;
        $items[] = array(
            'key' => 'title', 'label' => 'Descriptive page title',
            'status' => $title_ok ? 'found' : 'missing', 'found_on' => null,
            'why' => 'A clear title tag helps Google understand and rank your pages.',
        );
        $desc_ok = preg_match('#<meta[^>]+name=["\']description["\'][^>]*content=["\']([^"\']{20,})#i', $home_html);
        $items[] = array(
            'key' => 'meta_desc', 'label' => 'Meta description',
            'status' => $desc_ok ? 'found' : 'missing', 'found_on' => null,
            'why' => 'Meta descriptions control how your school appears in Google results.',
        );
        return $items;
    }

    /** Case-insensitive phrase match with flexible whitespace/hyphens + word boundaries. */
    private function match_any($text, $phrases) {
        foreach ($phrases as $p) {
            $p = trim(mb_strtolower($p));
            if ($p === '') continue;
            $rx = '/\b' . str_replace('\ ', '[\s\-_]+', preg_quote($p, '/')) . '/u';
            // Allow a trailing "s" (policy/policies handled by phrase list instead).
            if (preg_match($rx, $text)) return true;
        }
        return false;
    }
}
