<?php
namespace No3x\WPML;


use No3x\WPML\Model\WPML_Mail;

class WPML_PrivacyController implements IHooks {

    const WPML_PRIVACY_EXPORTER = "wp-mail-logging-exporter";
    const WPML_PRIVACY_ERASER = "wp-mail-logging-eraser";
    const PER_PAGE = 500;

    private $plugin_meta;

    /**
     * WPML_PrivacyController constructor.
     */
    function __construct( $plugin_meta ) {
        $this->plugin_meta = $plugin_meta;
    }

    public function addActionsAndFilters() {
        add_filter( 'wp_privacy_personal_data_exporters', [$this, 'register_exporter'], 10);
        add_filter( 'wp_privacy_personal_data_erasers', [$this, 'register_eraser'], 10);
        add_action( 'admin_init', [$this, 'register_privacy_policy_content'] );
        add_action( 'wp_privacy_personal_data_erased', [$this, 'suspendLogging'], 9 );
    }

    function suspendLogging() {
        (new WPML_Hook_Remover())->remove_class_hook(
            'wp_mail',
            WPML_Plugin::getClass(),
            WPML_Plugin::HOOK_LOGGING_MAIL,
            WPML_Plugin::HOOK_LOGGING_MAIL_PRIORITY
        );
    }

    function register_privacy_policy_content() {
        if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
            return;
        }

        $content = __( 'When you use this site several actions (e.g. commenting) trigger the dispatch of emails. They contain information about you associated with your email address. Which data are part of these emails depends on the action performed. These emails are stored and accessible to the site management as log.', 'wp-mail-logging' );

        wp_add_privacy_policy_content(
            $this->plugin_meta['display_name'],
            wp_kses_post( wpautop( $content, false ) )
        );

    }

    function register_exporter( $exporters ) {
        $exporters[self::WPML_PRIVACY_EXPORTER] = array(
            'exporter_friendly_name' => __( 'WP Mail Logging' ),
            'callback'               => [$this, 'export'],
        );
        return $exporters;
    }

    function register_eraser( $erasers ) {
        $erasers[self::WPML_PRIVACY_ERASER] = array(
            'eraser_friendly_name' => __( 'WP Mail Logging' ),
            'callback'             => [$this, 'erase'],
        );
        return $erasers;
    }

    /**
     * @param $email_address
     * @param $current_page
     * @return array|WPML_Mail[]
     */
    private function queryMails($email_address, $current_page) {
        $offset = ( $current_page - 1 ) * self::PER_PAGE;
        return WPML_Mail::query()
            ->search( $email_address )
            ->limit( self::PER_PAGE )
            ->offset( $offset )
            ->find();
    }

    public function export($email_address, $page = 1) {
        $mails = $this->queryMails($email_address, $page);

        $export_items = [];
        foreach ($mails as $mail) {
            // Most item IDs should look like postType-postID
            // If you don't have a post, comment or other ID to work with,
            // use a unique value to avoid having this item's export
            // combined in the final report with other items of the same id
            $item_id = "mail-{$mail->get_mail_id()}";

            // Core group IDs include 'comments', 'posts', etc.
            // But you can add your own group IDs as needed
            $group_id = 'mails';

            // Optional group label. Core provides these for core groups.
            // If you define your own group, the first exporter to
            // include a label will be used as the group label in the
            // final exported report
            $group_label = __( 'Mails' );

            // Plugins can add as many items in the item data array as they want
            $mail_as_array = $mail->to_array();
            $data = [];
            foreach ($mail_as_array as $name => $value) {
                 $data[] = [
                    'name' => $name, //TODO: translate function
                    'value' => $value
                ];
            }

            $export_items[] = array(
                'group_id' => $group_id,
                'group_label' => $group_label,
                'item_id' => $item_id,
                'data' => $data,
            );
        }

        return array(
            'data' => $export_items,
            'done' => $this->isDone($mails),
        );
    }

    function erase( $email_address, $page = 1 ) {
        $mails = $this->queryMails($email_address, $page);

        $items_removed = false;
        $items_retained = false;
        $messages = [];
        foreach ($mails as $mail) {
            if($mail->delete()) {
                $items_removed = true;
            } else {
                $messages[] = sprintf( __( 'A mail with the id %d was unable to be removed at this time.', 'wp-mail-logging'), $mail->get_mail_id());
                $items_retained = true;
            }
        }

        return array( 'items_removed' => $items_removed,
            'items_retained' => $items_retained, // always false in this example
            'messages' => $messages, // no messages in this example
            'done' => $this->isDone($mails),
        );
    }

    /**
     * True if we have more mails to work on still.
     * @param $mails
     * @return bool
     */
    private function isDone($mails) {
        return count($mails) < self::PER_PAGE;
    }
}
