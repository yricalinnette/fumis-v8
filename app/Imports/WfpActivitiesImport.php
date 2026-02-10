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
            $selectedFund = SourceOfFund::findOrFail((int)$this->defaultFundId);
            $finalFundId = $selectedFund->id;

            // 1. Validate Source Name from Excel if provided
            $excelSourceName = trim($row[$keys['source']] ?? '');
            if (!empty($excelSourceName)) {
                $excelFund = SourceOfFund::where('name', $excelSourceName)->first();
                if (!$excelFund || (int)$excelFund->id !== (int)$selectedFund->id) {
                    throw new \Exception("Mismatch: Row says '{$excelSourceName}', but you selected '{$selectedFund->name}'.");
                }
            }

            // 2. CHECK IF ACTIVITY EXISTS
            $activity = Activity::where('source_of_fund_id', $finalFundId)
                                ->where('name', $activityName)
                                ->first();

            if ($activity) {
                // UPDATE EXISTING: Do NOT touch budget
                $activity->update([
                    'uacs_code'        => $row[$keys['uacs']] ?? $activity->uacs_code,
                    'objective'        => $row[$keys['obj']] ?? $activity->objective,
                    'budget_line_item' => $row[$keys['line']] ?? $activity->budget_line_item,
                ]);
                $this->updatedCount++;
            } else {
                // CREATE NEW: Force budget to 0 regardless of Excel value
                $activity = Activity::create([
                    'source_of_fund_id' => $finalFundId,
                    'name'              => $activityName,
                    'uacs_code'         => $row[$keys['uacs']] ?? null,
                    'budget'            => 0, 
                    'budget_adjusted'   => 0, 
                    'objective'         => $row[$keys['obj']] ?? null,
                    'budget_line_item'  => $row[$keys['line']] ?? null,
                ]);
                
                $this->createdCount++;

                // 3. INFORM USER: Log a notification that the budget was ignored
                $this->failures[] = [
                    'row'    => $activityName,
                    'reason' => "New activity detected. Budget set to ₱0.00. Please allocate funds via Budget Realignment."
                ];
            }

            return $activity;

        } catch (\Exception $e) {
            $this->failures[] = [
                'row'    => $activityName,
                'reason' => $e->getMessage()
            ];
            return null;
        }
    }
}