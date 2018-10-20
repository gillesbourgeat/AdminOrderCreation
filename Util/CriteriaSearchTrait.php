<?php

namespace AdminOrderCreation\Util;

use Propel\Runtime\ActiveQuery\ModelCriteria;

trait CriteriaSearchTrait
{
    /**
     * @param string $q
     * @return string
     */
    public function getRegex($q)
    {
        $q = explode(' ', $q);

        $words = array();

        foreach ($q as $v) {
            $v = trim($v);
            if (strlen($v) > 2 && preg_match('/^[a-z0-9]+$/i', $v)) {
                $words[] = $v;
            }
        }

        if (!count($words)) {
            return null;
        }

        $regex = array();
        $regex[] = '.*' . implode('.+', $words) . '.*';
        if (count($words) > 1) {
            $regex[] = '.*' . implode('.+', array_reverse($words)) . '.*';
        }

        return implode('|', $regex);
    }

    /**
     * @param ModelCriteria $query
     * @param array $columns
     * @param string $q
     */
    public function whereConcatRegex(ModelCriteria $query, array $columns, $q)
    {
        $query->where("CONCAT_WS(' ', " . implode(',', $columns). ") REGEXP ?", self::getRegex($q), \PDO::PARAM_STR);
    }
}
