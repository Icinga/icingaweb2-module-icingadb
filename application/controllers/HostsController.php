<?php

/* Icinga DB Web | (c) 2020 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Icingadb\Controllers;

use GuzzleHttp\Psr7\ServerRequest;
use Icinga\Module\Icingadb\Common\CommandActions;
use Icinga\Module\Icingadb\Common\Links;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Icingadb\Model\HoststateSummary;
use Icinga\Module\Icingadb\Util\FeatureStatus;
use Icinga\Module\Icingadb\Web\Control\SearchBar\ObjectSuggestions;
use Icinga\Module\Icingadb\Web\Controller;
use Icinga\Module\Icingadb\Widget\Detail\MultiselectQuickActions;
use Icinga\Module\Icingadb\Widget\Detail\ObjectsDetail;
use Icinga\Module\Icingadb\Widget\HostList;
use Icinga\Module\Icingadb\Widget\HostStatusBar;
use Icinga\Module\Icingadb\Widget\ShowMore;
use Icinga\Module\Icingadb\Widget\ViewModeSwitcher;
use ipl\Stdlib\Filter;
use ipl\Web\Control\LimitControl;
use ipl\Web\Control\SortControl;
use ipl\Web\Filter\QueryString;
use ipl\Web\Url;

class HostsController extends Controller
{
    use CommandActions;

    public function indexAction()
    {
        $this->setTitle(t('Hosts'));
        $compact = $this->view->compact;

        $db = $this->getDb();

        $hosts = Host::on($db)->with('state');

        $this->handleSearchRequest($hosts);

        $summary = null;
        if (! $compact) {
            $summary = HoststateSummary::on($db)->with('state');
        }

        $limitControl = $this->createLimitControl();
        $paginationControl = $this->createPaginationControl($hosts);
        $sortControl = $this->createSortControl(
            $hosts,
            [
                'host.display_name'                 => t('Name'),
                'host.state.severity desc'          => t('Severity'),
                'host.state.soft_state'             => t('Current State'),
                'host.state.last_state_change desc' => t('Last State Change')
            ]
        );
        $viewModeSwitcher = $this->createViewModeSwitcher($paginationControl, $limitControl);
        $searchBar = $this->createSearchBar($hosts, [
            $limitControl->getLimitParam(),
            $sortControl->getSortParam(),
            $viewModeSwitcher->getViewModeParam()
        ]);

        if ($searchBar->hasBeenSent() && ! $searchBar->isValid()) {
            if ($searchBar->hasBeenSubmitted()) {
                $filter = $this->getFilter();
            } else {
                $this->addControl($searchBar);
                $this->sendMultipartUpdate();
                return;
            }
        } else {
            $filter = $searchBar->getFilter();
        }

        $hosts->peekAhead($compact);

        $this->filter($hosts, $filter);
        if (isset($summary)) {
            $this->filter($summary, $filter);
            yield $this->export($hosts, $summary);
        } else {
            yield $this->export($hosts);
        }

        $this->addControl($paginationControl);
        $this->addControl($sortControl);
        $this->addControl($limitControl);
        $this->addControl($viewModeSwitcher);
        $this->addControl($searchBar);
        $continueWith = $this->createContinueWith(Links::hostsDetails(), $searchBar);

        $results = $hosts->execute();
        $hostList = (new HostList($results))
            ->setViewMode($viewModeSwitcher->getViewMode());

        $this->addContent($hostList);

        if ($compact) {
            $this->addContent(
                (new ShowMore($results, Url::fromRequest()->without(['showCompact', 'limit'])))
                    ->setAttribute('data-base-target', '_next')
                    ->setAttribute('title', sprintf(
                        t('Show all %d hosts'),
                        $hosts->count()
                    ))
            );
        } else {
            $this->addFooter((new HostStatusBar($summary->first()))->setBaseFilter($filter));
        }

        if (! $searchBar->hasBeenSubmitted() && $searchBar->hasBeenSent()) {
            $this->sendMultipartUpdate($continueWith);
        }

        $this->setAutorefreshInterval(10);
    }

    public function detailsAction()
    {
        $this->setTitle(t('Hosts'));

        $db = $this->getDb();

        $hosts = Host::on($db)->with('state');
        $summary = HoststateSummary::on($db)->with(['state']);

        $this->filter($hosts);
        $this->filter($summary);

        $hosts->limit(3);
        $hosts->peekAhead();

        yield $this->export($hosts, $summary);

        $results = $hosts->execute();
        $summary = $summary->first();

        $downtimes = Host::on($db)->with(['downtime']);
        $downtimes->getWith()['host.downtime']->setJoinType('INNER');
        $this->filter($downtimes);
        $summary->downtimes_total = $downtimes->count();

        $comments = Host::on($db)->with(['comment']);
        $comments->getWith()['host.comment']->setJoinType('INNER');
        // TODO: This should be automatically done by the model/resolver and added as ON condition
        $comments->filter(Filter::equal('comment.object_type', 'host'));
        $this->filter($comments);
        $summary->comments_total = $comments->count();

        $this->addControl(
            (new HostList($results))
                ->setViewMode('minimal')
        );
        $this->addControl(new ShowMore(
            $results,
            Links::hosts()->setQueryString(QueryString::render($this->getFilter())),
            sprintf(t('Show all %d hosts'), $hosts->count())
        ));
        $this->addControl(
            (new MultiselectQuickActions('host', $summary))
                ->setBaseFilter($this->getFilter())
        );

        $this->addContent(
            (new ObjectsDetail('host', $summary))
                ->setBaseFilter($this->getFilter())
        );
    }

    public function completeAction()
    {
        $suggestions = new ObjectSuggestions();
        $suggestions->setModel(Host::class);
        $suggestions->forRequest(ServerRequest::fromGlobals());
        $this->getDocument()->add($suggestions);
    }

    public function searchEditorAction()
    {
        $editor = $this->createSearchEditor(Host::on($this->getDb()), [
            LimitControl::DEFAULT_LIMIT_PARAM,
            SortControl::DEFAULT_SORT_PARAM,
            ViewModeSwitcher::DEFAULT_VIEW_MODE_PARAM
        ]);

        $this->getDocument()->add($editor);
        $this->setTitle(t('Adjust Filter'));
    }

    public function fetchCommandTargets()
    {
        $db = $this->getDb();

        $hosts = Host::on($db)->with('state');

        switch ($this->getRequest()->getActionName()) {
            case 'acknowledge':
                $hosts->filter(Filter::equal('state.is_problem', 'y'))
                    ->filter(Filter::equal('state.is_acknowledged', 'n'));

                break;
        }

        $this->filter($hosts);

        return $hosts;
    }

    public function getCommandTargetsUrl()
    {
        return Links::hostsDetails()->setQueryString(QueryString::render($this->getFilter()));
    }

    protected function getFeatureStatus()
    {
        $summary = HoststateSummary::on($this->getDb())->with(['state']);
        $this->filter($summary);

        return new FeatureStatus('host', $summary->first());
    }
}
