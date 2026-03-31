@php
    $layout = config('laraimporter.layout', 'admin-layout');
    $layoutType = config('laraimporter.layout_type', 'component');
    $titleSlot = config('laraimporter.title_slot', 'title');
@endphp

@if($layoutType === 'component')
<x-dynamic-component :component="$layout">
    <x-slot :name="$titleSlot">{{ $pageTitle ?? __('laraimporter::messages.title') }}</x-slot>
    {{ $slot }}
</x-dynamic-component>
@else
@extends($layout)
@section($titleSlot, $pageTitle ?? __('laraimporter::messages.title'))
@section('content')
    {{ $slot }}
@endsection
@endif
