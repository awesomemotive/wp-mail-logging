<?php
namespace No3x\WPML;


use No3x\WPML\Model\WPML_Mail;

class WPML_PrivacyController {

    const WPML_PRIVACY_EXPORTER = "wpml-exporter";

    /**
     * WPML_PrivacyController constructor.
     */
    public function __construct() {

    }

    public function addActionsAndFilters() {
        add_filter('wp_privacy_personal_data_exporters', [$this, 'register_exporter'], 10);
    }

    function register_exporter( $exporters ) {
        $exporters[self::WPML_PRIVACY_EXPORTER] = array(
            'exporter_friendly_name' => __( 'WP Mail Logging' ),
            'callback' => [$this, 'export'],
        );
        return $exporters;
    }



    public function export($email_address, $page = 1) {
        $search = $email_address;
        $per_page = 500;
        $current_page = $page;
        $offset = ( $current_page - 1 ) * $per_page;

        $mails = WPML_Mail::query()
            ->search( $search )
            ->limit( $per_page )
            ->offset( $offset )
            ->find();

        $export_items = [];
        /** @var WPML_Mail $mail */
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
            $data_reference = array(
                array(
                    'name' => __( 'Commenter Latitude' ),
                    'value' => '1'
                ),
                array(
                    'name' => __( 'Commenter Longitude' ),
                    'value' => '2'
                )
            );

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

        // Tell core if we have more comments to work on still
        $done = count( $mails ) < $per_page;
        return array(
            'data' => $export_items,
            'done' => $done,
        );
    }
}
