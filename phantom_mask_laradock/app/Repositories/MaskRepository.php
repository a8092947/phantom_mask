<?php

namespace App\Repositories;

use App\Models\Mask;

class MaskRepository extends BaseRepository
{
    public function __construct(Mask $model)
    {
        parent::__construct($model);
    }

    public function findWithLock($id)
    {
        return $this->model->lockForUpdate()->findOrFail($id);
    }
} 