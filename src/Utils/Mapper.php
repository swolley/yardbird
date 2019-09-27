<?php
namespace Swolley\YardBird\Utils;
use Swolley\YardBird\Interfaces\IConnectable;
use Swolley\YardBird\Utils\Utils;

class Mapper
{
	public static function mapDB(IConnectable $connection, bool $prettyNames = false)
	{
		$tables = $connection->showColumns($connection->showTables());
		foreach ($tables as $table => $fields) {
			if($table === 'user') {
				$class_name = $prettyNames ? Utils::toCamelCase($table) : $table;
				$class_code = '';
				foreach ($fields as $field => $info) {
					$class_code .= 'private $_' . $field . '; ' . PHP_EOL;
					$default = $info['nullable'] ? '= ' . ($info['default'] === null ? 'null' : $info['default']) : ($info['default'] != null ? '= ' . $info['default'] : '');
					$type_to_type = static::typeToType($info['type']);
					$class_code .= 'public set' . ucwords($field) . '(' . ($info['nullable'] ? '?' : '') . $type_to_type[0] . ' $value ' . $default . ') { ' . $type_to_type[1] . ' $this->$_' . $field . ' = ' . $type_to_type[2] . '; }' . PHP_EOL . PHP_EOL;
				}
				
				eval("final class $class_name { " . $class_code . "}");
			}
		}
	}

	private static function typeToType(string $type): array
	{
		$types = [
			'char' => [ 'string', '', '$value' ],
			'varchar' => [ 'string', '', '$value' ],
			'varchar2' => [ 'string', '', '$value' ],
			'text' => [ 'string', '', '$value' ],
			'json' => [ 'string', '', 'json_decode($value, true)' ],

			'decimal' => [ 'float', '', '(float)$value' ],
			'float' => [ 'float', '', '(float)$value' ],
			
			'int' => [ 'integer', '', '(int)$value' ],
			'integer' => [ 'integer', '', '(int)$value' ],
			'year' => [ 'integer', '', '(int)$value' ],
			'month' => [ 'integer', '', '(int)$value' ]
		];

		$rules = preg_split('/\(|\)/', $type);
		$rules = array_map('trim', $rules);
		$return = $types[$rules[0]];
		unset($rules[0]);

		$idxs = array_keys($rules);
		foreach($idxs as $idx) {
			if(!empty($return[1])) {
				$return[1] .= ' && ';
			}

			if(is_numeric($rules[$idx])) {
				if($return[0] === 'integer' || $return[0] === 'string') {
					$return[1] = 'mb_strlen($value) > ' . $rules[$idx];
				} elseif($return[0] === 'float' && preg_match('/\d*,\d*/', $rules[$idx])) {
					$exploded = explode(',', $rules[$idx]);
					$return[1] = 'mb_strlen((int)$value) > ' . $exploded[0] . ' && mb_strlen((string)((int)$value - floor($value))) > ' . $exploded[0];
				}
				unset($rules[$idx]);
			} elseif($rules[$idx] === 'unsigned') {
				$return[1] = $return[2]  . ' > 0';
				unset($rules[$idx]);
			}
		}

		if(!empty($return[1])) {
			$return[1] = 'if(' . trim(preg_replace('/\s?&&\s/', '', $return[1])) . ')';
		}

		return $return;
	}
}