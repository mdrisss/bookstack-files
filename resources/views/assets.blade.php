@php
    $afdBase = url('/afridocs/assets');
    $afdVersion = \Afridocs\Files\AfridocsFilesServiceProvider::VERSION;
@endphp
<link rel="stylesheet" href="{{ $afdBase }}/afridocs-files.css?v={{ $afdVersion }}">

<template id="afd-icons">
    <span data-icon="file">@icon('file')</span>
    <span data-icon="image">@icon('image')</span>
    <span data-icon="download">@icon('download')</span>
    <span data-icon="delete">@icon('delete')</span>
    <span data-icon="close">@icon('close')</span>
    <span data-icon="export">@icon('export')</span>
</template>

<script type="application/json" id="afd-translations">@json(trans('afridocs-files::messages'))</script>
<script src="{{ $afdBase }}/afridocs-files.js?v={{ $afdVersion }}" nonce="{{ $cspNonce ?? '' }}" defer></script>
