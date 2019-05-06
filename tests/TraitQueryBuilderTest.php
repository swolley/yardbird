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

	public function test_createQuery_should_return_array_if_parameters_are_correct(): void
  	{
		$response = [];
		$query = $this->createQuery('SELECT id FROM table');
		$this->assertEquals('array', gettype($query));
		//$this->assertEquals('select', $query['type']);
		//$this->assertEquals('table', $query['table']);
		//$this->assertEquals(['id'], $query['params']);
		//$this->assertEquals(['id' => ['$eq' => 1]], $query['options']);
	}

	public function test_parseInsert_should_throw_exception_if_any_syntax_error(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$this->createQuery('INSERT INTO table');
	}

	public function test_parseInsert_should_throw_exception_if_columns_count_differs_from_values(): void
	{
		$this->expectException(\Exception::class);
		$this->parseInsert('INSERT INTO table (`column1`, `missingColumn`) VALUES(:column)');
	}

	public function test_parseInsert_shold_return_array_if_parameters_are_correct(): void
	{
		$query = $this->parseInsert('INSERT INTO table (`column1`) VALUES(:column)');
		$response = [
			'type' => 'insert',
			'table' => 'table',
			'params' => ['column1' => ':column'],
			'ignore' => false
		];
		$this->assertEquals($response, $query);
	}
	
	public function test_parseDelete_should_throw_exception_if_any_sintax_error(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$this->parseDelete('DELETE FROM table WHERE');
	}

	public function test_parseDelete_shold_return_array_if_parameters_are_correct(): void
	{
		$query = $this->parseDelete('DELETE FROM `table` WHERE id=1');
		$response = [
			'type' => 'delete',
			'table' => 'table',
			'params' => ['id' => ['$eq' => 1]]
		];
		$this->assertEquals($response, $query);
	}

	public function test_parseUpdate_should_throw_exception_if_any_sintax_error(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$this->parseUpdate("UPDATE WHERE `column` != 'value'");
	}

	public function test_parseUpdate_shold_return_array_if_parameters_are_correct(): void
	{
		$query = $this->parseUpdate("UPDATE `table` SET id='value'");
		$response = [
			'type' => 'update',
			'table' => 'table',
			'params' => ['id' => 'value'],
			'where' => []
		];
		$this->assertEquals($response, $query);
	}

	public function test_parseSelect_should_throw_exception_if_any_sintax_error(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$this->parseSelect("SELECT INTO `table` WHERE `column` > 'value'");
	}

	public function test_parseSelect_shold_return_array_if_parameters_are_correct(): void
	{
		$query = $this->parseSelect("SELECT * FROM `table`");
		$response = [
			'type' => 'select',
			'table' => 'table',
			'params' => [],
			'options' => []
		];
		$this->assertEquals($response, $query);

		$query = $this->parseSelect("SELECT DISTINCT * FROM `table`");
		$response = [
			'type' => 'command',
			'table' => 'table',
			'params' => [],
			'options' => ['distinct' => 'table']
		];
		$this->assertEquals($response, $query);
	}

	public function test_parseProcedure_should_throw_exception_if_any_sintax_error(): void
	{
		$this->expectException(BadMethodCallException::class);
		$this->parseProcedure("CALL procedure_name (:value1, :value2)");
	}

	public function test_parseProcedure_shold_return_array_if_parameters_are_correct(): void
	{
		$query = $this->parseProcedure("CALL procedure_name ()", []);
		$response = [
			'type' => 'procedure',
			'name' => 'procedure_name',
			'params' => []
		];
		$this->assertEquals($response, $query);
	}

	public function test_parseOperators_should_throw_exception_if_no_valid_operator_found(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$query = ["a!!b"];
		$params = [];
		$nested_level = $i = 0;

		$this->parseOperators($query, $params, $i, $nested_level);
	}

	public function test_parseOperators_should_return_array_if_parameters_are_correct(): void
	{
		$query = ["a=1", "b>'value'", 'id=:id'];
		$params = ['id' => 1];
		$nested_level = $i = 0;
		$parsed = $this->parseOperators($query, $params, $i, $nested_level);
		$this->assertEquals('array', gettype($parsed));
	}

	public function test_groupLogicalOperators_should_return_grouped_params_by_operators(): void
	{
		$query = [
			[ 'a' => [ '$eq' => 1 ] ], 
			'AND', 
			[ 'b' => [ '$gt' => 'value' ] ]
		];
		
		$parsed = $this->groupLogicalOperators($query);
		$response = [
			'$and' => [
				'a' => ['$eq' => 1],
				'b' => ['$gt' => 'value']
			]
		];
		$this->assertEquals($response, $parsed);
	}

	public function test_castValue_should_return_converted_value(): void
	{
		$casted = $this->castValue("'string'");
		$response = "string";
		$this->assertEquals($response, $casted);

		$casted = $this->castValue(0);
		$response = 0;
		$this->assertEquals($response, $casted);
	}
}