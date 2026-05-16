<?php
/**
 * Core singleton: category registry, settings access, and boot orchestration.
 *
 * @package PreFlight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PreFlight_Core {

	/** @var self|null */
	private static $instance = null;

	/** @var PreFlight_Check_Category[] Keyed by category ID. */
	private $categories = array();

	private function __construct() {}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Fire the category registration action.
	 *
	 * Check category classes hook onto 'preflight_register_categories' and call
	 * $core->register_category( new My_Category() ). No category is instantiated
	 * in this file — adding a new category requires no modification here (§11).
	 */
	public function boot(): void {
		do_action( 'preflight_register_categories', $this );
	}

	/**
	 * Register a check category with the core registry.
	 *
	 * @param PreFlight_Check_Category $category
	 */
	public function register_category( PreFlight_Check_Category $category ): void {
		$this->categories[ $category->get_category_id() ] = $category;
	}

	/**
	 * Return all registered check categories.
	 *
	 * @return PreFlight_Check_Category[]
	 */
	public function get_categories(): array {
		return $this->categories;
	}

	/**
	 * Read a single value from the preflight_settings option (§5.3).
	 *
	 * @param string $key     Top-level key within the settings array.
	 * @param mixed  $default Returned when the key is absent.
	 * @return mixed
	 */
	public function get_setting( string $key, $default = null ) {
		$settings = get_option( 'preflight_settings', array() );
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}
}
