@if (session('status'))
    <div class="toasts" role="status">
        <div class="toast"><x-icon name="check-circle"/>{{ session('status') }}</div>
    </div>
@endif
@if (session('warning'))
    <div class="alert warning">{{ session('warning') }}</div>
@endif
@if ($errors->any())
    <div class="alert error" role="alert">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
