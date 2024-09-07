<?php
namespace App\Admin\Form;

use App\Models\Produk;
use Closure;
use Encore\Admin\Form as AdminForm;
use Encore\Admin\Form\Field;

class Form extends AdminForm {
    /**
     * Array of queries of the eloquent model.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $queries;

    public function __construct($model, Closure $callback = null)
    {
        $this->queries = collect();
        parent::__construct($model, $callback);
    }

    protected function setFieldValue($id)
    {
        $relations = $this->getRelations();
        $this->queries->unique()->each(function ($query) {
            $this->model = call_user_func_array([$this->model, $query['method']], $query['arguments']);
        });
        $builder = $this->model;

        if ($this->isSoftDeletes) {
            $builder = $builder->withTrashed();
        }
        

        $this->model = $builder->with($relations)->findOrFail($id);

        $this->callEditing();

        $data = $this->model->toArray();

        $this->fields()->each(function (Field $field) use ($data) {
            if (!in_array($field->column(), $this->ignored, true)) {
                $field->fill($data);
            }
        });
    }

    public function addQuery($method, ...$arguments) {
        $this->queries->push([
            'method'    => $method,
            'arguments' => $arguments,
        ]);
        return $this;
    }
}