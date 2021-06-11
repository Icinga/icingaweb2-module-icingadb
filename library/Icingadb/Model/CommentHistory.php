<?php

/* Icinga DB Web | (c) 2020 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Icingadb\Model;

use Icinga\Module\Icingadb\Model\Behavior\BoolCast;
use Icinga\Module\Icingadb\Model\Behavior\Timestamp;
use ipl\Orm\Behaviors;
use ipl\Orm\Model;
use ipl\Orm\Relations;

/**
 * Model for table `comment_history`
 *
 * Please note that using this model will fetch history entries for decommissioned services. To avoid this,
 * the query needs a `comment_history.service_id IS NULL OR comment_history_service.id IS NOT NULL` where.
 */
class CommentHistory extends Model
{
    public function getTableName()
    {
        return 'comment_history';
    }

    public function getKeyName()
    {
        return 'comment_id';
    }

    public function getColumns()
    {
        return [
            'environment_id',
            'endpoint_id',
            'object_type',
            'host_id',
            'service_id',
            'entry_time',
            'author',
            'removed_by',
            'comment',
            'entry_type',
            'is_persistent',
            'is_sticky',
            'expire_time',
            'remove_time',
            'has_been_removed'
        ];
    }

    public function getMetaData()
    {
        return [
            'environment_id'    => t('Comment Environment Id (History)'),
            'endpoint_id'       => t('Comment Endpoint Id (History)'),
            'object_type'       => t('Comment Object Type (History)'),
            'host_id'           => t('Comment Host Id (History)'),
            'service_id'        => t('Comment Service Id (History)'),
            'entry_time'        => t('Comment Entry Time (History)'),
            'author'            => t('Comment Author (History)'),
            'removed_by'        => t('Comment Removed By (History)'),
            'comment'           => t('Comment Comment (History)'),
            'entry_type'        => t('Comment Entry Type (History)'),
            'is_persistent'     => t('Comment Is Persistent (History)'),
            'is_sticky'         => t('Comment Is Sticky (History)'),
            'expire_time'       => t('Comment Expire Time (History)'),
            'remove_time'       => t('Comment Remove Time (History)'),
            'has_been_removed'  => t('Comment Has Been Removed (History)')
        ];
    }

    public function createBehaviors(Behaviors $behaviors)
    {
        $behaviors->add(new BoolCast([
            'is_persistent',
            'is_sticky',
            'has_been_removed'
        ]));

        $behaviors->add(new Timestamp([
            'entry_time',
            'expire_time',
            'remove_time'
        ]));
    }

    public function createRelations(Relations $relations)
    {
        $relations->belongsTo('endpoint', Endpoint::class);
        $relations->belongsTo('environment', Environment::class);
        $relations->belongsTo('history', History::class)
            ->setCandidateKey('comment_id')
            ->setForeignKey('comment_history_id');
        $relations->belongsTo('host', Host::class);
        $relations->belongsTo('service', Service::class)->setJoinType('LEFT');
    }
}
