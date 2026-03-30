<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'user_id',
        'shipping_name',
        'shipping_phone',
        'shipping_street',
        'shipping_city',
        'shipping_region',
        'shipping_method',
        'status',
        'subtotal',
        'shipping_cost',
        'discount',
        'total',
        'payment_method',
        'stripe_payment_id',
        'notes',
        'paid_at',
        'shipped_at',
        'delivered_at',
    ];

    protected $casts = [
        'paid_at'      => 'datetime',
        'shipped_at'   => 'datetime',
        'delivered_at' => 'datetime',
    ];

    // -------------------------------------------------------
    // Auto-genera el order_number al crear
    // Formato: IST-20260330-0001
    // -------------------------------------------------------
    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->order_number)) {
                $date  = now()->format('Ymd');
                $count = static::whereDate('created_at', today())->count() + 1;
                $order->order_number = 'IST-' . $date . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    // -------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------

    public function isPaid(): bool
    {
        return in_array($this->status, ['paid', 'processing', 'shipped', 'delivered']);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /** Etiqueta legible del estado para mostrar en el frontend */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'pending'    => 'Pendiente',
            'paid'       => 'Pagado',
            'processing' => 'En preparación',
            'shipped'    => 'Enviado',
            'delivered'  => 'Entregado',
            'cancelled'  => 'Cancelado',
            default      => $this->status,
        };
    }
}
