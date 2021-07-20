<?php

/* Icinga DB Web | (c) 2020 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Icingadb\Widget\ItemList;

use Icinga\Date\DateFormatter;
use Icinga\Module\Icingadb\Common\HostLink;
use Icinga\Module\Icingadb\Common\Icons;
use Icinga\Module\Icingadb\Common\Links;
use Icinga\Module\Icingadb\Common\MarkdownLine;
use Icinga\Module\Icingadb\Common\NoSubjectLink;
use Icinga\Module\Icingadb\Common\ObjectLinkDisabled;
use Icinga\Module\Icingadb\Common\ServiceLink;
use Icinga\Module\Icingadb\Model\Downtime;
use Icinga\Module\Icingadb\Widget\BaseListItem;
use Icinga\Module\Icingadb\Widget\DowntimeList;
use ipl\Html\Attributes;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use ipl\Html\HtmlElement;
use ipl\Html\TemplateString;
use ipl\Html\Text;
use ipl\Stdlib\Filter;
use ipl\Web\Widget\Icon;
use ipl\Web\Widget\Link;

/**
 * Downtime item of a downtime list. Represents one database row.
 *
 * @property Downtime $item
 * @property DowntimeList $list
 */
abstract class BaseDowntimeListItem extends BaseListItem
{
    use HostLink;
    use ServiceLink;
    use NoSubjectLink;
    use ObjectLinkDisabled;

    /** @var int Current Time */
    protected $currentTime;

    /** @var int Duration */
    protected $duration;

    /** @var int Downtime end time */
    protected $endTime;

    /** @var bool Whether the downtime is active */
    protected $isActive;

    /** @var int Downtime start time */
    protected $startTime;

    protected function init()
    {
        if ($this->item->is_flexible && $this->item->is_in_effect) {
            $this->startTime = $this->item->start_time;
        } else {
            $this->startTime = $this->item->scheduled_start_time;
        }

        if ($this->item->is_flexible && $this->item->is_in_effect) {
//            $this->endTime = $this->item->end_time;
            $this->endTime = $this->item->start_time + $this->item->flexible_duration;
        } else {
            $this->endTime = $this->item->scheduled_end_time;
        }

        $this->currentTime = time();

        $this->isActive = $this->item->is_in_effect
            || $this->item->is_flexible && $this->item->scheduled_start_time <= $this->currentTime;

        $until = ($this->isActive ? $this->endTime : $this->startTime) - $this->currentTime;
        $this->duration = explode(' ', DateFormatter::formatDuration(
            $until <= 3600 ? $until : $until + (3600 - ($until % 3600))
        ), 2)[0];

        $this->setMultiselectFilter(Filter::equal('name', $this->item->name));
        $this->setDetailFilter(Filter::equal('name', $this->item->name));
        $this->setObjectLinkDisabled($this->list->getObjectLinkDisabled());
        $this->setNoSubjectLink($this->list->getNoSubjectLink());
    }

    protected function createProgress()
    {
        $ref = floor(
            (float) ($this->currentTime - $this->startTime)
            / (float) ($this->endTime - $this->startTime)
            * 100
        );

        $progress = Html::tag(
            'div',
            ['class' => 'progress'],
            Html::tag(
                'div',
                [
                    'class' => 'progress-bar',
                    'style' => 'width: ' . $ref . '%'
                ]
            )
        );

        return $progress;
    }

    protected function assembleCaption(BaseHtmlElement $caption)
    {
        $markdownLine = new MarkdownLine($this->item->comment);
        $caption->getAttributes()->add($markdownLine->getAttributes());
        $caption->addHtml(
            new HtmlElement(
                'span',
                null,
                new Icon(Icons::USER),
                Text::create($this->item->author)
            ),
            Text::create(': ')
        )->addFrom($markdownLine);
    }

    protected function assembleTitle(BaseHtmlElement $title)
    {
        if ($this->getObjectLinkDisabled()) {
            $link = null;
        } elseif ($this->item->object_type === 'host') {
            $link = $this->createHostLink($this->item->host, true);
        } else {
            $link = $this->createServiceLink($this->item->service, $this->item->service->host, true);
        }

        if ($this->item->is_flexible) {
            if ($link !== null) {
                $template = t('{{#link}}Flexible Downtime{{/link}} for %s');
            } else {
                $template = t('{{#link}}Flexible Downtime{{/link}}');
            }
        } else {
            if ($link !== null) {
                $template = t('{{#link}}Fixed Downtime{{/link}} for %s');
            } else {
                $template = t('{{#link}}Fixed Downtime{{/link}}');
            }
        }

        if ($this->getNoSubjectLink()) {
            $title->addHtml(TemplateString::create(
                $template,
                ['link' => HtmlElement::create('span', [ 'class' => 'subject'])],
                $link
            ));
        } else {
            $title->addHtml(TemplateString::create(
                $template,
                ['link' => new Link('', Links::downtime($this->item))],
                $link
            ));
        }
    }

    protected function assembleVisual(BaseHtmlElement $visual)
    {
        $dateTime = DateFormatter::formatDateTime($this->endTime);

        if ($this->isActive) {
            if ($this->item->is_in_effect) {
                $visual->addAttributes(['class' => 'active']);
            }

            $visual->addHtml(Html::sprintf(
                t('%s left', '<timespan>..'),
                Html::tag(
                    'strong',
                    Html::tag(
                        'time',
                        [
                            'datetime' => $dateTime,
                            'title'    => $dateTime
                        ],
                        $this->duration
                    )
                )
            ));
        } else {
            $visual->addHtml(Html::sprintf(
                t('in %s', '..<timespan>'),
                Html::tag('strong', $this->duration)
            ));
        }
    }

    protected function createTimestamp()
    {
        $dateTime = DateFormatter::formatDateTime($this->endTime);

        return Html::tag(
            'time',
            [
                'datetime' => $dateTime,
                'title'    => $dateTime
            ],
            sprintf(
                $this->isActive
                   ? t('expires in %s', '..<timespan>')
                   : t('starts in %s', '..<timespan>'),
                $this->duration
            )
        );
    }
}
