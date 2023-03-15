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

use No3x\WPML\Model\DefaultMailService;
use No3x\WPML\Renderer\WPML_MailRenderer;
use No3x\WPML\Renderer\WPML_MailRenderer_AJAX_Handler;

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
            return new WPML_Plugin($c['supported-mail-renderer-formats'], $c['mailRendererAjaxHandler']);
        };
        $this->container['plugin-meta'] = function ($c) use ($file) {
            /* @var $plugin WPML_Plugin */
            $plugin = $c['plugin'];
            $path = trailingslashit(realpath( plugin_dir_path( $file ) ) );
            return [
                'path' => $path,
                'uri' => plugin_dir_url( $file ),
                'display_name' => $plugin->getPluginDisplayName(),
                'slug' => $plugin->getPluginSlug(),
                'main_file' => $plugin->getMainPluginFileName(),
                'main_file_path' => $path . $plugin->getMainPluginFileName(),
                'description' => $plugin->getPluginHeaderValue( 'Description' ),
                'version' => $plugin->getVersion(),
                'version_installed' => $plugin->getVersionSaved(),
                'author_name' => $plugin->getPluginHeaderValue( 'Author' ),
                'author_uri' => $plugin->getPluginHeaderValue( 'Author URI' ),
                'wp_uri' => $plugin->getPluginHeaderValue( 'Plugin URI' ),
                'support_uri' => $plugin->getPluginHeaderValue( 'Support URI' ),
                'license' => $plugin->getPluginHeaderValue( 'License' ),
            ];
        };
        $this->container['supported-mail-renderer-formats'] = function ($c) {
            /** @var WPML_MailRenderer $mailRenderer */
            $mailRenderer = $c['mailRenderer'];
            return $mailRenderer->getSupportedFormats();
        };
        $this->container['emailLogList'] = function ($c) {
            return new WPML_Email_Log_List( $c['emailResender'] );
        };
        $this->container['emailResender'] = function ($c) {
            return new WPML_Email_Resender( $c['emailDispatcher'] );
        };
        $this->container['emailDispatcher'] = function () {
            return new WPML_Email_Dispatcher();
        };
        $this->container['logRotation'] = function ($c) {
            return new WPML_LogRotation( $c['plugin-meta'] );
        };
        $this->container['privacyController'] = function ($c) {
            return new WPML_PrivacyController($c['plugin-meta']);
        };
        $this->container['mailRendererAjaxHandler'] = function ($c) {
            return new WPML_MailRenderer_AJAX_Handler($c['mailRenderer']);
        };
        $this->container['mailRenderer'] = function ($c) {
            return new WPML_MailRenderer( new DefaultMailService() );
        };
        $this->container['userFeedback'] = function ($c) {
            return new WPML_UserFeedback();
        };
        $this->container['productEducation'] = new WPML_ProductEducation();

        $this->container->addActionsAndFilters();

        add_filter( 'wpml_get_di_container', function() {
            return $this->container;
        } );

        add_filter( 'wpml_get_di_service', function( $service ) {
            return $this->getService( $service );
        } );

        // Set plugin activation time for all installs.
        if ( is_admin() && empty( get_option( 'wp_mail_logging_activated_time' ) ) ) {
            add_option( 'wp_mail_logging_activated_time', time() );
        }

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

        if ( $file ) {
            // Register the Plugin Activation Hook.
            register_activation_hook( $file, array( &$this->container['plugin'], 'activate' ) );

            // Register the Plugin Deactivation Hook.
            register_deactivation_hook( $file, array( &$this->container['plugin'], 'deactivate' ) );
        }
    }

    public function getService( $key ) {
        if( in_array( $key, $this->container->keys() ) ) {
            return $this->container[ $key ];
        }
        throw new \Exception("Service '{$key}' is not registered");
    }
}
