<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinancialTransaction extends Model
{
    use HasFactory;

    /**
     * ✅ Constantes de Tipo de Transação
     */
    public const TYPE_SIGNAL = 'signal';
    public const TYPE_PAYMENT = 'payment';
    public const TYPE_REFUND = 'refund';
    public const TYPE_NO_SHOW_PENALTY = 'no_show_penalty';

    public const TYPE_RETEN_NOSHOW_COMP = 'RETEN_NOSHOW_COMP';
    public const TYPE_RETEN_CANC_COMP = 'RETEN_CANC_COMP';
    public const TYPE_RETEN_CANC_P_COMP = 'RETEN_CANC_P_COMP';
    public const TYPE_RETEN_CANC_S_COMP = 'RETEN_CANC_S_COMP';

    /**
     * 💳 FONTE ÚNICA: PADRONIZAÇÃO DE MÉTODOS DE PAGAMENTO
     * Centraliza as chaves oficiais para evitar variações como "Cartão" e "card".
     */
    public const PAYMENT_PIX = 'pix';
    public const PAYMENT_MONEY = 'money';
    public const PAYMENT_CREDIT = 'credit_card';
    public const PAYMENT_DEBIT = 'debit_card';
    public const PAYMENT_TRANSFER = 'transfer';
    public const PAYMENT_OTHER = 'other';

    /**
     * Retorna a lista amigável para Selects em todo o sistema (Dashboard e Caixa)
     */
    public static function getPaymentMethods(): array
    {
        return [
            self::PAYMENT_PIX    => 'PIX',
            self::PAYMENT_MONEY  => 'Dinheiro',
            self::PAYMENT_CREDIT => 'Cartão de Crédito',
            self::PAYMENT_DEBIT  => 'Cartão de Débito',
            self::PAYMENT_TRANSFER => 'Transferência',
            self::PAYMENT_OTHER  => 'Outros/Cortesia',
        ];
    }

    protected $fillable = [
        'reserva_id',
        'arena_id',
        'user_id',
        'manager_id',
        'amount',
        'type',
        'payment_method',
        'transaction_code',
        'description',
        'paid_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    /**
     * 🛡️ TRAVA DE SEGURANÇA GLOBAL & SINCRONIZAÇÃO
     */
    protected static function boot()
    {
        parent::boot();

        // Bloqueia a criação (Pagamentos, Sinais, Reforços) em caixa fechado
        static::creating(function ($transaction) {
            if (app()->runningInConsole()) return;

            $dateToCheck = $transaction->paid_at
                ? Carbon::parse($transaction->paid_at)->toDateString()
                : now()->toDateString();

            $isClosed = \App\Models\Cashier::where('date', $dateToCheck)
                ->where('arena_id', $transaction->arena_id)
                ->where('status', 'closed')
                ->exists();

            if ($isClosed) {
                $formattedDate = Carbon::parse($dateToCheck)->format('d/m/Y');
                throw new \Exception("Bloqueio de Segurança: O caixa desta arena para o dia {$formattedDate} já está encerrado. Reabra-o para lançar movimentações.");
            }
        });

        // Bloqueia a exclusão em caixa fechado
        static::deleting(function ($transaction) {
            if (app()->runningInConsole()) return;

            $dateToCheck = $transaction->paid_at
                ? Carbon::parse($transaction->paid_at)->toDateString()
                : now()->toDateString();

            $isClosed = \App\Models\Cashier::where('date', $dateToCheck)
                ->where('arena_id', $transaction->arena_id)
                ->where('status', 'closed')
                ->exists();

            if ($isClosed) {
                throw new \Exception("Bloqueio de Segurança: Não é possível excluir ou estornar movimentações de uma arena com caixa encerrado.");
            }
        });

        // ⭐ SINCRONIZAÇÃO AUTOMÁTICA DA RESERVA
        static::created(function ($transaction) {
            if (!$transaction->reserva_id) return;

            $reserva = \App\Models\Reserva::find($transaction->reserva_id);
            if (!$reserva) return;

            $total = DB::table('financial_transactions')
                ->where('reserva_id', $reserva->id)
                ->sum('amount');

            $total = max(0, $total);

            $reserva->update([
                'total_paid' => $total,
                'signal_value' => $total,
                'payment_status' => $total >= $reserva->price
                    ? 'paid'
                    : ($total > 0 ? 'partial' : 'unpaid')
            ]);
        });
    }

    /**
     * ✅ RELAÇÕES
     */
    public function arena(): BelongsTo
    {
        return $this->belongsTo(Arena::class);
    }

    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class)->withDefault([
            'client_name' => 'Reserva Excluída/Finalizada',
            'id' => 'N/D'
        ]);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * ✅ HELPER DE SCOPE
     */
    public function scopeCredits($query)
    {
        return $query->where('amount', '>', 0);
    }
    public function scopeDebits($query)
    {
        return $query->where('amount', '<', 0);
    }

    /**
     * 🛡️ PADRONIZAÇÃO AUTOMÁTICA DE MÉTODO DE PAGAMENTO
     * Este mutator limpa os dados e converte termos genéricos para os oficiais.
     */
    public function setPaymentMethodAttribute($value)
    {
        $cleanValue = strtolower(trim($value ?? self::PAYMENT_OTHER));

        // Mapeia variações comuns vindas do Dashboard ou outros módulos
        $map = [
            'cartão' => self::PAYMENT_DEBIT, // Define débito como padrão para "Cartão" genérico
            'card'   => self::PAYMENT_DEBIT,
            'dinheiro' => self::PAYMENT_MONEY,
            'especie' => self::PAYMENT_MONEY,
            'cash' => self::PAYMENT_MONEY,
            'transferencia' => self::PAYMENT_TRANSFER,
            'transfer' => self::PAYMENT_TRANSFER,
        ];

        $this->attributes['payment_method'] = $map[$cleanValue] ?? $cleanValue;
    }

    /**
     * Acessador para retornar o nome bonito (Ex: $t->payment_method_label)
     */
    public function getPaymentMethodLabelAttribute(): string
    {
        return self::getPaymentMethods()[$this->payment_method] ?? ucfirst($this->payment_method);
    }
}
