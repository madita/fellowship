<?php

namespace App\Jobs\Migrations;

use App\Models\Event\Event;
use DateTime;
use Exception;
use Illuminate\Support\Facades\DB;

class MigrateEventsJob extends BaseMigrationJob
{
    protected function getMigrationKey(): string
    {
        return 'events';
    }

    protected function runMigration(): void
    {
        $events = DB::connection('stadtwache')->select('select * from treffen_treffen order by entrydate');
        $this->setTotal(count($events));
        $this->log('info', 'Found ' . count($events) . ' events to migrate');

        foreach ($events as $event) {
            try {
                $eventItem = new Event();
                $eventItem->title = html_entity_decode($event->location);
                $eventItem->description = html_entity_decode($event->bericht);
                $eventItem->user_id = 1;
                $eventItem->event_type_id = 1;
                $eventItem->startDate = DateTime::createFromFormat('Ymd', $event->starttag);
                $eventItem->startTime = DateTime::createFromFormat('Hi', $event->startzeit);
                $eventItem->endDate = DateTime::createFromFormat('Ymd', $event->endtag);
                $eventItem->endTime = DateTime::createFromFormat('Hi', $event->endzeit);
                $eventItem->created_at = DateTime::createFromFormat('Ymd', $event->entrydate);

                $options = [
                    'max' => $event->max_teiln,
                    'creator' => $event->entryuser,
                    'lastEditedBy' => $event->lasteditby,
                    'albumName' => $event->foto_topic,
                ];

                $eventItem->save();

                $eventItem->details()->create([
                    'lat' => $event->x,
                    'lng' => $event->y,
                    'options' => json_encode($options),
                ]);

                $this->progress($eventItem->title);
            } catch (MigrationCancelledException $e) {
                throw $e;
            } catch (Exception $e) {
                $this->error("Error migrating event {$event->location}: " . $e->getMessage());
            }

            // Free up memory
            unset($eventItem);

            // Prevent memory issues
            if ($this->log->processed_items % 50 === 0) {
                gc_collect_cycles();
            }
        }

        $this->log('info', "Events migration complete: {$this->log->processed_items} migrated, {$this->log->error_count} errors");
    }
}
