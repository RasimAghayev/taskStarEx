<?php

namespace App\Repository\Company;

use App\Interfaces\Company\UserRepositoryInterface;
use App\Models\Company\User;
use Illuminate\Support\LazyCollection;

class UserRepository implements UserRepositoryInterface
{
    public function index($search = null, $perPage = 15)
    {
        return $this->buildSearchQuery($search)->paginate((int) $perPage);
    }

    public function all($search = null): LazyCollection
    {
        return $this->buildSearchQuery($search)->cursor();
    }

    /**
     * Shared search-filter builder used by both the paginated index() and
     * the unpaginated all() (CSV export) — keeps the LIKE/ILIKE logic in
     * exactly one place (DRY).
     */
    private function buildSearchQuery($search = null)
    {
        $query = User::query();

        if (! empty($search)) {
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

        return $query->orderBy('surname');
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
