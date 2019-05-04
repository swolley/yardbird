<?php
namespace Swolley\Database;

class QueryBuilder
{
	public function __invoke(string $query, array $params = [])
	{
		if (preg_match('/^select/i', $query) === 1) {
			return [
				'type' => 'select',
				'query' => self::parseSelect($query, $params)
			];
		} elseif (preg_match('/^insert/i', $query) === 1) {
			return [
				'type' => 'insert',
				'query' => self::parseInsert($query, $params)
			];
		} elseif (preg_match('/^delete from/i', $query) === 1) {
			return [
				'type' => 'delete',
				'query' => self::parseDelete($query, $params)
			];
		} elseif (preg_match('/^update/i', $query) === 1) {
			return [
				'type' => 'update',
				'query' => self::parseUpdate($query, $params)
			];
		} else {
			throw new \UnexpectedValueException('queryBuilder is unable to convert query');
		}
	}

	private function parseInsert(string $query, array $params = [])
	{
		//recognize ignore keyword
		$ignore = false;
		if (preg_match('/^(insert\s)(ignore\s)/i', $query) === 1) {
			$ignore = true;
		}
		$query = rtrim(preg_replace('/^(insert\s)(ignore\s)?(into\s)/i', '', $query), ';');

		//splits main macro blocks (table, columns, values)
		$matches = [];
		preg_match_all('/^(`?\w+(?=\s*)`?\s?)(\([^(]*\)\s?)(values\s?)(\([^(]*\))$/i', $query, $matches);
		$query = array_slice($matches, 1);
		unset($matches);

		//set table name
		$table = preg_replace('/`|\s/', '', array_shift($query)[0]);
		if (preg_match('/^values/i', $query[0][0]) === 1) {
			throw new \UnexpectedValueException('parseInsert needs to know columns\' names');
		}

		//list of columns'names
		$keys_list = preg_split('/,\s?/', preg_replace('/\(|\)/', '', array_shift($query)[0]));
		if (count($keys_list) === 0) {
			throw new \UnexpectedValueException('parseInsert needs to know columns\' names');
		}
		$keys_list = array_map(function ($key) {
			return trim($key, '`');
		}, $keys_list);

		//list of columns'values
		if (preg_match('/^values/i', array_shift($query)[0]) === 0) {
			throw new \UnexpectedValueException('columns\' list must be followed by VALUES keyword');
		}

		$values_list = preg_split('/,\s?/', preg_replace('/\(|\)/', '', array_shift($query)[0]));
		if (count($values_list) === 0) {
			throw new \UnexpectedValueException('parseInsert needs to know columns\' values');
		}
		$values_list = array_map(function ($value) {
			return $this->castValue($value);
		}, $values_list);

		if (count($keys_list) !== count($values_list)) {
			throw new \Exception('Columns count must match values count');
		}

		//substitute params in array of values
		foreach ($params as $key => $value) {
			if ($index = array_search(':' . $key, $values_list)) {
				$values_list[$index] = $value;
			}
		}

		//compose array column/value
		$params = array_combine($keys_list, $values_list);

		//query elements ready to be passed to driver function
		return [
			'table' => $table,
			'params' => $params,
			'ignore' => $ignore
		];
	}

	private function parseDelete(string $query, array $params = [])
	{
		//splits main macro blocks (table, columns, values)
		$query = rtrim(preg_replace('/^(delete from\s)/i', '', $query), ';');
		$matches = [];
		preg_match_all('/^(`?\w+(?=\s*)`?\s?)(where\s?)(.*)$/i', $query, $matches);
		$query = array_slice($matches, 1);
		unset($matches);

		//set table name
		$table = preg_replace('/`|\s/', '', array_shift($query)[0]);

		//checks for where clauses
		if (preg_match('/^where/i', $query[0][0]) === 1) {
			array_shift($query);

			//splits on parentheses
			$query = preg_split('/\s/', $query[0][0]);
			for ($i = 0; $i < count($query); $i++) {
				if (strpos($query[$i], '(') !== false || strpos($query[$i], ')') !== false) {
					$first_part = array_slice($query, 0, $i);
					$second_part = array_slice($query, $i + 1);
					if (substr($query[$i], 0, 1) === '(') {
						$first_part[] = '(';
						$first_part[] = substr($query[$i], 1);
					} elseif (substr($query[$i], -1, 1) === ')') {
						$first_part[] = substr($query[$i], 0, -1);
						$first_part[] = ')';
					}
					$query = array_merge($first_part, $second_part);
					$i++;
				}
			}

			//parse and nest parameters
			$i = 0;
			$nested_level = 0;
			$where_params = $this->parseOperators($query, $params, $i, $nested_level);

			//groups params by logical operators
			$final_nested = $this->groupLogicalOperators($where_params);
		} else {
			$final_nested = [];
		}

		//query elements ready to be passed to driver function
		return [
			'table' => $table,
			'params' => $$final_nested
		];
	}

	private function parseUpdate(string $query, array $params = [])
	{
		//splits main macro blocks (table, columns, values)
		$query = rtrim(preg_replace('/^(update\s)/i', '', $query), ';');
		$matches = [];
		//FIXME non tiene conto che potrebbe mancare il where
		preg_match_all('/^(`?\w+(?=\s*)`?\s?)(set\s)(.*\s?)(where\s)(.*)$/i', $query, $matches);
		$query = array_slice($matches, 1);
		unset($matches);

		//set table name
		$table = preg_replace('/`|\s/', '', array_shift($query)[0]);
		//removes SET keyword
		array_shift($query);

		//list of columns'names
		$keys_list = preg_split('/,\s?/', array_shift($query)[0]);
		if (count($keys_list) === 0) {
			throw new \UnexpectedValueException('parseInsert needs to know columns\' names');
		}
		
		$parsed_params = [];
		foreach ($keys_list as $value) {
			$exploded = explode('=', preg_replace('/`|\s/', '', $value));
			$key = ltrim($exploded[1], ':');
			$parsed_params[$exploded[0]] = array_key_exists($key, $params) ? $params[$key] : $this->castValue($key);
		}
		unset($keys_list);

		//checks for where clauses
		if (preg_match('/^where/i', $query[0][0]) === 1) {
			array_shift($query);

			//splits on parentheses
			$query = preg_split('/\s/', $query[0][0]);
			for ($i = 0; $i < count($query); $i++) {
				if (strpos($query[$i], '(') !== false || strpos($query[$i], ')') !== false) {
					$first_part = array_slice($query, 0, $i);
					$second_part = array_slice($query, $i + 1);
					if (substr($query[$i], 0, 1) === '(') {
						$first_part[] = '(';
						$first_part[] = substr($query[$i], 1);
					} elseif (substr($query[$i], -1, 1) === ')') {
						$first_part[] = substr($query[$i], 0, -1);
						$first_part[] = ')';
					}
					$query = array_merge($first_part, $second_part);
					$i++;
				}
			}

			//parse and nest parameters
			$i = 0;
			$nested_level = 0;
			$where_params = $this->parseOperators($query, $params, $i, $nested_level);

			//groups params by logical operators
			$final_nested = $this->groupLogicalOperators($where_params);
		} else {
			$final_nested = [];
		}

		//query elements ready to be passed to driver function
		return [
			'table' => $table,
			'params' => $parsed_params,
			'where' => $final_nested
		];
	}

	private function parseSelect(string $query, array $params = [])
	{
		/*
		SELECT a,b FROM users WHERE age=33 ORDER BY name	$db->users->find(array("age" => 33), array("a" => 1, "b" => 1))->sort(array("name" => 1));
		SELECT * FROM users WHERE name LIKE "%Joe%"	$db->users->find(array("name" => new MongoRegex("/Joe/")));
		SELECT * FROM users WHERE name LIKE "Joe%"	$db->users->find(array("name" => new MongoRegex("/^Joe/")));
		SELECT * FROM users ORDER BY name DESC	$db->users->find()->sort(array("name" => -1));
		SELECT * FROM users LIMIT 20, 10	$db->users->find()->limit(10)->skip(20);
		SELECT * FROM users LIMIT 1	$db->users->find()->limit(1);
		SELECT COUNT(*y) FROM users	$db->users->count();
		SELECT COUNT(*y) FROM users where AGE > 30	$db->users->find(array("age" => array('$gt' => 30)))->count();
		SELECT COUNT(AGE) from users	$db->users->find(array("age" => array('$exists' => true)))->count();
		*/ 

		//splits main macro blocks (table, columns, values)
		$query = rtrim(preg_replace('/^(select\s)/i', '', $query), ';');
		$matches = [];
		preg_match_all('/^(distinct\s)?(.*)(from\s)(.*)(where\s?)(.*)$/i', $query, $matches);
		$query = array_slice($matches, 1);
		unset($matches);

		//check if distinct
		$isDistinct = false;
		if (preg_match('/^distinct/i', $query[0][0]) === 1) {
			array_shift($query);
			$isDistinct = true;
		} elseif($query[0][0] === '') {
			array_shift($query);
		}

		//parse columns to select
		$columns_list = preg_split('/,\s?/', array_shift($query)[0]);
		$columns_list = array_map(function ($key) {
			return trim(trim($key, '`'));
		}, $columns_list);

		if(count($columns_list) === 1 && $columns_list[0] === '*') {
			$columns_list = [];
		}

		//bypasse FROM keyword
		if(preg_match('/^from\s/i', $query[0][0]) === 0) {
			throw new \UnexpectedValueException("select query require FROM keyword after columns list");
		} else {
			array_shift($query);
		}

		//set table name
		$table = preg_replace('/`|\s/', '', array_shift($query)[0]);

		if (preg_match('/^where/i', $query[0][0]) === 1) {
			array_shift($query);

			//splits on parentheses
			$query = preg_split('/\s/', $query[0][0]);
			for ($i = 0; $i < count($query); $i++) {
				if (strpos($query[$i], '(') !== false || strpos($query[$i], ')') !== false) {
					$first_part = array_slice($query, 0, $i);
					$second_part = array_slice($query, $i + 1);
					if (substr($query[$i], 0, 1) === '(') {
						$first_part[] = '(';
						$first_part[] = substr($query[$i], 1);
					} elseif (substr($query[$i], -1, 1) === ')') {
						$first_part[] = substr($query[$i], 0, -1);
						$first_part[] = ')';
					}
					$query = array_merge($first_part, $second_part);
					$i++;
				}
			}

			//parse and nest parameters
			$i = 0;
			$nested_level = 0;
			$where_params = $this->parseOperators($query, $params, $i, $nested_level);

			//groups params by logical operators
			$final_nested = $this->groupLogicalOperators($where_params);
		} else {
			$final_nested = [];
		}

		//query elements ready to be passed to driver function
		if($isDistinct) {
			$final_nested = array_merge(['distinct' => $table], $final_nested);
		}

		return [
			'table' => $table,
			'params' => $columns_list,
			'options' => $final_nested
		];
	}

	private function parseOperators(array &$query, array &$params, int &$i, int &$nested_level)
	{
		$where_params = [];
		while ($i < count($query)) {
			if (preg_match('/!?=|<=?|>=?/i', $query[$i]) === 1) {
				$splitted = preg_split('/(=|!=|<>|>=|<=|>(?!=)|<(?<!=)(?!>))/i', $query[$i], null, PREG_SPLIT_DELIM_CAPTURE);
				switch ($splitted[1]) {
					case '=':
						$operator = '$eq';
						break;
					case '!=':
					case '<>':
						$operator = '$ne';
						break;
					case '<':
						$operator = '$lt';
						break;
					case '<=':
						$operator = '$lte';
						break;
					case '>':
						$operator = '$gt';
						break;
					case '>=':
						$operator = '$gte';
						break;
					default:
						throw new \UnexpectedValueException('Unrecognised operator');
				}

				$splitted[2] = $this->castValue($splitted[2]);
				$trimmed = ltrim($splitted[2], ':');
				if (isset($params[$trimmed])) {
					$splitted[2] = $params[$trimmed];
				}

				$where_params[] = [$splitted[0] => [$operator => $splitted[2]]];
			} elseif (preg_match('/and|&&|or|\|\|/i', $query[$i]) === 1) {
				$where_params[] = $query[$i];
			} elseif ($query[$i] === '(') {
				$i++;
				$nested_level++;
				$where_params[] = $this->parseOperators($query, $params, $i, $nested_level);
			} elseif ($query[$i] === ')') {
				//$i++;
				$nested_level--;
				break;
			} else {
				throw new \UnexpectedValueException('Unexpected keyword ' . $query[$i]);
			}
			$i++;
		}

		return $where_params;
	}

	private function groupLogicalOperators(array $query)
	{
		$nested_group = [];
		$i = 0;
		while ($i < count($query)) {
			if (array_key_exists($i + 1, $query)) {
				if (is_string($query[$i + 1])) {
					$operator = strtolower($query[$i + 1]);
					if ($operator === 'and' || $operator === 'or') {
						$i = $i + 2;
						$sub_group = $this->groupLogicalOperators($query[$i]);
						$merged_array = array_merge(count($query[$i - 2]) === 1 ? $query[$i - 2] : $nested_group, $sub_group);
						$nested_group = [
							'$' . $operator => $merged_array
						];
					}
				}

				$pippo = 'ciao';
			} else {
				if (count($query) === 1) {
					$is_assoc = array_keys($query) !== range(0, count($query) - 1);
					$nested_group = $is_assoc ? $query : end($query);
				}
				break;
			}
		}

		return $nested_group;
	}

	private function castValue($value)
	{
		if (preg_match("/^'|\"\w+'|\"$/", $value)) {
			return preg_replace("/'|\"/", '', $value);
		} elseif (is_numeric($value)) {
			return $value + 0;
		} elseif (is_bool($value)) {
			return (bool)$value;
		} else {
			return $value;
		}
	}
}