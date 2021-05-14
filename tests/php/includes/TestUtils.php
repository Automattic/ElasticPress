<?php
/**
 * Test the includes/utils.php functionality
 *
 * @package elasticpress
 */

namespace ElasticPressTest\Utils;

use ElasticPress;

/**
 * Stats test class
 */
class TestUtils extends \ElasticPressTest\BaseTestCase {

	/**
	 * Setup each test.
	 *
	 * @since 3.2
	 */
	public function setUp() {
		global $wpdb;
		parent::setUp();
	}

	/**
	 * Clean up after each test.
	 *
	 * @since 3.2
	 */
	public function tearDown() {
		parent::tearDown();
	}

	/**
	 * Test the flatten_tree_array function.
	 *
	 * @return void
	 */
	public function test_flatten_tree_array() {
		$this->generate_test_flatten_tree_array( 0, 0 );
		$this->generate_test_flatten_tree_array( 1, 1 );
		$this->generate_test_flatten_tree_array( 3, 4 );
		$this->generate_test_flatten_tree_array( 100, 5 );
		$this->generate_test_flatten_tree_array( 5, 100 );
		$this->generate_test_flatten_tree_array( 5, 0 );
	}

	/**
	 * Generate test function for testing flatten_tree_array function.˝
	 *
	 * @param integer $base_size Array tree parent size.
	 * @param integer $child_size Array tree parent child size.
	 * @return void
	 */
	private function generate_test_flatten_tree_array( $base_size = 1, $child_size = 1 ) {

		$expected_size = ( $base_size ) + ( $base_size ) * ( $child_size );

		$test_object = $this->generate_array_tree( $base_size, $child_size );

		$flatten_array = ( \ElasticPress\Utils\flatten_tree_array( $test_object ) );

		$this->assertEquals( $expected_size, count( $flatten_array ) );

	}

	/**
	 * Generate an array tree with one child.
	 *
	 * @param integer $base_size Array tree parent size.
	 * @param integer $child_size Array tree parent child size.
	 * @return void
	 */
	private function generate_array_tree( $base_size, $child_size ) {
		$object = (object) array(
			'id'       => 1,
			'children' => null,
			'name'     => 'test',
		);

		$test_object = array();

		if ( $base_size < 1 ) {
			return $test_object;
		}

		foreach ( range( 1, $base_size ) as $i ) {
			$test_object[ $i ]     = clone $object;
			$test_object[ $i ]->id = $i;
			if ( $child_size < 1 ) {
				continue;
			}
			foreach ( range( $i * 100 + 1, $i * 100 + $child_size ) as $k ) {
				$test_object[ $i ]->children[ $k ]     = clone $object;
				$test_object[ $i ]->children[ $k ]->id = $k;
			}
		}

		return $test_object;
	}
}
