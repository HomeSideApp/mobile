<native:column class="w-full h-full bg-theme-background">
    <native:scroll-view class="w-full flex-1">
        <native:column class="w-full gap-4 p-5">

            @if ($needsLogin)
                <native:column class="w-full items-center gap-3 rounded-lg bg-theme-surface-variant px-4 py-10">
                    <native:icon ios="lock.fill" android="lock" :size="28" class="text-theme-primary" />
                    <native:text class="text-base font-semibold text-theme-on-surface">{{ __('app.lists.needs_login') }}</native:text>
                    <native:button :label="__('app.profile.login')" class="w-full" @press="goToLogin" />
                </native:column>
            @elseif ($error)
                <native:column class="w-full rounded-lg bg-theme-destructive/10 p-4">
                    <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
                </native:column>
            @elseif ($householdId === null)
                <native:column class="w-full items-center gap-2 rounded-lg bg-theme-surface-variant px-4 py-10">
                    <native:icon ios="house.fill" android="home" :size="30" class="text-theme-primary" />
                    <native:text class="text-base font-semibold text-theme-on-surface">{{ __('app.house.empty_title') }}</native:text>
                    <native:text class="text-center text-sm text-theme-on-surface-variant">
                        {{ __('app.house.empty_subtitle') }}
                    </native:text>
                </native:column>
            @else
                {{-- Cabecera del hogar --}}
                <native:column class="w-full gap-3 rounded-lg bg-theme-surface p-4">
                    <native:row class="w-full items-center gap-3">
                        <native:column class="h-10 w-10 items-center justify-center rounded-full bg-[{{ $householdColor ?? '#1E6B63' }}]">
                            <native:text class="text-base font-bold" color="#FFFFFF">{{ $householdInitial }}</native:text>
                        </native:column>
                        <native:column class="flex-1 gap-1">
                            <native:text class="text-lg font-semibold text-theme-on-surface">{{ $householdName }}</native:text>
                            @if ($householdDescription)
                                <native:text class="text-sm text-theme-on-surface-variant">{{ $householdDescription }}</native:text>
                            @endif
                        </native:column>
                    </native:row>

                    <native:row class="w-full items-center gap-2">
                        <native:text class="text-xs text-theme-on-surface-variant">
                            {{ trans_choice('app.house.members_count', $membersCount, ['count' => $membersCount]) }}
                        </native:text>
                        @if ($since)
                            <native:spacer />
                            <native:text class="text-xs text-theme-on-surface-variant">
                                {{ __('app.house.since', ['date' => $since]) }}
                            </native:text>
                        @endif
                    </native:row>
                </native:column>

                {{-- Cómo viene la semana --}}
                <native:row class="w-full gap-3">
                    <native:column class="flex-1 items-center gap-1 rounded-lg bg-theme-surface p-3">
                        <native:text class="text-xl font-bold text-theme-on-surface">{{ $pendingCount }}</native:text>
                        <native:text class="text-xs text-theme-on-surface-variant">{{ __('app.house.pending') }}</native:text>
                    </native:column>
                    <native:column class="flex-1 items-center gap-1 rounded-lg bg-theme-surface p-3">
                        <native:text class="text-xl font-bold text-theme-on-surface">{{ $listsCount }}</native:text>
                        <native:text class="text-xs text-theme-on-surface-variant">{{ __('app.house.lists') }}</native:text>
                    </native:column>
                    <native:column class="flex-1 items-center gap-1 rounded-lg bg-theme-surface p-3">
                        <native:text class="text-xl font-bold text-theme-on-surface">{{ $recipesCount }}</native:text>
                        <native:text class="text-xs text-theme-on-surface-variant">{{ __('app.house.recipes') }}</native:text>
                    </native:column>
                </native:row>

                {{-- Módulos habilitados en este hogar --}}
                @if ($modules !== [])
                    <native:column class="w-full gap-2 rounded-lg bg-theme-surface p-4">
                        <native:text class="text-xs font-semibold uppercase text-theme-on-surface-variant">
                            {{ __('app.house.modules') }}
                        </native:text>

                        @foreach ($modules as $module)
                            <native:row class="w-full items-center gap-2">
                                <native:icon
                                    ios="{{ $module['enabled'] ? 'checkmark.circle.fill' : 'circle' }}"
                                    android="{{ $module['enabled'] ? 'check_circle' : 'radio_button_unchecked' }}"
                                    :size="16"
                                    class="{{ $module['enabled'] ? 'text-theme-primary' : 'text-theme-on-surface-variant' }}"
                                />
                                <native:text class="flex-1 text-sm text-theme-on-surface">{{ $module['label'] }}</native:text>
                                <native:text class="text-xs text-theme-on-surface-variant">
                                    {{ $module['enabled'] ? __('app.house.module_enabled') : __('app.house.module_disabled') }}
                                </native:text>
                            </native:row>
                        @endforeach
                    </native:column>
                @endif

                {{-- Miembros --}}
                <native:column class="w-full gap-3 rounded-lg bg-theme-surface p-4">
                    <native:text class="text-xs font-semibold uppercase text-theme-on-surface-variant">
                        {{ __('app.house.members') }}
                    </native:text>

                    @if ($householdId !== null)
                        <native:row class="w-full items-center gap-2">
                            <native:button
                                :label="__('app.house.invite_by_email')"
                                class="flex-1"
                                @press="openInvite"
                            />
                            <native:button
                                :label="__('app.house.invite_share')"
                                variant="secondary"
                                class="flex-1"
                                @press="shareInviteLink"
                            />
                        </native:row>
                    @endif

                    @if ($inviteNotice)
                        <native:text class="text-xs text-theme-primary">{{ $inviteNotice }}</native:text>
                    @endif

                    @if ($inviteError && ! $inviteOpen)
                        <native:text class="text-xs text-theme-destructive">{{ $inviteError }}</native:text>
                    @endif

                    @forelse ($members as $member)
                        <native:row class="w-full items-center gap-3">
                            <native:column class="h-8 w-8 items-center justify-center rounded-full bg-theme-surface-variant">
                                <native:text class="text-sm font-semibold text-theme-on-surface">{{ $member['initial'] }}</native:text>
                            </native:column>
                            <native:column class="flex-1 gap-1">
                                <native:text class="text-sm font-medium text-theme-on-surface">{{ $member['name'] }}</native:text>
                                <native:text class="text-xs text-theme-on-surface-variant">{{ $member['email'] }}</native:text>
                            </native:column>
                            <native:column class="items-end gap-1">
                                <native:text class="text-xs font-semibold text-theme-primary">{{ $member['role_label'] }}</native:text>
                                @if ($member['joined'])
                                    <native:text class="text-[10px] text-theme-on-surface-variant">{{ $member['joined'] }}</native:text>
                                @endif
                            </native:column>
                        </native:row>
                    @empty
                        <native:text class="text-sm text-theme-on-surface-variant">{{ __('app.house.no_members') }}</native:text>
                    @endforelse
                </native:column>
            @endif

            {{-- Invitaciones pendientes: se muestran aunque no haya hogar activo,
                 que es justo el caso de alguien recién invitado. --}}
            @if ($invitations !== [])
                <native:column class="w-full gap-3 rounded-lg bg-theme-surface p-4">
                    <native:text class="text-xs font-semibold uppercase text-theme-on-surface-variant">
                        {{ trans_choice('app.house.invitations_count', $invitationCount, ['count' => $invitationCount]) }}
                    </native:text>

                    @foreach ($invitations as $invitation)
                        <native:column class="w-full gap-2 rounded-lg bg-theme-surface-variant p-3">
                            <native:text class="text-sm font-medium text-theme-on-surface">
                                {{ $invitation['household'] }}
                            </native:text>
                            @if ($invitation['inviter'])
                                <native:text class="text-xs text-theme-on-surface-variant">
                                    {{ __('app.house.invited_by', ['name' => $invitation['inviter']]) }}
                                </native:text>
                            @endif

                            <native:row class="w-full items-center gap-2">
                                <native:button
                                    :label="__('app.house.invitation_accept')"
                                    class="flex-1"
                                    @press="acceptInvitation({{ json_encode($invitation['id']) }})"
                                />
                                <native:button
                                    :label="__('app.house.invitation_decline')"
                                    variant="secondary"
                                    class="flex-1"
                                    @press="declineInvitation({{ json_encode($invitation['id']) }})"
                                />
                            </native:row>
                        </native:column>
                    @endforeach
                </native:column>
            @endif

        </native:column>
    </native:scroll-view>

    {{-- Invitar por email: hoja con un solo campo. --}}
    <native:bottom-sheet :visible="$inviteOpen" @dismiss="closeInvite" detents="medium">
        <native:column class="w-full gap-3 p-5">
            <native:text class="text-base font-semibold text-theme-on-surface">
                {{ __('app.house.invite_title') }}
            </native:text>
            <native:text class="text-xs text-theme-on-surface-variant">
                {{ __('app.house.invite_hint') }}
            </native:text>

            <native:outlined-text-input
                :label="__('app.house.invite_email_label')"
                keyboard="email"
                autocomplete="email"
                native:model.debounce.300ms="inviteEmail"
                @submit="sendInvite"
            />

            @if ($inviteError)
                <native:text class="text-xs text-theme-destructive">{{ $inviteError }}</native:text>
            @endif

            <native:row class="w-full items-center gap-2">
                <native:button
                    :label="__('app.common.cancel')"
                    variant="secondary"
                    class="flex-1"
                    @press="closeInvite"
                />
                <native:button
                    :label="__('app.house.invite_send')"
                    :loading="$inviting"
                    class="flex-1"
                    @press="sendInvite"
                />
            </native:row>
        </native:column>
    </native:bottom-sheet>
</native:column>
