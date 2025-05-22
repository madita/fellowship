<?php

namespace App\Listeners;

use Carbon\Carbon;

class RevisionListener
{
    /**
     * Handle created event.
     *
     * @param \Illuminate\Database\Eloquent\Model $revisioned
     */
    public function created($revisioned)
    {
        $this->log('created', $revisioned);
    }

    /**
     * Handle updated event.
     *
     * @param \Illuminate\Database\Eloquent\Model $revisioned
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
     * @param \Illuminate\Database\Eloquent\Model $revisioned
     */
    public function deleted($revisioned)
    {
        $this->log('deleted', $revisioned);
    }

    /**
     * Handle restored event.
     *
     * @param \Illuminate\Database\Eloquent\Model $revisioned
     */
    public function restored($revisioned)
    {
        $this->log('restored', $revisioned);
    }

    /**
     * Log the revision.
     *
     * @param string $action
     * @param  \Illuminate\Database\Eloquent\Model
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

        $revisioned->revisions()->create([
            'revisionable_type' => $revisioned->getTable(),
            'action'            => $action,
            'user_id'           => $this->getSystemUserId(),
            'old_value'         => json_encode($old),
            'new_value'         => json_encode($new),
            'ip'                => data_get($_SERVER, 'REMOTE_ADDR'),
            'ip_forwarded'      => data_get($_SERVER, 'HTTP_X_FORWARDED_FOR'),
            'created_at'        => Carbon::now(),
        ]);
    }

    /**
     * Attempt to find the user id of the currently logged in user
     * Supports Cartalyst Sentry/Sentinel based authentication, as well as stock Auth.
     **/
    public function getSystemUserId()
    {
        return auth()->user()->getAuthIdentifier();
    }
}
