<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Command;

use Skouerr\Cli\Console\CliException;
use Skouerr\Cli\Console\Input;
use Skouerr\Cli\Console\Output;
use Skouerr\Cli\Console\Parameter;
use Skouerr\Cli\Support\Archive;
use Skouerr\Cli\Support\Filesystem;
use Skouerr\Cli\Support\Process;
use Skouerr\Cli\Support\Remote;
use Skouerr\Cli\Support\Validator;
use WP_CLI;

/**
 * `wp skouerr make:theme`: downloads the Skouerr starter theme and installs it.
 */
final class MakeThemeCommand extends AbstractCommand {

	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return 'make:theme';
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		return 'Download the Skouerr starter theme into wp-content/themes and set it up.';
	}

	/**
	 * {@inheritDoc}
	 */
	public function side_effects(): array {
		return array( self::EFFECT_NETWORK, self::EFFECT_FILES, self::EFFECT_PROCESS, self::EFFECT_DATABASE );
	}

	/**
	 * {@inheritDoc}
	 */
	public function examples(): array {
		return array(
			'skouerr make:theme --title="Acme" --name=acme --activate',
			'skouerr make:theme --title="Acme" --name=acme --skip-composer --format=json',
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function parameters(): array {
		return array(
			new Parameter(
				name: 'title',
				description: 'Theme name displayed in WordPress.',
				required: true,
				prompt: __( 'Title of the theme', 'skouerr-cli' )
			),
			new Parameter(
				name: 'name',
				description: 'Theme folder name. Defaults to the slugified title.',
				pattern: Validator::IDENTIFIER,
				prompt: __( 'Folder name of the theme', 'skouerr-cli' )
			),
			new Parameter(
				name: 'text_domain',
				description: 'Text domain of the theme. Defaults to the folder name.',
				pattern: Validator::TEXT_DOMAIN,
				prompt: __( 'Text domain of the theme', 'skouerr-cli' )
			),
			new Parameter(
				name: 'sha256',
				description: 'Expected SHA-256 checksum of the downloaded archive.',
				pattern: Validator::SHA256
			),
			Parameter::flag( 'activate', 'Activate the theme and run "wp skouerr-theme setup".' ),
			Parameter::flag( 'skip-composer', 'Do not run "composer install" in the theme.' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function handle( Input $input, Output $output ): string {
		$title       = Validator::label( $input->require( 'title' ) );
		$name        = Validator::assert( $input->require( 'name', Validator::slugify( $title ) ), Validator::IDENTIFIER, 'name' );
		$text_domain = $input->require( 'text_domain', str_replace( '_', '-', $name ) );
		$directory   = get_theme_root() . '/' . $name;

		if ( file_exists( $directory ) && ! $input->flag( 'force' ) ) {
			throw CliException::conflict( $directory );
		}

		$url = add_query_arg(
			array(
				'title'       => rawurlencode( $title ),
				'name'        => rawurlencode( $name ),
				'text_domain' => rawurlencode( $text_domain ),
			),
			SKOUERR_REMOTE_THEME_URL
		);

		$output->info( __( 'Downloading theme…', 'skouerr-cli' ) );
		$download = Remote::download( $url, $input->get( 'sha256' ) );
		if ( ! $download['verified'] ) {
			$output->warning( __( 'The archive checksum was not verified (no --sha256 and no X-Skouerr-Sha256 header).', 'skouerr-cli' ) );
		}

		$output->info( __( 'Extracting theme…', 'skouerr-cli' ) );
		try {
			$extracted = Archive::extract_to_temp( $download['path'] );
			try {
				( new Filesystem( $directory, $output, $input->flag( 'force' ) ) )->copy_tree( $extracted );
			} finally {
				Archive::remove( $extracted );
			}
		} finally {
			wp_delete_file( $download['path'] );
		}

		$output->set(
			'theme',
			array(
				'name'        => $name,
				'title'       => $title,
				'text_domain' => $text_domain,
				'directory'   => $directory,
				'sha256'      => $download['sha256'],
			)
		);

		if ( $output->is_dry_run() ) {
			/* translators: %s: theme folder name. */
			return sprintf( __( 'Theme %s would be installed.', 'skouerr-cli' ), $name );
		}

		if ( ! $input->flag( 'skip-composer' ) ) {
			$this->install_composer_dependencies( $directory, $output );
		}

		$activate = $input->flag( 'activate' ) || ( $input->is_interactive() && $input->confirm( __( 'Activate the theme now?', 'skouerr-cli' ), false ) );
		if ( $activate ) {
			switch_theme( $name );
			$output->info( __( 'Theme activated.', 'skouerr-cli' ) );
			$this->run_theme_setup( $input, $output );
		} else {
			$output->next_step( 'wp theme activate ' . $name );
			$output->next_step( 'wp skouerr-theme setup' );
		}

		/* translators: %s: theme folder name. */
		return sprintf( __( 'Theme %s created.', 'skouerr-cli' ), $name );
	}

	/**
	 * Runs "composer install" in the theme, without a shell.
	 *
	 * @param string $directory Theme directory.
	 * @param Output $output    Output.
	 */
	private function install_composer_dependencies( string $directory, Output $output ): void {
		if ( ! file_exists( $directory . '/composer.json' ) ) {
			return;
		}

		$composer = Process::find( 'composer' );
		if ( null === $composer ) {
			$output->warning( __( 'Composer not found in PATH: run "composer install" in the theme manually.', 'skouerr-cli' ) );
			$output->next_step( 'composer install --working-dir=' . $directory );
			return;
		}

		$output->info( __( 'Installing Composer dependencies…', 'skouerr-cli' ) );
		$result = Process::run( array( $composer, 'install', '--no-interaction', '--no-progress' ), $directory );
		if ( 0 !== $result['code'] ) {
			/* translators: %s: error output of Composer. */
			$output->warning( sprintf( __( 'composer install failed: %s', 'skouerr-cli' ), trim( substr( $result['stderr'], -500 ) ) ) );
			$output->next_step( 'composer install --working-dir=' . $directory );
		}
	}

	/**
	 * Runs the setup command of the Skouerr theme in a new process.
	 *
	 * Humans get the command attached to the terminal (it may prompt);
	 * scripts and agents get its output captured.
	 *
	 * @param Input  $input  Input.
	 * @param Output $output Output.
	 */
	private function run_theme_setup( Input $input, Output $output ): void {
		if ( $input->is_interactive() ) {
			WP_CLI::runcommand( 'skouerr-theme setup', array( 'exit_error' => false ) );
			return;
		}

		$result = WP_CLI::runcommand(
			'skouerr-theme setup',
			array(
				'return'     => 'all',
				'exit_error' => false,
			)
		);

		$result = is_object( $result ) ? get_object_vars( $result ) : array();
		if ( 0 !== (int) ( $result['return_code'] ?? 0 ) ) {
			/* translators: %s: error output of the setup command. */
			$output->warning( sprintf( __( 'wp skouerr-theme setup failed: %s', 'skouerr-cli' ), trim( (string) ( $result['stderr'] ?? '' ) ) ) );
			$output->next_step( 'wp skouerr-theme setup' );
		}
	}
}
