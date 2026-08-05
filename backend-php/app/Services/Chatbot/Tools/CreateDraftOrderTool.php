<?php

namespace App\Services\Chatbot\Tools;

use App\Models\Order;
use App\Models\User;
use App\Services\Chatbot\DraftOrderService;
use App\Services\Chatbot\Exceptions\DraftLimitException;
use App\Services\Chatbot\Exceptions\DraftUnavailableException;
use App\Services\Chatbot\ToolContext;
use App\Services\Chatbot\ToolContract;
use App\Services\Chatbot\ToolResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

final class CreateDraftOrderTool implements ToolContract
{
    private const RATE_LIMIT_WINDOW_SECONDS = 3600;

    private const LOCK_SECONDS = 10;

    private const LOCK_WAIT_SECONDS = 5;

    public function __construct(private readonly DraftOrderService $draftOrders = new DraftOrderService)
    {
    }

    public function name(): string
    {
        return 'create_draft_order';
    }

    /** @return array<string, mixed> */
    public function declaration(): array
    {
        return [
            'name' => $this->name(),
            'description' => 'Crea una propuesta de pedido para un cliente autenticado. No compra, cobra ni reserva stock.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'items' => [
                        'type' => 'array',
                        'description' => 'Entre 1 y 20 productos distintos.',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'product_identifier' => [
                                    'type' => 'string',
                                    'description' => 'ID numérico o slug exacto del producto.',
                                ],
                                'quantity' => [
                                    'type' => 'integer',
                                    'description' => 'Cantidad entera entre 1 y 99.',
                                ],
                            ],
                            'required' => ['product_identifier', 'quantity'],
                        ],
                    ],
                ],
                'required' => ['items'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function responseSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'order_number' => ['type' => 'string'],
                'status' => ['type' => 'string'],
                'subtotal_clp' => ['type' => 'integer'],
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'quantity' => ['type' => 'integer'],
                            'unit_price_clp' => ['type' => 'integer'],
                            'subtotal_clp' => ['type' => 'integer'],
                        ],
                    ],
                ],
                'expires_at' => ['type' => ['string', 'null']],
                'requires_human_confirmation' => ['type' => 'boolean'],
            ],
        ];
    }

    /** @return array<string, string|array> */
    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1|max:20',
            'items.*.product_identifier' => 'required|string|max:255',
            'items.*.quantity' => 'required|integer|min:1|max:99',
        ];
    }

    public function requiresAuth(): bool
    {
        return true;
    }

    /** @param array{items: array<int, array{product_identifier: string, quantity: int}>} $args */
    public function handle(array $args, ToolContext $ctx): ToolResult
    {
        if ($ctx->user === null) {
            return ToolResult::error('AUTH_REQUIRED', 'Debes iniciar sesión para crear una propuesta.');
        }

        return Cache::lock($this->lockKey($ctx->user), self::LOCK_SECONDS)->block(
            self::LOCK_WAIT_SECONDS,
            fn (): ToolResult => $this->createDraft($args['items'], $ctx),
        );
    }

    /** @param array<int, array{product_identifier: string, quantity: int}> $items */
    private function createDraft(array $items, ToolContext $ctx): ToolResult
    {
        $user = $ctx->user;
        assert($user instanceof User);

        // Idempotent retries must remain available even after the user has reached the quota.
        $alreadyExists = Order::query()
            ->where('user_id', $user->id)
            ->where('draft_request_id', $ctx->draftRequestId)
            ->exists();

        $rateLimitKey = $this->rateLimitKey($user);
        $maximumDrafts = (int) config('santi.draft_max_per_hour');

        if (! $alreadyExists && RateLimiter::tooManyAttempts($rateLimitKey, $maximumDrafts)) {
            return ToolResult::error('RATE_LIMITED', 'Límite de propuestas por hora alcanzado.');
        }

        try {
            $draft = $this->draftOrders->create($user, $items, $ctx->draftRequestId);
        } catch (DraftLimitException|DraftUnavailableException $exception) {
            return ToolResult::error($exception->errorCode(), $exception->getMessage());
        }

        if ($draft->wasRecentlyCreated) {
            RateLimiter::hit($rateLimitKey, self::RATE_LIMIT_WINDOW_SECONDS);
        }

        return ToolResult::ok([
            'order_number' => $draft->order_number,
            'status' => $draft->status,
            'subtotal_clp' => (int) $draft->subtotal,
            'items' => $draft->items->map(fn ($item): array => [
                'name' => $item->product_name,
                'quantity' => (int) $item->quantity,
                'unit_price_clp' => (int) $item->product_price,
                'subtotal_clp' => (int) $item->subtotal,
            ])->values()->all(),
            'expires_at' => $draft->draft_expires_at?->utc()->toISOString(),
            'requires_human_confirmation' => true,
        ]);
    }

    private function rateLimitKey(User $user): string
    {
        return "santi:draft:{$user->id}";
    }

    private function lockKey(User $user): string
    {
        return "santi:draft:lock:{$user->id}";
    }
}
