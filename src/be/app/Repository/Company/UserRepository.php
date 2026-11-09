<?php

namespace App\Repository\Company;

use App\Interfaces\Company\UserRepositoryInterface;
use App\Models\Company\User;

class UserRepository implements UserRepositoryInterface
{
    public function index($search = null, $perPage = 15)
    {
        $query = User::query();

        if (!empty($search)) {
            $operator = User::query()->getModel()->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $term = "%{$search}%";
            $query->where(function ($q) use ($term, $operator) {
                $q->where('fin', $operator, $term)
                    ->orWhere('name', $operator, $term)
                    ->orWhere('surname', $operator, $term)
                    ->orWhere('father', $operator, $term)
                    ->orWhere('email', $operator, $term);
            });
        }

        return $query->orderBy('surname')->paginate((int) $perPage);
    }

    public function getByFin($fin)
    {
        return User::findOrFail($fin);
    }

    public function store(array $data)
    {
        return User::create($data);
    }

    public function update(array $data, $fin)
    {
        $user = User::findOrFail($fin);
        $user->update($data);

        return $user;
    }

    public function delete($fin)
    {
        $user = User::findOrFail($fin);

        return $user->delete();
    }
}
