<?php

namespace App\Interfaces\Company;

use Illuminate\Support\LazyCollection;

interface UserRepositoryInterface
{
    public function index($search = null, $perPage = 15);

    /**
     * All rows matching the given search term, unpaginated, as a memory-safe
     * lazy cursor (for CSV export of a potentially large result set).
     */
    public function all($search = null): LazyCollection;

    public function getByFin($id);

    public function store(array $data);

    public function update(array $data, $id);

    public function delete($id);
}
