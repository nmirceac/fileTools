<?php namespace ColorTools;

trait HasImages
{
    // protected $excludedImagesRoles;

    /**
     * @return mixed
     */
    public function images() {
        if(!empty($this->excludedImagesRoles)) {
            $query = $this->imagesRelationship();
            foreach($this->excludedImagesRoles as $excludedImageRole) {
                $query->wherePivot('role', '!=', $excludedImageRole);
            }
            return $query;
        }
        return $this->imagesRelationship();
    }

    /**
     * @return mixed
     */
    public function imagesRelationship() {
        return $this->morphToMany(\App\ImageStore::class,
            'association',
            'image_associations',
            'association_id',
            'image_id'
        )->withPivot(\App\ImageStore::$withPivot)
         ->orderBy('image_associations.order', 'ASC');
    }

    /**
     * @param $role
     * @return mixed
     */
    public function imageByRole($role)
    {
        return $this->imagesByRole($role)->first();
    }

    /**
     * @param $role
     * @return mixed
     */
    public function imagesByRole($role)
    {
        return $this->imagesRelationship()->wherePivot('role', $role);
    }

    /**
     * @param $imageIds
     * @param bool $delete
     */
    public function clearImage($imageIds, $delete = false)
    {
        if(is_array($imageIds) or (is_object($imageIds) and $imageIds instanceof \Illuminate\Support\Collection)) {
            $deleted = 0;
            foreach($imageIds as $imageId) {
                $deleted += $this->clearSingleImage($imageId, $delete);
            }
            return $deleted;
        } else if(is_object($imageIds) and $imageIds instanceof \App\ImageStore) {
            return $this->clearSingleImage($imageIds->id, $delete);
        } else if(is_numeric($imageIds)) {
            return $this->clearSingleImage($imageIds, $delete);
        } else {
            throw new \Exception('Don\'t understand the $imageIds: '.print_r($imageIds, true));
        }
    }

    /**
     * @param $imageId
     * @param bool $delete
     */
    public function clearSingleImage($imageId, $delete = false)
    {
        $image = $this->imagesRelationship()->where('id', $imageId)->first();
        if(is_null($image)) {
            return false;
        }
        if($delete) {
            $this->imagesRelationship()->where('id', $imageId)->delete();
        } else {
            $this->imagesRelationship()->detach($imageId);
        }
        $this->reorderImagesByRole([], $image->pivot->role);
        return true;
    }

    /**
     * @param $role
     * @param bool $delete
     */
    public function clearImagesByRole($role, $delete = false)
    {
        if($delete) {
            $this->imagesByRole($role)->delete();
        } else {
            foreach($this->imagesByRole($role)->get(['id'])->pluck('id') as $imageId) {
                $this->imagesRelationship()->detach($imageId);
            }
        }

    }

    /**
     * @param bool $delete
     */
    public function clearImages($delete = false)
    {
        if($delete) {
            $this->images()->delete();
        } else {
            foreach($this->images()->get(['id']) as $imageId) {
                $this->imagesRelationship()->detach($imageId);
            }
        }
    }

    /**
     * @param bool $delete
     */
    public function clearAllImages($delete = false)
    {
        if($delete) {
            $this->imagesRelationship()->delete();
        } else {
            foreach($this->imagesRelationship()->get(['id']) as $imageId) {
                $this->imagesRelationship()->detach($imageId);
            }
        }
    }

    /**
     * @param $imageIds
     * @param bool $role
     * @throws \Exception
     */
    public function reorderImagesByRole($imageIds, $role=false)
    {
        if(!empty($imageIds)) {
            $imageIds = array_values($imageIds);
            if(empty($role)) {
                $role = $this->imagesRelationship()->find($imageIds[0])->pivot->role;
            }

            $images = $this->imagesByRole($role);

            if($images->count()!=count($imageIds)) {
                throw new \Exception('Wrong image order count - sent order for '.count($imageIds).' '.
                    str_plural('image', count($imageIds)).' instead of '.$images->count());
            }

            foreach($imageIds as $order=>$imageId) {
                $this->imagesRelationship()->find($imageId)->pivot->update(['order' => ($order+1)]);
            }
        } else {
            $images = $this->imagesByRole($role)->get();
            foreach($images as $order=>$image) {
                $image->pivot->update(['order' => ($order+1)]);
            }
        }
    }
}
