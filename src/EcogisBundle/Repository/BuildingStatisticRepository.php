<?php

namespace EcogisBundle\Repository;

class BuildingStatisticRepository
{

    private function getStatisitcSqlFragment()
    {
        return "SELECT bu.bu_id, COALESCE(street.mu_id, fraction.mu_id) AS mu_id, co.co_year,
                       bpu_name_1, bpu_name_2, 
                       COALESCE(et1.et_code, (et2.et_code::text || '_UTILITY'::text)::character varying) AS et_code,
                       co.co_value, COALESCE(esu1.esu_kwh_factor, esu2.esu_kwh_factor) AS esu_kwh_factor,
                       COALESCE(esu1.esu_co2_factor, esu2.esu_co2_factor) AS esu_co2_factor
                FROM ecogis.consumption_year co
                INNER JOIN ecogis.energy_meter em ON em.em_id = co.em_id
                INNER JOIN ecogis.energy_meter_object emo ON em.emo_id = emo.emo_id AND emo.emo_code::text = 'BUILDING'::text
                INNER JOIN ecogis.building bu ON bu.bu_id = em.em_object_id
                INNER JOIN ecogis.building_purpose_use bpu ON bu.bpu_id = bpu.bpu_id
                LEFT JOIN common.street USING (st_id)
                LEFT JOIN common.fraction USING (fr_id)
                LEFT JOIN (ecogis.energy_source_udm esu1
                    INNER JOIN ecogis.energy_source es1 ON esu1.es_id = es1.es_id
                    INNER JOIN ecogis.energy_type et1 ON es1.et_id = et1.et_id) ON em.esu_id = esu1.esu_id
                LEFT JOIN (ecogis.utility_product up
                    INNER JOIN ecogis.energy_source_udm esu2 ON up.esu_id = esu2.esu_id
                    INNER JOIN ecogis.energy_source es2 ON esu2.es_id = es2.es_id
                    INNER JOIN ecogis.utility_supplier us2 ON up.us_id = us2.us_id
                    INNER JOIN ecogis.energy_type et2 ON es2.et_id = et2.et_id) ON em.up_id = up.up_id";
    }

    private function getStatisitcSumFields($divider = 1)
    {
        return "co_year,
                ROUND(SUM(CASE WHEN et_code='HEATING' THEN co_value * esu_kwh_factor/{$divider} END)) AS heating,
                ROUND(SUM(CASE WHEN et_code='HEATING' THEN co_value * esu_kwh_factor * COALESCE(hdd.hdd_factor, 1)/{$divider} END)) AS heating_gg,
                ROUND(SUM(CASE WHEN et_code='HEATING_UTILITY' THEN co_value*esu_kwh_factor/{$divider} END)) AS heating_utility,
                ROUND(SUM(CASE WHEN et_code='HEATING_UTILITY' THEN co_value*esu_kwh_factor*COALESCE(hdd.hdd_factor, 1)/{$divider} END)) AS heating_utility_gg,
                ROUND(SUM(CASE WHEN et_code='ELECTRICITY' THEN co_value*esu_kwh_factor/{$divider} END)) AS electricity,
                ROUND(SUM(CASE WHEN et_code='ELECTRICITY_UTILITY' THEN co_value*esu_kwh_factor/{$divider} END)) AS electricity_utility,
                ROUND(SUM(co_value * esu_co2_factor/{$divider})) AS co2,
                ROUND(SUM(co_value * esu_co2_factor *
                      COALESCE(
                         CASE WHEN et_code IN ('HEATING', 'HEATING_UTILITY') THEN hdd.hdd_factor END,
                         1)/{$divider})) AS co2_gg";
    }

    private function fetchStatisticData($sql)
    {
        $db = \ezcDbInstance::get();

        $rows = array();
        foreach ($db->query($sql, \PDO::FETCH_ASSOC) as $row) {
            foreach (array('heating', 'heating_gg', 'heating_utility', 'heating_utility_gg', 'electricity',
            'electricity_utility', 'co2', 'co2_gg') as $field) {
                $row["{$field}_fmt"] = R3NumberFormat($row[$field], null, true);
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Return an array with the statistic data for the given building
     * @param integer $muId   Municipality ID
     */
    public function getStatisticForBuilding($buId)
    {
        $buId = (int) $buId;
        $q1 = $this->getStatisitcSqlFragment();
        $fields = $this->getStatisitcSumFields();
        $sql = "WITH q1 AS (
                    {$q1}
                    WHERE bu_id={$buId}
                )
                SELECT {$fields}
                FROM q1
                    LEFT JOIN ecogis.heating_degree_day hdd ON hdd.mu_id = q1.mu_id AND hdd.hdd_year=q1.co_year
                GROUP BY bu_id, co_year
                ORDER BY co_year DESC";
        return $this->fetchStatisticData($sql);
    }

    /**
     * Return an array with the statistic data for the given building
     * @param integer $muId   Municipality ID
     */
    public function getStatisticForMunicipality($muId, $divider = 1, $reverseOrder = false)
    {

        $muId = (int) $muId;
        $where = $muId > 0 ? "bu.mu_id={$muId}" : "true";

        $q1 = $this->getStatisitcSqlFragment();
        $fields = $this->getStatisitcSumFields($divider);
        $orderBy = $reverseOrder ? 'co_year DESC' : 'co_year';
        $sql = "WITH q1 AS (
                    {$q1}
                    WHERE {$where}
                )
                SELECT {$fields}
                FROM q1
                    LEFT JOIN ecogis.heating_degree_day hdd ON hdd.mu_id = q1.mu_id AND hdd.hdd_year=q1.co_year
                GROUP BY co_year
                ORDER BY {$orderBy}";
        return $this->fetchStatisticData($sql);
    }

    /**
     * Return an array with the statistic data for the given building
     * @param integer $muId          Municipality ID
     * @param integer $lang          Language id (1 or 2)
     * @param integer $bpuIdFilter   If > 0, the data is filtered by the given buoiding pourpose use
     * @param integer $divider       Divider to apply (1 or 1000)
     */
    public function getStatisticForMunicipalityAndBpu($muId, $lang, $bpuIdFilter, $divider = 1, $reverseOrder = false)
    {
        $muId = (int) $muId;
        $bpuIdFilter = (int)$bpuIdFilter;
        $whereArray = array('true');
        if ($muId > 0) {
            $whereArray[] = "bu.mu_id={$muId}";
        }
        if ($bpuIdFilter > 0) {
            $whereArray[] = "bu.bpu_id={$bpuIdFilter}";
        }
        $where = implode(' AND ', $whereArray);
        $orderBy = $reverseOrder ? "co_year DESC, bpu_name_{$lang} ASC" : "co_year, bpu_name_{$lang} ASC";

        $q1 = $this->getStatisitcSqlFragment();
        $fields = $this->getStatisitcSumFields($divider);
        $sql = "WITH q1 AS (
                    {$q1}
                    WHERE {$where}
                )
                SELECT bpu_name_{$lang} AS bpu_name, {$fields}
                FROM q1
                    LEFT JOIN ecogis.heating_degree_day hdd ON hdd.mu_id = q1.mu_id AND hdd.hdd_year=q1.co_year
                GROUP BY co_year, bpu_name_{$lang}
                ORDER BY {$orderBy}";
        return $this->fetchStatisticData($sql);
    }
}