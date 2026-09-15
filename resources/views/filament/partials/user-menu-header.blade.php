@if ($user)
    <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.875rem 1rem; border-bottom: 1px solid rgba(255,255,255,0.08);">
        <x-filament-panels::avatar.user :user="$user" size="lg" />

        <div style="min-width: 0;">
            <p style="margin: 0; font-weight: 700; font-size: 0.9375rem; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                {{ $user->name }}
            </p>

            @if (filled($user->position))
                <p style="margin: 0.125rem 0 0; font-size: 0.8125rem; color: rgba(255,255,255,0.55); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                    {{ $user->position }}
                </p>
            @endif
        </div>
    </div>
@endif
