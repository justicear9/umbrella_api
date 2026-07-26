<?php

namespace Database\Seeders;

use App\Models\Region;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Idempotent demo communicators across regions for Directory / Chat testing.
 *
 * php artisan db:seed --class=DemoCommunicatorsSeeder --force
 */
class DemoCommunicatorsSeeder extends Seeder
{
    public function run(): void
    {
        $people = [
            ['name' => 'Kwame Asante', 'region' => 'Ashanti', 'party_id' => 'NDC-DEMO-01'],
            ['name' => 'Ama Mensah', 'region' => 'Greater Accra', 'party_id' => 'NDC-DEMO-02'],
            ['name' => 'Kofi Boateng', 'region' => 'Western', 'party_id' => 'NDC-DEMO-03'],
            ['name' => 'Akosua Darko', 'region' => 'Eastern', 'party_id' => 'NDC-DEMO-04'],
            ['name' => 'Yaw Owusu', 'region' => 'Central', 'party_id' => 'NDC-DEMO-05'],
            ['name' => 'Efua Addo', 'region' => 'Volta', 'party_id' => 'NDC-DEMO-06'],
            ['name' => 'Kojo Appiah', 'region' => 'Northern', 'party_id' => 'NDC-DEMO-07'],
            ['name' => 'Abena Sarpong', 'region' => 'Upper East', 'party_id' => 'NDC-DEMO-08'],
            ['name' => 'Fiifi Crentsil', 'region' => 'Western North', 'party_id' => 'NDC-DEMO-09'],
            ['name' => 'Adwoa Nkrumah', 'region' => 'Bono', 'party_id' => 'NDC-DEMO-10'],
        ];

        $password = 'password123';

        foreach ($people as $person) {
            $region = Region::query()
                ->with('constituencies')
                ->where('name', 'like', $person['region'].'%')
                ->first();

            if (! $region || $region->constituencies->isEmpty()) {
                $this->command?->warn("Skip {$person['name']}: region {$person['region']} missing. Run GhanaGeographySeeder first.");

                continue;
            }

            $constituency = $region->constituencies->sortBy('name')->values()->first();

            User::query()->updateOrCreate(
                ['party_id' => $person['party_id']],
                [
                    'role' => User::ROLE_COMMUNICATOR,
                    'name' => $person['name'],
                    'email' => strtolower($person['party_id']).'@party.ndc.local',
                    'password' => $password,
                    'date_of_birth' => '1990-01-15',
                    'occupation' => 'Communicator',
                    'comms_level' => 'constituency',
                    'region_id' => $region->id,
                    'constituency_id' => $constituency->id,
                    'constituency' => $constituency->name,
                ]
            );

            $this->command?->info("{$person['party_id']} → {$person['name']} ({$region->name} / {$constituency->name})");
        }

        $this->command?->info('Password for all demo accounts: '.$password);
    }
}
