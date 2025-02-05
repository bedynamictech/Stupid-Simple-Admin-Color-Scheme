<?php
/*
Plugin Name: Stupid Simple Login Logo
description: Easily change the logo displayed on the Login page.
Version: 1.0
Author: Dynamic Technologies
Author URI: http://bedynamic.tech
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

/* Settings to manage WP login logo */
function register_custom_logo_settings() 
{
    // Register settings with proper sanitization callbacks
    register_setting( 
        'change_login_options_group', 
        'wp_logo_url', 
        array(
            'type' => 'string', 
            'sanitize_callback' => 'esc_url_raw', // Sanitize as a valid URL
            'default' => NULL,
        )
    );
    register_setting( 
        'change_login_options_group', 
        'wp_logo_height', 
        array(
            'type' => 'string', 
            'sanitize_callback' => 'sanitize_text_field', // Sanitize as plain text
            'default' => NULL,
        )
    );
    register_setting( 
        'change_login_options_group', 
        'wp_logo_width', 
        array(
            'type' => 'string', 
            'sanitize_callback' => 'sanitize_text_field', // Sanitize as plain text
            'default' => NULL,
        )
    );
}
add_action( 'admin_init', 'register_custom_logo_settings' );


function register_login_logo_setting_page() {
  add_options_page('Login Logo', 'Login Logo', 'manage_options', 'change-login-logo', 'change_wordpress_login_logo');
}
add_action('admin_menu', 'register_login_logo_setting_page');

function change_wordpress_login_logo()
{
	wp_enqueue_script('jquery');
	wp_enqueue_media();
	?>
	<div class="wrap">
		<h1>Login Logo Settings</h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'change_login_options_group' ); ?>
			<?php do_settings_sections( 'change_login_options_group' ); ?>
			<table class="form-table">
				<tr valign="top">
					<th scope="row">Logo Image</th>
					<td>
						<input type="text" id="wp_logo_url" name="wp_logo_url" value="<?php echo esc_attr( get_option('wp_logo_url') ); ?>" />
						<input type="button" name="logo-btn" id="logo-btn" class="button-secondary" value="<?php echo esc_attr( get_option('wp_logo_url') ) ? 'Remove Logo' : 'Upload Logo'; ?>">
						<p class="description"><i>The selected image will replace the default WordPress logo on the login page.</i></p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Logo Height</th>
					<td>
						<input type="number" name="wp_logo_height" value="<?php echo esc_attr( get_option('wp_logo_height') ); ?>" />					
						<p class="description"><i>Optional</i></p>

					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Login Width</th>
					<td>
						<input type="number" name="wp_logo_width" value="<?php echo esc_attr( get_option('wp_logo_width') ); ?>" />
						<p class="description"><i>Optional</i></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<script type="text/javascript">
	jQuery(document).ready(function($){
		var logoUrlInput = $('#wp_logo_url');
		var logoBtn = $('#logo-btn');

		// Function to update the button text
		function updateButtonText() {
			if (logoUrlInput.val()) {
				logoBtn.val('Remove Logo');
			} else {
				logoBtn.val('Upload Logo');
			}
		}

		// Initial button text update
		updateButtonText();

		// Upload Logo Button
		logoBtn.click(function(e) {
			e.preventDefault();
			if (logoBtn.val() === 'Remove Logo') {
				// Remove Logo
				$.ajax({
					url: '<?php echo esc_url( admin_url('admin-ajax.php') ); ?>',
					type: 'POST',
					data: {
						action: 'remove_login_logo',
						nonce: '<?php echo esc_js( wp_create_nonce('remove_login_logo_nonce') ); ?>'
					},
					success: function(response) {
						if (response.success) {
							logoUrlInput.val(''); // Clear the logo URL field
							updateButtonText(); // Update button text
							location.reload(); // Reload the page to reflect changes
						}
					}
				});
			} else {
				// Upload Logo
				var image = wp.media({ 
					title: 'Select Image',
					multiple: false
				}).open()
				.on('select', function(e){
					var uploaded_image = image.state().get('selection').first();
					var image_url = uploaded_image.toJSON().url;
					logoUrlInput.val(image_url);
					updateButtonText(); // Update button text after selecting an image
					$('form').submit(); // Submit the form to save changes
				});
			}
		});
	});
	</script>
	</div>
	<?php
}

/* AJAX handler to remove the logo */
function remove_login_logo() {
	check_ajax_referer('remove_login_logo_nonce', 'nonce'); // Verify nonce
	update_option('wp_logo_url', ''); // Clear the logo URL
	wp_send_json_success(); // Send success response
}
add_action('wp_ajax_remove_login_logo', 'remove_login_logo');

/* Custom WordPress admin login header logo */
function wordpress_custom_login_logo() {
    $logo_url = esc_url( get_option('wp_logo_url') ); // Sanitize URL
    $wp_logo_height = sanitize_text_field( get_option('wp_logo_height') ); // Sanitize height
    $wp_logo_width = sanitize_text_field( get_option('wp_logo_width') ); // Sanitize width

    if (empty($wp_logo_height)) {
        $wp_logo_height = '100px';
    } else {
        $wp_logo_height .= 'px';
    }

    if (empty($wp_logo_width)) {
        $wp_logo_width = '100%';
    } else {
        $wp_logo_width .= 'px';
    }

    if (!empty($logo_url)) {
        echo '<style type="text/css">'.
             'h1 a { 
                background-image:url('.esc_url($logo_url).') !important;
                height:'.esc_attr($wp_logo_height).' !important;
                width:'.esc_attr($wp_logo_width).' !important;
                background-size:100% !important;
                line-height:inherit !important;
                }'.
         '</style>';
    }
}
add_action( 'login_head', 'wordpress_custom_login_logo' );

/* Add action links to plugin list*/
add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'add_change_wordpress_login_logo_action_links' );
function add_change_wordpress_login_logo_action_links ( $links ) {
	$settings_link = array('<a href="' . esc_url( admin_url('options-general.php?page=change-login-logo') ) . '">Logo Settings</a>');
	return array_merge( $links, $settings_link );
}

/*Change login logo URL*/
add_filter( 'login_headerurl', 'change_login_logo_url' );
function change_login_logo_url($url) {
	return esc_url( home_url() );
}
?>