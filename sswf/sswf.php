<?php
/*
Plugin Name: Stupid Simple Word Filter
Description: Easily manage prohibited words and phrases in comments or forms.
Version: 1.0
Author: Dynamic Technologies
Author URI: http://bedynamic.tech
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Add admin menu
function sswf_add_menu() {
    add_menu_page(
        'Stupid Simple',
        'Stupid Simple',
        'manage_options',
        'stupidsimple',
        function () {
            wp_redirect('https://bedynamic.tech/stupid-simple/');
            exit;
        },
        'dashicons-hammer',
        99
    );

    add_submenu_page(
        'stupidsimple',
        'Word Filter Settings',
        'Word Filter',
        'manage_options',
        'stupid-simple-word-filter',
        'sswf_settings_page_content'
    );
}
add_action( 'admin_menu', 'sswf_add_menu' );

// Display settings page content
function sswf_settings_page_content() {
    ?>
    <div class="wrap">
        <h1>Word Filter</h1>

        <?php
        // Handle form submission for adding a word
        if ( isset( $_POST['sswf_word_to_add'] ) && check_admin_referer( 'sswf_save_words', 'sswf_nonce' ) ) {
            $new_word = sanitize_text_field( $_POST['sswf_word_to_add'] );
            $prohibited_words = get_option( 'sswf_prohibited_words', array() );

            // Add the new word only if it doesn't already exist
            if ( ! in_array( $new_word, $prohibited_words ) ) {
                $prohibited_words[] = $new_word;
                update_option( 'sswf_prohibited_words', $prohibited_words );
                echo '<div class="updated"><p>Word or Phrase Added!</p></div>';
            } else {
                echo '<div class="error"><p>This word/phrase is already in the list.</p></div>';
            }
        }

        // Handle form submission for deleting a word
        if ( isset( $_POST['word_to_delete'] ) && check_admin_referer( 'sswf_save_words', 'sswf_nonce' ) ) {
            $prohibited_words = get_option( 'sswf_prohibited_words', array() );
            $word_to_delete = sanitize_text_field( $_POST['word_to_delete'] );

            // Remove word from the list
            $prohibited_words = array_filter( $prohibited_words, function( $word ) use ( $word_to_delete ) {
                return $word !== $word_to_delete;
            });
            $prohibited_words = array_values( $prohibited_words ); // Reindex array
            update_option( 'sswf_prohibited_words', $prohibited_words );
            echo '<div class="updated"><p>Word or Phrase Removed!</p></div>';
        }
        ?>

        <form method="post" action="">
            <?php wp_nonce_field( 'sswf_save_words', 'sswf_nonce' ); ?>
            <p>
                <input type="text" name="sswf_word_to_add" id="sswf_word_to_add" class="regular-text" style="width: auto; display: inline-block;" />
                <input type="submit" value="Add to Blocklist" class="button button-primary" />
            </p>
        </form>

        <h2>Currently Prohibited Words/Phrases</h2>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th>Word/Phrase</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $prohibited_words = get_option( 'sswf_prohibited_words', array() );
                if ( ! empty( $prohibited_words ) ) {
                    foreach ( $prohibited_words as $word ) {
                        echo '<tr>';
                        echo '<td>' . esc_html( $word ) . '</td>';
                        echo '<td>
                                <form method="post" action="">
                                    ' . wp_nonce_field( 'sswf_save_words', 'sswf_nonce', false, false ) . '
                                    <input type="hidden" name="word_to_delete" value="' . esc_attr( $word ) . '" />
                                    <input type="submit" value="Delete" class="button button-secondary" />
                                </form>
                              </td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="2">No prohibited words or phrases added yet.</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
}

// Register the settings
function sswf_register_settings() {
    register_setting( 'sswf_options_group', 'sswf_prohibited_words' );
}
add_action( 'admin_init', 'sswf_register_settings' );

// Block prohibited words in comments
function sswf_block_prohibited_words_in_comment( $comment_data ) {
    $prohibited_words = get_option( 'sswf_prohibited_words', array() );
    foreach ( $prohibited_words as $word ) {
        if ( stripos( $comment_data['comment_content'], $word ) !== false ) {
            wp_die( 'Your comment contains prohibited words or phrases.' );
        }
    }
    return $comment_data;
}
add_filter( 'preprocess_comment', 'sswf_block_prohibited_words_in_comment' );

// Block prohibited words in form submissions
function sswf_block_prohibited_words_in_form_submission( $content ) {
    $prohibited_words = get_option( 'sswf_prohibited_words', array() );
    foreach ( $prohibited_words as $word ) {
        if ( stripos( $content, $word ) !== false ) {
            wp_die( 'Your form submission contains prohibited words or phrases.' );
        }
    }
    return $content;
}
add_filter( 'preprocess_comment', 'sswf_block_prohibited_words_in_form_submission', 10, 2 );
?>
