<?php

namespace App\Console\Commands;

use App\Models\Okpd2Item;
use Illuminate\Console\Command;

class ValidateOkpd2HierarchyCommand extends Command
{
    protected $signature = 'okpd2:validate-hierarchy';
    protected $description = 'Validate OKPD2 parent/child relationships against hierarchy rules';

    /**
     * Reference cases from дерево.docx / classifikators.ru.
     *
     * @var array<string, list<string>>
     */
    private array $referenceCases = [
        '16' => ['16.1', '16.2'],
        '16.2' => ['16.21', '16.22', '16.23', '16.24', '16.29'],
        '16.29' => ['16.29.1', '16.29.2', '16.29.9'],
        '16.29.2' => ['16.29.21', '16.29.22', '16.29.23', '16.29.24', '16.29.25'],
        '16.29.25' => ['16.29.25.110', '16.29.25.120', '16.29.25.130', '16.29.25.140'],
        '16.29.25.130' => [],
        '16.10.10' => ['16.10.10.110', '16.10.10.120', '16.10.10.130', '16.10.10.140', '16.10.10.150', '16.10.10.160'],
        '16.10.10.110' => ['16.10.10.111', '16.10.10.112', '16.10.10.113', '16.10.10.114', '16.10.10.115', '16.10.10.119'],
        '99' => ['99.0'],
        '99.0' => ['99.00'],
        '99.00' => ['99.00.1'],
        '99.00.1' => ['99.00.10'],
        '99.00.10' => ['99.00.10.000'],
        '99.00.10.000' => [],
    ];

    public function handle(): int
    {
        $mismatches = 0;

        Okpd2Item::query()->orderBy('code')->chunk(1000, function ($items) use (&$mismatches) {
            foreach ($items as $item) {
                $expected = Okpd2Item::resolveParentCode($item->code);

                if ($item->parent_code !== $expected) {
                    $mismatches++;

                    if ($mismatches <= 20) {
                        $this->error("{$item->code}: stored={$item->parent_code}, expected={$expected}");
                    }
                }
            }
        });

        if ($mismatches > 20) {
            $this->error('... and '.($mismatches - 20).' more parent mismatches');
        }

        $caseFailures = 0;

        foreach ($this->referenceCases as $parent => $expectedChildren) {
            $actual = Okpd2Item::query()
                ->where('parent_code', $parent)
                ->orderBy('code')
                ->pluck('code')
                ->all();

            if ($actual !== $expectedChildren) {
                $caseFailures++;
                $this->error("Case {$parent}: expected ".count($expectedChildren).', got '.count($actual));

                if ($expectedChildren !== []) {
                    $this->line('  expected: '.implode(', ', $expectedChildren));
                }

                if ($actual !== []) {
                    $this->line('  actual:   '.implode(', ', $actual));
                }
            }
        }

        if ($mismatches === 0 && $caseFailures === 0) {
            $this->info('Hierarchy validation passed.');

            return self::SUCCESS;
        }

        $this->error("Validation failed: {$mismatches} parent mismatches, {$caseFailures} reference cases.");

        return self::FAILURE;
    }
}
