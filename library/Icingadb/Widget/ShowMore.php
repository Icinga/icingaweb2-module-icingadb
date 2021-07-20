<?php

/* Icinga DB Web | (c) 2020 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Icingadb\Widget;

use ipl\Html\BaseHtmlElement;
use ipl\Orm\ResultSet;
use ipl\Web\Url;
use ipl\Web\Widget\ActionLink;

class ShowMore extends BaseHtmlElement
{
    protected $defaultAttributes = ['class' => 'show-more'];

    protected $tag = 'div';

    protected $resultSet;

    protected $url;

    protected $label;

    public function __construct(ResultSet $resultSet, Url $url, $label = null)
    {
        $this->label = $label;
        $this->resultSet = $resultSet;
        $this->url = $url;
    }

    public function setLabel($label)
    {
        $this->label = $label;

        return $this;
    }

    public function getLabel()
    {
        return $this->label ?: t('Show More');
    }

    public function renderUnwrapped()
    {
        if ($this->resultSet->hasMore()) {
            return parent::renderUnwrapped();
        }

        return '';
    }

    protected function assemble()
    {
        if ($this->resultSet->hasMore()) {
            $this->add(new ActionLink($this->getLabel(), $this->url));
        }
    }
}
