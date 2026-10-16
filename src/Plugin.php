<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli;

use Skouerr\Cli\Command\AbstractCommand;
use WP_CLI;

/**
 * Registers the `wp skouerr` commands.
 */
final class Plugin {

	/**
	 * Command instances, created once.
	 *
	 * @var list<AbstractCommand>|null
	 */
	private static ?array $commands = null;

	/**
	 * Every command of the CLI.
	 *
	 * @return list<AbstractCommand>
	 */
	public static function commands(): array {
		return self::$commands ??= array(
			new Command\ListCommand(),
			new Command\DescribeCommand(),
			new Command\InfoCommand(),
			new Command\AgentInstallCommand(),
			new Command\MakeThemeCommand(),
			new Command\MakeBlockCommand(),
			new Command\MakePostTypeCommand(),
			new Command\MakeTemplateCommand(),
			new Command\MakeVariationCommand(),
			new Command\MakeBindingCommand(),
			new Command\MakePatternCommand(),
			new Command\MakeStyleCommand(),
			new Command\MakeStyleCommand( true ),
			new Command\MakeTaxonomyCommand(),
			new Command\ListBlocksCommand(),
			new Command\RenameBlockCommand(),
			new Command\DeleteBlockCommand(),
			new Command\ImportCommand(),
			new Command\SaveTemplateCommand(),
			new Command\SavePatternCommand(),
			new Command\SaveStylesCommand(),
			new Command\SaveNavigationCommand(),
			new Command\UpdateCoreCommand(),
		);
	}

	/**
	 * Registers the commands in WP-CLI.
	 */
	public static function register(): void {
		foreach ( self::commands() as $command ) {
			WP_CLI::add_command( 'skouerr ' . $command->name(), $command, $command->registration() );
		}
	}
}
