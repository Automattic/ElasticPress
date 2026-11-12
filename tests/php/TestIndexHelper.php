<?php
/**
 * Test InderHelper class.
 *
 * @since 4.4.0
 * @package elasticpress
 */

namespace ElasticPressTest;

/**
 * InderHelper test class
 */
class TestIndexHelper extends BaseTestCase {

	/**
	 * Test that a full sync ends when a batch comes back empty before the offset reaches the total,
	 * instead of starting over from the first object.
	 *
	 * @since 5.3.6
	 * @group index-helper
	 */
	public function test_full_index_ends_on_empty_batch() {
		global $wpdb;

		\ElasticPress\Elasticsearch::factory()->delete_all_indices();
		\ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();

		$this->ep_factory->post->create_many( 4 );

		$created = false;

		// A post created while the sync runs raises the total, but its ID is above the ones still to be synced.
		$create_post = function () use ( &$created, $wpdb ) {
			if ( $created ) {
				return;
			}

			$created = true;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				$wpdb->posts,
				[
					'post_title'            => 'Created mid-sync',
					'post_status'           => 'publish',
					'post_type'             => 'post',
					'post_content'          => '',
					'post_excerpt'          => '',
					'to_ping'               => '',
					'pinged'                => '',
					'post_content_filtered' => '',
				]
			);
		};
		add_action( 'ep_pre_index_batch', $create_post );

		\ElasticPress\IndexHelper::factory()->full_index(
			[
				'method'        => 'cli',
				'indexables'    => [ 'post' ],
				'per_page'      => 2,
				'output_method' => '__return_null',
			]
		);

		$last_sync = \ElasticPress\Utils\get_option( 'ep_last_cli_index' );

		$this->assertSame( 4, $last_sync['synced'] );
	}
}
