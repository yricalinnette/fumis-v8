<?php

namespace App\Imports;

use App\Models\Activity;
use App\Models\ImportTemplate;
use App\Models\SourceOfFund;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\WithEvents;

class WfpActivitiesImport implements ToModel, WithHeadingRow, WithEvents
{
    protected $config;
    protected $defaultFundId;
    
    public $createdCount = 0;
    public $updatedCount = 0;
    public $failures = []; // This stores our error list

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function(BeforeImport $event) {
                $reader = $event->getReader();
                $sheets = $reader->getDelegate()->getSheetNames();
                
                // Get the actual headers from the file
                $headings = (new HeadingRowImport($this->headingRow()))->toArray($this->filepath);
                $actualHeaders = array_map(function($header) {
                    return \Illuminate\Support\Str::slug($header, '_');
                }, $headings[0][0]);

                // Define the REQUIRED slugs based on your mapping
                $required = [
                    \Illuminate\Support\Str::slug($this->config->activity_col, '_'),
                    \Illuminate\Support\Str::slug($this->config->budget_col, '_')
                ];

                // Check if the required headers exist in the file
                foreach ($required as $req) {
                    if (!in_array($req, $actualHeaders)) {
                        throw new \Exception("INVALID TEMPLATE: The column for '" . str_replace('_', ' ', $req) . "' was not found. Please do not change the header names in the downloaded template.");
                    }
                }
            },
        ];
    }
    
    // You'll need to pass the filepath from the controller to use HeadingRowImport
    protected $filepath;
    public function __construct($fundSourceId, $filepath = null)
    {
        $this->config = ImportTemplate::first();
        $this->defaultFundId = $fundSourceId;
        $this->filepath = $filepath;
    }

    public function headingRow(): int
    {
        return $this->config->header_row ?? 1;
    }

    public function model(array $row)
    {
        $config = $this->config;
        
        // Map keys using slugs from your settings
        $keys = [
            'activity' => Str::slug($config->activity_col, '_'),
            'uacs'     => Str::slug($config->uacs_col, '_'),
            'budget'   => Str::slug($config->budget_col, '_'),
            'source'   => Str::slug($config->source_col, '_'),
            'obj'      => Str::slug($config->objective_col, '_'),
            'line'     => Str::slug($config->budget_line_col, '_'),
        ];

        $activityName = trim($row[$keys['activity']] ?? '');
        if (empty($activityName)) return null;

        try {
            // 1. Get the Selected Fund Source details from the UI dropdown
            $selectedFund = SourceOfFund::findOrFail((int)$this->defaultFundId);
            $finalFundId = $selectedFund->id;

            // 2. Check the Excel "Source" column
            $excelSourceName = trim($row[$keys['source']] ?? '');

            if (!empty($excelSourceName)) {
                // Find what fund the Excel is CLAIMING to be
                $excelFund = SourceOfFund::where('name', $excelSourceName)->first();

                if (!$excelFund) {
                    throw new \Exception("Fund Source '{$excelSourceName}' does not exist in the Library.");
                }

                // 3. THE STRICT BLOCK: 
                // Compare the ID found in Excel against the ID from the UI Dropdown
                if ((int)$excelFund->id !== (int)$selectedFund->id) {
                    throw new \Exception("Mismatch: Row says '{$excelSourceName}', but you selected '{$selectedFund->name}'. Row skipped.");
                }
            }

            // 4. Proceed with Save using the validated finalFundId
            $activity = Activity::updateOrCreate(
                [
                    'source_of_fund_id' => $finalFundId,
                    'name'              => $activityName,
                ],
                [
                    'uacs_code'   => $row[Str::slug($config->uacs_col, '_')] ?? null,
                    'budget'      => (float) str_replace([',', '₱', ' '], '', $row[Str::slug($config->budget_col, '_')] ?? 0),
                    'objective'   => $row[Str::slug($config->objective_col, '_')] ?? null,
                    'budget_line_item' => $row[Str::slug($config->budget_line_col, '_')] ?? null,
                ]
            );

            $activity->wasRecentlyCreated ? $this->createdCount++ : $this->updatedCount++;
            return $activity;

        } catch (\Exception $e) {
            $this->failures[] = [
                'row'    => $activityName,
                'reason' => $e->getMessage()
            ];
            return null; // This row fails, but the import continues for the next rows
        }
    }
}