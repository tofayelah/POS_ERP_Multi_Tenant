<?php

namespace App\Modules\Billing\Services;

use App\Models\Setting;
use App\Models\SettingAuditLog;
use App\Modules\Billing\Contracts\HierarchicalSettingsServiceContract;
use Illuminate\Support\Facades\Cache;

class HierarchicalSettingsService implements HierarchicalSettingsServiceContract
{
    public function get(
        string $key,
        ?string $terminalId = null,
        ?string $branchId = null,
        ?string $companyId = null,
        ?string $tenantId = null,
        mixed $default = null
    ): mixed {
        $cacheKey = 'setting:'.($tenantId ?? 'global').':'.$key.':'.($terminalId ?? 'null').':'.($branchId ?? 'null').':'.($companyId ?? 'null');

        return Cache::remember($cacheKey, 3600, function () use ($key, $terminalId, $branchId, $companyId, $tenantId, $default) {
            // 1. Terminal level (most specific)
            if ($terminalId) {
                $setting = Setting::where('key', $key)
                    ->where('scope_type', 'terminal')
                    ->where('scope_id', $terminalId)
                    ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                    ->first();
                if ($setting) {
                    return $setting->value;
                }
            }

            // 2. Branch level
            if ($branchId) {
                $setting = Setting::where('key', $key)
                    ->where('scope_type', 'branch')
                    ->where('scope_id', $branchId)
                    ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                    ->first();
                if ($setting) {
                    return $setting->value;
                }
            }

            // 3. Company level
            if ($companyId) {
                $setting = Setting::where('key', $key)
                    ->where('scope_type', 'company')
                    ->where('scope_id', $companyId)
                    ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                    ->first();
                if ($setting) {
                    return $setting->value;
                }
            }

            // 4. Tenant level
            if ($tenantId) {
                $setting = Setting::where('key', $key)
                    ->where('scope_type', 'tenant')
                    ->where('tenant_id', $tenantId)
                    ->first();
                if ($setting) {
                    return $setting->value;
                }
            }

            // 5. Platform default level
            $setting = Setting::where('key', $key)
                ->where('scope_type', 'platform')
                ->whereNull('tenant_id')
                ->first();
            if ($setting) {
                return $setting->value;
            }

            return $default;
        });
    }

    public function set(
        string $key,
        mixed $value,
        string $scopeType,
        ?string $scopeId = null,
        ?string $tenantId = null,
        bool $isSecret = false,
        ?string $userId = null
    ): Setting {
        $existing = Setting::where('key', $key)
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->where('tenant_id', $tenantId)
            ->first();

        $oldValue = $existing ? $existing->value : null;

        $setting = Setting::updateOrCreate(
            [
                'key' => $key,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'tenant_id' => $tenantId,
            ],
            [
                'value' => $value,
                'is_secret' => $isSecret,
            ]
        );

        SettingAuditLog::create([
            'tenant_id' => $tenantId,
            'setting_id' => $setting->id,
            'key' => $key,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'old_value' => $oldValue,
            'new_value' => $value,
            'changed_by_user_id' => $userId,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        $this->invalidateCache($tenantId);

        return $setting;
    }

    public function delete(
        string $key,
        string $scopeType,
        ?string $scopeId = null,
        ?string $tenantId = null,
        ?string $userId = null
    ): bool {
        $setting = Setting::where('key', $key)
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $setting) {
            return false;
        }

        SettingAuditLog::create([
            'tenant_id' => $tenantId,
            'setting_id' => $setting->id,
            'key' => $key,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'old_value' => $setting->value,
            'new_value' => null,
            'changed_by_user_id' => $userId,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        $setting->delete();
        $this->invalidateCache($tenantId);

        return true;
    }

    public function invalidateCache(?string $tenantId = null): void
    {
        // Flush all cached settings
        Cache::flush();
    }
}
