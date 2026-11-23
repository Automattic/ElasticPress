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

	/**
	 * Test that a sync doesn't count a batch as synced when one of its requests failed
	 *
	 * @since 5.4.0
	 * @group index-helper
	 */
	public function test_full_index_counts_batch_with_failed_request_as_failed() {
		\ElasticPress\Elasticsearch::factory()->delete_all_indices();
		\ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();

		// Each document is larger than the default buffer size, so the batch takes more than one request
		$this->ep_factory->post->create_many( 3, [ 'post_content' => str_repeat( 'lorem ipsum ', 50000 ) ] );

		// Only the first request fails
		$requests = 0;
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$requests ) {
				if ( ! str_contains( $url, '/_bulk' ) || ++$requests > 1 ) {
					return $preempt;
				}

				return [
					'body'     => '',
					'response' => [
						'code'    => 503,
						'message' => '',
					],
				];
			},
			10,
			3
		);

		\ElasticPress\IndexHelper::factory()->full_index(
			[
				'method'        => 'cli',
				'indexables'    => [ 'post' ],
				'output_method' => '__return_null',
			]
		);

		$last_sync = \ElasticPress\Utils\get_option( 'ep_last_cli_index' );

		$this->assertGreaterThan( 1, $requests, 'The batch should take more than one request' );
		$this->assertGreaterThan( 0, $last_sync['failed'], 'The batch should be counted as failed' );
	}
}
