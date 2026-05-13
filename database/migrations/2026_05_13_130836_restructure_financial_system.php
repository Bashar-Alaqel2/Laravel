<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prevent transaction to avoid PostgreSQL 25P02 errors during CASCADE drops.
     */
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // تعطيل قيود المفاتيح مؤقتاً لضمان حذف الجداول القديمة بدون مشاكل
        Schema::disableForeignKeyConstraints();
        
        // 1. حذف جداول المحافظ والفواتير القديمة باستخدام CASCADE للتخلص من أي قيود في PostgreSQL
        \Illuminate\Support\Facades\DB::statement('DROP TABLE IF EXISTS transactions CASCADE');
        \Illuminate\Support\Facades\DB::statement('DROP TABLE IF EXISTS invoice_items CASCADE');
        \Illuminate\Support\Facades\DB::statement('DROP TABLE IF EXISTS invoices CASCADE');
        \Illuminate\Support\Facades\DB::statement('DROP TABLE IF EXISTS wallet_transactions CASCADE');
        \Illuminate\Support\Facades\DB::statement('DROP TABLE IF EXISTS wallets CASCADE');

        // 2. إنشاء جدول الحسابات البنكية لملاك الشاشات
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id('account_id');
            $table->foreignId('user_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->string('bank_name');
            $table->string('account_name');
            $table->string('account_number');
            $table->string('iban')->nullable();
            $table->string('swift_code')->nullable();
            $table->string('branch')->nullable();
            $table->timestamps();
        });

        // 3. إنشاء دفتر الأستاذ المالي (لتسجيل كل حركة بالقرش وتوزيع الأرباح)
        Schema::create('financial_ledgers', function (Blueprint $table) {
            $table->id('ledger_id');
            // ربط الحركة بالحملة أو الشاشة (اختياري، لفصل الأرباح)
            $table->foreignId('advertisement_id')->nullable()->constrained('advertisements', 'ad_id')->onDelete('cascade');
            $table->foreignId('screen_id')->nullable()->constrained('screens', 'screen_id')->onDelete('cascade');
            // المستخدم المعني (المعلن اللي دفع، أو مالك الشاشة اللي استلم)
            $table->foreignId('user_id')->constrained('users', 'user_id')->onDelete('cascade'); 
            
            // نوع الحركة: الدفع الكلي (payment_in)، عمولة التطبيق (platform_fee)، أرباح الشاشة (payout_pending)، تحويل فعلي للمالك (payout_completed)
            $table->string('transaction_type'); 
            
            $table->decimal('amount', 10, 2);
            $table->string('payment_method')->nullable(); // حوالة، بطاقة، كاش
            $table->string('reference_number')->nullable(); // رقم الإيصال البنكي الخارجي
            $table->string('status')->default('pending'); // pending, completed, failed
            $table->text('notes')->nullable();
            
            $table->timestamps();
        });
        
        // 4. تحديث جدول الإعلانات لمعرفة حالة الدفع مباشرة
        Schema::table('advertisements', function (Blueprint $table) {
            if (!Schema::hasColumn('advertisements', 'payment_status')) {
                $table->string('payment_status')->default('unpaid')->after('total_cost'); // unpaid, paid
            }
            if (!Schema::hasColumn('advertisements', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('payment_status');
            }
        });
        
        // إعادة تفعيل قيود المفاتيح
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('advertisements', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'payment_method']);
        });
        
        Schema::dropIfExists('financial_ledgers');
        Schema::dropIfExists('bank_accounts');
    }
};
