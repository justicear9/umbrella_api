<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AudienceResolver
{
    public const MODE_ALL = 'all';

    public const MODE_GROUP_NATIONAL = 'group_national';

    public const MODE_GROUP_CONSTITUENCY = 'group_constituency';

    public const MODE_REGIONS = 'regions';

    public const MODE_CONSTITUENCIES = 'constituencies';

    /**
     * @param  list<int>  $targetIds
     * @return Collection<int, User>
     */
    public function usersFor(string $mode, array $targetIds = []): Collection
    {
        $query = User::query()
            ->where('role', User::ROLE_COMMUNICATOR);

        $ids = array_values(array_unique(array_map('intval', $targetIds)));

        switch ($mode) {
            case self::MODE_ALL:
                break;
            case self::MODE_GROUP_NATIONAL:
                $query->where('comms_level', 'national');
                break;
            case self::MODE_GROUP_CONSTITUENCY:
                $query->where('comms_level', 'constituency');
                break;
            case self::MODE_REGIONS:
                // Empty targets = every communicator assigned to any region.
                if ($ids === []) {
                    $query->whereNotNull('region_id');
                } else {
                    $query->whereIn('region_id', $ids);
                }
                break;
            case self::MODE_CONSTITUENCIES:
                if ($ids === []) {
                    return collect();
                }
                $query->whereIn('constituency_id', $ids);
                break;
            default:
                return collect();
        }

        return $query->orderBy('name')->get();
    }

    public function userMatches(User $user, string $mode, array $targetIds = []): bool
    {
        if ($user->role !== User::ROLE_COMMUNICATOR) {
            return false;
        }

        $ids = array_values(array_unique(array_map('intval', $targetIds)));

        return match ($mode) {
            self::MODE_ALL => true,
            self::MODE_GROUP_NATIONAL => $user->comms_level === 'national',
            self::MODE_GROUP_CONSTITUENCY => $user->comms_level === 'constituency',
            self::MODE_REGIONS => $user->region_id !== null && ($ids === [] || in_array((int) $user->region_id, $ids, true)),
            self::MODE_CONSTITUENCIES => $user->constituency_id !== null && in_array((int) $user->constituency_id, $ids, true),
            default => false,
        };
    }

    /**
     * @return Builder<User>
     */
    public function communicatorQueryMatching(string $mode, array $targetIds = []): Builder
    {
        $ids = array_values(array_unique(array_map('intval', $targetIds)));
        $query = User::query()->where('role', User::ROLE_COMMUNICATOR);

        return match ($mode) {
            self::MODE_ALL => $query,
            self::MODE_GROUP_NATIONAL => $query->where('comms_level', 'national'),
            self::MODE_GROUP_CONSTITUENCY => $query->where('comms_level', 'constituency'),
            self::MODE_REGIONS => $ids === []
                ? $query->whereNotNull('region_id')
                : $query->whereIn('region_id', $ids),
            self::MODE_CONSTITUENCIES => $ids === [] ? $query->whereRaw('1=0') : $query->whereIn('constituency_id', $ids),
            default => $query->whereRaw('1=0'),
        };
    }
}
