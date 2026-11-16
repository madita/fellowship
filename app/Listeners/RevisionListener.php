<?php

namespace App\Listeners;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class RevisionListener
{
    /**
     * Handle saving event, before anything is written.
     *
     * Translated attributes live in their own table and are saved as part of
     * this save, so the previous text has to be copied now or it is lost by
     * the time the diff is taken.
     *
     * @param  Model  $revisioned
     */
    public function saving($revisioned)
    {
        if (method_exists($revisioned, 'snapshotTranslatedOriginals')) {
            $revisioned->snapshotTranslatedOriginals();
        }
    }

    /**
     * Handle created event.
     *
     * @param  Model  $revisioned
     */
    public function created($revisioned)
    {
        $this->log('created', $revisioned);

        // saved() runs straight after this, for the same save.
        if (property_exists($revisioned, 'revisionJustCreated')) {
            $revisioned->revisionJustCreated = true;
        }
    }

    /**
     * Handle saved event.
     *
     * Not updated: an edit that only touches a translated attribute leaves the
     * parent row clean, and Eloquent skips performUpdate entirely for a clean
     * model, so updated never fires. Saved fires either way, and Laravel fires
     * it before syncOriginal, so the previous values are still readable here.
     *
     * @param  Model  $revisioned
     */
    public function saved($revisioned)
    {
        // The creation was just recorded by created(); one save, one revision.
        if (property_exists($revisioned, 'revisionJustCreated') && $revisioned->revisionJustCreated) {
            $revisioned->revisionJustCreated = false;

            return;
        }

        if (count($revisioned->getDiff())) {
            $this->log('updated', $revisioned);
        }
    }

    /**
     * Handle deleted event.
     *
     * @param  Model  $revisioned
     */
    public function deleted($revisioned)
    {
        $this->log('deleted', $revisioned);
    }

    /**
     * Handle restored event.
     *
     * @param  Model  $revisioned
     */
    public function restored($revisioned)
    {
        $this->log('restored', $revisioned);
    }

    /**
     * Attempt to find the user id of the currently logged in user
     * Supports Cartalyst Sentry/Sentinel based authentication, as well as stock Auth.
     **/
    public function getSystemUserId()
    {
        return auth()->user()?->getAuthIdentifier();
    }

    /**
     * Log the revision.
     *
     * @param  string  $action
     * @param  Model  $revisioned  The model being revisioned
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
}
