<?php
/**
 * Test utils functionality
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

/**
 * Dashboard test class
 */
class TestUtils extends BaseTestCase {

	/**
	 * Setup each test.
	 *
	 * @since 3.2
	 */
	public function set_up() {
		global $wpdb;
		parent::set_up();
		$wpdb->suppress_errors();

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $admin_id );

		wp_set_current_user( $admin_id );

		ElasticPress\Elasticsearch::factory()->delete_all_indices();
		ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();

		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->reset_sync_queue();

		$this->setup_test_post_type();

		$this->current_host = get_option( 'ep_host' );

		global $hook_suffix;
		$hook_suffix = 'sites.php';
		set_current_screen();
	}

	/**
	 * Clean up after each test.
	 *
	 * @since 3.2
	 */
	public function tear_down() {
		parent::tear_down();

		// Update since we are deleting to test notifications
		update_site_option( 'ep_host', $this->current_host );

		ElasticPress\Screen::factory()->set_current_screen( null );
	}

	/**
	 * Check that a site is indexable by default
	 *
	 * @since 3.2
	 * @group utils
	 */
	public function testIsSiteIndexableByDefault() {
		delete_option( 'ep_indexable' );

		$this->assertTrue( ElasticPress\Utils\is_site_indexable() );
	}

	/**
	 * Check that a spam site is NOT indexable by default
	 *
	 * @since 3.2
	 * @group utils
	 */
	public function testIsSiteIndexableByDefaultSpam() {
		delete_option( 'ep_indexable' );

		if ( is_multisite() ) {
			update_blog_status( get_current_blog_id(), 'spam', 1 );

			$this->assertFalse( ElasticPress\Utils\is_site_indexable() );

			update_blog_status( get_current_blog_id(), 'spam', 0 );
		} else {
			$this->assertTrue( ElasticPress\Utils\is_site_indexable() );
		}
	}

	/**
	 * Check that a site is not indexable after being set that way in the admin
	 *
	 * @since 3.2
	 * @group utils
	 */
	public function testIsSiteIndexableDisabled() {
		update_option( 'ep_indexable', 'no' );

		if ( is_multisite() ) {
			$this->assertFalse( ElasticPress\Utils\is_site_indexable() );
		} else {
			$this->assertTrue( ElasticPress\Utils\is_site_indexable() );
		}
	}

	/**
	 * Tests the sanitize_credentials utils function.
	 *
	 * @return void
	 */
	public function testSanitizeCredentials() {

		// First test anything that is not an array.
		$creds = \ElasticPress\Utils\sanitize_credentials( false );
		$this->assertTrue( is_array( $creds ) );

		$this->assertArrayHasKey( 'username', $creds );
		$this->assertArrayHasKey( 'token', $creds );

		$this->assertSame( '', $creds['username'] );
		$this->assertSame( '', $creds['token'] );

		// Then test arrays with invalid data.
		$creds = \ElasticPress\Utils\sanitize_credentials( [] );

		$this->assertTrue( is_array( $creds ) );

		$this->assertArrayHasKey( 'username', $creds );
		$this->assertArrayHasKey( 'token', $creds );

		$this->assertSame( '', $creds['username'] );
		$this->assertSame( '', $creds['token'] );

		$creds = \ElasticPress\Utils\sanitize_credentials(
			[
				'username' => '<strong>hello</strong> world',
				'token'    => 'able <script>alert("baker");</script>',
			]
		);

		$this->assertTrue( is_array( $creds ) );

		$this->assertArrayHasKey( 'username', $creds );
		$this->assertArrayHasKey( 'token', $creds );

		$this->assertSame( 'hello world', $creds['username'] );
		$this->assertSame( 'able', $creds['token'] );

		// Finally, test with valid data.
		$creds = \ElasticPress\Utils\sanitize_credentials(
			[
				'username' => 'my-user-name',
				'token'    => 'my-token',
			]
		);

		$this->assertTrue( is_array( $creds ) );

		$this->assertArrayHasKey( 'username', $creds );
		$this->assertArrayHasKey( 'token', $creds );

		$this->assertSame( 'my-user-name', $creds['username'] );
		$this->assertSame( 'my-token', $creds['token'] );
	}

	/**
	 * Tests the is_indexing function.
	 *
	 * @return void
	 */
	public function testIsIndexing() {

		// VIP: We use per-site option
		update_option( 'ep_index_meta', [ 'method' => 'test' ] );

		$this->assertTrue( ElasticPress\Utils\is_indexing() );

		// VIP: We use per-site option
		delete_option( 'ep_index_meta' );

		$this->assertFalse( ElasticPress\Utils\is_indexing() );
	}

	/**
	 * Tests get_term_object_ids() returns each object once, in ascending order, across several terms
	 *
	 * @since 5.4.0
	 * @group utils
	 */
	public function test_get_term_object_ids_for_several_terms() {
		$term_1 = $this->factory->term->create( [ 'taxonomy' => 'post_tag' ] );
		$term_2 = $this->factory->term->create( [ 'taxonomy' => 'post_tag' ] );

		$in_both   = $this->factory->post->create();
		$in_term_2 = $this->factory->post->create();
		$in_term_1 = $this->factory->post->create();
		wp_set_object_terms( $in_both, [ $term_1, $term_2 ], 'post_tag' );
		wp_set_object_terms( $in_term_2, [ $term_2 ], 'post_tag' );
		wp_set_object_terms( $in_term_1, [ $term_1 ], 'post_tag' );

		$expected = [ $in_both, $in_term_2, $in_term_1 ];

		// Both terms in one query
		$this->assertSame( $expected, ElasticPress\Utils\get_term_object_ids( [ $term_1, $term_2 ], 'post_tag' ) );

		// One object ID per query, so each term is queried separately and their results are merged
		add_filter(
			'ep_term_object_ids_chunk_size',
			function () {
				return 1;
			}
		);

		$this->assertSame( $expected, ElasticPress\Utils\get_term_object_ids( [ $term_1, $term_2 ], 'post_tag' ) );
		$this->assertSame( [], ElasticPress\Utils\get_term_object_ids( [ $term_1 ], 'category' ) );
		$this->assertSame( [], ElasticPress\Utils\get_term_object_ids( [], 'post_tag' ) );
	}

	/**
	 * Tests get_term_object_ids() stops at the limit, counting objects shared by several terms once
	 *
	 * @since 5.4.0
	 * @group utils
	 */
	public function test_get_term_object_ids_limit() {
		$term_1 = $this->factory->term->create( [ 'taxonomy' => 'post_tag' ] );
		$term_2 = $this->factory->term->create( [ 'taxonomy' => 'post_tag' ] );

		$post_ids = $this->factory->post->create_many( 4 );
		wp_set_object_terms( $post_ids[0], [ $term_1, $term_2 ], 'post_tag' );
		wp_set_object_terms( $post_ids[1], [ $term_1, $term_2 ], 'post_tag' );
		wp_set_object_terms( $post_ids[2], [ $term_2 ], 'post_tag' );
		wp_set_object_terms( $post_ids[3], [ $term_2 ], 'post_tag' );

		// One object ID per query, so each term is its own batch and the second one repeats the first one's objects
		add_filter(
			'ep_term_object_ids_chunk_size',
			function () {
				return 1;
			}
		);

		$this->assertSame( array_slice( $post_ids, 0, 3 ), ElasticPress\Utils\get_term_object_ids( [ $term_1, $term_2 ], 'post_tag', 3 ) );
		$this->assertSame( $post_ids, ElasticPress\Utils\get_term_object_ids( [ $term_1, $term_2 ], 'post_tag' ) );
	}
}
