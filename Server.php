<?php

namespace Extensions\Servers\Virtfusion;

use App\Extensions\Foundation\ServerExtension;
use App\Models\Order;
use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\ServerConnection;
use Exception;
use Extensions\Servers\Virtfusion\Providers\EmailServiceProvider;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class Server extends ServerExtension
{
    protected string $id = 'server-virtfusion';

    protected string $name = 'VirtFusion';

    protected string $description = 'Sell virtual servers on a VirtFusion panel with one-click login.';

    protected string $type = 'Server';

    protected string $version = '1.0.0';

    protected array $wemxVersions = ['*'];

    protected array $authors = [
        [
            'name' => 'WemX',
            'email' => 'mubeen@wemx.net',
        ],
    ];

    public function providers(): array
    {
        return [
            EmailServiceProvider::class,
        ];
    }

    public function elements(): array
    {
        return [
            [
                'element' => 'client-order-top-view',
                'view' => 'server-virtfusion::client_area.default.orders.widgets.virtfusion-panel',
            ],
        ];
    }

    public function setConfig(): array
    {
        $doesNotEndWithSlash = function ($attribute, $value, $fail) {
            if (is_string($value) && preg_match('/\/$/', $value)) {
                $fail('VirtFusion URL must not end with a slash "/". It should be like https://panel.example.com');
            }
        };

        return [
            [
                'key' => 'host',
                'name' => 'Host',
                'description' => 'The host / url of the VirtFusion Panel i.e https://panel.example.com',
                'type' => 'url',
                'default_value' => 'https://panel.example.com',
                'rules' => ['required', 'active_url', $doesNotEndWithSlash],
            ],
            [
                'key' => 'api_key',
                'name' => 'API Key',
                'description' => 'The API Key of the VirtFusion Panel',
                'type' => 'password',
                'rules' => ['required'],
            ],
        ];
    }

    public function setPackageConfig(Package $package, ServerConnection $connection): array
    {
        $packages = $this->cachedPackages($connection);

        $packageField = $packages === []
            ? [
                'col' => 'col-12',
                'key' => 'package',
                'name' => 'Package ID',
                'description' => 'ID of the VirtFusion package. Packages could not be loaded from VirtFusion, so enter the ID manually.',
                'type' => 'number',
                'rules' => ['required', 'numeric'],
                'is_configurable' => false,
            ]
            : [
                'col' => 'col-12',
                'key' => 'package',
                'name' => 'Package',
                'description' => 'Select the package to use for this service',
                'type' => 'select',
                'options' => $packages,
                'default_value' => array_key_first($packages),
                'rules' => ['required'],
                'is_configurable' => false,
            ];

        return [
            $packageField,
            [
                'col' => 'col-6',
                'key' => 'hypervisor_group_id',
                'name' => 'Hypervisor Group ID',
                'description' => 'Enter the Hypervisor Group ID to use for this service',
                'type' => 'number',
                'rules' => ['required', 'numeric'],
                'is_configurable' => false,
            ],
            [
                'col' => 'col-6',
                'key' => 'allowed_ips',
                'name' => 'Number of Allowed IPv4 IPs',
                'description' => 'Enter the number of allowed IPv4 IPs for this service',
                'type' => 'number',
                'default_value' => 1,
                'rules' => ['required', 'numeric', 'min:0'],
                'is_configurable' => true,
            ],
        ];
    }

    public static function testConnection(array $credentials)
    {
        self::api($credentials, 'get', '/connect');

        return 'Successfully connected with VirtFusion';
    }

    /**
     * Make a request to the VirtFusion API.
     */
    public static function api(array $credentials, string $method, string $endpoint, array $data = []): Response
    {
        $method = strtolower($method);

        if (! in_array($method, ['get', 'post', 'put', 'delete', 'patch', 'head'])) {
            throw new Exception("[VirtFusion] Invalid method: {$method}");
        }

        $url = rtrim($credentials['host'] ?? '', '/').'/api/v1'.$endpoint;

        $response = Http::withToken($credentials['api_key'] ?? '')
            ->acceptJson()
            ->asJson()
            ->$method($url, $data);

        if ($response->failed()) {
            if ($response->json('errors')) {
                throw new Exception('[VirtFusion] '.json_encode($response->json('errors')));
            }

            if ($response->json('message')) {
                throw new Exception('[VirtFusion] '.$response->json('message'));
            }

            if ($response->unauthorized() || $response->forbidden()) {
                throw new Exception('[VirtFusion] This action is unauthorized! Confirm that API token has the right permissions');
            }

            if ($response->serverError()) {
                throw new Exception("[VirtFusion] Internal Server Error: {$response->status()}");
            }

            throw new Exception('[VirtFusion] Failed to connect to the API. Ensure the API details and hostname are valid.');
        }

        return $response;
    }

    public function create(Order $order, ServerConnection $connection)
    {
        $user = $order->user;
        $package = $order->package;

        if (! $order->hasExternalUser()) {
            try {
                $externalUser = self::api($connection->config, 'post', '/users', [
                    'name' => trim($user->first_name.' '.$user->last_name),
                    'email' => $user->email,
                    'extRelationId' => $user->id,
                    'sendMail' => false,
                ])['data'];
            } catch (Exception $e) {
                throw new Exception("Failed to create user on the panel, please make sure the email isn't already in use or that your name is longer than 10 chars. {$e->getMessage()}");
            }

            $order->createExternalUser([
                'external_id' => $externalUser['id'],
                'username' => $user->email,
                'password' => $externalUser['password'] ?? null,
                'data' => $externalUser,
            ]);

            $this->emailCredentials($order, $connection, $user->email, (string) ($externalUser['password'] ?? ''));
        }

        $response = self::api($connection->config, 'post', '/servers', [
            'packageId' => (int) $package->data('package'),
            'userId' => (int) $order->getExternalUser()->external_id,
            'hypervisorId' => (int) $package->data('hypervisor_group_id'),
            'ipv4' => (int) $order->option('allowed_ips', 1),
        ]);

        $order->update([
            'external_id' => $response['data']['id'],
            'data' => $response['data'],
        ]);
    }

    public function upgradeOrDowngrade(Order $order, PackagePrice $oldPackagePrice, PackagePrice $newPackagePrice, ServerConnection $connection)
    {
        $packageId = $newPackagePrice->package->data('package');

        self::api($connection->config, 'put', "/servers/{$order->external_id}/package/{$packageId}");
    }

    public function suspend(Order $order, ServerConnection $connection)
    {
        self::api($connection->config, 'post', "/servers/{$order->external_id}/suspend");
    }

    public function unsuspend(Order $order, ServerConnection $connection)
    {
        self::api($connection->config, 'post', "/servers/{$order->external_id}/unsuspend");
    }

    public function terminate(Order $order, ServerConnection $connection)
    {
        self::api($connection->config, 'delete', "/servers/{$order->external_id}?delay=5");
    }

    /**
     * Create a one-time login URL for the server owner.
     */
    public static function loginUrl(Order $order): string
    {
        $config = $order->package->serverConnection->config;

        $response = self::api($config, 'post', "/users/{$order->user->id}/serverAuthenticationTokens/{$order->external_id}");

        return rtrim($config['host'] ?? '', '/').$response['data']['authentication']['endpoint_complete'];
    }

    public static function usesVirtfusion(?Order $order): bool
    {
        return $order?->package?->serverConnection?->extension_identifier === 'server-virtfusion';
    }

    protected function emailCredentials(Order $order, ServerConnection $connection, string $email, string $password): void
    {
        $order->user->email([
            'identifier' => 'server.virtfusion.account_created',
            'mailable_type' => Order::class,
            'mailable_id' => $order->id,
            'subject' => 'Panel Account Created',
            'lines' => [
                'Your account has been created on the VPS panel.',
                'You can login using the following details:',
                "Email: {$email}",
                "Password: {$password}",
            ],
            'variables' => [
                'panel_email' => $email,
                'panel_password' => $password,
            ],
            'button' => [
                'text' => 'VPS Panel',
                'url' => $connection->config['host'] ?? route('orders.view', $order->id),
            ],
        ]);
    }

    /**
     * Enabled VirtFusion packages keyed by id.
     *
     * @return array<int|string, string>
     */
    protected function cachedPackages(ServerConnection $connection): array
    {
        $connectionId = $connection->id ?? 'new';

        try {
            return Cache::remember("virtfusion:packages:{$connectionId}", now()->addMinutes(10), function () use ($connection) {
                return collect(self::api($connection->config, 'get', '/packages')['data'] ?? [])
                    ->filter(fn ($item) => $item['enabled'] ?? false)
                    ->mapWithKeys(fn ($item) => [$item['id'] => $item['name']])
                    ->all();
            });
        } catch (\Throwable) {
            return [];
        }
    }
}
