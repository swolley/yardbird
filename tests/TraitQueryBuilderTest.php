<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Swolley\Database\Utils\TraitQueryBuilder;
use Swolley\Database\Exceptions\QueryException;
use Swolley\Database\Exceptions\BadMethodCallException;
use Swolley\Database\Exceptions\UnexpectedValueException;

final class TraitQueryBuilderTest extends TestCase
{
	use TraitQueryBuilder;

	public function test_createQuery_should_return_exception_if_not_recognized_a_valid_query(): void
  	{
		$this->expectException(UnexpectedValueException::class);
		$this->createQuery('invalid query');
	}

	public function test_createQuery_should_return_parsed_sql_if_parameters_are_correct(): void
  	{
		$response = [];
		$query = $this->createQuery('SELECT id FROM table WHERE id=1');
		$this->assertEquals('array', gettype($query));
		$this->assertEquals('select', $query['type']);
		$this->assertEquals('table', $query['table']);
		$this->assertEquals(['id'], $query['params']);
		$this->assertEquals(['id' => ['$eq' => 1]], $query['options']);
	}
}