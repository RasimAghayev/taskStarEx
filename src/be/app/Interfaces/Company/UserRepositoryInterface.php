<?php

namespace App\Interfaces\Company;

interface UserRepositoryInterface
{
    public function index();

    public function getByFin($id);

    public function store(array $data);

    public function update(array $data, $id);

    public function delete($id);
}
