<?php

/* Icinga DB Web | (c) 2021 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Icingadb\Controllers;

use Icinga\Module\Icingadb\Model\History;
use Icinga\Module\Icingadb\Web\Controller;
use Icinga\Module\Icingadb\Widget\Detail\EventDetail;
use Icinga\Module\Icingadb\Widget\ItemList\HistoryList;
use ipl\Stdlib\Filter;

class EventController extends Controller
{
    /** @var History */
    protected $event;

    public function init()
    {
        $this->setTitle(t('Event'));

        $id = $this->params->shiftRequired('id');

        $query = History::on($this->getDb())
            ->with([
               'host',
               'host.state',
               'service',
               'service.state',
               'comment',
               'downtime',
               'flapping',
               'notification',
               'acknowledgement',
               'state'
            ])
            ->filter(Filter::equal('id', hex2bin($id)));

        $this->applyRestrictions($query);

        $event = $query->first();
        if ($event === null) {
            $this->httpNotFound(t('Event not found'));
        }

        $this->event = $event;
    }

    public function indexAction()
    {
        $this->addControl((new HistoryList([$this->event]))
            ->setPageSize(1)
            ->setCaptionDisabled()
            ->setNoSubjectLink()
            ->setDetailActionsDisabled());
        $this->addContent(new EventDetail($this->event));
    }
}
