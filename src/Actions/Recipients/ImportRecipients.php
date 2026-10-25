<?php

namespace CSeidl\EmailComposer\Actions\Recipients;

use CSeidl\EmailComposer\Data\ImportResult;
use CSeidl\EmailComposer\Models\Recipient;
use CSeidl\EmailComposer\Models\RecipientList;
use CSeidl\EmailComposer\Services\ConfigService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Creates or updates recipients from CSV rows, matched by email
 * case-insensitively. Columns other than email, name and locale are merged
 * into metadata. Never resubscribes or restores a recipient.
 */
class ImportRecipients
{
    /** Rows per transaction. 200 stay under SQL Server's 2,100 bound parameters per statement. */
    public const CHUNK_SIZE = 200;

    protected const COLUMNS = ['email', 'name', 'locale'];

    public function __construct(protected ConfigService $config) {}

    /**
     * Rows that share an email must arrive in file order; the last one wins.
     *
     * @param  iterable<int, array<string, string>>  $rows  line number to cells by column
     */
    public function __invoke(iterable $rows, ?RecipientList $list = null): ImportResult
    {
        $results = [];
        $seen = [];
        $chunk = [];

        foreach ($rows as $line => $cells) {
            $chunk[$line] = $cells;

            if (count($chunk) === self::CHUNK_SIZE) {
                $results[] = $this->importChunk($chunk, $list, $seen);
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            $results[] = $this->importChunk($chunk, $list, $seen);
        }

        return ImportResult::combine(...$results);
    }

    /**
     * @param  array<int, array<string, string>>  $rows  line number to cells by column
     * @param  array<string, true>  $seen  lowercased emails written by earlier chunks; this chunk's are added
     */
    protected function importChunk(array $rows, ?RecipientList $list, array &$seen): ImportResult
    {
        [$valid, $rejected] = $this->validate($rows);
        $skipped = 0;
        $unique = [];

        foreach ($valid as [$attributes, $cells]) {
            $key = mb_strtolower((string) $attributes['email']);

            // The last row for an email wins; the one it replaces is skipped.
            if (isset($unique[$key])) {
                $skipped++;
            }

            $unique[$key] = [$attributes, array_diff_key($cells, array_flip(self::COLUMNS))];
        }

        if ($unique === []) {
            return new ImportResult(skipped: $skipped, rejected: $rejected);
        }

        return DB::transaction(function () use ($unique, $list, $rejected, $skipped, &$seen) {
            $matches = $this->matches(array_map('strval', array_keys($unique)));
            $created = $updated = 0;
            $writes = [];
            $ids = [];
            $new = [];

            foreach ($unique as $key => [$attributes, $metadata]) {
                $recipient = $matches->get($key);
                // An earlier chunk wrote this email. This row still wins, but counts
                // as skipped, the same as a repeat within one chunk.
                $repeat = isset($seen[$key]);
                $seen[$key] = true;

                if ($recipient === null) {
                    $writes[] = [
                        'email' => $attributes['email'],
                        'name' => $attributes['name'] ?? $attributes['email'],
                        'locale' => $attributes['locale'],
                        'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR),
                    ];
                    $new[] = $attributes['email'];
                    $created++;
                } elseif ($recipient->trashed()) {
                    $skipped++;
                } else {
                    $write = [
                        'email' => $recipient->email,
                        'name' => $attributes['name'] ?? $recipient->name,
                        'locale' => $attributes['locale'] ?? $recipient->locale,
                        'metadata' => $metadata === []
                            ? $recipient->getRawOriginal('metadata')
                            : json_encode(array_merge($recipient->metadata ?? [], $metadata), JSON_THROW_ON_ERROR),
                    ];

                    if ($write['name'] !== $recipient->name || $write['locale'] !== $recipient->locale || $metadata !== []) {
                        $writes[] = $write;
                    }

                    $ids[] = $recipient->id;
                    $repeat ? $skipped++ : $updated++;
                }
            }

            // Upsert skips model casts and events, hence the encoded metadata.
            Recipient::query()->upsert($writes, ['email'], ['name', 'locale', 'metadata']);

            if ($list !== null) {
                $this->attach($list, array_merge($ids, Recipient::query()->whereIn('email', $new)->pluck('id')->all()));
            }

            return new ImportResult($created, $updated, $skipped, $rejected);
        });
    }

    /**
     * Add recipients to the list, keeping its members. Unlike syncWithoutDetaching(),
     * which loads every member on each call, this looks up only the given ids.
     *
     * @param  array<int, int>  $ids
     */
    protected function attach(RecipientList $list, array $ids): void
    {
        $members = $list->recipients()->newPivotStatement()
            ->where('recipient_list_id', $list->id)
            ->whereIn('recipient_id', $ids)
            ->pluck('recipient_id')
            ->all();

        $list->recipients()->attach(array_values(array_diff($ids, $members)));
    }

    /**
     * Validate a chunk in one pass.
     *
     * @param  array<int, array<string, string>>  $rows  line number to cells by column
     * @return array{array<int, array{array{email: string|null, name: string|null, locale: string|null}, array<string, string>}>, array<int, string>} the valid rows, and the rejected lines' reasons
     */
    protected function validate(array $rows): array
    {
        $attributes = array_map(fn (array $cells) => [
            'email' => $cells['email'] ?? null,
            'name' => $cells['name'] ?? null,
            'locale' => $cells['locale'] ?? null,
        ], $rows);

        $messages = Validator::make($attributes, [
            '*.email' => ['required', 'email', 'max:255'],
            '*.name' => ['nullable', 'max:255'],
            '*.locale' => ['nullable', Rule::in($this->config->locales())],
        ], [], ['*.email' => 'email', '*.name' => 'name', '*.locale' => 'locale'])->errors()->messages();

        // Keys are "<line>.<column>", email first, then name, then locale.
        $reasons = [];

        foreach ($messages as $key => $lineMessages) {
            $reasons[(int) strstr($key, '.', true)][] = implode(' ', $lineMessages);
        }

        $valid = $rejected = [];

        foreach ($rows as $line => $cells) {
            if (isset($reasons[$line])) {
                $rejected[$line] = implode(' ', $reasons[$line]);
            } else {
                $valid[$line] = [$attributes[$line], $cells];
            }
        }

        return [$valid, $rejected];
    }

    /**
     * Recipients, deleted ones included, keyed by lowercased email.
     *
     * @param  array<int, string>  $keys  lowercased emails
     * @return Collection<string, Recipient>
     */
    protected function matches(array $keys): Collection
    {
        $query = Recipient::withTrashed();

        // Postgres and SQLite compare case-sensitively, so they match on
        // lower(email), which the migration indexes for them.
        in_array($query->getModel()->getConnection()->getDriverName(), ['pgsql', 'sqlite'], true)
            ? $query->whereIn(DB::raw('lower(email)'), $keys)
            : $query->whereIn('email', $keys);

        return $query->get()->keyBy(fn (Recipient $recipient) => mb_strtolower($recipient->email));
    }
}
