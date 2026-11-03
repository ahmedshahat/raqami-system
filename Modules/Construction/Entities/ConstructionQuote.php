<?php

namespace Modules\Construction\Entities;

use App\Contact;
use Illuminate\Database\Eloquent\Model;

class ConstructionQuote extends Model
{
    public const STATUSES = ['draft', 'sent', 'accepted', 'rejected', 'expired'];

    /**
     * انتقالات الحالة المسموحة (مصدر واحد للواجهة والكنترولر). بعد التحويل لمشروع مفيش أي انتقال.
     * الانتهاء يدوي (مفيش مهمة بتغيّر الحالة بالتاريخ)، فإعادة فتح عرض منتهي مش بترجع لوحدها.
     */
    public const TRANSITIONS = [
        'draft' => ['sent'],
        'sent' => ['accepted', 'rejected', 'expired', 'draft'],
        'accepted' => ['sent'],
        'rejected' => ['sent'],
        'expired' => ['sent'],
    ];

    /** البيانات والبنود بتتعدل في الحالتين دول بس، وقبل التحويل لمشروع */
    public const EDITABLE_STATUSES = ['draft', 'sent'];

    protected $table = 'construction_quotes';

    protected $guarded = ['id'];

    protected $casts = ['quote_date' => 'date', 'total' => 'decimal:4'];

    public function customer()
    {
        return $this->belongsTo(Contact::class, 'customer_id');
    }

    public function items()
    {
        return $this->hasMany(ConstructionQuoteItem::class, 'quote_id');
    }

    public function project()
    {
        return $this->belongsTo(ConstructionProject::class, 'project_id');
    }

    public function refreshTotal(): void
    {
        $this->forceFill(['total' => $this->items()->sum('total')])->save();
    }

    /**
     * رقم الكود التلقائي اللي عليه الدور (ITM-001, ITM-002…): بعد أكبر رقم مستخدم، فالترقيم بيمشي بترتيب الإدخال
     * ومش بيرجع لأرقام بنود اتحذفت. (العدد + 1 كان بيلخبط الترتيب لما بند يتحذف)
     */
    public function nextItemCodeNumber(): int
    {
        $max = $this->items()->pluck('code')
            ->map(fn ($code) => preg_match('/^ITM-(\d+)$/i', trim((string) $code), $match) ? (int) $match[1] : 0)
            ->max();

        return ((int) $max) + 1;
    }

    public function allowedTransitions(): array
    {
        return $this->project_id ? [] : (self::TRANSITIONS[$this->status] ?? []);
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, self::EDITABLE_STATUSES, true) && ! $this->project_id;
    }

    public function validUntil(): \Carbon\Carbon
    {
        return $this->quote_date->copy()->addDays((int) $this->validity_days);
    }

    public function isPastValidity(): bool
    {
        return $this->validUntil()->endOfDay()->isPast();
    }

    public static function formatItemCode(int $number): string
    {
        return 'ITM-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT);
    }
}
