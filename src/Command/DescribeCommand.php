<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Command;

use Skouerr\Cli\Console\CliException;
use Skouerr\Cli\Console\ExitCode;
use Skouerr\Cli\Console\Input;
use Skouerr\Cli\Console\Output;
use Skouerr\Cli\Console\Parameter;
use Skouerr\Cli\Plugin;

/**
 * `wp skouerr describe`: machine-readable manifest of the CLI, for agents and scripts.
 */
final class DescribeCommand extends AbstractCommand {

	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return 'describe';
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		return 'Describe the commands, parameters, side effects and exit codes as JSON.';
	}

	/**
	 * {@inheritDoc}
	 */
	public function examples(): array {
		return array(
			'skouerr describe',
			'skouerr describe make:block',
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function parameters(): array {
		return array(
			new Parameter(
				name: 'command',
				description: 'Only describe this command (e.g. make:block).',
				positional: true
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function handle( Input $input, Output $output ): string {
		$name     = $input->get( 'command' );
		$commands = array();
		foreach ( Plugin::commands() as $command ) {
			if ( null === $name || $command->name() === $name ) {
				$commands[] = $command->schema();
			}
		}

		if ( empty( $commands ) ) {
			throw CliException::invalid(
				/* translators: %s: command name. */
				sprintf( __( 'Unknown command "%s".', 'skouerr-cli' ), $name ),
				array( 'choices' => array_map( static fn( AbstractCommand $command ): string => $command->name(), Plugin::commands() ) )
			);
		}

		$manifest = array(
			'name'        => 'skouerr-cli',
			'version'     => SKOUERR_CLI_VERSION,
			'conventions' => array(
				'invocation'      => 'wp skouerr <command> [--<option>=<value>] [--<flag>]',
				'json_output'     => 'Pass --format=json: exactly one JSON document on STDOUT, {"ok": bool, ...}, and nothing else is printed by the CLI.',
				'non_interactive' => 'Prompts are disabled with --format=json, --no-interaction, a non-TTY STDIN, or the SKOUERR_NO_INTERACTION / CI environment variables. Missing required parameters then fail with exit code 2 and the parameter schema in error.details.',
				'dry_run'         => 'Commands with the "filesystem" side effect accept --dry-run (report only) and --force (overwrite).',
				'paths'           => 'Every reported path is absolute.',
			),
			'exit_codes'  => array_combine(
				array_map( static fn( ExitCode $code ): string => (string) $code->value, ExitCode::cases() ),
				array_map( static fn( ExitCode $code ): string => $code->slug(), ExitCode::cases() )
			),
			'commands'    => $commands,
		);

		$output->document( 'manifest', $manifest, (string) wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );

		/* translators: %d: number of commands. */
		return sprintf( __( '%d command(s) described.', 'skouerr-cli' ), count( $commands ) );
	}
}
