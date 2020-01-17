<?php

namespace Icinga\Module\Icingadb\Widget\ItemList;

use Icinga\Module\Icingadb\Widget\BaseItemList;

class UserList extends BaseItemList
{
    protected $defaultAttributes = ['class' => 'user-list item-table'];

    protected function getItemClass()
    {
        return UserListItem::class;
    }
}
