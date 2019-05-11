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

	protected static function replaceCarriageReturns(string $query): string
	{
		return preg_replace('(/\n\r|\n|\r/)', ' ', $query);
	}

	protected static function colonsToQuestionMarksPlaceholders(string &$query, array &$params): void
	{
		$total_params = count($params);
		$total_questionmark_placeholders = substr_count($query, '?');
		$colon_placeholders = [];
		preg_match_all('/(:\w+)/i', $query, $colon_placeholders);
		$colon_placeholders = array_shift($colon_placeholders);
		$total_colon_placeholders = count($colon_placeholders);

		if($total_colon_placeholders > 0 && $total_questionmark_placeholders > 0) {
			throw new UnexpectedValueException('Possible incongruence in query placeholders');
		}

		if(($total_colon_placeholders === 0 && $total_questionmark_placeholders !== $total_params) || ($total_questionmark_placeholders === 0 && $total_colon_placeholders !== $total_params)) {
			throw new BadMethodCallException('Number of params and placeholders must be the same');
		}
		
		//changes colon placeholders found they are switched to question marks because of mysqli bind restruction
		if($total_questionmark_placeholders === 0) {
			$reordered_params = [];
			foreach($colon_placeholders as $param) {
				$trimmed = ltrim($param, ':');
				if(array_key_exists($trimmed, $params)) {
					$reordered_params[] = $params[$trimmed];
					$query = str_replace($param, '?', $query);
				} else {
					throw new BadMethodCallException("`$param` not found in parameters list");
				}
			}

			$params = $reordered_params;
			unset($reordered_params);
		}
	}
}
