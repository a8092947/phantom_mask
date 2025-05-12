<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Model;

abstract class BaseRepository implements RepositoryInterface
{
    /**
     * @var Model
     */
    protected $model;

    /**
     * BaseRepository constructor.
     *
     * @param Model $model
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * {@inheritdoc}
     */
    public function all()
    {
        return $this->model->all();
    }

    /**
     * {@inheritdoc}
     */
    public function paginate(int $perPage = 15)
    {
        return $this->model->paginate($perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $data)
    {
        return $this->model->create($data);
    }

    /**
     * {@inheritdoc}
     */
    public function update(int $id, array $data)
    {
        $record = $this->find($id);
        return $record->update($data);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(int $id)
    {
        return $this->model->destroy($id);
    }

    /**
     * {@inheritdoc}
     */
    public function find(int $id)
    {
        return $this->model->find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function findOrFail(int $id)
    {
        return $this->model->findOrFail($id);
    }
} 