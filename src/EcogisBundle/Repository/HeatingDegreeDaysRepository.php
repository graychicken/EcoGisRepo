<?php

namespace EcogisBundle\Repository;

class HeatingDegreeDaysRepository
{

    /**
     * Return true if there are heating degree days for the given municipality
     * @param integer $muId   Municipality ID
     */
    public function hasHeatingDegreeDayForMunicipality($muId)
    {
        $db = \ezcDbInstance::get();
        $muId = (int) $muId;

        $sql = "SELECT COUNT(*)
                FROM ecogis.heating_degree_day
                WHERE mu_id={$muId}";
        return $db->query($sql)->fetchColumn() > 0;
    }

    /**
     * Return true if there are heating degree days for the given domain
     * @param integer $doId   Domain ID
     */
    public function hasHeatingDegreeDayForDomain($doId)
    {
        $db = \ezcDbInstance::get();
        $doId = (int) $doId;

        $sql = "SELECT COUNT(*)
                FROM ecogis.heating_degree_day
                INNER JOIN ecogis.municipality USING(mu_id)
                WHERE do_id={$doId}";
        return $db->query($sql)->fetchColumn() > 0;
    }
}