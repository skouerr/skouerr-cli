<?php
/**
 * Input and Output tests.
 *
 * @package Skouerr_CLI
 */

namespace Skouerr\Cli\Tests\Unit;

use Skouerr\Cli\Console\BufferWriter;
use Skouerr\Cli\Console\CliException;
use Skouerr\Cli\Console\ExitCode;
use Skouerr\Cli\Console\Input;
use Skouerr\Cli\Console\Output;
use Skouerr\Cli\Console\Parameter;
use Skouerr\Cli\Console\Prompter;

final class ConsoleTest extends TestCase {

	/**
	 * @return list<Parameter>
	 */
	private function parameters(): array {
		return array(
			new Parameter( name: 'title', description: 'Title', required: true, prompt: 'Title?' ),
			new Parameter(
				name: 'type',
				description: 'Type',
				default: 'native',
				options: array(
					'native' => 'N',
					'acf'    => 'A',
				),
				prompt: 'Type?'
			),
			new Parameter( name: 'name', description: 'Name', pattern: '/^[a-z]+$/', prompt: 'Name?' ),
			new Parameter( name: 'command', description: 'Command', positional: true ),
			Parameter::flag( 'yes', 'Yes' ),
		);
	}

	private function prompter( array $answers ): Prompter {
		return new class( $answers ) implements Prompter {
			public function __construct( private array $answers ) {}
			public function ask( string $question, ?string $default = null ): string {
				return $this->answers[ $question ] ?? (string) $default;
			}
			public function choose( string $question, array $options, ?string $default = null ): string {
				return $this->answers[ $question ] ?? (string) $default;
			}
			public function confirm( string $question, bool $default = true ): bool {
				return (bool) ( $this->answers[ $question ] ?? $default );
			}
		};
	}

	public function test_values_from_arguments(): void {
		$input = new Input(
			$this->parameters(),
			array( 'make:block' ),
			array(
				'title' => 'Hero',
				'type'  => 'acf',
			)
		);

		self::assertSame( 'Hero', $input->require( 'title' ) );
		self::assertSame( 'acf', $input->require( 'type' ) );
		self::assertSame( 'make:block', $input->get( 'command' ) );
		self::assertNull( $input->get( 'name' ) );
		self::assertSame( 'fallback', $input->get( 'name', 'fallback' ) === 'fallback' ? 'fallback' : 'x' );
	}

	public function test_missing_required_parameter_is_reported_with_schema(): void {
		$input = new Input( $this->parameters(), array(), array() );
		try {
			$input->require( 'title' );
			self::fail( 'Exception expected' );
		} catch ( CliException $e ) {
			self::assertSame( ExitCode::InvalidInput, $e->exit_code );
			self::assertSame( 'title', $e->details['parameter']['name'] );
			self::assertTrue( $e->details['parameter']['required'] );
		}
	}

	public function test_defaults_and_validation(): void {
		$input = new Input( $this->parameters(), array(), array( 'name' => 'Bad Name' ) );
		self::assertSame( 'native', $input->require( 'type' ) );

		$this->expectException( CliException::class );
		$input->get( 'name' );
	}

	public function test_enum_validation(): void {
		$this->expectException( CliException::class );
		( new Input( $this->parameters(), array(), array( 'type' => 'react' ) ) )->get( 'type' );
	}

	public function test_option_without_value(): void {
		$this->expectException( CliException::class );
		( new Input( $this->parameters(), array(), array( 'title' => true ) ) )->get( 'title' );
	}

	public function test_prompts_only_in_interactive_mode(): void {
		$input = new Input(
			$this->parameters(),
			array(),
			array(),
			$this->prompter(
				array(
					'Title?' => 'Prompted',
					'Type?'  => 'acf',
				)
			)
		);
		self::assertTrue( $input->is_interactive() );
		self::assertSame( 'Prompted', $input->require( 'title' ) );
		self::assertSame( 'acf', $input->require( 'type' ) );
		self::assertSame( 'abc', $input->require( 'name', 'abc' ), 'The fallback is the default answer.' );
	}

	public function test_confirm(): void {
		self::assertFalse( ( new Input( $this->parameters(), array(), array() ) )->confirm( 'Q', false ) );
		self::assertTrue( ( new Input( $this->parameters(), array(), array( 'yes' => true ) ) )->confirm( 'Q', false ) );
	}

	public function test_choose_lists_choices_when_missing(): void {
		try {
			( new Input( $this->parameters(), array(), array() ) )->choose(
				'name',
				array(
					'a' => 'A',
					'b' => 'B',
				)
			);
			self::fail( 'Exception expected' );
		} catch ( CliException $e ) {
			self::assertSame( array( 'a', 'b' ), $e->details['choices'] );
		}
	}

	public function test_json_output_success(): void {
		$writer = new BufferWriter();
		$output = new Output( $writer, 'make:block', true, true );
		$output->info( 'hidden' );
		$output->warning( 'careful' );
		$output->file( 'create', '/tmp/x' );
		$output->set( 'block', array( 'name' => 'skouerr/x' ) );
		$output->next_step( 'npm run build' );
		$output->success( 'Done' );

		self::assertSame( array(), $writer->lines, 'Nothing but the JSON document is printed.' );
		$document = json_decode( $writer->stdout, true );
		self::assertTrue( $document['ok'] );
		self::assertTrue( $document['dry_run'] );
		self::assertSame( 'make:block', $document['command'] );
		self::assertSame( array( 'careful' ), $document['warnings'] );
		self::assertSame(
			array(
				array(
					'action' => 'create',
					'path'   => '/tmp/x',
				),
			),
			$document['files']
		);
		self::assertSame( 'skouerr/x', $document['data']['block']['name'] );
		self::assertSame( array( 'npm run build' ), $document['next_steps'] );
	}

	public function test_json_output_failure(): void {
		$writer = new BufferWriter();
		$code   = ( new Output( $writer, 'make:block', true ) )->failure( CliException::conflict( '/tmp/x' ) );

		self::assertSame( 3, $code );
		$document = json_decode( $writer->stdout, true );
		self::assertFalse( $document['ok'] );
		self::assertSame( 'conflict', $document['error']['code'] );
		self::assertSame( '/tmp/x', $document['error']['details']['path'] );
	}

	public function test_empty_data_is_an_object(): void {
		$writer = new BufferWriter();
		( new Output( $writer, 'x', true ) )->success( 'ok' );
		self::assertStringContainsString( '"data": {}', $writer->stdout );
	}

	public function test_synopsis_and_schema(): void {
		$parameter = new Parameter( name: 'type', description: 'Type.', default: 'native', options: array( 'native' => 'N' ), pattern: '/x/' );
		self::assertSame( 'assoc', $parameter->to_synopsis()['type'] );
		self::assertTrue( $parameter->to_synopsis()['optional'] );
		self::assertArrayNotHasKey( 'default', $parameter->to_synopsis(), 'Defaults are resolved by the command, not WP-CLI.' );
		self::assertSame( array( 'native' ), $parameter->to_schema()['enum'] );
		self::assertSame( 'boolean', Parameter::flag( 'force', 'Force.' )->to_schema()['type'] );
	}
}
