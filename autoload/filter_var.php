<?php
declare(strict_types=1);
namespace gimle;

const FILTER_VALIDATE_FILENAME = 'gv1';
const FILTER_SANITIZE_FILENAME = 'gs1';
const FILTER_VALIDATE_DIRNAME = 'gv2';
const FILTER_SANITIZE_DIRNAME = 'gs2';
const FILTER_VALIDATE_NAME = 'gv3';
const FILTER_SANITIZE_NAME = 'gs3';

function filter_var ($variable, $filter = FILTER_DEFAULT, $options = null)
{
	if ($filter === FILTER_VALIDATE_FILENAME) {
		return !preg_match('/[\x00*:;\\\"\/<>\|\?]/', $variable);
	}
	if ($filter === FILTER_SANITIZE_FILENAME) {
		$replaceCharacter = '';
		if ((is_array($options)) && (isset($options['replace_char']))) {
			$replaceCharacter = $options['replace_char'];
		}
		return preg_replace('/[\x00*:;\\\"\/<>\|\?]/', $replaceCharacter, $variable);
	}
	if ($filter === FILTER_VALIDATE_DIRNAME) {
		return !preg_match('/[\x00*:;\\\"<>\|\?]/', $variable);
	}
	if ($filter === FILTER_SANITIZE_DIRNAME) {
		$replaceCharacter = '';
		if ((is_array($options)) && (isset($options['replace_char']))) {
			$replaceCharacter = $options['replace_char'];
		}
		return preg_replace('/[\x00*:;\\\"<>\|\?]/', $replaceCharacter, $variable);
	}

	if ($filter === FILTER_VALIDATE_NAME) {
		return ($variable === filter_var($variable, FILTER_SANITIZE_NAME));
	}
	if ($filter === FILTER_SANITIZE_NAME) {
		$string = str_replace(['\'', "\n", "\r", "\t", "\xC2\xA0"], ['’', ' ', ' ', ' ', ' '], $variable);
		$string = trim(preg_replace('/[ ]{2,}/', ' ', $string));
		$string = preg_replace("#[^\pL \-\.’]#iu", '', $string);
		$wordSplitters = [' ', '-', "O’", "L’", "D’", 'St.', 'Mc'];
		$lcExceptions = ['the', 'van', 'den', 'von', 'und', 'der', 'de', 'da', 'of', 'and', "l’", "d’"];
		$ucExceptions = ['III', 'IV', 'VI', 'VII', 'VIII', 'IX'];

		$string = mb_strtolower($string);
		foreach ($wordSplitters as $delimiter) {
			$words = explode($delimiter, $string);
			$newwords = [];
			foreach ($words as $word) {
				if (in_array(mb_strtoupper($word), $ucExceptions)) {
					$word = mb_strtoupper($word);
				}
				elseif (!in_array($word, $lcExceptions)) {
					$word = mb_ucfirst($word);
				}

				$newwords[] = $word;
			}

			if (in_array(mb_strtolower($delimiter), $lcExceptions)) {
				$delimiter = mb_strtolower($delimiter);
			}

			$string = join($delimiter, $newwords);
		}
		return $string;
	}

	if ($options === null) {
		return \filter_var($variable, $filter);
	}
	return \filter_var($variable, $filter, $options);
}
