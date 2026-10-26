@php
    $bsfBase = url('/bookstack-files/assets');
    $bsfVersion = \Mdrisss\BookstackFiles\BookstackFilesServiceProvider::VERSION;
@endphp
<link rel="stylesheet" href="{{ $bsfBase }}/bookstack-files.css?v={{ $bsfVersion }}">

<template id="bsf-icons">
    <span data-icon="file">@icon('file')</span>
    <span data-icon="image">@icon('image')</span>
    <span data-icon="download">@icon('download')</span>
    <span data-icon="delete">@icon('delete')</span>
    <span data-icon="close">@icon('close')</span>
    <span data-icon="export">@icon('export')</span>
</template>

<script type="application/json" id="bsf-translations">@json(trans('bookstack-files::messages'))</script>
<script src="{{ $bsfBase }}/bookstack-files.js?v={{ $bsfVersion }}" nonce="{{ $cspNonce ?? '' }}" defer></script>
