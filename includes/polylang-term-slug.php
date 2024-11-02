<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {	
	exit;
}

/**
 * Polylang_Term_Slug class.
 */
class Polylang_Term_Slug 
{
	/**
	 * Constructor - get the plugin hooked in and ready
	 */
	public function __construct() 
	{
		add_action( 'pre_post_update', array( $this, 'pre_post_update' ), 5 );
		add_filter( 'pre_term_name', array( $this, 'pre_term_name' ), 5 );
		add_filter( 'pre_term_slug', array( $this, 'pre_term_slug' ), 5, 2 );

		add_action( 'created_term', array( $this, 'save_term' ), 1, 3 );
		add_action( 'edited_term', array( $this, 'save_term' ), 1, 3 );
	}

	/**
	 * Stores the current post_id when bulk editing posts for use in save_language and pre_term_slug
	 *
	 * @since 1.9
	 *
	 * @param int $post_id The id of the current post being updated.
	 * @return void
	 */
	public function pre_post_update( $post_id ) {
		if ( isset( $_GET['bulk_edit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$this->post_id = $post_id;
		}
	}

	/**
	 * Stores the name of a term being saved, for use in the filter pre_term_slug
	 *
	 * @since 1.9
	 *
	 * @param string $name The term name to store.
	 * @return string Unmodified term name.
	 */
	public function pre_term_name( $name ) {
		return $this->pre_term_name = $name;
	}

	public function pll_model()
	{
		if ( function_exists( 'PLL' ) ) {
			return PLL()->model;
		} elseif ( array_key_exists( 'polylang', $GLOBALS ) ) {
			global $polylang;
			return $polylang->model;
		} else {
			return;
		}
	}

	/**
	 * Creates the term slug in case the term already exists in another language
	 *
	 * @since 1.9
	 *
	 * @param string $slug     The inputed slug of the term being saved, may be empty.
	 * @param string $taxonomy The term taxonomy.
	 * @return string
	 */
	public function pre_term_slug( $slug, $taxonomy ) {
		if ( ! $slug ) {
			$slug = sanitize_title( $this->pre_term_name );
		}

		

		if ( pll_is_translated_taxonomy( $taxonomy ) && term_exists( $slug, $taxonomy ) ) {
			$parent = 0;

			if ( isset( $_POST['term_lang_choice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$lang = $this->pll_model()->get_language( sanitize_key( $_POST['term_lang_choice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification

				if ( isset( $_POST['parent'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
					$parent = intval( $_POST['parent'] ); // phpcs:ignore WordPress.Security.NonceVerification
				} elseif ( isset( $_POST[ "new{$taxonomy}_parent" ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
					$parent = intval( $_POST[ "new{$taxonomy}_parent" ] ); // phpcs:ignore WordPress.Security.NonceVerification
				}
			}

			elseif ( isset( $_POST['inline_lang_choice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$lang = $this->pll_model()->get_language( sanitize_key( $_POST['inline_lang_choice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			}

			// *Post* bulk edit, in case a new term is created.
			elseif ( isset( $_GET['bulk_edit'], $_GET['inline_lang_choice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				// Bulk edit does not modify the language.
				if ( -1 == $_GET['inline_lang_choice'] ) { // phpcs:ignore WordPress.Security.NonceVerification
					$lang = $this->pll_model()->post->get_language( $this->post_id );
				} else {
					$lang = $this->pll_model()->get_language( sanitize_key( $_GET['inline_lang_choice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
				}
			}

			// Special cases for default categories as the select is disabled.
			elseif ( ! empty( $_POST['tag_ID'] ) && in_array( get_option( 'default_category' ), $this->pll_model()->term->get_translations( (int) $_POST['tag_ID'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$lang = $this->pll_model()->term->get_language( (int) $_POST['tag_ID'] ); // phpcs:ignore WordPress.Security.NonceVerification
			}

			elseif ( ! empty( $_POST['tax_ID'] ) && in_array( get_option( 'default_category' ), $this->pll_model()->term->get_translations( (int) $_POST['tax_ID'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$lang = $this->pll_model()->term->get_language( (int) $_POST['tax_ID'] ); // phpcs:ignore WordPress.Security.NonceVerification
			}

			if ( ! empty( $lang ) ) {
				$term_id = $this->pll_model()->term_exists_by_slug( $slug, $lang, $taxonomy, $parent );

				// If no term exists or if we are editing the existing term, trick WP to allow shared slugs.
				if ( ! $term_id || ( ! empty( $_POST['tag_ID'] ) && $_POST['tag_ID'] == $term_id ) || ( ! empty( $_POST['tax_ID'] ) && $_POST['tax_ID'] == $term_id ) ) { // phpcs:ignore WordPress.Security.NonceVerification
					$slug .= '___' . $lang->slug;
				}
			}
		}

		return $slug;
	}
	
	/**
	 * Ugly hack to enable the same slug in several languages
	 *
	 * @since 1.9
	 *
	 * @param int    $term_id  The term id of a saved term.
	 * @param int    $tt_id    The term taxononomy id.
	 * @param string $taxonomy The term taxonomy.
	 * @return void
	 */
	public function save_term( $term_id, $tt_id, $taxonomy ) {
		global $wpdb;

		$options = get_option( 'polylang' );

		// Does nothing except on taxonomies which are filterable.
		if ( ! $this->pll_model()->is_translated_taxonomy( $taxonomy ) || 0 === $options['force_lang'] ) {
			return;
		}

		$term = get_term( $term_id, $taxonomy );

		if ( ! ( $term instanceof WP_Term ) || false === ( $pos = strpos( $term->slug, '___' ) ) ) {
			return;
		}

		$slug = substr( $term->slug, 0, $pos );
		$lang = substr( $term->slug, $pos + 3 );

		// Need to check for unique slug as we tricked wp_unique_term_slug from WP.
		$slug = $this->unique_term_slug( $slug, $lang, (object) $term );
		$wpdb->update( $wpdb->terms, compact( 'slug' ), compact( 'term_id' ) );
		clean_term_cache( $term_id, $taxonomy );
	}

	/**
	 * Will make slug unique per language and taxonomy
	 * Mostly taken from wp_unique_term_slug
	 *
	 * @since 1.9
	 *
	 * @param string  $slug The string that will be tried for a unique slug.
	 * @param string  $lang Language slug.
	 * @param WP_Term $term The term object that the $slug will belong too.
	 * @return string Will return a true unique slug.
	 */
	protected function unique_term_slug( $slug, $lang, $term ) {
		global $wpdb;

		$original_slug = $slug; // Save this for the filter at the end.

		// Quick check.
		if ( ! $this->pll_model()->term_exists_by_slug( $slug, $lang, $term->taxonomy ) ) {
			/** This filter is documented in /wordpress/wp-includes/taxonomy.php */
			return apply_filters( 'wp_unique_term_slug', $slug, $term, $original_slug );
		}

		/*
		 * As done by WP in term_exists except that we use our own term_exist.
		 * If the taxonomy supports hierarchy and the term has a parent,
		 * make the slug unique by incorporating parent slugs.
		 */
		if ( is_taxonomy_hierarchical( $term->taxonomy ) && ! empty( $term->parent ) ) {
			$the_parent = $term->parent;
			while ( ! empty( $the_parent ) ) {
				$parent_term = get_term( $the_parent, $term->taxonomy );
				if ( ! $parent_term instanceof WP_Term ) {
					break;
				}
				$slug .= '-' . $parent_term->slug;
				if ( ! pll_term_exists_by_slug( $slug, $lang ) ) { // Calls our own term_exists.
					/** This filter is documented in /wordpress/wp-includes/taxonomy.php */
					return apply_filters( 'wp_unique_term_slug', $slug, $term, $original_slug );
				}

				if ( empty( $parent_term->parent ) ) {
					break;
				}
				$the_parent = $parent_term->parent;
			}
		}

		// If we didn't get a unique slug, try appending a number to make it unique.
		if ( ! empty( $term->term_id ) ) {
			$query = $wpdb->prepare( "SELECT slug FROM {$wpdb->terms} WHERE slug = %s AND term_id != %d", $slug, $term->term_id );
		}
		else {
			$query = $wpdb->prepare( "SELECT slug FROM {$wpdb->terms} WHERE slug = %s", $slug );
		}

		// PHPCS:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( $wpdb->get_var( $query ) ) {
			$num = 2;
			do {
				$alt_slug = $slug . "-$num";
				$num++;
				$slug_check = $wpdb->get_var( $wpdb->prepare( "SELECT slug FROM {$wpdb->terms} WHERE slug = %s", $alt_slug ) );
			} while ( $slug_check );
			$slug = $alt_slug;
		}

		/** This filter is documented in /wordpress/wp-includes/taxonomy.php */
		return apply_filters( 'wp_unique_term_slug', $slug, $term, $original_slug );
	}
}

new Polylang_Term_Slug();