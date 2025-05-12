<?php

namespace App\Repositories;

interface RepositoryInterface
{
    /**
     * 取得所有記錄
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function all();

    /**
     * 取得分頁記錄
     *
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginate(int $perPage = 15);

    /**
     * 建立記錄
     *
     * @param array $data
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function create(array $data);

    /**
     * 更新記錄
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update(int $id, array $data);

    /**
     * 刪除記錄
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id);

    /**
     * 取得單筆記錄
     *
     * @param int $id
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function find(int $id);

    /**
     * 取得單筆記錄，如果不存在則拋出例外
     *
     * @param int $id
     * @return \Illuminate\Database\Eloquent\Model
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findOrFail(int $id);
} 