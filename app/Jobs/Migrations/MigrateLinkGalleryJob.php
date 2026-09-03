<?php

namespace App\Jobs\Migrations;

use App\Models\Collection;
use App\Models\Event\Event;
use App\Models\Event\EventDetail;

class MigrateLinkGalleryJob extends BaseMigrationJob
{
    protected function getMigrationKey(): string
    {
        return 'linkGallery';
    }

    protected function runMigration(): void
    {
        $events = Event::all();
        $this->setTotal($events->count());
        $this->log('info', 'Linking galleries to ' . $events->count() . ' events');

        $linked = 0;
        $notFound = 0;

        foreach ($events as $event) {
            $details = EventDetail::where('event_id', $event->id)->first();

            if (isset($details)) {
                $options = json_decode($details['options']);

                if (isset($options->albumName)) {
                    // name lives in collection_translations — match via translation.
                    $gallery = Collection::whereTranslation('name', $options->albumName)->first();
                    if ($gallery) {
                        $event->relate($gallery);
                        $linked++;
                    } else {
                        $notFound++;
                    }
                }
            }

            $this->progress($event->title);

            // Memory management
            if ($this->log->processed_items % 100 === 0) {
                gc_collect_cycles();
            }
        }

        $this->log('info', "Link Gallery complete: {$linked} linked, {$notFound} not found");
    }
}
