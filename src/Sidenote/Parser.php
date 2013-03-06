<?php

namespace Epsi\Sidenote;

use \Reflector;

/**
 * Annotation parser
 *
 * Can parse class/property/method annotations.
 * Upon first request reads all annotations on given reflector.
 * Keeps parsed annotations cached.
 *
 * @author Michał Rudnicki <michal.rudnicki@epsi.pl>
 */
final class Parser {

	/**
	 * Local cache for parsed annotations
	 *
	 * Key is CRC32 sum of a reflector string representation.
	 * Value is hash of parsed annotations as keys pointing to array of their values.
	 *
	 * @var array
	 */
	private static $cache = array();

	/**
	 * Return annotation value
	 *
	 * @param Reflector $reflection
	 * @param string $annotation
	 * @return mixed
	 */
	public static function get(Reflector $reflection, $annotation) {
		$key = crc32($reflection->__toString());
		$annotation[0] === "@" or $annotation = "@" . $annotation;
		isset(self::$cache[$key]) or self::$cache[$key] = self::parse($reflection);
		return isset(self::$cache[$key][$annotation])
			? self::$cache[$key][$annotation][0]
			: FALSE;
	}

	/**
	 * Return all annotation values
	 *
	 * @param Reflector $reflection
	 * @param string $annotation
	 * @return mixed[]
	 */
	public static function getAll(Reflector $reflection, $annotation) {
		$key = crc32($reflection->__toString());
		$annotation[0] === "@" or $annotation = "@" . $annotation;
		isset(self::$cache[$key]) or self::$cache[$key] = self::parse($reflection);
		return isset(self::$cache[$key][$annotation])
			? self::$cache[$key][$annotation]
			: array();
	}

	/**
	 * Parse annotations on given reflector
	 *
	 * @param Reflector $reflection
	 * @return array
	 */
	public static function parse(Reflector $reflection) {
		// check if reflection provides doc comment
		if (!method_exists($reflection, "getDocComment")) {
			throw new Exception("Reflector of class " . get_class($reflection) . " does not implement getDocComment() method");
		}

		// parse doc comment
		$out = array();
		$lines = explode("\n", $reflection->getDocComment());
		foreach ($lines as $line) {

			// check if line starts with @
			$line = trim($line, "\t\n */");
			if ($line === "" or $line[0] !== "@") {
				continue;
			}

			// get annotation value
			$pos = mb_strpos($line, " ");
			if ($pos === FALSE) {
				$firstWord = $line;
				$value = TRUE;
			} else {
				$firstWord = mb_substr($line, 0, $pos);
				$rest = trim(mb_substr($line, $pos + 1));
				if (mb_strtolower($rest) === "null") {
					$value = NULL;
				} else {
					$value = json_decode($rest); // try to decode JSON string
					NULL === $value and $value = json_decode("[{$rest}]", TRUE); // try to decode JSON string as hash array
					NULL === $value and $value = $rest; // fall back to raw string
				}
			}

			// set the value
			isset($out[$firstWord]) or $out[$firstWord] = array();
			$out[$firstWord][] = $value;
		}
		return $out;
	}

}