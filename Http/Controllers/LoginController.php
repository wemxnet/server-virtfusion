<?php

namespace Extensions\Servers\Virtfusion\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Extensions\Servers\Virtfusion\Server;
use Illuminate\Http\RedirectResponse;
use Throwable;

class LoginController extends Controller
{
    public function __invoke(Order $order): RedirectResponse
    {
        abort_unless(Server::usesVirtfusion($order), 404);

        if ($order->user_id !== auth()->id()) {
            $isMember = $order->members()
                ->where('status', 'active')
                ->where('user_id', auth()->id())
                ->exists();

            abort_unless($isMember, 403);
        }

        if ($order->status !== 'active' || ! $order->external_id) {
            return redirect()->route('orders.view', $order->id)->withErrors(['virtfusion' => 'The server is not active.']);
        }

        try {
            return redirect()->away(Server::loginUrl($order));
        } catch (Throwable) {
            return redirect()->route('orders.view', $order->id)->withErrors(['virtfusion' => 'Something went wrong, please try again later.']);
        }
    }
}
