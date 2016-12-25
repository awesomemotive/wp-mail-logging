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

    /**
     * @var Singleton The reference to *Singleton* instance of this class
     */
    private static $instance;
    /**
     * @var WPML_DI_Container The DI Container
     */
    private $container;

    /**
     * Returns the *Singleton* instance of this class.
     *
     * @return WPML_Init The *Singleton* instance.
     */
    public static function getInstance() {
        if (null === static::$instance) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Protected constructor to prevent creating a new instance of the
     * *Singleton* via the `new` operator from outside of this class.
     */
    protected function __construct() {
        $this->container = new WPML_DI_Container();
    }

    public function getClosure() {
        return function ($prop) {
            return $this->$prop;
        };
    }

    public function init( $file ) {

        $this->container['plugin'] = function ($c) {
            return new WPML_Plugin();
        };
        $this->container['plugin-meta'] = function ($c) {
            /* @var $plugin WPML_Plugin  */
            $plugin = $c['plugin'];
            return array(
                'path' => realpath( plugin_dir_path( __FILE__ ) ) . DIRECTORY_SEPARATOR,
                'uri' => plugin_dir_url( __FILE__ ),
                'display_name' => $plugin->getPluginDisplayName(),
                'slug' => $plugin->getPluginSlug(),
                'main_file' => $plugin->getMainPluginFileName(),
                'description' => $plugin->getPluginHeaderValue( 'Description' ),
                'version' => $plugin->getVersion(),
                'version_installed' => $plugin->getVersionSaved(),
                'author_name' => $plugin->getPluginHeaderValue( 'Author' ),
                'author_uri' => $plugin->getPluginHeaderValue( 'Author URI' ),
                'wp_uri' => $plugin->getPluginHeaderValue( 'Plugin URI' ),
                'support_uri' => $plugin->getPluginHeaderValue( 'Support URI' ),
                'license' => $plugin->getPluginHeaderValue( 'License' ),
            );
        };
        $this->container['emailLogList-supported-formats'] = function ($c) {
            return array(
                'html',
                'raw',
                'json'
            );
        };
        $this->container['emailLogList'] = function ($c) {
            return new WPML_Email_Log_List( $c['emailLogList-supported-formats'] );
        };
        $this->container['redux'] = function ($c) {
            return new WPML_Redux_Framework_config( $c['plugin-meta'] );
        };
        $this->container['logRotation'] = function ($c) {
            return new WPML_LogRotation( $c['plugin-meta'] );
        };
        $this->container['api'] = function ($c) {
            // Uncomment for an API Example
            // return new WPML_API_Example();
        };
        $this->container->addActionsAndFilters();

        add_filter( 'wpml_get_di_container', function() {
            return $this->container;
        } );

        add_filter( 'wpml_get_di_service', function( $service ) {
            return $this->getService( $service );
        } );

        /*
         * Install the plugin
         * NOTE: this file gets run each time you *activate* the plugin.
         * So in WP when you "install" the plugin, all that does it dump its files in the plugin-templates directory
         * but it does not call any of its code.
         * So here, the plugin tracks whether or not it has run its install operation, and we ensure it is run only once
         * on the first activation
         */
        if ( ! $this->container['plugin']->isInstalled() ) {
            $this->container['plugin']->install();
        } else {
            // Perform any version-upgrade activities prior to activation (e.g. database changes).
            $this->container['plugin']->upgrade();
        }

        if ( ! $file ) {
            $file = __FILE__;
        }
        // Register the Plugin Activation Hook.
        register_activation_hook( $file, array( &$this->container['plugin'], 'activate' ) );

        // Register the Plugin Deactivation Hook.
        register_deactivation_hook( $file, array( &$this->container['plugin'], 'deactivate' ) );
    }

    public function getService( $key ) {
        if( in_array( $key, $this->container->keys() ) ) {
            return $this->container[ $key ];
        }
        throw new \Exception("Service '{$key}' is not registered");
    }
}
