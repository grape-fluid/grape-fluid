<?php

namespace Grapesc\GrapeFluid\Utils;

use Nette\Utils\Reflection;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

class Annotation
{

	/**
	 * Static class - cannot be instantiated.
	 * @throws \Nette\StaticClassException
	 */
	final public function __construct()
	{
		throw new \Nette\StaticClassException;
	}

	

	/**
	 * @param string $key
	 * @param string|object $class
	 * @param bool $useInheritance
	 * @return mixed
	 * @throws LogicException
	 */
    public static function getValue($key, $class, $useInheritance = true)
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        if (!is_string($class)) {
            throw new LogicException("\$class have to be a string or object.");
        }

        $ref = new \ReflectionClass($class);
		$mat = [];
		$val = self::getAnnotation($ref, $key);

        if ($val === null && $useInheritance) {
            $class = get_parent_class($class);

            if ($class !== false) {
                $val = self::getValue($key, $class, true);
            }
        }

        return $val;
    }


	public static function hasAnnotation(ReflectionClass|ReflectionMethod|ReflectionProperty $reflection, string $annotation): bool
	{
		return array_key_exists($annotation, self::getAnnotations($reflection));
	}


	public static function getAnnotations(ReflectionClass|ReflectionMethod|ReflectionProperty $reflection, array $multipleValuesAnnotations = []): array
	{
		return self::getAnnotation($reflection, null, $multipleValuesAnnotations) ?: [];
	}


	public static function getAnnotation(ReflectionClass|ReflectionMethod|ReflectionProperty $reflection, ?string $annotation, bool|array $multipleValues = false): string|bool|array|null
	{
		if (!Reflection::areCommentsAvailable()) {
			throw new InvalidStateException('You have to enable phpDoc comments in opcode cache.');
		}

		$tokens = ['true' => true, 'false' => false, 'null' => null];

		if ($annotation === null) {
			if ($reflection->getDocComment() && preg_match_all("#[\\s*]@" . '([_a-zA-Z\x7F-\xFF][_a-zA-Z0-9\x7F-\xFF-\\\]*)' .
					'(\((?>\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*"|[^\'")@]+)+\)|[^(@\r\n][^@\r\n]*|)' . "#",
					trim($reflection->getDocComment(), '/*'), $m)) {

				$output = [];

				foreach ($m[1] ?? [] AS $key => $name) {
					$value = trim($m[2][$key], " \n\r\t\v\0()");
					$output[$name] = array_key_exists($tmp = strtolower($value), $tokens) ? $tokens[$tmp] : $value;
					if ((is_array($multipleValues) && in_array($name, $multipleValues)) || $multipleValues === true) {
						$output[$name] = explode(',', preg_replace('/[*\s]/', '', $output[$name]));
					}
				}

				return $output;
			}
		} else {
			$name = preg_quote($annotation, '#');

			if ($reflection->getDocComment() && preg_match("#[\\s*]@$name" .
					'(\((?>\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*"|[^\'")@]+)+\)|[^(@\r\n][^@\r\n]*|)' . "#",
					trim($reflection->getDocComment(), '/*'), $m)) {
				$value = isset($m[1]) ? trim($m[1], " \n\r\t\v\0()") : '';

				$value = array_key_exists($tmp = strtolower($value), $tokens) ? $tokens[$tmp] : $value;

				if ($multipleValues === true) {
					return explode(',', preg_replace('/[*\s]/', '', $value));
				} else {
					return $value;
				}
			}
		}

		return null;
	}

}