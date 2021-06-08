<?php

/* Icinga DB Web | (c) 2020 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Icingadb\Model;

use Icinga\Module\Icingadb\Model\Behavior\IdKey;
use Icinga\Module\Icingadb\Model\Behavior\ReRoute;
use Icinga\Module\Icingadb\Model\Behavior\Timestamp;
use ipl\Orm\Behaviors;
use ipl\Orm\Model;
use ipl\Orm\Relations;

/**
 * Model for table `notification_history`
 *
 * Please note that using this model will fetch history entries for decommissioned services. To avoid this, the
 * query needs a `notification_history.service_id IS NULL OR notification_history_service.id IS NOT NULL` where.
 */
class NotificationHistory extends Model
{
    public function getTableName()
    {
        return 'notification_history';
    }

    public function getKeyName()
    {
        return 'id';
    }

    public function getColumns()
    {
        return [
            'environment_id',
            'endpoint_id',
            'object_type',
            'host_id',
            'service_id',
            'notification_id',
            'type',
            'send_time',
            'state',
            'previous_hard_state',
            'author',
            'text',
            'users_notified'
        ];
    }

    public function getMetaData()
    {
        return [
            'id'                    => t('Notification History Id'),
            'environment_id'        => t('Notification Environment Id (History)'),
            'endpoint_id'           => t('Notification Endpoint Id (History)'),
            'object_type'           => t('Notification Object Type (History)'),
            'host_id'               => t('Notification Host Id (History)'),
            'service_id'            => t('Notification Service Id (History)'),
            'notification_id'       => t('Notification Id (History)'),
            'type'                  => t('Notification Type (History)'),
            'send_time'             => t('Notification Sent On (History)'),
            'state'                 => t('Notification Object State (History)'),
            'previous_hard_state'   => t('Notification Previous Object State (History)'),
            'author'                => t('Notification Author (History)'),
            'text'                  => t('Notification Text (History)'),
            'users_notified'        => t('Notification Users Notified (History)')
        ];
    }

    public function getSearchColumns()
    {
        return ['text'];
    }

    public function getDefaultSort()
    {
        return 'notification_history.send_time desc';
    }

    public function createBehaviors(Behaviors $behaviors)
    {
        $behaviors->add(new IdKey());
        $behaviors->add(new Timestamp([
            'send_time'
        ]));
        $behaviors->add(new ReRoute([
            'hostgroup'     => 'host.hostgroup',
            'servicegroup'  => 'service.servicegroup'
        ]));
    }

    public function createRelations(Relations $relations)
    {
        $relations->belongsTo('environment', Environment::class);
        $relations->belongsTo('history', History::class)
            ->setCandidateKey('id')
            ->setForeignKey('notification_history_id');
        $relations->belongsTo('host', Host::class);
        $relations->belongsTo('service', Service::class)->setJoinType('LEFT');

        $relations->belongsToMany('user', User::class)
            ->through('user_notification_history');
    }
}
