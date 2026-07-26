<x-mail::message>
# Hello {{ $user->name }}

A new notice was published for NDC Communicators.

**{{ $notice->title }}**

{{ $notice->body }}

@if ($notice->link_url)
<x-mail::button :url="$notice->link_url">
Open link
</x-mail::button>
@endif

Open the NDC Communicators app → **Notices** to read it.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
