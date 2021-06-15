<?php

/* Icinga DB Web | (c) 2020 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Icingadb\Model;

use Icinga\Module\Icingadb\Model\Behavior\BoolCast;
use Icinga\Module\Icingadb\Model\Behavior\Timestamp;
use ipl\Orm\Behaviors;
use ipl\Orm\Model;
use ipl\Orm\Relations;

/**
 * Model for table `downtime_history`
 *
 * Please note that using this model will fetch history entries for decommissioned services. To avoid this,
 * the query needs a `downtime_history.service_id IS NULL OR downtime_history_service.id IS NOT NULL` where.
 */
class DowntimeHistory extends Model
{
    public function getTableName()
    {
        return 'downtime_history';
    }

    public function getKeyName()
    {
        return 'downtime_id';
    }

    public function getColumns()
    {
        return [
            'environment_id',
            'endpoint_id',
            'triggered_by_id',
            'object_type',
            'host_id',
            'service_id',
            'entry_time',
            'author',
            'cancelled_by',
            'comment',
            'is_flexible',
            'flexible_duration',
            'scheduled_start_time',
            'scheduled_end_time',
            'start_time',
            'end_time',
            'has_been_cancelled',
            'trigger_time',
            'cancel_time'
        ];
    }

    public function getMetaData()
    {
        return [
            'environment_id'        => t('Downtime Environment Id (History)'),
            'endpoint_id'           => t('Downtime Endpoint Id (History)'),
            'triggered_by_id'       => t('Downtime Triggered By Id (History)'),
            'object_type'           => t('Downtime Object Type (History)'),
            'host_id'               => t('Downtime Host Id (History)'),
            'service_id'            => t('Downtime Service Id (History)'),
            'entry_time'            => t('Downtime Entry Time (History)'),
            'author'                => t('Downtime Author (History)'),
            'cancelled_by'          => t('Downtime Cancelled By (History)'),
            'comment'               => t('Downtime Comment (History)'),
            'is_flexible'           => t('Downtime Is Flexible (History)'),
            'flexible_duration'     => t('Downtime Flexible Duration (History)'),
            'scheduled_start_time'  => t('Downtime Scheduled Start (History)'),
            'scheduled_end_time'    => t('Downtime Scheduled End (History)'),
            'start_time'            => t('Downtime Actual Start (History)'),
            'end_time'              => t('Downtime Actual End (History)'),
            'has_been_cancelled'    => t('Downtime Has Been Cancelled (History)'),
            'trigger_time'          => t('Downtime Trigger Time (History)'),
            'cancel_time'           => t('Downtime Cancel Time (History)')
        ];
    }

    public function createBehaviors(Behaviors $behaviors)
    {
        $behaviors->add(new BoolCast([
            'is_flexible',
            'has_been_cancelled'
        ]));

        $behaviors->add(new Timestamp([
            'entry_time',
            'scheduled_start_time',
            'scheduled_end_time',
            'start_time',
            'end_time',
            'trigger_time',
            'cancel_time'
        ]));
    }

    public function createRelations(Relations $relations)
    {
        // @TODO(el): Add relation for triggered_by_id
        $relations->belongsTo('endpoint', Endpoint::class);
        $relations->belongsTo('environment', Environment::class);
        $relations->belongsTo('history', History::class)
            ->setCandidateKey('downtime_id')
            ->setForeignKey('downtime_history_id');
        $relations->belongsTo('host', Host::class);
        $relations->belongsTo('service', Service::class)->setJoinType('LEFT');
    }
}
