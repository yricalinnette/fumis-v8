<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Objective;

class ObjectiveSeeder extends Seeder
{
    public function run(): void
    {
        $defaultObjectives = [
            'To provide LGUs with competencies and resources for health system strengthening in support of the Health Sector 8-Point Action Agenda',
            'To catalyze the transformation of local health systems to province-wide and city-wide health system',
            'To strengthen engagements with stakeholders towards a well-coordinated and aligned implementation of the 8-Point Action Agenda',
            'To ensure that relevant policies, guidelines, and programs are cascaded to LGUs and other health partners',
            'To ensure efficacy on the provision of technical assistance to LGUs and other health partners towards the achievement of UHC',
            'To ensure systematic preventive and corrective maintenance of all IT equipment and the effective delivery of other related ICT services.',
            'To ensure that internal clients are effectively supported through the transformation of office processes and that external clients receive reliable, high-quality information via the agency website, thereby enhancing operational efficiency and service delivery.',
            'To ensure the cybersecurity posture of DOH-EVCHD digital solutions and applications, safeguarding data integrity, confidentiality, and availability.',
            'To ensure alignment of policies, programs and standards towards sectoral goals on equity, access and quality of care',
            'To ensure efficient utilization of DOH funds',
            'To increase capacity of DOH personnel in order to improve workplace performance.',
            'To ensure compliance with cross-cutting requirements based on standard procedures and timelines in accordance to Anti-Red Tape Authority (ARTA) and other relevant laws',
            'Submission of reportorial requirements.',
            'To ensure delivery of quality service through performance of other task assigned in Committees (As clearing house and Inspection for ICT Supplies / Equipment and Licenses).'
        ];

        foreach ($defaultObjectives as $objText) {
            Objective::firstOrCreate(
                ['title' => trim($objText)],
                ['source' => 'local', 'is_active' => true]
            );
        }
    }
}