<?php
/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Console;

/**
 * Asks questions to a human.
 */
interface Prompter {

	/**
	 * Asks a free-text question.
	 *
	 * @param string      $question Question.
	 * @param string|null $default  Default answer.
	 */
	public function ask( string $question, ?string $default = null ): string;

	/**
	 * Asks to choose one value.
	 *
	 * @param string                    $question Question.
	 * @param array<int|string, string> $options  Choices (value => label).
	 * @param string|null               $default  Default value.
	 * @return string The chosen value (array key).
	 */
	public function choose( string $question, array $options, ?string $default = null ): string;

	/**
	 * Asks a yes/no question.
	 *
	 * @param string $question Question.
	 * @param bool   $default  Default answer.
	 */
	public function confirm( string $question, bool $default = true ): bool;
}
