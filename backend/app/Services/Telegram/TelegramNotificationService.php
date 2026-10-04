<?php

namespace App\Services\Telegram;

use App\Models\NotificationDelivery;
use App\Models\OutboxEvent;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class TelegramNotificationService
{
    public function __construct(
        protected TelegramClient $client
    ) {}

    /**
     * Outbox hodisasini vakolatli Telegram qabul qiluvchilarga tarqatish
     *
     * @return array<int, NotificationDelivery>
     */
    public function notifyOutboxEvent(OutboxEvent $outboxEvent): array
    {
        $deliveries = [];

        // Ruxsatli xodimlar ro'yxati (Telegram chat ID mavjud bo'lgan faol foydalanuvchilar)
        $recipients = User::where('status', 'ACTIVE')
            ->whereNotNull('telegram_chat_id')
            ->get();

        if ($recipients->isEmpty()) {
            return [];
        }

        $eventName = $outboxEvent->event_name;
        $payload = $outboxEvent->payload ?? [];

        foreach ($recipients as $recipient) {
            if (! $this->shouldReceiveNotification($recipient, $eventName)) {
                continue;
            }

            $messageText = $this->formatNotificationMessage($recipient, $eventName, $payload);
            if (! $messageText) {
                continue;
            }

            $delivery = NotificationDelivery::create([
                'outbox_event_id' => $outboxEvent->id,
                'chat_id' => $recipient->telegram_chat_id,
                'user_id' => $recipient->id,
                'channel' => 'telegram',
                'notification_type' => $eventName,
                'message_text' => $messageText,
                'status' => 'PENDING',
                'retry_count' => 0,
            ]);

            try {
                $res = $this->client->sendMessage($recipient->telegram_chat_id, $messageText);
                if ($res['ok'] ?? false) {
                    $delivery->update([
                        'status' => 'SENT',
                        'sent_at' => now(),
                    ]);
                } else {
                    $delivery->update([
                        'status' => 'FAILED',
                        'last_error' => $res['error'] ?? 'API_ERROR',
                    ]);
                }
            } catch (\Throwable $e) {
                // Hech qanday holda tashqi aloqa xatosi asosiy biznes hujjatiga ta'sir qilmaydi!
                $delivery->update([
                    'status' => 'FAILED',
                    'last_error' => $e->getMessage(),
                ]);
                Log::warning('Telegram bildirishnoma yuborishda xatolik: '.$e->getMessage());
            }

            $deliveries[] = $delivery;
        }

        return $deliveries;
    }

    /**
     * Qayta yuborish mexanizmi (Retry)
     */
    public function retryFailedDeliveries(int $limit = 10, int $maxRetries = 3): int
    {
        $failed = NotificationDelivery::where('status', 'FAILED')
            ->where('retry_count', '<', $maxRetries)
            ->take($limit)
            ->get();

        $retriedCount = 0;
        foreach ($failed as $delivery) {
            $delivery->increment('retry_count');

            try {
                $res = $this->client->sendMessage($delivery->chat_id, $delivery->message_text);
                if ($res['ok'] ?? false) {
                    $delivery->update([
                        'status' => 'SENT',
                        'sent_at' => now(),
                        'last_error' => null,
                    ]);
                    $retriedCount++;
                } else {
                    $delivery->update([
                        'last_error' => $res['error'] ?? 'RETRY_FAILED',
                    ]);
                }
            } catch (\Throwable $e) {
                $delivery->update(['last_error' => $e->getMessage()]);
            }
        }

        return $retriedCount;
    }

    /**
     * Hodisa turiga qarab foydalanuvchi vakolatini tekshirish
     */
    protected function shouldReceiveNotification(User $user, string $eventName): bool
    {
        if ($user->isOwner() || $user->isAdmin()) {
            return true;
        }

        return match ($eventName) {
            'SaleCreated' => $user->hasPermission('create_sale'),
            'PurchaseReceived' => $user->hasPermission('create_purchase'),
            'PaymentRecorded' => $user->hasPermission('view_cash') || $user->hasPermission('create_payment'),
            'LowStock' => $user->hasPermission('manage_stock'),
            default => false,
        };
    }

    /**
     * HTML escaping bilan xavfsiz bildirishnoma matnini shakllantirish
     */
    protected function formatNotificationMessage(User $user, string $eventName, array $payload): ?string
    {
        $esc = fn ($txt) => htmlspecialchars((string) $txt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $canCost = $user->hasPermission('view_cost_price') || $user->isOwner();

        switch ($eventName) {
            case 'SaleCreated':
                $invoice = $esc($payload['invoice_number'] ?? 'N/A');
                $customer = $esc($payload['customer_name'] ?? 'Tezkor xaridor');
                $total = number_format($payload['total_amount'] ?? 0, 0, '.', ' ');
                $paid = number_format($payload['paid_amount'] ?? 0, 0, '.', ' ');
                $debt = number_format($payload['debt_amount'] ?? 0, 0, '.', ' ');

                $msg = "🛒 <b>YANGI SAVDO RASMIYLASHTIRILDI!</b>\n";
                $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
                $msg .= "🧾 Chek: #{$invoice}\n";
                $msg .= "👤 Xaridor: <b>{$customer}</b>\n";
                $msg .= "💰 Jami: <b>{$total} so'm</b>\n";
                $msg .= "💵 To'landi: {$paid} so'm\n";
                if (($payload['debt_amount'] ?? 0) > 0) {
                    $msg .= "📝 Qarz: <font color='red'>{$debt} so'm</font>\n";
                }

                return $msg;

            case 'PurchaseReceived':
                $invoice = $esc($payload['invoice_number'] ?? 'N/A');
                $supplier = $esc($payload['supplier_name'] ?? 'Ta\'minotchi');
                $total = number_format($payload['total_amount'] ?? 0, 0, '.', ' ');
                $paid = number_format($payload['paid_amount'] ?? 0, 0, '.', ' ');

                $msg = "📥 <b>YANGI YUK KIRIMI QABUL QILINDI!</b>\n";
                $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
                $msg .= "🧾 Hujjat: #{$invoice}\n";
                $msg .= "🏭 Zavod: <b>{$supplier}</b>\n";
                if ($canCost) {
                    $msg .= "💰 Jami summa: <b>{$total} so'm</b>\n";
                }
                $msg .= "💵 To'landi: {$paid} so'm\n";

                return $msg;

            case 'PaymentRecorded':
                $num = $esc($payload['payment_number'] ?? 'N/A');
                $amt = number_format($payload['amount'] ?? 0, 0, '.', ' ');
                $party = $esc($payload['party_name'] ?? 'Mijoz');

                $msg = "💳 <b>YANGI TO'LOV QABUL QILINDI!</b>\n";
                $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
                $msg .= "🧾 Kvitansiya: #{$num}\n";
                $msg .= "👤 Taraf: <b>{$party}</b>\n";
                $msg .= "💵 Summa: <b>{$amt} so'm</b>\n";

                return $msg;

            case 'LowStock':
                $pName = $esc($payload['product_name'] ?? 'Tovar');
                $vName = $esc($payload['volume_name'] ?? '');
                $qty = (int) ($payload['quantity'] ?? 0);
                $threshold = (int) ($payload['threshold'] ?? 10);

                $msg = "⚠️ <b>KAM QOLDIQ OGOHLANTIRISHI!</b>\n";
                $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
                $msg .= "🥤 Tovar: <b>{$pName} ({$vName})</b>\n";
                $msg .= "📦 Ombordagi qoldiq: <b>{$qty} dona</b> (Chegara: {$threshold} dona)\n";
                $msg .= "<i>Iltimos, qayta buyurtma berishni rejalashtiring.</i>\n";

                return $msg;

            default:
                return null;
        }
    }
}
