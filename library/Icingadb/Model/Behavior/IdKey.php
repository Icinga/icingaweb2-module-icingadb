<?php

/* Icinga DB Web | (c) 2021 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Icingadb\Model\Behavior;

use ipl\Orm\Contract\RewriteFilterBehavior;
use ipl\Stdlib\Filter;

/**
 * Rewrite filter behavior IdKey
 *
 * Transforms hexadecimal values to binary in filter conditions targeting id columns.
 */
class IdKey implements RewriteFilterBehavior
{
    public function rewriteCondition(Filter\Condition $condition, $relation = null)
    {
        $column = $condition->metaData()->get('columnName');
        if ($column === 'id' || substr($column, -3) === '_id') {
            $value = $condition->getValue();
            if ($value && ctype_alnum($value)) {
                $condition->setValue(hex2bin($value));
            }
        }
    }
}
