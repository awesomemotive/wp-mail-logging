<?php

namespace No3x\WPML;

// Exit if accessed directly.
use No3x\WPML\Admin\SettingsTab;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Utils
 * @author No3x
 * @since 1.6.0
 */
class WPML_Utils {

    /**
     * Admin page slug.
     *
     * @since 1.11.0
     *
     * @var string
     */
    const ADMIN_PAGE_SLUG = 'wpml_plugin_log';

    /**
     * CSS class for a success notice.
     *
     * @since 1.11.0
     *
     * @var string
     */
    const ADMIN_NOTICE_SUCCESS = 'notice-success';

    /**
     * CSS class for an info notice.
     *
     * @since 1.11.0
     *
     * @var string
     */
    const ADMIN_NOTICE_INFO = 'notice-info';

    /**
     * The "queue" of notices.
     *
     * @since 1.11.0
     *
     * @var array
     */
    private static $admin_notices = [];

    /**
     * Add a notice to the "queue of notices".
     *
     * @since 1.11.0
     *
     * @param string $message        Message text (HTML is OK).
     * @param string $class          Display class (severity).
     * @param bool   $is_dismissible Whether the message should be dismissible.
     *
     * @return void
     */
    public static function add_admin_notice( $message, $class = self::ADMIN_NOTICE_INFO, $is_dismissible = true ) {

        self::$admin_notices[] = [
            'class'          => $class,
            'message'        => $message,
            'is_dismissible' => $is_dismissible,
        ];
    }

    /**
     * Display all admin notices.
     *
     * @since 1.11.0
     *
     * @return void
     */
    public static function display_admin_notices() {

        foreach ( self::$admin_notices as $notice ) {
            $dismissible = $notice['is_dismissible'] ? 'is-dismissible' : '';
            ?>
            <div class="notice wp-mail-logging-notice <?php echo esc_attr( $notice['class'] ); ?> notice <?php echo esc_attr( $dismissible ); ?>">
                <p>
                    <?php echo wp_kses_post( $notice['message'] ); ?>
                </p>
            </div>
            <?php
        }
    }

    /**
     * Ensure value is subset of given set
     * @since 1.6.0
     * @param string $value expected value.
     * @param array  $allowed_values allowed values.
     * @param string $default_value default value.
     * @return mixed
     */
    public static function sanitize_expected_value( $value, $allowed_values, $default_value = null ) {
        $allowed_values = (is_array( $allowed_values ) ) ? $allowed_values : array( $allowed_values );
        if ( $value && in_array( $value, $allowed_values ) ) {
            return $value;
        }
        if ( null !== $default_value ) {
            return $default_value;
        }
        return false;
    }

    /**
     * Multilevel array_search
     * @since 1.3
     * @param string $needle the searched value.
     * @param array  $haystack the array.
     * @return mixed Returns the value if needle is found in the array, false otherwise.
     * @see array_search()
     */
    public static function recursive_array_search( $needle, $haystack ) {
        foreach ( $haystack as $key => $value ) {
            $current_key = $key;
            if ( $needle === $value or ( is_array( $value ) && self::recursive_array_search( $needle, $value ) !== false ) ) {
                return $current_key;
            }
        }
        return false;
    }

    /**
     * Determines appropriate Dashicon icon for a given icon class
     *
     * @since 1.11.0
     *
     * @param string $iconClass Icon class.
     *
     * @return string Dashicon icon HTML.
     */
    public static function determine_dashicon_icon( $iconClass ) {

        $dashicon_class = self::get_dashicon_icon_class( $iconClass );

        return '<span class="dashicons dashicons-' . esc_attr( $dashicon_class ) . '"></span>';
    }

    /**
     * Get the appropriate Dashicon class for a given icon class.
     *
     * @since 1.11.0
     *
     * @param string $iconClass Icon class.
     *
     * @return string
     */
    private static function get_dashicon_icon_class( $iconClass ) {

        $supported = [
            'archive' => 'media-archive',
            'audio'   => 'media-audio',
            'code'    => 'media-code',
            'excel'   => 'media-spreadsheet',
            'image'   => 'format-image',
            'movie'   => 'media-video',
            'pdf'     => 'pdf',
            'photo'   => 'format-image',
            'picture' => 'format-image',
            'sound'   => 'media-audio',
            'video'   => 'media-video',
            'zip'     => 'media-archive',
        ];

        if ( ! array_key_exists( $iconClass, $supported ) ) {
            // Default Dashicon.
            return 'media-document';
        }

        return $supported[ $iconClass ];
    }

    /**
     * Determines appropriate fa icon for a given icon class
     *
     * @since 1.9.0
     * @deprecated 1.11.0 Removed Font Awesome library and moved to Dashicons. Use `WPML_Utils::determine_dashicon_icon()`.
     * @see WPML_Utils::determine_dashicon_icon()
     *
     * @param string $iconClass icon class.
     *
     * @return string returns fa icon.
     */
    public static function determine_fa_icon( $iconClass ) {

        return '<i class="fa ' . esc_attr($iconClass == "file" ? "fa-file-o" : "fa-file-{$iconClass}-o") . '"></i>';
    }

    /**
     * Find appropriate Dashicon icon from file path
     *
     * @since 1.9.0
     * @since 1.11.0 Use Dashicons instead of Font Awesome for the icons.
     *
     * @param WPML_Attachment $attachment attachment.
     *
     * @return string
     */
    public static function generate_attachment_icon( $attachment ) {

        return self::determine_dashicon_icon( $attachment->getIconClass() );
    }

    /**
     * Get UTM URL.
     *
     * @since 1.11.0
     *
     * @param string       $url Base url.
     * @param array|string $utm Array of UTM params, or if string provided - utm_content URL parameter.
     *
     * @return string
     */
    public static function get_utm_url( $url, $utm ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

        // Defaults.
        $source   = 'WordPress';
        $medium   = 'plugin';
        $campaign = 'wp-mail-logging-plugin';
        $content  = 'general';

        if ( is_array( $utm ) ) {
            if ( isset( $utm['source'] ) ) {
                $source = $utm['source'];
            }
            if ( isset( $utm['medium'] ) ) {
                $medium = $utm['medium'];
            }
            if ( isset( $utm['campaign'] ) ) {
                $campaign = $utm['campaign'];
            }
            if ( isset( $utm['content'] ) ) {
                $content = $utm['content'];
            }
        } elseif ( is_string( $utm ) ) {
            $content = $utm;
        }

        $query_args = [
            'utm_source'   => esc_attr( rawurlencode( $source ) ),
            'utm_medium'   => esc_attr( rawurlencode( $medium ) ),
            'utm_campaign' => esc_attr( rawurlencode( $campaign ) ),
        ];

        if ( ! empty( $content ) ) {
            $query_args['utm_content'] = esc_attr( rawurlencode( $content ) );
        }

        return add_query_arg( $query_args, $url );
    }

    /**
     * Get the admin page base URL.
     *
     * @since 1.11.0
     *
     * @return string
     */
    public static function get_admin_page_url() {

        $settings = SettingsTab::get_settings();

        if ( isset( $settings['top-level-menu'] ) && $settings['top-level-menu'] === '0' ) {
            $admin_base = admin_url( 'tools.php' );
        } else {
            $admin_base = admin_url( 'admin.php' );
        }

        return add_query_arg( 'page', self::ADMIN_PAGE_SLUG, $admin_base );
    }
}
