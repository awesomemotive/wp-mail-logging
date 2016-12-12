<?php

namespace No3x\WPML\Model;

use \DateTime;
use No3x\WPML\ORM\BaseModel;

// Exit if accessed directly
if(!defined( 'ABSPATH' )) exit;

/**
 * WPML Mail model.
 * @since 1.6.0
 * @author No3x
 */
class WPML_Mail extends BaseModel
{
    /**
     * @var integer
     */
    protected $mail_id;

    /**
     * @var DateTime
     */
    protected $timestamp;

    /**
     * @var string
     */
    protected $host;

    /**
     * @var string
     */
    protected $receiver;

    /**
     * @var string
     */
    protected $subject;

    /**
     * @var string
     */
    protected $message;

    /**
     * @var string
     */
    protected $headers;

    /**
     * @var string
     */
    protected $attachments;

    /**
     * @var string
     */
    protected $error;

    /**
     * @var string
     */
    protected $plugin_version;

    /**
     * @var string
     */

    public function __construct(array $properties = array())
    {
        parent::__construct($properties);
    }

    /**
     * Get the model's primary key.
     *
     * @return string
     */
    public static function get_primary_key()
    {
        return 'mail_id';
    }

    /**
     * Get the table used to store posts.
     *
     * @return string
     */
    public static function get_table()
    {
        global $wpdb;
        return $wpdb->prefix . 'wpml_mails';
    }

    /**
     * Get an array of properties to search when doing a search query.
     *
     * @return array
     */
    public static function get_searchable_fields()
    {
        return array('receiver', 'subject', 'headers', 'message', 'attachments', 'host');
    }
}