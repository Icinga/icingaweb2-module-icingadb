<?php

/* Icinga DB Web | (c) 2020 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Icingadb\Common;

use ipl\Html\BaseHtmlElement;

trait ListItemCommonLayout
{
    use CaptionDisabled;

    protected function assembleHeader(BaseHtmlElement $header)
    {
        $header->add($this->createTitle());
        $header->add($this->createTimestamp());
    }

    protected function assembleMain(BaseHtmlElement $main)
    {
        $main->add($this->createHeader());
        if (! $this->isCaptionDisabled()) {
            $main->add($this->createCaption());
        }
    }
}
