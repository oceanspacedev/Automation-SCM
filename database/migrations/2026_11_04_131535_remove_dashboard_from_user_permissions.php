<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The Dashboard is open to every user and is no longer a permission, so it is taken out of the users' custom access
     * lists (saving a user with it would fail validation).
     */
    public function up(): void
    {
        DB::table('users')->whereNotNull('permissions')->orderBy('id')->get(['id', 'permissions'])
            ->each(function (object $user): void {
                $permissions = json_decode($user->permissions, true);

                if (is_array($permissions) && in_array('dashboard', $permissions, true)) {
                    DB::table('users')->where('id', $user->id)->update([
                        'permissions' => json_encode(array_values(array_diff($permissions, ['dashboard']))),
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     *
     * Nothing to restore: the Dashboard stays open to every user.
     */
    public function down(): void
    {
        //
    }
};
