<?php
/**
 * FTSA RDS TLS database drop-in.
 *
 * Forces WordPress MySQL connections to Amazon RDS to negotiate TLS while
 * preserving the standard wpdb behavior and DB credentials supplied by
 * wp-config.php / ECS runtime configuration.
 *
 * This file intentionally contains no credentials or secret values.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wpdb' ) ) {
	return;
}

if ( ! defined( 'FTSA_RDS_CA_FILE' ) ) {
	define( 'FTSA_RDS_CA_FILE', '/etc/pki/rds/us-east-2-bundle.pem' );
}

class FTSA_RDS_TLS_WPDB extends wpdb {
	/**
	 * Connects to Amazon RDS using mysqli with certificate verification enabled.
	 *
	 * @param bool $allow_bail Whether WordPress may bail on connection failure.
	 * @return bool True on success, false on failure.
	 */
	public function db_connect( $allow_bail = true ) {
		$this->is_mysql = true;

		$client_flags = defined( 'MYSQL_CLIENT_FLAGS' ) ? MYSQL_CLIENT_FLAGS : 0;
		$client_flags |= MYSQLI_CLIENT_SSL;

		mysqli_report( MYSQLI_REPORT_OFF );

		$this->dbh = mysqli_init();

		if ( ! $this->dbh ) {
			return $this->ftsa_tls_connection_failure(
				$allow_bail,
				'Unable to initialize the MySQL client.'
			);
		}

		if ( ! is_readable( FTSA_RDS_CA_FILE ) ) {
			$this->dbh = null;

			return $this->ftsa_tls_connection_failure(
				$allow_bail,
				'Amazon RDS TLS trust bundle is unavailable.'
			);
		}

		if ( defined( 'MYSQLI_OPT_SSL_VERIFY_SERVER_CERT' ) ) {
			mysqli_options( $this->dbh, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true );
		}

		if ( ! mysqli_ssl_set( $this->dbh, null, null, FTSA_RDS_CA_FILE, null, null ) ) {
			$this->dbh = null;

			return $this->ftsa_tls_connection_failure(
				$allow_bail,
				'Unable to configure TLS for the MySQL client.'
			);
		}

		$host    = $this->dbhost;
		$port    = null;
		$socket  = null;
		$is_ipv6 = false;

		$host_data = $this->parse_db_host( $this->dbhost );
		if ( $host_data ) {
			list( $host, $port, $socket, $is_ipv6 ) = $host_data;
		}

		if ( $is_ipv6 && extension_loaded( 'mysqlnd' ) ) {
			$host = "[$host]";
		}

		if ( WP_DEBUG ) {
			mysqli_real_connect(
				$this->dbh,
				$host,
				$this->dbuser,
				$this->dbpassword,
				null,
				$port,
				$socket,
				$client_flags
			);
		} else {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@mysqli_real_connect(
				$this->dbh,
				$host,
				$this->dbuser,
				$this->dbpassword,
				null,
				$port,
				$socket,
				$client_flags
			);
		}

		if ( $this->dbh->connect_errno ) {
			$this->dbh = null;
		}

		if ( ! $this->dbh ) {
			return $this->ftsa_tls_connection_failure(
				$allow_bail,
				'Unable to establish the database TLS connection.'
			);
		}

		/*
		 * The upstream wpdb implementation only initializes charset on the first
		 * successful connection. The upstream state flag is private, therefore a
		 * drop-in subclass cannot access it safely. Re-initializing charset on a
		 * successful reconnect is deterministic and preserves the effective
		 * connection contract.
		 */
		$this->init_charset();
		$this->set_charset( $this->dbh );

		$this->ready = true;
		$this->set_sql_mode();
		$this->select( $this->dbname, $this->dbh );

		return true;
	}

	/**
	 * Preserves WordPress-style fail/return behavior without exposing secrets.
	 *
	 * @param bool   $allow_bail Whether WordPress may bail.
	 * @param string $message    Non-sensitive diagnostic message.
	 * @return false
	 */
	private function ftsa_tls_connection_failure( $allow_bail, $message ) {
		if ( ! $allow_bail ) {
			return false;
		}

		if ( function_exists( 'wp_load_translations_early' ) ) {
			wp_load_translations_early();
		}

		if ( file_exists( WP_CONTENT_DIR . '/db-error.php' ) ) {
			require_once WP_CONTENT_DIR . '/db-error.php';
			die();
		}

		$this->bail(
			'<h1>' . esc_html__( 'Error establishing a database connection' ) . '</h1>' .
			'<p>' . esc_html( $message ) . '</p>',
			'db_connect_fail'
		);

		return false;
	}
}

$wpdb = new FTSA_RDS_TLS_WPDB( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
