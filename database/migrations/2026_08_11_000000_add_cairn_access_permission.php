<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Installation neuve : PermissionsTableSeeder s'en charge déjà.
        if (Permission::query()->count() === 0) {
            return;
        }

        if (Permission::query()->where('id', 335)->exists()) {
            return;
        }

        Permission::query()->insert([
            ['id' => 335, 'title' => 'cairn_access'],
        ]);

        $adminId = DB::table('roles')->where('title', 'Admin')->value('id');
        if ($adminId) {
            Role::query()->findOrFail($adminId)->permissions()->syncWithoutDetaching([335]);
        }
    }

    public function down(): void
    {
        DB::table('permission_role')->where('permission_id', 335)->delete();
        DB::table('permissions')->where('id', 335)->delete();
    }
};
