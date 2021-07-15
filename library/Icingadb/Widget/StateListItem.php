<?php

/* Icinga DB Web | (c) 2020 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Icingadb\Widget;

use Icinga\Module\Icingadb\Common\Icons;
use Icinga\Module\Icingadb\Compat\CompatPluginOutput;
use Icinga\Module\Icingadb\Model\State;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use ipl\Html\HtmlElement;
use ipl\Html\Text;
use ipl\Web\Widget\Icon;
use ipl\Web\Widget\StateBall;

/**
 * Host or service item of a host or service list. Represents one database row.
 */
abstract class StateListItem extends BaseListItem
{
    /** @var State The state of the item */
    protected $state;

    protected function init()
    {
        $this->state = $this->item->state;

        if (isset($this->item->icon_image->icon_image)) {
            $this->list->setHasIconImages(true);
        }
    }

    abstract protected function createSubject();

    abstract protected function getStateBallSize();

    protected function assembleCaption(BaseHtmlElement $caption)
    {
        if ($this->state->soft_state === null && $this->state->output === null) {
            $caption->add(Text::create(t('Waiting for Icinga DB to synchronize the state.')));
        } else {
            if (empty($this->state->output)) {
                $pluginOutput = new EmptyState(t('Output unavailable.'));
            } else {
                $pluginOutput = CompatPluginOutput::getInstance()->render($this->state->output);
            }

            $caption->add($pluginOutput);
        }
    }

    protected function assembleIconImage(BaseHtmlElement $iconImage)
    {
        if (isset($this->item->icon_image->icon_image)) {
            $iconImage->add(HtmlElement::create('img', [
                'src' => $this->item->icon_image->icon_image,
                'alt' => $this->item->icon_image_alt
            ]));
        } else {
            $iconImage->addAttributes(['class' => 'placeholder']);
        }
    }

    protected function assembleTitle(BaseHtmlElement $title)
    {
        $title->add(Html::sprintf(
            t('%s is %s', '<hostname> is <state-text>'),
            $this->createSubject(),
            Html::tag('span', ['class' => 'state-text'], $this->state->getStateTextTranslated())
        ));
    }

    protected function assembleVisual(BaseHtmlElement $visual)
    {
        $stateBall = new StateBall($this->state->getStateText(), $this->getStateBallSize());

        if ($this->state->is_handled) {
            switch (true) {
                case $this->state->in_downtime:
                    $icon = Icons::IN_DOWNTIME;
                    break;
                case $this->state->is_acknowledged:
                    $icon = Icons::IS_ACKNOWLEDGED;
                    break;
                case $this->state->is_flapping:
                    $icon = Icons::IS_FLAPPING;
                    break;
                default:
                    $icon = Icons::HOST_DOWN;
            }

            $stateBall->add(new Icon($icon));
            $stateBall->getAttributes()->add('class', 'handled');
        }

        $visual->add($stateBall);
        if ($this->state->state_type === 'soft') {
            $visual->add(new CheckAttempt($this->state->attempt, $this->item->max_check_attempts));
        }
    }

    protected function createTimestamp()
    {
        if ($this->state->is_overdue) {
            $since = new TimeSince($this->state->next_update);
            $since->prepend(t('Overdue') . ' ');
            $since->prepend(new Icon(Icons::WARNING));
            return $since;
        } elseif ($this->state->last_state_change !== null) {
            return new TimeSince($this->state->last_state_change);
        }
    }

    protected function createIconImage()
    {
        $iconImage = HtmlElement::create('div', [
            'class' => 'icon-image',
        ]);

        $this->assembleIconImage($iconImage);

        return $iconImage;
    }

    protected function assemble()
    {
        if ($this->state->is_overdue) {
            $this->addAttributes(['class' => 'overdue']);
        }

        $this->add($this->createVisual());
        if ($this->list->hasIconImages()) {
            $this->add($this->createIconImage());
        }
        $this->add($this->createMain());
    }
}
