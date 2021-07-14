<?php

/* Icinga DB Web | (c) 2020 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Icingadb\Controllers;

use GuzzleHttp\Psr7\ServerRequest;
use Icinga\Module\Icingadb\Common\CommandActions;
use Icinga\Module\Icingadb\Common\Links;
use Icinga\Module\Icingadb\Model\Service;
use Icinga\Module\Icingadb\Model\ServicestateSummary;
use Icinga\Module\Icingadb\Util\FeatureStatus;
use Icinga\Module\Icingadb\Web\Control\SearchBar\ObjectSuggestions;
use Icinga\Module\Icingadb\Web\Controller;
use Icinga\Module\Icingadb\Widget\Detail\MultiselectQuickActions;
use Icinga\Module\Icingadb\Widget\Detail\ObjectsDetail;
use Icinga\Module\Icingadb\Widget\ServiceList;
use Icinga\Module\Icingadb\Widget\ServiceStatusBar;
use Icinga\Module\Icingadb\Widget\ShowMore;
use Icinga\Module\Icingadb\Widget\ViewModeSwitcher;
use ipl\Stdlib\Filter;
use ipl\Web\Control\LimitControl;
use ipl\Web\Control\SortControl;
use ipl\Web\Filter\QueryString;
use ipl\Web\Url;

class ServicesController extends Controller
{
    use CommandActions;

    public function indexAction()
    {
        $this->setTitle(t('Services'));
        $compact = $this->view->compact;

        $db = $this->getDb();

        $services = Service::on($db)->with([
            'state',
            'host',
            'host.state'
        ]);

        $this->handleSearchRequest($services);

        $summary = null;
        if (! $compact) {
            $summary = ServicestateSummary::on($db)->with('state');
        }

        $limitControl = $this->createLimitControl();
        $paginationControl = $this->createPaginationControl($services);
        $sortControl = $this->createSortControl(
            $services,
            [
                'service.display_name, host.display_name' => t('Name'),
                'service.state.severity desc'             => t('Severity'),
                'service.state.soft_state'                => t('Current State'),
                'service.state.last_state_change desc'    => t('Last State Change'),
                'host.display_name, service.display_name' => t('Host')
            ]
        );
        $viewModeSwitcher = $this->createViewModeSwitcher($paginationControl, $limitControl);
        $searchBar = $this->createSearchBar($services, [
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

        $services->peekAhead($compact);

        $this->filter($services, $filter);
        if (! $compact) {
            $this->filter($summary, $filter);
            yield $this->export($services, $summary);
        } else {
            yield $this->export($services);
        }

        $this->addControl($paginationControl);
        $this->addControl($sortControl);
        $this->addControl($limitControl);
        $this->addControl($viewModeSwitcher);
        $this->addControl($searchBar);
        $continueWith = $this->createContinueWith(Links::servicesDetails(), $searchBar);

        $results = $services->execute();
        $serviceList = (new ServiceList($results))
            ->setViewMode($viewModeSwitcher->getViewMode());

        $this->addContent($serviceList);

        if ($compact) {
            $this->addContent(
                (new ShowMore($results, Url::fromRequest()->without(['showCompact', 'limit'])))
                    ->setAttribute('data-base-target', '_next')
                    ->setAttribute('title', sprintf(
                        t('Show all %d services'),
                        $services->count()
                    ))
            );
        } else {
            $this->addFooter((new ServiceStatusBar($summary->first()))->setBaseFilter($filter));
        }

        if (! $searchBar->hasBeenSubmitted() && $searchBar->hasBeenSent()) {
            $this->sendMultipartUpdate($continueWith);
        }

        $this->setAutorefreshInterval(10);
    }

    public function detailsAction()
    {
        $this->setTitle(t('Services'));

        $db = $this->getDb();

        $services = Service::on($db)->with([
            'state',
            'host',
            'host.state'
        ]);
        $summary = ServicestateSummary::on($db)->with(['state']);

        $this->filter($services);
        $this->filter($summary);

        $services->limit(3);
        $services->peekAhead();

        yield $this->export($services, $summary);

        $results = $services->execute();
        $summary = $summary->first();

        $downtimes = Service::on($db)->with(['downtime']);
        $downtimes->getWith()['service.downtime']->setJoinType('INNER');
        $this->filter($downtimes);
        $summary->downtimes_total = $downtimes->count();

        $comments = Service::on($db)->with(['comment']);
        $comments->getWith()['service.comment']->setJoinType('INNER');
        // TODO: This should be automatically done by the model/resolver and added as ON condition
        $comments->filter(Filter::equal('comment.object_type', 'service'));
        $this->filter($comments);
        $summary->comments_total = $comments->count();

        $this->addControl(
            (new ServiceList($results))
                ->setViewMode('minimal')
        );
        $this->addControl(new ShowMore(
            $results,
            Links::services()->setQueryString(QueryString::render($this->getFilter())),
            sprintf(t('Show all %d services'), $services->count())
        ));
        $this->addControl(
            (new MultiselectQuickActions('service', $summary))
                ->setBaseFilter($this->getFilter())
        );

        $this->addContent(
            (new ObjectsDetail('service', $summary))
                ->setBaseFilter($this->getFilter())
        );
    }

    public function completeAction()
    {
        $suggestions = new ObjectSuggestions();
        $suggestions->setModel(Service::class);
        $suggestions->forRequest(ServerRequest::fromGlobals());
        $this->getDocument()->add($suggestions);
    }

    public function searchEditorAction()
    {
        $editor = $this->createSearchEditor(Service::on($this->getDb()), [
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

        $services = Service::on($db)->with([
            'state',
            'host',
            'host.state'
        ]);

        switch ($this->getRequest()->getActionName()) {
            case 'acknowledge':
                $services->filter(Filter::equal('state.is_problem', 'y'))
                    ->filter(Filter::equal('state.is_acknowledged', 'n'));

                break;
        }

        $this->filter($services);

        return $services;
    }

    public function getCommandTargetsUrl()
    {
        return Links::servicesDetails()->setQueryString(QueryString::render($this->getFilter()));
    }

    protected function getFeatureStatus()
    {
        $summary = ServicestateSummary::on($this->getDb())->with(['state']);
        $this->filter($summary);

        return new FeatureStatus('service', $summary->first());
    }
}
