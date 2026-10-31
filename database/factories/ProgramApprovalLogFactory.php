<?php

namespace Database\Factories;

use App\Models\ProgramApprovalLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgramApprovalLog>
 */
class ProgramApprovalLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'area' => 'jabar',
            'brand' => 'realme',
            'event' => ProgramApprovalLog::TELEMARKETING_NOTIFIED,
            'success' => true,
            'actor' => ProgramApprovalLog::AUTOMATIC,
            'recipient' => '08'.fake()->numerify('##########'),
            'note' => null,
        ];
    }

    /**
     * A WhatsApp that could not be sent.
     */
    public function failed(): static
    {
        return $this->state(fn () => ['success' => false, 'note' => 'Gagal mengirim pesan WhatsApp.']);
    }
}
