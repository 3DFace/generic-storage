<?php

namespace dface\GenericStorage\Generic;

class ArrayPathNavigator
{

	public static function getPropertyValue(array $arr, array $path, $default = null)
	{
		$x = $arr;
		foreach ($path as $p) {
			if (\is_object($x)) {
				$x = (array)$x;
			}
			if ($x === null || !\array_key_exists($p, $x)) {
				return $default;
			}
			$x = $x[$p];
		}
		return $x;
	}

	public static function fallbackPropertyValue(array &$arr, array $path, $value) : void
	{
		$last = \count($path) - 1;
		$x = &$arr;
		for ($i = 0; $i < $last; $i++) {
			$p = $path[$i];
			if (!\array_key_exists($p, $x)) {
				$x[$p] = [];
			}
			$x = &$x[$p];
			if(!\is_array($x)){
				return;
			}
		}
		$p = $path[$last];
		if (!\array_key_exists($p, $x)) {
			$x[$p] = $value;
		}
	}

	public static function setPropertyValue(array &$arr, array $path, $value) : void
	{
		$last = \count($path) - 1;
		$x = &$arr;
		for ($i = 0; $i < $last; $i++) {
			$p = $path[$i];
			if (!isset($x[$p])) {
				$x[$p] = [];
			}
			$x = &$x[$p];
		}
		$p = $path[$last];
		$x[$p] = $value;
	}

	/**
	 * @param array $arr
	 * @param array $path
	 * @param mixed|null $default
	 * @return mixed|null
	 */
	public static function extractProperty(array &$arr, array $path, $default = null)
	{
		$last = \count($path) - 1;
		$x = &$arr;
		for ($i = 0; $i < $last; $i++) {
			$p = $path[$i];
			if (\is_object($x)) {
				if (!isset($x->{$p})) {
					return $default;
				}
				$x = &$x->{$p};
			} elseif (\is_array($x)) {
				if (!isset($x[$p])) {
					return $default;
				}
				$x = &$x[$p];
			} else {
				return $default;
			}
		}
		$p = $path[$last];
		if (\is_object($x)) {
			if (!\property_exists($x, $p)) {
				return $default;
			}
			$result = $x->{$p};
			unset($x->{$p});
			return $result;
		}

		if (\is_array($x)) {
			if (!\array_key_exists($p, $x)) {
				return $default;
			}
			$result = $x[$p];
			unset($x[$p]);
			return $result;
		}

		return $default;

	}

}
