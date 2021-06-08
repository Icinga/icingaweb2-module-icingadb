<?php

/* Icinga DB Web | (c) 2021 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Icingadb\Widget\Detail;

use Icinga\Module\Icingadb\Common\Auth;
use Icinga\Module\Icingadb\Common\Links;
use Icinga\Module\Icingadb\Model\History;
use Icinga\Module\Icingadb\Model\NotificationHistory;
use Icinga\Module\Icingadb\Widget\EmptyState;
use Icinga\Module\Icingadb\Widget\ItemList\UserList;
use Icinga\Module\Icingadb\Widget\ShowMore;
use ipl\Html\BaseHtmlElement;
use ipl\Html\FormattedString;
use ipl\Html\HtmlElement;
use ipl\Orm\ResultSet;
use ipl\Stdlib\Str;
use ipl\Web\Widget\Icon;
use ipl\Web\Widget\StateBall;

class EventDetail extends BaseHtmlElement
{
    use Auth;

    protected $tag = 'div';

    protected $defaultAttributes = ['class' => 'event-detail'];

    /** @var History */
    protected $event;

    public function __construct(History $event)
    {
        $this->event = $event;
    }

    protected function assembleNotificationEvent(NotificationHistory $notification)
    {
        $this->add([
            new HtmlElement('h2', null, $notification->author ? t('Comment') : t('Plugin Output')),
            new HtmlElement('div', [
                'id'    => 'check-output-' . (
                    $notification->object_type === 'host'
                        ? $this->event->host->checkcommand
                        : $this->event->service->checkcommand
                ),
                'class' => 'collapsible',
                'data-visible-height' => 100
            ], CompatPluginOutput::getInstance()->render($notification->text))
        ]);

        if ($notification->object_type === 'host') {
            $objectKey = t('Host');
            $objectInfo = new HtmlElement('span', ['class' => 'accompanying-text'], [
                new HtmlElement('span', ['class' => 'state-change'], [
                    new StateBall(HostStates::text($notification->previous_hard_state), StateBall::SIZE_MEDIUM),
                    new StateBall(HostStates::text($notification->state), StateBall::SIZE_MEDIUM)
                ]),
                ' ',
                new HtmlElement('span', ['class' => 'subject'], $this->event->host->display_name)
            ]);
        } else {
            $objectKey = t('Service');
            $objectInfo = new HtmlElement('span', ['class' => 'accompanying-text'], [
                new HtmlElement('span', ['class' => 'state-change'], [
                    new StateBall(ServiceStates::text($notification->previous_hard_state), StateBall::SIZE_MEDIUM),
                    new StateBall(ServiceStates::text($notification->state), StateBall::SIZE_MEDIUM)
                ]),
                ' ',
                FormattedString::create(
                    t('%s on %s', '<service> on <host>'),
                    new HtmlElement('span', ['class' => 'subject'], $this->event->service->display_name),
                    new HtmlElement('span', ['class' => 'subject'], $this->event->host->display_name)
                )
            ]);
        }

        $this->add([
            new HtmlElement('h2', null, t('Event Info')),
            new HorizontalKeyValue(t('Sent On'), DateFormatter::formatDateTime($notification->send_time)),
            $notification->author ? new HorizontalKeyValue(t('Sent by'), [
                new Icon('user'),
                $notification->author
            ]) : null,
            new HorizontalKeyValue(t('Type'), ucfirst(Str::camel($notification->type))),
            new HorizontalKeyValue($objectKey, $objectInfo)
        ]);

        $this->add(new HtmlElement('h2', null, t('Notified Users')));
        if ($notification->users_notified === 0) {
            $this->add(new EmptyState(t('None', 'notified users: none')));
        } elseif (! $this->isPermittedRoute('users')) {
            $this->add(sprintf(tp(
                'This notification received a single user',
                'This notification received %d users',
                $notification->users_notified
            ), $notification->users_notified));
        } elseif ($notification->users_notified > 0) {
            $users = $notification->user
                ->limit(5)
                ->peekAhead();

            $users = $users->execute();
            /** @var ResultSet $users */

            $this->add([
                new UserList($users),
                (new ShowMore($users, Links::users()->addParams([
                        'notification_history.id' => bin2hex($notification->id)
                    ]), sprintf(t('Show all %d recipients'), $notification->users_notified)))
                    ->setBaseTarget('_next')
            ]);
        }
    }

    protected function assemble()
    {
        switch ($this->event->event_type) {
            case 'notification':
                $this->assembleNotificationEvent($this->event->notification);

                break;
            case 'state_change':
            case 'downtime_start':
            case 'downtime_end':
            case 'comment_add':
            case 'comment_remove':
            case 'flapping_start':
            case 'flapping_end':
            case 'ack_set':
            case 'ack_clear':
        }
    }
}
