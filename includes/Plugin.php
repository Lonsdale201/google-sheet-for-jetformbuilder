<?php

namespace GoogleSheet\JetFormBuilder;

use GoogleSheet\JetFormBuilder\Action\GoogleSheetAction;
use GoogleSheet\JetFormBuilder\Settings\SettingsTab;
use GoogleSheet\JetFormBuilder\Rest\SheetsRoutes;
use Jet_Form_Builder\Actions\Manager as ActionsManager;
use Jet_Form_Builder\Form_Messages\Manager as Messages_Manager;
use YahnisElsts\PluginUpdateChecker\v5p0\PucFactory;

class Plugin {

	private static ?self $instance = null;

	private string $slug = 'google-sheet-for-jetformbuilder';

	private string $plugin_file;

	private string $version;

	private function __construct( string $plugin_file ) {
		$this->plugin_file = $plugin_file;
		$this->version     = get_file_data( $plugin_file, array( 'Version' => 'Version' ) )['Version'] ?? '0.0';

		$this->init_updater();
		$this->init_hooks();
	}

	public static function instance( ?string $plugin_file = null ): self {
		if ( null === self::$instance ) {
			if ( null === $plugin_file ) {
				throw new \RuntimeException( 'Plugin file path is required on first initialization.' );
			}

			self::$instance = new self( $plugin_file );
		}

		return self::$instance;
	}

	public function slug(): string {
		return $this->slug;
	}

	public function version(): string {
		return $this->version;
	}

	public function path( string $path = '' ): string {
		return plugin_dir_path( $this->plugin_file ) . ltrim( $path, '/' );
	}

	public function url( string $path = '' ): string {
		return plugin_dir_url( $this->plugin_file ) . ltrim( $path, '/' );
	}

	private function init_hooks(): void {
		add_filter(
			'plugin_action_links_' . plugin_basename( $this->plugin_file ),
			array( $this, 'add_action_links' )
		);

		add_filter(
			'jet-form-builder/register-tabs-handlers',
			array( $this, 'register_tabs' )
		);

		add_action(
			'jet-form-builder/actions/register',
			array( $this, 'register_actions' )
		);

		add_action(
			'jet-form-builder/editor-assets/before',
			array( $this, 'enqueue_editor_assets' )
		);

		add_action(
			'rest_api_init',
			array( $this, 'register_rest_routes' )
		);

		add_action(
			'jet-form-builder/form-handler/after-send',
			array( $this, 'maybe_adjust_response_message' ),
			10,
			2
		);
	}

	public function add_action_links( array $links ): array {
		$settings_url = admin_url( 'edit.php?post_type=jet-form-builder&page=jfb-settings#google-sheet-settings-tab' );

		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( $settings_url ), esc_html__( 'Configure', 'google-sheet-for-jetformbuilder' ) )
		);

		return $links;
	}

	public function register_tabs( array $tabs ): array {
		$tabs[] = new SettingsTab();

		return $tabs;
	}

	public function register_actions( ActionsManager $manager ): void {
		$manager->register_action_type( new GoogleSheetAction() );
	}

	public function enqueue_editor_assets(): void {
		$script_rel_path = 'assets/js/action-editor.js';
		$script_path     = $this->path( $script_rel_path );
		$version         = file_exists( $script_path ) ? (string) filemtime( $script_path ) : $this->version();

		$css_rel_path = 'assets/css/settings-tab.css';
		$css_path     = $this->path( $css_rel_path );
		$css_version  = file_exists( $css_path ) ? (string) filemtime( $css_path ) : $this->version();

		wp_register_script(
			$this->slug() . '-action-editor',
			$this->url( $script_rel_path ),
			array( 'jet-fb-components', 'wp-element', 'wp-components', 'wp-i18n' ),
			$version,
			true
		);

		wp_register_style(
			$this->slug() . '-action-editor',
			$this->url( $css_rel_path ),
			array(),
			$css_version
		);

		wp_enqueue_script( $this->slug() . '-action-editor' );
		wp_enqueue_style( $this->slug() . '-action-editor' );
	}

	public function register_rest_routes(): void {
		( new SheetsRoutes() )->register();
	}

	/**
	 * Override the form response message if a custom success/skip message was stored.
	 *
	 * @param object $form_handler
	 * @param bool   $is_success
	 */
	public function maybe_adjust_response_message( $form_handler, bool $is_success ): void {
		if ( ! $is_success || ! isset( $form_handler->action_handler ) ) {
			return;
		}

		$message = $form_handler->action_handler->get_context( 'google_sheet', 'gsjfb_success_message' );

		if ( ! $message ) {
			return;
		}

		$form_handler->set_response_args(
			array(
				'status'  => Messages_Manager::dynamic_success( $message ),
				'message' => $message,
			)
		);
	}

	private function init_updater(): void {
		if ( ! class_exists( PucFactory::class ) ) {
			return;
		}

		PucFactory::buildUpdateChecker(
			'https://pluginupdater.hellodevs.dev/plugins/google-sheet-for-jetformbuilder.json',
			$this->plugin_file,
			'google-sheet-for-jetformbuilder'
		);
	}
}
