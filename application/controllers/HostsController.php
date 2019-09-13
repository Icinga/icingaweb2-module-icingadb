<?php

namespace Icinga\Module\Eagle\Controllers;

use Icinga\Module\Eagle\Model\Host;
use Icinga\Module\Eagle\Model\HostState;
use Icinga\Module\Eagle\Redis\VolatileHostState;
use Icinga\Module\Eagle\Web\Controller;
use Icinga\Module\Eagle\Widget\HostList;

class HostsController extends Controller
{
    public function indexAction()
    {

        $this->setTitle($this->translate('Hosts'));

        $db = $this->getDb();

        $hosts = Host::on($db)->with('state');
        $hosts->limit(25);

        $hostList = (new HostList($hosts))
            ->setVolatileState(new VolatileHostState($this->getRedis()));

        $this->addContent($hostList);
    }
}
