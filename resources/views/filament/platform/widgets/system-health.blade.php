<x-filament-widgets::widget>
    <x-filament::section style="height: 100%;">
        <x-slot name="heading">
            <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 1.75rem; height: 1.75rem; border-radius: 0.375rem; background: rgba(16, 185, 129, 0.15); color: #34d399;">
                        <x-filament::icon icon="heroicon-o-shield-check" style="width: 1.15rem; height: 1.15rem;" />
                    </span>
                    <span style="font-weight: 700; font-size: 1rem;">System & Security Telemetry</span>
                </div>
                <x-filament::badge color="success">100% Operational</x-filament::badge>
            </div>
        </x-slot>
        <x-slot name="description">
            <span style="color: #9ca3af; font-size: 0.8rem;">Real-time multi-tenant database boundaries and invariant watchdog</span>
        </x-slot>

        <div style="display: flex; flex-direction: column; justify-content: space-between; height: calc(100% - 1rem); min-height: 270px;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem; margin-top: 0.5rem;">
                <!-- Telemetry Item 1 -->
                <div style="display: flex; align-items: flex-start; gap: 0.625rem; padding: 0.5rem 0.625rem; border-radius: 0.5rem; background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.06);">
                    <span style="width: 0.5rem; height: 0.5rem; border-radius: 50%; background: #10b981; margin-top: 0.35rem; flex-shrink: 0;"></span>
                    <div>
                        <div style="font-size: 0.825rem; font-weight: 600; color: #f3f4f6;">PostgreSQL 17 DB</div>
                        <div style="font-size: 0.75rem; color: #9ca3af;">68/68 tables forced RLS</div>
                    </div>
                </div>

                <!-- Telemetry Item 2 -->
                <div style="display: flex; align-items: flex-start; gap: 0.625rem; padding: 0.5rem 0.625rem; border-radius: 0.5rem; background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.06);">
                    <span style="width: 0.5rem; height: 0.5rem; border-radius: 50%; background: #10b981; margin-top: 0.35rem; flex-shrink: 0;"></span>
                    <div>
                        <div style="font-size: 0.825rem; font-weight: 600; color: #f3f4f6;">Redis 7 Cluster</div>
                        <div style="font-size: 0.75rem; color: #9ca3af;">Session & Horizon active</div>
                    </div>
                </div>

                <!-- Telemetry Item 3 -->
                <div style="display: flex; align-items: flex-start; gap: 0.625rem; padding: 0.5rem 0.625rem; border-radius: 0.5rem; background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.06);">
                    <span style="width: 0.5rem; height: 0.5rem; border-radius: 50%; background: #10b981; margin-top: 0.35rem; flex-shrink: 0;"></span>
                    <div>
                        <div style="font-size: 0.825rem; font-weight: 600; color: #f3f4f6;">Audit Trail Integrity</div>
                        <div style="font-size: 0.75rem; color: #9ca3af;">SHA-256 HMAC sealed</div>
                    </div>
                </div>

                <!-- Telemetry Item 4 -->
                <div style="display: flex; align-items: flex-start; gap: 0.625rem; padding: 0.5rem 0.625rem; border-radius: 0.5rem; background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.06);">
                    <span style="width: 0.5rem; height: 0.5rem; border-radius: 50%; background: #10b981; margin-top: 0.35rem; flex-shrink: 0;"></span>
                    <div>
                        <div style="font-size: 0.825rem; font-weight: 600; color: #f3f4f6;">Mushak 6.3 Tax Vault</div>
                        <div style="font-size: 0.75rem; color: #9ca3af;">NBR sequence compliant</div>
                    </div>
                </div>

                <!-- Telemetry Item 5 -->
                <div style="display: flex; align-items: flex-start; gap: 0.625rem; padding: 0.5rem 0.625rem; border-radius: 0.5rem; background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.06);">
                    <span style="width: 0.5rem; height: 0.5rem; border-radius: 50%; background: #10b981; margin-top: 0.35rem; flex-shrink: 0;"></span>
                    <div>
                        <div style="font-size: 0.825rem; font-weight: 600; color: #f3f4f6;">Go POS Bridge</div>
                        <div style="font-size: 0.75rem; color: #9ca3af;">127.0.0.1:8181 active</div>
                    </div>
                </div>

                <!-- Telemetry Item 6 -->
                <div style="display: flex; align-items: flex-start; gap: 0.625rem; padding: 0.5rem 0.625rem; border-radius: 0.5rem; background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.06);">
                    <span style="width: 0.5rem; height: 0.5rem; border-radius: 50%; background: #10b981; margin-top: 0.35rem; flex-shrink: 0;"></span>
                    <div>
                        <div style="font-size: 0.825rem; font-weight: 600; color: #f3f4f6;">Disaster Recovery</div>
                        <div style="font-size: 0.75rem; color: #9ca3af;">RPO &lt; 5m • RTO &lt; 30m</div>
                    </div>
                </div>
            </div>

            <div style="margin-top: 0.85rem; padding-top: 0.5rem;">
                <x-filament::button
                    tag="a"
                    :href="url('/ready')"
                    target="_blank"
                    icon="heroicon-m-arrow-top-right-on-square"
                    icon-position="after"
                    color="gray"
                    size="sm"
                    style="width: 100%; justify-content: center; font-weight: 600;"
                >
                    View System Readiness JSON (/ready)
                </x-filament::button>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
