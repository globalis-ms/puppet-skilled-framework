<?php
namespace Globalis\PuppetSkilled\Database\Magic\Lock;

use \Carbon\Carbon;
use \Exception;

trait Lockable
{
    public function lock()
    {
        // Lock GC
        Lock::gc();
        return $this->morphOne(Lock::class, 'lockable', 'lockable_type', 'row_id');
    }

    public function isLocked()
    {
        $user = app()->authentificationService->user();
        return ($this->lock && $this->lock->expired_at->gt(Carbon::now()) && $this->lock->user_id !== $user->getKey());
    }

    public function acquireLock(Carbone $expired_at = null)
    {
        if (!$this->isLocked()) {
            $user = app()->authentificationService->user();
            if ($this->lock) {
                $this->lock->update([
                    'user_id' => $user->id,
                    'expired_at' => ($expired_at?: $this->getDefaultLockTime()),
                    'created_at' => Carbon::now(),
                ]);
            } else {
                $this->lock()->create([
                    'user_id' => $user->id,
                    'expired_at' => ($expired_at?: $this->getDefaultLockTime()),
                    'created_at' => Carbon::now(),
                ]);
            }
            return true;
        }
        return false;
    }

    public function releaseLock()
    {
        if (!$this->isLocked()) {
            return $this->forceReleaseLock();
        }
        return true;
    }

    public function forceReleaseLock()
    {
        if ($this->lock) {
            return $this->lock->delete();
        }
        return true;
    }

    protected function getDefaultLockTime()
    {
        $addTime = property_exists($this, 'lockDefaultTime')
            ? $this->lockDefaultTime
            : 150;
        return Carbon::now()->addSeconds($addTime);
    }
}
