<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Console;

/**
 * Resolves command parameters.
 *
 * Resolution order for each parameter: command-line value, interactive
 * prompt (only when a human is at the terminal), fallback, declared default.
 * A required parameter without value fails with a structured error that
 * tells the caller exactly which option to pass.
 */
final class Input {

	/**
	 * Parameters indexed by name.
	 *
	 * @var array<string, Parameter>
	 */
	private array $parameters = array();

	/**
	 * Raw values indexed by parameter name.
	 *
	 * @var array<string, mixed>
	 */
	private array $values;

	/**
	 * Constructor.
	 *
	 * @param list<Parameter>      $parameters Declared parameters.
	 * @param list<string>         $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @param Prompter|null        $prompter   Prompter, null in non-interactive mode.
	 */
	public function __construct( array $parameters, array $args, array $assoc_args, private readonly ?Prompter $prompter = null ) {
		$this->values = $assoc_args;
		$position     = 0;
		foreach ( $parameters as $parameter ) {
			$this->parameters[ $parameter->name ] = $parameter;
			if ( $parameter->positional ) {
				if ( isset( $args[ $position ] ) ) {
					$this->values[ $parameter->name ] = $args[ $position ];
				}
				++$position;
			}
		}
	}

	/**
	 * Tells whether a human can answer prompts.
	 */
	public function is_interactive(): bool {
		return null !== $this->prompter;
	}

	/**
	 * Output format: "json" or "text".
	 */
	public function format(): string {
		return 'json' === ( $this->values['format'] ?? null ) ? 'json' : 'text';
	}

	/**
	 * Tells whether the caller passed a value for the parameter.
	 *
	 * @param string $name Parameter name.
	 */
	public function has( string $name ): bool {
		return isset( $this->values[ $name ] ) && '' !== $this->values[ $name ];
	}

	/**
	 * Value of a boolean flag.
	 *
	 * @param string $name Flag name.
	 */
	public function flag( string $name ): bool {
		$value = $this->values[ $name ] ?? false;
		return true === $value || in_array( $value, array( '1', 'true', 'yes' ), true );
	}

	/**
	 * Resolves the value of a parameter.
	 *
	 * @param string      $name     Parameter name.
	 * @param string|null $fallback Value used when nothing was passed. Overrides the declared default.
	 * @throws CliException When a required value is missing or the value is invalid.
	 */
	public function get( string $name, ?string $fallback = null ): ?string {
		$parameter = $this->parameter( $name );
		$default   = $fallback ?? $parameter->default;
		$value     = $this->raw( $parameter );

		if ( null === $value && null !== $this->prompter && null !== $parameter->prompt ) {
			$value = empty( $parameter->options )
				? $this->prompter->ask( $parameter->prompt, $default )
				: $this->prompter->choose( $parameter->prompt, $parameter->options, $default );
		}

		if ( null === $value || '' === $value ) {
			$value = $default;
		}

		if ( null === $value ) {
			if ( $parameter->required ) {
				throw CliException::invalid(
					/* translators: %1$s: parameter name, %2$s: parameter description. */
					sprintf( __( 'Missing required parameter --%1$s (%2$s).', 'skouerr-cli' ), $name, $parameter->description ),
					array( 'parameter' => $parameter->to_schema() )
				);
			}
			return null;
		}

		return $this->validate( $parameter, $value );
	}

	/**
	 * Same as get() for a required parameter.
	 *
	 * @param string      $name     Parameter name.
	 * @param string|null $fallback Fallback value.
	 * @throws CliException When the value is missing or invalid.
	 */
	public function require( string $name, ?string $fallback = null ): string {
		$value = $this->get( $name, $fallback );
		if ( null === $value ) {
			$parameter = $this->parameter( $name );
			throw CliException::invalid(
				/* translators: %1$s: parameter name, %2$s: parameter description. */
				sprintf( __( 'Missing required parameter --%1$s (%2$s).', 'skouerr-cli' ), $name, $parameter->description ),
				array( 'parameter' => $parameter->to_schema() )
			);
		}
		return $value;
	}

	/**
	 * Asks for confirmation. Non-interactive callers get the default unless they pass --yes.
	 *
	 * @param string $question Question.
	 * @param bool   $default  Answer used without a human.
	 */
	public function confirm( string $question, bool $default = true ): bool {
		if ( $this->flag( 'yes' ) ) {
			return true;
		}
		if ( null === $this->prompter ) {
			return $default;
		}
		return $this->prompter->confirm( $question, $default );
	}

	/**
	 * Lets a human choose among values computed at runtime.
	 *
	 * @param string                    $name     Parameter name, used when the caller passed a value.
	 * @param array<int|string, string> $options  Choices (value => label).
	 * @throws CliException When no value can be resolved.
	 */
	public function choose( string $name, array $options ): string {
		$parameter = $this->parameter( $name );
		$value     = $this->raw( $parameter );

		if ( null === $value && null !== $this->prompter && ! empty( $options ) ) {
			$value = $this->prompter->choose( $parameter->prompt ?? $parameter->description, $options );
		}

		if ( null === $value ) {
			throw CliException::invalid(
				/* translators: %1$s: parameter name, %2$s: parameter description. */
				sprintf( __( 'Missing required parameter --%1$s (%2$s).', 'skouerr-cli' ), $name, $parameter->description ),
				array(
					'parameter' => $parameter->to_schema(),
					'choices'   => array_map( 'strval', array_keys( $options ) ),
				)
			);
		}

		if ( ! empty( $options ) && ! array_key_exists( $value, $options ) ) {
			throw CliException::invalid(
				/* translators: %1$s: value, %2$s: parameter name. */
				sprintf( __( 'Invalid value "%1$s" for --%2$s.', 'skouerr-cli' ), $value, $name ),
				array(
					'parameter' => $name,
					'choices'   => array_map( 'strval', array_keys( $options ) ),
				)
			);
		}

		return $value;
	}

	/**
	 * Raw string value passed by the caller.
	 *
	 * @param Parameter $parameter Parameter.
	 * @throws CliException When an option is passed without value.
	 */
	private function raw( Parameter $parameter ): ?string {
		if ( ! array_key_exists( $parameter->name, $this->values ) ) {
			return null;
		}
		$value = $this->values[ $parameter->name ];
		if ( true === $value ) {
			throw CliException::invalid(
				/* translators: %1$s: parameter name. */
				sprintf( __( 'Parameter --%1$s expects a value: --%1$s=<value>.', 'skouerr-cli' ), $parameter->name ),
				array( 'parameter' => $parameter->to_schema() )
			);
		}
		$value = trim( (string) $value );
		return '' === $value ? null : $value;
	}

	/**
	 * Validates a value against the declaration.
	 *
	 * @param Parameter $parameter Parameter.
	 * @param string    $value     Value.
	 * @throws CliException When the value is invalid.
	 */
	private function validate( Parameter $parameter, string $value ): string {
		if ( ! empty( $parameter->options ) && ! array_key_exists( $value, $parameter->options ) ) {
			throw CliException::invalid(
				/* translators: %1$s: value, %2$s: parameter name, %3$s: allowed values. */
				sprintf( __( 'Invalid value "%1$s" for --%2$s. Expected one of: %3$s.', 'skouerr-cli' ), $value, $parameter->name, implode( ', ', array_keys( $parameter->options ) ) ),
				array( 'parameter' => $parameter->to_schema() )
			);
		}

		if ( null !== $parameter->pattern && 1 !== preg_match( $parameter->pattern, $value ) ) {
			throw CliException::invalid(
				/* translators: %1$s: value, %2$s: parameter name, %3$s: expected regular expression. */
				sprintf( __( 'Invalid value "%1$s" for --%2$s. Expected format: %3$s.', 'skouerr-cli' ), $value, $parameter->name, $parameter->pattern ),
				array( 'parameter' => $parameter->to_schema() )
			);
		}

		return $value;
	}

	/**
	 * Declared parameter.
	 *
	 * @param string $name Parameter name.
	 * @throws \LogicException When the parameter is not declared.
	 */
	private function parameter( string $name ): Parameter {
		if ( ! isset( $this->parameters[ $name ] ) ) {
			throw new \LogicException( sprintf( 'Parameter "%s" is not declared.', $name ) );
		}
		return $this->parameters[ $name ];
	}
}
