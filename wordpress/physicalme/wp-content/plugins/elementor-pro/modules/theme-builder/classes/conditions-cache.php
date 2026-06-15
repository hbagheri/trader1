<?php
namespace ElementorPro\Modules\ThemeBuilder\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stub replacement for the upstream Conditions_Cache (file was deleted by
 * mistake and Elementor Pro is paid, so we can't re-download it).
 *
 * The real upstream class caches the (template_id => conditions[]) map in a
 * WP option, keyed by location. This stub skips the cache and queries the DB
 * directly each time. Performance is fine for low template counts.
 *
 * Drop-in compatible with all Conditions_Manager call sites:
 *   add(), save(), get_by_location(), regenerate(), remove(), clear()
 */
class Conditions_Cache {

	public function add( $document, $conditions ) {
		return $this;
	}

	public function save() {
		return $this;
	}

	public function remove( $post_id ) {
		return $this;
	}

	public function clear() {
		return $this;
	}

	public function regenerate() {
		return true;
	}

	/**
	 * @param string $location e.g. "header", "footer", "single", "archive"
	 * @return array<int, string[]>  Map of template_id → array of condition strings
	 */
	public function get_by_location( $location ) {
		global $wpdb;

		$post_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT p.ID
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} m
				 ON m.post_id = p.ID AND m.meta_key = '_elementor_template_type'
			 WHERE p.post_type   = 'elementor_library'
			   AND p.post_status = 'publish'
			   AND m.meta_value  = %s",
			$location
		) );

		$groups = [];
		foreach ( $post_ids as $pid ) {
			$pid = (int) $pid;
			$conditions = get_post_meta( $pid, '_elementor_conditions', true );
			if ( is_array( $conditions ) && ! empty( $conditions ) ) {
				$groups[ $pid ] = $conditions;
			}
		}
		return $groups;
	}
}
