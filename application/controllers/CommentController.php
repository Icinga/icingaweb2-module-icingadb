<?php

/* Icinga DB Web | (c) 2020 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Icingadb\Controllers;

use Icinga\Exception\NotFoundError;
use Icinga\Module\Icingadb\Common\CommandActions;
use Icinga\Module\Icingadb\Common\Links;
use Icinga\Module\Icingadb\Model\Comment;
use Icinga\Module\Icingadb\Web\Controller;
use Icinga\Module\Icingadb\Widget\Detail\CommentDetail;
use Icinga\Module\Icingadb\Widget\ItemList\CommentList;

class CommentController extends Controller
{
    use CommandActions;

    /** @var Comment The comment object */
    protected $comment;

    public function init()
    {
        $this->setTitle(t('Comment'));

        $name = $this->params->shiftRequired('name');

        $query = Comment::on($this->getDb())->with([
            'host',
            'host.state',
            'service',
            'service.state',
            'service.host',
            'service.host.state'
        ]);

        $query->getSelectBase()
            ->where(['comment.name = ?' => $name]);

        $this->applyRestrictions($query);

        $comment = $query->first();
        if ($comment === null) {
            throw new NotFoundError(t('Comment not found'));
        }

        $this->comment = $comment;
    }

    public function indexAction()
    {
        $this->addControl((new CommentList([$this->comment]))
            ->setViewMode('minimal')
            ->setDetailActionsDisabled()
            ->setCaptionDisabled()
            ->setNoSubjectLink());

        $this->addContent(new CommentDetail($this->comment));

        $this->setAutorefreshInterval(10);
    }

    protected function fetchCommandTargets()
    {
        return [$this->comment];
    }

    protected function getCommandTargetsUrl()
    {
        return Links::comment($this->comment);
    }
}
