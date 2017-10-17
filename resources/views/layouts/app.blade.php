<!DOCTYPE html>
<html lang="en">
        @include('partials.app.head')
        @section('css')
            {{--this is used in case we need additional CSS Inside a child view --}}
        @show
    </head>
    <body>
        <div class="container">
            <div class="header clearfix">
               @include('partials.header')
            </div>

            @yield('content')

            <footer class="footer">
                @include('partials.app.footer')
            </footer>
        </div>

        <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
        @section('scripts')
            {{--this is used in case we need additional JS Inside a child view--}}
        @show
    </body>
</html>