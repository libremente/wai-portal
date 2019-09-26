@extends('layouts.default')

@section('page-content')
    @include('layouts.includes.header', [
        'navbar' => false,
        'classes' => ['tall'],
    ])

    <div class="position-relative">
        <div class="page-bulk-container">
            <div id="main">
                @yield('before-title')

                <h1 class="display-2">@yield('title')@yield('title-after')</h1>

                @yield('content')
            </div>
        </div>
        <div class="page-background-image">
            <img alt="" src="{{ asset('images/page-bulk-bg.svg') }}">
        </div>
    </div>
@endsection