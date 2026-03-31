@include('laraimporter::layout')

@php
    $isExt = $isExternal ?? false;
    $mongoAvail = $mongoAvailable ?? false;

    $jsTranslations = [
        'select_target_table' => __('laraimporter::messages.select_target_table'),
        'select_target_collection' => __('laraimporter::messages.select_target_collection'),
        'loading_tables' => __('laraimporter::messages.loading_tables'),
        'loading_collections' => __('laraimporter::messages.loading_collections'),
        'search_tables' => __('laraimporter::messages.search_tables'),
        'search_collections' => __('laraimporter::messages.search_collections'),
        'primary_table' => __('laraimporter::messages.primary_table'),
        'primary_collection' => __('laraimporter::messages.primary_collection'),
        'related_tables_fk' => __('laraimporter::messages.related_tables_fk'),
        'related_collections_guessed' => __('laraimporter::messages.related_collections_guessed'),
        'related_tables_hint' => __('laraimporter::messages.related_tables_hint'),
        'related_collections_hint' => __('laraimporter::messages.related_collections_hint'),
        'mongo_collections_note' => __('laraimporter::messages.mongo_collections_note'),
        'target_table' => __('laraimporter::messages.target_table'),
        'target_collection' => __('laraimporter::messages.target_collection'),
        'target_column' => __('laraimporter::messages.target_column'),
        'target_field' => __('laraimporter::messages.target_field'),
        'target_table_label' => __('laraimporter::messages.target_table_label'),
        'target_collection_label' => __('laraimporter::messages.target_collection_label'),
        'fk_column_on' => __('laraimporter::messages.fk_column_on'),
        'large_file_detected' => __('laraimporter::messages.large_file_detected'),
        'large_dataset_detected' => __('laraimporter::messages.large_dataset_detected'),
        'background_import' => __('laraimporter::messages.background_import'),
        'unknown_error' => __('laraimporter::messages.unknown_error'),
    ];

    $jsSteps = [
        __('laraimporter::messages.step_connection'),
        __('laraimporter::messages.step_upload'),
        __('laraimporter::messages.step_select_table'),
        __('laraimporter::messages.step_mapping'),
        __('laraimporter::messages.step_import'),
    ];
@endphp

<div x-data="importWizard()" x-cloak class="max-w-6xl mx-auto py-6 px-4">

    {{-- Step Indicator --}}
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <template x-for="(stepInfo, idx) in steps" :key="idx">
                <div class="flex items-center" :class="idx < steps.length - 1 ? 'flex-1' : ''">
                    <div class="flex flex-col items-center">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold transition-all duration-300 border-2"
                             :class="{
                                 'bg-gradient-to-r from-blue-600 to-purple-600 border-transparent text-white shadow-lg shadow-blue-500/25': step > idx + 1,
                                 'bg-blue-500/30 border-blue-500 text-blue-400 shadow-lg shadow-blue-500/25': step === idx + 1,
                                 'bg-gray-800/50 border-gray-600/50 text-gray-500': step < idx + 1
                             }">
                            <template x-if="step > idx + 1">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </template>
                            <template x-if="step <= idx + 1">
                                <span x-text="idx + 1"></span>
                            </template>
                        </div>
                        <span class="mt-2 text-xs font-medium hidden sm:block"
                              :class="step >= idx + 1 ? 'text-blue-400' : 'text-gray-500'"
                              x-text="stepInfo"></span>
                    </div>
                    <div x-show="idx < steps.length - 1" class="flex-1 h-0.5 mx-3 mt-[-20px] transition-all duration-300"
                         :class="step > idx + 1 ? 'bg-blue-500' : 'bg-gray-700'"></div>
                </div>
            </template>
        </div>
    </div>

    {{-- Global Error Display --}}
    <div x-show="globalError" x-transition
         class="mb-6 bg-red-500/20 backdrop-blur-md border border-red-500/50 rounded-lg p-4 flex items-center justify-between">
        <div class="flex items-center">
            <svg class="w-5 h-5 text-red-400 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
            <span class="text-sm text-red-200" x-text="globalError"></span>
        </div>
        <button @click="globalError = ''" class="text-red-300 hover:text-red-100">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
        </button>
    </div>

    {{-- ========== STEP 1: Database Connection (External only) ========== --}}
    <div x-show="step === 1" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
        <div class="bg-gray-800/30 backdrop-blur-xl border border-blue-500/30 rounded-xl p-6">
            <div class="flex items-center mb-6">
                <svg class="w-6 h-6 text-blue-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                <h3 class="text-lg font-semibold text-white">{{ __('laraimporter::messages.db_connection') }}</h3>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-white mb-2">{{ __('laraimporter::messages.driver') }} <span class="text-red-400">*</span></label>
                    <select x-model="db.driver" @change="onDriverChange()" class="w-full bg-white border border-blue-500/30 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="mysql">MySQL / MariaDB</option>
                        <option value="pgsql">PostgreSQL</option>
                        <option value="mongodb" {{ $mongoAvail ? '' : 'disabled' }}>MongoDB {{ $mongoAvail ? '' : '(' . __('laraimporter::messages.mongo_ext_not_installed') . ')' }}</option>
                    </select>
                    @if(!$mongoAvail)
                    <p class="text-xs text-yellow-400 mt-1">{{ __('laraimporter::messages.mongo_ext_not_installed') }}</p>
                    @endif
                </div>

                <div>
                    <label class="block text-sm font-medium text-white mb-2">{{ __('laraimporter::messages.host') }} <span class="text-red-400">*</span></label>
                    <input type="text" x-model="db.host" placeholder="127.0.0.1" class="w-full bg-white border border-blue-500/30 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-white mb-2">{{ __('laraimporter::messages.port') }} <span class="text-red-400">*</span></label>
                    <input type="number" x-model="db.port" class="w-full bg-white border border-blue-500/30 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-white mb-2">{{ __('laraimporter::messages.database_name') }} <span class="text-red-400">*</span></label>
                    <input type="text" x-model="db.database" placeholder="my_database" class="w-full bg-white border border-blue-500/30 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-white mb-2">{{ __('laraimporter::messages.username') }} <span x-show="db.driver !== 'mongodb'" class="text-red-400">*</span></label>
                    <input type="text" x-model="db.username" placeholder="root" class="w-full bg-white border border-blue-500/30 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-white mb-2">{{ __('laraimporter::messages.password') }}</label>
                    <input type="password" x-model="db.password" placeholder="••••••••" class="w-full bg-white border border-blue-500/30 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                </div>

                <div x-show="db.driver === 'mongodb'" x-transition>
                    <label class="block text-sm font-medium text-white mb-2">{{ __('laraimporter::messages.auth_database') }}</label>
                    <input type="text" x-model="db.authentication_database" placeholder="admin" class="w-full bg-white border border-blue-500/30 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-400 mt-1">{{ __('laraimporter::messages.auth_database_hint') }}</p>
                </div>
            </div>

            <div x-show="db.driver === 'mongodb'" x-transition class="mt-4 p-3 bg-purple-500/10 border border-purple-500/30 rounded-lg">
                <p class="text-xs text-purple-300 flex items-center">
                    <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ __('laraimporter::messages.mongo_info') }}
                </p>
            </div>

            <div x-show="connectionStatus" x-transition class="mt-4 p-3 rounded-lg text-sm"
                 :class="connectionStatus === 'success' ? 'bg-green-500/20 border border-green-500/50 text-green-300' : 'bg-red-500/20 border border-red-500/50 text-red-300'">
                <span x-text="connectionMessage"></span>
            </div>

            <div class="flex items-center justify-between mt-6 pt-6 border-t border-gray-700">
                <button @click="testConnection()" :disabled="loading"
                        class="px-4 py-2 bg-gray-700/50 border border-blue-500/30 text-blue-400 rounded-lg text-sm font-medium hover:bg-blue-500/20 transition-all disabled:opacity-50">
                    <span x-show="!loading">{{ __('laraimporter::messages.test_connection') }}</span>
                    <span x-show="loading" class="flex items-center">
                        <svg class="animate-spin h-4 w-4 mr-2" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        {{ __('laraimporter::messages.testing') }}
                    </span>
                </button>
                <button @click="goToStep(2)" :disabled="connectionStatus !== 'success'"
                        class="px-6 py-2 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-lg text-sm font-medium hover:shadow-lg hover:shadow-blue-500/25 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                    {{ __('laraimporter::messages.next_upload_file') }}
                </button>
            </div>
        </div>
    </div>

    {{-- ========== STEP 2: Upload File ========== --}}
    <div x-show="step === 2" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
        <div class="bg-gray-800/30 backdrop-blur-xl border border-blue-500/30 rounded-xl p-6">
            <div class="flex items-center mb-6">
                <svg class="w-6 h-6 text-blue-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                <h3 class="text-lg font-semibold text-white">{{ __('laraimporter::messages.upload_data_file') }}</h3>
            </div>

            <div class="border-2 border-dashed border-blue-500/30 rounded-xl p-10 text-center hover:border-blue-500/60 transition-all cursor-pointer"
                 @click="$refs.fileInput.click()"
                 @dragover.prevent="dragOver = true"
                 @dragleave.prevent="dragOver = false"
                 @drop.prevent="handleFileDrop($event)"
                 :class="dragOver ? 'border-blue-500 bg-blue-500/10' : ''">
                <input type="file" x-ref="fileInput" @change="handleFileSelect($event)" accept=".csv,.txt,.json,.xls,.xlsx,.ods" class="hidden">
                <div x-show="!uploadedFileName">
                    <svg class="mx-auto h-12 w-12 text-gray-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                    <p class="text-white text-sm mb-1">{{ __('laraimporter::messages.drop_file_here') }}</p>
                    <p class="text-gray-400 text-xs">{{ __('laraimporter::messages.supported_formats') }}</p>
                </div>
                <div x-show="uploadedFileName" class="flex items-center justify-center space-x-3">
                    <svg class="h-8 w-8 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div class="text-left">
                        <p class="text-sm text-white font-medium" x-text="uploadedFileName"></p>
                        <p class="text-xs text-gray-400" x-text="filePreview ? filePreview.total_rows + ' {{ __("laraimporter::messages.rows_found") }}' : ''"></p>
                    </div>
                    <button @click.stop="clearFile()" class="text-red-400 hover:text-red-300 p-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            <div x-show="uploading" class="mt-4">
                <div class="h-2 bg-gray-700/50 rounded-full overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-blue-600 to-purple-600 rounded-full transition-all duration-300" :style="'width:' + uploadProgress + '%'"></div>
                </div>
                <p class="text-xs text-gray-400 mt-1 text-center" x-text="uploadProgress + '%'"></p>
            </div>

            <div x-show="suggestQueue" x-transition class="mt-4 p-3 bg-yellow-500/10 border border-yellow-500/30 rounded-lg">
                <p class="text-xs text-yellow-300 flex items-center">
                    <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span x-text="t.large_file_detected.replace(':count', fileTotalRows)"></span>
                </p>
            </div>

            {{-- File Preview Table --}}
            <div x-show="filePreview" x-transition class="mt-6">
                <h4 class="text-sm font-semibold text-blue-400 mb-3">{{ __('laraimporter::messages.data_preview') }} (<span x-text="filePreview ? filePreview.showing : 0"></span> {{ __('laraimporter::messages.total_rows') }})</h4>
                <div class="overflow-x-auto rounded-lg border border-blue-500/30">
                    <table class="w-full text-xs">
                        <thead class="bg-blue-500/10">
                            <tr>
                                <template x-for="header in (filePreview ? filePreview.headers : [])" :key="header">
                                    <th class="px-3 py-2 text-left text-blue-400 font-medium whitespace-nowrap" x-text="header"></th>
                                </template>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(row, ri) in (filePreview ? filePreview.rows : [])" :key="ri">
                                <tr class="border-t border-gray-700/50 hover:bg-blue-500/5">
                                    <template x-for="header in filePreview.headers" :key="header + ri">
                                        <td class="px-3 py-2 text-white whitespace-nowrap max-w-[200px] truncate" x-text="row[header] || '—'"></td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex items-center justify-between mt-6 pt-6 border-t border-gray-700">
                <button x-show="isExternal" @click="goToStep(1)" class="px-4 py-2 bg-gray-700/50 border border-gray-600 text-white rounded-lg text-sm hover:bg-gray-700 transition-all">
                    {{ __('laraimporter::messages.back') }}
                </button>
                <span x-show="!isExternal"></span>
                <button @click="goToStep(3); loadTables()" :disabled="!filePreview"
                        class="px-6 py-2 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-lg text-sm font-medium hover:shadow-lg hover:shadow-blue-500/25 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                    {{ __('laraimporter::messages.next_select_table') }}
                </button>
            </div>
        </div>
    </div>

    {{-- ========== STEP 3: Select Table ========== --}}
    <div x-show="step === 3" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
        <div class="bg-gray-800/30 backdrop-blur-xl border border-blue-500/30 rounded-xl p-6">
            <div class="flex items-center mb-6">
                <svg class="w-6 h-6 text-blue-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                <h3 class="text-lg font-semibold text-white" x-text="isMongoDB ? t.select_target_collection : t.select_target_table"></h3>
            </div>

            <div x-show="loading" class="text-center py-8">
                <svg class="animate-spin h-8 w-8 mx-auto text-blue-400 mb-3" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <p class="text-sm text-gray-400" x-text="isMongoDB ? t.loading_collections : t.loading_tables"></p>
            </div>

            <div x-show="!loading">
                <div x-show="isMongoDB" x-transition class="mb-4 p-3 bg-purple-500/10 border border-purple-500/30 rounded-lg">
                    <p class="text-xs text-purple-300" x-text="t.mongo_collections_note"></p>
                </div>

                <div class="mb-4">
                    <input type="text" x-model="tableSearch" :placeholder="isMongoDB ? t.search_collections : t.search_tables"
                           class="w-full bg-white border border-blue-500/30 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                </div>

                {{-- Primary Table Grid --}}
                <div class="mb-6">
                    <label class="block text-sm font-semibold text-blue-400 mb-3">
                        <span x-text="isMongoDB ? t.primary_collection : t.primary_table"></span> <span class="text-red-400">*</span>
                    </label>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2 max-h-60 overflow-y-auto pr-2">
                        <template x-for="table in filteredTables" :key="table">
                            <button @click="selectPrimaryTable(table)"
                                    class="px-3 py-2 text-xs rounded-lg border text-left truncate transition-all"
                                    :class="primaryTable === table ? 'bg-blue-500/20 border-blue-500 text-blue-400 font-medium' : 'bg-gray-800/30 border-gray-600/50 text-white hover:border-blue-500/50'"
                                    x-text="table"></button>
                        </template>
                    </div>
                </div>

                {{-- Linked Tables (Foreign Keys) --}}
                <div x-show="primaryTableFks.length > 0" class="mb-6">
                    <label class="block text-sm font-semibold text-blue-400 mb-3">{{ __('laraimporter::messages.linked_tables') }}</label>
                    <p class="text-xs text-gray-400 mb-3">{{ __('laraimporter::messages.linked_tables_hint') }}</p>
                    <div class="space-y-2">
                        <template x-for="fk in primaryTableFks" :key="fk.column">
                            <div class="flex items-center p-3 bg-cyan-500/5 border border-cyan-500/20 rounded-lg">
                                <svg class="w-4 h-4 text-cyan-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                <div>
                                    <span class="text-sm text-white font-medium" x-text="fk.foreign_table"></span>
                                    <span class="text-xs text-gray-400 ml-2">{{ __('laraimporter::messages.via') }} <code class="text-blue-400" x-text="fk.column + ' → ' + fk.foreign_table + '.' + fk.foreign_column"></code></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Pivot / Many-to-Many Tables (uses x-html to avoid template x-for bug) --}}
                <div x-show="pivotTables.length > 0" class="mb-6">
                    <label class="block text-sm font-semibold text-purple-400 mb-3">{{ __('laraimporter::messages.many_to_many_tables') }}</label>
                    <p class="text-xs text-gray-400 mb-3">{{ __('laraimporter::messages.many_to_many_hint') }}</p>
                    <div class="space-y-2" x-html="buildPivotListHtml()"></div>
                </div>
            </div>

            <div class="flex items-center justify-between mt-6 pt-6 border-t border-gray-700">
                <button @click="goToStep(2)" class="px-4 py-2 bg-gray-700/50 border border-gray-600 text-white rounded-lg text-sm hover:bg-gray-700 transition-all">
                    {{ __('laraimporter::messages.back') }}
                </button>
                <button @click="loadMappingData()" :disabled="!primaryTable || loading"
                        class="px-6 py-2 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-lg text-sm font-medium hover:shadow-lg hover:shadow-blue-500/25 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                    {{ __('laraimporter::messages.next_map_columns') }}
                </button>
            </div>
        </div>
    </div>

    {{-- ========== STEP 4: Column Mapping ========== --}}
    <div x-show="step === 4" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
        <div class="bg-gray-800/30 backdrop-blur-xl border border-blue-500/30 rounded-xl p-6">
            <div class="flex items-center mb-6">
                <svg class="w-6 h-6 text-blue-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                <h3 class="text-lg font-semibold text-white">{{ __('laraimporter::messages.map_columns') }}</h3>
            </div>

            <p class="text-xs text-gray-400 mb-6">{{ __('laraimporter::messages.mapping_hint') }}</p>

            {{-- Duplicate Handling --}}
            <div class="mb-6 p-4 bg-gray-800/30 border border-gray-600/50 rounded-lg">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-white mb-2">{{ __('laraimporter::messages.duplicate_handling') }}</label>
                        <select x-model="duplicateHandling" class="w-full bg-white border border-blue-500/30 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                            <option value="create">{{ __('laraimporter::messages.always_create_new') }}</option>
                            <option value="skip">{{ __('laraimporter::messages.skip_duplicates') }}</option>
                            <option value="update">{{ __('laraimporter::messages.update_existing') }}</option>
                        </select>
                    </div>
                    <div x-show="duplicateHandling !== 'create'">
                        <label class="block text-sm font-medium text-white mb-2">{{ __('laraimporter::messages.check_duplicate_by') }}</label>
                        {{-- Using x-html instead of template x-for inside select to avoid Alpine.js bug --}}
                        <select x-model="duplicateCheckColumn"
                                x-html="buildDuplicateCheckOptions()"
                                class="w-full bg-white border border-blue-500/30 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                        </select>
                    </div>
                </div>
            </div>

            {{-- Mapping Table --}}
            <div class="overflow-x-auto rounded-lg border border-blue-500/30">
                <table class="w-full text-sm">
                    <thead class="bg-blue-500/10">
                        <tr>
                            <th class="px-4 py-3 text-left text-blue-400 font-medium">{{ __('laraimporter::messages.file_column') }}</th>
                            <th class="px-4 py-3 text-left text-blue-400 font-medium">{{ __('laraimporter::messages.sample_value') }}</th>
                            <th class="px-4 py-3 text-center text-blue-400 font-medium">&rarr;</th>
                            <th class="px-4 py-3 text-left text-blue-400 font-medium" x-text="isMongoDB ? t.target_collection : t.target_table"></th>
                            <th class="px-4 py-3 text-left text-blue-400 font-medium" x-text="isMongoDB ? t.target_field : t.target_column"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(header, hi) in fileHeaders" :key="header">
                            <tr class="border-t border-gray-700/50 hover:bg-blue-500/5">
                                <td class="px-4 py-3">
                                    <span class="text-white font-medium text-xs bg-blue-500/20 px-2 py-1 rounded" x-text="header"></span>
                                </td>
                                <td class="px-4 py-3 text-gray-400 text-xs max-w-[150px] truncate" x-text="getSampleValue(header)"></td>
                                <td class="px-4 py-3 text-center text-blue-500">&rarr;</td>
                                <td class="px-4 py-3">
                                    {{-- Using x-html to avoid template x-for inside select bug --}}
                                    <select x-model="mapping[header].table" @change="onMappingTableChange(header)"
                                            x-html="buildTableOptions()"
                                            class="w-full bg-white border border-blue-500/30 rounded px-2 py-1 text-xs focus:ring-1 focus:ring-blue-500">
                                    </select>
                                </td>
                                <td class="px-4 py-3">
                                    <template x-if="isMongoDB && mapping[header]?.table">
                                        <input type="text" x-model="mapping[header].column" placeholder="{{ __('laraimporter::messages.field_name_placeholder') }}"
                                               class="w-full bg-white border border-blue-500/30 rounded px-2 py-1 text-xs focus:ring-1 focus:ring-blue-500">
                                    </template>
                                    <template x-if="!isMongoDB || !mapping[header]?.table">
                                        <select x-model="mapping[header].column" :disabled="!mapping[header]?.table"
                                                class="w-full bg-white border border-blue-500/30 rounded px-2 py-1 text-xs focus:ring-1 focus:ring-blue-500 disabled:opacity-50">
                                            <option value="">{{ __('laraimporter::messages.select') }}</option>
                                            <template x-for="col in getColumnsForTable(mapping[header]?.table)" :key="col.name">
                                                <option :value="col.name" x-text="col.name + ' (' + col.type + ')' + (col.is_auto ? ' [{{ __("laraimporter::messages.auto") }}]' : '')"></option>
                                            </template>
                                        </select>
                                    </template>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- Required Columns Default Values --}}
            <div x-show="unmappedRequiredColumns.length > 0" x-transition class="mt-6">
                <h4 class="text-sm font-semibold text-red-400 mb-2">{{ __('laraimporter::messages.required_columns_defaults') }}</h4>
                <p class="text-xs text-gray-400 mb-3">{{ __('laraimporter::messages.required_columns_hint') }}</p>
                <div class="space-y-3">
                    <template x-for="col in unmappedRequiredColumns" :key="'def_' + col.name">
                        <div class="flex items-center gap-4 p-3 bg-red-500/5 border border-red-500/20 rounded-lg">
                            <div class="flex-shrink-0 w-40">
                                <span class="text-sm text-white font-medium" x-text="col.name"></span>
                                <span class="text-xs text-red-400 ml-1">*</span>
                                <p class="text-xs text-gray-400" x-text="col.type"></p>
                            </div>
                            <div class="flex-1">
                                <input type="text" :placeholder="'{{ __('laraimporter::messages.default_value_for') }} ' + col.name"
                                       x-model="defaultValues[col.name]"
                                       class="w-full bg-white border border-red-500/30 rounded px-3 py-1.5 text-xs focus:ring-1 focus:ring-red-500 focus:border-red-500">
                            </div>
                            <div class="flex-shrink-0">
                                <span class="text-xs px-2 py-1 rounded"
                                      :class="defaultValues[col.name] ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'"
                                      x-text="defaultValues[col.name] ? '{{ __('laraimporter::messages.set') }}' : '{{ __('laraimporter::messages.required') }}'"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Many-to-Many Pivot Mapping --}}
            <div x-show="pivotTables.length > 0" x-transition class="mt-6">
                <h4 class="text-sm font-semibold text-purple-400 mb-2">{{ __('laraimporter::messages.many_to_many_mapping') }}</h4>
                <p class="text-xs text-gray-400 mb-3">{{ __('laraimporter::messages.many_to_many_mapping_hint') }}</p>
                <div class="space-y-3">
                    <template x-for="pivot in pivotTables" :key="'pv_' + pivot.pivot_table">
                        <div class="p-4 bg-purple-500/5 border border-purple-500/20 rounded-lg">
                            <div class="flex items-center mb-3">
                                <svg class="w-4 h-4 text-purple-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                <span class="text-sm text-white font-medium" x-text="pivot.related_table"></span>
                                <span class="text-xs text-gray-400 ml-2">{{ __('laraimporter::messages.via') }} <code class="text-purple-400" x-text="pivot.pivot_table"></code></span>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-xs text-gray-400 mb-1">{{ __('laraimporter::messages.file_column') }}</label>
                                    <select x-model="pivotMappings[pivot.pivot_table].file_column" class="w-full bg-white border border-purple-500/30 rounded px-2 py-1.5 text-xs focus:ring-1 focus:ring-purple-500">
                                        <option value="">{{ __('laraimporter::messages.skip') }}</option>
                                        <template x-for="h in fileHeaders" :key="'pvh_' + h">
                                            <option :value="h" x-text="h"></option>
                                        </template>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-400 mb-1">{{ __('laraimporter::messages.match_by') }}</label>
                                    <select x-model="pivotMappings[pivot.pivot_table].match_column" class="w-full bg-white border border-purple-500/30 rounded px-2 py-1.5 text-xs focus:ring-1 focus:ring-purple-500">
                                        <template x-for="col in getColumnsForTable(pivot.related_table)" :key="'pvc_' + col.name">
                                            <option :value="col.name" x-text="col.name + (col.is_auto ? ' [auto]' : '')"></option>
                                        </template>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-400 mb-1">{{ __('laraimporter::messages.separator') }}</label>
                                    <input type="text" x-model="pivotMappings[pivot.pivot_table].separator" placeholder=","
                                           class="w-full bg-white border border-purple-500/30 rounded px-2 py-1.5 text-xs focus:ring-1 focus:ring-purple-500">
                                </div>
                            </div>
                            <div x-show="pivotMappings[pivot.pivot_table]?.file_column" class="mt-2">
                                <p class="text-xs text-purple-300">
                                    {{ __('laraimporter::messages.pivot_example') }}
                                    <code class="text-white" x-text="getSampleValue(pivotMappings[pivot.pivot_table]?.file_column) || 'tag1, tag2, tag3'"></code>
                                </p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div class="flex items-center justify-between mt-6 pt-6 border-t border-gray-700">
                <button @click="goToStep(3)" class="px-4 py-2 bg-gray-700/50 border border-gray-600 text-white rounded-lg text-sm hover:bg-gray-700 transition-all">
                    {{ __('laraimporter::messages.back') }}
                </button>
                <button @click="runPreview()" :disabled="!hasMappedColumns() || loading"
                        class="px-6 py-2 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-lg text-sm font-medium hover:shadow-lg hover:shadow-blue-500/25 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                    <span x-show="!loading">{{ __('laraimporter::messages.next_preview') }}</span>
                    <span x-show="loading" class="flex items-center">
                        <svg class="animate-spin h-4 w-4 mr-2" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        {{ __('laraimporter::messages.preparing') }}
                    </span>
                </button>
            </div>
        </div>
    </div>

    {{-- ========== STEP 5: Preview & Import ========== --}}
    <div x-show="step === 5" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
        <div class="bg-gray-800/30 backdrop-blur-xl border border-blue-500/30 rounded-xl p-6">
            <div class="flex items-center mb-6">
                <svg class="w-6 h-6 text-blue-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <h3 class="text-lg font-semibold text-white">{{ __('laraimporter::messages.preview_and_import') }}</h3>
            </div>

            {{-- Summary Cards --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-blue-500/10 border border-blue-500/30 rounded-lg p-4 text-center">
                    <p class="text-2xl font-bold text-blue-400" x-text="importPreview?.total_rows || 0"></p>
                    <p class="text-xs text-gray-400 mt-1">{{ __('laraimporter::messages.total_rows') }}</p>
                </div>
                <div class="bg-blue-500/10 border border-blue-500/30 rounded-lg p-4 text-center">
                    <p class="text-2xl font-bold text-white truncate" x-text="primaryTable"></p>
                    <p class="text-xs text-gray-400 mt-1" x-text="isMongoDB ? t.target_collection_label : t.target_table_label"></p>
                </div>
                <div class="bg-blue-500/10 border border-blue-500/30 rounded-lg p-4 text-center">
                    <p class="text-2xl font-bold text-cyan-400" x-text="Object.keys(mapping).filter(k => mapping[k]?.table).length"></p>
                    <p class="text-xs text-gray-400 mt-1">{{ __('laraimporter::messages.mapped_columns') }}</p>
                </div>
                <div class="bg-blue-500/10 border border-blue-500/30 rounded-lg p-4 text-center">
                    <p class="text-2xl font-bold text-purple-400" x-text="Object.keys(buildRelatedConfig()).length"></p>
                    <p class="text-xs text-gray-400 mt-1">{{ __('laraimporter::messages.related_tables') }}</p>
                </div>
            </div>

            {{-- Queue/Direct Toggle --}}
            <div x-show="!importResults && !importing && !queuedJobId" x-transition class="mb-6 p-4 bg-gray-800/30 rounded-lg"
                 :class="importPreview?.suggest_queue ? 'border border-yellow-500/30' : 'border border-gray-600/50'">
                <div class="flex items-start space-x-3">
                    <svg class="w-5 h-5 mt-0.5 flex-shrink-0" :class="importPreview?.suggest_queue ? 'text-yellow-400' : 'text-blue-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div class="flex-1">
                        <p class="text-sm font-medium mb-2" :class="importPreview?.suggest_queue ? 'text-yellow-300' : 'text-white'">
                            {{ __('laraimporter::messages.processing_mode') }}
                            <template x-if="importPreview?.suggest_queue">
                                <span class="text-xs text-yellow-400 ml-2" x-text="'(' + t.large_dataset_detected.replace(':count', importPreview?.total_rows) + ')'"></span>
                            </template>
                        </p>
                        <div class="flex items-center space-x-4">
                            <label class="flex items-center text-sm text-white cursor-pointer">
                                <input type="radio" x-model="useQueue" :value="false" class="text-blue-500 focus:ring-blue-500 mr-2">
                                {{ __('laraimporter::messages.direct_mode') }}
                            </label>
                            <label class="flex items-center text-sm text-white cursor-pointer">
                                <input type="radio" x-model="useQueue" :value="true" class="text-blue-500 focus:ring-blue-500 mr-2">
                                {{ __('laraimporter::messages.queue_mode') }}
                                <span x-show="importPreview?.suggest_queue" class="ml-1 text-xs text-yellow-400">({{ __('laraimporter::messages.recommended') }})</span>
                            </label>
                        </div>
                        <p class="text-xs text-gray-400 mt-2" x-show="useQueue">{{ __('laraimporter::messages.queue_hint') }}</p>
                    </div>
                </div>
            </div>

            {{-- Preview Data Table --}}
            <div x-show="importPreview?.preview && !importing && !importResults && !queuedJobId" class="mb-6">
                <h4 class="text-sm font-semibold text-blue-400 mb-3">{{ __('laraimporter::messages.preview_hint') }}</h4>
                <div class="overflow-x-auto rounded-lg border border-blue-500/30">
                    <table class="w-full text-xs">
                        <thead class="bg-blue-500/10">
                            <tr>
                                <template x-for="key in importPreview?.preview?.[0] ? Object.keys(importPreview.preview[0]) : []" :key="'ph_' + key">
                                    <th class="px-3 py-2 text-left text-blue-400 font-medium whitespace-nowrap" x-text="key"></th>
                                </template>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(row, ri) in importPreview?.preview || []" :key="'pr_' + ri">
                                <tr class="border-t border-gray-700/50">
                                    <template x-for="key in Object.keys(row)" :key="'pv_' + key + ri">
                                        <td class="px-3 py-2 text-white whitespace-nowrap max-w-[180px] truncate" x-text="row[key] || '—'"></td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Direct Import Progress --}}
            <div x-show="importing && !queuedJobId" class="mb-6">
                <div class="p-6 bg-gray-800/30 border border-blue-500/30 rounded-lg text-center">
                    <svg class="animate-spin h-10 w-10 mx-auto text-blue-400 mb-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <p class="text-sm text-white font-medium mb-1">{{ __('laraimporter::messages.importing_data') }}</p>
                    <p class="text-xs text-gray-400">{{ __('laraimporter::messages.do_not_close') }}</p>
                </div>
            </div>

            {{-- Queued Job Progress --}}
            <div x-show="queuedJobId" x-transition class="mb-6">
                <div class="p-6 bg-gray-800/30 border border-blue-500/30 rounded-lg">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="text-sm font-semibold text-white flex items-center">
                            <template x-if="queuedJob?.status === 'pending'">
                                <svg class="animate-pulse h-5 w-5 mr-2 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
                            </template>
                            <template x-if="queuedJob?.status === 'processing'">
                                <svg class="animate-spin h-5 w-5 mr-2 text-blue-400" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            </template>
                            <template x-if="queuedJob?.status === 'completed'">
                                <svg class="h-5 w-5 mr-2 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </template>
                            <template x-if="queuedJob?.status === 'failed'">
                                <svg class="h-5 w-5 mr-2 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </template>
                            <span x-text="t.background_import + ' — ' + (queuedJob?.status || 'pending')"></span>
                        </h4>
                        <span class="text-xs text-gray-400">{{ __('laraimporter::messages.job') }} #<span x-text="queuedJobId"></span></span>
                    </div>

                    <div class="mb-4">
                        <div class="flex justify-between text-xs text-gray-400 mb-1">
                            <span x-text="(queuedJob?.processed_rows || 0) + ' / ' + (queuedJob?.total_rows || 0) + ' {{ __("laraimporter::messages.total_rows") }}'"></span>
                            <span x-text="(queuedJob?.progress || 0) + '%'"></span>
                        </div>
                        <div class="h-3 bg-gray-700/50 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-500"
                                 :class="queuedJob?.status === 'failed' ? 'bg-red-500' : queuedJob?.status === 'completed' ? 'bg-green-500' : 'bg-gradient-to-r from-blue-600 to-purple-600'"
                                 :style="'width:' + (queuedJob?.progress || 0) + '%'"></div>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3 mb-4">
                        <div class="text-center p-2 bg-green-500/10 rounded-lg">
                            <p class="text-lg font-bold text-green-400" x-text="queuedJob?.inserted || 0"></p>
                            <p class="text-xs text-gray-400">{{ __('laraimporter::messages.inserted') }}</p>
                        </div>
                        <div class="text-center p-2 bg-blue-500/10 rounded-lg">
                            <p class="text-lg font-bold text-blue-400" x-text="queuedJob?.updated || 0"></p>
                            <p class="text-xs text-gray-400">{{ __('laraimporter::messages.updated') }}</p>
                        </div>
                        <div class="text-center p-2 bg-yellow-500/10 rounded-lg">
                            <p class="text-lg font-bold text-yellow-400" x-text="queuedJob?.skipped || 0"></p>
                            <p class="text-xs text-gray-400">{{ __('laraimporter::messages.skipped') }}</p>
                        </div>
                    </div>

                    <div x-show="queuedJob?.status === 'failed'" class="p-3 bg-red-500/10 border border-red-500/30 rounded-lg">
                        <p class="text-sm text-red-300" x-text="queuedJob?.error_message || t.unknown_error"></p>
                    </div>

                    <div x-show="queuedJob?.errors?.length > 0" class="mt-3">
                        <h5 class="text-sm font-medium text-red-400 mb-2">{{ __('laraimporter::messages.row_errors') }} (<span x-text="queuedJob?.errors?.length || 0"></span>)</h5>
                        <div class="max-h-32 overflow-y-auto space-y-1">
                            <template x-for="(err, ei) in (queuedJob?.errors || []).slice(0, 50)" :key="'qerr_' + ei">
                                <div class="text-xs p-2 bg-red-500/10 border border-red-500/20 rounded">
                                    <span class="text-red-300">{{ __('laraimporter::messages.row') }} <span x-text="err.row"></span>:</span>
                                    <span class="text-red-200" x-text="err.message"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Direct Import Results --}}
            <div x-show="importResults && !queuedJobId" x-transition class="mb-6">
                <div class="p-6 bg-green-500/10 border border-green-500/30 rounded-lg">
                    <div class="flex items-center mb-4">
                        <svg class="w-8 h-8 text-green-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <h4 class="text-lg font-semibold text-green-400">{{ __('laraimporter::messages.import_complete') }}</h4>
                    </div>
                    <div class="grid grid-cols-3 gap-4 mb-4">
                        <div class="text-center p-3 bg-green-500/10 rounded-lg">
                            <p class="text-xl font-bold text-green-400" x-text="importResults?.inserted || 0"></p>
                            <p class="text-xs text-gray-400">{{ __('laraimporter::messages.inserted') }}</p>
                        </div>
                        <div class="text-center p-3 bg-blue-500/10 rounded-lg">
                            <p class="text-xl font-bold text-blue-400" x-text="importResults?.updated || 0"></p>
                            <p class="text-xs text-gray-400">{{ __('laraimporter::messages.updated') }}</p>
                        </div>
                        <div class="text-center p-3 bg-yellow-500/10 rounded-lg">
                            <p class="text-xl font-bold text-yellow-400" x-text="importResults?.skipped || 0"></p>
                            <p class="text-xs text-gray-400">{{ __('laraimporter::messages.skipped') }}</p>
                        </div>
                    </div>

                    <div x-show="importResults?.errors?.length > 0" class="mt-4">
                        <h5 class="text-sm font-medium text-red-400 mb-2">{{ __('laraimporter::messages.errors') }} (<span x-text="importResults?.errors?.length || 0"></span>)</h5>
                        <div class="max-h-40 overflow-y-auto space-y-1">
                            <template x-for="(err, ei) in importResults?.errors || []" :key="'err_' + ei">
                                <div class="text-xs p-2 bg-red-500/10 border border-red-500/20 rounded">
                                    <span class="text-red-300">{{ __('laraimporter::messages.row') }} <span x-text="err.row"></span>:</span>
                                    <span class="text-red-200" x-text="err.message"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div x-show="importResults?.image_errors?.length > 0" class="mt-4">
                        <h5 class="text-sm font-medium text-yellow-400 mb-2">{{ __('laraimporter::messages.image_errors') }} (<span x-text="importResults?.image_errors?.length || 0"></span>)</h5>
                        <div class="max-h-40 overflow-y-auto space-y-1">
                            <template x-for="(err, ei) in importResults?.image_errors || []" :key="'ierr_' + ei">
                                <div class="text-xs p-2 bg-yellow-500/10 border border-yellow-500/20 rounded">
                                    <span class="text-yellow-200" x-text="err.value + ': ' + err.error"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between mt-6 pt-6 border-t border-gray-700">
                <button @click="goToStep(4)" :disabled="importing || (queuedJobId && queuedJob?.status === 'processing')"
                        class="px-4 py-2 bg-gray-700/50 border border-gray-600 text-white rounded-lg text-sm hover:bg-gray-700 transition-all disabled:opacity-50">
                    {{ __('laraimporter::messages.back') }}
                </button>
                <div class="flex space-x-3">
                    <template x-if="importResults || (queuedJob?.status === 'completed') || (queuedJob?.status === 'failed')">
                        <a href="{{ route('laraimporter.index') }}" class="px-6 py-2 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-lg text-sm font-medium hover:shadow-lg hover:shadow-blue-500/25 transition-all">
                            {{ __('laraimporter::messages.start_new_import') }}
                        </a>
                    </template>
                    <template x-if="!importResults && !queuedJobId">
                        <button @click="executeImport()" :disabled="importing || loading"
                                class="px-6 py-2 bg-green-600 hover:bg-green-500 text-white rounded-lg text-sm font-medium transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                            <span x-show="!importing">{{ __('laraimporter::messages.execute_import') }}</span>
                            <span x-show="importing" class="flex items-center">
                                <svg class="animate-spin h-4 w-4 mr-2" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                {{ __('laraimporter::messages.importing') }}
                            </span>
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function importWizard() {
    const t = @json($jsTranslations);
    const isExternal = @json($isExt);

    return {
        t,
        isExternal,
        step: isExternal ? 1 : 2,
        steps: @json($jsSteps),
        loading: false,
        globalError: '',

        // Step 1: DB Connection
        db: {
            driver: 'mysql',
            host: '127.0.0.1',
            port: '3306',
            database: '',
            username: 'root',
            password: '',
            authentication_database: 'admin'
        },
        connectionStatus: '',
        connectionMessage: '',

        // Step 2: Upload
        dragOver: false,
        uploading: false,
        uploadProgress: 0,
        uploadedFileName: '',
        filePreview: null,
        suggestQueue: false,
        fileTotalRows: 0,

        // Step 3: Table Selection
        tables: [],
        tableSearch: '',
        primaryTable: '',
        primaryTableColumns: [],
        primaryTableFks: [],
        pivotTables: [],
        pivotMappings: {},
        isMongoDB: false,

        // Step 4: Column Mapping
        fileHeaders: [],
        mapping: {},
        allTableColumns: {},
        duplicateHandling: 'create',
        duplicateCheckColumn: '',
        defaultValues: {},

        // Step 5: Preview & Import
        importPreview: null,
        importing: false,
        importResults: null,
        useQueue: false,
        queuedJobId: null,
        queuedJob: null,
        pollInterval: null,

        // Computed: filtered tables for search
        get filteredTables() {
            if (!this.tableSearch) return this.tables;
            const q = this.tableSearch.toLowerCase();
            return this.tables.filter(t => t.toLowerCase().includes(q));
        },

        // Computed: all tables except primary, sorted
        get allMappableTablesList() {
            return this.tables.filter(t => t !== this.primaryTable).sort();
        },

        // Computed: related table names from FK data
        get relatedTableNames() {
            return [...new Set(this.primaryTableFks.map(fk => fk.foreign_table))];
        },

        // Computed: unmapped required columns needing defaults
        get unmappedRequiredColumns() {
            if (!this.primaryTable || !this.allTableColumns[this.primaryTable]) return [];
            const mappedCols = Object.values(this.mapping)
                .filter(m => m?.table === this.primaryTable && m?.column)
                .map(m => m.column);
            const fkCols = this.primaryTableFks.map(fk => fk.column);
            return this.allTableColumns[this.primaryTable].filter(col =>
                !col.nullable && !col.is_auto && col.default === null
                && !mappedCols.includes(col.name)
                && !fkCols.includes(col.name)
            );
        },

        // --- HTML builder methods (avoid template x-for inside select) ---

        buildTableOptions() {
            let html = '<option value="">{{ __("laraimporter::messages.skip") }}</option>';
            if (this.primaryTable) {
                html += '<option value="' + this.primaryTable + '">' + this.primaryTable + '</option>';
            }
            this.allMappableTablesList.forEach(t => {
                html += '<option value="' + t + '">' + t + '</option>';
            });
            return html;
        },

        buildDuplicateCheckOptions() {
            let html = '<option value="">{{ __("laraimporter::messages.select_column") }}</option>';
            (this.primaryTableColumns || []).forEach(col => {
                html += '<option value="' + col.name + '">' + col.name + '</option>';
            });
            return html;
        },

        buildPivotListHtml() {
            return this.pivotTables.map(p => `
                <div class="flex items-center p-3 bg-purple-500/5 border border-purple-500/20 rounded-lg">
                    <svg class="w-4 h-4 text-purple-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                    <div>
                        <span class="text-sm text-white font-medium">${p.related_table}</span>
                        <span class="text-xs text-gray-400 ml-2">{{ __('laraimporter::messages.via') }} <code class="text-purple-400">${p.pivot_table}</code></span>
                    </div>
                </div>
            `).join('');
        },

        // --- Lifecycle ---

        init() {
            this._beforeUnloadHandler = (e) => {
                if (this.step > (this.isExternal ? 1 : 2) && !this.importResults && !(this.queuedJob?.status === 'completed')) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            };
            window.addEventListener('beforeunload', this._beforeUnloadHandler);
        },

        destroy() {
            this.stopPolling();
            if (this._beforeUnloadHandler) {
                window.removeEventListener('beforeunload', this._beforeUnloadHandler);
            }
        },

        // --- Navigation ---

        goToStep(s) {
            this.step = s;
            this.globalError = '';
        },

        // --- Step 1: Connection ---

        onDriverChange() {
            this.connectionStatus = '';
            this.connectionMessage = '';
            if (this.db.driver === 'mongodb') {
                this.db.port = '27017';
                this.db.username = '';
            } else if (this.db.driver === 'pgsql') {
                this.db.port = '5432';
                this.db.username = 'root';
            } else {
                this.db.port = '3306';
                this.db.username = 'root';
            }
        },

        async testConnection() {
            this.loading = true;
            this.connectionStatus = '';
            try {
                const res = await axios.post('{{ route("laraimporter.test-connection") }}', this.db);
                if (res.data.success) {
                    this.connectionStatus = 'success';
                    this.connectionMessage = res.data.message || ('Connected to ' + this.db.database);
                } else {
                    this.connectionStatus = 'error';
                    this.connectionMessage = res.data.message;
                }
            } catch (e) {
                this.connectionStatus = 'error';
                this.connectionMessage = e.response?.data?.message || e.message;
            }
            this.loading = false;
        },

        // --- Step 2: File Upload ---

        handleFileSelect(event) {
            const file = event.target.files?.[0];
            if (file) this.uploadFile(file);
        },

        handleFileDrop(event) {
            this.dragOver = false;
            const file = event.dataTransfer?.files?.[0];
            if (file) this.uploadFile(file);
        },

        async uploadFile(file) {
            this.uploading = true;
            this.uploadProgress = 0;
            this.uploadedFileName = '';
            this.filePreview = null;
            const formData = new FormData();
            formData.append('file', file);
            try {
                const res = await axios.post('{{ route("laraimporter.upload-file") }}', formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                    onUploadProgress: (e) => {
                        this.uploadProgress = Math.round((e.loaded / (e.total || 1)) * 100);
                    }
                });
                if (res.data.success) {
                    this.uploadedFileName = file.name;
                    this.filePreview = res.data.preview;
                    this.suggestQueue = res.data.suggest_queue || false;
                    this.fileTotalRows = res.data.total_rows || 0;
                    this.useQueue = this.suggestQueue;
                } else {
                    this.globalError = res.data.message;
                }
            } catch (e) {
                this.globalError = e.response?.data?.message || 'Upload failed';
            }
            this.uploading = false;
        },

        clearFile() {
            this.uploadedFileName = '';
            this.filePreview = null;
            this.suggestQueue = false;
            if (this.$refs.fileInput) {
                this.$refs.fileInput.value = '';
            }
        },

        // --- Step 3: Table Selection ---

        async loadTables() {
            this.loading = true;
            try {
                const res = await axios.get('{{ route("laraimporter.get-tables") }}');
                this.tables = res.data.tables || [];
                this.isMongoDB = res.data.is_mongodb || false;
            } catch (e) {
                this.globalError = e.response?.data?.message || 'Failed to load tables';
            }
            this.loading = false;
        },

        async selectPrimaryTable(table) {
            this.primaryTable = table;
            this.loading = true;
            try {
                const res = await axios.post('{{ route("laraimporter.get-columns") }}', { table });
                this.primaryTableColumns = res.data.columns || [];
                this.primaryTableFks = res.data.foreign_keys || [];

                const pivots = res.data.pivot_tables || [];
                this.pivotTables = [...pivots];

                const mappings = {};
                pivots.forEach(p => {
                    mappings[p.pivot_table] = { file_column: '', match_column: '', separator: ',' };
                });
                this.pivotMappings = mappings;
            } catch (e) {
                this.globalError = e.response?.data?.message || 'Failed to load columns';
            }
            this.loading = false;
        },

        // --- Step 4: Mapping ---

        async loadMappingData() {
            this.loading = true;
            try {
                const pivotRelatedTables = this.pivotTables.map(p => p.related_table);
                const allRelated = [...new Set([...this.relatedTableNames, ...pivotRelatedTables])];
                const res = await axios.post('{{ route("laraimporter.select-table") }}', {
                    primary_table: this.primaryTable,
                    related_tables: allRelated
                });
                if (res.data.success) {
                    this.fileHeaders = res.data.file_headers;
                    this.allTableColumns = {};
                    this.allTableColumns[this.primaryTable] = res.data.primary_table?.columns || [];
                    Object.keys(res.data.related_tables || {}).forEach(rt => {
                        this.allTableColumns[rt] = res.data.related_tables[rt]?.columns || [];
                    });
                    this.mapping = {};
                    this.fileHeaders.forEach(header => {
                        this.mapping[header] = this.autoSuggestMapping(header);
                    });
                    this.goToStep(4);
                }
            } catch (e) {
                this.globalError = e.response?.data?.message || 'Failed to prepare mapping';
            }
            this.loading = false;
        },

        autoSuggestMapping(fileHeader) {
            const normalized = fileHeader.toLowerCase().replace(/[^a-z0-9]/g, '_').replace(/_+/g, '_');
            const allTables = [this.primaryTable, ...this.relatedTableNames];
            for (const table of allTables) {
                const cols = this.allTableColumns[table] || [];
                const exact = cols.find(c => c.name?.toLowerCase() === normalized);
                if (exact && !exact.is_auto) return { table, column: exact.name };
                const partial = cols.find(c =>
                    normalized.includes(c.name?.toLowerCase()) || c.name?.toLowerCase().includes(normalized)
                );
                if (partial && !partial.is_auto) return { table, column: partial.name };
            }
            if (this.isMongoDB) return { table: this.primaryTable, column: normalized };
            return { table: '', column: '' };
        },

        getColumnsForTable(table) {
            return this.allTableColumns[table] || [];
        },

        async onMappingTableChange(header) {
            if (this.mapping[header]) {
                this.mapping[header].column = '';
            }
            const table = this.mapping[header]?.table;
            if (table && !this.allTableColumns[table]) {
                try {
                    const res = await axios.post('{{ route("laraimporter.get-columns") }}', { table });
                    if (res.data.success) {
                        this.allTableColumns[table] = res.data.columns || [];
                    }
                } catch (e) { /* silent */ }
            }
        },

        getSampleValue(header) {
            return this.filePreview?.rows?.[0]?.[header] || '';
        },

        hasMappedColumns() {
            return Object.values(this.mapping).some(m => m?.table && m?.column);
        },

        buildPivotConfig() {
            const config = [];
            this.pivotTables.forEach(pivot => {
                const pm = this.pivotMappings[pivot.pivot_table];
                if (pm?.file_column) {
                    config.push({
                        file_column: pm.file_column,
                        pivot_table: pivot.pivot_table,
                        primary_fk_column: pivot.primary_fk_column,
                        related_table: pivot.related_table,
                        related_fk_column: pivot.related_fk_column,
                        related_match_column: pm.match_column || 'name',
                        related_reference: pivot.related_reference,
                        separator: pm.separator || ',',
                    });
                }
            });
            return config;
        },

        buildRelatedConfig() {
            const relatedPayload = {};
            this.primaryTableFks.forEach(fk => {
                const mappedCol = Object.values(this.mapping).find(m => m?.table === fk.foreign_table && m?.column);
                if (mappedCol) {
                    relatedPayload[fk.foreign_table] = {
                        fk_column: fk.column,
                        reference_column: fk.foreign_column,
                        match_column: mappedCol.column,
                        create_if_missing: true,
                    };
                }
            });
            return relatedPayload;
        },

        // --- Step 5: Preview & Execute ---

        async runPreview() {
            this.loading = true;
            this.globalError = '';
            const mappingPayload = {};
            Object.keys(this.mapping).forEach(h => {
                if (this.mapping[h]?.table && this.mapping[h]?.column) {
                    mappingPayload[h] = { table: this.mapping[h].table, column: this.mapping[h].column };
                }
            });
            const relatedPayload = this.buildRelatedConfig();
            const pivotPayload = this.buildPivotConfig();
            try {
                const res = await axios.post('{{ route("laraimporter.preview") }}', {
                    mapping: mappingPayload,
                    related_config: relatedPayload,
                    pivot_config: pivotPayload,
                    duplicate_handling: this.duplicateHandling,
                    duplicate_check_column: this.duplicateCheckColumn || null,
                    default_values: this.defaultValues
                });
                if (res.data.success) {
                    this.importPreview = res.data.preview;
                    this.goToStep(5);
                } else {
                    this.globalError = res.data.message;
                }
            } catch (e) {
                this.globalError = e.response?.data?.message || 'Preview failed';
            }
            this.loading = false;
        },

        async executeImport() {
            this.importing = true;
            this.importResults = null;
            this.queuedJobId = null;
            this.queuedJob = null;
            this.globalError = '';
            try {
                const res = await axios.post('{{ route("laraimporter.execute") }}', { use_queue: this.useQueue });
                if (res.data.success) {
                    if (res.data.queued) {
                        this.queuedJobId = res.data.job_id;
                        this.importing = false;
                        this.startPolling();
                    } else {
                        this.importResults = res.data.results;
                    }
                } else {
                    this.globalError = res.data.message;
                }
            } catch (e) {
                this.globalError = e.response?.data?.message || 'Import failed';
            }
            if (!this.queuedJobId) this.importing = false;
        },

        // --- Queue Polling ---

        startPolling() {
            this.pollJobStatus();
            this.pollInterval = setInterval(() => this.pollJobStatus(), 2000);
        },

        stopPolling() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
                this.pollInterval = null;
            }
        },

        async pollJobStatus() {
            if (!this.queuedJobId) return;
            try {
                const res = await axios.post('{{ route("laraimporter.job-status") }}', { job_id: this.queuedJobId });
                if (res.data.success) {
                    this.queuedJob = res.data.job;
                    if (['completed', 'failed'].includes(res.data.job?.status)) {
                        this.stopPolling();
                    }
                }
            } catch (e) { /* retry silently */ }
        },
    };
}
</script>
