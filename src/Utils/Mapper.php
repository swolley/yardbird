<?php
declare(strict_types=1);

namespace Swolley\YardBird\Utils;
use Swolley\YardBird\Interfaces\IConnectable;
use Swolley\YardBird\Utils\Utils;

class Mapper
{
	/**
	 * writes down new class code from bd columns data
	 * @param	IConnectable	$conn 			db connection instance
	 * @param	bool			$prettyNames	(optional) respect db naming convention or prettify tables and properties names. Default is false
	 * @param	string|null		$classPath		(optional) write generated class definitions to filesystem or evaluate at runtime
	 */
	public static function mapDB(IConnectable $conn, bool $prettyNames = false, string $classPath = null): void
	{
		$tables = $conn->showColumns($conn->showTables());
		foreach ($tables as $table => $fields) {
			$class_name = $prettyNames ? Utils::toCamelCase($table) : $table;
			$class_code = '';
			foreach ($fields as $field => $info) {
				$property_name = $prettyNames ? Utils::toPascalCase($field) : $field;
				$class_code .= "\t/*$field {$info['type']}*/" . PHP_EOL;
				$class_code .= "\tprivate \$$property_name; " . PHP_EOL;
				$default = $info['nullable'] ? '= ' . ($info['default'] === null ? 'null' : $info['default']) : ($info['default'] != null ? '= ' . $info['default'] : '');
				$type_to_type = static::typeToType($info['type'], $default);
				//all properties are created as private with getter and setter containig values validations from db column data)
				$class_code .= "\tpublic function get" . ucwords($property_name) . '() { return $this->' . $property_name . '; }' . PHP_EOL;
				$class_code .= "\tpublic function set" . ucwords($property_name) . '(' . ($info['nullable'] ? '?' : '') . $type_to_type[0] . ' $value ' . $default . ') { ' . $type_to_type[1] . ' $this->' . $property_name . ' = ' . $type_to_type[2] . '; ' . (!empty($type_to_type[3]) ? 'else $this->' . $property_name . ' = ' . $type_to_type[3] . ';' : '') . ' }' . PHP_EOL . PHP_EOL;
			}
			
			$new_class = "/*$table*/" . PHP_EOL . "final class $class_name extends Swolley\YardBird\Models\AbstractModel { " . PHP_EOL . PHP_EOL . $class_code . "}";
			
			//write code to file or evaluate at runtime
			//TODO check for security
			if($classPath) {
				file_put_contents('temp.php', '<?php' . PHP_EOL . PHP_EOL . $new_class);
			} else {
				eval($new_class);
			}
		}
	}

	/**
	 * maps db columns with php types and generates validations
	 * @param	string	$type		column type
	 * @param	mixed	$default	used to add null value accept in validation
	 * @return	array	portions of property assignment code 
	 */
	private static function typeToType(string $type, $default = null): array
	{
		//types mapping
		$types = [
			'char' => [ 'string', '', '$value', '' ],
			'varchar' => [ 'string', '', '$value', '' ],
			'varchar2' => [ 'string', '', '$value', '' ],
			'text' => [ 'string', '', '$value', '' ],
			'json' => [ '', '$value === null || is_array($value)', 'json_decode($value, true)', '$value' ],

			'decimal' => [ 'float', '', '$value', '' ],
			'float' => [ 'float', '', '$value', '' ],
			
			'int' => [ 'int', '', '$value', '' ],
			'tinyint' => [ 'int', '', '$value', '' ],
			'smallint' => [ 'int', '', '$value', '' ],
			'bigint' => [ 'int', '', '$value', '' ],
			'integer' => [ 'int', '', '$value', '' ],
			'year' => [ 'int', '', '$value', '' ],
			'month' => [ 'int', '', '$value', '' ],

			'date' => [ '', '$value instanceof DateTime', '$value', 'new DateTime($value)' ],
			'datetime' => [ '', '$value instanceof DateTime', '$value', 'new DateTime($value)' ],
			'timestamp' => [ '', '$value instanceof DateTime', '$value', 'new DateTime($value)' ],

			'enum' => ['', '', '$value', '']
		];

		$rules = preg_split('/\(|\)|\s/', $type);
		$rules = array_map('trim', $rules);
		$return = $types[$rules[0]];
		unset($rules[0]);

		$idxs = array_keys($rules);
		foreach($idxs as $idx) {
			if(empty($rules[$idx])) {
				//unset($rules[$idx]);
				continue;
			}

			//multiple validation concatenation
			if(!empty($return[1])) {
				$return[1] .= ' && ';
			}

			if(is_numeric($rules[$idx])) {
				if($return[0] === 'int' || $return[0] === 'string') {
					$return[1] .= 'mb_strlen(' . ($return[0] === 'int' ? '(string)' : '') . '$value)' . (mb_strpos($type, 'char') === 0 ? ' = ' : ' <= ') . $rules[$idx];
				} elseif($return[0] === 'float' && mb_ereg_match('/\d*,\d*/', $rules[$idx])) {
					$exploded = explode(',', $rules[$idx]);
					$return[1] .= 'mb_strlen((string)$value) <= ' . $exploded[0] . ' && mb_strlen((string)((int)$value - floor($value))) <= ' . $exploded[0];
				}
				//unset($rules[$idx]);
			} elseif($rules[$idx] === 'unsigned') {
				$return[1] .= $return[2]  . ' > 0';
				//unset($rules[$idx]);
			} elseif(mb_ereg_match('/\w*(,\s?\w*)?/', $rules[$idx])) {
				$return[1] .= 'in_array(' . $return[2] . ', [' . $rules[$idx] . '])';
			}
		}

		if(!empty($return[1])) {
			$return[1] = 'if(' . $return[1] . (strpos($default, 'null') !== false ? ' || ' . $return[2] . ' === null' : '') .')';
		}

		return $return;
	}
}