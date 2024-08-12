<?php

/**
 * Copyright (C) 2024 R2
 * This file is part of the Skouerr CLI project.
 *
 * @package Skouerr_CLI
 */

if (! defined('ABSPATH')) {
    die('Kangaroos cannot jump here');
}

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 * The WP_CLI_Input class is used to manage the input in the WP CLI.
 */
class SK_CLI_Input extends WP_CLI
{

    /**
     * The function asks a question and returns the answer.
     *
     * @param string             $name The name of the question.
     * @param ArgvInput|null     $input Optional ArgvInput instance.
     * @param ConsoleOutput|null $output Optional ConsoleOutput instance.
     * @return string The answer to the question.
     */
    public static function ask(string $name, ?ArgvInput $input = null, ?ConsoleOutput $output = null): string
    {
        $input = $input ?? new ArgvInput();
        $output = $output ?? new ConsoleOutput();
        $helper = new Symfony\Component\Console\Helper\QuestionHelper();

        $question = new Question($name . ' : ');

        $value = $helper->ask($input, $output, $question);
        return $value;
    }

    /**
     * The function asks a question and returns the answer.
     *
     * @param string             $name The name of the question.
     * @param array              $choices The choices for the question.
     * @param ArgvInput|null     $input Optional ArgvInput instance.
     * @param ConsoleOutput|null $output Optional ConsoleOutput instance.
     * @return string The answer to the question.
     */
    public static function select(string $name, array $choices, ?ArgvInput $input = null, ?ConsoleOutput $output = null): string
    {
        $input = $input ?? new ArgvInput();
        $output = $output ?? new ConsoleOutput();
        $helper = new Symfony\Component\Console\Helper\QuestionHelper();

        $question = new ChoiceQuestion($name, $choices);
        $question->setMultiselect(false);

        $value = $helper->ask($input, $output, $question);
        return $value;
    }

    /**
     * The function asks a question and returns the answer.
     *
     * @param string             $name The name of the question.
     * @param array              $choices The choices for the question.
     * @param ArgvInput|null     $input Optional ArgvInput instance.
     * @param ConsoleOutput|null $output Optional ConsoleOutput instance.
     * @return array The answer to the question.
     */
    public static function select_multiple(string $name, array $choices, ?ArgvInput $input = null, ?ConsoleOutput $output = null): array
    {
        $input = $input ?? new ArgvInput();
        $output = $output ?? new ConsoleOutput();
        $helper = new Symfony\Component\Console\Helper\QuestionHelper();

        $question = new ChoiceQuestion($name, $choices);
        $question->setMultiselect(true);
        $values = $helper->ask($input, $output, $question);
        return $values;
    }
}
