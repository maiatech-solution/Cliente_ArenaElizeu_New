<?php

namespace App\Models\Bar;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class BarCashMovement extends Model
{
    protected $table = 'bar_cash_movements';
    
    protected $fillable = [
        'bar_cash_session_id', 
        'user_id', 
        'bar_order_id', 
        'type',             // venda, reforco, sangria, estorno
        'payment_method',    // money, pix, credit, debit
        'amount', 
        'description'
    ];

    /**
     * Relacionamento com a Sessão de Caixa
     */
    public function session()
    {
        return $this->belongsTo(BarCashSession::class, 'bar_cash_session_id');
    }

    /**
     * Relacionamento com o Usuário que realizou a movimentação
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relacionamento com a Comanda/Pedido
     * Adicionamos isso para o Controller conseguir buscar os dados da mesa
     */
    public function barOrder()
    {
        return $this->belongsTo(BarOrder::class, 'bar_order_id');
    }
}