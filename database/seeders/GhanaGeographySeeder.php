<?php

namespace Database\Seeders;

use App\Models\Constituency;
use App\Models\Region;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GhanaGeographySeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/ghana_constituencies.json');
        if (! is_file($path)) {
            $this->command?->error('Missing '.$path);

            return;
        }

        /** @var list<array{region: string, constituencies: list<string>}> $rows */
        $rows = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        foreach ($rows as $row) {
            $regionName = trim((string) ($row['region'] ?? ''));
            if ($regionName === '') {
                continue;
            }

            $region = Region::query()->updateOrCreate(
                ['slug' => Str::slug($regionName)],
                ['name' => $regionName]
            );

            foreach ($row['constituencies'] ?? [] as $name) {
                $name = trim((string) $name);
                if ($name === '') {
                    continue;
                }
                Constituency::query()->updateOrCreate(
                    [
                        'region_id' => $region->id,
                        'slug' => Str::slug($name),
                    ],
                    ['name' => $name]
                );
            }
        }

        $this->command?->info(
            'Geography: '.Region::query()->count().' regions, '.Constituency::query()->count().' constituencies'
        );
    }
}
