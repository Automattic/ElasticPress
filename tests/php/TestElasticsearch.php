<?php
/**
 * Test Elasticsearch methods
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;
use ElasticPress\Utils;

/**
 * Elasticsearch test class
 */
class TestElasticsearch extends BaseTestCase {
	/**
	 * Cluster status
	 *
	 * Test cluster status.
	 *
	 * @since 0.1.0
	 * @group elasticsearch
	 */
	public function testGetClusterStatus() {

		$status_indexed = ElasticPress\Elasticsearch::factory()->get_cluster_status();

		ElasticPress\Elasticsearch::factory()->delete_all_indices();

		$status_unindexed = ElasticPress\Elasticsearch::factory()->get_cluster_status();

		$this->set_up();

		if ( is_array( $status_indexed ) ) {

			$this->assertTrue( $status_indexed['status'] );

		} else {

			$this->assertTrue( isset( $status_indexed->cluster_name ) );

		}

		if ( is_array( $status_unindexed ) ) {

			$this->assertTrue( $status_unindexed['status'] );

		} else {

			$this->assertTrue( isset( $status_unindexed->cluster_name ) );

		}
	}

	/**
	 * Test get documents
	 *
	 * @since 3.6.0
	 * @group elasticsearch
	 */
	public function testGetDocuments() {

		$post_ids   = array();
		$post_ids[] = $this->ep_factory->post->create();
		$post_ids[] = $this->ep_factory->post->create();

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$index_name = ElasticPress\Indexables::factory()->get( 'post' )->get_index_name();

		$documents = ElasticPress\Elasticsearch::factory()->get_documents( $index_name, 'post', $post_ids );

		$this->assertIsArray( $documents );
		$this->assertEquals( 2, count( $documents ) );
		$this->assertArrayHasKey( $post_ids[0], $documents );
		$this->assertArrayHasKey( $post_ids[1], $documents );

		$post_ids[] = 99999999; // Adding an id that doesn't exist

		$documents = ElasticPress\Elasticsearch::factory()->get_documents( $index_name, 'post', $post_ids );

		$this->assertIsArray( $documents );
		$this->assertEquals( 2, count( $documents ) );
		$this->assertArrayHasKey( $post_ids[0], $documents );
		$this->assertArrayHasKey( $post_ids[1], $documents );

		// Trying to get a document that doesn't exist
		$documents = ElasticPress\Elasticsearch::factory()->get_documents( $index_name, 'post', [ 99999999 ] );

		$this->assertIsArray( $documents );
		$this->assertEmpty( $documents );

		$documents = ElasticPress\Elasticsearch::factory()->get_documents( $index_name, 'post', [] );

		$this->assertIsArray( $documents );
		$this->assertEmpty( $documents );
	}

	/**
	 * Test the ep_disable_query_logging filter
	 *
	 * @since 5.1.4
	 * @group elasticsearch
	 */
	public function testEpDisableQueryLoggingFilter() {
		$elasticsearch = new \ElasticPress\Elasticsearch();

		$reflection = new \ReflectionClass( $elasticsearch );
		$property   = $reflection->getProperty( 'queries' );
		$property->setAccessible( true );
		$method = $reflection->getMethod( 'add_query_log' );
		$method->setAccessible( true );

		$example_query = [ 'example_query' ];

		add_filter( 'ep_disable_query_logging', '__return_true' );

		$method->invokeArgs( $elasticsearch, [ $example_query ] );
		$this->assertEmpty( $property->getValue( $elasticsearch ) );

		remove_filter( 'ep_disable_query_logging', '__return_true' );

		$method->invokeArgs( $elasticsearch, [ $example_query ] );

		$queries = $property->getValue( $elasticsearch );
		$this->assertCount( 1, $queries );
		unset( $queries[0]['backtrace'] );
		$this->assertSame( $example_query, $queries[0] );
	}

	/**
	 * Test the `ep_remote_request` action
	 *
	 * @since 5.2.0
	 * @group elasticsearch
	 */
	public function test_ep_remote_request_action() {
		$elasticsearch = new \ElasticPress\Elasticsearch();

		// Make sure we don't fire any real request
		add_filter( 'ep_do_intercept_request', '__return_empty_array' );

		$callback = function ( $query, $type ) {
			$this->assertIsArray( $query );
			$this->assertSame( 'example_type', $type );
		};
		add_action( 'ep_remote_request', $callback, 10, 2 );

		// It starts with 1, likely because of some previous tests.
		$initial_count = did_action( 'ep_remote_request' );

		$elasticsearch->remote_request( '', [], [], 'example_type' );
		$this->assertSame( $initial_count + 1, did_action( 'ep_remote_request' ) );
	}
}
