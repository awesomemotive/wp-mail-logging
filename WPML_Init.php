<?php
/*
	"WordPress Plugin Template" Copyright (C) 2013 Michael Simpson  (email : michael.d.simpson@gmail.com)

	This file is part of WordPress Plugin Template for WordPress.

	WordPress Plugin Template is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	WordPress Plugin Template is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with Contact Form to Database Extension.
	If not, see http://www.gnu.org/licenses/gpl-3.0.html
*/

namespace No3x\WPML;

use No3x\WPML\Settings\WPML_Redux_Framework_config;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

class WPML_Init {

	public static $plugin;

	static function init( $file ) {

		require_once( plugin_dir_path( __FILE__ ) . 'inc/redux/admin-init.php' );

		$container = new WPML_DI_Container();

		$container['plugin'] = function ($c) {
			return new WPML_Plugin($c['emailLogList']);
		};
		$container['plugin-meta'] = function ($c) {
			return array(
				'path' => realpath( plugin_dir_path( __FILE__ ) ) . DIRECTORY_SEPARATOR,
				'uri' => plugin_dir_url( __FILE__ ),
				'display_name' => $c['plugin']->getPluginDisplayName(),
				'slug' => $c['plugin']->getPluginSlug(),
				'main_file' => $c['plugin']->getMainPluginFileName(),
				'description' =>  $c['plugin']->getPluginHeaderValue('Description'),
				'version' => $c['plugin']->getVersion(),
				'version_installed' => $c['plugin']->getVersionSaved(),
				'author_name' => $c['plugin']->getPluginHeaderValue( 'Author' ),
				'author_uri' => $c['plugin']->getPluginHeaderValue( 'Author URI' ),
				'wp_uri' => $c['plugin']->getPluginHeaderValue( 'Plugin URI' ),
				'support_uri' => $c['plugin']->getPluginHeaderValue( 'Support URI' ),
				'license' => $c['plugin']->getPluginHeaderValue( 'License' ),
			);
		};
		$container['emailLogList'] = function ($c) {
			return new WPML_Email_Log_List();
		};
		$container['settings'] = function ($c) {
			return new WPML_Redux_Framework_config( $c['plugin-meta'] );
		};
		$container['api'] = function ($c) {
			// Uncomment for an API Exmaple
			// return new WPML_API_Example();
		};
		$container->run();

		add_filter( 'wpml_get_di_container', function() use( $container ) {
			return $container;
		} );

		add_filter( 'wpml_get_di_service', function( $service ) use( $container ) {
			return $container[$service];
		} );

		// For Testing make plugin available global.
		/*if ( ! array_key_exists( 'WPML_Plugin', $GLOBALS ) ) {
			$GLOBALS['WPML_Plugin'] = &$container['plugin'];
		}*/

		/*
		 * Install the plugin
		 * NOTE: this file gets run each time you *activate* the plugin.
		 * So in WP when you "install" the plugin, all that does it dump its files in the plugin-templates directory
		 * but it does not call any of its code.
		 * So here, the plugin tracks whether or not it has run its install operation, and we ensure it is run only once
		 * on the first activation
		 */
		if (!$container['plugin']->isInstalled()) {
			$container['plugin']->install();
		} else {
			// Perform any version-upgrade activities prior to activation (e.g. database changes).
			$container['plugin']->upgrade();
		}

		if ( ! $file ) {
			$file = __FILE__;
		}
		// Register the Plugin Activation Hook.
		register_activation_hook( $file, array( &$container['plugin'], 'activate' ) );

		// Register the Plugin Deactivation Hook.
		register_deactivation_hook( $file, array( &$container['plugin'], 'deactivate' ) );
	}
}
