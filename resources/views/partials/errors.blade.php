@if($errors->any())
    <ul class="notice error" role="alert">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
@endif
