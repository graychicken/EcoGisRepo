<?php

namespace EcogisBundle\Repository;

class UtilitySupplierRepository
{

    /**
     * Return the concatenate name of the heating utility supplier (used in statistic)
     * @param integer $muId       Municipality ID
     * @param integer $lang       Language id (1 or 2)
     * @param string  $separator  Separator
     */
    public function getHeatingConcatNameForMunicipality($muId, $lang, $separator = ' / ')
    {
        $db = \ezcDbInstance::get();
        $muId = (int) $muId;

        $sql = "SELECT array_to_string(array_agg(DISTINCT us_name_{$lang}),'{$separator}') AS us_name
                FROM ecogis.utility_supplier
                INNER JOIN ecogis.utility_supplier_municipality USING (us_id)
                INNER JOIN ecogis.utility_product ON utility_supplier.us_id=utility_product.us_id AND up_code='DISTRICT_HEATING'
                WHERE mu_id={$muId}";
        return $db->query($sql)->fetchColumn();
    }

    /**
     * Return the concatenate name of the heating utility supplier (used in statistic)
     * @param integer $doId       Domain ID
     * @param integer $lang       Language id (1 or 2)
     * @param string  $separator  Separator
     */
    public function getHeatingConcatNameForDomain($doId, $lang, $separator = ' / ')
    {
        $db = \ezcDbInstance::get();
        $doId = (int) $doId;

        $sql = "SELECT array_to_string(array_agg(DISTINCT us_name_{$lang}),' / ') AS us_name
                FROM ecogis.utility_supplier us
                INNER JOIN ecogis.utility_supplier_municipality usm USING (us_id)
                INNER JOIN ecogis.utility_product up ON us.us_id=up.us_id AND up_code='DISTRICT_HEATING'
                WHERE us.do_id={$doId}";
        return $db->query($sql)->fetchColumn();
    }
}