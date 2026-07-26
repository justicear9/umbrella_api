@php
    $prefix = $prefix ?? 'audience';
    $audienceId = $prefix.'-audience';
    $defaultMode = $defaultMode ?? 'all';
    $defaultTargets = $defaultTargets ?? [];
    $oldMode = old('audience_mode', $defaultMode);
    $oldTargets = collect(old('target_ids', $defaultTargets))->values()->all();
    $geoCatalog = $regions->map(static function ($region) {
        return [
            'id' => $region->id,
            'name' => $region->name,
            'constituencies' => $region->constituencies->map(static function ($c) {
                return ['id' => $c->id, 'name' => $c->name];
            })->values()->all(),
        ];
    })->values()->all();
@endphp

<div
    class="audience-picker"
    data-audience-picker
    data-geo='@json($geoCatalog)'
    data-old-targets='@json($oldTargets)'
>
    <label for="{{ $audienceId }}">Audience</label>
    <select id="{{ $audienceId }}" name="audience_mode" required data-audience-mode>
        <option value="all" @selected($oldMode === 'all')>All Comms</option>
        <option value="group_national" @selected($oldMode === 'group_national')>National Comms</option>
        <option value="group_constituency" @selected(in_array($oldMode, ['group_constituency', 'constituencies'], true))>Constituency Comms</option>
        <option value="regions" @selected($oldMode === 'regions')>Region</option>
    </select>

    <div class="audience-targets" data-audience-targets hidden>
        <label>Targets <span class="muted">(optional)</span></label>
        <div class="tag-field" data-tag-field>
            <div class="tag-chips" data-tag-chips></div>
            <input
                type="text"
                class="tag-search"
                data-tag-search
                placeholder="Type to search and add…"
                autocomplete="off"
                aria-autocomplete="list"
            >
            <div class="tag-suggestions" data-tag-suggestions hidden role="listbox"></div>
        </div>
        <div data-tag-inputs></div>
        <p class="muted" data-tag-hint></p>
    </div>
</div>
