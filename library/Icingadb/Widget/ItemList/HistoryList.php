<?php

/* Icinga DB Web | (c) 2020 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Icingadb\Widget\ItemList;

use Icinga\Module\Icingadb\Common\CaptionDisabled;
use Icinga\Module\Icingadb\Common\NoSubjectLink;
use Icinga\Module\Icingadb\Widget\BaseItemList;

class HistoryList extends BaseItemList
{
    use CaptionDisabled;
    use NoSubjectLink;

    protected $defaultAttributes = ['class' => 'history-list'];

    protected $pageSize;

    protected $pageNumber;

    protected function init()
    {
        $this->realData = $this->data;
        $this->data = $this->getIterator();
    }

    public function setPageSize($size)
    {
        $this->pageSize = $size;

        return $this;
    }

    public function setPageNumber($number)
    {
        $this->pageNumber = $number;

        return $this;
    }

    protected function getItemClass()
    {
        return HistoryListItem::class;
    }

    protected function getIterator()
    {
        $count = 0;
        $pageNumber = $this->pageNumber ?: 1;

        if ($pageNumber > 1) {
            $this->add(new PageSeparatorItem($pageNumber));
        }

        foreach ($this->realData as $data) {
            $count++;

            if ($count % $this->pageSize === 0) {
                $pageNumber++;
            } elseif ($count > $this->pageSize && $count % $this->pageSize === 1) {
                $this->add(new PageSeparatorItem($pageNumber));
            }

            yield $data;
        }
    }
}
