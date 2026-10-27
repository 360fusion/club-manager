<?php

namespace App\Http\Requests\LodgeDirectory;

use App\Http\Requests\AppRequest;
use App\Models\LodgeSchedule;

/**
 * The form posted to LodgeDirectoryController::index().
 */
class IndexLodgeDirectoryRequest extends AppRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:60',
            'order' => 'nullable|string|max:60',
            'day' => 'nullable|in:'.implode(',', LodgeSchedule::DAYS),
            'when' => 'nullable|in:week,month',
            'near' => 'nullable|string|max:100',
            'within' => 'nullable|in:5,10,25,50',
        ];
    }
}
