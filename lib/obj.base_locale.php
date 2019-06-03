<?php

/**
 * Local management
 */
class R3Locale {

    /**
     * @var class|null      The language ID (Eg: 0=EN, 1=IT, 2=DE, ...);
     * @TODO                Translation table needed?
     */
    static private $R3LangID = 0;

    /**
     * the language map (1 => 'it', 2 => 'de')
     * @var type
     */
    static private $languages = array();

    static private $jQueryDateFormat;

    static private $phpDateFormat;

    static private $phpDateTimeFormat;

    /**
     * Get the current language ID
     * @return integer  The language ID
     */
    static public function getLanguageID() {
        return R3Locale::$R3LangID;
    }

    /**
     * Set the laguage ID (1, 2)
     * @return integer  The language ID
     */
    static public function setLanguageID($langId) {
        R3Locale::$R3LangID = (int) $langId;
        return R3Locale::$R3LangID;
    }

    /**
     * Set the laguage ID from the code (it, de)
     * @return integer  The language ID
     */
    static public function setLanguageIDFromCode($langCode) {
        $languages = self::getLanguages();
        $langId =array_search($langCode, $languages);
        if (empty($langId)) {
            throw new Exception("Invalid language \"{$langCode}\"");
        }
        return self::setLanguageID($langId);
    }

    /**
     * Set the language map
     * @param array $languages (1 => 'it', 2 => 'de')
     */
    static public function setLanguages(array $languages) {
        R3Locale::$languages = $languages;
    }

    /**
     * Get the language map
     * return array
     */
    static public function getLanguages() {
        if (empty(R3Locale::$languages)) {
            throw new \Exception("Missing language map");
        }
        return R3Locale::$languages;
    }

    static public function setJqueryDateFormat($jQueryDateFormat) {
        R3Locale::$jQueryDateFormat = $jQueryDateFormat;
    }

    static public function setPhpDateFormat($phpDateFormat) {
        R3Locale::$phpDateFormat = $phpDateFormat;
    }

    static public function setPhpDateTimeFormat($phpDateTimeFormat) {
        R3Locale::$phpDateTimeFormat = $phpDateTimeFormat;
    }

    /**
     * Get the language code
     * @return string  The language ID
     */
    static public function getLanguageCode() {
        
        $languages = self::getLanguages();
        if (isset($languages[R3Locale::$R3LangID])) {
            return $languages[R3Locale::$R3LangID];
        }
        return null;
    }

    /**
     * Get the jquery date format
     * @return string  The php date format
     */
    static public function getJQueryDateFormat() {
        if (empty(R3Locale::$jQueryDateFormat)) {
            throw new \Exception("Missing jquery date format");
        }
        $jQueryDateFormat = R3Locale::$jQueryDateFormat;

        if (isset($jQueryDateFormat[R3Locale::getLanguageCode()])) {
            return $jQueryDateFormat[R3Locale::getLanguageCode()];
        }
        return null;
    }

    /**
     * Get the php date format
     * @return string  The php date format
     */
    static public function getPhpDateFormat() {
        if (empty(R3Locale::$phpDateFormat)) {
            throw new \Exception("Missing php date format");
        }
        $phpDateFormat = R3Locale::$phpDateFormat;
        if (isset($phpDateFormat[R3Locale::getLanguageCode()])) {
            return $phpDateFormat[R3Locale::getLanguageCode()];
        }
        return null;
    }

    /**
     * Get the php date format
     * @return string  The php date format
     */
    static public function getPhpDateTimeFormat() {
        if (empty(R3Locale::$phpDateTimeFormat)) {
            throw new \Exception("Missing php date/time format");
        }
        $phpDateTimeFormat = R3Locale::$phpDateTimeFormat;

        if (isset($phpDateTimeFormat[R3Locale::getLanguageCode()])) {
            return $phpDateTimeFormat[R3Locale::getLanguageCode()];
        }
        return null;
    }

    static public function getDateSeparator() {
        $fmt = R3Locale::getPhpDateFormat();
        if ($fmt == '')
            return null;
        return $fmt[1];
    }

    /**
     * Convert the given data (float and ineger) into php data
     */
    static function convert2PHP($data, $useThousandsSep = true) {
        if (is_array($data)) {
            foreach ($data as $key => $val)
                $data[$key] = R3Locale::convert2PHP($val, $useThousandsSep);
        } else {
            if (is_string($data) === true && is_numeric($data) === true) {
                if (strpos($data, '.') === false) {
                    $data = (int) $data;
                } else {
                    $data = (float) $data;
                }
            }
        }
        return $data;
    }

}
