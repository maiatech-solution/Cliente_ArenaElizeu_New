<?php

namespace App\Models\Bar;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class BarOrder extends Model
{
    protected $table = 'bar_orders';

    protected $fillable = [
        'bar_table_id',
        'user_id',
        'total_value',
        'status',
        'closed_at',
        'bar_cash_session_id'
    ];

    public function table()
    {
        return $this->belongsTo(BarTable::class, 'bar_table_id');
    }

    public function items()
    {
        return $this->hasMany(BarOrderItem::class);
    }

    public function waiter()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cashSession()
    {
        return $this->belongsTo(BarCashSession::class, 'bar_cash_session_id');
    }

    /**
     * 💰 RELAÇÃO COM MOVIMENTAÇÕES DE CAIXA
     * Isso permite que o relatório rastreie o dinheiro exato desta mesa.
     */
    public function movements()
    {
        return $this->hasMany(BarCashMovement::class, 'bar_order_id');
    }
}
