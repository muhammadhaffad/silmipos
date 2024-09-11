<select class="form-control {{ $class }}" name="{{$name}}[]" select2 data-url="{{$resourceUrl}}" data-value="{{implode(',',(array)$value)}}" multiple style="width: 100%;">
    <option></option>
    @foreach($options as $select => $option)
        <option value="{{$select}}" {{ in_array((string)$select, (array)$value) ?'selected':'' }}>{{$option}}</option>
    @endforeach
</select>