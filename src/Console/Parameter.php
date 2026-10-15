<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Console;

/**
 * Declaration of a command parameter.
 *
 * A single declaration feeds the WP-CLI synopsis, the interactive prompts,
 * the validation and the machine-readable schema (`wp skouerr describe`).
 */
final readonly class Parameter {

	/**
	 * Constructor.
	 *
	 * @param string                $name        Name of the `--name=<value>` option.
	 * @param string                $description Description.
	 * @param bool                  $required    The command fails without a value (after prompt and default).
	 * @param string|null           $default     Default value.
	 * @param array<string, string> $options     Allowed values (value => label).
	 * @param string|null           $pattern     Validation regular expression.
	 * @param bool                  $flag        Boolean `--name` flag.
	 * @param string|null           $prompt      Question asked in interactive mode. Null: never prompt.
	 * @param bool                  $positional  Positional argument instead of option.
	 */
	public function __construct(
		public string $name,
		public string $description,
		public bool $required = false,
		public ?string $default = null,
		public array $options = array(),
		public ?string $pattern = null,
		public bool $flag = false,
		public ?string $prompt = null,
		public bool $positional = false,
	) {}

	/**
	 * Shortcut for a boolean flag.
	 *
	 * @param string $name        Flag name.
	 * @param string $description Description.
	 */
	public static function flag( string $name, string $description ): self {
		return new self( name: $name, description: $description, flag: true );
	}

	/**
	 * WP-CLI synopsis entry.
	 *
	 * Defaults and options are not passed to WP-CLI on purpose: the command
	 * resolves them itself so it can prompt, validate and report errors in JSON.
	 *
	 * @return array<string, mixed>
	 */
	public function to_synopsis(): array {
		$description = $this->description;
		if ( ! empty( $this->options ) ) {
			$description .= ' One of: ' . implode( ', ', array_keys( $this->options ) ) . '.';
		}
		if ( null !== $this->default ) {
			$description .= ' Default: ' . $this->default . '.';
		}

		return array(
			'type'        => $this->positional ? 'positional' : ( $this->flag ? 'flag' : 'assoc' ),
			'name'        => $this->name,
			'description' => $description,
			'optional'    => true,
		);
	}

	/**
	 * JSON schema-like description for agents.
	 *
	 * @return array<string, mixed>
	 */
	public function to_schema(): array {
		$schema = array(
			'name'        => $this->name,
			'kind'        => $this->positional ? 'positional' : ( $this->flag ? 'flag' : 'option' ),
			'type'        => $this->flag ? 'boolean' : 'string',
			'required'    => $this->required,
			'description' => $this->description,
		);
		if ( null !== $this->default ) {
			$schema['default'] = $this->default;
		}
		if ( ! empty( $this->options ) ) {
			$schema['enum'] = array_map( 'strval', array_keys( $this->options ) );
		}
		if ( null !== $this->pattern ) {
			$schema['pattern'] = $this->pattern;
		}
		return $schema;
	}
}
