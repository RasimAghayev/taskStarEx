<?php

namespace App\Repository\Company;

use App\Interfaces\Company\UserRepositoryInterface;
use App\Models\Company\User;
use Illuminate\Support\Facades\Cache;
class UserReposiotry implements UserRepositoryInterface
{
    public function index()
    {
        return User::all();
    }

    public function getByFin($fin)
    {

        $user = User::findOrFail($fin);
    }

    public function store(array $data)
    {
        return User::create($data);
    }

    public function update(array $data, $fin)
    {
        return User::whereFin($fin)->update($data);
    }

    public function delete($fin)
    {
        User::destroy($fin);
    }
}
