<?php

namespace Icinga\Module\Icingadb\Data;

use Icinga\Application\Icinga;
use ipl\Orm\Query;
use ipl\Stdlib\Contract\Paginatable;
use ipl\Stdlib\Filter;

class PivotTable
{
    const SORT_ASC = 'asc';

    /**
     * The query to fetch as pivot table
     *
     * @var Query
     */
    protected $baseQuery;

    /**
     * X-axis pivot column
     *
     * @var string
     */
    protected $xAxisColumn;

    /**
     * Y-axis pivot column
     *
     * @var string
     */
    protected $yAxisColumn;

    /**
     * The filter being applied on the query for the x-axis
     *
     * @var Filter\Rule
     */
    protected $xAxisFilter;

    /**
     * The filter being applied on the query for the y-axis
     *
     * @var Filter\Rule
     */
    protected $yAxisFilter;

    /**
     * The query to fetch the leading x-axis rows and their headers
     *
     * @var Query
     */
    protected $xAxisQuery;

    /**
     * The query to fetch the leading y-axis rows and their headers
     *
     * @var Query
     */
    protected $yAxisQuery;

    /**
     * X-axis header column
     *
     * @var string|null
     */
    protected $xAxisHeader;

    /**
     * Y-axis header column
     *
     * @var string|null
     */
    protected $yAxisHeader;

    /**
     * Order by column and direction
     *
     * @var array
     */
    protected $order = [];

    /**
     * Grid columns as [Alias => Column name] pairs
     *
     * @var array
     */
    protected $gridcols = [];

    /**
     * Create a new pivot table
     *
     * @param   Query       $query          The query to fetch as pivot table
     * @param   string      $xAxisColumn    X-axis pivot column
     * @param   string      $yAxisColumn    Y-axis pivot column
     * @param   array      $gridcols        Grid columns
     */
    public function __construct(Query $query, $xAxisColumn, $yAxisColumn, $gridcols)
    {
        foreach ($query->getOrderBy() as $sort) {
            $this->order[$sort[0]] = $sort[1];
        }

        // ipl/sql branch put-reset-methods-into-the trait is required for resetOrderBy().
        $this->baseQuery = $query->setColumns($gridcols)->resetOrderBy();
        $this->xAxisColumn = $xAxisColumn;
        $this->yAxisColumn = $yAxisColumn;
        $this->gridcols = $gridcols;
    }

    /**
     * Set the filter to apply on the query for the x-axis
     *
     * @param   Filter\Rule  $filter
     *
     * @return  $this
     */
    public function setXAxisFilter(Filter\Rule $filter = null)
    {
        $this->xAxisFilter = $filter;
        return  $this;
    }

    /**
     * Set the filter to apply on the query for the y-axis
     *
     * @param   Filter  $filter
     *
     * @return  $this
     */
    public function setYAxisFilter(Filter\Rule $filter = null)
    {
        $this->yAxisFilter = $filter;
        return  $this;
    }

    /**
     * Get the x-axis header
     *
     * Defaults to {@link $xAxisColumn} in case no x-axis header has been set using {@link setXAxisHeader()}
     *
     * @return string
     */
    public function getXAxisHeader()
    {
        return $this->xAxisHeader !== null ? $this->xAxisHeader : $this->xAxisColumn;
    }

    /**
     * Set the x-axis header
     *
     * @param   string $xAxisHeader
     *
     * @return  $this
     */
    public function setXAxisHeader($xAxisHeader)
    {
        $this->xAxisHeader = (string) $xAxisHeader;
        return $this;
    }

    /**
     * Get the y-axis header
     *
     * Defaults to {@link $yAxisColumn} in case no x-axis header has been set using {@link setYAxisHeader()}
     *
     * @return string
     */
    public function getYAxisHeader()
    {
        return $this->yAxisHeader !== null ? $this->yAxisHeader : $this->yAxisColumn;
    }

    /**
     * Set the y-axis header
     *
     * @param   string $yAxisHeader
     *
     * @return  $this
     */
    public function setYAxisHeader($yAxisHeader)
    {
        $this->yAxisHeader = (string) $yAxisHeader;
        return $this;
    }

    /**
     * Return the value for the given request parameter
     *
     * @param   string  $axis       The axis for which to return the parameter ('x' or 'y')
     * @param   string  $param      The parameter name to return
     * @param   int     $default    The default value to return
     *
     * @return  int
     */
    protected function getPaginationParameter($axis, $param, $default = null)
    {
        $request = Icinga::app()->getRequest();

        $value = $request->getParam($param, '');
        if (strpos($value, ',') > 0) {
            $parts = explode(',', $value, 2);
            return intval($parts[$axis === 'x' ? 0 : 1]);
        }

        return $default !== null ? $default : 0;
    }

    /**
     * Query horizontal (x) axis
     *
     * @return Query
     */
    protected function queryXAxis()
    {
        if ($this->xAxisQuery === null) {
            $this->xAxisQuery = clone $this->baseQuery;
            $xAxisHeader = $this->getXAxisHeader();
            $table = $this->xAxisQuery->getModel()->getTableName();
            $xCol = explode('.', $this->gridcols[$this->xAxisColumn]);
            $columns = [
                $this->xAxisColumn => $this->gridcols[$this->xAxisColumn],
                $xAxisHeader => $this->gridcols[$xAxisHeader]
            ];

            // TODO: This shouldn't be required. Refactor this once ipl\Orm\Query has support for group by rules!
            if ($xCol[0] !== $table) {
                $groupCols = array_unique([
                    $this->xAxisColumn => $table . '_' . $this->gridcols[$this->xAxisColumn],
                    $xAxisHeader => $table . '_' . $this->gridcols[$xAxisHeader]
                ]);
            } else {
                $groupCols = $columns;
            }

            $this->xAxisQuery->getSelectBase()->groupBy($groupCols);

            if (count($columns) !== 2) {
                $columns[] = $this->gridcols[$xAxisHeader];
            }

            $this->xAxisQuery->setColumns($columns);

            if ($this->xAxisFilter !== null) {
                $this->xAxisQuery->filter($this->xAxisFilter);
            }

            $this->xAxisQuery->orderBy(
                $this->gridcols[$xAxisHeader],
                isset($this->order[$this->gridcols[$xAxisHeader]]) ?
                    $this->order[$this->gridcols[$xAxisHeader]] : self::SORT_ASC
            );
        }

        return $this->xAxisQuery;
    }

    /**
     * Query vertical (y) axis
     *
     * @return Query
     */
    protected function queryYAxis()
    {
        if ($this->yAxisQuery === null) {
            $this->yAxisQuery = clone $this->baseQuery;
            $yAxisHeader = $this->getYAxisHeader();
            $table = $this->yAxisQuery->getModel()->getTableName();
            $columns = [
                $this->yAxisColumn => $this->gridcols[$this->yAxisColumn],
                $yAxisHeader => $this->gridcols[$yAxisHeader]
            ];
            $yCol = explode('.', $this->gridcols[$this->yAxisColumn]);

            // TODO: This shouldn't be required. Refactor this once ipl\Orm\Query has support for group by rules!
            if ($yCol[0] !== $table) {
                $groupCols = array_unique([
                    $this->yAxisColumn => $table . '_' . $this->gridcols[$this->yAxisColumn],
                    $yAxisHeader => $table . '_' . $this->gridcols[$yAxisHeader]
                ]);
            } else {
                $groupCols = $columns;
            }

            $this->yAxisQuery->getSelectBase()->groupBy($groupCols);

            if (count($columns) !== 2) {
                $columns[] = $this->gridcols[$yAxisHeader];
            }

            $this->yAxisQuery->setColumns($columns);

            if ($this->yAxisFilter !== null) {
                $this->yAxisQuery->filter($this->yAxisFilter);
            }

            $this->yAxisQuery->orderBy(
                $this->gridcols[$yAxisHeader],
                isset($this->order[$this->gridcols[$yAxisHeader]]) ?
                    $this->order[$this->gridcols[$yAxisHeader]] : self::SORT_ASC
            );
        }

        return $this->yAxisQuery;
    }

    /**
     * Return a pagination adapter for the x-axis query
     *
     * $limit and $page are taken from the current request if not given.
     *
     * @param   int     $limit  The maximum amount of entries to fetch
     * @param   int     $page   The page to set as current one
     *
     * @return  Paginatable
     */
    public function paginateXAxis($limit = null, $page = null)
    {
        if ($limit === null || $page === null) {
            if ($limit === null) {
                $limit = $this->getPaginationParameter('x', 'limit', 20);
            }

            if ($page === null) {
                $page = $this->getPaginationParameter('x', 'page', 1);
            }
        }

        $query = $this->queryXAxis();

        $query->limit($limit);

        $query->offset($page > 0 ? ($page - 1) * $limit : 0);

        return $query;
    }

    /**
     * Return a Paginatable for the y-axis query
     *
     * $limit and $page are taken from the current request if not given.
     *
     * @param int $limit The maximum amount of entries to fetch
     * @param int $page The page to set as current one
     *
     * @return  Paginatable
     */
    public function paginateYAxis($limit = null, $page = null)
    {
        if ($limit === null || $page === null) {
            if ($limit === null) {
                $limit = $this->getPaginationParameter('y', 'limit', 20);
            }

            if ($page === null) {
                $page = $this->getPaginationParameter('y', 'page', 1);
            }
        }

        $query = $this->queryYAxis();
        $query->limit($limit);
        $query->offset($page > 0 ? ($page - 1) * $limit : 0);

        return $query;
    }

    /**
     * Return the pivot table as an array of pivot data and pivot header
     *
     * @return array
     */
    public function toArray()
    {
        if (
            ($this->xAxisFilter === null && $this->yAxisFilter === null)
            || ($this->xAxisFilter !== null && $this->yAxisFilter !== null)
        ) {
            $xAxis = $this->queryXAxis()->getDb()->fetchPairs($this->queryXAxis()->assembleSelect());
            $xAxisKeys = array_keys($xAxis);
            $yAxis = $this->queryYAxis()->getDb()->fetchPairs($this->queryYAxis()->assembleSelect());
            $yAxisKeys = array_keys($yAxis);
        } else {
            if ($this->xAxisFilter !== null) {
                $xAxis = $this->queryXAxis()->getDb()->fetchPairs($this->queryXAxis()->assembleSelect());
                $xAxisKeys = array_keys($xAxis);
                $yQuery = $this->queryYAxis();
                $yQuery->filter(Filter::equal($this->gridcols[$this->xAxisColumn], $xAxisKeys));
                $yAxis = $this->queryYAxis()->getDb()->fetchPairs($this->queryYAxis()->assembleSelect());
                $yAxisKeys = array_keys($yAxis);
            } else { // $this->yAxisFilter !== null
                $yAxis = $this->queryYAxis()->getDb()->fetchPairs($this->queryYAxis()->assembleSelect());
                $yAxisKeys = array_keys($yAxis);
                $xQuery = $this->queryXAxis();
                $xQuery->filter(Filter::equal($this->gridcols[$this->yAxisColumn], $yAxisKeys));
                $xAxis = $this->queryXAxis()->getDb()->fetchPairs($this->queryXAxis()->assembleSelect());
                $xAxisKeys = array_keys($yAxis);
            }
        }

        $pivotData = [];
        $pivotHeader = [
            'cols'  => $xAxis,
            'rows'  => $yAxis
        ];

        if (! empty($xAxis) && ! empty($yAxis)) {
            $this->baseQuery->filter(Filter::equal($this->gridcols[$this->xAxisColumn], $xAxisKeys));
            $this->baseQuery->filter(Filter::equal($this->gridcols[$this->yAxisColumn], $yAxisKeys));
            foreach ($yAxisKeys as $yAxisKey) {
                foreach ($xAxisKeys as $xAxisKey) {
                    $pivotData[$yAxisKey][$xAxisKey] = null;
                }
            }

            foreach ($this->baseQuery as $row) {
                $pivotData[$row->{$this->yAxisColumn}][$row->{$this->xAxisColumn}] = $row;
            }
        }

        return [$pivotData, $pivotHeader];
    }
}
