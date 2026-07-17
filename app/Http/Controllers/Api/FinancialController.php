<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FinancialLedger;
use App\Models\Advertisement;
use App\Models\Screen;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;

class FinancialController extends Controller
{
    // ==========================================
    // 1. تسجيل عملية دفع (من المعلن)
    // ==========================================
    public function recordPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ad_id'            => 'required|exists:advertisements,ad_id',
            'amount'           => 'required|numeric|min:0.01',
            'payment_method'   => 'required|string',
            'reference_number' => 'nullable|string',
            'notes'            => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 400);
        }

        $ad = Advertisement::findOrFail($request->ad_id);

        try {
            DB::beginTransaction();

            // 1. تسجيل الحركة في دفتر الأستاذ
            $ledger = FinancialLedger::create([
                'advertisement_id' => $ad->ad_id,
                'user_id'          => $ad->advertiser_id,
                'transaction_type' => 'payment_in',
                'amount'           => $request->amount,
                'payment_method'   => $request->payment_method,
                'reference_number' => $request->reference_number,
                'status'           => 'completed',
                'notes'            => $request->notes ?? "دفع قيمة الإعلان: {$ad->title}",
            ]);

            // 2. تحديث حالة الدفع في الإعلان
            $ad->update([
                'payment_status' => 'paid',
                'payment_method' => $request->payment_method,
            ]);

            // 3. (اختياري) توزيع الأرباح تلقائياً أو تركها لخطوة لاحقة
            $this->distributeEarnings($ad, $request->amount);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم تسجيل الدفع وتوزيع الأرباح بنجاح.',
                'data'    => $ledger
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'خطأ في معالجة الدفع: ' . $e->getMessage()], 500);
        }
    }

    // ==========================================
    // 2. توزيع الأرباح على ملاك الشاشات
    // (يتم اقتطاع نسبة للمنصة والباقي للشاشات)
    // ==========================================
    public function distributeEarnings(Advertisement $ad, $totalAmount)
    {
        // جلب نسبة المنصة من الإعدادات، وإذا لم تكن موجودة نعتبرها 20%
        $settings = \Illuminate\Support\Facades\Cache::rememberForever('system_settings_cache', function () {
            return \App\Models\SystemSetting::all()->pluck('setting_value', 'setting_key')->toArray();
        });
        $feePercentage = isset($settings['platform_fee_percentage']) ? (float)$settings['platform_fee_percentage'] : 20.0;
        
        $platformFeeRate = $feePercentage / 100;
        $platformFee = $totalAmount * $platformFeeRate;
        $netToOwners = $totalAmount - $platformFee;

        // تسجيل عمولة المنصة
        FinancialLedger::create([
            'advertisement_id' => $ad->ad_id,
            'user_id'          => 1, // غالباً الآدمن الأول أو حساب المنصة
            'transaction_type' => 'platform_fee',
            'amount'           => $platformFee,
            'status'           => 'completed',
            'notes'            => "عمولة المنصة من إعلان: {$ad->title}",
        ]);

        // توزيع الباقي على الشاشات المرتبطة بالإعلان
        $screens = $ad->screens;
        if ($screens->count() > 0) {
            $amountPerScreen = $netToOwners / $screens->count();

            foreach ($screens as $screen) {
                if ($screen->owner_id) {
                    FinancialLedger::create([
                        'advertisement_id' => $ad->ad_id,
                        'screen_id'        => $screen->screen_id,
                        'user_id'          => $screen->owner_id,
                        'transaction_type' => 'payout_pending',
                        'amount'           => $amountPerScreen,
                        'status'           => 'pending', // بانتظار طلب السحب من المالك
                        'notes'            => "أرباح مستحقة عن شاشة: {$screen->screen_name}",
                    ]);
                }
            }
        }
    }

    // ==========================================
    // 3. جلب سجل الحركات المالية (للمدير أو المحاسب)
    // ==========================================
    public function getLedger(Request $request)
    {
        $user = $request->user();
        $userId = $user ? $user->user_id : 'guest';
        $role = $user ? $user->role_id : 'guest';
        $type = $request->has('type') ? $request->type : 'all';
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $cacheKey = "financial_ledger_{$userId}_{$role}_{$type}_{$startDate}_{$endDate}";

        $data = Cache::remember($cacheKey, 60, function () use ($user, $request, $startDate, $endDate) {
            $baseQuery = FinancialLedger::query();

            if ($user) {
                if ($user->role_id === 8 || ($user->hasRole(\App\Models\Role::SCREEN_OWNER))) {
                    $baseQuery->where('user_id', $user->user_id);
                } elseif ($user->hasRole(\App\Models\Role::SECRETARY)) {
                    $baseQuery->where('transaction_type', 'payment_pending');
                } else {
                    if ($request->has('user_id')) {
                        $baseQuery->where('user_id', $request->user_id);
                    }
                }
            }

            if ($request->has('type') && $request->type !== 'all') {
                $baseQuery->where('transaction_type', $request->type);
            }

            // --- Apply Date Filters for BOTH Aggregations and Transactions ---
            $filteredQuery = clone $baseQuery;
            
            if ($startDate || $endDate) {
                if ($startDate) {
                    $filteredQuery->where('created_at', '>=', \Carbon\Carbon::parse($startDate)->startOfDay());
                }
                if ($endDate) {
                    $filteredQuery->where('created_at', '<=', \Carbon\Carbon::parse($endDate)->endOfDay());
                }
            } else {
                // Soft Archiving: Default to last 60 days if no date is provided
                $filteredQuery->where('created_at', '>=', \Carbon\Carbon::now()->subDays(60));
            }

            // --- Aggregations (Now respects Date Filters!) ---
            $aggQuery = clone $filteredQuery;
            
            $totalPayments = (clone $aggQuery)->whereIn('transaction_type', ['payment', 'payment_in'])
                                             ->where('status', 'completed')
                                             ->sum('amount');
                                             
            $platformProfit = (clone $aggQuery)->where('transaction_type', 'platform_fee')
                                              ->where('status', 'completed')
                                              ->sum('amount');
                                              
            $ownersLiabilities = (clone $aggQuery)->whereIn('transaction_type', ['payout_pending', 'payout_requested'])
                                                 ->sum('amount');
                                                 
            $ownersPaid = (clone $aggQuery)->where('transaction_type', 'payout_completed')
                                          ->sum('amount');

            // --- Transactions Query ---
            $txQuery = clone $filteredQuery;

            $txQuery->with(['user', 'advertisement', 'screen'])
                    ->select([
                        'ledger_id', 'advertisement_id', 'screen_id', 'user_id', 
                        'transaction_type', 'amount', 'payment_method', 'reference_number', 
                        'status', 'notes', 'created_at', 'updated_at'
                    ])
                    ->addSelect(\Illuminate\Support\Facades\DB::raw('CASE WHEN receipt_path IS NOT NULL THEN 1 ELSE 0 END as has_receipt'));

            $ledger = $txQuery->orderBy('created_at', 'desc')->get();
            
            return [
                'total_payments' => $totalPayments,
                'platform_profit' => $platformProfit,
                'owners_liabilities' => $ownersLiabilities,
                'owners_paid' => $ownersPaid,
                'transactions'   => $ledger
            ];
        });
        
        return response()->json([
            'success' => true, 
            'data' => $data
        ], 200);
    }

    // ==========================================
    // 4. جلب أرباح مالك شاشة محدد
    // ==========================================
    public function getOwnerEarnings(Request $request)
    {
        $userId = $request->user()->user_id;
        
        $data = Cache::remember("owner_earnings_{$userId}", 300, function () use ($userId) {
            $totalEarnings = FinancialLedger::where('user_id', $userId)
                ->where('transaction_type', 'payout_pending')
                ->sum('amount');

            $withdrawn = FinancialLedger::where('user_id', $userId)
                ->where('transaction_type', 'payout_completed')
                ->sum('amount');
                
            $requested = FinancialLedger::where('user_id', $userId)
                ->where('transaction_type', 'payout_requested')
                ->sum('amount');
                
            $availableBalance = $totalEarnings - $withdrawn - $requested;

            $pendingLogs = FinancialLedger::with(['advertisement', 'screen'])
                ->where('user_id', $userId)
                ->whereIn('transaction_type', ['payout_pending', 'payout_requested', 'payout_completed', 'payout_rejected'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->values()
                ->toArray();

            return [
                'total_earnings' => $totalEarnings,
                'withdrawn' => $withdrawn,
                'available_balance' => $availableBalance,
                'pending_logs' => $pendingLogs
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data
        ], 200);
    }
    
    // ==========================================
    // طلب سحب أرباح للمالك (Screen Owner)
    // ==========================================
    public function requestPayout(Request $request)
    {
        $user = $request->user();
        
        $request->validate([
            'amount' => 'required|numeric|min:50',
            'bank_name' => 'nullable|string',
            'account_number' => 'required|string'
        ]);

        $amount = $request->amount;
        
        $totalEarnings = FinancialLedger::where('user_id', $user->user_id)->where('transaction_type', 'payout_pending')->sum('amount');
        $withdrawn = FinancialLedger::where('user_id', $user->user_id)->where('transaction_type', 'payout_completed')->sum('amount');
        $requested = FinancialLedger::where('user_id', $user->user_id)->where('transaction_type', 'payout_requested')->sum('amount');
        
        $availableBalance = $totalEarnings - $withdrawn - $requested;
            
        if ($amount > $availableBalance) {
            return response()->json(['success' => false, 'message' => 'الرصيد المتاح غير كافٍ.'], 400);
        }

        FinancialLedger::create([
            'user_id' => $user->user_id,
            'transaction_type' => 'payout_requested',
            'amount' => $amount,
            'payment_method' => 'bank_transfer',
            'status' => 'pending',
            'notes' => json_encode(['bank_name' => $request->bank_name, 'account_number' => $request->account_number])
        ]);

        // إشعار الإدارة بوجود طلب سحب جديد
        $adminUsers = \App\Models\User::whereHas('role', function($q) {
            $q->whereIn('role_id', [\App\Models\Role::SUPER_ADMIN, \App\Models\Role::ADMIN]);
        })->get();
        
        foreach($adminUsers as $adminUser) {
            \App\Models\Notification::create([
                'user_id' => $adminUser->user_id,
                'title' => json_encode(['key' => 'notif_title_payout_requested']),
                'message' => json_encode(['key' => 'notif_msg_payout_requested', 'args' => ['name' => $user->full_name, 'amount' => $amount]]),
                'is_read' => false,
            ]);
        }

        // Clear cache so the admin and the owner immediately see the update
        // مسح الكاش المتعلق بالمالية فقط (بدلاً من مسح كل الكاش)
        \Illuminate\Support\Facades\Cache::forget('admin_dashboard_overview');
        \Illuminate\Support\Facades\Cache::forget('secretary_dashboard_overview');
        \Illuminate\Support\Facades\Cache::forget("owner_earnings_{$user->user_id}");
        \Illuminate\Support\Facades\Cache::forget("owner_dashboard_{$user->user_id}");

        return response()->json(['success' => true, 'message' => 'تم استلام طلب السحب بنجاح.']);
    }

    // ==========================================
    // اعتماد طلب السحب (Payout Approved)
    // ==========================================
    public function approvePayout(Request $request, $id)
    {
        $admin = $request->user();
        if (!$admin->can('manage_all')) {
            return response()->json(['success' => false, 'message' => 'غير مصرح لك بذلك.'], 403);
        }

        $request->validate([
            'reference_number' => 'required|string',
        ]);

        try {
            DB::beginTransaction();

            $ledger = FinancialLedger::findOrFail($id);
            if ($ledger->transaction_type !== 'payout_requested') {
                return response()->json(['success' => false, 'message' => 'هذه العملية ليست طلب سحب قيد المراجعة.'], 400);
            }

            // تحديث الطلب إلى مكتمل
            $ledger->update([
                'transaction_type' => 'payout_completed',
                'status' => 'completed',
                'reference_number' => $request->reference_number,
            ]);
            
            $notes = json_decode($ledger->notes, true) ?: [];
            $notes['approved_by'] = $admin->user_id;
            $notes['approved_at'] = now()->toDateTimeString();
            $ledger->update(['notes' => json_encode($notes)]);

            // خصم من رصيد المنصة (القيد المزدوج)
            FinancialLedger::create([
                'user_id' => 1, // حساب المنصة الرئيسي
                'transaction_type' => 'platform_payout_deduction',
                'amount' => $ledger->amount,
                'status' => 'completed',
                'reference_number' => $request->reference_number,
                'notes' => json_encode([
                    'message' => 'صرف أرباح لمالك شاشة',
                    'screen_owner_id' => $ledger->user_id,
                    'payout_ledger_id' => $ledger->ledger_id,
                ])
            ]);

            // إشعار المالك
            \App\Models\Notification::create([
                'user_id' => $ledger->user_id,
                'title' => json_encode(['key' => 'notif_title_payout_approved']),
                'message' => json_encode(['key' => 'notif_msg_payout_approved', 'args' => ['amount' => $ledger->amount, 'ref' => $request->reference_number]]),
                'is_read' => false,
            ]);

            DB::commit();
            Cache::forget('admin_dashboard_overview');
            Cache::forget('secretary_dashboard_overview');
            Cache::forget("owner_earnings_{$ledger->user_id}");
            Cache::forget("owner_dashboard_{$ledger->user_id}");

            return response()->json(['success' => true, 'message' => 'تم اعتماد طلب السحب وخصم المبلغ من الخزينة.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    // ==========================================
    // رفض طلب السحب (Payout Rejected)
    // ==========================================
    public function rejectPayout(Request $request, $id)
    {
        $admin = $request->user();
        if (!$admin->can('manage_all')) {
            return response()->json(['success' => false, 'message' => 'غير مصرح لك بذلك.'], 403);
        }

        $request->validate([
            'reason' => 'required|string',
        ]);

        try {
            DB::beginTransaction();

            $ledger = FinancialLedger::findOrFail($id);
            if ($ledger->transaction_type !== 'payout_requested') {
                return response()->json(['success' => false, 'message' => 'هذه العملية ليست طلب سحب قيد المراجعة.'], 400);
            }

            // تحديث الطلب إلى مرفوض
            $ledger->update([
                'transaction_type' => 'payout_rejected',
                'status' => 'rejected',
            ]);
            
            $notes = json_decode($ledger->notes, true) ?: [];
            $notes['rejected_by'] = $admin->user_id;
            $notes['rejection_reason'] = $request->reason;
            $notes['rejected_at'] = now()->toDateTimeString();
            $ledger->update(['notes' => json_encode($notes)]);

            // إشعار المالك بالرفض
            \App\Models\Notification::create([
                'user_id' => $ledger->user_id,
                'title' => json_encode(['key' => 'notif_title_payout_rejected']),
                'message' => json_encode(['key' => 'notif_msg_payout_rejected', 'args' => ['amount' => $ledger->amount, 'reason' => $request->reason]]),
                'is_read' => false,
            ]);

            DB::commit();
            Cache::forget('admin_dashboard_overview');
            Cache::forget('secretary_dashboard_overview');
            Cache::forget("owner_earnings_{$ledger->user_id}");
            Cache::forget("owner_dashboard_{$ledger->user_id}");

            return response()->json(['success' => true, 'message' => 'تم إرجاع المبلغ لمحفظة المالك مع إشعار بالرفض.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    // ==========================================
    // 5. اعتماد دفعة (تغيير الحالة من Pending إلى Completed)
    // ==========================================
    public function approvePayment(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $ledger = FinancialLedger::findOrFail($id);
            
            if ($ledger->transaction_type !== 'payment_pending') {
                return response()->json(['success' => false, 'message' => 'هذه العملية ليست دفعة معلقة.'], 400);
            }

            // 1. تحديث حالة القيد المالي
            $ledger->update([
                'status' => 'completed',
                'transaction_type' => 'payment_in'
            ]);

            // 2. تحديث حالة الإعلان
            if ($ledger->advertisement_id) {
                $ad = Advertisement::find($ledger->advertisement_id);
                if ($ad) {
                    $ad->update(['payment_status' => 'paid']);
                    
                    // إرسال إشعار للمعلن
                    \App\Models\Notification::create([
                        'user_id' => $ad->advertiser_id,
                        'title' => json_encode(['key' => 'notif_title_payment_confirmed']),
                        'message' => json_encode(['key' => 'notif_msg_payment_confirmed', 'args' => ['amount' => $ledger->amount, 'title' => $ad->title]]),
                        'is_read' => false,
                    ]);

                    // 3. توزيع الأرباح
                    $this->distributeEarnings($ad, $ledger->amount);
                }
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'تم اعتماد الدفع وتوزيع الأرباح بنجاح.']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ==========================================
    // 6. رفض دفعة (تغيير الحالة من Pending إلى Rejected)
    // ==========================================
    public function rejectPayment(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $ledger = FinancialLedger::findOrFail($id);
            
            if ($ledger->transaction_type !== 'payment_pending') {
                return response()->json(['success' => false, 'message' => 'هذه العملية ليست دفعة معلقة.'], 400);
            }

            // 1. تحديث حالة القيد المالي
            $ledger->update([
                'status' => 'rejected'
            ]);

            // 2. تحديث حالة الإعلان
            if ($ledger->advertisement_id) {
                $ad = Advertisement::find($ledger->advertisement_id);
                if ($ad) {
                    $ad->update(['payment_status' => 'unpaid']);
                    
                    // إرسال إشعار للمعلن
                    \App\Models\Notification::create([
                        'user_id' => $ad->advertiser_id,
                        'title' => json_encode(['key' => 'notif_title_payment_rejected']),
                        'message' => json_encode(['key' => 'notif_msg_payment_rejected', 'args' => ['title' => $ad->title]]),
                        'is_read' => false,
                    ]);
                }
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'تم رفض الدفعة بنجاح.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ==========================================
    // 7. جلب صورة السند فقط (لتحسين أداء النظام)
    // ==========================================
    public function getReceipt($id)
    {
        $ledger = FinancialLedger::findOrFail($id);
        
        if (!$ledger->receipt_path) {
            return response()->json(['success' => false, 'message' => 'لا يوجد سند لهذه العملية.'], 404);
        }

        return response()->json([
            'success' => true,
            'receipt_path' => $ledger->receipt_path
        ], 200);
    }

    // ==========================================
    // 8. مسح السجلات القديمة وأرشفتها (توليد CSV + أرصدة افتتاحية)
    // ==========================================
    public function archiveRecords(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->can('manage_all')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'months' => 'required|integer|in:3,6,12,24'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        $cutoffDate = \Carbon\Carbon::now()->subMonths($request->months)->endOfDay();

        // 1. Fetch records to archive
        $recordsToArchive = FinancialLedger::where('created_at', '<=', $cutoffDate)->get();

        if ($recordsToArchive->isEmpty()) {
            return response()->json(['message' => 'لا توجد سجلات أقدم من المدة المحددة لأرشفتها'], 400);
        }

        // 2. Generate CSV Backup
        $directory = storage_path('app/public/archives');
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = 'financial_archive_' . now()->format('Y_m_d_His') . '.csv';
        $path = $directory . '/' . $filename;
        
        $handle = fopen($path, 'w');
        // Add BOM for Excel Arabic support
        fputs($handle, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));
        
        fputcsv($handle, ['ID', 'User ID', 'Ad ID', 'Screen ID', 'Transaction Type', 'Amount', 'Payment Method', 'Reference Number', 'Status', 'Notes', 'Created At']);
        
        foreach ($recordsToArchive as $record) {
            fputcsv($handle, [
                $record->ledger_id,
                $record->user_id,
                $record->advertisement_id,
                $record->screen_id,
                $record->transaction_type,
                $record->amount,
                $record->payment_method,
                $record->reference_number,
                $record->status,
                $record->notes,
                $record->created_at
            ]);
        }
        fclose($handle);

        // 3. Aggregate sums by user_id and transaction_type
        $aggregates = FinancialLedger::select('user_id', 'transaction_type', DB::raw('SUM(amount) as total_amount'))
            ->where('created_at', '<=', $cutoffDate)
            ->where('status', 'completed')
            ->groupBy('user_id', 'transaction_type')
            ->get();

        // 4. Delete old records
        FinancialLedger::where('created_at', '<=', $cutoffDate)->delete();

        // 5. Insert Rollover Balances
        foreach ($aggregates as $agg) {
            FinancialLedger::create([
                'user_id' => $agg->user_id,
                'transaction_type' => $agg->transaction_type,
                'amount' => $agg->total_amount,
                'status' => 'completed',
                'notes' => 'رصيد مرحل من الأرشيف (أقدم من ' . $request->months . ' أشهر)',
                'created_at' => clone $cutoffDate,
                'updated_at' => now(),
            ]);
        }

        // Clear cache
        Cache::flush();

        return response()->json([
            'message' => 'تم أرشفة ' . $recordsToArchive->count() . ' سجل بنجاح وحفظ نسخة احتياطية.',
            'download_url' => asset('storage/archives/' . $filename)
        ], 200);
    }
}
