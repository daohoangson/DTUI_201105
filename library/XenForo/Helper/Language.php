<?php

class XenForo_Helper_Language
{
	public static function getLocaleList()
	{
		$output = array();
		foreach (self::getLanguageCodes() AS $code)
		{
			$output[$code] = new XenForo_Phrase('language_' . str_replace('-', '_', $code));
		}

		asort($output, SORT_STRING);

		return $output;
	}

	public static function getLanguageCodes()
	{
		return array(
			'af-ZA',
			'ar-AR',
			'ay-BO',
			'az-AZ',
			'be-BY',
			'bg-BG',
			'bn-IN',
			'bs-BA',
			'ca-ES',
			'ck-US',
			'cs-CZ',
			'cy-GB',
			'da-DK',
			'de-DE',
			'el-GR',
			'en-GB',
			'en-US',
			'eo-EO',
			'es-CL',
			'es-CO',
			'es-ES',
			'es-LA',
			'es-MX',
			'es-VE',
			'et-EE',
			'eu-ES',
			'fa-IR',
			'fi-FI',
			'fo-FO',
			'fr-CA',
			'fr-FR',
			'ga-IE',
			'gl-ES',
			'gu-IN',
			'he-IL',
			'hi-IN',
			'hr-HR',
			'hu-HU',
			'hy-AM',
			'id-ID',
			'is-IS',
			'it-IT',
			'ja-JP',
			'jv-ID',
			'ka-GE',
			'kk-KZ',
			'km-KH',
			'kn-IN',
			'ko-KR',
			'ku-TR',
			'la-VA',
			'li-NL',
			'lt-LT',
			'lv-LV',
			'mg-MG',
			'mk-MK',
			'ml-IN',
			'mn-MN',
			'mr-IN',
			'ms-MY',
			'mt-MT',
			'nb-NO',
			'ne-NP',
			'nl-NL',
			'nn-NO',
			'pa-IN',
			'pl-PL',
			'ps-AF',
			'pt-BR',
			'pt-PT',
			'qu-PE',
			'rm-CH',
			'ro-RO',
			'ru-RU',
			'sa-IN',
			'sk-SK',
			'sl-SI',
			'so-SO',
			'sq-AL',
			'sr-RS',
			'sv-SE',
			'sw-KE',
			'sy-SY',
			'ta-IN',
			'te-IN',
			'tg-TJ',
			'th-TH',
			'tl-PH',
			'tl-ST',
			'tr-TR',
			'tt-RU',
			'uk-UA',
			'ur-PK',
			'uz-UZ',
			'vi-VN',
			'xh-ZA',
			'yi-DE',
			'zh-CN',
			'zh-HK',
			'zh-TW',
			'zu-ZA'
		);
	}
}