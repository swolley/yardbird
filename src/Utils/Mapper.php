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
		self::mapTables($tables, $prettyNames, $classPath);
	}

	/**
	 * writes down new class code from bd columns data
	 * @param	string|array	$tables 		table or tables list
	 * @param	bool			$prettyNames	(optional) respect db naming convention or prettify tables and properties names. Default is false
	 * @param	string|null		$classPath		(optional) write generated class definitions to filesystem or evaluate at runtime
	 */
	public static function mapTables($tables, bool $prettyNames = false, string $classPath = null): void
	{
		$tables = is_string($tables) ? [$tables] : $tables;

		foreach ($tables as $table => $fields) {
			$class_name = $prettyNames ? Utils::toCamelCase($table) : $table;
			$class_code = '';
			foreach ($fields as $field => $info) {
				$property_name = $prettyNames ? Utils::toPascalCase($field) : $field;
				$class_code .= "\t/*$field {$info['type']}*/" . PHP_EOL;
				$class_code .= "\tprivate \$$property_name; " . PHP_EOL;
				$default = $info['nullable'] ? '= ' . ($info['default'] === null ? 'null' : $info['default']) : ($info['default'] != null ? '= ' . $info['default'] : '');
				$type_to_type = static::typeToType($info['type'], $property_name, $default);
				//all properties are created as private with getter and setter containig values validations from db column data)
				$class_code .= "\tpublic function get" . ucwords($property_name) . '() { return $this->' . $property_name . '; }' . PHP_EOL;
				$class_code .= "\tpublic function set" . ucwords($property_name) . '(' . ($info['nullable'] ? '?' : '') . "$type_to_type[0] $property_name $default ) { $type_to_type[1] " . '$this->' . "$property_name = $type_to_type[2] ; " . (!empty($type_to_type[3]) ? 'else $this->' . "$property_name = $type_to_type[3];" : '') . ' }' . PHP_EOL . PHP_EOL;
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
	private static function typeToType(string $type, string $propertyName, $default = null): array
	{
		//types mapping
		$types = [
			'char' => [ 'string', '', $propertyName, '' ],
			'varchar' => [ 'string', '', $propertyName, '' ],
			'varchar2' => [ 'string', '', $propertyName, '' ],
			'text' => [ 'string', '', $propertyName, '' ],
			
			'json' => [ '', "$propertyName === null || is_array($propertyName)", "json_decode($propertyName, true)", $propertyName ],

			'decimal' => [ 'float', '', $propertyName, '' ],
			'float' => [ 'float', '', $propertyName, '' ],
			
			'int' => [ 'int', '', $propertyName, '' ],
			'tinyint' => [ 'int', '', $propertyName, '' ],
			'smallint' => [ 'int', '', $propertyName, '' ],
			'bigint' => [ 'int', '', $propertyName, '' ],
			'integer' => [ 'int', '', $propertyName, '' ],
			'year' => [ 'int', '', $propertyName, '' ],
			'month' => [ 'int', '', $propertyName, '' ],

			'date' => [ '', "$propertyName instanceof DateTime", $propertyName, "new DateTime($propertyName)" ],
			'datetime' => [ '', "$propertyName instanceof DateTime", $propertyName, "new DateTime($propertyName)" ],
			'timestamp' => [ '', "$propertyName instanceof DateTime", $propertyName, "new DateTime($propertyName)" ],

			'enum' => ['', '', $propertyName, '']
		];

		$rul = array_map('trim', preg_split('/\(|\)|\s/', $type));
		$ret = $types[$rul[0]];
		unset($rul[0]);

		$idxs = array_keys($rul);
		foreach($idxs as $idx) {
			if(empty($rul[$idx])) continue;

			//multiple validation concatenation
			if(!empty($ret[1])) {
				$ret[1] .= ' && ';
			}

			if(is_numeric($rul[$idx])) {
				if($ret[0] === 'int' || $ret[0] === 'string') {
					$ret[1] .= 'mb_strlen(' . ($ret[0] === 'int' ? '(string)' : '') . "$ret[2])" . (mb_strpos($type, 'char') === 0 ? ' = ' : ' <= ') . $rul[$idx];
				} elseif($ret[0] === 'float' && mb_ereg_match('/\d*,\d*/', $rul[$idx])) {
					list($int, $dec) = explode(',', $rul[$idx]);
					$ret[1] .= "mb_strlen((string)$ret[2]) <= $int && mb_strlen((string)((int)$ret[2] - floor($ret[2]))) <= $dec";
				}
			} elseif($rul[$idx] === 'unsigned') {
				$ret[1] .= $ret[2]  . ' > 0';
			} elseif(mb_ereg_match('/\w*(,\s?\w*)?/', $rul[$idx])) {
				$ret[1] .= 'in_array(' . $ret[2] . ', [' . $rul[$idx] . '])';
			}
		}

		if(!empty($ret[1])) {
			$ret[1] = 'if(' . $ret[1] . (strpos($default, 'null') !== false ? ' || ' . $ret[2] . ' === null' : '') .')';
		}

		return $ret;
	}
}