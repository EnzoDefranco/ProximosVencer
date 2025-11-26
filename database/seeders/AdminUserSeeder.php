<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run()
    {
        $u = new User();
        $u->name = 'Admin';
        $u->email = 'admin@enro';
        $u->password = Hash::make('admin1234');
        $u->role = 'admin';
        $u->save();
    }
}
