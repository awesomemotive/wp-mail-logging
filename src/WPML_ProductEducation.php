<?php

namespace No3x\WPML;

class WPML_ProductEducation {

    /**
     * Option key used to saved in `wp_options` table.
     *
     * @since 1.11.0
     *
     * @var string
     */
    const OPTION_KEY = 'wp_mail_logging_product_education';

    /**
     * Nonce action for production education dismiss.
     *
     * @since 1.11.0
     *
     * @var string
     */
    const DISMISS_NONCE_ACTION = 'WP_MAIL_LOGGING_PRODUCT_EDUCATION_DISMISS';

    /**
     * Product education option.
     *
     * @since 1.11.0
     *
     * @var null|array
     */
    private $option = null;

    /**
     * Constructor
     *
     * @since 1.11.0
     */
    public function __construct() {

        $this->hooks();
    }

    /**
     * WP-related hooks.
     *
     * @since 1.11.0
     *
     * @return void
     */
    private function hooks() {

        add_action( 'wp_ajax_wp_mail_logging_product_education_dismiss', [ $this, 'product_education_dismiss' ] );
    }

    /**
     * AJAX function for product education dismiss.
     *
     * @since 1.11.0
     *
     * @return void
     */
    public function product_education_dismiss() {

        check_ajax_referer( self::DISMISS_NONCE_ACTION, 'nonce' );

        if ( empty( $_POST['productEducationID'] ) ) {

            wp_send_json_error(
                esc_html__( 'Request invalid.', 'wp-mail-logging' )
            );
        }

        $this->update_dismissed_option( htmlspecialchars( $_POST['productEducationID'] ) );

        wp_send_json_success();
    }

    /**
     * Include the `$id` to the dismissed product education option.
     *
     * @since 1.11.0
     *
     * @param string $id ID of the product education banner dismissed.
     *
     * @return bool Whether or not the option was updated.
     */
    private function update_dismissed_option( $id ) {

        $option = $this->get_option();

        $dismissed        = empty( $option['dismissed'] ) ? [] : $option['dismissed'];
        $dismissed[ $id ] = true;

        $option['dismissed'] = $dismissed;

        return update_option( self::OPTION_KEY, $option, false );
    }

    /**
     * Get saved option.
     *
     * @since 1.11.0
     *
     * @return array
     */
    public function get_option() {

        if ( ! is_null( $this->option ) ) {
            return $this->option;
        }

        return get_option( self::OPTION_KEY, [] );
    }

    /**
     * Returns whether a banner was previously dismissed or not.
     *
     * @since 1.11.0
     *
     * @param string $id Product Education banner ID.
     *
     * @return bool
     */
    public function is_banner_dismissed( $id ) {

        $option = $this->get_option();

        if ( empty( $option['dismissed'] ) || ! array_key_exists( $id, $option['dismissed'] ) ) {
            return false;
        }

        return true;
    }

    /**
     * Create product education banner.
     *
     * @since 1.11.0
     *
     * @param string   $id      Unique ID for the product education banner.
     * @param string   $title   Title of the product education banner.
     * @param string   $content Education banner content.
     * @param string[] $button  {
     *     Optional. An array of arguments for the button. If not provided, the product
     *     education banner won't display a button.
     *
     *     @type string $url   URL of the button.
     *     @type string $label Label of the button.
     * }
     *
     * @return void
     */
    public static function create_banner( $id, $title, $content, $button = [] ) {
        ?>
        <div id="wp-mail-logging-product-education-<?php echo esc_attr( $id ); ?>"
             class="wp-mail-logging-product-education"
             data-product-education-id="<?php echo esc_attr( $id ); ?>"
             data-nonce="<?php echo esc_attr( wp_create_nonce( self::DISMISS_NONCE_ACTION ) ); ?>">

            <div class="wp-mail-logging-product-education-content">

                <span class="wp-mail-logging-product-education-dismiss">
                    <button>
                        <span class="dashicons dashicons-dismiss"></span>
                    </button>
                </span>

                <h3><?php echo esc_html( $title ); ?></h3>

                <?php echo wp_kses_post( $content ); ?>

                <?php
                if ( ! empty( $button ) ) {
                    // Default.
                    $button_target = '_self';
                    if ( ! empty( $button['target'] ) && in_array( $button['target'], [ '_blank', '_parent', '_top' ], true ) ) {
                        $button_target = $button['target'];
                    }
                    ?>
                    <a class="wp-mail-logging-education-btn button button-primary" target="<?php echo esc_attr( $button_target ); ?>" href="<?php echo esc_url( $button['url'] ); ?>">
                        <?php echo esc_html( $button['label'] ); ?>
                    </a>
                <?php
                }
                ?>
            </div>
        </div>
        <?php
    }
}
