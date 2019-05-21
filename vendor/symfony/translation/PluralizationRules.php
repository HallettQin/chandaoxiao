<?php
//cgxlm
namespace Symfony\Component\Translation;

class PluralizationRules
{
	static private $rules = array();

	static public function get($number, $locale)
	{
		if ('pt_BR' === $locale) {
			$locale = 'xbr';
		}

		if (3 < strlen($locale)) {
			$locale = substr($locale, 0, 0 - strlen(strrchr($locale, '_')));
		}

		if (isset(self::$rules[$locale])) {
			$return = call_user_func(self::$rules[$locale], $number);
			if (!is_int($return) || ($return < 0)) {
				return 0;
			}

			return $return;
		}

		switch ($locale) {
		case 'az':
		case 'bo':
		case 'dz':
		case 'id':
		case 'ja':
		case 'jv':
		case 'ka':
		case 'km':
		case 'kn':
		case 'ko':
		case 'ms':
		case 'th':
		case 'tr':
		case 'vi':
		case 'zh':
			return 0;
			break;

		case 'af':
		case 'bn':
		case 'bg':
		case 'ca':
		case 'da':
		case 'de':
		case 'el':
		case 'en':
		case 'eo':
		case 'es':
		case 'et':
		case 'eu':
		case 'fa':
		case 'fi':
		case 'fo':
		case 'fur':
		case 'fy':
		case 'gl':
		case 'gu':
		case 'ha':
		case 'he':
		case 'hu':
		case 'is':
		case 'it':
		case 'ku':
		case 'lb':
		case 'ml':
		case 'mn':
		case 'mr':
		case 'nah':
		case 'nb':
		case 'ne':
		case 'nl':
		case 'nn':
		case 'no':
		case 'om':
		case 'or':
		case 'pa':
		case 'pap':
		case 'ps':
		case 'pt':
		case 'so':
		case 'sq':
		case 'sv':
		case 'sw':
		case 'ta':
		case 'te':
		case 'tk':
		case 'ur':
		case 'zu':
			return $number == 1 ? 0 : 1;
		case 'am':
		case 'bh':
		case 'fil':
		case 'fr':
		case 'gun':
		case 'hi':
		case 'hy':
		case 'ln':
		case 'mg':
		case 'nso':
		case 'xbr':
		case 'ti':
		case 'wa':
			return ($number == 0) || ($number == 1) ? 0 : 1;
		case 'be':
		case 'bs':
		case 'hr':
		case 'ru':
		case 'sr':
		case 'uk':
			return (($number % 10) == 1) && (($number % 100) != 11) ? 0 : ((2 <= $number % 10) && (($number % 10) <= 4) && ((($number % 100) < 10) || (20 <= $number % 100)) ? 1 : 2);
		case 'cs':
		case 'sk':
			return $number == 1 ? 0 : ((2 <= $number) && ($number <= 4) ? 1 : 2);
		case 'ga':
			return $number == 1 ? 0 : ($number == 2 ? 1 : 2);
		case 'lt':
			return (($number % 10) == 1) && (($number % 100) != 11) ? 0 : ((2 <= $number % 10) && ((($number % 100) < 10) || (20 <= $number % 100)) ? 1 : 2);
		case 'sl':
			return ($number % 100) == 1 ? 0 : (($number % 100) == 2 ? 1 : ((($number % 100) == 3) || (($number % 100) == 4) ? 2 : 3));
		case 'mk':
			return ($number % 10) == 1 ? 0 : 1;
		case 'mt':
			return $number == 1 ? 0 : (($number == 0) || ((1 < ($number % 100)) && (($number % 100) < 11)) ? 1 : ((10 < ($number % 100)) && (($number % 100) < 20) ? 2 : 3));
		case 'lv':
			return $number == 0 ? 0 : ((($number % 10) == 1) && (($number % 100) != 11) ? 1 : 2);
		case 'pl':
			return $number == 1 ? 0 : ((2 <= $number % 10) && (($number % 10) <= 4) && ((($number % 100) < 12) || (14 < ($number % 100))) ? 1 : 2);
		case 'cy':
			return $number == 1 ? 0 : ($number == 2 ? 1 : (($number == 8) || ($number == 11) ? 2 : 3));
		case 'ro':
			return $number == 1 ? 0 : (($number == 0) || ((0 < ($number % 100)) && (($number % 100) < 20)) ? 1 : 2);
		case 'ar':
			return $number == 0 ? 0 : ($number == 1 ? 1 : ($number == 2 ? 2 : ((3 <= $number % 100) && (($number % 100) <= 10) ? 3 : ((11 <= $number % 100) && (($number % 100) <= 99) ? 4 : 5))));
		default:
			return 0;
		}
	}

	static public function set( $rule, $locale)
	{
		if ('pt_BR' === $locale) {
			$locale = 'xbr';
		}

		if (3 < strlen($locale)) {
			$locale = substr($locale, 0, 0 - strlen(strrchr($locale, '_')));
		}

		self::$rules[$locale] = $rule;
	}
}


?>
