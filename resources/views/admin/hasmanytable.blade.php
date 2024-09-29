<style>
    td .form-group {
        margin-bottom: 0 !important;
    }
</style>

<div class="row">
    <label class="control-label {{$viewClass['label']}}">
        {{ $label }}
    </label>
    <div class="{{$viewClass['field']}}">
        <div id="has-many-{{$column}}">
            <table class="table table-has-many has-many-{{$column}}">
                <thead>
                <tr>
                    @foreach($headers as $header)
                        <th class="{!! str_replace('hidden', '', $header['labelClass']) !!}">{!! $header['label'] !!}</th>
                    @endforeach

                    <th class="hidden"></th>

                    @if($options['allowDelete'])
                        <th></th>
                    @endif
                </tr>
                </thead>
                <tbody class="has-many-{{$column}}-forms">
                @foreach($forms as $pk => $form)
                    <tr class="has-many-{{$column}}-form fields-group">

                        <?php $hidden = ''; ?>

                        @foreach($form->fields() as $field)
                            @if (is_a($field, \Encore\Admin\Form\Field\Hidden::class))
                                <?php $hidden .= $field->render(); ?>
                                @continue
                            @endif

                            <td>{!! $field->setLabelClass(['hidden'])->setWidth(12, 0)->render() !!}</td>
                        @endforeach

                        <td class="hidden">{!! $hidden !!}</td>

                        @if($options['allowDelete'])
                            <td class="form-group">
                                <div>
                                    <div class="remove btn btn-warning btn-sm pull-right"><i class="fa fa-trash">&nbsp;</i>{{ trans('admin.remove') }}</div>
                                </div>
                            </td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>

            <template class="{{$column}}-tpl">
                <tr class="has-many-{{$column}}-form fields-group">

                    {!! $template !!}
                    @if($options['allowDelete'])
                        <td class="form-group">
                            <div>
                                <div class="remove btn btn-warning btn-sm pull-right"><i class="fa fa-trash">&nbsp;</i>{{ trans('admin.remove') }}</div>
                            </div>
                        </td>
                    @endif
                </tr>
            </template>

            @if($options['allowCreate'])
                <div class="form-group">
                    <div class="{{$viewClass['field']}}">
                        <div class="add btn btn-success btn-sm"><i class="fa fa-save"></i>&nbsp;{{ trans('admin.new') }}</div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>