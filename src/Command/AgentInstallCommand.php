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
use Skouerr\Cli\Support\Filesystem;
use Skouerr\Cli\Support\Templates;
use Skouerr\Cli\Support\Theme;

/**
 * `wp skouerr agent:install`: installs the Claude Code skill and an AGENTS.md in a project.
 */
final class AgentInstallCommand extends AbstractCommand {

	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return 'agent:install';
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		return 'Install the Skouerr CLI skill (.claude/skills/skouerr-cli) and an AGENTS.md in the project.';
	}

	/**
	 * {@inheritDoc}
	 */
	public function side_effects(): array {
		return array( self::EFFECT_FILES );
	}

	/**
	 * {@inheritDoc}
	 */
	public function examples(): array {
		return array(
			'skouerr agent:install',
			'skouerr agent:install --path=/var/www/project --force',
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function parameters(): array {
		return array(
			new Parameter(
				name: 'path',
				description: 'Project directory (usually the repository root). Defaults to the theme directory.'
			),
			Theme::parameter(),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function handle( Input $input, Output $output ): string {
		$theme     = Theme::directory( $input->get( 'theme' ) );
		$directory = $input->get( 'path' ) ?? $theme;
		$real      = realpath( $directory );
		if ( false === $real || ! is_dir( $real ) ) {
			/* translators: %s: directory path. */
			throw CliException::invalid( sprintf( __( 'Directory %s not found.', 'skouerr-cli' ), $directory ), array( 'parameter' => 'path' ) );
		}

		$skill = (string) file_get_contents( SKOUERR_CLI_PATH . '/skills/skouerr-cli/SKILL.md' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$files = array( '.claude/skills/skouerr-cli/SKILL.md' => $skill );

		$filesystem = new Filesystem( $real, $output, $input->flag( 'force' ) );
		$agents     = $real . '/AGENTS.md';
		if ( ! is_file( $agents ) ) {
			$files['AGENTS.md'] = (string) file_get_contents( Templates::file( 'agents/AGENTS.md', $theme ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		} elseif ( ! str_contains( (string) file_get_contents( $agents ), 'wp skouerr' ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$output->warning( __( 'AGENTS.md exists and does not mention Skouerr CLI: it was left untouched.', 'skouerr-cli' ) );
			/* translators: %1$s: template AGENTS.md path, %2$s: project AGENTS.md path. */
			$output->next_step( sprintf( __( 'Add the instructions of %1$s to %2$s.', 'skouerr-cli' ), Templates::file( 'agents/AGENTS.md', $theme ), $agents ) );
		}

		$paths = $filesystem->write_many( $files );
		$output->set( 'files', $paths );

		return __( 'Agent files installed.', 'skouerr-cli' );
	}
}
