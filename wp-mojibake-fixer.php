<?php
/**
 * Plugin Name: WP Mojibake Fixer (IT & DE)
 * Plugin URI:  https://riccardodicurti.it
 * Description: Fixes Italian and German character encoding issues (Mojibake, e.g., Ã¨, Ã¼, ÃŸ) after a database migration. Includes a Charset debug panel.
 * Version:     1.3.1
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
            wp_mojibake_fixer_execute();
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>🎉 Operation Completed!</strong> The database has been updated successfully. Please clear your cache and check the frontend.</p>';
            echo '</div>';
        }
    }

    global $wpdb;

    // Retrieve System Variables
    $db_charset = defined('DB_CHARSET') ? DB_CHARSET : '<em>Not defined</em>';
    $db_collate = defined('DB_COLLATE') ? DB_COLLATE : '<em>Not defined</em>';
    
    // Retrieve MySQL Server Variables
    $mysql_charsets = $wpdb->get_results("SHOW VARIABLES LIKE 'character_set%'");
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
        <p>The "Mojibake" issue usually occurs when there is a mismatch between the encoding of the exported file (e.g., <code>utf8</code>) and how the destination server or <code>wp-config.php</code> imports it (often falling back to <code>latin1</code> or vice versa).</p>
        
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

    // Mapping: Broken String => Correct String
    $mapping = array(
        // -- FULL WORDS (To prevent trailing "à" breaking issues) --
        'FelicitÃ'     => 'Felicità',
        'ospitalitÃ'   => 'ospitalità',
        'qualitÃ'      => 'qualità',
        'stagionalitÃ' => 'stagionalità',
        'novitÃ'       => 'novità',
        'cittÃ'        => 'città',
        'specialitÃ'   => 'specialità',
        'QualitÃ¤t'    => 'Qualität', 
        
        // -- GERMAN CHARACTERS (Umlauts & Eszett) --
        'Ã¤'   => 'ä',
        'Ã¶'   => 'ö',
        'Ã¼'   => 'ü',
        'Ã„'   => 'Ä',
        'Ã–'   => 'Ö',
        'Ãœ'   => 'Ü',
        'ÃŸ'   => 'ß',
        '”“'   => '–', // Broken en-dash common in DE texts

        // -- ITALIAN CHARACTERS & SYMBOLS --
        'Ã¨'   => 'è',
        'Ã©'   => 'é',
        'Ã '   => 'à',  
        'Ã¬'   => 'ì',
        'Ã²'   => 'ò',
        'Ã¹'   => 'ù',
        'Ãˆ'   => 'È',
        'â€™'  => '’', // Typographic Apostrophe
        'â€˜'  => '‘', // Left Single Quote
        'â€œ'  => '“', // Left Double Quote
        'â€'   => '”', // Right Double Quote
        'â€“'  => '–', // En-dash
        'Â©'   => '©'   // Copyright symbol
    );

    foreach ( $mapping as $broken => $correct ) {
        // Fix main content
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->posts} SET 
            post_content = REPLACE(post_content, %s, %s),
            post_title = REPLACE(post_title, %s, %s),
            post_excerpt = REPLACE(post_excerpt, %s, %s)",
            $broken, $correct, 
            $broken, $correct, 
            $broken, $correct
        ) );
        
        // Fix meta data (Crucial for Page Builders like Elementor, ACF, etc.)
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->postmeta} SET 
            meta_value = REPLACE(meta_value, %s, %s)",
            $broken, $correct
        ) );
    }
}
