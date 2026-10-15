<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Scaffold;

use Skouerr\Cli\Console\CliException;

/**
 * Validates the `--attributes` and `--fields` JSON of `make:block` and builds
 * the ACF field group of a block.
 *
 * Attributes: {"title": {"type": "string", "default": ""}, "count": {"type": "number"}}
 * Fields:     [{"name": "title", "type": "text", "label": "Title"}, {"name": "items", "type": "repeater", "sub_fields": [...]}]
 */
final class BlockSchema {

	/** Attribute types accepted by block.json. */
	private const array ATTRIBUTE_TYPES = array( 'string', 'number', 'integer', 'boolean', 'object', 'array', 'null' );

	/** Field types shipped with ACF Pro. */
	public const array ACF_TYPES = array(
		'text',
		'textarea',
		'number',
		'range',
		'email',
		'url',
		'password',
		'image',
		'file',
		'wysiwyg',
		'oembed',
		'gallery',
		'select',
		'checkbox',
		'radio',
		'button_group',
		'true_false',
		'link',
		'post_object',
		'page_link',
		'relationship',
		'taxonomy',
		'user',
		'google_map',
		'date_picker',
		'date_time_picker',
		'time_picker',
		'color_picker',
		'icon_picker',
		'message',
		'accordion',
		'tab',
		'group',
		'repeater',
		'flexible_content',
		'clone',
	);

	/**
	 * Parses and validates block attributes.
	 *
	 * @param string $json JSON object.
	 * @return array<string, mixed>
	 * @throws CliException When the JSON is invalid.
	 */
	public static function attributes( string $json ): array {
		$attributes = self::decode( $json, 'attributes' );
		if ( array_is_list( $attributes ) && array() !== $attributes ) {
			throw CliException::invalid( __( '--attributes must be a JSON object: {"name": {"type": "string"}}.', 'skouerr-cli' ), array( 'parameter' => 'attributes' ) );
		}

		foreach ( $attributes as $name => $definition ) {
			if ( 1 !== preg_match( '/^[a-zA-Z][a-zA-Z0-9_]*$/D', (string) $name ) ) {
				/* translators: %s: attribute name. */
				throw CliException::invalid( sprintf( __( 'Invalid attribute name "%s".', 'skouerr-cli' ), $name ), array( 'parameter' => 'attributes' ) );
			}
			$types = is_array( $definition ) ? (array) ( $definition['type'] ?? array() ) : array();
			if ( ! is_array( $definition ) || array() === $types || array_diff( $types, self::ATTRIBUTE_TYPES ) ) {
				throw CliException::invalid(
					/* translators: %1$s: attribute name, %2$s: list of types. */
					sprintf( __( 'Attribute "%1$s" needs a "type" among: %2$s.', 'skouerr-cli' ), $name, implode( ', ', self::ATTRIBUTE_TYPES ) ),
					array( 'parameter' => 'attributes' )
				);
			}
		}
		return $attributes;
	}

	/**
	 * Parses and validates ACF fields.
	 *
	 * @param string $json JSON array.
	 * @return list<array<string, mixed>>
	 * @throws CliException When the JSON is invalid.
	 */
	public static function fields( string $json ): array {
		$fields = self::decode( $json, 'fields' );
		if ( ! array_is_list( $fields ) ) {
			throw CliException::invalid( __( '--fields must be a JSON array: [{"name": "title", "type": "text"}].', 'skouerr-cli' ), array( 'parameter' => 'fields' ) );
		}
		self::validate_fields( $fields );
		/** @var list<array<string, mixed>> $fields */
		return $fields;
	}

	/**
	 * Builds an ACF local JSON field group attached to a block.
	 *
	 * Keys are derived from the block and field names, so regenerating the
	 * group keeps the same keys and the saved values.
	 *
	 * @param string                     $slug   Block name (namespace/name).
	 * @param string                     $title  Block title.
	 * @param list<array<string, mixed>> $fields Fields.
	 * @return array<string, mixed>
	 */
	public static function field_group( string $slug, string $title, array $fields ): array {
		return array(
			'key'                   => self::key( 'group', 'block:' . $slug ),
			'title'                 => 'Block: ' . $title,
			'fields'                => self::build_fields( $fields, $slug ),
			'location'              => array(
				array(
					array(
						'param'    => 'block',
						'operator' => '==',
						'value'    => $slug,
					),
				),
			),
			'menu_order'            => 0,
			'position'              => 'normal',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'active'                => true,
			'description'           => '',
			'show_in_rest'          => 0,
			'modified'              => time(),
		);
	}

	/**
	 * Field group key of a block.
	 *
	 * @param string $slug Block name.
	 */
	public static function group_key( string $slug ): string {
		return self::key( 'group', 'block:' . $slug );
	}

	/**
	 * Builds ACF fields with stable keys.
	 *
	 * @param list<array<string, mixed>> $fields Fields.
	 * @param string                     $path   Key prefix.
	 * @return list<array<string, mixed>>
	 */
	private static function build_fields( array $fields, string $path ): array {
		$built = array();
		foreach ( $fields as $field ) {
			$name  = (string) $field['name'];
			$field = array_merge(
				array(
					'key'   => self::key( 'field', $path . '/' . $name ),
					'label' => ucfirst( str_replace( '_', ' ', $name ) ),
				),
				$field
			);
			if ( isset( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
				$field['sub_fields'] = self::build_fields( array_values( $field['sub_fields'] ), $path . '/' . $name );
			}
			$built[] = $field;
		}
		return $built;
	}

	/**
	 * Validates fields recursively.
	 *
	 * @param array<mixed> $fields Fields.
	 * @throws CliException When a field is invalid.
	 */
	private static function validate_fields( array $fields ): void {
		$names = array();
		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) || ! is_string( $field['name'] ?? null ) || 1 !== preg_match( '/^[a-z][a-z0-9_]*$/D', $field['name'] ) ) {
				throw CliException::invalid( __( 'Each field needs a "name" in snake_case (a-z, 0-9, _).', 'skouerr-cli' ), array( 'parameter' => 'fields' ) );
			}
			if ( ! in_array( $field['type'] ?? null, self::ACF_TYPES, true ) ) {
				throw CliException::invalid(
					/* translators: %1$s: field name, %2$s: list of types. */
					sprintf( __( 'Field "%1$s" needs a "type" among: %2$s.', 'skouerr-cli' ), $field['name'], implode( ', ', self::ACF_TYPES ) ),
					array( 'parameter' => 'fields' )
				);
			}
			if ( isset( $names[ $field['name'] ] ) ) {
				/* translators: %s: field name. */
				throw CliException::invalid( sprintf( __( 'Duplicate field "%s".', 'skouerr-cli' ), $field['name'] ), array( 'parameter' => 'fields' ) );
			}
			$names[ $field['name'] ] = true;
			if ( isset( $field['sub_fields'] ) ) {
				if ( ! is_array( $field['sub_fields'] ) ) {
					/* translators: %s: field name. */
					throw CliException::invalid( sprintf( __( 'Field "%s": "sub_fields" must be an array.', 'skouerr-cli' ), $field['name'] ), array( 'parameter' => 'fields' ) );
				}
				self::validate_fields( $field['sub_fields'] );
			}
		}//end foreach
	}

	/**
	 * Decodes a JSON parameter.
	 *
	 * @param string $json Json.
	 * @param string $name Parameter name.
	 * @return array<mixed>
	 * @throws CliException When the JSON is invalid.
	 */
	private static function decode( string $json, string $name ): array {
		try {
			$data = json_decode( $json, true, 32, JSON_THROW_ON_ERROR );
		} catch ( \JsonException $e ) {
			/* translators: %1$s: parameter name, %2$s: JSON error. */
			throw CliException::invalid( sprintf( __( 'Invalid JSON in --%1$s: %2$s.', 'skouerr-cli' ), $name, $e->getMessage() ), array( 'parameter' => $name ) );
		}
		if ( ! is_array( $data ) ) {
			/* translators: %s: parameter name. */
			throw CliException::invalid( sprintf( __( 'Invalid JSON in --%s.', 'skouerr-cli' ), $name ), array( 'parameter' => $name ) );
		}
		return $data;
	}

	/**
	 * Stable ACF key.
	 *
	 * @param string $prefix "group" or "field".
	 * @param string $seed   Seed.
	 */
	private static function key( string $prefix, string $seed ): string {
		return $prefix . '_' . substr( md5( $seed ), 0, 13 );
	}
}
