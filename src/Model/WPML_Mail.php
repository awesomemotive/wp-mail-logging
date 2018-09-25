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
 * @method integer get_mail_id()
 * @method set_mail_id(integer $id)
 * @method string get_timestamp()
 * @method set_timestamp(string $timestamp)
 * @method string get_host()
 * @method set_host(string $host)
 * @method string get_receiver()
 * @method set_receiver(string $receiver)
 * @method string get_subject()
 * @method set_subject(string $subject)
 * @method string get_message()
 * @method set_message(string $message)
 * @method string get_headers()
 * @method set_headers(string $headers)
 * @method string get_attachments()
 * @method set_attachments(string $attachments)
 * @method string get_error()
 * @method set_error(string|string[] $error)
 * @method string get_plugin_version()
 * @method set_plugin_version(string $plugin_version)
 */
class WPML_Mail extends BaseModel {
    /**
     * @var integer
     */
    protected $mail_id;

    /**
     * @var string
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
     * @param array $properties
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
