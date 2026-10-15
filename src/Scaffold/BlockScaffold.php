<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Scaffold;

use Skouerr\Cli\Support\Template;

/**
 * Builds the files of a block from a `block/<type>-<engine>/` template folder.
 *
 * Template files are named after their role (`controller`, `template`, `block`…)
 * and renamed after the block: `hero.controller.php`, `hero.block.json`…
 */
final class BlockScaffold {

	/** Block types. */
	public const array TYPES = array(
		'native'      => 'Native (server-side render)',
		'acf'         => 'ACF (needs ACF Pro)',
		'interactive' => 'Interactivity API',
	);

	/** Template engines. React means a JSX editor with a PHP front-end render. */
	public const array ENGINES = array(
		'php'   => 'PHP',
		'twig'  => 'Twig / Timber',
		'react' => 'React (JSX editor, needs a build step)',
	);

	/** Extension of the front-end template for each engine. */
	private const array TEMPLATE_EXTENSIONS = array(
		'php'   => 'php',
		'twig'  => 'twig',
		'react' => 'php',
	);

	/**
	 * File name patterns by role. `{name}` is the block name, `{ext}` the template extension.
	 */
	private const array FILE_NAMES = array(
		'block'      => '{name}.block.json',
		'controller' => '{name}.controller.php',
		'template'   => '{name}.template.{ext}',
		'register'   => '{name}.register.js',
		'style'      => '{name}.style.scss',
		'script'     => '{name}.script.js',
		'editor'     => '{name}.editor.js',
		'view'       => '{name}.view.js',
		'view.asset' => '{name}.view.asset.php',
	);

	/**
	 * Builds the files of a block.
	 *
	 * @param string                                                                                                                                  $source Template folder (`…/block/<type>-<engine>`).
	 * @param string                                                                                                                                  $engine Template engine.
	 * @param array{name: string, title: string, slug: string, icon: string, textdomain: string, category: string, attributes?: array<string, mixed>} $block Block values.
	 * @return array<string, string> Contents indexed by path relative to the block folder.
	 */
	public function files( string $source, string $engine, array $block ): array {
		$values = array(
			'SK_BLOCK_NAME'       => $block['name'],
			'SK_BLOCK_TITLE'      => $block['title'],
			'SK_BLOCK_SLUG'       => $block['slug'],
			'SK_BLOCK_ICON'       => $block['icon'],
			'SK_BLOCK_TEXTDOMAIN' => $block['textdomain'],
			'SK_BLOCK_CATEGORY'   => $block['category'],
		);

		$files    = array();
		$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $source, \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iterator as $file ) {
			/** @var \SplFileInfo $file */
			if ( ! $file->isFile() ) {
				continue;
			}
			$relative = ltrim( str_replace( '\\', '/', substr( $file->getPathname(), strlen( $source ) ) ), '/' );
			$target   = $this->target_name( $relative, $block['name'], self::TEMPLATE_EXTENSIONS[ $engine ] ?? $engine );

			$files[ $target ] = Template::render_file( $file->getPathname(), $values, Template::context_for( $target ) );
		}

		$metadata = $block['name'] . '.block.json';
		if ( ! empty( $block['attributes'] ) && isset( $files[ $metadata ] ) ) {
			$files[ $metadata ] = self::add_attributes( $files[ $metadata ], $block['attributes'] );
		}

		ksort( $files );
		return $files;
	}

	/**
	 * Adds attributes to a block.json document.
	 *
	 * @param string               $json       block.json contents.
	 * @param array<string, mixed> $attributes Attributes indexed by name.
	 * @throws \JsonException When the template is not valid JSON.
	 */
	public static function add_attributes( string $json, array $attributes ): string {
		$data = json_decode( $json, true, 512, JSON_THROW_ON_ERROR );
		if ( ! is_array( $data ) ) {
			return $json;
		}
		$existing           = is_array( $data['attributes'] ?? null ) ? $data['attributes'] : array();
		$data['attributes'] = (object) array_merge( $existing, $attributes );

		return self::encode( $data );
	}

	/**
	 * Encodes JSON with the 2-space indentation of the block templates.
	 *
	 * @param mixed $data Data.
	 */
	public static function encode( mixed $data ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Used without WordPress in tests.
		$json = (string) json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$json = (string) preg_replace_callback(
			'/^( {4})+/m',
			static fn( array $match ): string => str_repeat( '  ', intdiv( strlen( $match[0] ), 4 ) ),
			$json
		);
		return $json . "\n";
	}

	/**
	 * Final path of a template file.
	 *
	 * @param string $relative  Path relative to the template folder (e.g. "src/style").
	 * @param string $name      Block name.
	 * @param string $extension Template extension.
	 */
	private function target_name( string $relative, string $name, string $extension ): string {
		$role = basename( $relative );
		if ( ! isset( self::FILE_NAMES[ $role ] ) ) {
			return $relative;
		}
		$file      = strtr(
			self::FILE_NAMES[ $role ],
			array(
				'{name}' => $name,
				'{ext}'  => $extension,
			)
		);
		$directory = dirname( $relative );
		return '.' === $directory ? $file : $directory . '/' . $file;
	}
}
