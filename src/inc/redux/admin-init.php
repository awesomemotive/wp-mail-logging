<?php

// Load the embedded Redux Framework
if ( !class_exists( 'ReduxFramework' ) && file_exists( dirname( __FILE__ ).'/../../../lib/vendor/redux-framework/redux-core/framework.php' ) ) {
    require_once dirname(__FILE__) . '/../../../lib/vendor/redux-framework/redux-core/framework.php';
}
// Load the theme/plugin options
if ( file_exists( dirname( __FILE__ ) . '/options-init.php' ) ) {
    require_once dirname( __FILE__ ) . '/options-init.php';
}

