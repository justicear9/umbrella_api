<?php

namespace App\Services;

use App\Models\Constituency;
use App\Models\RoomMessageMention;
use Illuminate\Support\Collection;

class RoomMentionParser
{
    /**
     * @return array{has_comrade: bool, constituencies: list<array{id: int, name: string}>}
     */
    public function parse(string $body): array
    {
        $hasComrade = (bool) preg_match('/(^|[\s])@comrade\b/iu', $body);

        $constituencies = Constituency::query()
            ->orderByRaw('CHAR_LENGTH(name) DESC')
            ->get(['id', 'name']);

        $matched = [];
        $seen = [];

        foreach ($constituencies as $constituency) {
            $name = trim((string) $constituency->name);
            if ($name === '') {
                continue;
            }
            $pattern = '/@'.preg_quote($name, '/').'\b/iu';
            if (! preg_match($pattern, $body)) {
                continue;
            }
            $id = (int) $constituency->id;
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $matched[] = ['id' => $id, 'name' => $name];
        }

        return [
            'has_comrade' => $hasComrade,
            'constituencies' => $matched,
        ];
    }

    /**
     * @param  array{has_comrade: bool, constituencies: list<array{id: int, name: string}>}  $parsed
     * @return list<array{mention_type: string, constituency_id: int|null}>
     */
    public function toMentionRows(array $parsed): array
    {
        $rows = [];
        if ($parsed['has_comrade']) {
            $rows[] = [
                'mention_type' => RoomMessageMention::TYPE_COMRADE,
                'constituency_id' => null,
            ];
        }
        foreach ($parsed['constituencies'] as $c) {
            $rows[] = [
                'mention_type' => RoomMessageMention::TYPE_CONSTITUENCY,
                'constituency_id' => $c['id'],
            ];
        }

        return $rows;
    }

    public function stripComradeMention(string $body): string
    {
        $cleaned = preg_replace('/(^|[\s])@comrade\b/iu', '$1', $body) ?? $body;

        return trim(preg_replace('/\s+/u', ' ', $cleaned) ?? $cleaned);
    }

    /**
     * @return Collection<int, array{type: string, id: int|null, label: string}>
     */
    public function suggestions(?string $query = null, int $limit = 20): Collection
    {
        $q = trim((string) $query);
        $out = collect();

        if ($q === '' || str_starts_with('comrade', strtolower($q))) {
            $out->push([
                'type' => RoomMessageMention::TYPE_COMRADE,
                'id' => null,
                'label' => 'comrade',
            ]);
        }

        $constituencies = Constituency::query()
            ->when($q !== '', fn ($builder) => $builder->where('name', 'like', '%'.$q.'%'))
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name']);

        foreach ($constituencies as $c) {
            $out->push([
                'type' => RoomMessageMention::TYPE_CONSTITUENCY,
                'id' => (int) $c->id,
                'label' => $c->name,
            ]);
        }

        return $out->take($limit)->values();
    }
}
