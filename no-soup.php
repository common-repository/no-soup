<?php
/*
Plugin Name: No Soup
Plugin URI: http://jonasnordstrom.se/plugins/no-soup/
Description: Blocks user from IP-ranges that you specify and sends them to another page
Version: 0.3
Author: Jonas Nordström
Author URI: http://jonasnordstrom.se/ 
*/

/**
 * Copyright (c) 2012 Jonas Nordstrom. All rights reserved.
 *
 * Released under the GPL license
 * http://www.opensource.org/licenses/gpl-license.php
 *
 * This is an add-on for WordPress
 * http://wordpress.org/
 *
 * **********************************************************************
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * **********************************************************************
 */

if (!class_exists("NoSoup")) {
	class NoSoup {
		
		protected $bad_ip_ranges;
		protected $eat_that;
		
		public function NoSoup() {
			$this->bad_ip_ranges = get_option('_no_soup_bad_ip_ranges', '');
			$this->eat_that = get_option('_no_soup_eat_that', '');			
		}
		public function init_admin() {
			add_options_page(__('No Soup', "nosoup"), __('No Soup', "nosoup"), 'manage_options', basename(__FILE__), array(&$this, 'no_soup_admin_page') );
		}
		
		public function no_soup_admin_page() {
			// Handle updates
			if( !empty($_POST) && $_POST[ 'action' ] == 'no-soup-save' ) {
				check_admin_referer('no-soup-save', 'no-soup-nonce');
				$this->bad_ip_ranges = esc_html($_POST[ 'no_soup_bad_ip_ranges' ]);
				update_option('_no_soup_bad_ip_ranges', $this->bad_ip_ranges );
				$this->eat_that = esc_html($_POST[ 'no_soup_eat_that' ]);
				update_option('_no_soup_eat_that', $this->eat_that );
				?>
				<div class="updated"><p><strong><?php _e('Settings saved.', 'nosoup' ); ?></strong></p></div>
				<?php
			}
			
			?>
			<div class="wrap">
			<h2><?php _e("No Soup", "nosoup"); ?></h2>
			<p>Block users from a specific IP or range(s) of IPs and redirect them to another site.</p>
			
			<form name="no-soup-admin-form" method="post" action="">
				<input type="hidden" name="action" value="no-soup-save" />
				<?php wp_nonce_field('no-soup-save', 'no-soup-nonce'); ?>
				<table class="nosoup-form-table">
					<tr>
						<td colspan="2">
							&nbsp;
						</td>
					</tr>
					<tr>
						<td valign="top"><?php _e("Block these ip ranges<br/>Separate with new-line<br/> CIDR format (192.168.0.1/20)<br/> or IP range (fromip - toip)): ", "nosoup"); ?></td>
						<td><textarea rows="25" cols="50" id="no_soup_bad_ip_ranges" name="no_soup_bad_ip_ranges"><?php echo $this->bad_ip_ranges; ?></textarea></td>
					</tr>
					<tr>
						<td><?php _e("Send them here: ", "nosoup"); ?></td>
						<td><input type="text" style="width: 100%" id="no_soup_eat_that" name="no_soup_eat_that" value="<?php echo $this->eat_that; ?>" /></td>
					</tr>
					<tr>
						<td colspan="2">
							<p class="submit">
								<input type="submit" name="Submit" value="<?php _e('Update settings', 'nosoup' ) ?>" />
							</p>
						</td>
					</tr>

				</table>
			</form>
			</div>
			<?php
		}
		
		function check_ip() {
			$current_url = $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
			
			// Test if the client is bad (exception, the redirect-to-page)
			if ( $current_url != $this->eat_that && $this->banned_ip() ) :
				wp_redirect( $this->eat_that );
				die;
			endif;
		}
		
		private function banned_ip() {
			$ip = $_SERVER['REMOTE_ADDR'];
			$networks = preg_split("/[\r\n,]+/", $this->bad_ip_ranges); 

			foreach ( $networks as $range ) :
				if ( false === strpos($range, '-')) :
					if ( 3 <= substr_count( $range, '.' ) && $this->in_cidr( $range, $ip ) ) :
 						return true;
					endif;
				else :
					if ( $this->in_ip_range($range, $ip)) :
						return true;
					endif;
				endif;
			endforeach;
			return false;
		}
		
		function in_cidr($network, $ip) {
			$ip_arr = explode('/', $network);
			
			$network_long = ip2long($ip_arr[0]);

			$x = ip2long($ip_arr[1]);
			$mask =  long2ip($x) == $ip_arr[1] ? $x : 0xffffffff << (32 - $ip_arr[1]);
			$ip_long = ip2long($ip);

			return ($ip_long & $mask) == ($network_long & $mask);
		}
		
		function in_ip_range($range, $ip){
			$range = preg_replace("'\s+'", '', $range);
			if ( false === strpos( $range, '-') ) :
				return ( $ip_start == $ip );
			else :
				list($ip_start, $ip_end) = preg_split('/-/', $range);
				$iplong = ip2long( $ip );
				return ( ip2long( $ip_start ) <= $iplong && ip2long( $ip_end ) >= $iplong );
			endif;
		}
	}
}
if ( ! function_exists( '_log' ) ) {
	function _log() {
		if( WP_DEBUG === true ) {
			$args = func_get_args();
			error_log( print_r( $args, true ) );
		}
	}
}
// Init class
if (class_exists("NoSoup")) {
	$nosoup = new NoSoup();
}

add_action( 'admin_menu', array(&$nosoup, 'init_admin') );
add_action( 'init', array(&$nosoup, 'check_ip') );
