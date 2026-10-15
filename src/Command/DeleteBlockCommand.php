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
use Skouerr\Cli\Scaffold\BlockSchema;
use Skouerr\Cli\Support\BlockUsage;
use Skouerr\Cli\Support\Filesystem;
use Skouerr\Cli\Support\Theme;
use Skouerr\Cli\Support\Validator;

/**
 * `wp skouerr delete:block`: deletes the folder of a block (and its generated ACF field group).
 */
final class DeleteBlockCommand extends AbstractCommand {

	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return 'delete:block';
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		return 'Delete a block folder from the theme. Reports where the block is still used.';
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
			'skouerr delete:block --name=hero --dry-run --format=json',
			'skouerr delete:block --name=hero --yes',
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function parameters(): array {
		return array(
			new Parameter(
				name: 'name',
				description: 'Folder name of the block in blocks/.',
				required: true,
				pattern: Validator::BLOCK_SEGMENT,
				prompt: __( 'Block to delete', 'skouerr-cli' )
			),
			Theme::parameter(),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function handle( Input $input, Output $output ): string {
		$filesystem = new Filesystem( Theme::directory( $input->get( 'theme' ) ), $output );
		$name       = $input->has( 'name' )
			? (string) $input->get( 'name' )
			: $input->choose( 'name', RenameBlockCommand::existing_blocks( $filesystem ) );
		$files      = $filesystem->read_tree( 'blocks/' . $name );
		if ( empty( $files ) ) {
			throw CliException::invalid(
				/* translators: %s: block folder name. */
				sprintf( __( 'Block folder blocks/%s not found.', 'skouerr-cli' ), $name ),
				array( 'choices' => array_keys( RenameBlockCommand::existing_blocks( $filesystem ) ) )
			);
		}

		$slug = RenameBlockCommand::block_name( $files, $name );
		if ( null !== $slug ) {
			$group = 'acf-json/' . BlockSchema::group_key( $slug ) . '.json';
			if ( $filesystem->exists( $group ) ) {
				$files[ $group ] = '';
			}
			$this->report_usage( $filesystem, $output, $slug );
		}

		/* translators: %1$d: number of files, %2$s: block name. */
		if ( ! $output->is_dry_run() && ! $input->confirm( sprintf( __( 'Delete %1$d files of block %2$s?', 'skouerr-cli' ), count( $files ), $slug ?? $name ), false ) ) {
			throw new CliException( __( 'Deletion aborted. Pass --yes to confirm in non-interactive mode.', 'skouerr-cli' ), ExitCode::Aborted );
		}

		$filesystem->delete_many( array_keys( $files ) );
		$output->set( 'block', $slug ?? $name );

		/* translators: %s: block name. */
		return sprintf( __( 'Block %s deleted.', 'skouerr-cli' ), $slug ?? $name );
	}

	/**
	 * Warns when the block is still used.
	 *
	 * @param Filesystem $filesystem Theme filesystem.
	 * @param Output     $output     Output.
	 * @param string     $slug       Block name.
	 */
	private function report_usage( Filesystem $filesystem, Output $output, string $slug ): void {
		$posts = BlockUsage::posts( $slug );
		$files = array_keys( BlockUsage::theme_files( $filesystem, $slug ) );
		$output->set(
			'usage',
			array(
				'posts' => $posts,
				'files' => $files,
			)
		);
		if ( ! empty( $posts ) ) {
			/* translators: %1$s: block name, %2$d: number of posts, %3$s: list of posts. */
			$output->warning( sprintf( __( 'Block %1$s is still used in %2$d post(s): %3$s.', 'skouerr-cli' ), $slug, count( $posts ), implode( ', ', $posts ) ) );
		}
		if ( ! empty( $files ) ) {
			/* translators: %1$s: block name, %2$s: list of files. */
			$output->warning( sprintf( __( 'Block %1$s is still used in theme files: %2$s.', 'skouerr-cli' ), $slug, implode( ', ', $files ) ) );
		}
	}
}
