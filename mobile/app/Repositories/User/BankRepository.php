<?php

namespace App\Repositories\User;

use App\Contracts\Repositories\RepositoryInterface;

class BankRepository implements RepositoryInterface
{
    public function all($columns = ['*'])
    {
    }

    public function paginate($perPage = 15, $columns = ['*'])
    {
    }

    public function create(array $data)
    {
    }

    public function update(array $data, $id)
    {
    }

    public function delete($id)
    {
    }

    public function find($id, $columns = ['*'])
    {
    }

    public function findBy($field, $value, $columns = ['*'])
    {
    }
}
