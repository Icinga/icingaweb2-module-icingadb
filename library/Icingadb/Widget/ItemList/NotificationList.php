<?php

/* Icinga DB Web | (c) 2020 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Icingadb\Widget\ItemList;

use Icinga\Module\Icingadb\Common\CaptionDisabled;
use Icinga\Module\Icingadb\Common\NoSubjectLink;
use Icinga\Module\Icingadb\Widget\BaseItemList;

class NotificationList extends BaseItemList
{
    use CaptionDisabled;
    use NoSubjectLink;

    protected $defaultAttributes = ['class' => 'notification-list'];

    protected function getItemClass()
    {
        return NotificationListItem::class;
    }
}
