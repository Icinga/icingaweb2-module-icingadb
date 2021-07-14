<?php

/* Icinga DB Web | (c) 2020 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Icingadb\Web;

use Generator;
use GuzzleHttp\Psr7\ServerRequest;
use Icinga\Application\Icinga;
use Icinga\Module\Icingadb\Common\Auth;
use Icinga\Module\Icingadb\Common\Database;
use Icinga\Module\Icingadb\Web\Control\SearchBar\ObjectSuggestions;
use Icinga\Module\Icingadb\Widget\BaseItemList;
use Icinga\Module\Icingadb\Widget\ViewModeSwitcher;
use Icinga\Security\SecurityException;
use InvalidArgumentException;
use ipl\Html\Html;
use ipl\Html\ValidHtml;
use ipl\Orm\Common\SortUtil;
use ipl\Orm\Query;
use ipl\Orm\UnionQuery;
use ipl\Stdlib\Contract\Paginatable;
use ipl\Stdlib\Filter;
use ipl\Web\Compat\CompatController;
use ipl\Web\Control\LimitControl;
use ipl\Web\Control\PaginationControl;
use ipl\Web\Control\SearchBar;
use ipl\Web\Control\SearchEditor;
use ipl\Web\Control\SortControl;
use ipl\Web\Filter\QueryString;
use ipl\Web\Url;
use ipl\Web\Widget\ContinueWith;

class Controller extends CompatController
{
    use Auth;
    use Database;

    /** @var Filter Filter from query string parameters */
    private $filter;

    /** @var string|null */
    private $format;

    /** @var bool */
    private $formatProcessed = false;

    /**
     * Get the filter created from query string parameters
     *
     * @return Filter\Rule
     */
    public function getFilter()
    {
        if ($this->filter === null) {
            $this->filter = QueryString::parse((string) $this->params);
        }

        return $this->filter;
    }

    /**
     * Create and return the LimitControl
     *
     * This automatically shifts the limit URL parameter from {@link $params}.
     *
     * @return LimitControl
     */
    public function createLimitControl()
    {
        $limitControl = new LimitControl(Url::fromRequest());
        $limitControl->setDefaultLimit($this->getPageSize(null));

        $this->params->shift($limitControl->getLimitParam());

        return $limitControl;
    }

    /**
     * Create and return the PaginationControl
     *
     * This automatically shifts the pagination URL parameters from {@link $params}.
     *
     * @return PaginationControl
     */
    public function createPaginationControl(Paginatable $paginatable)
    {
        $paginationControl = new PaginationControl($paginatable, Url::fromRequest());
        $paginationControl->setDefaultPageSize($this->getPageSize(null));
        $paginationControl->setAttribute('id', $this->getRequest()->protectId('pagination-control'));

        $this->params->shift($paginationControl->getPageParam());
        $this->params->shift($paginationControl->getPageSizeParam());

        return $paginationControl->apply();
    }

    /**
     * Create and return the SortControl
     *
     * This automatically shifts the sort URL parameter from {@link $params}.
     *
     * @param Query $query
     * @param array $columns Possible sort columns as sort string-label pairs
     *
     * @return SortControl
     */
    public function createSortControl(Query $query, array $columns)
    {
        $default = (array) $query->getModel()->getDefaultSort();
        $normalized = [];
        foreach ($columns as $key => $value) {
            $normalized[SortUtil::normalizeSortSpec($key)] = $value;
        }
        $sortControl = (new SortControl(Url::fromRequest()))
            ->setColumns($normalized);

        if (! empty($default)) {
            $sortControl->setDefault(SortUtil::normalizeSortSpec($default));
        }

        $sort = $sortControl->getSort();

        if (! empty($sort)) {
            $query->orderBy(SortUtil::createOrderBy($sort));
        }

        $this->params->shift($sortControl->getSortParam());

        return $sortControl;
    }

    /**
     * Create and return the SearchBar
     *
     * @param Query $query The query being filtered
     * @param array $preserveParams Query params to preserve when redirecting
     *
     * @return SearchBar
     */
    public function createSearchBar(Query $query, array $preserveParams = null)
    {
        $requestUrl = Url::fromRequest();
        $redirectUrl = $preserveParams !== null ? $requestUrl->onlyWith($preserveParams) : $requestUrl;

        $filter = QueryString::fromString((string) $this->params)
            ->on(QueryString::ON_CONDITION, function (Filter\Condition $condition) use ($query) {
                $this->enrichFilterCondition($condition, $query);
            })
            ->parse();

        $searchBar = new SearchBar();
        $searchBar->setFilter($filter);
        $searchBar->setAction($requestUrl->getAbsoluteUrl());
        $searchBar->setIdProtector([$this->getRequest(), 'protectId']);

        if (method_exists($this, 'completeAction')) {
            $searchBar->setSuggestionUrl(Url::fromPath(
                'icingadb/' . $this->getRequest()->getControllerName() . '/complete',
                ['_disableLayout' => true, 'showCompact' => true]
            ));
        }

        if (method_exists($this, 'searchEditorAction')) {
            $searchBar->setEditorUrl(Url::fromPath(
                'icingadb/' . $this->getRequest()->getControllerName() . '/search-editor'
            )->setParams($redirectUrl->getParams()));
        }

        $searchBar->on(SearchBar::ON_CHANGE, function (array &$changes) use ($query) {
            if ($changes['type'] === 'remove') {
                return;
            }

            $metaData = iterator_to_array(
                ObjectSuggestions::collectFilterColumns($query->getModel(), $query->getResolver())
            );
            foreach ($changes['terms'] as &$termData) {
                if (($pos = strpos($termData['search'], '.vars.')) !== false) {
                    try {
                        $query->getResolver()->resolveRelation(substr($termData['search'], 0, $pos + 5));
                    } catch (InvalidArgumentException $_) {
                        $termData['invalidMsg'] = sprintf(
                            t('"%s" is not a valid relation'),
                            substr($termData['search'], 0, $pos)
                        );
                    }
                } elseif ($termData['type'] === 'column') {
                    $column = $termData['search'];
                    if (strpos($column, '.') === false) {
                        $column = $query->getResolver()->qualifyPath($column, $query->getModel()->getTableName());
                        // TODO: Also apply this change, though not until.. (see below)
                    }

                    if (! isset($metaData[$column])) {
                        // TODO: Enable once https://github.com/Icinga/ipl-stdlib/issues/17 is done and
                        //       used by the search bar so that not every "change" makes it invalid
                        $path = false; //array_search($column, $metaData, true);
                        if ($path === false) {
                            $termData['invalidMsg'] = t('Is not a valid column');
                        } else {
                            $termData['search'] = $path;
                        }
                    }
                }
            }
        })->on(SearchBar::ON_SENT, function (SearchBar $form) use ($redirectUrl) {
            $existingParams = $redirectUrl->getParams();
            $redirectUrl->setQueryString(QueryString::render($form->getFilter()));
            foreach ($existingParams->toArray(false) as $name => $value) {
                if (is_int($name)) {
                    $name = $value;
                    $value = true;
                }

                $redirectUrl->getParams()->addEncoded($name, $value);
            }

            $form->setRedirectUrl($redirectUrl);
        })->on(SearchBar::ON_SUCCESS, function (SearchBar $form) {
            $this->getResponse()->redirectAndExit($form->getRedirectUrl());
        })->handleRequest(ServerRequest::fromGlobals());

        Html::tag('div', ['class' => 'filter'])->wrap($searchBar);

        return $searchBar;
    }

    /**
     * Create and return the SearchEditor
     *
     * @param Query $query The query being filtered
     * @param array $preserveParams Query params to preserve when redirecting
     *
     * @return SearchEditor
     */
    public function createSearchEditor(Query $query, array $preserveParams = null)
    {
        $requestUrl = Url::fromRequest();
        $redirectUrl = Url::fromPath('icingadb/' . $this->getRequest()->getControllerName());
        if (! empty($preserveParams)) {
            $redirectUrl->setParams($requestUrl->onlyWith($preserveParams)->getParams());
        }

        $editor = new SearchEditor();
        $editor->setQueryString((string) $this->params->without($preserveParams));
        $editor->setAction($requestUrl->getAbsoluteUrl());

        if (method_exists($this, 'completeAction')) {
            $editor->setSuggestionUrl(Url::fromPath(
                'icingadb/' . $this->getRequest()->getControllerName() . '/complete',
                ['_disableLayout' => true, 'showCompact' => true]
            ));
        }

        $editor->getParser()->on(QueryString::ON_CONDITION, function (Filter\Condition $condition) use ($query) {
            if ($condition->getColumn()) {
                $this->enrichFilterCondition($condition, $query);
            }
        });

        $metaData = iterator_to_array(
            ObjectSuggestions::collectFilterColumns($query->getModel(), $query->getResolver())
        );
        $editor->on(SearchEditor::ON_VALIDATE_COLUMN, function (Filter\Condition $condition) use ($query, $metaData) {
            $column = $condition->getColumn();
            if (($pos = strpos($column, '.vars.')) !== false) {
                try {
                    $query->getResolver()->resolveRelation(substr($column, 0, $pos + 5));
                } catch (InvalidArgumentException $_) {
                    throw new SearchBar\SearchException(sprintf(
                        t('"%s" is not a valid relation'),
                        substr($column, 0, $pos)
                    ));
                }
            } else {
                if (! isset($metaData[$column])) {
                    $path = array_search(
                        $condition->metaData()->get('columnLabel', $column),
                        $metaData,
                        true
                    );
                    if ($path === false) {
                        throw new SearchBar\SearchException(t('Is not a valid column'));
                    } else {
                        $condition->setColumn($path);
                    }
                }
            }
        })->on(SearchEditor::ON_SUCCESS, function (SearchEditor $form) use ($redirectUrl) {
            $existingParams = $redirectUrl->getParams();
            $redirectUrl->setQueryString(QueryString::render($form->getFilter()));
            foreach ($existingParams->toArray(false) as $name => $value) {
                if (is_int($name)) {
                    $name = $value;
                    $value = true;
                }

                $redirectUrl->getParams()->addEncoded($name, $value);
            }

            $this->getResponse()
                ->setHeader('X-Icinga-Container', '_self')
                ->redirectAndExit($redirectUrl);
        })->handleRequest(ServerRequest::fromGlobals());

        return $editor;
    }

    /**
     * Create and return a ContinueWith
     *
     * This will automatically be appended to the SearchBar's wrapper. It's not necessary
     * to add it separately as control or content!
     *
     * @param Url $detailsUrl
     * @param SearchBar $searchBar
     *
     * @return ContinueWith
     */
    public function createContinueWith(Url $detailsUrl, SearchBar $searchBar)
    {
        $continueWith = new ContinueWith($detailsUrl, [$searchBar, 'getFilter']);
        $continueWith->setBaseTarget('_next');
        $continueWith->getAttributes()
            ->set('id', $this->getRequest()->protectId('continue-with'))
            ->set('title', t('Show bulk processing actions for all filtered results'));

        $searchBar->getWrapper()->add($continueWith);

        return $continueWith;
    }

    /**
     * Create and return the ViewModeSwitcher
     *
     * This automatically shifts the view mode URL parameter from {@link $params}.
     *
     * @return ViewModeSwitcher
     */
    public function createViewModeSwitcher(PaginationControl $paginationControl, LimitControl $limitControl)
    {
        $viewModeSwitcher = new ViewModeSwitcher();
        $viewModeSwitcher->setIdProtector([$this->getRequest(), 'protectId']);

        $prefs = $this->Auth()->getUser()->getPreferences();
        $viewMode = $prefs->getValue('icingadb', 'view_mode');
        if (isset($viewMode)) {
            $viewModeSwitcher->setDefaultViewMode($viewMode);
        }

        $viewModeSwitcher->populate([
            'view' => $this->params->shift($viewModeSwitcher->getViewModeParam())
        ]);

        $session = $this->Window()->getSessionNamespace(
            'icingadb-viewmode-' . $this->Window()->getContainerId()
        );

        $viewModeSwitcher->on(
            ViewModeSwitcher::ON_SUCCESS,
            function (ViewModeSwitcher $viewModeSwitcher) use ($prefs, $paginationControl, &$session) {
                $viewMode = $viewModeSwitcher->getValue($viewModeSwitcher->getViewModeParam());
                $icingadbPrefs = $prefs->icingadb ?: [];
                $icingadbPrefs['view_mode'] = $viewMode;
                $prefs->icingadb = $icingadbPrefs;

                $currentPage = $paginationControl->getCurrentPageNumber();

                $requestUrl = Url::fromRequest();
                $pageParam = $paginationControl->getPageParam();
                $limitParam = LimitControl::DEFAULT_LIMIT_PARAM;
                $urlParams[$viewModeSwitcher->getViewModeParam()] = $viewMode;

                if (! $requestUrl->hasParam($limitParam)) {
                    if ($viewMode === 'minimal') {
                        $session->set('previous_page', $currentPage);
                        $session->set('request_path', $requestUrl->getPath());

                        $limit = $paginationControl->getLimit();
                        $currentPage = (int) (floor((($currentPage * $limit) - $limit) / ($limit * 2)) + 1);

                        $session->set('current_page', $currentPage);
                    } elseif ($viewModeSwitcher->getDefaultViewMode() === 'minimal') {
                        if ($currentPage === $session->get('current_page')) {
                            // No other page numbers have been selected, i.e the user only
                            // switches back and forth without changing the page numbers
                            $currentPage =  $session->get('previous_page');
                        } else {
                            $limit = $paginationControl->getLimit();
                            $currentPage = (int) (floor((($currentPage * $limit) - $limit) / ($limit / 2)) + 1);
                        }

                        $session->clear();
                    }

                    if (($requestUrl->hasParam($pageParam) && $currentPage > 1) || $currentPage > 1) {
                        $urlParams[$pageParam] = $currentPage;
                    }
                } else {
                    $urlParams[$limitParam] = $requestUrl->getParam($limitParam);
                }

                $this->redirectNow($requestUrl->setParams($urlParams));
            }
        )->handleRequest(ServerRequest::fromGlobals());

        if ($viewModeSwitcher->getViewMode() === 'minimal') {
            $hasLimitParam = Url::fromRequest()->hasParam($limitControl->getLimitParam());

            if ($paginationControl->getDefaultPageSize() <= LimitControl::DEFAULT_LIMIT && ! $hasLimitParam) {
                $paginationControl->setDefaultPageSize($paginationControl->getDefaultPageSize() * 2);
                $limitControl->setDefaultLimit($limitControl->getDefaultLimit() * 2);

                $paginationControl->apply();
            }
        }

        $requestPath =  $session->get('request_path');

        if ($requestPath && $requestPath !== Url::fromRequest()->getPath()) {
            $session->clear();
        }

        return $viewModeSwitcher;
    }

    /**
     * Process a search request
     *
     * @param Query $query
     */
    public function handleSearchRequest(Query $query)
    {
        $q = trim($this->params->shift('q', ''), ' *');
        if (! $q) {
            return;
        }

        $filter = Filter::any();
        foreach ($query->getModel()->getSearchColumns() as $column) {
            $filter->add(Filter::equal($column, "*$q*"));
        }

        $requestUrl = Url::fromRequest();

        $existingParams = $requestUrl->getParams()->without('q');
        $requestUrl->setQueryString(QueryString::render($filter));
        foreach ($existingParams->toArray(false) as $name => $value) {
            $requestUrl->getParams()->addEncoded($name, $value);
        }

        $this->getResponse()->redirectAndExit($requestUrl);
    }

    /**
     * Require permission to access the given route
     *
     * @param string $name If NULL, the current controller name is used
     *
     * @throws SecurityException
     */
    public function assertRouteAccess($name = null)
    {
        if (! $name) {
            $name = $this->getRequest()->getControllerName();
        }

        if (! $this->isPermittedRoute($name)) {
            throw new SecurityException('No permission to access this route');
        }
    }

    public function export(Query ...$queries)
    {
        if ($this->format === 'sql') {
            foreach ($queries as $query) {
                list($sql, $values) = $query->getDb()->getQueryBuilder()->assembleSelect($query->assembleSelect());

                $unused = [];
                foreach ($values as $value) {
                    $pos = strpos($sql, '?');
                    if ($pos !== false) {
                        if (is_string($value)) {
                            $value = "'" . $value . "'";
                        }

                        $sql = substr_replace($sql, $value, $pos, 1);
                    } else {
                        $unused[] = $value;
                    }
                }

                if (!empty($unused)) {
                    $sql .= ' /* Unused values: "' . join('", "', $unused) . '" */';
                }

                $this->content->add(Html::tag('pre', $sql));
            }

            return true;
        }

        $this->getTabs()->enableDataExports();
    }

    public function dispatch($action)
    {
        // Notify helpers of action preDispatch state
        $this->_helper->notifyPreDispatch();

        $this->preDispatch();

        if ($this->getRequest()->isDispatched()) {
            // If pre-dispatch hooks introduced a redirect then stop dispatch
            // @see ZF-7496
            if (! $this->getResponse()->isRedirect()) {
                $interceptable = $this->$action();
                if ($interceptable instanceof Generator) {
                    foreach ($interceptable as $stopSignal) {
                        if ($stopSignal === true) {
                            $this->formatProcessed = true;
                            break;
                        }
                    }
                }
            }
            $this->postDispatch();
        }

        // whats actually important here is that this action controller is
        // shutting down, regardless of dispatching; notify the helpers of this
        // state
        $this->_helper->notifyPostDispatch();
    }

    /**
     * Enrich the filter condition with meta data from the query
     *
     * @param Filter\Condition $condition
     * @param Query $query
     *
     * @return void
     */
    protected function enrichFilterCondition(Filter\Condition $condition, Query $query)
    {
        $path = $condition->getColumn();
        if (strpos($path, '.') === false) {
            $path = $query->getResolver()->qualifyPath($path, $query->getModel()->getTableName());
            $condition->setColumn($path);
        }

        if (strpos($path, '.vars.') !== false) {
            list($target, $varName) = explode('.vars.', $path);
            if (strpos($target, '.') === false) {
                // Programmatically translated since the full definition is available in class ObjectSuggestions
                $condition->metaData()->set(
                    'columnLabel',
                    sprintf(t(ucfirst($target) . ' %s', '..<customvar-name>'), $varName)
                );
            }
        } else {
            $metaData = iterator_to_array(
                ObjectSuggestions::collectFilterColumns($query->getModel(), $query->getResolver())
            );
            if (isset($metaData[$path])) {
                $condition->metaData()->set('columnLabel', $metaData[$path]);
            }
        }
    }

    protected function addContent(ValidHtml $content)
    {
        if ($content instanceof BaseItemList) {
            $this->content->getAttributes()->add('class', 'full-width');
        }

        return parent::addContent($content);
    }

    public function filter(Query $query, Filter\Rule $filter = null)
    {
        if ($this->format !== 'sql' || $this->hasPermission('config/authentication/roles/show')) {
            $this->applyRestrictions($query);
        }

        if ($query instanceof UnionQuery) {
            foreach ($query->getUnions() as $query) {
                $query->filter($filter ?: $this->getFilter());
            }
        } else {
            $query->filter($filter ?: $this->getFilter());
        }

        return $this;
    }

    public function preDispatch()
    {
        parent::preDispatch();

        $this->format = $this->params->shift('format');
    }

    public function postDispatch()
    {
        if (! $this->formatProcessed && $this->format !== null) {
            // The purpose of this is not only to show that a requested format isn't supported.
            // It's main purpose is to not allow to bypass restrictions with `?format=sql` as
            // it may be possible that an action applies restrictions, but doesn't support any
            // output formats. Since the restrictions are bypassed in method `$this->filter()`
            // for the SQL output format and the actual format processing is part of a different
            // method (`$this->export()`) which needs to be called explicitly by an action,
            // it's otherwise possible for bad individuals to access unrestricted data.
            $this->httpBadRequest(t('This route does not support the requested output format'));
        }

        parent::postDispatch();
    }

    protected function moduleInit()
    {
        Icinga::app()->getModuleManager()->loadModule('monitoring');

        Icinga::app()->getFrontController()
            ->getPlugin('Zend_Controller_Plugin_ErrorHandler')
            ->setErrorHandlerModule('icingadb');
    }
}
