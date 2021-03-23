<?php namespace FileTools;

use Illuminate\Database\Eloquent\SoftDeletes;

trait HasSoftDeletes {
    use SoftDeletes {
        SoftDeletes::forceDelete as parentForceDelete;
    }

    /**
     * Force deletes a file
     * @return bool
     * @throws \Exception
     */
    public function forceDelete($skipChecks = false)
    {
        if (!$skipChecks and static::where('id', '!=', $this->id)->where('hash', $this->hash)->count() == 0) {
            self::deleteFromBackendForTrait($this->hash);
        }

        return $this->parentForceDelete();
    }
}
