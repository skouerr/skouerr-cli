<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Command;

use Skouerr\Cli\Console\Input;
use Skouerr\Cli\Console\Output;
use Skouerr\Cli\Plugin;

/**
 * `wp skouerr list`: lists the commands.
 */
final class ListCommand extends AbstractCommand {

	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return 'list';
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		return 'List the Skouerr commands.';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function parameters(): array {
		return array();
	}

	/**
	 * {@inheritDoc}
	 */
	protected function handle( Input $input, Output $output ): string {
		$rows = array();
		foreach ( Plugin::commands() as $command ) {
			$rows[] = array(
				'command'      => 'wp skouerr ' . $command->name(),
				'summary'      => $command->summary(),
				'side_effects' => implode( ', ', $command->side_effects() ),
			);
		}
		$output->table( 'commands', $rows, array( 'command', 'summary', 'side_effects' ) );
		$output->next_step( 'wp skouerr describe --format=json' );

		/* translators: %d: number of commands. */
		return sprintf( __( '%d commands. Run "wp help skouerr <command>" for details.', 'skouerr-cli' ), count( $rows ) );
	}
}
