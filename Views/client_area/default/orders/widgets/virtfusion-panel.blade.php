@if(isset($order) && \Extensions\Servers\Virtfusion\Server::usesVirtfusion($order))
    <x-theme::card class="mb-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-xl font-bold text-gray-900 dark:text-white">Virtual Server</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    @if(!empty($order->data['name']))
                        {{ $order->data['name'] }} ·
                    @endif
                    Manage your server, console, and reinstalls on the VPS panel.
                </p>
                @if(isset($errors) && $errors->has('virtfusion'))
                    <x-theme::form.error :text="$errors->first('virtfusion')" />
                @endif
            </div>
            @if($order->status === 'active' && $order->external_id)
                <x-theme::button.primary :href="route('virtfusion.login', $order)" target="_blank" text="Login to Panel" />
            @endif
        </div>
    </x-theme::card>
@endif
