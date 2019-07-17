<?php namespace FileTools;

trait HasFiles {
    // protected $excludedFilesRoles;

    /**
     * @return mixed
     */
    public function files() {
        if(!empty($this->excludedfilesRoles)) {
            $query = $this->filesRelationship();
            foreach($this->excludedfilesRoles as $excludedfileRole) {
                $query->wherePivot('role', '!=', $excludedfileRole);
            }
            return $query;
        }
        return $this->filesRelationship();
    }

    /**
     * @return mixed
     */
    public function filesRelationship() {
        return $this->morphToMany(\App\File::class,
            'association',
            'file_associations',
            'association_id',
            'file_id'
        )->withPivot(\App\File::$withPivot)
            ->orderBy('file_associations.order', 'ASC');
    }

    /**
     * @param $role
     * @return mixed
     */
    public function fileByRole($role)
    {
        return $this->filesByRole($role)->first();
    }

    /**
     * @param $role
     * @return mixed
     */
    public function filesByRole($role)
    {
        return $this->filesRelationship()->wherePivot('role', $role);
    }

    /**
     * @param $fileId
     * @param bool $delete
     */
    public function clearFile($fileId, $delete = false)
    {
        if($delete) {
            $this->filesRelationship()->where('id', $fileId)->delete();
        } else {
            $this->filesRelationship()->detach($fileId);
        }

    }

    /**
     * @param $role
     * @param bool $delete
     */
    public function clearFilesByRole($role, $delete = false)
    {
        if($delete) {
            $this->filesByRole($role)->delete();
        } else {
            foreach($this->filesByRole($role)->get(['id'])->pluck('id') as $fileId) {
                $this->filesRelationship()->detach($fileId);
            }
        }

    }

    /**
     * @param bool $delete
     */
    public function clearFiles($delete = false)
    {
        if($delete) {
            $this->files()->delete();
        } else {
            foreach($this->files()->get(['id']) as $fileId) {
                $this->filesRelationship()->detach($fileId);
            }
        }
    }

    /**
     * @param bool $delete
     */
    public function clearAllFiles($delete = false)
    {
        if($delete) {
            $this->filesRelationship()->delete();
        } else {
            foreach($this->filesRelationship()->get(['id']) as $fileId) {
                $this->filesRelationship()->detach($fileId);
            }
        }
    }

    /**
     * @param $fileIds
     * @param bool $role
     * @throws \Exception
     */
    public function reorderFilesByRole($fileIds, $role=false)
    {
        if(!empty($fileIds)) {
            $fileIds = array_values($fileIds);
            if(empty($role)) {
                $role = $this->filesRelationship()->find($fileIds[0])->pivot->role;
            }
        }

        $files = $this->filesByRole($role)->get();

        if($files->count()!=count($fileIds)) {
            throw new \Exception('Wrong file order count - sent order for '.count($fileIds).' '.
                Str::plural('file', count($fileIds)).' instead of '.$files->count());
        }

        foreach($fileIds as $order=>$fileId) {
            $this->filesRelationship()->find($fileId)->pivot->update(['order' => ($order+1)]);
        }
    }
}
