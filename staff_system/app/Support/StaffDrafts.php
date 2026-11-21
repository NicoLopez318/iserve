<?php
namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class StaffDrafts
{
    public static function prefix(User $user, string $kind): string
    {
        return 'staff.draft.'.$user->id.'.'.$kind.'.';
    }

    public static function assertOwned(User $user, string $kind, ?string $id): void
    {
        if ($id) {
            abort_unless(preg_match('/^[a-f0-9-]{36}$/', $id) && DB::table('settings')->where('key', self::prefix($user, $kind).$id)->exists(), 404);
        }
    }

    public static function consume(User $user, string $kind, ?string $id): void
    {
        if ($id) {
            DB::table('settings')->where('key', self::prefix($user, $kind).$id)->delete();
        }
    }

    public static function listing(User $user, string $kind): array
    {
        return DB::table('settings')->where('key', 'like', self::prefix($user, $kind).'%')->get()->map(fn ($row) => json_decode($row->value, true))->filter()->values()->all();
    }
}
