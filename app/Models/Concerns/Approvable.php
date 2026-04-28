<?php

namespace App\Models\Concerns;

use App\Models\Approval;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Str;

trait Approvable
{
    public function approval(): MorphOne
    {
        return $this->morphOne(Approval::class, 'approvable');
    }

    public function scopeApproved($query)
    {
        return $query->whereHas('approval');
    }

    public function scopePending($query)
    {
        return $query->whereDoesntHave('approval');
    }

    public function approve(User $approver): Approval
    {
        return $this->approval()->updateOrCreate(
            ['approvable_type' => get_class($this), 'approvable_id' => $this->id],
            ['approved_at' => now(), 'approved_by' => $approver->id]
        );
    }

    public function unapprove(): void
    {
        $this->approval()->delete();
    }

    public function isApproved(): bool
    {
        return $this->approval()->exists();
    }

    public function isPending(): bool
    {
        return !$this->isApproved();
    }

    public function approver(): ?User
    {
        return $this->approval?->approver;
    }

    public static function getAutoApproveSettingKey(): string
    {
        return 'auto_approve_roles_' . Str::snake(class_basename(static::class));
    }

    public function shouldAutoApprove(?User $user = null): bool
    {
        $user = $user ?? auth()->user();
        if (!$user) return false;

        $roles = Setting::get(static::getAutoApproveSettingKey(), []);
        if (empty($roles)) return false;

        return $user->hasAnyRole($roles);
    }
}
