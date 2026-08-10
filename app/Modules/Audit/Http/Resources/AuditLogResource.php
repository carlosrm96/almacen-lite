<?php

namespace App\Modules\Audit\Http\Resources;

use App\Modules\Audit\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AuditLog */
class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'usuario' => $this->whenLoaded('user', fn (): ?string => $this->user?->name),
            'accion' => $this->accion,
            'auditable_type' => class_basename($this->auditable_type),
            'auditable_id' => $this->auditable_id,
            'warehouse_id' => $this->warehouse_id,
            'datos' => $this->datos,
            'created_at' => $this->created_at,
        ];
    }
}
