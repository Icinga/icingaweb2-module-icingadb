<?php

/* Icinga DB Web | (c) 2020 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Icingadb\Widget;

use Icinga\Module\Icingadb\Common\NoSubjectLink;
use Icinga\Module\Icingadb\Common\ViewMode;

abstract class StateList extends BaseItemList
{
    use ViewMode;
    use NoSubjectLink;

    protected $hasIconImages = false;

    /**
     * @return bool
     */
    public function hasIconImages()
    {
        return $this->hasIconImages;
    }

    /**
     * @param bool $hasIconImages
     */
    public function setHasIconImages(bool $hasIconImages)
    {
        $this->hasIconImages = $hasIconImages;
    }

    protected function assemble()
    {
        $this->addAttributes(['class' => $this->getViewMode()]);

        parent::assemble();
    }
}
