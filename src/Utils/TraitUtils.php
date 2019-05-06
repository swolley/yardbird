<?php
namespace Swolley\Database\Utils;

use Swolley\Database\Exceptions\BadMethodCallException;
use Swolley\Database\Exceptions\UnexpectedValueException;

trait TraitUtils
{
	/**
	 * casts params in object format to array
	 * @param	array|object	$params	params to cast
	 */
	protected static function castToArray($params): array
	{
		$paramsType = gettype($params);
		if ($paramsType !== 'array' && $paramsType !== 'object') {
			throw new UnexpectedValueException('$params can be only array or object');
		}

		return $paramsType === 'object' ? (array)$params : $params;
	}

	/**
	 * casts params in object format to array
	 * @param	array|object	$params	params to cast
	 */
	protected static function castToObject($params): object
	{
		$paramsType = gettype($params);
		if ($paramsType !== 'array' && $paramsType !== 'object') {
			throw new UnexpectedValueException('$params can be only array or object');
		}

		return $paramsType === 'array' ? (object)$params : $params;
	}
}
