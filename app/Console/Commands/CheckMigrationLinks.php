<?php

namespace App\Console\Commands;

use App\Models\Migration\MigrationLink;
use App\Services\Migration\LinkAudit;
use Illuminate\Console\Command;

/**
 * The link report from the command line.
 *
 * The dashboard works through the links in batches because it has to
 * answer the browser between them. A few thousand links is an hour of
 * waiting on other people's servers, and that belongs in a terminal -
 * the same reason the big imports have their own command. Stopping it
 * loses nothing: every verdict is written as it arrives, and the next
 * run carries on with what is still pending.
 */
class CheckMigrationLinks extends Command
{
    protected $signature = 'migration:check-links
        {--scan : Read the content again before checking}
        {--scan-only : Only read the content, ask no servers}
        {--hosts= : The old site\'s domains, comma separated, saved for later runs}
        {--scopes=external,legacy : Which links to check (internal, legacy, external)}
        {--limit=0 : Stop after this many links (0 = all of them)}
        {--batch=10 : How many to ask at once}
        {--recheck : Ask again about links that already have a verdict}';

    protected $description = 'Find the links in imported content and check whether they still lead anywhere';

    public function handle(LinkAudit $audit): int
    {
        if ($hosts = (string) $this->option('hosts')) {
            $saved = $audit->setLegacyHosts($hosts);
            $this->info('The old site: ' . (implode(', ', $saved) ?: 'none given'));
        }

        $scopes = $this->scopes();

        if ($scopes === []) {
            $this->error('No usable scope given. Use internal, legacy or external.');

            return self::FAILURE;
        }

        if ($this->option('scan') || $this->option('scan-only') || MigrationLink::count() === 0) {
            $this->scan($audit);
        }

        if ($this->option('scan-only')) {
            $this->report($audit);

            return self::SUCCESS;
        }

        if ($this->option('recheck')) {
            $this->line('Put ' . $audit->reset($scopes) . ' links back in the queue');
        }

        $this->checkAll($audit, $scopes);
        $this->report($audit);

        return self::SUCCESS;
    }

    private function scan(LinkAudit $audit): void
    {
        $this->info('Reading the content…');

        $result = $audit->scan(function (string $place, int $records) {
            $this->output->write("\r  {$records} pieces of content read ({$place})   ");
        });

        $this->output->write("\r");
        $this->line("Found {$result['links']} distinct links in {$result['records']} pieces of content"
            . ($result['removed'] ? ", dropped {$result['removed']} that are gone from the content" : ''));

        if ($result['truncated']) {
            $this->warn('Stopped at ' . LinkAudit::MAX_LINKS . ' links — the report is not complete');
        }
    }

    /**
     * @param  string[]  $scopes
     */
    private function checkAll(LinkAudit $audit, array $scopes): void
    {
        $waiting = MigrationLink::query()->whereIn('scope', $scopes)->checkable()->count();
        $limit   = (int) $this->option('limit');
        $total   = $limit > 0 ? min($limit, $waiting) : $waiting;

        if ($total === 0) {
            $this->line('Nothing waiting to be checked. Use --recheck to ask again.');

            return;
        }

        $this->info("Asking about {$total} links…");

        $bar     = $this->output->createProgressBar($total);
        $batch   = max(1, (int) $this->option('batch'));
        $checked = 0;
        $broken  = 0;

        $bar->start();

        while ($checked < $total) {
            $result = $audit->check($scopes, min($batch, $total - $checked));

            if ($result['checked'] === 0) {
                break;
            }

            $checked += $result['checked'];
            $broken += $result['broken'];
            $bar->advance($result['checked']);
        }

        $bar->finish();
        $this->newLine(2);
        $this->line("Checked {$checked} links, {$broken} of them gone");
    }

    private function report(LinkAudit $audit): void
    {
        $summary = $audit->summary();

        $this->newLine();
        $this->table(
            ['Points at', 'Links', 'Used', 'Gone', 'Fine', 'Unsure', 'Not checked'],
            collect($summary['scopes'])->map(fn (array $scope, string $key) => [
                $key,
                $scope['links'],
                $scope['uses'],
                $scope['statuses'][MigrationLink::STATUS_BROKEN] ?? 0,
                $scope['statuses'][MigrationLink::STATUS_OK] ?? 0,
                $scope['statuses'][MigrationLink::STATUS_UNSURE] ?? 0,
                $scope['statuses'][MigrationLink::STATUS_PENDING] ?? 0,
            ])->values()->all()
        );

        $hosts = collect($audit->hosts(10))->filter(fn (array $host) => $host['broken'] > 0);

        if ($hosts->isNotEmpty()) {
            $this->line('Worst hosts:');

            foreach ($hosts as $host) {
                $this->line("  {$host['host']}: {$host['broken']} of {$host['links']} gone, used {$host['uses']} times");
            }
        }

        $this->newLine();
        $this->line('The whole report, with the pages each link sits on, is on the migration dashboard.');
    }

    /**
     * @return string[]
     */
    private function scopes(): array
    {
        $allowed = [MigrationLink::SCOPE_INTERNAL, MigrationLink::SCOPE_LEGACY, MigrationLink::SCOPE_EXTERNAL];

        $given = array_filter(array_map(
            fn ($scope) => strtolower(trim((string) $scope)),
            explode(',', (string) $this->option('scopes'))
        ));

        return array_values(array_intersect($allowed, $given));
    }
}
