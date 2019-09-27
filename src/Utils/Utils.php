<?php
declare(strict_types=1);

namespace Swolley\YardBird\Utils;

use Swolley\YardBird\Exceptions\BadMethodCallException;
use Swolley\YardBird\Exceptions\UnexpectedValueException;

class Utils
{
	/**
	 * casts params in object format to array
	 * @param	array|object	$params	params to cast
	 * @return	array					converted object
	 */
	public static function castToArray($params): array
	{
		$is_object = is_object($params);
		if (!is_array($params) && !$is_object) throw new UnexpectedValueException('$params can be only array or object');

		return $is_object ? (array)$params : $params;
	}

	/**
	 * casts params in object format to array
	 * @param	array|object	$params	params to cast
	 * @return	object					converted array
	 */
	public static function castToObject($params): object
	{
		$is_array = is_array($params);
		if (!$is_array && !is_object($params)) throw new UnexpectedValueException('$params can be only array or object');

		return $is_array ? (object)$params : $params;
	}

	/**
	 * @param 	string	$query query string
	 * @return	string	trimmed query
	 */
	public static function trimQueryString(string $query): string
	{
		return rtrim(preg_replace('/\s\s+/', ' ', $query), ';');
	}

	/**
	 * @param	mixed	$data	data to hash
	 * @return	string	hashed data
	 */
	public static function hash($data): string
	{
		return md5(serialize($data));
	}
}
