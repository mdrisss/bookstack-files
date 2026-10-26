@if($afridocsEntity)
    <section class="afd-files mb-xl"
             data-afd-files
             data-list-url="{{ url('/afridocs/entities/' . $afridocsEntity->getMorphClass() . '/' . $afridocsEntity->id . '/files') }}"
             data-max-size="{{ config('app.upload_limit') }}"
             hidden>
        <h5>{{ trans('afridocs-files::messages.block_title') }}</h5>
        <div class="afd-files-list" data-afd-list aria-live="polite">
            <p class="text-muted text-small">{{ trans('afridocs-files::messages.loading') }}</p>
        </div>
        <div class="afd-files-upload" data-afd-upload hidden>
            <label class="afd-dropzone">
                <input type="file" data-afd-input multiple>
                @icon('upload')
                <span>{{ trans('afridocs-files::messages.upload_prompt') }}</span>
            </label>
            <div class="afd-progress text-small text-muted" data-afd-progress></div>
        </div>
    </section>
    @once
        @include('afridocs-files::assets')
    @endonce
@endif
