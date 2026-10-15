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
use Skouerr\Cli\Scaffold\BlockScaffold;
use Skouerr\Cli\Support\Dashicons;
use Skouerr\Cli\Support\Filesystem;
use Skouerr\Cli\Support\Templates;
use Skouerr\Cli\Support\Theme;
use Skouerr\Cli\Support\Validator;

/**
 * `wp skouerr make:block`: scaffolds a block in `<theme>/blocks/<name>/`.
 */
final class MakeBlockCommand extends AbstractCommand {

	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return 'make:block';
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		return 'Create a block in the theme (native or ACF, PHP or Twig template).';
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
			'skouerr make:block --title="Hero Banner" --type=native --template=twig',
			'skouerr make:block --title="Team" --type=acf --template=php --icon=groups --fields=\'[{"name":"heading","type":"text"},{"name":"members","type":"repeater","sub_fields":[{"name":"photo","type":"image"}]}]\' --format=json',
			'skouerr make:block --title="Accordion" --type=interactive --template=php --attributes=\'{"open":{"type":"boolean","default":false}}\'',
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function parameters(): array {
		return array(
			new Parameter(
				name: 'title',
				description: 'Human-readable title of the block.',
				required: true,
				prompt: __( 'Title of the block', 'skouerr-cli' )
			),
			new Parameter(
				name: 'name',
				description: 'Block name (folder and file names). Defaults to the slugified title.',
				pattern: Validator::BLOCK_SEGMENT,
				prompt: __( 'Name of the block', 'skouerr-cli' )
			),
			new Parameter(
				name: 'type',
				description: 'Block type.',
				default: 'native',
				options: BlockScaffold::TYPES,
				prompt: __( 'Type of block', 'skouerr-cli' )
			),
			new Parameter(
				name: 'template',
				description: 'Template engine.',
				default: 'php',
				options: BlockScaffold::ENGINES,
				prompt: __( 'Template engine', 'skouerr-cli' )
			),
			new Parameter(
				name: 'prefix',
				description: 'Block namespace, the part before the slash in the block name.',
				default: 'skouerr',
				pattern: Validator::BLOCK_SEGMENT,
				prompt: __( 'Block namespace', 'skouerr-cli' )
			),
			new Parameter(
				name: 'icon',
				description: 'Dashicon slug (https://developer.wordpress.org/resource/dashicons/).',
				default: 'block-default',
				pattern: Validator::ICON,
				prompt: __( 'Dashicon', 'skouerr-cli' )
			),
			new Parameter(
				name: 'category',
				description: 'Block category in the inserter.',
				default: 'theme_utils-blocks',
				pattern: Validator::IDENTIFIER
			),
			new Parameter(
				name: 'text-domain',
				description: 'Text domain of block.json. Defaults to the theme text domain.',
				pattern: Validator::TEXT_DOMAIN
			),
			new Parameter(
				name: 'attributes',
				description: 'Block attributes as a JSON object, merged into block.json: {"title": {"type": "string", "default": ""}}.'
			),
			new Parameter(
				name: 'fields',
				description: 'ACF fields as a JSON array, written to acf-json/ (ACF blocks only): [{"name": "title", "type": "text", "label": "Title"}]. Any ACF field setting is accepted; "sub_fields" for repeaters and groups.'
			),
			Theme::parameter(),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function handle( Input $input, Output $output ): string {
		$theme     = $input->get( 'theme' );
		$directory = Theme::directory( $theme );

		$title    = Validator::label( $input->require( 'title' ) );
		$name     = Validator::assert( $input->require( 'name', Validator::slugify( $title ) ), Validator::BLOCK_SEGMENT, 'name' );
		$type     = $input->require( 'type' );
		$template = $input->require( 'template' );
		$prefix   = $input->require( 'prefix' );
		$icon     = Validator::assert( Dashicons::normalize( $input->require( 'icon' ) ), Validator::ICON, 'icon' );
		$category = $input->require( 'category' );
		$domain   = $input->require( 'text-domain', Theme::text_domain( $theme ) );
		$slug     = $prefix . '/' . $name;

		$source = Templates::directory( 'block/' . $type . '-' . $template, $directory );
		if ( null === $source ) {
			throw CliException::invalid(
				/* translators: %1$s: template engine, %2$s: block type. */
				sprintf( __( 'No "%1$s" template for "%2$s" blocks.', 'skouerr-cli' ), $template, $type ),
				array( 'choices' => Templates::block_kinds( $directory ) )
			);
		}

		$attributes = $input->has( 'attributes' ) ? BlockSchema::attributes( (string) $input->get( 'attributes' ) ) : array();
		$fields     = $input->has( 'fields' ) ? BlockSchema::fields( (string) $input->get( 'fields' ) ) : array();
		if ( ! empty( $fields ) && 'acf' !== $type ) {
			throw CliException::invalid( __( '--fields only applies to ACF blocks (--type=acf). Use --attributes for other blocks.', 'skouerr-cli' ), array( 'parameter' => 'fields' ) );
		}

		$this->warn( $output, $type, $template, $prefix, $icon );

		/* translators: %1$s: block title, %2$s: block name, %3$s: block type, %4$s: template engine, %5$s: dashicon. */
		$summary = sprintf( __( '%1$s (%2$s) — %3$s, %4$s template, icon %5$s', 'skouerr-cli' ), $title, $slug, $type, $template, $icon );
		/* translators: %s: block summary. */
		if ( ! $input->confirm( sprintf( __( 'Create block %s?', 'skouerr-cli' ), $summary ) ) ) {
			throw new CliException( __( 'Block creation aborted.', 'skouerr-cli' ), ExitCode::Aborted );
		}

		$files = array();
		$block = array(
			'name'       => $name,
			'title'      => $title,
			'slug'       => $slug,
			'icon'       => $icon,
			'textdomain' => $domain,
			'category'   => $category,
			'attributes' => $attributes,
		);
		foreach ( ( new BlockScaffold() )->files( $source, $template, $block ) as $path => $contents ) {
			$files[ 'blocks/' . $name . '/' . $path ] = $contents;
		}
		if ( ! empty( $fields ) ) {
			$files[ 'acf-json/' . BlockSchema::group_key( $slug ) . '.json' ] = BlockScaffold::encode( BlockSchema::field_group( $slug, $title, $fields ) );
		}

		$filesystem = new Filesystem( $directory, $output, $input->flag( 'force' ) );
		$filesystem->write_many( $files );

		$output->set(
			'block',
			array(
				'name'       => $slug,
				'title'      => $title,
				'type'       => $type,
				'template'   => $template,
				'icon'       => $icon,
				'category'   => $category,
				'textdomain' => $domain,
				'attributes' => array_keys( $attributes ),
				'fields'     => array_map( static fn( array $field ): string => (string) $field['name'], $fields ),
				'directory'  => $filesystem->path( 'blocks/' . $name ),
				'source'     => $source,
			)
		);
		$output->next_step( __( 'Build the block assets with the theme bundler (e.g. npm run build).', 'skouerr-cli' ) );
		if ( 'react' === $template ) {
			/* translators: %1$s: block folder name. */
			$output->next_step( sprintf( __( 'Add blocks/%1$s/src/%1$s.editor.js as a JSX entry of the bundler, output blocks/%1$s/dist/editor.js.', 'skouerr-cli' ), $name ) );
		}

		/* translators: %s: block name. */
		return sprintf( __( 'Block %s created.', 'skouerr-cli' ), $slug );
	}

	/**
	 * Warns about missing integrations.
	 *
	 * @param Output $output   Output.
	 * @param string $type     Block type.
	 * @param string $template Template engine.
	 * @param string $prefix   Block namespace.
	 * @param string $icon     Icon.
	 */
	private function warn( Output $output, string $type, string $template, string $prefix, string $icon ): void {
		if ( ! Dashicons::exists( $icon ) ) {
			/* translators: %s: dashicon name. */
			$output->warning( sprintf( __( 'Unknown dashicon "%s".', 'skouerr-cli' ), $icon ) );
		}
		if ( 'core' === $prefix ) {
			$output->warning( __( 'The "core" namespace is reserved for WordPress core blocks.', 'skouerr-cli' ) );
		}
		if ( 'acf' === $type && ! function_exists( 'acf_register_block_type' ) ) {
			$output->warning( __( 'ACF Pro is not active: ACF blocks will not be registered.', 'skouerr-cli' ) );
		}
		if ( 'twig' === $template && ! class_exists( '\Timber\Timber' ) ) {
			$output->warning( __( 'Timber is not installed: Twig templates will not render.', 'skouerr-cli' ) );
		}
	}
}
