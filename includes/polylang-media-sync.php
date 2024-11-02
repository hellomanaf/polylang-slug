<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {	
	exit;
}

/**
 * Polylang_Media_Sync class.
 */
class Polylang_Media_Sync 
{
	/**
	 * Constructor - get the plugin hooked in and ready
	 */
	public function __construct() 
	{
		add_action( 'init', array( $this, 'sync_media' ) );

		add_action( 'add_attachment', array( $this, 'media_setting' ), 99 );
	}

	public function sync_media()
	{
	    $sync_media = isset($_REQUEST['sync_media']) ? sanitize_text_field($_REQUEST['sync_media']) : '';

	    if($sync_media === 'yes')
	    {
	        if( function_exists('pll_the_languages') ) 
	        {
	            $args = array(
	                'post_type' => 'attachment',
	                'post_status' => 'inherit',
	                'posts_per_page' => -1,
	            );
	            $medies = new WP_Query( $args );

	            if($medies->found_posts > 0)
	            {
	                $languages = pll_the_languages( array(
	                    'raw' => true
	                ) );

	                foreach ($medies->posts as $key => $medie) 
	                {
	                    foreach ( $languages as $lang => $language ) 
	                    {
	                        $columns = [
	                            'object_id',
	                            'term_taxonomy_id',
	                            'term_order',
	                        ];

	                        $data = [
	                            'object_id' => $medie->ID,
	                            'term_taxonomy_id' => $language['id'],
	                            'term_order' => 0,
	                        ];

	                        //file_put_contents(dirname(__FILE__) . '/shoot.txt', "\n" . print_r($data, true));

	                        $this->wp_insert_on_duplicate('term_relationships', $columns, [$data]);
	                    }
	                }
	            }
	        }
	    }
	}

	public function media_setting($post_ID)
	{
	    if( function_exists('pll_the_languages') ) 
	    {
	        $languages = pll_the_languages( array(
	                'raw' => true
	            ) );

	        foreach ( $languages as $lang => $language ) 
	        {
	            $columns = [
	                'object_id',
	                'term_taxonomy_id',
	                'term_order',
	            ];

	            $data = [
	                'object_id' => $post_ID,
	                'term_taxonomy_id' => $language['id'],
	                'term_order' => 0,
	            ];

	            $this->wp_insert_on_duplicate('term_relationships', $columns, [$data]);
	        }
	    }
	}

	/**
	 * wp_insert_on_duplicate function.
	 * @access public
	 * @param 
	 * @return 
	 * @since 1.0.0
	 */
	public function wp_insert_on_duplicate($table_name = '', $columns = [], $rows = []) 
	{
	    global $wpdb;

	    $columns_name = implode(',',$columns);

	    $rows_value = '';
	    $i = 0;
	    foreach ($rows as $key => $row) 
	    {
	        if($i > 0)
	        {
	            $rows_value .= ',';
	        }

	        $rows_value .= "('" . implode("','", $row) . "')";

	        $i++;
	    }

	    $columns_value = '';
	    foreach ($columns as $key => $column) 
	    {
	        if($key > 0)
	            $columns_value .= ',';

	        $columns_value .= $column . '=VALUES(' . $column . ')';
	    }

	    $sql = "INSERT INTO {$wpdb->prefix}{$table_name} ({$columns_name}) 

	            VALUES {$rows_value}

	            ON DUPLICATE KEY UPDATE 
	                    {$columns_value};";

	    /* error_log($sql); */

	    $wpdb->query($sql);

	    /* echo $wpdb->last_query;
	    die; */
	}
}

new Polylang_Media_Sync();