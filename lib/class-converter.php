<?php
/**
 * Main plugin class.
 *
 * @package Newspack
 */

namespace NewspackContentConverter;

/**
 * Content Converter.
 */
class Converter {

	/**
	 * The main Controller.
	 *
	 * @var ConverterController $controller The main Controller.
	 */
	private $controller;

	/**
	 * Converter constructor.
	 *
	 * @param ConverterController $controller The main Controller.
	 */
	public function __construct( ConverterController $controller ) {

		$this->controller = $controller;

		$this->add_admin_menu();
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'rest_api_init', [ $this->controller, 'register_routes' ] );
		$this->disable_autosave_posts();
		$this->register_filters();
	}

	/**
	 * Registers filters.
	 *
	 * @return void
	 */
	public function register_filters() {

		/**
		 * Filters to run on HTML content before the conversion.
		 */
		$preconversion_filters = [
			// Encode blocks as very first thing.
			[ ContentPatcher\Patchers\BlockEncodePatcher::class, 'patch_html_source' ],
			[ ContentPatcher\Patchers\WpFiltersPatcher::class, 'patch_html_source' ],
			[ ContentPatcher\Patchers\ShortcodePreconversionPatcher::class, 'patch_html_source' ],
		];
		foreach ( $preconversion_filters as $preconversion_filter ) {
			$class  = $preconversion_filter[0];
			$method = $preconversion_filter[1];
			if ( class_exists( $class ) && method_exists( $class, $method ) ) {
				$object = new $class();
				add_filter( 'ncc_filter_html_before_conversion', [ $object, $method ], 10, 2 );
			}
		}

		/**
		 * Filters to run on Blocks content after the conversion, and before getting saved to DB.
		 */
		$postconversion_filters = [
			[ ContentPatcher\Patchers\ImgPatcher::class, 'patch_blocks_contents' ],
			[ ContentPatcher\Patchers\CaptionImgPatcher::class, 'patch_blocks_contents' ],
			[ ContentPatcher\Patchers\ParagraphPatcher::class, 'patch_blocks_contents' ],
			[ ContentPatcher\Patchers\BlockquotePatcher::class, 'patch_blocks_contents' ],
			[ ContentPatcher\Patchers\VideoPatcher::class, 'patch_blocks_contents' ],
			[ ContentPatcher\Patchers\AudioPatcher::class, 'patch_blocks_contents' ],
			[ ContentPatcher\Patchers\ShortcodeModulePatcher::class, 'patch_blocks_contents' ],
			[ ContentPatcher\Patchers\ShortcodePullquotePatcher::class, 'patch_blocks_contents' ],
			// Decode blocks as the very last thing.
			[ ContentPatcher\Patchers\BlockDecodePatcher::class, 'patch_blocks_contents' ],
		];
		foreach ( $postconversion_filters as $ostconversion_filter ) {
			$class  = $ostconversion_filter[0];
			$method = $ostconversion_filter[1];
			if ( class_exists( $class ) && method_exists( $class, $method ) ) {
				$object = new $class();
				add_filter( 'ncc_filter_blocks_after_conversion', [ $object, $method ], 10, 3 );
			}
		}
	}

	/**
	 * Disables the auto-saving on the Plugin's conversion app.
	 * The Converter plugin app piggy-backs on top of the Block Editor (loads on the page
	 * /wp-admin/post-new.php?newspack-content-converter), so turning off Autosave is necessary not to have new Drafts generated
	 * while converting.
	 */
	private function disable_autosave_posts() {
		if ( ! $this->is_current_page_the_converter_app_page() ) {
			return;
		}

		if ( false === defined( 'AUTOSAVE_INTERVAL' ) ) {
			define( 'AUTOSAVE_INTERVAL', 60 * 60 * 24 * 5 ); // Seconds.
		}
	}

	/**
	 * Adds admin menu.
	 */
	private function add_admin_menu() {

		// Remember to refresh $this->is_current_page_a_plugin_page() when adding pages here.
		add_action(
			'admin_menu',
			function () {

				add_menu_page(
					__( 'Newspack Content Converter' ),
					__( 'Newspack Content Converter' ),
					'manage_options',
					'newspack-content-converter',
					function () {
						echo '<div id="ncc-conversion"></div>';
					},
					'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCI+PHBhdGggZD0iTTcgNy4yaDguMkwxMy41IDlsMS4xIDEuMSAzLjYtMy42LTMuNS00LTEuMSAxIDEuOSAyLjNIN2MtLjkgMC0xLjcuMy0yLjMuOS0xLjQgMS41LTEuNCA0LjItMS40IDUuNnYuMmgxLjV2LS4zYzAtMS4xIDAtMy41IDEtNC41LjMtLjMuNy0uNSAxLjItLjV6bTEzLjggNFYxMWgtMS41di4zYzAgMS4xIDAgMy41LTEgNC41LS4zLjMtLjcuNS0xLjMuNUg4LjhsMS43LTEuNy0xLjEtMS4xTDUuOSAxN2wzLjUgNCAxLjEtMS0xLjktMi4zSDE3Yy45IDAgMS43LS4zIDIuMy0uOSAxLjUtMS40IDEuNS00LjIgMS41LTUuNnoiIGZpbGw9IndoaXRlIi8+PC9zdmc+Cg=='
				);

				add_submenu_page(
					'newspack-content-converter',
					__( 'Converter' ),
					__( 'Converter' ),
					'manage_options',
					'newspack-content-converter',
					function () {
						echo '<div id="ncc-conversion"></div>';
					}
				);

				add_submenu_page(
					'newspack-content-converter',
					__( 'Restore content' ),
					__( 'Restore content' ),
					'manage_options',
					'newspack-content-converter-restore',
					function () {
						echo '<div id="ncc-restore"></div>';
					}
				);

				add_submenu_page(
					'newspack-content-converter',
					__( 'Settings' ),
					__( 'Settings' ),
					'manage_options',
					'newspack-content-converter-settings',
					function () {
						echo '<div id="ncc-settings"></div>';
					}
				);
			}
		);
	}

	/**
	 * Enqueues script assets.
	 */
	public function enqueue_scripts() {
		if ( ! $this->is_current_page_a_plugin_page() && ! $this->is_current_page_the_converter_app_page() ) {
			return;
		}

		wp_enqueue_script(
			'newspack-content-converter-script',
			plugins_url( '../assets/dist/main.js', __FILE__ ),
			[
				'wp-element',
				'wp-components',
				// TODO: remove those that are unused (list taken gutenberg/docs/contributors/scripts.md).
				// 'wp-blocks' .
				'wp-annotations',
				'wp-block-editor',
				'wp-blocks',
				'wp-compose',
				'wp-data',
				'wp-dom-ready',
				'wp-edit-post',
				'wp-edit-post',
				'wp-editor',
				'wp-element',
				'wp-hooks',
				'wp-i18n',
				'wp-plugins',
				'wp-rich-text',
			],
			filemtime( plugin_dir_path( __FILE__ ) . '../assets/dist/main.js' ),
			false
		);

		wp_enqueue_style(
			'newspack-content-converter-script',
			plugins_url( '../assets/dist/main.css', __FILE__ ),
			[
				'wp-components',
			],
			filemtime( plugin_dir_path( __FILE__ ) . '../assets/dist/main.css' )
		);
	}

	/**
	 * Checks if a plugin page is being accessed.
	 *
	 * @return bool
	 */
	private function is_current_page_a_plugin_page() {
		$current_screen = get_current_screen();

		return isset( $current_screen ) && ( false !== strpos( $current_screen->id, 'newspack-content-converter' ) );
	}

	/**
	 * Checks if the Gutenberg mass converter app is currently accessed, which is at
	 * /wp-admin/post-new.php?newspack-content-converter .
	 *
	 * @return bool
	 */
	private function is_current_page_the_converter_app_page() {
		global $pagenow;

		return (
			'post-new.php' === $pagenow &&
			isset( $_GET['newspack-content-converter'] ) // phpcs:ignore
		);
	}
}
