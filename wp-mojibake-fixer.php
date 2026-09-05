<?php
/**
 * Plugin Name: WP Mojibake Fixer (IT & DE)
 * Plugin URI:  https://riccardodicurti.it
 * Description: Fixes Italian and German character encoding issues (Mojibake, e.g., Ã¨, Ã¼, ÃŸ, and the tricky "Ã + NBSP" => à) after a database migration. Includes a Charset debug panel.
 * Version:     1.4.1
 * Author:      Riccardo Di Curti
 * Author URI:  https://riccardodicurti.it
 * License:     GPL-2.0+
 */

// Security: Prevent direct access to the file
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 1. Add the menu item under "Tools"
add_action( 'admin_menu', 'wp_mojibake_fixer_add_menu' );
function wp_mojibake_fixer_add_menu() {
    add_submenu_page(
        'tools.php',
        'Fix Mojibake IT/DE',
        'Fix Mojibake IT/DE',
        'manage_options',
        'wp-mojibake-fixer',
        'wp_mojibake_fixer_render_page'
    );
}

// 2. Render the GUI, Handle Button Click, and Display Debug Panel
function wp_mojibake_fixer_render_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'You do not have sufficient permissions to access this page.' );
    }

    // Handle form submission
    if ( isset( $_POST['wp_mojibake_submit'] ) ) {
        if ( check_admin_referer( 'run_mojibake_fix', 'wp_mojibake_nonce' ) ) {
            $affected = wp_mojibake_fixer_execute();
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>🎉 Operation Completed!</strong> The database has been updated successfully (' . intval( $affected ) . ' rows touched). Please clear your cache and check the frontend.</p>';
            echo '</div>';
        }
    }

    global $wpdb;

    // Retrieve System Variables
    $db_charset = defined('DB_CHARSET') ? DB_CHARSET : '<em>Not defined</em>';
    $db_collate = defined('DB_COLLATE') ? DB_COLLATE : '<em>Not defined</em>';

    // Retrieve MySQL Server Variables
    $mysql_charsets   = $wpdb->get_results("SHOW VARIABLES LIKE 'character_set%'");
    $mysql_collations = $wpdb->get_results("SHOW VARIABLES LIKE 'collation%'");

    ?>
    <div class="wrap">
        <h1>Character Encoding Fixer & Debugger (IT & DE)</h1>
        <p>This tool scans your database (Posts and Postmeta tables) to find and replace broken characters caused by encoding mismatches.</p>

        <div class="notice notice-warning inline" style="margin-top: 15px; margin-bottom: 20px;">
            <p><strong>⚠️ IMPORTANT:</strong> Always perform a full database backup before running this tool. Database changes are irreversible.</p>
        </div>

        <form method="post" action="" style="margin-bottom: 30px;">
            <?php wp_nonce_field( 'run_mojibake_fix', 'wp_mojibake_nonce' ); ?>
            <p class="submit">
                <input type="submit" name="wp_mojibake_submit" id="submit" class="button button-primary button-large" value="Run Database Fix">
            </p>
        </form>

        <hr>

        <h2>🔍 Debug: Why did this happen?</h2>
        <p>The "Mojibake" issue usually occurs when there is a mismatch between the encoding of the exported file (e.g., <code>utf8</code>) and how the destination server or <code>wp-config.php</code> imports it (often falling back to <code>latin1</code> / <code>Windows-1252</code> or vice versa).</p>

        <div style="display: flex; gap: 20px; flex-wrap: wrap;">

            <!-- WordPress Constants Table -->
            <div style="background: #fff; padding: 15px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); min-width: 300px;">
                <h3>Constants in wp-config.php</h3>
                <p>How WordPress communicates with the database.</p>
                <table class="wp-list-table widefat fixed striped">
                    <tbody>
                        <tr><td><strong>DB_CHARSET</strong></td><td><code><?php echo wp_kses_post($db_charset); ?></code></td></tr>
                        <tr><td><strong>DB_COLLATE</strong></td><td><code><?php echo wp_kses_post($db_collate); ?></code></td></tr>
                    </tbody>
                </table>
            </div>

            <!-- MySQL Variables Table -->
            <div style="background: #fff; padding: 15px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); min-width: 400px; flex-grow: 1;">
                <h3>MySQL Server Variables (Actual Database)</h3>
                <p>How the server is currently handling data.</p>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr><th>Variable</th><th>Value</th></tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach($mysql_charsets as $var) {
                            echo "<tr><td><strong>" . esc_html($var->Variable_name) . "</strong></td><td><code>" . esc_html($var->Value) . "</code></td></tr>";
                        }
                        foreach($mysql_collations as $var) {
                            echo "<tr><td><strong>" . esc_html($var->Variable_name) . "</strong></td><td><code>" . esc_html($var->Value) . "</code></td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <p style="margin-top: 30px; font-size: 13px; color: #666;">
            Developed by <a href="https://riccardodicurti.it" target="_blank">Riccardo Di Curti</a>.
        </p>
    </div>
    <?php
}

// 3. The Core Plugin Logic
function wp_mojibake_fixer_execute() {
    global $wpdb;
    $affected = 0;

    /**
     * Mapping: Broken byte-sequence => Correct character.
     *
     * IMPORTANT — why the previous version missed every "à":
     *   "à" (U+00E0) is stored in UTF-8 as the bytes  C3 A0.
     *   Read by mistake as Windows-1252 it becomes:   Ã (C3) + 0xA0.
     *   But 0xA0 is a NON-BREAKING SPACE (NBSP), NOT a normal space (0x20).
     *   The old rule  'Ã ' => 'à'  used a regular space, so it never matched.
     *
     *   "À" (U+00C0) is stored as  C3 80. Read as Windows-1252, the byte
     *   0x80 maps to the Euro sign €, so it surfaces as "Ã€".
     *
     * IMPORTANT — v1.4.1 fix for the German low opening quote „:
     *   „ (U+201E) is stored in UTF-8 as bytes E2 80 9E.
     *   Read as Windows-1252 that becomes:  â (E2) + € (80) + ž (9E)
     *   i.e. the mojibake string "â€ž".
     *   The old mapping only had the generic fallback 'â€' => '”' (used
     *   for the plain right double quote), and since REPLACE() matches
     *   substrings, that generic rule fired INSIDE "â€ž" too, consuming
     *   only the "â€" part and leaving the trailing "ž" orphaned. That
     *   produced the broken "”ž" you saw on the live site instead of „.
     *   The fix: add a dedicated rule for 'â€ž' BEFORE the generic
     *   fallback (order matters, same as for the other multi-byte
     *   sequences below) — plus a cleanup rule for '”ž', because the
     *   site was already run once and the DB now contains that
     *   half-converted artifact instead of the original mojibake.
     */
    $mapping = array(

        // === THE REAL FIX (lowercase à and uppercase À) ===
        "\xC3\x83\xC2\xA0"     => 'à',  // Ã + NBSP (C3 83 C2 A0) => à
        "\xC3\x83\xE2\x82\xAC" => 'À',  // Ã + €    (C3 83 E2 82 AC) => À

        // === GERMAN CHARACTERS (Umlauts & Eszett) ===
        'Ã¤' => 'ä',
        'Ã¶' => 'ö',
        'Ã¼' => 'ü',
        'Ã„' => 'Ä',
        'Ã–' => 'Ö',
        'Ãœ' => 'Ü',
        'ÃŸ' => 'ß',

        // === ITALIAN CHARACTERS ===
        'Ã¨' => 'è',
        'Ã©' => 'é',
        'Ã¬' => 'ì',
        'Ã²' => 'ò',
        'Ã¹' => 'ù',
        'Ãˆ' => 'È',

        // === TYPOGRAPHIC PUNCTUATION ===
        // Order matters: longer / more specific 'â€x' sequences MUST come
        // before the bare 'â€', otherwise the short rule eats them first
        // and leaves trailing bytes orphaned (see â€ž note above).
        'â€ž' => '„',  // German opening low quote (NEW in 1.4.1)
        'â€™' => '’',  // Typographic apostrophe
        'â€˜' => '‘',  // Left single quote
        'â€œ' => '“',  // Left double quote
        'â€“' => '–',  // En-dash
        'â€”' => '—',  // Em-dash
        'â€'  => '”',  // Right double quote (keep LAST in this group)
        'Â©'  => '©',  // Copyright symbol

        // === CLEANUP for the half-converted state left by v1.4.0 ===
        // A previous run already turned 'â€ž' into '”ž' (see note above).
        // This catches that intermediate artifact directly.
        '”ž' => '„',   // NEW in 1.4.1
    );

    foreach ( $mapping as $broken => $correct ) {

        // Fix main content
        $affected += (int) $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->posts} SET
                post_content = REPLACE(post_content, %s, %s),
                post_title   = REPLACE(post_title,   %s, %s),
                post_excerpt = REPLACE(post_excerpt, %s, %s)",
            $broken, $correct,
            $broken, $correct,
            $broken, $correct
        ) );

        // Fix meta data (crucial for page builders: Salient/WPBakery, ACF, Elementor, etc.)
        $affected += (int) $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->postmeta} SET
                meta_value = REPLACE(meta_value, %s, %s)",
            $broken, $correct
        ) );
    }

    return $affected;
}
