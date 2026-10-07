<?php
/**
 * Altus School Website Compliance Checker — WP admin: leads list + settings.
 */

if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'asc_admin_menu');
function asc_admin_menu() {
    add_menu_page(
        'School Checker Leads', 'School Checker', 'manage_options',
        'asc-leads', 'asc_leads_page', 'dashicons-clipboard', 30
    );
    add_submenu_page('asc-leads', 'Leads', 'Leads', 'manage_options', 'asc-leads', 'asc_leads_page');
    add_submenu_page('asc-leads', 'Settings', 'Settings', 'manage_options', 'asc-settings', 'asc_settings_page');
}

function asc_leads_page() {
    global $wpdb;
    $table = $wpdb->prefix . ASC_TABLE;

    // Delete action.
    if (isset($_GET['delete']) && check_admin_referer('asc_delete_' . (int) $_GET['delete'])) {
        $wpdb->delete($table, array('id' => (int) $_GET['delete']), array('%d'));
        echo '<div class="notice notice-success"><p>Lead deleted.</p></div>';
    }

    $leads = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 500", ARRAY_A);
    ?>
    <div class="wrap">
        <h1>School Checker — Leads <span class="subtitle">(<?php echo count($leads); ?>)</span></h1>
        <p>Every completed compliance check that unlocked the full report. Hottest leads first (lowest scores).</p>
        <table class="widefat striped">
            <thead><tr>
                <th>Date</th><th>Name</th><th>Phone</th><th>Email</th><th>School</th><th>URL</th><th>Score</th><th></th>
            </tr></thead>
            <tbody>
            <?php if (!$leads): ?>
                <tr><td colspan="8">No leads yet. Add the <code>[altus_school_checker]</code> shortcode to a page to start collecting.</td></tr>
            <?php else: ?>
                <?php foreach ($leads as $l):
                    $del = wp_nonce_url(admin_url('admin.php?page=asc-leads&delete=' . $l['id']), 'asc_delete_' . $l['id']);
                    $color = $l['score'] >= 70 ? '#17803d' : ($l['score'] >= 50 ? '#b26a00' : '#c0392b');
                ?>
                <tr>
                    <td><?php echo esc_html($l['created_at']); ?></td>
                    <td><?php echo esc_html($l['name']); ?></td>
                    <td><?php echo isset($l['phone']) && $l['phone'] !== '' ? '<a href="tel:' . esc_attr(preg_replace('/[^\d+]/', '', $l['phone'])) . '">' . esc_html($l['phone']) . '</a>' : '—'; ?></td>
                    <td><a href="mailto:<?php echo esc_attr($l['email']); ?>"><?php echo esc_html($l['email']); ?></a></td>
                    <td><?php echo esc_html($l['school_name']); ?></td>
                    <td><a href="<?php echo esc_url($l['school_url']); ?>" target="_blank" rel="noopener"><?php echo esc_html(wp_parse_url($l['school_url'], PHP_URL_HOST)); ?></a></td>
                    <td style="font-weight:bold;color:<?php echo $color; ?>"><?php echo (int) $l['score']; ?>% (<?php echo esc_html($l['grade']); ?>)</td>
                    <td><a href="<?php echo esc_url($del); ?>" onclick="return confirm('Delete this lead?')">Delete</a></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        <h2>How to use</h2>
        <ol>
            <li>Create a page (e.g. <em>Free School Website Compliance Check</em>) and add the shortcode <code>[altus_school_checker]</code>.</li>
            <li>Promote it on social media and the blog — every completed check is a qualified lead, emailed to you instantly.</li>
            <li>Low scores are your hottest leads: their site needs the most help.</li>
        </ol>
    </div>
    <?php
}

function asc_settings_page() {
    if (isset($_POST['asc_save']) && check_admin_referer('asc_settings')) {
        update_option('asc_settings', array(
            'notify_email' => sanitize_email($_POST['notify_email']),
            'book_url'     => esc_url_raw($_POST['book_url']),
            'from_name'    => sanitize_text_field($_POST['from_name']),
            'max_pages'    => max(3, min(25, (int) $_POST['max_pages'])),
        ));
        echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
    }
    $s = asc_get_settings();
    ?>
    <div class="wrap">
        <h1>School Checker — Settings</h1>
        <form method="post">
            <?php wp_nonce_field('asc_settings'); ?>
            <table class="form-table">
                <tr><th><label for="notify_email">Lead notification email</label></th>
                    <td><input type="email" id="notify_email" name="notify_email" class="regular-text" value="<?php echo esc_attr($s['notify_email']); ?>">
                    <p class="description">Each new lead is emailed here instantly.</p></td></tr>
                <tr><th><label for="book_url">Consultation booking URL</label></th>
                    <td><input type="url" id="book_url" name="book_url" class="regular-text" value="<?php echo esc_attr($s['book_url']); ?>"></td></tr>
                <tr><th><label for="from_name">Email sender name</label></th>
                    <td><input type="text" id="from_name" name="from_name" class="regular-text" value="<?php echo esc_attr($s['from_name']); ?>"></td></tr>
                <tr><th><label for="max_pages">Max pages to scan</label></th>
                    <td><input type="number" id="max_pages" name="max_pages" min="3" max="25" value="<?php echo (int) $s['max_pages']; ?>">
                    <p class="description">Homepage + this many internal pages. Higher is slower but more thorough.</p></td></tr>
            </table>
            <p><button class="button button-primary" name="asc_save" value="1">Save settings</button></p>
        </form>
    </div>
    <?php
}
