<?php

$dir = __DIR__ . '/app/Models';

$models = [
    'Role' => <<<PHP
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Role extends Model {
    protected \$table = 'roles';
    protected \$primaryKey = 'role_id';
    public \$timestamps = false;
    protected \$fillable = ['role_name'];

    public function users() {
        return \$this->hasMany(User::class, 'role_id', 'role_id');
    }
}
PHP,

    'UserSession' => <<<PHP
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class UserSession extends Model {
    protected \$table = 'user_sessions';
    protected \$primaryKey = 'session_id';
    public const UPDATED_AT = null;
    protected \$fillable = ['user_id', 'device_name', 'device_id', 'ip_address', 'fcm_token', 'last_active', 'is_revoked'];

    public function user() {
        return \$this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
PHP,

    'Governorate' => <<<PHP
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Governorate extends Model {
    protected \$table = 'governorates';
    protected \$primaryKey = 'gov_id';
    public \$timestamps = false;
    protected \$fillable = ['name'];

    public function regions() {
        return \$this->hasMany(Region::class, 'gov_id', 'gov_id');
    }
}
PHP,

    'Region' => <<<PHP
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Region extends Model {
    protected \$table = 'regions';
    protected \$primaryKey = 'region_id';
    public \$timestamps = false;
    protected \$fillable = ['gov_id', 'name'];

    public function governorate() {
        return \$this->belongsTo(Governorate::class, 'gov_id', 'gov_id');
    }

    public function streets() {
        return \$this->hasMany(Street::class, 'region_id', 'region_id');
    }
}
PHP,

    'Street' => <<<PHP
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Street extends Model {
    protected \$table = 'streets';
    protected \$primaryKey = 'street_id';
    public \$timestamps = false;
    protected \$fillable = ['region_id', 'name'];

    public function region() {
        return \$this->belongsTo(Region::class, 'region_id', 'region_id');
    }

    public function screens() {
        return \$this->hasMany(Screen::class, 'street_id', 'street_id');
    }
}
PHP,

    'ScreenType' => <<<PHP
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ScreenType extends Model {
    protected \$table = 'screen_types';
    protected \$primaryKey = 'type_id';
    public \$timestamps = false;
    protected \$fillable = ['type_name', 'resolution_width', 'resolution_height', 'orientation'];

    public function screens() {
        return \$this->hasMany(Screen::class, 'type_id', 'type_id');
    }
}
PHP,

    'Screen' => <<<PHP
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Screen extends Model {
    use SoftDeletes;
    protected \$table = 'screens';
    protected \$primaryKey = 'screen_id';
    public \$timestamps = false;
    protected \$fillable = ['owner_id', 'type_id', 'street_id', 'screen_name', 'status', 'linked_by', 'linked_at', 'disconnected_at'];

    public function owner() {
        return \$this->belongsTo(User::class, 'owner_id', 'user_id');
    }

    public function linkedBy() {
        return \$this->belongsTo(User::class, 'linked_by', 'user_id');
    }

    public function type() {
        return \$this->belongsTo(ScreenType::class, 'type_id', 'type_id');
    }

    public function street() {
        return \$this->belongsTo(Street::class, 'street_id', 'street_id');
    }

    public function advertisements() {
        return \$this->belongsToMany(Advertisement::class, 'ad_screens', 'screen_id', 'ad_id')
                    ->withPivot('price');
    }

    public function playbackLogs() {
        return \$this->hasMany(PlaybackLog::class, 'screen_id', 'screen_id');
    }
}
PHP,

    'Category' => <<<PHP
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Category extends Model {
    protected \$table = 'categories';
    protected \$primaryKey = 'category_id';
    public \$timestamps = false;
    protected \$fillable = ['category_name', 'price', 'max_duration', 'max_size', 'discount_type', 'discount_value'];

    public function advertisements() {
        return \$this->hasMany(Advertisement::class, 'category_id', 'category_id');
    }
}
PHP,

    'Advertisement' => <<<PHP
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Advertisement extends Model {
    protected \$table = 'advertisements';
    protected \$primaryKey = 'ad_id';
    public \$timestamps = false; // using uploaded_at manually
    protected \$fillable = ['advertiser_id', 'category_id', 'title', 'file_path', 'duration', 'file_size', 'status', 'rejection_reason', 'is_deleted'];

    public function advertiser() {
        return \$this->belongsTo(User::class, 'advertiser_id', 'user_id');
    }

    public function category() {
        return \$this->belongsTo(Category::class, 'category_id', 'category_id');
    }

    public function screens() {
        return \$this->belongsToMany(Screen::class, 'ad_screens', 'ad_id', 'screen_id')
                    ->withPivot('price');
    }

    public function schedules() {
        return \$this->hasMany(AdSchedule::class, 'ad_id', 'ad_id');
    }

    public function playbackLogs() {
        return \$this->hasMany(PlaybackLog::class, 'ad_id', 'ad_id');
    }
}
PHP,

    'AdScreen' => <<<PHP
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AdScreen extends Model {
    protected \$table = 'ad_screens';
    protected \$primaryKey = 'ad_screen_id';
    public \$timestamps = false;
    protected \$fillable = ['ad_id', 'screen_id', 'price'];

    public function advertisement() {
        return \$this->belongsTo(Advertisement::class, 'ad_id', 'ad_id');
    }

    public function screen() {
        return \$this->belongsTo(Screen::class, 'screen_id', 'screen_id');
    }
}
PHP,

    'AdSchedule' => <<<PHP
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AdSchedule extends Model {
    protected \$table = 'ad_schedules';
    protected \$primaryKey = 'schedule_id';
    public \$timestamps = false;
    protected \$fillable = ['ad_id', 'start_date', 'end_date', 'start_time', 'end_time', 'is_active'];

    public function advertisement() {
        return \$this->belongsTo(Advertisement::class, 'ad_id', 'ad_id');
    }
}
PHP,

    'Invoice' => <<<PHP
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model {
    protected \$table = 'invoices';
    protected \$primaryKey = 'invoice_id';
    public \$timestamps = false;
    protected \$fillable = ['invoice_number', 'advertiser_id', 'total_amount', 'total_platform_fee', 'total_owner_share', 'status', 'issue_date'];

    public function advertiser() {
        return \$this->belongsTo(User::class, 'advertiser_id', 'user_id');
    }

    public function items() {
        return \$this->hasMany(InvoiceItem::class, 'invoice_id', 'invoice_id');
    }

    public function transactions() {
        return \$this->hasMany(Transaction::class, 'invoice_id', 'invoice_id');
    }
}
PHP,

    'InvoiceItem' => <<<PHP
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model {
    protected \$table = 'invoice_items';
    protected \$primaryKey = 'item_id';
    public \$timestamps = false;
    protected \$fillable = ['invoice_id', 'ad_id', 'item_price'];

    public function invoice() {
        return \$this->belongsTo(Invoice::class, 'invoice_id', 'invoice_id');
    }

    public function advertisement() {
        return \$this->belongsTo(Advertisement::class, 'ad_id', 'ad_id');
    }
}
PHP,

    'Transaction' => <<<PHP
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model {
    protected \$table = 'transactions';
    protected \$primaryKey = 'transaction_id';
    public const UPDATED_AT = null;
    protected \$fillable = ['invoice_id', 'payment_method', 'amount_paid', 'payment_status', 'approved_by', 'is_platform_fee_deducted', 'deduction_scheduled_date'];

    public function invoice() {
        return \$this->belongsTo(Invoice::class, 'invoice_id', 'invoice_id');
    }

    public function approvedBy() {
        return \$this->belongsTo(User::class, 'approved_by', 'user_id');
    }
}
PHP,

    'Wallet' => <<<PHP
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model {
    protected \$table = 'wallets';
    protected \$primaryKey = 'wallet_id';
    public \$timestamps = false;
    protected \$fillable = ['user_id', 'available_balance'];

    public function user() {
        return \$this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function transactions() {
        return \$this->hasMany(WalletTransaction::class, 'wallet_id', 'wallet_id');
    }
}
PHP,

    'WalletTransaction' => <<<PHP
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model {
    protected \$table = 'wallet_transactions';
    protected \$primaryKey = 'wallet_tx_id';
    public const UPDATED_AT = null;
    protected \$fillable = ['wallet_id', 'transaction_type', 'amount', 'reference_id', 'description'];

    public function wallet() {
        return \$this->belongsTo(Wallet::class, 'wallet_id', 'wallet_id');
    }
}
PHP,

    'SystemSetting' => <<<PHP
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model {
    protected \$table = 'system_settings';
    protected \$primaryKey = 'setting_id';
    public \$timestamps = false;
    protected \$fillable = ['setting_key', 'setting_value', 'description'];
}
PHP,

    'PlaybackLog' => <<<PHP
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PlaybackLog extends Model {
    protected \$table = 'playback_logs';
    protected \$primaryKey = 'log_id';
    public \$timestamps = false;
    protected \$fillable = ['ad_id', 'screen_id', 'played_at'];

    public function advertisement() {
        return \$this->belongsTo(Advertisement::class, 'ad_id', 'ad_id');
    }

    public function screen() {
        return \$this->belongsTo(Screen::class, 'screen_id', 'screen_id');
    }
}
PHP,

    'Notification' => <<<PHP
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model {
    protected \$table = 'notifications';
    protected \$primaryKey = 'notification_id';
    public const UPDATED_AT = null;
    protected \$fillable = ['user_id', 'title', 'message', 'is_read'];

    public function user() {
        return \$this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
PHP
];

foreach ($models as $name => $content) {
    file_put_contents($dir . '/' . $name . '.php', $content);
}

echo "Models generated successfully!";
