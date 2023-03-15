<?php

namespace No3x\WPML\Renderer\Format;


use No3x\WPML\Admin\EmailLogsTab;
use No3x\WPML\Admin\SettingsTab;
use No3x\WPML\Admin\SMTPTab;
use No3x\WPML\Renderer\Column\AttachmentsColumn;
use No3x\WPML\Renderer\Column\ColumnFormat;
use No3x\WPML\Renderer\WPML_ColumnManager;
use No3x\WPML\WPML_Utils;

abstract class BaseRenderer implements IMailRenderer {

    /** @var WPML_ColumnManager */
    protected $columnManager;

    /**
     * BaseRenderer constructor.
     * @param WPML_ColumnManager $columnManager
     */
    public function __construct(WPML_ColumnManager $columnManager) {
        $this->columnManager = $columnManager;
    }

    abstract function render($item);

    protected function getHiddenColumns() {
        return [
            WPML_ColumnManager::COLUMN_MAIL_ID,
            WPML_ColumnManager::COLUMN_PLUGIN_VERSION
        ];
    }

    public function renderRawOrHtmlModal( $item, $message ) {

        $content_keys = [
            'timestamp'   => __( 'Time', 'wp-mail-logging' ),
            'receiver'    => __( 'Receiver', 'wp-mail-logging' ),
            'subject'     => __( 'Subject', 'wp-mail-logging' ),
            'error'       => __( 'Error', 'wp-mail-logging' ),
            'headers'     => __( 'Headers', 'wp-mail-logging' ),
            'message'     => __( 'Message', 'wp-mail-logging' ),
            'attachments' => __( 'Attachments', 'wp-mail-logging' ),
        ];

        $settings = SettingsTab::get_settings( SettingsTab::DEFAULT_SETTINGS );

        if ( ! empty( $settings['display-host' ] ) && $settings['display-host'] == '1' ) {
            $content_keys['host'] = __( 'Host', 'wp-mail-logging' );
        }

        ob_start();
        ?>
        <div id="wp-mail-logging-modal-content-body-table">

            <?php
            foreach ( $content_keys as $key => $label ) {

                if ( empty( $item[ $key ] ) ) {
                    continue;
                }

                switch ( $key ) {
                    case 'timestamp':
                        $value = date_i18n( apply_filters( 'wpml_get_date_time_format', '' ), strtotime( $item['timestamp'] ) );
                        break;
                    case 'attachments':
                        $attachmentsCol = new AttachmentsColumn();
                        $value          = $attachmentsCol->render( $item, ColumnFormat::FULL );
                        break;
                    default:
                        $value = $item[ $key ];
                        break;
                }
                ?>
                <div class="wp-mail-logging-modal-row wp-mail-logging-modal-clear">
                    <div class="wp-mail-logging-modal-row-label wp-mail-logging-modal-row-label-<?php echo esc_attr( $key ); ?>">
                        <?php echo esc_html( $label ); ?>
                    </div>

                    <?php
                    if ( $key === 'message') {
                        $this->render_message_value( $item, $settings['preferred-mail-format'] );
                    } else {
                        $this->render_column_value( $key, $value );
                    }
                    ?>
                </div>
                <?php
            }
            ?>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Render the message value.
     *
     * @since 1.11.0
     *
     * @param array  $mail           Mail data in context.
     * @param string $default_format Default format of the message to render.
     *
     * @return void
     */
    private function render_message_value( $mail, $default_format = 'html' ) {
        $format = empty( $_POST['format'] ) ? $default_format : $_POST['format'];
        ?>
        <div class="wp-mail-logging-modal-row-html-container">
            <?php
            if ( $format === 'raw' ) {
                echo nl2br( esc_html( $mail['message'] ) );
            } else {
                $iframe_src = add_query_arg(
                    [
                        'email_log_id' => absint( $mail['mail_id'] ),
                        'mode'         => 'iframe_preview',
                    ],
                    wp_nonce_url( WPML_Utils::get_admin_page_url(), EmailLogsTab::SINGLE_EMAIL_CONTENT_PREVIEW_MODE_NONCE, EmailLogsTab::SINGLE_EMAIL_CONTENT_PREVIEW_MODE_NONCE )
                );

                $iframe_title = sprintf(
                    /* translators: %d - Email Log ID. */
                    __( "Email Log ID Content: %d" ),
                    absint( $mail['mail_id'] )
                )
                ?>
                <iframe id="SingleEmailLogContent>"
                    title="<?php echo esc_attr( $iframe_title ); ?>"
                    height="320"
                    width="598"
                    src="<?php echo esc_url( $iframe_src ); ?>">
                </iframe>
            <?php
            }
            ?>
        </div>
        <?php
    }

    /**
     * Render the value.
     *
     * @since 1.11.0
     *
     * @param string $key   Key of the value to render.
     * @param string $value Value to be rendered.
     *
     * @return void
     */
    private function render_column_value( $key, $value ) {
        ?>
        <div class="wp-mail-logging-modal-row-value wp-mail-logging-modal-row-value-<?php echo esc_attr( $key ); ?>">
            <?php

            echo wp_kses_post( $value );

            if ( $key === 'error' ) {
                ?>
                <div class="notice wp-mail-logging-html-error-notice is-dismissible">
                    <p>
                        <?php
                        printf(
                            wp_kses( /* translators: %s - Link to the SMTP page. */
                                __( '<strong>This email failed to send.</strong> <a href="%s">Install WP Mail SMTP</a> to solve your deliverability issues.', 'wp-mail-logging' ),
                                [
                                    'a'      => [
                                        'href' => [],
                                    ],
                                    'strong' => [],
                                ]
                            ),
                            esc_url( SMTPTab::get_url() )
                        );
                        ?>
                    </p>
                    <button type="button" class="notice-dismiss"><span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'wp-mail-logging' ); ?></span></button>
                </div>
                <?php
            }
            ?>
        </div>
        <?php
    }
}
