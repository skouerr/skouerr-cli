<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli;

use Skouerr\Cli\Command\AbstractCommand;
use Skouerr\Cli\Console\BufferWriter;
use Skouerr\Cli\Console\Input;
use Skouerr\Cli\Console\Output;
use Skouerr\Cli\Console\Parameter;

/**
 * Exposes the commands as WordPress abilities (Abilities API, WordPress 6.9+).
 *
 * Each ability runs the same command code as WP-CLI, in non-interactive JSON
 * mode, so REST clients and MCP agents get the same contract as `--format=json`.
 * Commands that download code or run programs (make:theme, import:block,
 * agent:install) stay CLI-only.
 */
final class Abilities {

	/** Ability category. */
	public const string CATEGORY = 'skouerr';

	/** Commands never exposed as abilities. */
	private const array CLI_ONLY = array( 'list', 'describe', 'make:theme', 'import:block', 'agent:install' );

	/** Common CLI options that make no sense for abilities. */
	private const array CLI_OPTIONS = array( 'format', 'interaction' );

	/** Commands that remove or rewrite existing content. */
	private const array DESTRUCTIVE = array( 'delete:block', 'rename:block', 'save:styles', 'save:template', 'save:pattern' );

	/**
	 * Hooks the registration.
	 */
	public static function register(): void {
		add_action( 'wp_abilities_api_categories_init', array( self::class, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( self::class, 'register_abilities' ) );
	}

	/**
	 * Registers the ability category.
	 */
	public static function register_category(): void {
		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => 'Skouerr',
				'description' => 'Scaffold and maintain the Skouerr FSE theme: blocks, post types, templates, patterns, styles.',
			)
		);
	}

	/**
	 * Registers one ability per eligible command.
	 */
	public static function register_abilities(): void {
		foreach ( self::commands() as $command ) {
			wp_register_ability( self::id( $command ), self::definition( $command ) );
		}
	}

	/**
	 * Commands exposed as abilities.
	 *
	 * @return list<AbstractCommand>
	 */
	public static function commands(): array {
		return array_values(
			array_filter(
				Plugin::commands(),
				static fn( AbstractCommand $command ): bool => ! in_array( $command->name(), self::CLI_ONLY, true )
			)
		);
	}

	/**
	 * Ability ID of a command: "make:block" → "skouerr/make-block".
	 *
	 * @param AbstractCommand $command Command.
	 * @return lowercase-string&non-falsy-string
	 */
	public static function id( AbstractCommand $command ): string {
		return strtolower( self::CATEGORY . '/' . str_replace( ':', '-', $command->name() ) );
	}

	/**
	 * Arguments of wp_register_ability().
	 *
	 * @param AbstractCommand $command Command.
	 * @return array<string, mixed>
	 */
	public static function definition( AbstractCommand $command ): array {
		$readonly = array() === $command->side_effects();

		return array(
			'label'               => 'Skouerr: ' . $command->name(),
			'description'         => $command->summary(),
			'category'            => self::CATEGORY,
			'input_schema'        => self::input_schema( $command ),
			'output_schema'       => array(
				'type'        => 'object',
				'description' => 'Same document as `wp skouerr ' . $command->name() . ' --format=json`.',
				'properties'  => array(
					'ok'         => array( 'type' => 'boolean' ),
					'command'    => array( 'type' => 'string' ),
					'dry_run'    => array( 'type' => 'boolean' ),
					'message'    => array( 'type' => 'string' ),
					'data'       => array( 'type' => 'object' ),
					'files'      => array( 'type' => 'array' ),
					'warnings'   => array( 'type' => 'array' ),
					'next_steps' => array( 'type' => 'array' ),
				),
			),
			'execute_callback'    => static fn( mixed $input = null ): mixed => self::execute( $command, is_array( $input ) ? $input : array() ),
			'permission_callback' => static fn(): bool => self::can_run( $command ),
			'meta'                => array(
				'show_in_rest' => true,
				'mcp'          => array( 'public' => true ),
				'annotations'  => array(
					'readonly'    => $readonly,
					'destructive' => in_array( $command->name(), self::DESTRUCTIVE, true ),
					'idempotent'  => $readonly,
				),
			),
		);
	}

	/**
	 * JSON schema of the ability input, from the command parameters.
	 *
	 * @param AbstractCommand $command Command.
	 * @return array<string, mixed>
	 */
	public static function input_schema( AbstractCommand $command ): array {
		$properties = array();
		$required   = array();
		foreach ( self::parameters( $command ) as $parameter ) {
			$property = array(
				'type'        => $parameter->flag ? 'boolean' : 'string',
				'description' => $parameter->description,
			);
			if ( ! empty( $parameter->options ) ) {
				$property['enum'] = array_map( 'strval', array_keys( $parameter->options ) );
			}
			if ( null !== $parameter->default ) {
				$property['default'] = $parameter->default;
			}
			$properties[ $parameter->name ] = $property;
			if ( $parameter->required ) {
				$required[] = $parameter->name;
			}
		}

		$schema = array(
			'type'                 => 'object',
			'properties'           => $properties,
			'additionalProperties' => false,
		);
		if ( ! empty( $required ) ) {
			$schema['required'] = $required;
		}
		return $schema;
	}

	/**
	 * Runs a command and returns its JSON document.
	 *
	 * @param AbstractCommand      $command Command.
	 * @param array<string, mixed> $input   Ability input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function execute( AbstractCommand $command, array $input ): array|\WP_Error {
		$assoc_args = array();
		foreach ( self::parameters( $command ) as $parameter ) {
			if ( ! array_key_exists( $parameter->name, $input ) || null === $input[ $parameter->name ] ) {
				continue;
			}
			$value = $input[ $parameter->name ];
			if ( $parameter->flag ) {
				if ( true === $value ) {
					$assoc_args[ $parameter->name ] = true;
				}
				continue;
			}
			if ( is_scalar( $value ) ) {
				$assoc_args[ $parameter->name ] = (string) $value;
			}
		}

		// Agents get the same English messages as `--format=json`.
		unload_textdomain( 'skouerr-cli' );
		$writer = new BufferWriter();
		$output = new Output( $writer, $command->name(), true, ! empty( $assoc_args['dry-run'] ) );
		$command->execute( new Input( $command->all_parameters(), array(), $assoc_args ), $output );

		$document = json_decode( $writer->stdout, true );
		if ( ! is_array( $document ) ) {
			return new \WP_Error( 'skouerr_internal_error', 'The command returned an invalid document.' );
		}
		if ( true !== ( $document['ok'] ?? false ) ) {
			$error = is_array( $document['error'] ?? null ) ? $document['error'] : array();
			return new \WP_Error(
				'skouerr_' . ( $error['code'] ?? 'error' ),
				(string) ( $error['message'] ?? 'Command failed.' ),
				array(
					'status'  => self::http_status( (string) ( $error['code'] ?? '' ) ),
					'details' => $error['details'] ?? array(),
				)
			);
		}
		return $document;
	}

	/**
	 * Tells whether the current user may run a command.
	 *
	 * Commands that write files need `edit_themes`, which WordPress removes when
	 * DISALLOW_FILE_EDIT is set, and file modifications allowed for this context.
	 *
	 * @param AbstractCommand $command Command.
	 */
	public static function can_run( AbstractCommand $command ): bool {
		if ( array() === $command->side_effects() ) {
			return current_user_can( 'edit_theme_options' );
		}
		return current_user_can( 'edit_themes' ) && wp_is_file_mod_allowed( 'skouerr_cli_ability' );
	}

	/**
	 * Parameters accepted by an ability.
	 *
	 * @param AbstractCommand $command Command.
	 * @return list<Parameter>
	 */
	private static function parameters( AbstractCommand $command ): array {
		return array_values(
			array_filter(
				$command->all_parameters(),
				static fn( Parameter $parameter ): bool => ! $parameter->positional && ! in_array( $parameter->name, self::CLI_OPTIONS, true )
			)
		);
	}

	/**
	 * HTTP status of an error code.
	 *
	 * @param string $code Error code.
	 */
	private static function http_status( string $code ): int {
		return match ( $code ) {
			'invalid_input' => 400,
			'conflict'      => 409,
			'aborted'       => 412,
			'remote_error'  => 502,
			default         => 500,
		};
	}
}
