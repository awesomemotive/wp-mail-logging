<?php

namespace No3x\WPML\Model;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Email_Log_Collection {

    /**
     * Status to use when fetching all logs.
     *
     * @since 1.11.0
     *
     * @var int
     */
    const STATUS_ALL = 0;

    /**
     * Status of email log without error.
     *
     * @since 1.11.0
     *
     * @var int
     */
    const STATUS_SUCCESSFUL = 1;

    /**
     * Status of email log with error.
     *
     * @since 1.11.0
     *
     * @var int
     */
    const STATUS_FAILED = 2;

    /**
     * Order ASC.
     *
     * @since 1.11.0
     *
     * @var string
     */
    const ORDER_ASC  = 'asc';

    /**
     * Order DESC.
     *
     * @since 1.11.0
     *
     * @var string
     */
    const ORDER_DESC = 'desc';

    /**
     * DB table name where email logs are stored.
     *
     * @since 1.11.0
     *
     * @var string
     */
    private $table_name = '';

    /**
     * Searchable fields.
     *
     * @since 1.11.0
     *
     * @var string[]
     */
    private $searchable_fields = [];

    /**
     * Contains the number of logs for each status.
     *
     * @since 1.11.0
     *
     * @var int[]
     */
    private $statuses_count = [
        self::STATUS_ALL        => 0,
        self::STATUS_SUCCESSFUL => 0,
        self::STATUS_FAILED     => 0,
    ];

    /**
     * Whether or not to only count the results.
     *
     * @since 1.11.0
     *
     * @var bool
     */
    private $count_only = false;

    /**
     * Status of the email logs to fetch. Default to all logs.
     *
     * @since 1.11.0
     *
     * @var int
     */
    private $status = self::STATUS_ALL;

    /**
     * Search term.
     *
     * @since 1.11.0
     *
     * @var string
     */
    private $search = '';

    /**
     * Sorting of the email logs.
     *
     * @since 1.11.0
     *
     * @var string
     */
    private $sort_by = 'mail_id';

    /**
     * Order of the email logs.
     *
     * @since 1.11.0
     *
     * @var string
     */
    private $order = self::ORDER_DESC;

    /**
     * Number of email logs in the collection.
     *
     * @since 1.11.0
     *
     * @var int
     */
    private $limit = 25;

    /**
     * Offset used for pagination.
     *
     * @since 1.11.0
     *
     * @var int
     */
    private $offset = 0;

    /**
     * Constructor
     *
     * @since 1.11.0
     *
     * @param string   $table_name        DB table name where email logs are stored.
     * @param string[] $searchable_fields Array containing searchable fields.
     */
    public function __construct( $table_name, $searchable_fields ) {

        $this->table_name        = $table_name;
        $this->searchable_fields = $searchable_fields;
    }

    /**
     * Returns an array containing the number of email logs for each status.
     *
     * @since 1.11.0
     *
     * @return int[]
     */
    public function get_statuses_count() {

        // Cache temporarily the vars.
        $count_only = $this->count_only;
        $status     = $this->status;

        $this->count_only = true;

        // Get count of the successful logs.
        $this->status = self::STATUS_SUCCESSFUL;
        $this->statuses_count[ self::STATUS_SUCCESSFUL ] = $this->find_list();

        // Get count of the failed logs.
        $this->status = self::STATUS_FAILED;
        $this->statuses_count[ self::STATUS_FAILED ] = $this->find_list();

        // "All" is the combination of both successful and failed logs.
        $this->statuses_count[ self::STATUS_ALL ] = absint( $this->statuses_count[ self::STATUS_SUCCESSFUL ] ) + absint( $this->statuses_count[ self::STATUS_FAILED ] );

        // Revert back.
        $this->count_only = $count_only;
        $this->status = $status;

        return $this->statuses_count;
    }

    /**
     * Set if we are only counting the number of results or not.
     *
     * @since 1.11.0
     *
     * @param bool $count_only Whether or not to only count the email logs. Default `true`.
     *
     * @return Email_Log_Collection
     */
    public function count( $count_only = true ) {

        $this->count_only = $count_only;

        return $this;
    }

    /**
     * Set the status of the email logs we want to fetch.
     *
     * @since 1.11.0
     *
     * @param int $status
     *
     * @return Email_Log_Collection
     */
    public function status( $status = self::STATUS_ALL ) {

        $this->status = $status;

        return $this;
    }

    /**
     * Set the search term we want the email logs in this collection to match with.
     *
     * @since 1.11.0
     *
     * @param string $search Search term.
     *
     * @return Email_Log_Collection
     */
    public function search( $search ) {

        $this->search = $search;

        return $this;
    }

    /**
     * Set the sorting field of the collection.
     *
     * @since 1.11.0
     *
     * @param string $sort_by Sort by field.
     *
     * @return Email_Log_Collection
     */
    public function sort_by( $sort_by ) {

        $this->sort_by = $sort_by;

        return $this;
    }

    /**
     * Order of the collection.
     *
     * @since 1.11.0
     *
     * @param string $order Only accepts `asc` or `desc`. Case-insensitive.
     *
     * @return Email_Log_Collection
     */
    public function order( $order ) {

        $order = strtolower( $order );

        if ( ! in_array( $order, [ self::ORDER_ASC, self::ORDER_DESC ], true ) ) {
            return $this;
        }

        $this->order = $order;

        return $this;
    }

    /**
     * Set the number of limit of the email logs in the collection.
     *
     * @since 1.11.0
     *
     * @param int $limit Limit of the email logs in the collection.
     *
     * @return Email_Log_Collection
     */
    public function limit( $limit ) {

        $this->limit = absint( $limit );

        return $this;
    }

    /**
     * Set the offset to use when calculating results.
     *
     * @since 1.11.0
     *
     * @param int $offset Offset.
     *
     * @return Email_Log_Collection
     */
    public function offset( $offset ) {

        $this->offset = $offset;

        return $this;
    }

    /**
     * Compose the query and fetch the collection of email logs.
     *
     * @since 1.11.0
     *
     * @return int|array Returns an integer which is the number of results if `$this->count_only` is `true`.
     *                   Otherwise returns an array of email logs.
     */
    public function find_list() {

        global $wpdb;

        // Build `SELECT` clause.
        if ( $this->count_only ) {
            $select = 'SELECT COUNT(*) ';
        } else {
            $select = 'SELECT * ';
        }
        $select .= 'FROM ' . esc_sql( $this->table_name );

        // Build there `WHERE` clause in the context of log status.
        $status_where = '';
        switch( $this->status ) {
            case self::STATUS_SUCCESSFUL:
                $status_where .= " WHERE `error` IS NULL";
                break;
            case self::STATUS_FAILED:
                $status_where .= " WHERE `error` IS NOT NULL AND `error` != ''";
                break;
            default:
                break;
        }

        // Build there `WHERE` clause in the context of search term.
        $search_where = '';
        if ( ! empty( $this->search ) ) {
            if ( empty( $status_where ) ) {
                $search_where = ' WHERE (';
            } else {
                $search_where .= ' AND (';
            }

            foreach ( $this->searchable_fields as $field ) {
                $search_where .= '`' . esc_sql( $field ) . '` LIKE "%' . esc_sql( $this->search ) . '%" OR ';
            }

            // Remove the last ' OR ' and add the closing ')';
            $search_where = substr( $search_where, 0, -4 ) . ')';
        }

        // Build query.
        $query = $select . $status_where . $search_where;

        if ( $this->count_only ) {
            $results = $wpdb->get_var( $query );
        } else {

            // SORT BY, LIMIT, and ORDER are only applicable if we're not counting the results.
            if ( ! empty( $this->sort_by ) ) {
                $query .= ' ORDER BY `' . esc_sql( $this->sort_by ) . '`';

                if ( ! empty( $this->order ) && in_array( $this->order, [ self::ORDER_ASC, self::ORDER_DESC ], true ) ) {
                    $query .= ' ' . esc_sql( $this->order );
                }
            }

            if ( ! empty( $this->limit ) ) {
                $query .= ' LIMIT ' . absint( $this->limit );
            }

            if ( ! empty( $this->offset ) ) {
                $query .= ' OFFSET ' . absint( $this->offset );
            }

            $results = $wpdb->get_results( $query, ARRAY_A );
        }

        if ( empty( $results ) ) {
            if ( $this->count_only ) {
                return 0;
            }

            return [];
        }

        return $results;
    }

    /**
     * Get the email log statuses for filtering purposes.
     *
     * @since 1.11.0
     *
     * @return array
     */
    public static function get_statuses() {

        return [
            self::STATUS_ALL        => __( 'All', 'wp-mail-logging' ),
            self::STATUS_SUCCESSFUL => __( 'Successful', 'wp-mail-logging' ),
            self::STATUS_FAILED     => __( 'Failed', 'wp-mail-logging' ),
        ];
    }
}
