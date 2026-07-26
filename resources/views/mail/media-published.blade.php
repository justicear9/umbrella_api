<x-mail::message>
# Hello {{ $user->name }}

New media is available in Comrade AI.

**{{ $asset->title }}**  
Type: {{ strtoupper($asset->kind) }}

@if ($asset->description)
{{ $asset->description }}
@endif

Open the app → **Media** to download.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
