<?php

namespace App\Models;

use Database\Factories\ProjectContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property string|null $role
 * @property string|null $phone
 * @property string|null $email
 * @property-read Project $project
 */
#[Fillable(['project_id', 'name', 'role', 'phone', 'email'])]
class ProjectContact extends Model
{
    /** @use HasFactory<ProjectContactFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
