<?php
namespace Globalis\PuppetSkilled\Database\Magic\Revisionable;

use Carbon\Carbon;

class Listener
{

    /**
     * Handle created event.
     *
     * @param \Globalis\PuppetSkilled\Database\Magic\Model $revisioned
     */
    public function created($revisioned)
    {
        $this->log('created', $revisioned);
    }

    /**
     * Handle updated event.
     *
     * @param \Globalis\PuppetSkilled\Database\Magic\Model $revisioned
     */
    public function updated($revisioned)
    {
        if (count($revisioned->getDiff())) {
            $this->log('updated', $revisioned);
        }
    }

    /**
     * Handle deleted event.
     *
     * @param \Globalis\PuppetSkilled\Database\Magic\Model $revisioned
     */
    public function deleted($revisioned)
    {
        $this->log('deleted', $revisioned);
    }

    /**
     * Handle restored event.
     *
     * @param \Globalis\PuppetSkilled\Database\Magic\Model $revisioned
     */
    public function restored($revisioned)
    {
        $this->log('restored', $revisioned);
    }

    /**
     * Log the revision.
     *
     * @param string $action
     * @param  \Globalis\PuppetSkilled\Database\Magic\Model
     */
    protected function log($action, $revisioned)
    {
        $old = $new = [];

        switch ($action) {
            case 'created':
                $new = $revisioned->getNewAttributes();
                break;
            case 'deleted':
                $old = $revisioned->getOldAttributes();
                break;
            case 'updated':
                $old = $revisioned->getOldAttributes();
                $new = $revisioned->getNewAttributes();
                break;
        }
        $user = app()->authentificationService->user();
        $revisioned->revisions()->create([
            'table_name' => $revisioned->getTable(),
            'action' => $action,
            'user_id' => ($user ? $user->getKey() : null),
            'user' => ($user ? json_encode($user->getAttributes()) : null),
            'old' => json_encode($old),
            'new' => json_encode($new),
            'ip' => data_get($_SERVER, 'REMOTE_ADDR'),
            'ip_forwarded' => data_get($_SERVER, 'HTTP_X_FORWARDED_FOR'),
            'created_at' => Carbon::now(),
        ]);
    }
}
