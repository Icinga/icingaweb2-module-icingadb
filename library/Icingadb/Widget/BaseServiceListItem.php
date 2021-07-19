<?php

/* Icinga DB Web | (c) 2020 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Icingadb\Widget;

use Icinga\Module\Icingadb\Common\Links;
use Icinga\Module\Icingadb\Common\NoSubjectLink;
use Icinga\Module\Icingadb\Model\Service;
use ipl\Html\Attributes;
use ipl\Html\Html;
use ipl\Html\HtmlElement;
use ipl\Html\Text;
use ipl\Stdlib\Filter;
use ipl\Web\Widget\Link;
use ipl\Web\Widget\StateBall;

/**
 * Service item of a service list. Represents one database row.
 *
 * @property Service $item
 * @property ServiceList $list
 */
abstract class BaseServiceListItem extends StateListItem
{
    use NoSubjectLink;

    protected function createSubject()
    {
        $service = $this->item->display_name;
        if (isset($this->item->host->icon_image->icon_image)) {
            $hostIconImage = HtmlElement::create('div', [
                'class' => 'icon-image inline',
            ]);

            $hostIconImage->add(HtmlElement::create('img', [
                'src' => $this->item->host->icon_image->icon_image,
                'alt' => $this->item->host->icon_image_alt
            ]));

            $host = [
                new StateBall($this->item->host->state->getStateText(), StateBall::SIZE_MEDIUM),
                ' ',
                $hostIconImage,
                ' ',
                $this->item->host->display_name
            ];
        } else {
            $host = [
                new StateBall($this->item->host->state->getStateText(), StateBall::SIZE_MEDIUM),
                ' ',
                $this->item->host->display_name
            ];
        }

        $host = new Link($host, Links::host($this->item->host), ['class' => 'subject']);
        if ($this->getNoSubjectLink()) {
            $service = new HtmlElement('span', Attributes::create(['class' => 'subject']), Text::create($service));
        } else {
            $service = new Link($service, Links::service($this->item, $this->item->host), ['class' => 'subject']);
        }

        return [Html::sprintf(t('%s on %s', '<service> on <host>'), $service, $host)];
    }

    protected function init()
    {
        parent::init();

        if ($this->list->getNoSubjectLink()) {
            $this->setNoSubjectLink();
        }

        $this->list->addMultiselectFilterAttribute(
            $this,
            Filter::all(
                Filter::equal('service.name', $this->item->name),
                Filter::equal('host.name', $this->item->host->name)
            )
        );
        $this->list->addDetailFilterAttribute(
            $this,
            Filter::all(
                Filter::equal('name', $this->item->name),
                Filter::equal('host.name', $this->item->host->name)
            )
        );
    }
}
