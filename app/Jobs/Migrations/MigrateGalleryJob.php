<?php

namespace App\Jobs\Migrations;

use App\Models\Collection;
use DateTime;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MigrateGalleryJob extends BaseMigrationJob
{
    protected function getMigrationKey(): string
    {
        return 'gallery';
    }

    protected function runMigration(): void
    {
        $collections = DB::connection('stadtwache')
            ->select('SELECT COUNT(id) AS count, topic, MIN(datum) AS datum FROM treffen_bilder GROUP BY topic;');

        $this->setTotal(count($collections));
        $this->log('info', 'Found ' . count($collections) . ' gallery collections to migrate');

        $galleryPath = "C:\\Users\\madit\\PhpstormProjects\\stadtwache\\treffen\\uploads";

        foreach ($collections as $collection) {
            try {
                $collectionItem = Collection::create([
                    'user_id' => 1,
                    'taxonomy_id' => 1,
                    'name' => $collection->topic,
                    'created_at' => $collection->datum
                ]);

                $galleries = DB::connection('stadtwache')
                    ->select('select * from treffen_bilder where topic = ?', [$collection->topic]);

                $this->log('log', "Processing collection: {$collection->topic} ({$collection->count} images)");

                $imageCount = 0;
                $imageErrors = 0;

                foreach ($galleries as $gallery) {
                    try {
                        $fileName = $this->katOrdner($gallery->topic) . "\\" . $gallery->id . ".jpg";
                        $pathToFile = $galleryPath . "\\" . $fileName;
                        $pathToFileTemp = $galleryPath . "\\copy\\" . $fileName;

                        if (!file_exists($pathToFile)) {
                            $imageErrors++;
                            continue;
                        }

                        // Ensure copy directory exists
                        $copyDir = dirname($pathToFileTemp);
                        if (!is_dir($copyDir)) {
                            mkdir($copyDir, 0755, true);
                        }

                        copy($pathToFile, $pathToFileTemp);

                        $topicSlug = Str::slug($gallery->topic, '-');
                        $date = Str::of($gallery->datum)->substr(0, 10);
                        $useFileName = "event-{$topicSlug}-{$date}-{$gallery->id}.jpg";

                        $image = $collectionItem->addMedia($pathToFileTemp)
                            ->usingFileName($useFileName)
                            ->withCustomProperties(['album' => $gallery->topic, 'uploader' => $gallery->uploader])
                            ->toMediaCollection("gallery");

                        if ($gallery->info) {
                            $image->setCustomProperty('caption', $gallery->info);
                        }
                        $image->created_at = DateTime::createFromFormat('Y-m-d H:i:s', $gallery->datum);
                        $image->save();

                        $imageCount++;
                        unset($image);
                    } catch (Exception $e) {
                        $imageErrors++;
                    }

                    // Prevent memory issues
                    if ($imageCount % 20 === 0) {
                        gc_collect_cycles();
                    }
                }

                if ($imageErrors > 0) {
                    $this->log('warning', "Collection {$collection->topic}: {$imageCount} images, {$imageErrors} errors");
                }

                $this->progress($collection->topic);
                unset($collectionItem, $galleries);
            } catch (Exception $e) {
                $this->error("Error creating collection {$collection->topic}: " . $e->getMessage());
            }

            gc_collect_cycles();
        }

        $this->log('info', "Gallery migration complete: {$this->log->processed_items} collections, {$this->log->error_count} errors");
    }

    protected function katOrdner(string $kat): string
    {
        $out = $kat;
        $out = str_replace("ä", "ae", $out);
        $out = str_replace("Ä", "Ae", $out);
        $out = str_replace("ö", "oe", $out);
        $out = str_replace("Ö", "Oe", $out);
        $out = str_replace("ü", "ue", $out);
        $out = str_replace("Ü", "Ue", $out);
        $out = str_replace("é", "e", $out);
        $out = str_replace("è", "e", $out);
        $out = str_replace("ê", "e", $out);
        $out = str_replace("/", "_", $out);
        $out = str_replace("\\", "_", $out);
        $out = str_replace(":", "_", $out);
        $out = str_replace("?", "_", $out);
        $out = str_replace("&", "_", $out);
        $out = str_replace("(", "_", $out);
        $out = str_replace(")", "_", $out);
        $out = str_replace("&szlig;", "ss", htmlentities($out));
        return $out;
    }
}
