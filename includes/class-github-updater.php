<?php
/**
 * GitHub Releases updater — production ZIP assets only.
 *
 * @package Fluent_IMAP_Support_Desk
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Offers WordPress plugin updates from GitHub Release ZIP assets
 * (not source archives).
 */
class FISD_Github_Updater {

	const API_LATEST = 'https://api.github.com/repos/magpern/fluent-imap-support-desk/releases/latest';

	const TRANSIENT_RELEASE = 'fisd_github_release_latest';

	const CACHE_TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Plugin basename (e.g. fluent-imap-support-desk/fluent-imap-support-desk.php).
	 *
	 * @var string
	 */
	private $plugin_basename;

	/**
	 * Plugin directory slug.
	 *
	 * @var string
	 */
	private $plugin_slug;

	/**
	 * Installed version from BIOPENTRA_INBOX_VERSION.
	 *
	 * @var string
	 */
	private $installed_version;

	/**
	 * Register hooks when enabled.
	 */
	public static function maybe_init() {
		if ( ! self::is_enabled() ) {
			return;
		}

		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		self::$instance->register_hooks();
	}

	/**
	 * Whether the GitHub updater should run on this site.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		if ( defined( 'FISD_DISABLE_GITHUB_UPDATER' ) && FISD_DISABLE_GITHUB_UPDATER ) {
			return false;
		}

		/**
		 * Override GitHub updater enablement.
		 *
		 * Return true/false to force. Return null (default) to use environment type:
		 * enabled on `production`, disabled on development/staging/local.
		 *
		 * @param bool|null $enabled Whether the updater is enabled.
		 */
		$filtered = apply_filters( 'fisd_github_updater_enabled', null );
		if ( null !== $filtered ) {
			return (bool) $filtered;
		}

		$env = function_exists( 'wp_get_environment_type' )
			? wp_get_environment_type()
			: 'production';

		return 'production' === $env;
	}

	/**
	 * @return bool
	 */
	public static function is_prerelease_install_version( $version ) {
		return (bool) preg_match( '/-(dev|snapshot|alpha|beta|rc)(\.|$|-)/i', (string) $version );
	}

	/**
	 * Strip prerelease suffix for comparison (e.g. 2.0.3-dev → 2.0.3).
	 *
	 * @param string $version Installed version string.
	 * @return string
	 */
	public static function base_version( $version ) {
		$version = (string) $version;
		if ( self::is_prerelease_install_version( $version ) ) {
			return preg_replace( '/-(dev|snapshot|alpha|beta|rc).*$/i', '', $version );
		}
		return $version;
	}

	/**
	 * Whether to inject an update into the plugins transient.
	 *
	 * @param string $installed Installed plugin version.
	 * @param string $remote      Stable release version from GitHub.
	 * @return bool
	 */
	public static function should_offer_update( $installed, $remote ) {
		$installed = (string) $installed;
		$remote    = (string) $remote;

		if ( '' === $remote || ! preg_match( '/^\d+\.\d+\.\d+/', $remote ) ) {
			return false;
		}

		if ( self::is_prerelease_install_version( $installed ) ) {
			$offer = version_compare( $remote, self::base_version( $installed ), '>' );
		} else {
			$offer = version_compare( $remote, $installed, '>' );
		}

		/**
		 * Filter whether a GitHub release update is offered.
		 *
		 * @param bool   $offer     Default decision.
		 * @param string $installed Installed version.
		 * @param string $remote    Latest stable release version.
		 */
		return (bool) apply_filters( 'fisd_github_updater_should_offer_update', $offer, $installed, $remote );
	}

	private function __construct() {
		$this->plugin_slug       = defined( 'FISD_PLUGIN_SLUG' ) ? FISD_PLUGIN_SLUG : 'fluent-imap-support-desk';
		$this->plugin_basename   = plugin_basename( BIOPENTRA_INBOX_PATH . $this->plugin_slug . '.php' );
		$this->installed_version = defined( 'BIOPENTRA_INBOX_VERSION' ) ? BIOPENTRA_INBOX_VERSION : '0.0.0';
	}

	private function register_hooks() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'filter_update_plugins' ) );
		add_filter( 'plugins_api', array( $this, 'filter_plugins_api' ), 20, 3 );
	}

	/**
	 * @param object|false $transient Update plugins transient.
	 * @return object|false
	 */
	public function filter_update_plugins( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
			return $transient;
		}

		if ( ! isset( $transient->checked[ $this->plugin_basename ] ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();
		if ( empty( $release['version'] ) || empty( $release['package'] ) ) {
			return $transient;
		}

		if ( ! self::should_offer_update( $this->installed_version, $release['version'] ) ) {
			return $transient;
		}

		$response                 = new stdClass();
		$response->slug           = $this->plugin_slug;
		$response->plugin         = $this->plugin_basename;
		$response->new_version    = $release['version'];
		$response->url            = $release['url'];
		$response->package        = $release['package'];
		$response->icons          = array();
		$response->banners        = array();
		$response->banners_rtl    = array();
		$response->tested         = '';
		$response->requires_php   = '7.4';
		$response->requires       = '6.0';

		$transient->response[ $this->plugin_basename ] = $response;

		return $transient;
	}

	/**
	 * @param false|object|array $result Plugin API result.
	 * @param string             $action API action.
	 * @param object             $args   Query args.
	 * @return false|object|array
	 */
	public function filter_plugins_api( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || $args->slug !== $this->plugin_slug ) {
			return $result;
		}

		$release = $this->get_latest_release();
		if ( empty( $release['version'] ) ) {
			return $result;
		}

		$info          = new stdClass();
		$info->name    = 'Fluent IMAP Support Desk';
		$info->slug    = $this->plugin_slug;
		$info->version = $release['version'];
		$info->author  = '<a href="https://github.com/magpern">magpern</a>';
		$info->homepage = $release['url'];
		$info->download_link = $release['package'];
		$info->requires     = '6.0';
		$info->requires_php = '7.4';
		$info->sections     = array(
			'description' => __( 'Support desk bridging Fluent Forms with IMAP/SMTP.', 'biopentra-contact-inbox' ),
			'changelog'   => ! empty( $release['notes'] ) ? wp_kses_post( $release['notes'] ) : '',
		);

		return $info;
	}

	/**
	 * Fetch and normalize latest GitHub release (cached).
	 *
	 * @return array{version:string,package:string,url:string,notes:string}
	 */
	private function get_latest_release() {
		$cached = get_site_transient( self::TRANSIENT_RELEASE );
		if ( is_array( $cached ) && isset( $cached['version'] ) ) {
			return $cached;
		}

		$parsed = $this->fetch_latest_release();
		set_site_transient( self::TRANSIENT_RELEASE, $parsed, self::CACHE_TTL );

		return $parsed;
	}

	/**
	 * @return array{version:string,package:string,url:string,notes:string}
	 */
	private function fetch_latest_release() {
		$empty = array(
			'version' => '',
			'package' => '',
			'url'     => '',
			'notes'   => '',
		);

		$response = wp_remote_get(
			self::API_LATEST,
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'fluent-imap-support-desk-updater/' . $this->installed_version,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $empty;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return $empty;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return $empty;
		}

		if ( ! empty( $data['prerelease'] ) || ! empty( $data['draft'] ) ) {
			return $empty;
		}

		$version = $this->version_from_tag( isset( $data['tag_name'] ) ? $data['tag_name'] : '' );
		if ( '' === $version || ! preg_match( '/^\d+\.\d+\.\d+/', $version ) ) {
			return $empty;
		}

		$package = $this->find_release_zip_url( $data, $version );
		if ( '' === $package ) {
			return $empty;
		}

		return array(
			'version' => $version,
			'package' => $package,
			'url'     => isset( $data['html_url'] ) ? (string) $data['html_url'] : '',
			'notes'   => isset( $data['body'] ) ? (string) $data['body'] : '',
		);
	}

	/**
	 * @param string $tag_name Git tag (e.g. v2.0.2).
	 * @return string Semver without leading v.
	 */
	private function version_from_tag( $tag_name ) {
		$tag_name = ltrim( (string) $tag_name, 'vV' );
		if ( preg_match( '/^(\d+\.\d+\.\d+)/', $tag_name, $m ) ) {
			return $m[1];
		}
		return '';
	}

	/**
	 * Locate the production ZIP asset for this release.
	 *
	 * @param array  $data    GitHub release JSON.
	 * @param string $version Parsed stable version.
	 * @return string Download URL or empty.
	 */
	private function find_release_zip_url( array $data, $version ) {
		if ( empty( $data['assets'] ) || ! is_array( $data['assets'] ) ) {
			return '';
		}

		$expected = $this->plugin_slug . '-' . $version . '.zip';

		foreach ( $data['assets'] as $asset ) {
			if ( ! is_array( $asset ) ) {
				continue;
			}
			$name = isset( $asset['name'] ) ? (string) $asset['name'] : '';
			if ( $name !== $expected ) {
				continue;
			}
			$url = isset( $asset['browser_download_url'] ) ? (string) $asset['browser_download_url'] : '';
			if ( '' !== $url && false !== strpos( $url, 'github.com' ) ) {
				return $url;
			}
		}

		return '';
	}
}
