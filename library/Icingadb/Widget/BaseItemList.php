<?php

/* Icinga DB Web | (c) 2020 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Icingadb\Widget;

use Icinga\Module\Icingadb\Common\BaseFilter;
use Icinga\Module\Icingadb\Common\BaseTableRowItem;
use Icinga\Module\Icingadb\Common\DetailActions;
use InvalidArgumentException;
use ipl\Html\BaseHtmlElement;

/**
 * Base class for item lists
 *
 * @todo Move this to Icinga\Module\Icingadb\Common
 */
abstract class BaseItemList extends BaseHtmlElement
{
    use BaseFilter;
    use DetailActions;

    protected $baseAttributes = ['class' => 'item-list', 'data-base-target' => '_next'];

    /** @var iterable */
    protected $data;

    protected $tag = 'ul';

    /**
     * Create a new item  list
     *
     * @param iterable $data Data source of the list
     */
    public function __construct($data)
    {
        if (! is_iterable($data)) {
            throw new InvalidArgumentException('Data must be an array or an instance of Traversable');
        }

        $this->data = $data;

        $this->addAttributes($this->baseAttributes);

        $this->initializeDetailActions();
        $this->init();
    }

    abstract protected function getItemClass();

    /**
     * Initialize the item list
     *
     * If you want to adjust the item list after construction, override this method.
     */
    protected function init()
    {
    }

    protected function assemble()
    {
        $itemClass = $this->getItemClass();

        foreach ($this->data as $data) {
            /** @var BaseListItem|BaseTableRowItem $item */
            $item = new $itemClass($data, $this);

            $this->add($item);
        }

        if ($this->isEmpty()) {
            $this->setTag('div');
            $this->add(new EmptyState(t('No items found.')));
        }
    }
}
