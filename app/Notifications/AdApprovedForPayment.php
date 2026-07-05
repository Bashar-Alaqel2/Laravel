<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\Advertisement;

class AdApprovedForPayment extends Notification
{
    use Queueable;

    protected $ad;

    public function __construct(Advertisement $ad)
    {
        $this->ad = $ad;
    }

    public function via($notifiable)
    {
        return ['database']; // Store in database so it shows up in dashboard bell
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'ad_approved',
            'title' => 'تهانينا! تمت الموافقة على إعلانك',
            'message' => 'الإعلان "' . $this->ad->title . '" اجتاز المراجعة وهو جاهز للدفع لكي يتم نشره.',
            'ad_id' => $this->ad->ad_id,
            'action_url' => '/dashboard/ads',
        ];
    }
}
