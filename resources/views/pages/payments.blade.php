@extends('layouts.app')
@section('title')
    @lang('payments.content.page.title')
@endsection
@section('content')
    <div class="jumbotron">
        <h1>@lang('payments.content.header.title')</h1>
        <p class="lead">@lang('payments.content.header.title')</p>
        <p>
            <a class="btn btn-lg btn-success" href="{{ route('process', ['type' => 'csv']) }}" role="button">
                @lang('payments.content.bank')
            </a>
            <a class="btn btn-lg btn-success" href="{{ route('process', ['type' => 'txt']) }}" role="button">
                @lang('payments.content.bank')
            </a>
            <a class="btn btn-lg btn-success" href="{{ route('process', ['type' => 'pdf']) }}" role="button">
                @lang('payments.content.bank')
            </a>
        </p>
    </div>

    <div class="row marketing">
        <div class="col-lg-12">
            <h4>
                @lang('payments.content.subheading.title')
            </h4>
            <p>
                @lang('payments.content.subheading.content')
            </p>
        </div>
    </div>
@endsection