@if($bsfEntity)
    <section class="bsf-files mb-xl"
             data-bsf-files
             data-list-url="{{ url('/bookstack-files/entities/' . $bsfEntity->getMorphClass() . '/' . $bsfEntity->id . '/files') }}"
             data-max-size="{{ config('app.upload_limit') }}"
             hidden>
        <h5>{{ trans('bookstack-files::messages.block_title') }}</h5>
        <div class="bsf-files-list" data-bsf-list aria-live="polite">
            <p class="text-muted text-small">{{ trans('bookstack-files::messages.loading') }}</p>
        </div>
        <div class="bsf-files-upload" data-bsf-upload hidden>
            <label class="bsf-dropzone">
                <input type="file" data-bsf-input multiple>
                @icon('upload')
                <span>{{ trans('bookstack-files::messages.upload_prompt') }}</span>
            </label>
            <div class="bsf-progress text-small text-muted" data-bsf-progress></div>
        </div>
    </section>
    @once
        @include('bookstack-files::assets')
    @endonce
@endif
