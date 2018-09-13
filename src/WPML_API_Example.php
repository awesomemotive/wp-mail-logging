<?php

namespace No3x\WPML;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * @author No3x
 * @since 1.0
 * The Plugin provides mechanisms to extend the displayed data.
 * This class is not an API class. It is just an example how to hook in.
 * If you consider writing a plugin please contact me for better hook support/documentation.
 */
class WPML_API_Example {

    // require_once('WPML_API_Example.php');
    // $aAPI = new WPML_API_Example();

    public function addActionsAndFilters() {
        // In this example we are going to add a column 'test' in add_column.
        add_filter( WPML_Plugin::HOOK_LOGGING_COLUMNS, array( &$this, 'add_column' ) );
        add_filter( WPML_Plugin::HOOK_LOGGING_COLUMNS_RENDER, array( &$this, 'render_column' ), 10, 2 );
        // Change the supported formats of modal e.g. dashed:
        add_filter( WPML_Plugin::HOOK_LOGGING_SUPPORTED_FORMATS, array( &$this, 'add_supported_format') );
        // Change content of format dashed HOOK_LOGGING_FORMAT_CONTENT_{$your_format} e.g. dashed:
        add_filter( WPML_Plugin::HOOK_LOGGING_FORMAT_CONTENT . '_dashed', array( &$this, 'supported_format_dashed') );
    }

    /**
     * Is called when List Table is gathering columns.
     * @since 1.0
     * @param array $columns Array of columns.
     * @return array $columns Updated array of columns.
     */
    public function add_column( $columns ) {
        return $columns = array_merge( $columns,
            array( 'test' => 'test' )
        //,array('test2'	=> 'wp-mail-logging' ) // ...
        );
    }

    /**
     * Is called when the List Table could not find the column. So we can hook in and modify the column.
     * @since 1.0
     * @param array $item A singular item (one full row's worth of data).
     * @param array $column_name The name/slug of the column to be processed.
     * @return string Text or HTML to be placed inside the column <td>
     */
    public function render_column( $item, $column_name ) {
        switch ( $column_name ) {
            case 'test':
                return 'display relevant data. item contains all information you need about the row. You can process the data and add the result to this column. You can access it like this: $item[$column_name]';
            default:
                return '';
        }
    }

    /**
     * Is called when supported formats are collected. You can add a format here then you can provide a content function.
     * @since 1.6.0
     * @param array $formats supported formats
     * @return array supported formats + your additional formats
     * @see WPML_Plugin::HOOK_LOGGING_SUPPORTED_FORMATS
     */
    public function add_supported_format( $formats ) {
        $formats[] = 'dashed';
        return $formats;
    }

    /**
     * This function is called for each of your additional formats. Change the content of the modal here.
     * For example I add some dashes.
     * @since 1.6.0
     * @param $mail
     * @return string
     * @see WPML_Plugin::HOOK_LOGGING_FORMAT_CONTENT
     */
    public function supported_format_dashed( $mail ) {
        $dashedAppend = '';
        foreach( $mail as $property => $value )
            $dashedAppend .= str_replace(' ', '-', $value);
        return $dashedAppend;
    }
}
