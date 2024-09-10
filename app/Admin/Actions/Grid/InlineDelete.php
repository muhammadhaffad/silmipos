<?php

namespace App\Admin\Actions\Grid;

use Encore\Admin\Actions\RowAction;
use Illuminate\Database\Eloquent\Model;

class InlineDelete extends RowAction
{
    public $name = 'Delete';
    public $tableName = '';
    public $route = '';

    public function __construct($tableName = '', $route = '')
    {
        $this->tableName = $tableName;
        $this->route = $route;
    }

    protected function buildActionPromise()
    {
        return <<<SCRIPT
        var process = new Promise(function (resolve,reject) {

            Object.assign(data, {
                _token: $.admin.token,
                _action: '{$this->getCalledClass()}',
            });

            $.ajax({
                method: '{$this->method}',
                url: '{$this->route}',
                data: data,
                success: function (data) {
                    resolve([data, target]);
                },
                error:function(request){
                    reject(request);
                }
            });
        });
        SCRIPT;
    }

    public function actionScript()
    {
        return <<<SCRIPT
        let customData = [];
        $('[inline-edit="{$this->tableName}_{$this->getKey()}"]').each(function () {
            customData[$(this).attr('name')] = $(this).val();
        });
        Object.assign(data, customData);
        console.info(data); 
        SCRIPT;   
    }

    public function handle(Model $model)
    {
        // $model ...
        
        return $this->response()->success('Success message.')->refresh();
    }

}