<?php

namespace App\Imports;

use App\Models\Activity;
use App\Models\ImportTemplate;
use App\Models\SourceOfFund;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Str;

class WfpActivitiesImport implements ToModel, WithHeadingRow
{
    protected $config;

    public function __construct()
    {
        $this->config = ImportTemplate::first();
    }

    public function headingRow(): int
    {
        return $this->config->header_row ?? 15;
    }

    public function model(array $row)
    {
        $activityKey = \Illuminate\Support\Str::slug($this->config->activity_col, '_');
        $sourceKey   = \Illuminate\Support\Str::slug($this->config->source_col, '_');

        // If the activity name is empty, we skip (normal behavior for merged rows)
        if (empty(trim($row[$activityKey] ?? ''))) {
            return null; 
        }

        // Use trim() and make the search case-insensitive
        $sourceName = trim($row[$sourceKey] ?? '');

        $fundSource = \App\Models\SourceOfFund::where('name', 'LIKE', $sourceName)->first();

        if (!$fundSource) {
            // THIS IS THE CRITICAL CHANGE: Force an error you can actually see
            throw new \Exception("STOP! The Excel contains '{$sourceName}', but your database has no matching Fund Source. Double-check for extra spaces or typos.");
        }

        return new \App\Models\Activity([
            'name'              => $row[$activityKey],
            'budget'            => (float) str_replace([',', '₱', ' '], '', $row[\Illuminate\Support\Str::slug($this->config->budget_col, '_')] ?? 0),
            'source_of_fund_id' => $fundSource->id,
            'budget_line_item'  => $row[\Illuminate\Support\Str::slug($this->config->budget_line_col, '_')] ?? null,
            'objective'         => $row[\Illuminate\Support\Str::slug($this->config->objective_col, '_')] ?? null,
        ]);
    }
}