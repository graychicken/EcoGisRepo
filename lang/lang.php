<?php

/**
 * get an string with lang name short
 *
 * @param integer $lang
 * @return string with lang name short
 */
function getLangNameShort($lang) {
    global $auth, $dbini;

    if (isset($_SESSION['do_id']) && $auth->hasPerm('SHOW', 'ALL_DOMAINS') && $auth->getDomainID() <> $_SESSION['do_id']) {
        $dbini2 = clone $dbini;
        $dbini2->setDomainName($auth->getDomainCodeFromID($_SESSION['do_id']));
        return $dbini2->getValue('APPLICATION', "LANG_NAME_SHORT_{$lang}");
    }
    return $auth->getConfigValue('APPLICATION', "LANG_NAME_SHORT_{$lang}");
}

function getLangName($lang, $ucFirst = false) {

    switch ($lang) {
        case 2: $text = _('tedesco');
            break;
        case 3: $text = _('inglese');
            break;
        default:
            $text = _('italiano');
            break;
    }
    return $ucFirst ? UcFirst($text) : $text;
}

/* -------------------- set Locale & include Messages -------------------- */

