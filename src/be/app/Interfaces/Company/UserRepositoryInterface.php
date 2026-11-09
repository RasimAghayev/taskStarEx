<?php

namespace App\Interfaces\Company;

interface UserRepositoryInterface
{
    public function index($search = null, $perPage = 15);

    public function getByFin($id);

    public function store(array $data);

    public function update(array $data, $id);

    public function delete($id);
}
