<?php

namespace Icinga\Module\Eagle\Widget;

use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use ipl\Web\Widget\Link;

class TagList extends BaseHtmlElement
{
    protected $content = [];

    protected $defaultAttributes = ['class' => 'tag-list'];

    protected $tag = 'div';

    public function addLink($content, $url)
    {
        $this->content[] = new Link($content, $url);

        return $this;
    }

    protected function assemble()
    {
        $this->add(Html::wrapEach($this->content, 'li'));
    }
}