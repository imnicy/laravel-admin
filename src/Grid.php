<?php
namespace Encore\Admin;

use Closure;
use Encore\Admin\Grid\Action;
use Encore\Admin\Grid\Row;
use Encore\Admin\Grid\Model;
use Encore\Admin\Grid\Column;
use Illuminate\Support\Str;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Encore\Admin\Pagination\AdminThreePresenter;
use Illuminate\Database\Eloquent\Relations\Relation;

class Grid {

    protected $model;

    protected $columns;

    protected $rows;

    protected $cells;

    protected $rowsCallable;

    public $columnNames = [];

    protected $actions;

    protected $paginator;

    protected $builder;

    protected $builded = false;

    protected $viewVariables = [];

    protected $options = [
        'title' => 'List'
    ];

    /**
     * Create a new grid instance.
     *
     * @param Eloquent $model
     * @param callable $builder
     */
    public function __construct(Eloquent $model, Closure $builder)
    {
        $this->model    = new Model($model);
        $this->columns  = new Collection();
        $this->rows     = new Collection();

        $this->builder = $builder;
    }

    /**
     * Add column to Grid.
     *
     * @param string $name
     * @param string $label
     * @return Column
     */
    public function column($name, $label = '')
    {
        if(strpos($name, '.') !== false) {
            list($relation, $relationColumn) = explode('.', $name);

            $relation = $this->getModel()->$relation();

            $label = empty($label) ? ucfirst($relationColumn) : $label;
        }

        $column = $this->addColumn($name, $label);

        if($relation instanceof Relation) {
            $column->setRelation($relation, $relationColumn);
        }

        return $column;
    }

    /**
     * Batch add column to grid.
     *
     * @example
     * 1.$grid->columns(['name' => 'Name', 'email' => 'Email' ...]);
     * 2.$grid->columns('name', 'email' ...)
     *
     * @param array $columns
     * @return Collection|void
     */
    public function columns($columns = [])
    {
        if(func_num_args() == 0) {
            return $this->columns;
        }

        if(func_num_args() == 1 && is_array($columns)) {
            foreach($columns as $column => $label) {
                $this->column($column, $label);
            }

            return;
        }

        foreach(func_get_args() as $column) {
            $this->column($column);
        }
    }

    /**
     * Add column to grid.
     *
     * @param string $column
     * @param string $label
     * @return Column
     */
    protected function addColumn($column = '', $label = '')
    {
        $label = $label ?: Str::upper($column);

        return $this->columns[] = new Column($column, $label);
    }

    /**
     * Get Grid model.
     *
     * @return Model
     */
    public function model()
    {
        return $this->model;
    }

    /**
     * Get eloquent model.
     *
     * @return mixed
     */
    protected function getModel()
    {
        return $this->model->getModel();
    }

    /**
     * @return mixed
     */
    public function pageRender()
    {
        return $this->getModel()->render(
            new AdminThreePresenter($this->getModel())
        );
    }

    /**
     * @param string $actions
     * @return $this
     */
    public function actions($actions = 'show|edit|delete')
    {
        $this->actions = new Action($actions);

        return $this;
    }

    /**
     * Render grid actions for each data item.
     *
     * @param $id
     * @return mixed
     */
    public function renderActions($id)
    {
        return $this->actions->render($id);
    }

    /**
     * Build
     */
    public function build()
    {
        if($this->builded) return;

        call_user_func($this->builder, $this);

        $data  = $this->model()->buildData();

        $names = &$this->columnNames;

        $this->columns->map(function($column) use (&$data, &$names) {
            $data = $column->map($data);

            $names[] = $column->getName();
        });

        $this->rows = collect($data)->map(function($val, $key){
            return new Row($key, $val);
        });

        if($this->rowsCallable) {
            $this->rows->map($this->rowsCallable);
        }

        if(is_null($this->actions)) {
            $this->actions();
        }

        $this->builded = true;
    }

    public function rows(Closure $callable = null)
    {
        if(is_null($callable)) {
            return $this->rows;
        }

        $this->rowsCallable = $callable;
    }

    /**
     * Set the grid title.
     *
     * @param string $title
     */
    public function title($title = '')
    {
        $this->option('title', $title);
    }

    /**
     * Set grid option or get grid option.
     *
     * @param string $option
     * @param string $value
     * @return mixed
     */
    public function option($option, $value = '')
    {
        if(empty($value)) {
            return $this->options[$option];
        }

        $this->options[$option] = strval($value);
    }

    /**
     * @return mixed
     */
    public function resource()
    {
        return app('router')->current()->getPath();
    }

    /**
     * @param $method
     * @param $arguments
     * @return $this|Column
     */
    public function __call($method, $arguments)
    {
        if(Schema::hasColumn($this->getModel()->getTable(), $method))
        {
            $label = isset($arguments[0]) ? $arguments[0] : ucfirst($method);

            return $this->addColumn($method, $label);
        }

        $relation = $this->getModel()->$method();

        if($relation instanceof Relation) {

            $this->model()->with($method);

            return $this->addColumn()->setRelation($method);
        }
    }

    public function render()
    {
        if(! $this->builded) {
            $this->build();
        }

        return view('admin::grid', ['grid' => $this])->with($this->viewVariables)->render();
    }

    public function __toString()
    {
        return $this->render();
    }

    public function with($variables = [])
    {
        $this->viewVariables = $variables;

        return $this;
    }
}