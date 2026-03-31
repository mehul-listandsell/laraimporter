@php
    $layout = config('laraimporter.layout', 'admin-layout');
    $layoutType = config('laraimporter.layout_type', 'component');
    $titleSlot = config('laraimporter.title_slot', 'title');
@endphp

@if($layoutType === 'component')
<x-dynamic-component :component="$layout">
    <x-slot :name="$titleSlot">{{ __('laraimporter::messages.import_history') }}</x-slot>
@else
@extends($layout)
@section($titleSlot, __('laraimporter::messages.import_history'))
@section('content')
@endif

    <div class="max-w-6xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold text-white">{{ __('laraimporter::messages.history_title') }}</h2>
                <p class="text-sm text-gray-400 mt-1">{{ __('laraimporter::messages.history_description') }}</p>
            </div>
            <a href="{{ route('laraimporter.index') }}"
               class="px-4 py-2 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-lg text-sm font-medium hover:shadow-lg transition-all">
                {{ __('laraimporter::messages.new_import') }}
            </a>
        </div>

        <div class="bg-gray-800/30 backdrop-blur-xl border border-blue-500/30 rounded-xl p-6">
            @if($jobs->isEmpty())
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                    </svg>
                    <p class="text-sm text-gray-400">{{ __('laraimporter::messages.no_imports_yet') }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-blue-500/10">
                            <tr>
                                <th class="px-4 py-3 text-left text-blue-400 font-medium">#</th>
                                <th class="px-4 py-3 text-left text-blue-400 font-medium">{{ __('laraimporter::messages.status') }}</th>
                                <th class="px-4 py-3 text-left text-blue-400 font-medium">{{ __('laraimporter::messages.target') }}</th>
                                <th class="px-4 py-3 text-left text-blue-400 font-medium">{{ __('laraimporter::messages.progress') }}</th>
                                <th class="px-4 py-3 text-left text-blue-400 font-medium">{{ __('laraimporter::messages.inserted') }}</th>
                                <th class="px-4 py-3 text-left text-blue-400 font-medium">{{ __('laraimporter::messages.updated') }}</th>
                                <th class="px-4 py-3 text-left text-blue-400 font-medium">{{ __('laraimporter::messages.skipped') }}</th>
                                <th class="px-4 py-3 text-left text-blue-400 font-medium">{{ __('laraimporter::messages.errors') }}</th>
                                <th class="px-4 py-3 text-left text-blue-400 font-medium">{{ __('laraimporter::messages.date') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($jobs as $job)
                                <tr class="border-t border-gray-700/50 hover:bg-blue-500/5">
                                    <td class="px-4 py-3 text-gray-400">{{ $job->id }}</td>
                                    <td class="px-4 py-3">
                                        @switch($job->status)
                                            @case('completed')
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-500/20 text-green-400 border border-green-500/30">
                                                    <span class="w-1.5 h-1.5 bg-green-400 rounded-full mr-1.5"></span>{{ __('laraimporter::messages.completed') }}
                                                </span>
                                                @break
                                            @case('failed')
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-500/20 text-red-400 border border-red-500/30">
                                                    <span class="w-1.5 h-1.5 bg-red-400 rounded-full mr-1.5"></span>{{ __('laraimporter::messages.failed') }}
                                                </span>
                                                @break
                                            @case('processing')
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-500/20 text-blue-400 border border-blue-500/30">
                                                    <span class="w-1.5 h-1.5 bg-blue-400 rounded-full mr-1.5 animate-pulse"></span>{{ __('laraimporter::messages.processing') }}
                                                </span>
                                                @break
                                            @default
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-500/20 text-yellow-400 border border-yellow-500/30">
                                                    <span class="w-1.5 h-1.5 bg-yellow-400 rounded-full mr-1.5"></span>{{ __('laraimporter::messages.pending') }}
                                                </span>
                                        @endswitch
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="text-white text-xs font-medium bg-blue-500/20 px-2 py-1 rounded">{{ $job->config['primary_table'] ?? '—' }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center space-x-2">
                                            <div class="flex-1 h-1.5 bg-gray-700/50 rounded-full overflow-hidden w-20">
                                                <div class="h-full rounded-full {{ $job->status === 'failed' ? 'bg-red-500' : ($job->status === 'completed' ? 'bg-green-500' : 'bg-blue-500') }}"
                                                     style="width: {{ $job->getProgressPercentage() }}%"></div>
                                            </div>
                                            <span class="text-xs text-gray-400">{{ $job->processed_rows }}/{{ $job->total_rows }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-green-400 font-medium">{{ $job->inserted }}</td>
                                    <td class="px-4 py-3 text-blue-400 font-medium">{{ $job->updated }}</td>
                                    <td class="px-4 py-3 text-yellow-400 font-medium">{{ $job->skipped }}</td>
                                    <td class="px-4 py-3">
                                        @php $errorCount = count($job->errors ?? []) @endphp
                                        <span class="{{ $errorCount > 0 ? 'text-red-400 font-medium' : 'text-gray-400' }}">{{ $errorCount }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-400 text-xs">{{ $job->created_at->format('M d, Y H:i') }}</td>
                                </tr>
                                @if($job->status === 'failed' && $job->error_message)
                                    <tr class="border-t border-red-500/10">
                                        <td colspan="9" class="px-4 py-2">
                                            <p class="text-xs text-red-300 bg-red-500/10 rounded px-3 py-2">{{ $job->error_message }}</p>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($jobs->hasPages())
                    <div class="mt-4 pt-4 border-t border-gray-700">{{ $jobs->links() }}</div>
                @endif
            @endif
        </div>
    </div>

@if($layoutType === 'component')
</x-dynamic-component>
@else
@endsection
@endif
