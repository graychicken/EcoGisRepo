<?php
$isUserManager = true;

function AjaxSplitArray($elems, $separator='|', $trim=true) {

    $res = array();
    foreach ($elems as $value) {
      if (($p = strpos($value, $separator)) === null) {
        $res[$value] = null;
      } else {
        $param = substr($value, 0, $p);
        $value = substr($value, $p + 1);
        if (strpos($value, 'multiple:') !== false) {
            if (preg_match_all("/\[([^\[\]]+)\]/", substr($value, 9), $matches) !== false) {
                $value = $matches[1];
            }
        } else if ($trim)
          $value = trim($value);
        $res[$param] = $value;
      }
    }
    return $res;
}
