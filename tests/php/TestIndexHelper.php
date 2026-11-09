<?php
/**
 * Test IndexHelper class.
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

/**
 * IndexHelper test class
 */
class TestIndexHelper extends BaseTestCase {

	/**
	 * Setup each test.
	 */
	public function set_up() {
		parent::set_up();

		ElasticPress\Elasticsearch::factory()->delete_all_indices();
		ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();
	}

	/**
	 * Test that a full sync ends once it runs out of posts when posts are removed while it runs,
	 * instead of starting over from the first post.
	 *
	 * @group index-helper
	 */
	public function test_full_index_ends_when_posts_are_removed_mid_sync() {
		global $wpdb;

		$post_ids = $this->ep_factory->post->create_many( 6 );
		rsort( $post_ids );

		// Posts are synced from the highest ID down, 2 per batch, so the last 2 are not reached by the first 2 batches.
		$unreached_post_ids = implode( ',', array_slice( $post_ids, 4 ) );
		$batches            = 0;

		$remove_unreached_posts = function () use ( &$batches, $wpdb, $unreached_post_ids ) {
			// The second batch is the first one whose total used to be reused by every later batch.
			if ( 2 === ++$batches ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
				$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE ID IN ({$unreached_post_ids})" );
			}
		};
		add_action( 'ep_pre_index_batch', $remove_unreached_posts );

		ElasticPress\IndexHelper::factory()->full_index(
			[
				'method'        => 'cli',
				'indexables'    => [ 'post' ],
				'per_page'      => 2,
				'output_method' => '__return_null',
			]
		);

		remove_action( 'ep_pre_index_batch', $remove_unreached_posts );

		$last_sync = ElasticPress\Utils\get_option( 'ep_last_cli_index' );

		$this->assertSame( 4, $last_sync['synced'] );
	}
}
