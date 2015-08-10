<?php
/**
 * @package Lock-By-LDAP-Query
 * @version 1.0.1
 */
/*
Plugin Name: Lock By LDAP Query
Plugin URI: http://wordpress.org/plugins/lock-by-ldap-query
Description: Lock a page down so that it can only be viewed by certain LDAP groups
Author: Michael George
Version: 1.0.1

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

if ( ! class_exists( "LockByLDAPQuery" ) ) {
	class LockByLDAPQuery {
		var $adminOptionsName = "LockByLDAPQueryAdminOptions";
		var $join;
		var $where;

		function __construct() {
			$this->lblqGetAdminOptions();
		}

		//Returns an array of admin options
		function lblqGetAdminOptions() {
			$lockByLDAPQueryAdminOptions = array(
								"bindhost" => ""
								,"searchbase" => ""
								,"loginfield" => ""
								,"binduser" => ""
								,"bindpassword" => ""
								,"usessl" => false
								,"referrals" => false
								,"protocolversion3" => true
								);
			$devOptions = get_option( $this->adminOptionsName );
			if ( ! empty( $devOptions ) ) {
				foreach ( $devOptions as $optionName => $optionValue ) {
					$lockByLDAPQueryAdminOptions[$optionName] = $optionValue;
				}
			}
			update_option( $this->adminOptionsName, $lockByLDAPQueryAdminOptions );
			return $lockByLDAPQueryAdminOptions;
		}

		//Store the query in the meta table
		function lblqUpdatePost( $post_id ) {
			if ( isset( $_POST['lockByLDAPQuery_query'] ) ) {
				if ( $_POST['lockByLDAPQuery_query'] != "" && strlen( trim( $_POST['lockByLDAPQuery_query'] ) ) > 0 ) {
					update_post_meta( $post_id, 'lockByLDAPQuery_query', trim( $_POST['lockByLDAPQuery_query'] ) );
					if ( isset( $_POST['lockByLDAPQuery_text'] ) && $_POST['lockByLDAPQuery_text'] != "" && strlen( trim( $_POST['lockByLDAPQuery_text'] ) ) > 0 ) {
						update_post_meta( $post_id, 'lockByLDAPQuery_text', trim( $_POST['lockByLDAPQuery_text'] ) );
					} else {
						delete_post_meta( $post_id, 'lockByLDAPQuery_text' );
					}
				} else {
					delete_post_meta( $post_id, 'lockByLDAPQuery_query' );
					delete_post_meta( $post_id, 'lockByLDAPQuery_text' );
				}
			}
			return true;
		}

		//Check to see if user is logged in and that they match the query
		//If not, replace content with notice
		function lblqVerifyQuery( $content ) {
			global $post;
			$ldapquery = get_post_meta( $post->ID, 'lockByLDAPQuery_query' );
			$optionaltext = get_post_meta( $post->ID, 'lockByLDAPQuery_text' );
			if ( count( $ldapquery ) > 0 && strlen( trim( $ldapquery[0] ) ) != 0 ) {
				$devOptions = get_option( $this->adminOptionsName );
				if ( ! current_user_can( 'manage_options' ) ) {
					if ( $devOptions['bindhost'] != "" ) {
						if ( is_user_logged_in() ) {
							$wpUser = wp_get_current_user();
							$connectStr = "ldap" . ( $devOptions['usessl'] ? "s" : "" ) . "://" . $devOptions['bindhost'];
							$ldap = ldap_connect( $connectStr );
							if ( $devOptions['referrals'] ) {
								ldap_set_option( $ldap, LDAP_OPT_REFERRALS, 1 );
							} else {
								ldap_set_option( $ldap, LDAP_OPT_REFERRALS, 0 );
							}
							if ( $devOptions['protocolversion3'] ) {
								ldap_set_option( $ldap, LDAP_OPT_PROTOCOL_VERSION, 3 );
							} else {
								ldap_set_option( $ldap, LDAP_OPT_PROTOCOL_VERSION, 2 );
							}
							if ( $devOptions['binduser'] != "" ) {
								$bind = ldap_bind( $ldap, $devOptions['binduser'], $devOptions['bindpassword'] );
							} else {
								$bind = ldap_bind( $ldap );
							}
							if ( $bind ) {
								$sr = ldap_search( $ldap, $devOptions['searchbase'], $ldapquery[0], array( $devOptions['loginfield'] ), 0, 2000 );
								if ( $sr === false ) {
									$content = "<h3>Access Denied</h3>\r";
									$content .= "<p>Access to this document is limited to certain users. There was an error while trying to determine if your account should have access.</p>\r";
									$content .= "<p>Error during LDAP search: (" . ldap_errno( $ldap ) . ") " . ldap_error( $ldap ) . ". Malformed query?</p>\r";
								} else {
									$info = ldap_get_entries( $ldap, $sr );
									if ( $info['count'] != 0 ) {
										foreach ( $info as $user ) {
											if ( isset( $user[strtolower( $devOptions['loginfield'] )][0] ) ) {
												if ( strtolower( $user[strtolower( $devOptions['loginfield'] )][0] ) == strtolower( $wpUser->user_login ) ) {
													return $content;
												}
											}
										}
										$content = "<h3>Access Denied</h3>\r";
										$content .= "<p>Access to this document is limited to certain users. You, however, are not one of them.</p>\r";
										if ( count( $optionaltext ) > 0 ) {
											$content .= $optionaltext[0];
										}
									} else {
										$content = "<h3>Access Denied</h3>\r";
										$content .= "<p>Access to this document is limited to certain users. However, no users met the LDAP search criteria.</p>\r";
									}
								}
								if ( ! is_bool( $sr ) ) {
									ldap_free_result( $sr );
								}
							} else {
								$content = "<h3>Access Denied</h3>\r";
								$content .= "<p>Access to this document is limited to certain users. There was an error while trying to determine if your account should have access.</p>\r";
								$content .= "<p>Error during LDAP bind: " . ldap_error( $ldap ) . "</p>\r";
							}
						} else {
							$content = "<h3>Access Denied</h3>\r";
							$content .= "<p>Access to this document is limited to certain users and you are not logged in. Please login <a href='wp-login.php?redirect_to=" . $_SERVER['REQUEST_URI'] . "'>here</a>.</p>\r";
						}
					}
				}
			}
			return $content;
		}

		//Gets the settings link to show on the plugin management page
		//Thanks to "Floating Social Bar" plugin as the code is humbly taken from it
		function lblqSettingsLink( $links ) {
			$setting_link = sprintf( '<a href="%s">%s</a>', add_query_arg( array( 'page' => 'lock-by-ldap-query.php' ), admin_url( 'options-general.php' ) ), __( 'Settings', 'Lock By LDAP Query' ) );
			array_unshift( $links, $setting_link );
			return $links;
		}

		//Prints out the admin page
		//Since 1.0.0
		function lblqPrintAdminPage() {
			$devOptions = $this->lblqGetAdminOptions();
			$workingURL = $_SERVER["REQUEST_URI"];

			if ( isset( $_POST['update_lblqSettings'] ) ) {
				$devOptions['bindhost'] = $_POST['lockByLDAPQuery_bindhost'];
				$devOptions['searchbase'] = $_POST['lockByLDAPQuery_searchbase'];
				$devOptions['loginfield'] = $_POST['lockByLDAPQuery_loginfield'];
				$devOptions['binduser'] = $_POST['lockByLDAPQuery_binduser'];
				$devOptions['bindpassword'] = $_POST['lockByLDAPQuery_bindpassword'];
				$devOptions['usessl'] = ( $_POST['lockByLDAPQuery_usessl'] == 'true' ? true : false );
				$devOptions['referrals'] = ( $_POST['lockByLDAPQuery_referrals'] == 'true' ? true : false );
				$devOptions['protocolversion3'] = ( $_POST['lockByLDAPQuery_protocolversion3'] == 'true' ? true : false );
				$updated = update_option($this->adminOptionsName, $devOptions);
			} else if ( isset( $_GET['spDeleteItAll'] ) && $_GET['spDeleteItAll'] == 1 ) {
				$updated = $this->spDeleteItAll();
			}

			if ( isset( $updated ) && $updated ) {
				echo "<div class='updated'><p><strong>Settings Updated.</strong></p></div>\r";
			} else if ( isset( $updated ) && ! $updated ) {
				echo "<div class='updated'><p><strong>Settings failed to update.</strong></p></div>\r";
			}
?>
<div id="lock-by-ldap-query_option_page" style="width:80%">
<form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
<input type='hidden' name='update_lblqSettings' value='1'>
<h2>Lock By LDAP Query Settings</h2><?php
			echo "<h3 style='margin-bottom: -5px;'>LDAP Host</h3>\r";
			echo "<p><input id='lockByLDAPQuery_bindhost' type='text' name='lockByLDAPQuery_bindhost' value='" . $devOptions['bindhost'] . "' style='width: 250px;'><br>\r";
			echo "The name or IP address of the LDAP server. The protocol should be left out. (e.g., ldap.example.com)</p>\r";
			echo "<h3 style='margin-bottom: -5px;'>Search Base</h3>\r";
			echo "<p><input id='lockByLDAPQuery_searchbase' type='text' name='lockByLDAPQuery_searchbase' value='" . $devOptions['searchbase'] . "' style='width: 250px;'><br>\r";
			echo "The base DN in which to carry out LDAP searches.</p>\r";
			echo "<h3 style='margin-bottom: -5px;'>Login Field</h3>\r";
			echo "<p><input id='lockByLDAPQuery_loginfield' type='text' name='lockByLDAPQuery_loginfield' value='" . $devOptions['loginfield'] . "' style='width: 250px;'><br>\r";
			echo "The attribute in LDAP that needs to match the WP login name. (e.g., sAMAccountName if using AD)</p>\r";
			echo "<h3 style='margin-bottom: -5px;'>LDAP Bind User</h3>\r";
			echo "<p><input id='lockByLDAPQuery_binduser' type='text' name='lockByLDAPQuery_binduser' value='" . $devOptions['binduser'] . "' style='width: 250px;'><br>\r";
			echo "Leave blank for anonymous bind. Should be in DN format.</p>\r";
			echo "<h3 style='margin-bottom: -5px;'>LDAP Bind Password</h3>\r";
			echo "<p><input id='lockByLDAPQuery_bindpassword' type='password' name='lockByLDAPQuery_bindpassword' value='" . $devOptions['bindpassword'] . "' style='width: 250px;'><br>\r";
			echo "The password for the user above.</p>\r";
			echo "<h3 style='margin-bottom: -5px;'>Use SSL</h3>\r";
			echo "<p><input id='lockByLDAPQuery_usessl' type='checkbox' name='lockByLDAPQuery_usessl' value='true'". ( $devOptions['usessl'] ? " checked" : "" ) . "> Enabled<br>\r";
			echo "Default is disabled (no SSL).</p>\r";
			echo "<h3 style='margin-bottom: -5px;'>Use LDAP_OPT_REFERRALS = 1</h3>\r";
			echo "<p><input id='lockByLDAPQuery_referrals' type='checkbox' name='lockByLDAPQuery_referrals' value='true'". ( $devOptions['referrals'] ? " checked" : "" ) . "> Enabled<br>\r";
			echo "Setting to disabled is often necessary when using Active Directory.</p>\r";
			echo "<h3 style='margin-bottom: -5px;'>Use LDAP V3</h3>\r";
			echo "<p><input id='lockByLDAPQuery_protocolversion3' type='checkbox' name='lockByLDAPQuery_protocolversion3' value='true'". ( $devOptions['protocolversion3'] ? " checked" : "" ) . "> Enabled<br>\r";
			echo "Default is enabled.</p>\r";
			echo "<input type='submit' value='Save'>\r";
?></form>
</div><?php
		} //End function lblqPrintAdminPage()

	} //End Class SimplePermissions

} //End if class exists

if ( class_exists( "LockByLDAPQuery" ) ) {
	$svvsd_lockByLDAPQuery = new LockByLDAPQuery();
}

//Initialize the admin panel
if ( ! function_exists( "lblqAddOptionPage" ) ) {
	function lblqAddOptionPage() {
		global $svvsd_lockByLDAPQuery;
		if ( ! isset( $svvsd_lockByLDAPQuery ) ) {
			return;
		}
		if ( function_exists( 'add_options_page' ) ) {
			add_options_page( 'Lock By LDAP Query', 'Lock By LDAP Query', 9, basename( __FILE__ ), array( &$svvsd_lockByLDAPQuery, 'lblqPrintAdminPage' ) );
		}
	}	
}


function lblqAddMetaBox() {
	add_meta_box(
 			'lockByLDAPQuery_meta_box'
			,__( 'Lock By LDAP Query' )
			,'lblqRenderMetaBox'
			,get_post_type( get_the_ID() )
			,'normal'
			,'high'
		);
}

function lblqRenderMetaBox( $post ) {
	global $svvsd_lockByLDAPQuery;
?><textarea rows=4 cols=50 spellcheck='false' name='lockByLDAPQuery_query'><?php
	$ldapquery = get_post_meta( $post->ID, 'lockByLDAPQuery_query' );
	if ( count( $ldapquery ) > 0 ) {
		echo $ldapquery[0];
	}
?></textarea>
<br><h4>Additional text to display when a user is not in LDAP query results:</h4>
<textarea rows=4 cols=50 spellcheck='false' name='lockByLDAPQuery_text'><?php
	$optionaltext = get_post_meta( $post->ID, 'lockByLDAPQuery_text' );
	if ( count( $optionaltext ) > 0 ) {
		echo $optionaltext[0];
	}
?></textarea><?php
	return true;
}

//Actions and Filters
if ( isset( $svvsd_lockByLDAPQuery ) ) {
	//Filters
	add_filter( 'plugin_action_links_' . plugin_basename( plugin_dir_path( __FILE__ ) . 'lock-by-ldap-query.php' ), array( &$svvsd_lockByLDAPQuery, 'lblqSettingsLink' ) );
	add_filter( 'the_content', array( &$svvsd_lockByLDAPQuery, 'lblqVerifyQuery' ), 99 );

	//Actions
	add_action( 'admin_menu', 'lblqAddOptionPage' );
	add_action( 'activate_lockByLDAPQuery/lock-by-ldap-query.php',  array( &$svvsd_lockByLDAPQuery, '__construct' ) );
	add_action( 'add_meta_boxes', 'lblqAddMetaBox' );
	add_action( 'save_post', array( &$svvsd_lockByLDAPQuery, 'lblqUpdatePost' ) );
}
?>