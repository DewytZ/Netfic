@if (session('status'))
    <div class="toast status-toast" role="status">
        <p>{{ session('status') }}</p>
    </div>
@endif

@if ($errors->any())
    <div class="toast error-toast" role="alert">
        <ul>
            @foreach ($errors->unique() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
