<?php
/**
 * Plugin Name: Plugin Teste
 * Plugin URI: https://example.com
 * Description: Um plugin de teste simples para WordPress com página de configurações, shortcode e endpoint REST.
 * Version: 1.0.0
 * Author: Seu Nome
 * Author URI: https://example.com
 * License: GPLv2 or later
 * Text Domain: plugin-teste
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Bloqueia acesso direto.
}

final class Plugin_Teste {
	const OPTION_KEY = 'plugin_teste_mensagem';

	public function __construct() {
		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
		add_action( 'admin_menu', [ $this, 'register_settings_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_notices', [ $this, 'admin_notice' ] );
		add_shortcode( 'plugin_teste', [ $this, 'shortcode_output' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
	}

	/** Carrega traduções */
	public function load_textdomain() : void {
		load_plugin_textdomain( 'plugin-teste', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/** Hooks de ativação/desativação */
	public static function activate() : void {
		if ( false === get_option( self::OPTION_KEY ) ) {
			add_option( self::OPTION_KEY, 'Olá do Plugin Teste!' );
		}
	}

	public static function deactivate() : void {
		// Mantém dados por padrão. Remova a linha abaixo para excluir na desinstalação.
	}

	/** Página e registro de configurações */
	public function register_settings_page() : void {
		add_options_page(
			__( 'Plugin Teste', 'plugin-teste' ),
			__( 'Plugin Teste', 'plugin-teste' ),
			'manage_options',
			'plugin-teste',
			[ $this, 'render_settings_page' ]
		);
	}

	public function register_settings() : void {
		register_setting( 'plugin_teste_group', self::OPTION_KEY, [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'Olá do Plugin Teste!',
		] );

		add_settings_section(
			'plugin_teste_section',
			__( 'Configurações Básicas', 'plugin-teste' ),
			function () {
				echo '<p>' . esc_html__( 'Defina a mensagem que será exibida pelo shortcode e pela API.', 'plugin-teste' ) . '</p>';
			},
			'plugin-teste'
		);

		add_settings_field(
			'plugin_teste_mensagem_field',
			__( 'Mensagem', 'plugin-teste' ),
			function () {
				$value = get_option( self::OPTION_KEY, '' );
				echo '<input type="text" class="regular-text" name="' . esc_attr( self::OPTION_KEY ) . '" value="' . esc_attr( $value ) . '" />';
			},
			'plugin-teste',
			'plugin_teste_section'
		);
	}

	public function render_settings_page() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Plugin Teste', 'plugin-teste' ); ?></h1>
			<form action="options.php" method="post">
				<?php settings_fields( 'plugin_teste_group' ); ?>
				<?php do_settings_sections( 'plugin-teste' ); ?>
				<?php submit_button( __( 'Salvar alterações', 'plugin-teste' ) ); ?>
			</form>
			<p><?php echo esc_html__( 'Use o shortcode [plugin_teste nome="Maria"].', 'plugin-teste' ); ?></p>
			<p><?php echo esc_html__( 'Endpoint REST: /wp-json/plugin-teste/v1/mensagem', 'plugin-teste' ); ?></p>
		</div>
		<?php
	}

	/** Aviso rápido no admin */
	public function admin_notice() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( get_current_screen() && 'settings_page_plugin-teste' === get_current_screen()->id ) {
			return; // evita mostrar na página do próprio plugin.
		}
		echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'Plugin Teste ativo. Vá em Configurações → Plugin Teste.', 'plugin-teste' ) . '</p></div>';
	}

	/** Shortcode [plugin_teste nome="Mundo"] */
	public function shortcode_output( $atts = [] ) : string {
		$atts = shortcode_atts( [ 'nome' => 'Mundo' ], $atts, 'plugin_teste' );
		$mensagem = get_option( self::OPTION_KEY, '' );
		return '<div class="plugin-teste">' . esc_html( sprintf( '%s %s!', $mensagem, $atts['nome'] ) ) . '</div>';
	}

	/** Rota REST simples: GET /wp-json/plugin-teste/v1/mensagem */
	public function register_rest_routes() : void {
		register_rest_route( 'plugin-teste/v1', '/mensagem', [
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => function () {
				return rest_ensure_response( [
					'mensagem' => get_option( self::OPTION_KEY, '' ),
				] );
			},
		] );
	}
}

// Instancia o plugin
$__plugin_teste_instance = new Plugin_Teste();

// Registra hooks de ativação/desativação
register_activation_hook( __FILE__, [ 'Plugin_Teste', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Plugin_Teste', 'deactivate' ] );
