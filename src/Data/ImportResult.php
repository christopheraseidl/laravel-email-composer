<?php

namespace CSeidl\EmailComposer\Data;

readonly class ImportResult
{
    /**
     * @param  array<int, string>  $rejected  line number to reason
     */
    public function __construct(
        public int $created = 0,
        public int $updated = 0,
        public int $skipped = 0,
        public array $rejected = [],
    ) {}

    /** Rejected lines come back in line order, whatever order the results arrive in. */
    public static function combine(self ...$results): self
    {
        $created = $updated = $skipped = 0;
        $rejected = [];

        foreach ($results as $result) {
            $created += $result->created;
            $updated += $result->updated;
            $skipped += $result->skipped;
            $rejected += $result->rejected;
        }

        ksort($rejected);

        return new self($created, $updated, $skipped, $rejected);
    }

    /** @param  array<string, mixed>  $data  from toArray() */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['created'] ?? 0),
            (int) ($data['updated'] ?? 0),
            (int) ($data['skipped'] ?? 0),
            array_map(strval(...), (array) ($data['rejected'] ?? [])),
        );
    }

    /** @return array{created: int, updated: int, skipped: int, rejected: array<int, string>} */
    public function toArray(): array
    {
        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'rejected' => $this->rejected,
        ];
    }
}
