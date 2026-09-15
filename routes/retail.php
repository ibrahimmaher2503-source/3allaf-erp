<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Actions\ApprovePartyWalletAdjustmentAction;
use App\Modules\Customer\Actions\ApproveProductWalletAdjustmentAction;
use App\Modules\Customer\Actions\RejectPartyWalletAdjustmentAction;
use App\Modules\Customer\Actions\RejectProductWalletAdjustmentAction;
use App\Modules\Customer\Actions\SaveCustomerPolicySettingAction;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerPolicySettingVersion;
use App\Modules\Customer\Models\PartyWalletLedger;
use App\Modules\Customer\Models\ProductWalletLedger;
use App\Modules\Customer\Support\CustomerPolicy;
use App\Modules\Customer\Support\WalletPolicy;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Platform\Actions\DeliverAttachment;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Actions\RequestApproval;
use App\Modules\Platform\Actions\StoreAttachment;
use App\Modules\Platform\Data\ApprovalRequestData;
use App\Modules\Platform\Data\AttachmentSourceReference;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Attachment;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\TaxSetting;
use App\Modules\Pricing\Services\EffectivePriceResolver;
use App\Modules\Pricing\Services\OpenPricePolicy;
use App\Modules\Retail\Actions\EnrollOfflineDeviceAction;
use App\Modules\Retail\Actions\OpenShiftAction;
use App\Modules\Retail\Actions\PosCartAction;
use App\Modules\Retail\Actions\QueueOfflineTransactionAction;
use App\Modules\Retail\Actions\RecordCashMovementAction;
use App\Modules\Retail\Actions\ResolveOfflineConflictAction;
use App\Modules\Retail\Actions\RetailSaleAction;
use App\Modules\Retail\Actions\SavePosFinancialSettingAction;
use App\Modules\Retail\Actions\SubmitBlindShiftCloseAction;
use App\Modules\Retail\Actions\SyncOfflineTransactionsAction;
use App\Modules\Retail\Enums\ShiftState;
use App\Modules\Retail\Exceptions\PosFinancialConfigurationException;
use App\Modules\Retail\Models\CashMovement;
use App\Modules\Retail\Models\GiftCard;
use App\Modules\Retail\Models\OfflineConflict;
use App\Modules\Retail\Models\OfflineDevice;
use App\Modules\Retail\Models\OfflineTransaction;
use App\Modules\Retail\Models\PosFinancialSettingVersion;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Models\RetailReturn;
use App\Modules\Retail\Models\Sale;
use App\Modules\Retail\Models\SaleLine;
use App\Modules\Retail\Models\SalePayment;
use App\Modules\Retail\Services\DiscountPolicy;
use App\Modules\Retail\Services\PosCalculationService;
use App\Modules\Retail\Support\DecimalMoney;
use App\Modules\Retail\Support\PosContextResolver;
use App\Modules\Retail\Support\PosFinancialSettingRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

Route::middleware(['auth', 'verified'])->group(function (): void {
    require __DIR__.'/pos-hotfix16.php';
    require __DIR__.'/pos-hotfix16-customers.php';
    require __DIR__.'/pos-hotfix17.php';
    Route::get('pos/products/{product}/thumbnail/{attachment}', function (Request $request, Product $product, Attachment $attachment) {
        abort_unless($request->user()?->can('pos_sales.view'), 403);
        $authorized = $attachment->purpose === 'product_image'
            && $attachment->source_type === Product::class
            && $attachment->source_id === (string) $product->id
            && $product->images()->where('attachment_id', $attachment->id)->exists();
        abort_unless($authorized, 403);

        $disk = Storage::disk($attachment->storage_disk);
        abort_unless($disk->exists($attachment->storage_path), 404);
        $source = $disk->path($attachment->storage_path);
        $cacheDirectory = storage_path('framework/cache/private-product-thumbnails');
        $cachePath = $cacheDirectory.DIRECTORY_SEPARATOR.$attachment->sha256.'-320.webp';

        if (! is_file($cachePath) && function_exists('imagecreatefromstring') && function_exists('imagewebp')) {
            $bytes = file_get_contents($source);
            $image = is_string($bytes) ? @imagecreatefromstring($bytes) : false;
            if ($image !== false) {
                $width = imagesx($image);
                $height = imagesy($image);
                $scale = min(320 / max(1, $width), 320 / max(1, $height), 1);
                $targetWidth = max(1, (int) round($width * $scale));
                $targetHeight = max(1, (int) round($height * $scale));
                $thumb = imagecreatetruecolor($targetWidth, $targetHeight);
                imagealphablending($thumb, false);
                imagesavealpha($thumb, true);
                imagecopyresampled($thumb, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
                if (! is_dir($cacheDirectory)) {
                    @mkdir($cacheDirectory, 0700, true);
                }
                if (is_dir($cacheDirectory)) {
                    @imagewebp($thumb, $cachePath, 82);
                }
                imagedestroy($thumb);
                imagedestroy($image);
            }
        }

        $response = is_file($cachePath)
            ? response()->file($cachePath, ['Content-Type' => 'image/webp'])
            : response()->file($source, ['Content-Type' => $attachment->detected_mime_type ?: $attachment->mime_type]);

        return $response->header('Cache-Control', 'private, max-age=86400, immutable')->header('X-Content-Type-Options', 'nosniff');
    })->whereNumber('product')->middleware('can:pos_sales.view')->name('pos.products.thumbnail');

    Route::get('pos/returns-readiness', function (Request $request) {
        abort_unless($request->user()->is_super_admin || $request->user()->can('returns.view') || $request->user()->can('returns_exchanges_gift_instruments.view'), 403);

        return redirect()->route('returns.index');
    })->name('returns.readiness');

    Route::get('party/readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('party_bookings_invoices.view'), 403);

        return view('pages.party.readiness', [
            'title' => __('Party Bookings and Invoices'),
            'description' => __('Review party bookings, services, schedules, invoices, payments, and final settlement.'),
            'items' => [
                ['title' => __('Party store scope'), 'body' => __('Party stores and operational context remain separate from retail products and supplier returns.')],
                ['title' => __('Services and packages'), 'body' => __('Service/package catalog, rental assets, consumables, and planned lines remain owner-configurable.')],
                ['title' => __('Schedule and location'), 'body' => __('Date/time/location, timezone, conflict, reschedule, and contact rules remain PENDING.')],
                ['title' => __('Customer, child, and privacy'), 'body' => __('Required contact, child, consent, privacy, and notes fields remain PENDING; no record is created.')],
                ['title' => __('Cancellation and responsibility'), 'body' => __('Cancellation, reschedule, assigned responsibility, and operational ownership rules remain PENDING.')],
                ['title' => __('Working invoice and final close'), 'body' => __('Editable-before-close, immutable-after-close, pricing, deposit, payment-on-account, and checklist rules remain PENDING.')],
            ],
        ]);
    })->middleware('can:party_bookings_invoices.view')->name('party.readiness');

    Route::get('reports-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('dashboard_reports.view'), 403);

        return view('pages.reports.readiness', [
            'title' => __('Reports'),
            'cards' => [
                ['title' => __('Source lineage and scope'), 'body' => __('Every figure must identify its source, snapshot, branch/store/user scope, and authorization before rendering.')],
                ['title' => __('Filters and KPI formulas'), 'body' => __('Date, comparison, status, activity, and domain filters plus KPI formulas remain pending; no aggregation runs here.')],
                ['title' => __('Reconciliation and freshness'), 'body' => __('Precision, currency, historical snapshots, freshness, and cache rules require approved source contracts.')],
                ['title' => __('Operational alerts'), 'body' => __('Triggers, severity, owner, due time, acknowledgement, resolution, source link, and deduplication remain pending.')],
                ['title' => __('Pagination and drilldown'), 'body' => __('Detail views must be bounded, indexed, scope-filtered, and separately authorized before drilldown exists.')],
                ['title' => __('Export boundary'), 'body' => __('PDF/Excel/export artifacts require permission, bounded scope, audit, and approved source values; none are generated.')],
            ],
        ]);
    })->middleware('can:dashboard_reports.view')->name('reports.readiness');

    Route::get('alerts-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('dashboard_reports.view'), 403);

        return view('pages.alerts.readiness', [
            'title' => __('Operational Alerts'),
            'cards' => [
                ['title' => __('Alert triggers and source eligibility'), 'body' => __('Low/zero stock, unpriced, price approval, transfer, count, invoice, shift, party, balance, asset, and job triggers require approved source contracts; no alert is evaluated.')],
                ['title' => __('Severity and owner role'), 'body' => __('Severity, escalation, due time, and responsible owner role remain PENDING; no priority or assignment is applied.')],
                ['title' => __('Scope and safe navigation'), 'body' => __('Branch, store, role, user, source link, and detail authorization remain PENDING; no cross-scope navigation is exposed.')],
                ['title' => __('Acknowledgement and resolution'), 'body' => __('Acknowledged, resolved, dismissed, reopened, and immutable history states remain PENDING; no state mutation is enabled.')],
                ['title' => __('Suppression and deduplication'), 'body' => __('Duplicate, stale, suppression, retry, and source-missing behavior remain PENDING; no duplicate alert is created.')],
                ['title' => __('Notification and exception queue'), 'body' => __('In-app/email delivery, pagination, filters, empty/error states, and role-safe queue navigation remain PENDING; no notification is sent.')],
            ],
        ]);
    })->middleware('can:dashboard_reports.view')->name('alerts.readiness');

    Route::get('exports-audit-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('dashboard_reports.view'), 403);

        return view('pages.exports.audit-readiness', [
            'title' => __('Export Center and Audit History'),
            'cards' => [
                ['title' => __('Formats and templates'), 'body' => __('PDF, Excel, CSV eligibility, localized headers, templates, page breaks, and output dimensions remain PENDING; no artifact is generated.')],
                ['title' => __('Limits and queueing'), 'body' => __('Row, file-size, timeout, pagination, queue, retry, and failed-job limits remain PENDING; no export job is queued.')],
                ['title' => __('Retention and protected storage'), 'body' => __('Expiry, deletion, private storage, short-lived links, and artifact ownership remain PENDING; no file is stored or offered.')],
                ['title' => __('Redaction and formula safety'), 'body' => __('Sensitive fields, costs, margins, payments, customer data, and spreadsheet formula injection rules remain PENDING.')],
                ['title' => __('Reauthorization and audit export'), 'body' => __('Generation/download permissions, scope, actor, filters, outcome, correlation ID, and export audit events remain PENDING.')],
                ['title' => __('Audit filters and immutable history'), 'body' => __('Date, actor, category, source, branch/store, action, result, reason, pagination, redaction, and append-only history remain PENDING.')],
            ],
        ]);
    })->middleware('can:dashboard_reports.view')->name('exports.audit.readiness');

    Route::get('master-data-migration-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('company_settings.view'), 403);

        return view('pages.master-data.migration-readiness', [
            'title' => __('Import Master Data'),
            'cards' => [
                ['title' => __('Approved source artifacts'), 'body' => __('Signed owner-approved lists or files are required for company, branches, stores, users, catalog, suppliers, prices, and opening stock; no production source is inferred.')],
                ['title' => __('Dependency load order'), 'body' => __('Company, branches, stores, selling mappings, drawers, users/scopes, categories, brands, products, barcodes, suppliers, prices, and opening stock must follow the documented dependency order.')],
                ['title' => __('Create-only staging'), 'body' => __('Migration imports remain create-only. Update mode, destructive replacement, and automatic merge are disabled until a deliberate correction policy exists.')],
                ['title' => __('File safety and private storage'), 'body' => __('Private storage, MIME and extension checks, formula/macro rejection, filename safety, and approved size/type limits remain pending.')],
                ['title' => __('Duplicate and error disposition'), 'body' => __('Duplicate codes, barcodes, phones, and source references require an approved disposition; invalid rows must remain isolated and visible.')],
                ['title' => __('Stage validation and preview'), 'body' => __('Upload, parse, row validation, referential checks, preview, and error-file behavior remain readiness-only; no batch is persisted here.')],
                ['title' => __('Reconciliation gates'), 'body' => __('Completeness, referential integrity, uniqueness, and sampled field-by-field checks must pass before any next stage or opening-stock action.')],
                ['title' => __('Maker/checker and audit'), 'body' => __('Importer and approver separation, scoped approval, immutable evidence, and audit records remain pending.')],
                ['title' => __('Backup, cutover, and rollback'), 'body' => __('Freeze timestamp, backup/restore proof, opening-stock approval, backdating block, rollback, and post-cutover correction rules remain pending.')],
            ],
        ]);
    })->middleware('can:company_settings.view')->name('master-data.migration.readiness');

    Route::get('operations-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('audit_logs.view'), 403);

        return view('pages.operations.readiness', [
            'title' => __('Operations and Handover'),
            'cards' => [
                ['title' => __('Runtime and environment'), 'body' => __('Host, domain, TLS, runtime versions, debug mode, environment separation, and named ownership remain pending; local behavior is not production proof.')],
                ['title' => __('Secrets and access'), 'body' => __('Secret storage, rotation, least privilege, redaction, service accounts, and access review remain pending; no secret is displayed here.')],
                ['title' => __('Workers, scheduler, and cache'), 'body' => __('Worker supervision, scheduler heartbeat, retries, failed jobs, queue idempotency, and cache availability remain pending.')],
                ['title' => __('Storage and monitoring'), 'body' => __('Private attachment storage, capacity, retention, request IDs, error monitoring, alert recipients, and escalation remain pending.')],
                ['title' => __('Backup and restore evidence'), 'body' => __('Destination, encryption, retention, restore rehearsal in isolation, RPO/RTO, recovery owner, and reconciliation evidence remain pending; no backup is created.')],
                ['title' => __('Printers, scanners, and devices'), 'body' => __('Managed device enrollment, branch/store binding, printer/scanner models, bridges, templates, safe failure, and browser evidence remain pending.')],
                ['title' => __('Support and handover'), 'body' => __('Named support contact, escalation path, incident ownership, known issues, runbooks, and handover evidence remain pending.')],
                ['title' => __('Training and release gate'), 'body' => __('Training attendees, role-specific scenarios, completion evidence, UAT, security review, rollback, and go-live sign-off remain pending.')],
            ],
        ]);
    })->middleware('can:audit_logs.view')->name('operations.readiness');

    Route::get('uat-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('audit_logs.view'), 403);

        $isArabic = str_starts_with(app()->getLocale(), 'ar');
        $cards = [
            ['title' => $isArabic ? 'حزمة السيناريوهات والتغطية' : 'Scenario pack and coverage', 'body' => $isArabic ? 'معرّفات السيناريوهات والمتطلبات والشروط والنتائج المتوقعة تحتاج اعتمادًا.' : 'Scenario IDs, requirements, prerequisites, and expected results need approval.'],
            ['title' => $isArabic ? 'المالكون والأدوار' : 'Owners and roles', 'body' => $isArabic ? 'مالكو الأعمال والتقنية والعيوب والقبول والتوقيع النهائي غير محددين.' : 'Business, technical, defect, acceptance, and final sign-off owners are not named.'],
            ['title' => $isArabic ? 'البيانات والأجهزة' : 'Data and devices', 'body' => app()->isLocale('ar-EG') ? 'بيانات الاختبار والطابعات والماسحات والأجهزة والبيئة المعتمدة مش متاحة.' : ($isArabic ? 'بيانات الاختبار والطابعات والماسحات والأجهزة والبيئة المعتمدة غير متاحة.' : 'Approved test data, printers, scanners, devices, and environments are unavailable.')],
            ['title' => $isArabic ? 'قاعدة الأدلة' : 'Evidence repository', 'body' => $isArabic ? 'المكان المحمي وسجلات Run ID واللقطات والمخرجات والمراجع غير معتمد.' : 'Protected location, Run IDs, screenshots, outputs, and source references are not approved.'],
            ['title' => $isArabic ? 'العيوب وإعادة الاختبار' : 'Defects and retesting', 'body' => $isArabic ? 'التصنيف والمالك وإثبات الإصلاح وإعادة اختبار الانحدار غير مسجلة.' : 'Severity, owner, fix evidence, and regression retest are not recorded.'],
            ['title' => $isArabic ? 'المطابقة' : 'Reconciliation', 'body' => $isArabic ? 'مطابقة المخزون والمدفوعات والمحافظ والتقارير والتدقيق غير منفذة.' : 'Stock, payments, wallets, reports, and audit reconciliation is not executed.'],
            ['title' => $isArabic ? 'القبول الكتابي' : 'Written acceptance', 'body' => app()->isLocale('ar-EG') ? 'مفيش اقفل للعيوب الحرجة أو توقيع UAT أو تفويض قبول.' : ($isArabic ? 'لا يوجد إغلاق للعيوب الحرجة أو توقيع UAT أو تفويض قبول.' : 'No critical-defect closure, UAT sign-off, or acceptance authorization exists.')],
        ];

        return view('pages.uat.readiness', ['title' => $isArabic ? 'مراجعة التحقق' : 'Validation Review', 'cards' => $cards]);
    })->name('uat.readiness');

    Route::get('release-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('audit_logs.view'), 403);

        $isArabic = str_starts_with(app()->getLocale(), 'ar');
        $cards = [
            ['title' => $isArabic ? 'نسخة الإصدار والتجميد' : 'Release version and freeze', 'body' => $isArabic ? 'رقم الإصدار ونقطة التجميد وخطة التراجع غير معتمدة.' : 'Release ID, freeze point, and rollback plan are not approved.'],
            ['title' => $isArabic ? 'بيانات القطع' : 'Cutover data', 'body' => $isArabic ? 'البيانات النهائية والمطابقة ومالك القطع غير محددة.' : 'Final data, reconciliation, and cutover owner are not defined.'],
            ['title' => $isArabic ? 'النسخ الاحتياطي والاستعادة' : 'Backup and restore', 'body' => app()->isLocale('ar-EG') ? 'سجل النسخ والاستعادة المعزولة وRPO/RTO مش متاح.' : ($isArabic ? 'سجل النسخ والاستعادة المعزولة وRPO/RTO غير متاح.' : 'Backup record, isolated restore, and RPO/RTO evidence are unavailable.')],
            ['title' => $isArabic ? 'المراقبة والتنبيه' : 'Monitoring and alerts', 'body' => $isArabic ? 'المراقبة وقنوات التصعيد وملكية الدعم غير مفعلة.' : 'Monitoring, escalation channels, and support ownership are not activated.'],
            ['title' => $isArabic ? 'الأجهزة والمستخدمون' : 'Devices and users', 'body' => $isArabic ? 'المستخدمون والأجهزة والطابعات والإعدادات النهائية غير معتمدة.' : 'Final users, devices, printers, and settings are not approved.'],
            ['title' => $isArabic ? 'التدريب والدعم' : 'Training and support', 'body' => $isArabic ? 'الحضور والـrunbook ومسار الحوادث والإحالة غير موثقة.' : 'Attendance, runbook, incident path, and handover are not documented.'],
            ['title' => $isArabic ? 'الموافقة على الإطلاق' : 'Go-live approval', 'body' => app()->isLocale('ar-EG') ? 'مفيش موافقة عميل أو تفويض إطلاق أو قبول إنتاجي.' : ($isArabic ? 'لا توجد موافقة عميل أو تفويض إطلاق أو قبول إنتاجي.' : 'No client approval, launch authorization, or production acceptance exists.')],
        ];

        return view('pages.release.readiness', ['title' => $isArabic ? 'ضوابط الإصدار' : 'Release Controls', 'cards' => $cards]);
    })->name('release.readiness');

    Route::get('quotations-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('dashboard_reports.view'), 403);

        return view('pages.quotations.readiness', [
            'title' => __('Quotations'),
            'description' => __('Review quotation details, customers, validity, prices, terms, approvals, and sharing options.'),
            'items' => [
                ['title' => __('Typed activity and customer'), 'body' => __('Retail and party activity types remain separate; customer/source linkage and mixed-line blocking remain PENDING.')],
                ['title' => __('Validity, expiry, and status'), 'body' => __('Draft, issued, expired, cancelled, superseded, validity, and closure rules remain PENDING; no status changes are enabled.')],
                ['title' => __('Prices and terms'), 'body' => __('Price source/snapshot, terms, notes, conditions, and owner-configurable wording remain PENDING; no price is approved or rendered.')],
                ['title' => __('Approval, audit, and numbering'), 'body' => __('Approval separation, reasons, idempotency, audit, unique identity, and document sequence rules remain PENDING; no number is allocated.')],
                ['title' => __('Print and share boundary'), 'body' => __('Privacy, print, and share output rules remain PENDING; no output or attachment is generated.')],
                ['title' => __('Future conversion exclusion'), 'body' => __('A quotation may retain a future source reference only; Phase 1 conversion to sale, party invoice, inventory, wallet, payment, or financial effect is blocked.')],
            ],
        ]);
    })->middleware('can:dashboard_reports.view')->name('quotations.readiness');

    Route::get('party/final-close-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('party_bookings_invoices.view'), 403);

        return view('pages.party.final-close-readiness', [
            'title' => __('Party Final Settlement'),
            'description' => __('Review the booking, invoice, payment, credit, receipt, and settlement details before closing a party order.'),
            'items' => [
                ['title' => __('Final readiness checklist'), 'body' => __('Booking, operating order, consumables, rental return/inspection, payment, and outstanding-operation checks remain PENDING; no close is enabled.')],
                ['title' => __('Working invoice and freeze'), 'body' => __('Editable-before-close, immutable-after-close, controlled corrections, and no mixed retail lines remain PENDING; no invoice is frozen.')],
                ['title' => __('Payment reconciliation and residual'), 'body' => __('Multiple payments on account, evidence, duplicates, residual, underpayment, overpayment, and reconciliation rules remain PENDING; no amount is calculated.')],
                ['title' => __('Party Wallet and credit separation'), 'body' => __('Party Wallet-only settlement, Product Wallet exclusion, credit enablement, and explicit source linkage remain PENDING; no wallet entry is created.')],
                ['title' => __('Final invoice and receipt'), 'body' => __('Immutable final invoice, exact receipt wording, privacy, numbering, reprint, and correction references remain PENDING; no document is generated.')],
                ['title' => __('Approval, idempotency, audit, and print'), 'body' => __('Close approval/SoD, double-close prevention, retry/concurrency, audit, document sequence, and print rules remain PENDING.')],
            ],
        ]);
    })->middleware('can:party_bookings_invoices.view')->name('party.final-close.readiness');

    Route::get('party/asset-events-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('party_bookings_invoices.view'), 403);

        return view('pages.party.asset-events-readiness', [
            'title' => __('Asset Events'),
            'description' => __('Review damage, loss, maintenance, assessment, responsibility, evidence, and correction details for rental assets.'),
            'items' => [
                ['title' => __('Damage and loss event'), 'body' => __('Asset, party/source, reason, assessment, responsible user, final status, and evidence rules remain PENDING; no event is created.')],
                ['title' => __('Maintenance lifecycle'), 'body' => __('Maintenance reason, owner, inspection, release, final state, and evidence rules remain PENDING; no maintenance event is recorded.')],
                ['title' => __('Assessment and responsibility'), 'body' => __('Owner-configurable checklist, assessment method, party/source, actor, reviewer, and scope rules remain PENDING.')],
                ['title' => __('Evidence and privacy'), 'body' => __('Attachment purpose, source reference, access, retention, privacy, and cost visibility rules remain PENDING; no file is uploaded.')],
                ['title' => __('Approval and cost boundary'), 'body' => __('Optional cost impact, approval limits, SoD, and finance separation remain PENDING; no amount is calculated or posted.')],
                ['title' => __('Depreciation and correction'), 'body' => __('Operational-only depreciation method/amount, immutable history, and referenced correction rules remain PENDING; no ledger posting occurs.')],
            ],
        ]);
    })->middleware('can:party_bookings_invoices.view')->name('party.asset-events.readiness');

    Route::get('party/assets-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('party_bookings_invoices.view'), 403);

        return view('pages.party.assets-readiness', [
            'title' => __('Rental Assets and Calendar'),
            'description' => __('Review rental asset identity, availability, reservations, checkout, return, condition, and calendar details.'),
            'items' => [
                ['title' => __('Rental asset identity'), 'body' => __('Unique code, name, category, location, status, condition, and immutable history remain PENDING; no asset is created.')],
                ['title' => __('Asset and consumable separation'), 'body' => __('Unique rental assets remain separate from consumables and retail products; no mixed resource is created.')],
                ['title' => __('Availability and lifecycle states'), 'body' => __('Available, reserved, checked out, inspection, damaged, maintenance, retired, and lost states remain PENDING.')],
                ['title' => __('Reservation interval and concurrency'), 'body' => __('Party source, timezone, buffer, overlap lock, retry, cancellation, reschedule, and conflict rules remain PENDING; no reservation is created.')],
                ['title' => __('Checkout, return, and condition'), 'body' => __('Pre/post condition, location, inspector, responsible user, missing/damaged status, and evidence rules remain PENDING.')],
                ['title' => __('Approval, audit, cost privacy, and print'), 'body' => __('State-transition authorization, immutable history, cost redaction, calendar, reservation, checkout, return, and print rules remain PENDING.')],
            ],
        ]);
    })->middleware('can:party_bookings_invoices.view')->name('party.assets.readiness');

    Route::get('party/operating-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('party_bookings_invoices.view'), 403);

        return view('pages.party.operating-readiness', [
            'title' => __('Party Operating Orders'),
            'description' => __('Review party operating orders, services, consumables, issues, returns, and reconciliation.'),
            'items' => [
                ['title' => __('Operating-order lifecycle'), 'body' => __('Draft, release, execute, complete, immutable history, and source-link rules remain PENDING; no order is created.')],
                ['title' => __('Party store and resource scope'), 'body' => __('Party-only store, services, rental resources, responsibilities, and source scope remain PENDING; no stock is reserved.')],
                ['title' => __('Consumables and UOM'), 'body' => __('Catalog, unit, fraction, availability, and party-store mapping rules remain PENDING; no quantity is rendered.')],
                ['title' => __('Issue and actual consumption'), 'body' => __('Issue, actual, controlled additions/removals, operator evidence, and completion rules remain PENDING; no issue is posted.')],
                ['title' => __('Unused return movement'), 'body' => __('Eligible unused return, referenced movement, condition, approval, and excess handling remain PENDING; no return is posted.')],
                ['title' => __('Reconciliation, approval, audit, and print'), 'body' => __('Source/balance reconciliation, concurrency, SoD, idempotency, immutable history, privacy, and print rules remain PENDING.')],
            ],
        ]);
    })->middleware('can:party_bookings_invoices.view')->name('party.operating.readiness');

    Route::get('party/payments-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('party_bookings_invoices.view'), 403);

        return view('pages.party.payments-readiness', [
            'title' => __('Party Payments and Balances'),
            'description' => __('Review party payment methods, deposits, evidence, receipts, balances, and settlement history.'),
            'items' => [
                ['title' => __('Party payment methods'), 'body' => __('Allowed source, method, actor, and scope rules remain PENDING; no payment is posted.')],
                ['title' => __('Deposit and payment on account'), 'body' => __('Multiple/partial payment, deposit, source invoice, and exact receipt-label rules remain PENDING.')],
                ['title' => __('Evidence and privacy'), 'body' => __('Evidence, attachment, source reference, privacy, and retention rules remain PENDING; no file is uploaded.')],
                ['title' => __('Idempotency and reversal'), 'body' => __('Duplicate, retry, concurrent, cancellation, reversal, and audit rules remain PENDING.')],
                ['title' => __('Overpayment and balance'), 'body' => __('Underpayment, overpayment, residual, credit, and source-linked balance rules remain PENDING; no amount is calculated.')],
                ['title' => __('Receipt and Party Wallet settlement'), 'body' => __('Numbering/reprint, approval/SoD, Party Wallet-only settlement, and Product Wallet exclusion remain PENDING.')],
            ],
        ]);
    })->middleware('can:party_bookings_invoices.view')->name('party.payments.readiness');

    Route::get('gift-receipts-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('returns_exchanges_gift_instruments.view'), 403);

        return view('pages.gift-instruments.readiness', [
            'kind' => 'gift-receipts',
            'title' => __('Gift Receipts'),
            'description' => __('Review price-free gift receipt references, eligibility, privacy, numbering, and print options.'),
            'boundary' => __('Gift Receipt policy values are configurable from Settings, but no source sale lines or receipt references are loaded until eligibility, privacy, numbering, and format are approved.'),
            'items' => [
                ['title' => __('Source eligibility'), 'detail' => __('Eligible approved-sale lines, return context, and source linkage remain PENDING.')],
                ['title' => __('Price-free output'), 'detail' => __('A Gift Receipt must exclude unit price, discount, tax, total, and any price-inference field.')],
                ['title' => __('Reprint and privacy'), 'detail' => __('Reprint reason, authorization, privacy scope, and immutable history remain PENDING.')],
                ['title' => __('Format and numbering'), 'detail' => __('Reference format, numbering, and print template remain PENDING; no artifact is generated.')],
            ],
            'emptyTitle' => __('No Gift Receipt references yet'),
            'emptyBody' => __('The empty state is intentional. Add owner-approved policy values first; do not create a Gift Receipt from this screen.'),
        ]);
    })->middleware('can:returns_exchanges_gift_instruments.view')->name('gift.receipts');

    Route::get('gift-cards-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('returns_exchanges_gift_instruments.view'), 403);

        return view('pages.gift-instruments.readiness', [
            'kind' => 'gift-cards',
            'title' => __('Gift Cards'),
            'description' => __('Review gift card identifiers, balances, holders, redemption, voiding, and expiry rules.'),
            'boundary' => __('Gift Card policy values are configurable from Settings, but no identifier, balance, holder, payment, ledger entry, or redemption is loaded until source and validity policies are approved.'),
            'items' => [
                ['title' => __('Unique identifier'), 'detail' => __('Identifier format, uniqueness, concurrency, and source reference remain PENDING.')],
                ['title' => __('Validity and expiry'), 'detail' => __('Validity period and expired-use blocking remain PENDING; no card is active.')],
                ['title' => __('Holder and privacy'), 'detail' => __('Holder/reference purpose and role-safe visibility remain PENDING; no holder data is loaded.')],
                ['title' => __('Redemption and void'), 'detail' => __('Partial/full use, overuse, void reason, approval, and immutable ledger rules remain PENDING.')],
            ],
            'emptyTitle' => __('No Gift Cards yet'),
            'emptyBody' => __('The empty state is intentional. Add owner-approved policy values first; do not issue or redeem a Gift Card from this screen.'),
        ]);
    })->middleware('can:returns_exchanges_gift_instruments.view')->name('gift.cards');

    Route::get('wallets/product', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('product_wallet.view'), 403);

        $entries = ProductWalletLedger::query()->visibleTo($user)->with(['customer', 'store'])->latestFirst()->paginate(20)->withQueryString();
        $store = Store::query()->visibleTo($user)->where('status', 'active')->with('company')->orderBy('id')->first();
        $policyError = null;
        try {
            WalletPolicy::for('product');
        } catch (InvalidArgumentException $exception) {
            $policyError = \App\Support\UserSafeError::message($exception);
        }
        $pendingAdjustments = $user->can('product_wallet.approve')
            ? ApprovalRecord::query()->visibleTo($user)->where('source_type', 'product_wallet_adjustments')->where('approval_state', 'pending')->where('decision_permission', 'product_wallet.approve')->latest('id')->limit(20)->get()
            : collect();

        return view('pages.wallets.ledger', [
            'title' => __('Product Wallet'), 'description' => __('Retail-only customer balance derived from a separate immutable, source-linked ledger.'),
            'wallet' => 'product', 'customer' => null, 'ledgerTable' => 'product_wallet_ledger', 'entries' => $entries,
            'balance' => bcadd((string) ProductWalletLedger::query()->visibleTo($user)->sum('amount'), '0', 4),
            'currencyCode' => strtoupper((string) $store?->company?->currency_code), 'policyError' => $policyError,
            'pendingAdjustments' => $pendingAdjustments, 'canSettle' => $user->can('product_wallet.settle'), 'canAdjust' => $user->can('product_wallet.adjust'), 'canApprove' => $user->can('product_wallet.approve'),
            'otherRoute' => 'wallets.party', 'otherCustomerRoute' => 'customers.party-wallet', 'otherPermission' => 'party_wallet.view', 'otherLabel' => __('Open Party Wallet'),
            'exportRoute' => $user->can('product_wallet.export') ? route('wallets.product.export') : null,
            'approveRoute' => static fn (int $approvalId): string => route('wallets.product.adjustments.approve', $approvalId),
            'rejectRoute' => static fn (int $approvalId): string => route('wallets.product.adjustments.reject', $approvalId), 'guidePrefix' => 'product-wallet',
        ]);

        return view('pages.wallets.ledger', [
            'title' => str_starts_with(app()->getLocale(), 'ar') ? 'محفظة المنتجات' : 'Product Wallet',
            'description' => str_starts_with(app()->getLocale(), 'ar') ? 'سجل مستقل للقيود المرتبطة بالمنتج، مع إبقاء المصدر والسياسة والصلاحية خارج نطاق هذه الشريحة.' : 'A separate ledger for product-linked entries while source, policy, and authorization remain outside this slice.',
            'ledgerTable' => 'product_wallet_ledger',
            'entries' => ProductWalletLedger::query()->latestFirst()->paginate(20),
            'otherRoute' => 'wallets.party',
            'otherPermission' => 'party_wallet.view',
            'otherLabel' => __('Open Party Wallet'),
            'guidePrefix' => 'product-wallet',
        ]);
    })->middleware('can:product_wallet.view')->name('wallets.product');

    Route::get('wallets/party', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('party_wallet.view'), 403);

        $entries = PartyWalletLedger::query()->visibleTo($user)->with(['customer', 'store'])->latestFirst()->paginate(20)->withQueryString();
        $store = Store::query()->visibleTo($user)->where('status', 'active')->with('company')->orderBy('id')->first();
        $policyError = null;
        try {
            WalletPolicy::for('party');
        } catch (InvalidArgumentException $exception) {
            $policyError = \App\Support\UserSafeError::message($exception);
        }
        $pendingAdjustments = $user->can('party_wallet.approve')
            ? ApprovalRecord::query()->visibleTo($user)->where('source_type', 'party_wallet_adjustments')->where('approval_state', 'pending')->where('decision_permission', 'party_wallet.approve')->latest('id')->limit(20)->get()
            : collect();

        return view('pages.wallets.ledger', [
            'title' => __('Party Wallet'), 'description' => __('Party-only customer balance derived from a separate immutable, source-linked ledger.'),
            'wallet' => 'party', 'customer' => null, 'ledgerTable' => 'party_wallet_ledger', 'entries' => $entries,
            'balance' => bcadd((string) PartyWalletLedger::query()->visibleTo($user)->sum('amount'), '0', 4),
            'currencyCode' => strtoupper((string) $store?->company?->currency_code), 'policyError' => $policyError,
            'pendingAdjustments' => $pendingAdjustments, 'canSettle' => $user->can('party_wallet.settle'), 'canAdjust' => $user->can('party_wallet.adjust'), 'canApprove' => $user->can('party_wallet.approve'),
            'otherRoute' => 'wallets.product', 'otherCustomerRoute' => 'customers.product-wallet', 'otherPermission' => 'product_wallet.view', 'otherLabel' => __('Open Product Wallet'),
            'exportRoute' => $user->can('party_wallet.export') ? route('wallets.party.export') : null,
            'approveRoute' => static fn (int $approvalId): string => route('wallets.party.adjustments.approve', $approvalId),
            'rejectRoute' => static fn (int $approvalId): string => route('wallets.party.adjustments.reject', $approvalId), 'guidePrefix' => 'party-wallet',
        ]);

        return view('pages.wallets.ledger', [
            'title' => str_starts_with(app()->getLocale(), 'ar') ? 'محفظة الأطراف' : 'Party Wallet',
            'description' => str_starts_with(app()->getLocale(), 'ar') ? 'سجل مستقل للقيود المرتبطة بالطرف، مع إبقاء المصدر والسياسة والصلاحية خارج نطاق هذه الشريحة.' : 'A separate ledger for party-linked entries while source, policy, and authorization remain outside this slice.',
            'ledgerTable' => 'party_wallet_ledger',
            'entries' => PartyWalletLedger::query()->latestFirst()->paginate(20),
            'otherRoute' => 'wallets.product',
            'otherPermission' => 'product_wallet.view',
            'otherLabel' => __('Open Product Wallet'),
            'guidePrefix' => 'party-wallet',
        ]);
    })->middleware('can:party_wallet.view')->name('wallets.party');

    Route::get('wallets/product/export', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('product_wallet.export'), 403);
        $rows = ProductWalletLedger::query()->visibleTo($user)->latestFirst()->limit(500)->get();
        app(RecordAuditEvent::class)->execute(category: 'reporting', event: 'product_wallet_exported', metadata: ['wallet' => 'product', 'row_count' => $rows->count(), 'scope_limited' => ! $user->is_super_admin]);

        return response()->streamDownload(static function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['customer_id', 'entry_type', 'amount', 'balance_before', 'balance_after', 'currency_code', 'source_type', 'source_id', 'created_at']);
            foreach ($rows as $entry) {
                fputcsv($handle, [$entry->customer_id, $entry->entry_type, $entry->amount, $entry->balance_before, $entry->balance_after, $entry->currency_code, $entry->source_type, $entry->source_id, $entry->created_at?->format('c')]);
            }
            fclose($handle);
        }, 'product-wallet.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    })->middleware('can:product_wallet.export')->name('wallets.product.export');

    Route::get('wallets/party/export', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('party_wallet.export'), 403);
        $rows = PartyWalletLedger::query()->visibleTo($user)->latestFirst()->limit(500)->get();
        app(RecordAuditEvent::class)->execute(category: 'reporting', event: 'party_wallet_exported', metadata: ['wallet' => 'party', 'row_count' => $rows->count(), 'scope_limited' => ! $user->is_super_admin]);

        return response()->streamDownload(static function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['customer_id', 'entry_type', 'amount', 'balance_before', 'balance_after', 'currency_code', 'source_type', 'source_id', 'created_at']);
            foreach ($rows as $entry) {
                fputcsv($handle, [$entry->customer_id, $entry->entry_type, $entry->amount, $entry->balance_before, $entry->balance_after, $entry->currency_code, $entry->source_type, $entry->source_id, $entry->created_at?->format('c')]);
            }
            fclose($handle);
        }, 'party-wallet.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    })->middleware('can:party_wallet.export')->name('wallets.party.export');

    Route::post('wallets/product/adjustments/{approvalId}/approve', function (Request $request, int $approvalId, ApproveProductWalletAdjustmentAction $action) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('product_wallet.approve'), 403);
        $approval = ApprovalRecord::query()->visibleTo($user)->whereKey($approvalId)->where('source_type', 'product_wallet_adjustments')->firstOrFail();
        $store = Store::query()->visibleTo($user)->whereKey($approval->store_id)->where('status', 'active')->firstOrFail();
        $action->execute($user, $approval, $store);

        return back()->with('success', __('Product Wallet adjustment approved and posted.'));
    })->middleware('can:product_wallet.approve')->name('wallets.product.adjustments.approve');

    Route::post('wallets/product/adjustments/{approvalId}/reject', function (Request $request, int $approvalId, RejectProductWalletAdjustmentAction $action) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('product_wallet.approve'), 403);
        $validated = $request->validate(['decision_note' => ['required', 'string', 'min:3', 'max:1000']]);
        $approval = ApprovalRecord::query()->visibleTo($user)->whereKey($approvalId)->where('source_type', 'product_wallet_adjustments')->firstOrFail();
        $action->execute($user, $approval, $validated['decision_note']);

        return back()->with('success', __('Product Wallet adjustment rejected and audited.'));
    })->middleware('can:product_wallet.approve')->name('wallets.product.adjustments.reject');

    Route::post('wallets/party/adjustments/{approvalId}/approve', function (Request $request, int $approvalId, ApprovePartyWalletAdjustmentAction $action) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('party_wallet.approve'), 403);
        $approval = ApprovalRecord::query()->visibleTo($user)->whereKey($approvalId)->where('source_type', 'party_wallet_adjustments')->firstOrFail();
        $store = Store::query()->visibleTo($user)->whereKey($approval->store_id)->where('status', 'active')->firstOrFail();
        $action->execute($user, $approval, $store);

        return back()->with('success', __('Party Wallet adjustment approved and posted.'));
    })->middleware('can:party_wallet.approve')->name('wallets.party.adjustments.approve');

    Route::post('wallets/party/adjustments/{approvalId}/reject', function (Request $request, int $approvalId, RejectPartyWalletAdjustmentAction $action) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('party_wallet.approve'), 403);
        $validated = $request->validate(['decision_note' => ['required', 'string', 'min:3', 'max:1000']]);
        $approval = ApprovalRecord::query()->visibleTo($user)->whereKey($approvalId)->where('source_type', 'party_wallet_adjustments')->firstOrFail();
        $action->execute($user, $approval, $validated['decision_note']);

        return back()->with('success', __('Party Wallet adjustment rejected and audited.'));
    })->middleware('can:party_wallet.approve')->name('wallets.party.adjustments.reject');

    Route::get('pos/session-legacy', function (Request $request, PosContextResolver $contextResolver) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.view'), 403);
        $context = $contextResolver->resolve($user);
        $store = $context->store;
        $shift = $context->shift;
        $selectedCustomer = null;
        $customerSearchResults = collect();
        $customerQuery = trim((string) $request->query('customer_q', ''));
        $customerPurposes = [];
        $customerPolicyError = null;
        if ($store !== null && $user->can('customers.view')) {
            try {
                $customerPurposes = CustomerPolicy::allowedPurposes('customer.consent.purpose')['value'];
            } catch (InvalidArgumentException $exception) {
                $customerPolicyError = \App\Support\UserSafeError::message($exception);
            }
            $selectedCustomerId = $request->session()->get('pos.customer_id');
            if (is_numeric($selectedCustomerId)) {
                $selectedCustomer = Customer::query()
                    ->visibleFrom($user, (int) $store->branch_id, (int) $store->id)
                    ->where('status', 'active')
                    ->find((int) $selectedCustomerId);
            }
            if ($customerQuery !== '') {
                $digits = preg_replace('/[^0-9]+/', '', $customerQuery);
                $customerSearchResults = Customer::query()->visibleTo($user)->where('status', 'active')
                    ->where(function ($query) use ($customerQuery, $digits): void {
                        $query->where('name_ar', 'like', '%'.$customerQuery.'%')->orWhere('name_en', 'like', '%'.$customerQuery.'%');
                        if (is_string($digits) && $digits !== '') {
                            $query->orWhere('phone_normalized', 'like', '%'.$digits.'%');
                        }
                    })->orderBy('name_en')->limit(10)->get();
            }
        }
        /** @var array<int, array{product_id: int, quantity: numeric-string}> $sessionCart */
        $sessionCart = $request->session()->get('pos.cart', []);
        $cart = collect($sessionCart);
        $productIds = $cart->pluck('product_id')->map(fn ($id): int => (int) $id)->all();
        $cartProducts = Product::query()->whereIn('id', $productIds)->get()->keyBy('id');
        $productQuery = trim((string) $request->query('product_q', ''));
        $categoryId = $request->integer('category') ?: null;
        $availableCategories = Category::query()
            ->active()
            ->whereHas('products', fn ($query) => $query->active())
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->limit(12)
            ->get();
        if ($categoryId !== null && ! $availableCategories->contains('id', $categoryId)) {
            $categoryId = null;
        }
        $availableProductsQuery = Product::query()
            ->active()
            ->familiesAndSimple()
            ->with([
                'category:id,name_ar,name_en',
                'barcodes' => fn ($query) => $query->active()->orderByDesc('is_primary')->orderBy('barcode'),
            ])
            ->orderBy('item_code')
            ->limit(24);
        if ($categoryId !== null) {
            $availableProductsQuery->where('category_id', $categoryId);
        }
        if ($productQuery !== '') {
            $availableProductsQuery->where(function ($query) use ($productQuery): void {
                $query->where('item_code', 'like', '%'.$productQuery.'%')
                    ->orWhere('name_ar', 'like', '%'.$productQuery.'%')
                    ->orWhere('name_en', 'like', '%'.$productQuery.'%')
                    ->orWhereHas('barcodes', fn ($barcodeQuery) => $barcodeQuery->active()->where('barcode', 'like', '%'.$productQuery.'%'));
            });
        }
        $availableProducts = $availableProductsQuery->get();
        $stockByProduct = $store === null
            ? collect()
            : StockBalance::query()
                ->where('store_id', $store->id)
                ->whereIn('product_id', $availableProducts->pluck('id'))
                ->get(['product_id', 'on_hand', 'reserved'])
                ->keyBy('product_id');
        $pricedProductIds = $availableProducts->pluck('id')->merge($productIds)->all();
        $priceMap = $store === null
            ? collect()
            : app(EffectivePriceResolver::class)->resolveForStore($pricedProductIds, (int) $store->id);
        $otherStoreAvailability = collect();
        if ($productQuery !== '' && $store !== null) {
            $visibleOtherStoreIds = Store::query()
                ->visibleTo($user)
                ->where('type', 'selling')
                ->where('status', 'active')
                ->where('id', '<>', $store->id)
                ->limit(50)
                ->pluck('id');
            if ($visibleOtherStoreIds->isNotEmpty()) {
                $otherStoreAvailability = StockBalance::query()
                    ->with(['product', 'store.branch'])
                    ->whereIn('store_id', $visibleOtherStoreIds)
                    ->whereRaw('(on_hand - reserved) > 0')
                    ->whereHas('product', function ($query) use ($productQuery): void {
                        $query->active()->where(function ($productSearch) use ($productQuery): void {
                            $productSearch->where('item_code', 'like', '%'.$productQuery.'%')
                                ->orWhere('name_ar', 'like', '%'.$productQuery.'%')
                                ->orWhere('name_en', 'like', '%'.$productQuery.'%')
                                ->orWhereHas('barcodes', fn ($barcodeQuery) => $barcodeQuery->active()->where('barcode', 'like', '%'.$productQuery.'%'));
                        });
                    })
                    ->orderBy('store_id')
                    ->limit(50)
                    ->get();
            }
        }
        $suspendedCount = Sale::query()->visibleTo($user)->where('status', 'suspended')->count();
        $paymentMethods = PaymentMethod::query()->where('status', 'active')->orderBy('code')->get();
        $cashMethod = $paymentMethods->first(fn (PaymentMethod $method): bool => $method->isCash());
        $giftCardMethods = $paymentMethods->filter(fn (PaymentMethod $method): bool => (string) $method->type === 'gift_card')->values();
        $electronicMethods = $paymentMethods->reject(fn (PaymentMethod $method): bool => ($cashMethod !== null && $method->is($cashMethod)) || (string) $method->type === 'gift_card')->values();

        $previewLines = [];
        foreach ($cart as $cartLine) {
            $approvalIds = collect([
                $cartLine['open_price_approval_id'] ?? null,
                $cartLine['discount_approval_id'] ?? null,
            ])->filter(fn ($id): bool => filled($id))->map(fn ($id): int => (int) $id)->unique()->values();
            if ($approvalIds->isNotEmpty()) {
                $approvals = ApprovalRecord::query()->whereIn('id', $approvalIds)->get()->keyBy('id');
                if (filled($cartLine['open_price_approval_id'] ?? null) && ($approval = $approvals->get((int) $cartLine['open_price_approval_id'])) !== null) {
                    $cartLine['open_price_approval_state'] = $approval->approval_state->value;
                }
                if (filled($cartLine['discount_approval_id'] ?? null) && ($approval = $approvals->get((int) $cartLine['discount_approval_id'])) !== null) {
                    $cartLine['discount_approval_state'] = $approval->approval_state->value;
                }
                $cart[$cart->search(fn (array $candidate): bool => (int) ($candidate['product_id'] ?? 0) === (int) $cartLine['product_id'])] = $cartLine;
            }
            $product = $cartProducts->get((int) $cartLine['product_id']);
            $price = $product && $store ? $priceMap->get($product->id) : null;
            if ($product && $price) {
                $previewLines[] = [
                    'product' => $product,
                    'quantity' => (string) $cartLine['quantity'],
                    'unit_price' => (string) ($cartLine['open_price_amount'] ?? $price->amount),
                    'discount_amount' => (string) ($cartLine['discount_amount'] ?? '0.00'),
                    'price' => $price,
                    'cart' => $cartLine,
                ];
            }
        }
        $request->session()->put('pos.cart', $cart->values()->all());
        app(\App\Modules\Retail\Support\PosOpenOrderManager::class)->persistSession($request, $user, $context);
        $taxApplicable = (bool) $request->session()->get('pos.tax_applicable', false);
        $effectiveTaxes = TaxSetting::query()
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>', now()))
            ->get();
        $taxSetting = $effectiveTaxes->count() === 1 && filled($effectiveTaxes->first()?->rate) ? $effectiveTaxes->first() : null;
        $previewError = null;
        try {
            $preview = $previewLines === [] ? null : app(PosCalculationService::class)->calculate(
                array_map(static fn (array $line): array => [
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'discount_amount' => $line['discount_amount'],
                ], $previewLines),
                '0.00',
                $taxApplicable
                    ? ['applicable' => true, 'rate' => $taxSetting?->rate, 'inclusive' => (bool) ($taxSetting?->is_tax_inclusive ?? false)]
                    : ['applicable' => false],
            );
        } catch (InvalidArgumentException $exception) {
            $preview = null;
            $previewError = \App\Support\UserSafeError::message($exception);
        }

        $checkoutToken = $request->session()->get('pos.checkout_token');
        if (! is_string($checkoutToken) || $checkoutToken === '') {
            $checkoutToken = (string) Str::uuid();
            $request->session()->put('pos.checkout_token', $checkoutToken);
        }

        $cashRoundingConfigurationError = null;
        try {
            $cashDenomination = app(PosCalculationService::class)->cashRoundingDenomination();
        } catch (PosFinancialConfigurationException $exception) {
            $cashDenomination = null;
            $cashRoundingConfigurationError = \App\Support\UserSafeError::message($exception);
        }
        $openPriceApprovalLimit = PosFinancialSettingRegistry::numericValue(PosFinancialSettingRegistry::OPEN_PRICE_APPROVAL_LIMIT);
        $discountApprovalLimit = PosFinancialSettingRegistry::numericValue(PosFinancialSettingRegistry::DISCOUNT_APPROVAL_LIMIT);

        return view('pages.pos.index', compact(
            'context', 'store', 'shift', 'cart', 'cartProducts', 'availableProducts', 'priceMap', 'suspendedCount',
            'paymentMethods', 'cashMethod', 'electronicMethods', 'giftCardMethods', 'previewLines', 'preview', 'previewError', 'checkoutToken',
            'cashDenomination', 'cashRoundingConfigurationError', 'openPriceApprovalLimit', 'discountApprovalLimit', 'taxApplicable', 'taxSetting', 'selectedCustomer', 'customerSearchResults', 'customerQuery', 'customerPurposes', 'customerPolicyError',
            'productQuery', 'categoryId', 'availableCategories', 'otherStoreAvailability', 'stockByProduct',
        ));
    })->middleware('can:pos_sales.view')->name('pos.legacy');

    // TSK-025 - real shift/cash workflow (DEC-066, docs/32). Replaces the
    // previous read-only readiness boundary.
    Route::get('pos/shift', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('shifts_cash_movements.view'), 403);

        $shift = PosShift::query()->visibleTo($user)->active()
            ->where('cashier_id', $user->id)
            ->with(['cashDrawer', 'cashMovements'])
            ->latest('opened_at')
            ->first();

        $closedShifts = PosShift::query()->visibleTo($user)
            ->where('cashier_id', $user->id)
            ->where('status', ShiftState::Closed->value)
            ->with(['cashDrawer'])
            ->latest('closed_at')
            ->limit(10)
            ->get();

        $openToken = (string) $request->session()->get('pos.shift.open_token', '');
        if ($openToken === '') {
            $openToken = (string) Str::uuid();
            $request->session()->put('pos.shift.open_token', $openToken);
        }
        $movementToken = null;
        $closeToken = null;
        if ($shift !== null) {
            $movementTokenKey = 'pos.shift.movement_token.'.$shift->getKey();
            $closeTokenKey = 'pos.shift.close_token.'.$shift->getKey().'.'.$shift->getAttribute('recount_count');
            $movementToken = (string) $request->session()->get($movementTokenKey, '');
            $closeToken = (string) $request->session()->get($closeTokenKey, '');
            if ($movementToken === '') {
                $movementToken = (string) Str::uuid();
                $request->session()->put($movementTokenKey, $movementToken);
            }
            if ($closeToken === '') {
                $closeToken = (string) Str::uuid();
                $request->session()->put($closeTokenKey, $closeToken);
            }
        }

        // docs/32 §10 - expected totals must not reach a cashier session
        // before submission, so this view is given no expected figures at all.
        return view('pages.pos.shift', [
            'shift' => $shift,
            'closedShifts' => $closedShifts,
            'drawers' => CashDrawer::query()->visibleTo($user)->operationallyConsistent()->where('status', 'active')->whereHas('store', fn ($query) => $query->where('status', 'active')->where('type', 'selling'))->with(['branch', 'store'])->orderBy('code')->get(),
            'methods' => PaymentMethod::query()->where('status', 'active')->orderBy('code')->get()->reject->isCash()->values(),
            'movementTypes' => CashMovement::TYPES,
            'openToken' => $openToken,
            'movementToken' => $movementToken,
            'closeToken' => $closeToken,
        ]);
    })->middleware('can:shifts_cash_movements.view')->name('pos.shift');

    Route::get('pos/shift/open', fn () => to_route('pos.shift'))
        ->middleware([
            'can:pos_sales.view',
            'can:shifts_cash_movements.view',
            'can:shifts_cash_movements.create',
        ])->name('pos.shift.open');

    Route::post('pos/shift/open', function (Request $request, OpenShiftAction $action) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('shifts_cash_movements.create'), 403);

        $validated = $request->validate([
            'cash_drawer_id' => ['required', 'integer', 'exists:cash_drawers,id'],
            'opening_float' => ['required', 'numeric', 'gte:0'],
            'idempotency_key' => ['required', 'uuid'],
            'confirm_outlet' => ['accepted'],
        ]);

        try {
            $drawer = CashDrawer::query()->find((int) $validated['cash_drawer_id']);
            if ($drawer === null) {
                throw new InvalidArgumentException(__('The selected cash drawer has an invalid company, branch, or store configuration. Ask an administrator to correct the drawer before opening a shift.'));
            }

            $action->execute(
                $user,
                $drawer,
                (string) $validated['opening_float'],
                'SHIFT-OPEN:'.$validated['idempotency_key'],
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['cash_drawer_id' => \App\Support\UserSafeError::message($e)]);
        }

        $request->session()->forget('pos.shift.open_token');

        return to_route('pos')->with('success', __('Shift opened.'));
    })->middleware('can:shifts_cash_movements.create')->name('pos.shift.store');

    Route::post('pos/shift/{shift}/cash-movement', function (Request $request, PosShift $shift, RecordCashMovementAction $action) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('shifts_cash_movements.create'), 403);
        abort_unless(PosShift::query()->visibleTo($user)->whereKey($shift->getKey())->exists(), 403);

        $validated = $request->validate([
            'movement_type' => ['required', 'string', Rule::in(CashMovement::TYPES)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:190'],
            'idempotency_key' => ['required', 'uuid'],
        ]);

        try {
            $action->execute(
                $user,
                $shift,
                $validated['movement_type'],
                (string) $validated['amount'],
                $validated['reason'],
                'CASH-MOVE:'.$validated['idempotency_key'],
                $validated['reference'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['amount' => \App\Support\UserSafeError::message($e)]);
        }

        $request->session()->forget('pos.shift.movement_token.'.$shift->getKey());

        return redirect()->route('pos.shift')->with('success', __('Cash movement recorded.'));
    })->middleware('can:shifts_cash_movements.create')->name('pos.shift.cash-movement');

    Route::post('pos/shift/{shift}/blind-close', function (Request $request, PosShift $shift, SubmitBlindShiftCloseAction $action) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('shifts_cash_movements.submit'), 403);
        abort_unless(PosShift::query()->visibleTo($user)->whereKey($shift->getKey())->exists(), 403);

        $validated = $request->validate([
            'actual_cash' => ['required', 'numeric', 'gte:0'],
            'actual_by_method' => ['nullable', 'array'],
            'actual_by_method.*' => ['nullable', 'numeric', 'gte:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['required', 'uuid'],
        ]);

        /** @var array<string, mixed> $submitted */
        $submitted = $validated['actual_by_method'] ?? [];
        $actualByMethod = [];
        foreach ($submitted as $code => $value) {
            if ($value !== null && $value !== '') {
                $actualByMethod[(string) $code] = (string) $value;
            }
        }

        try {
            $action->execute(
                $user,
                $shift,
                (string) $validated['actual_cash'],
                $actualByMethod,
                'SHIFT-CLOSE:'.$validated['idempotency_key'],
                $validated['notes'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['actual_cash' => \App\Support\UserSafeError::message($e)]);
        }

        // The cashier returns to a screen that deliberately shows no expected
        // or variance figure (docs/32 §15 redaction).
        $request->session()->forget('pos.shift.close_token.'.$shift->getKey().'.'.$shift->getAttribute('recount_count'));

        return redirect()->route('pos.shift')->with('success', __('Count submitted for review.'));
    })->middleware('can:shifts_cash_movements.submit')->name('pos.shift.blind-close');

    Route::get('pos/shift-variance', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        // Expected vs actual is manager/reviewer-only (docs/32 §13).
        abort_unless($user->can('shifts_cash_movements.approve'), 403);

        $shifts = PosShift::query()->visibleTo($user)
            ->whereIn('status', [ShiftState::ClosingSubmitted->value, ShiftState::VarianceReview->value])
            ->with(['cashDrawer', 'cashier', 'closingSubmissions'])
            ->latest('submitted_at')
            ->paginate(20);

        $closedShifts = PosShift::query()->visibleTo($user)
            ->where('status', ShiftState::Closed->value)
            ->with(['cashDrawer', 'cashier'])
            ->latest('closed_at')
            ->limit(20)
            ->get();

        return view('pages.pos.shift-variance', compact('shifts', 'closedShifts'));
    })->middleware('can:shifts_cash_movements.approve')->name('pos.shift-variance');

    /**
     * Closed-shift print data is deliberately assembled from immutable source
     * rows at request time. The routes do not expose an open/submitted shift,
     * and the visibility query prevents a permitted reviewer from printing a
     * foreign branch/store document by guessing its ID.
     *
     * Expected totals and variance are sensitive review data. A cashier may
     * print the actual close summary, while only a reviewer with the approval
     * permission receives expected/variance values.
     *
     * @return array<string, mixed>
     */
    $closedShiftPrintData = static function (Request $request, PosShift $routeShift, string $format): array {
        /** @var User $user */
        $user = $request->user();
        $shift = PosShift::query()
            ->visibleTo($user)
            ->whereKey($routeShift->getKey())
            ->where('status', ShiftState::Closed->value)
            ->with([
                'branch',
                'store.company',
                'cashDrawer',
                'cashier',
                'closingSubmissions',
                'cashMovements',
                'sales' => static fn ($query) => $query->approved()->with('payments.paymentMethod'),
            ])
            ->firstOrFail();

        $submission = $shift->closingSubmissions->sortByDesc('attempt')->first();
        abort_unless($submission !== null, 409, __('This closed shift has no immutable closing submission.'));

        $viewerCanSeeExpected = $user->can('shifts_cash_movements.approve');
        $event = $format === 'thermal' ? 'shift_thermal_printed' : 'shift_a4_printed';
        $alreadyPrinted = AuditLog::query()
            ->where('event', $event)
            ->where('source_type', PosShift::class)
            ->where('source_id', (string) $shift->getKey())
            ->exists();

        app(RecordAuditEvent::class)->execute(
            category: 'retail',
            event: $event,
            source: $shift,
            branchId: (int) $shift->getAttribute('branch_id'),
            storeId: (int) $shift->getAttribute('store_id'),
            metadata: [
                'format' => $format,
                'reprint' => $alreadyPrinted,
                'viewer_can_view_expected' => $viewerCanSeeExpected,
                'closing_document_number' => $shift->getAttribute('closing_document_number'),
            ],
        );

        $expectedByMethod = $submission->expected_by_method ?? [];
        $actualByMethod = $submission->actual_by_method ?? [];
        $methodVariance = $submission->method_variance ?? [];
        $methodCodes = array_values(array_unique(array_merge(
            array_keys($expectedByMethod),
            array_keys($actualByMethod),
            array_keys($methodVariance),
        )));
        sort($methodCodes);

        $salesTotal = $shift->sales->reduce(
            static fn (string $total, Sale $sale): string => bcadd($total, (string) $sale->payable_total, 2),
            '0.00',
        );

        return [
            'shift' => $shift,
            'submission' => $submission,
            'canViewExpected' => $viewerCanSeeExpected,
            'methodRows' => collect($methodCodes)->map(static fn (string $code): array => [
                'code' => $code,
                'expected' => $expectedByMethod[$code] ?? '0.00',
                'actual' => $actualByMethod[$code] ?? '0.00',
                'variance' => $methodVariance[$code] ?? '0.00',
            ]),
            'salesCount' => $shift->sales->count(),
            'salesTotal' => $salesTotal,
            // Return/refund source documents are not part of the current
            // retail schema. Keep the report truthful until US-020 exists.
            'refundCount' => 0,
            'refundTotal' => '0.00',
        ];
    };

    Route::get('pos/shifts/{shift}/print/thermal', function (Request $request, PosShift $shift) use ($closedShiftPrintData) {
        return view('pages.pos.shift-print-thermal', $closedShiftPrintData($request, $shift, 'thermal'));
    })->middleware('can:shifts_cash_movements.print')->name('pos.shift.print.thermal');

    Route::get('pos/shifts/{shift}/print/a4', function (Request $request, PosShift $shift) use ($closedShiftPrintData) {
        return view('pages.pos.shift-print-a4', $closedShiftPrintData($request, $shift, 'a4'));
    })->middleware('can:shifts_cash_movements.print')->name('pos.shift.print.a4');

    Route::get('pos/offline-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.view'), 403);

        $enabled = ! app()->isProduction() && (bool) config('offline.enabled');
        if (! $enabled) {
            return to_route('pos');
        }

        $devices = OfflineDevice::query()
            ->where('user_id', $user->id)
            ->where('revoked_at', null)
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get(['id', 'name', 'branch_id', 'store_id', 'shift_id', 'policy_version', 'schema_version', 'expires_at']);
        $queuedCount = OfflineTransaction::query()->where('user_id', $user->id)->where('state', 'queued')->count();
        $conflictCount = OfflineTransaction::query()->where('user_id', $user->id)->where('state', 'conflict')->count();
        $shifts = PosShift::query()->visibleTo($user)->open()->with(['store', 'cashDrawer'])->orderByDesc('opened_at')->limit(10)->get();

        return view('pages.pos.offline-readiness', compact('enabled', 'devices', 'queuedCount', 'conflictCount', 'shifts'));
    })->middleware('can:pos_sales.view')->name('pos.offline-readiness');

    Route::post('pos/offline/devices', function (Request $request, EnrollOfflineDeviceAction $action) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->hasPermission('offline_queue_conflicts.create'), 403);
        $validated = $request->validate([
            'shift_id' => ['required', 'integer', 'exists:pos_shifts,id'],
            'name' => ['required', 'string', 'max:100'],
            'token' => ['required', 'string', 'min:20', 'max:255'],
        ]);

        try {
            $action->execute($user, PosShift::query()->findOrFail($validated['shift_id']), $validated['name'], $validated['token']);
        } catch (InvalidArgumentException|LogicException $exception) {
            return back()->withErrors(['offline' => \App\Support\UserSafeError::message($exception)]);
        }

        return to_route('pos.offline-readiness')->with('success', __('Offline device enrolled. Keep its token only on the enrolled browser.'));
    })->middleware('can:offline_queue_conflicts.create')->name('pos.offline.devices.store');

    Route::get('pos/offline/queue', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->hasPermission('offline_queue_conflicts.view'), 403);
        $devices = OfflineDevice::query()->where('user_id', $user->id)->whereNull('revoked_at')
            ->orderByDesc('updated_at')->limit(10)->get(['id', 'name', 'policy_version', 'schema_version', 'expires_at']);
        $transactions = OfflineTransaction::query()->where('user_id', $user->id)
            ->with('device:id,name')->latest('captured_at')->paginate(25);

        return view('pages.pos.offline-queue', compact('devices', 'transactions'));
    })->middleware('can:offline_queue_conflicts.view')->name('pos.offline.queue');

    Route::post('pos/offline/queue', function (Request $request, QueueOfflineTransactionAction $action) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->hasPermission('offline_queue_conflicts.submit'), 403);
        $validated = $request->validate([
            'offline_device_id' => ['required', 'integer', 'exists:offline_devices,id'],
            'token' => ['required', 'string', 'max:255'],
            'payload' => ['required', 'array'],
        ]);
        $device = OfflineDevice::query()->findOrFail($validated['offline_device_id']);
        abort_unless((int) $device->user_id === (int) $user->id, 403);

        try {
            $action->execute($user, $device, $validated['token'], $validated['payload']);
        } catch (InvalidArgumentException|LogicException $exception) {
            return to_route('pos.offline.queue')->withErrors(['offline' => \App\Support\UserSafeError::message($exception)]);
        }

        return to_route('pos.offline.queue')->with('success', __('Offline transaction queued provisionally. No sale document has been created.'));
    })->middleware('can:offline_queue_conflicts.submit')->name('pos.offline.queue.store');

    Route::post('pos/offline/sync', function (Request $request, SyncOfflineTransactionsAction $action) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->hasPermission('offline_queue_conflicts.submit'), 403);
        $validated = $request->validate([
            'offline_device_id' => ['required', 'integer', 'exists:offline_devices,id'],
            'token' => ['required', 'string', 'max:255'],
        ]);
        $device = OfflineDevice::query()->findOrFail($validated['offline_device_id']);
        abort_unless((int) $device->user_id === (int) $user->id, 403);

        try {
            $result = $action->execute($user, $device, $validated['token']);
        } catch (InvalidArgumentException|LogicException $exception) {
            return to_route('pos.offline.queue')->withErrors(['offline' => \App\Support\UserSafeError::message($exception)]);
        }

        return to_route('pos.offline.queue')->with('success', __('Sync completed: :accepted accepted, :conflicted require review.', $result));
    })->middleware('can:offline_queue_conflicts.submit')->name('pos.offline.sync');

    Route::get('offline/conflicts', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->hasPermission('offline_queue_conflicts.approve'), 403);
        $query = OfflineTransaction::query()->where('offline_transactions.state', 'conflict');
        if (! $user->is_super_admin) {
            $branchIds = $user->branchScopes()->where('status', 'active')->pluck('branch_id')->all();
            $storeIds = $user->storeScopes()->where('status', 'active')->pluck('store_id')->all();
            $query->where(function ($scope) use ($branchIds, $storeIds): void {
                $scope->whereIn('offline_transactions.branch_id', $branchIds)->orWhereIn('offline_transactions.store_id', $storeIds);
            });
        }
        $transactions = $query->select('offline_transactions.*')
            ->selectSub(
                Branch::query()->select('code')->whereColumn('branches.id', 'offline_transactions.branch_id'),
                'offline_branch_code',
            )
            ->selectSub(Store::query()->select('code')->whereColumn('stores.id', 'offline_transactions.store_id'), 'offline_store_code')
            ->selectSub(OfflineDevice::query()->select('name')->whereColumn('offline_devices.id', 'offline_transactions.offline_device_id'), 'offline_device_name')
            ->latest('offline_transactions.synced_at')->paginate(25);

        return view('pages.pos.offline-conflicts', compact('transactions'));
    })->middleware('can:offline_queue_conflicts.approve')->name('offline.conflicts.index');

    Route::get('offline/conflicts/{transaction}', function (Request $request, OfflineTransaction $transaction) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->hasPermission('offline_queue_conflicts.approve'), 403);
        abort_unless($user->canAccessBranch((int) $transaction->branch_id) && $user->canAccessStore((int) $transaction->store_id), 404);
        $transaction->load(['device:id,name', 'branch:id,code', 'store:id,code']);
        $conflicts = OfflineConflict::query()->where('offline_transaction_id', $transaction->id)->latest()->get();

        return view('pages.pos.offline-conflict-show', compact('transaction', 'conflicts'));
    })->middleware('can:offline_queue_conflicts.approve')->name('offline.conflicts.show');

    Route::post('offline/conflicts/{transaction}/resolve', function (Request $request, OfflineTransaction $transaction, ResolveOfflineConflictAction $action) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->hasPermission('offline_queue_conflicts.approve'), 403);
        abort_unless($user->canAccessBranch((int) $transaction->branch_id) && $user->canAccessStore((int) $transaction->store_id), 404);
        $validator = Validator::make($request->all(), [
            'disposition' => ['required', Rule::in(['reject'])],
            'reason' => ['required', 'string', 'min:8', 'max:2000'],
        ]);
        if ($validator->fails()) {
            return to_route('offline.conflicts.show', $transaction)->withErrors($validator)->withInput();
        }
        $validated = $validator->validated();

        try {
            $action->execute($user, $transaction, $validated['disposition'], $validated['reason']);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['offline' => \App\Support\UserSafeError::message($exception)]);
        }

        return to_route('offline.conflicts.index')->with('success', __('Offline conflict disposition was recorded in the audit trail.'));
    })->middleware('can:offline_queue_conflicts.approve')->name('offline.conflicts.resolve');

    Route::get('customers/loyalty-readiness', function (Request $request) {
        abort_unless($request->user()?->can('customers.view'), 403);

        return to_route('customers.index');
    })->middleware('can:customers.view')->name('customers.loyalty-readiness');

    Route::get('admin/settings/customer-loyalty', function (Request $request) {
        abort_unless($request->user()?->can('company_settings.view'), 403);

        $keys = [
            'customer.consent.purpose',
            'customer.consent.wording',
            'customer.consent.retention',
            'loyalty.retail_rule',
            'loyalty.expiry_policy',
            'loyalty.rounding_policy',
            'loyalty.approval_policy',
            'loyalty.ledger_integrity',
        ];
        $latest = CustomerPolicySettingVersion::query()
            ->whereIn('key', $keys)
            ->orderByDesc('version')
            ->get()
            ->groupBy('key')
            ->map(static fn ($versions) => $versions->first());

        $object = static function (?CustomerPolicySettingVersion $setting): array {
            $value = json_decode((string) $setting?->value, true);

            return is_array($value) ? $value : [];
        };
        $purposes = $object($latest->get('customer.consent.purpose'));
        $retention = $object($latest->get('customer.consent.retention'));
        $wording = $object($latest->get('customer.consent.wording'));
        $retailRule = $object($latest->get('loyalty.retail_rule'));
        $expiry = $object($latest->get('loyalty.expiry_policy'));

        return view('pages.admin.customer-loyalty-settings', [
            'customer' => [
                'purposes' => array_values(array_filter($purposes, 'is_string')),
                'wording_version' => (string) ($wording['version'] ?? ''),
                'wording_text' => (string) ($wording['text'] ?? ''),
                'retention_days' => $retention['days'] ?? 365,
                'version' => max((int) ($latest->get('customer.consent.purpose')?->version ?? 0), (int) ($latest->get('customer.consent.wording')?->version ?? 0), (int) ($latest->get('customer.consent.retention')?->version ?? 0)),
            ],
            'loyalty' => [
                'earn_points_per_currency' => (string) ($retailRule['earn_points_per_currency'] ?? '1'),
                'redeem_currency_per_point' => (string) ($retailRule['redeem_currency_per_point'] ?? '0.01'),
                'expiry_days' => $expiry['days'] ?? 30,
                'version' => max((int) ($latest->get('loyalty.retail_rule')?->version ?? 0), (int) ($latest->get('loyalty.expiry_policy')?->version ?? 0)),
            ],
        ]);
    })->middleware('can:company_settings.view')->name('admin.settings.customer-loyalty');

    Route::post('admin/settings/customer-loyalty', function (Request $request, SaveCustomerPolicySettingAction $action) {
        abort_unless($request->user()?->can('company_settings.edit'), 403);

        $data = $request->validate([
            'section' => ['required', Rule::in(['customer', 'loyalty'])],
            'purposes' => ['required_if:section,customer', 'array', 'min:1', 'max:3'],
            'purposes.*' => [Rule::in(['service_delivery', 'loyalty', 'marketing'])],
            'wording_version' => ['required_if:section,customer', 'string', 'max:80'],
            'wording_text' => ['required_if:section,customer', 'string', 'min:10', 'max:2000'],
            'retention_days' => ['required_if:section,customer', 'integer', 'min:0', 'max:36500'],
            'earn_points_per_currency' => ['required_if:section,loyalty', 'numeric', 'min:0.00000001', 'regex:/^(?:0|[1-9]\\d*)(?:\\.\\d{1,8})?$/'],
            'redeem_currency_per_point' => ['required_if:section,loyalty', 'numeric', 'min:0.00000001', 'regex:/^(?:0|[1-9]\\d*)(?:\\.\\d{1,8})?$/'],
            'expiry_days' => ['required_if:section,loyalty', 'integer', 'min:1', 'max:36500'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $notes = $data['notes'] ?? null;
        if ($data['section'] === 'customer') {
            $action->executeMany([
                'customer.phone_normalization' => 'digits_only',
                'customer.consent.purpose' => json_encode(array_values($data['purposes']), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'customer.consent.wording' => json_encode([
                    'version' => trim($data['wording_version']),
                    'text' => trim($data['wording_text']),
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'customer.consent.retention' => json_encode(['days' => (int) $data['retention_days']], JSON_THROW_ON_ERROR),
            ], $notes);
        } else {
            $action->executeMany([
                'loyalty.retail_rule' => json_encode([
                    'earn_points_per_currency' => $data['earn_points_per_currency'],
                    'redeem_currency_per_point' => $data['redeem_currency_per_point'],
                ], JSON_THROW_ON_ERROR),
                'loyalty.expiry_policy' => json_encode(['days' => (int) $data['expiry_days']], JSON_THROW_ON_ERROR),
                'loyalty.rounding_policy' => '{"earn":"floor","redeem":"floor"}',
                'loyalty.approval_policy' => '{"adjustment_requires_approval":true}',
                'loyalty.ledger_integrity' => '{"enabled":true}',
            ], $notes);
        }

        return to_route('admin.settings.customer-loyalty')->with('status', str_starts_with(app()->getLocale(), 'ar') ? 'تم حفظ نسخة جديدة من الإعدادات.' : 'A new settings version was saved.');
    })->middleware('can:company_settings.edit')->name('admin.settings.customer-loyalty.save');

    Route::get('pos/financial-readiness', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('company_settings.view'), 403);

        $latest = PosFinancialSettingVersion::query()
            ->whereIn('key', array_keys(PosFinancialSettingRegistry::all()))
            ->orderByDesc('version')
            ->get()
            ->groupBy('key')
            ->map(fn ($versions) => $versions->first());

        return view('pages.pos.financial-readiness', [
            'activePaymentMethods' => PaymentMethod::query()->where('status', 'active')->count(),
            'activeTaxSettings' => TaxSetting::query()->where('status', 'active')->count(),
            'definitions' => PosFinancialSettingRegistry::all(),
            'latestSettings' => $latest,
        ]);
    })->middleware('can:company_settings.view')->name('pos.financial-readiness');

    Route::post('admin/settings/pos-financial', function (Request $request, SavePosFinancialSettingAction $action) {
        abort_unless($request->user()?->can('company_settings.edit'), 403);
        $data = $request->validate([
            'key' => ['required', 'string', Rule::in(array_keys(PosFinancialSettingRegistry::all()))],
            'value' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $action->execute($data['key'], $data['value'] ?? null, $data['notes'] ?? null);

        return back()->with('success', __('A new POS financial setting version was saved.'));
    })->middleware('can:company_settings.edit')->name('admin.settings.pos-financial.save');

    Route::post('pos/session-legacy/cart/add', function (Request $request) {
        abort_unless($request->user()?->can('pos_sales.create'), 403);
        $data = $request->validate(['product_id' => ['required', 'integer'], 'quantity' => ['required', 'integer', 'min:1', 'max:999999']]);
        try {
            app(PosCartAction::class)->add($request, $request->user(), (int) $data['product_id'], (string) $data['quantity']);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['cart' => \App\Support\UserSafeError::message($exception)]);
        }

        return back()->with('success', __('Product added to cart.'));
    })->middleware('can:pos_sales.create')->name('pos.cart.add.legacy');

    Route::post('pos/session-legacy/cart/remove', function (Request $request) {
        abort_unless($request->user()?->can('pos_sales.create'), 403);
        $data = $request->validate(['product_id' => ['required', 'integer']]);
        /** @var array<int, array{product_id: int, quantity: numeric-string}> $sessionCart */
        $sessionCart = $request->session()->get('pos.cart', []);
        app(PosCartAction::class)->remove($request, $request->user(), (int) $data['product_id']);

        return back();
    })->middleware('can:pos_sales.create')->name('pos.cart.remove.legacy');

    Route::post('pos/session-legacy/cart/quantity', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.create'), 403);
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999999'],
        ]);
        try {
            app(PosCartAction::class)->quantity($request, $user, (int) $data['product_id'], (string) $data['quantity']);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['cart' => \App\Support\UserSafeError::message($exception)]);
        }

        return back()->with('success', __('Cart quantity updated.'));
    })->middleware('can:pos_sales.create')->name('pos.cart.quantity.legacy');

    Route::post('pos/cart/discount', function (Request $request, DiscountPolicy $policy, PosContextResolver $contextResolver) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.apply_discount'), 403);
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'discount_type' => ['required', Rule::in([DiscountPolicy::TYPE_LINE, DiscountPolicy::TYPE_CUSTOMER_GROUP])],
            'discount_amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['nullable', 'string', 'max:500'],
            'expected_revision' => ['required', 'integer', 'min:0'],
        ]);

        /** @var array<int, array<string, mixed>> $sessionCart */
        $sessionCart = $request->session()->get('pos.cart', []);
        $cart = collect($sessionCart);
        $index = $cart->search(fn (array $line): bool => (int) $line['product_id'] === (int) $data['product_id']);
        abort_if($index === false, 404);
        $line = $cart[$index];
        if ((int) ($line['discount_revision'] ?? 0) !== (int) $data['expected_revision']) {
            return back()->withErrors(['discount' => __('The discount changed in another request. Review the current value and try again.')]);
        }

        $context = $contextResolver->resolve($user);
        if (! $context->isReady() || $context->store === null) {
            return back()->withErrors(['discount' => $context->disabledReason]);
        }
        $store = $context->store;
        $price = app(EffectivePriceResolver::class)->resolve((int) $line['product_id'], $store->id);
        abort_if($price === null, 422, __('The product has no effective price.'));
        $unitPrice = (string) ($line['open_price_amount'] ?? $price->amount);
        $gross = DecimalMoney::round(bcmul((string) $line['quantity'], $unitPrice, 8));
        $existingType = filled($line['discount_type'] ?? null) ? (string) $line['discount_type'] : null;

        $discountAmount = DecimalMoney::round((string) $data['discount_amount']);
        $requiresApproval = $policy->requiresApproval($discountAmount, $gross);
        $approvalLimit = PosFinancialSettingRegistry::numericValue(PosFinancialSettingRegistry::DISCOUNT_APPROVAL_LIMIT);
        $checkoutToken = (string) $request->session()->get('pos.checkout_token', Str::uuid());
        $request->session()->put('pos.checkout_token', $checkoutToken);
        $nextRevision = (int) ($line['discount_revision'] ?? 0) + 1;
        $sourceHash = app(OpenPricePolicy::class)->fingerprint([
            'product_id' => (int) $line['product_id'],
            'store_id' => (int) $store->id,
            'price_line_id' => (int) $price->id,
            'price_updated_at' => (string) $price->updated_at,
            'gross' => $gross,
            'discount_amount' => $discountAmount,
            'discount_type' => (string) $data['discount_type'],
            'reason' => trim((string) ($data['reason'] ?? '')),
            'existing_type' => $existingType,
            'existing_amount' => (string) ($line['discount_amount'] ?? '0.00'),
            'approval_limit' => $approvalLimit,
        ]);
        $approval = null;
        if ($requiresApproval) {
            $approval = app(RequestApproval::class)->execute(new ApprovalRequestData(
                sourceType: 'pos_discount',
                sourceId: 'cart:'.$checkoutToken.':product:'.$line['product_id'].':discount-revision:'.$nextRevision,
                sourceVersion: (string) $price->id.':'.(string) $price->updated_at,
                requestedAction: 'approve_discount',
                requestPermission: 'pos_sales.apply_discount',
                branchId: (int) $store->branch_id,
                storeId: (int) $store->id,
                reasonText: trim((string) ($data['reason'] ?? '')),
                limitContext: [
                    'product_id' => (int) $line['product_id'],
                    'gross' => $gross,
                    'discount_amount' => $discountAmount,
                    'discount_type' => (string) $data['discount_type'],
                    'approval_limit_percent' => $approvalLimit,
                ],
                sourceHash: $sourceHash,
                idempotencyKey: 'POS-DISCOUNT:'.$checkoutToken.':'.$line['product_id'].':'.$nextRevision.':'.$discountAmount,
                expiresAt: now()->addMinutes(30),
                decisionPermission: 'pos_sales.discount_approve',
            ));
        }

        try {
            $resolved = $policy->buildLineDiscount(
                actor: $user,
                discountAmount: $discountAmount,
                baseAmount: $gross,
                newType: (string) $data['discount_type'],
                existingType: $existingType,
                reason: $data['reason'] ?? null,
                approved: $requiresApproval,
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['discount' => \App\Support\UserSafeError::message($exception)]);
        }

        $before = [
            'type' => $existingType,
            'amount' => (string) ($line['discount_amount'] ?? '0.00'),
            'revision' => (int) ($line['discount_revision'] ?? 0),
        ];
        $line['discount_type'] = $resolved['discount_type'];
        $line['discount_amount'] = DecimalMoney::round($resolved['discount_amount']);
        $line['discount_reason'] = $data['reason'] ?? null;
        $line['discount_replaces'] = $resolved['replaces'] ?? $existingType;
        $line['discount_previous_type'] = $existingType;
        $line['discount_previous_amount'] = $before['amount'];
        $line['discount_revision'] = $before['revision'] + 1;
        $line['discount_approval_id'] = $approval?->id;
        $line['discount_approval_state'] = $approval?->approval_state->value;
        $cart[$index] = $line;
        $request->session()->put('pos.cart', $cart->values()->all());
        app(\App\Modules\Retail\Support\PosOpenOrderManager::class)->persistSession($request, $user, $context);

        app(RecordAuditEvent::class)->execute(
            category: 'retail',
            event: $existingType === null ? 'pos_cart_discount_applied' : 'pos_cart_discount_replaced',
            before: $before,
            after: ['type' => $line['discount_type'], 'amount' => $line['discount_amount'], 'revision' => $line['discount_revision']],
            branchId: (int) $store->branch_id,
            storeId: (int) $store->id,
            reasonText: $data['reason'] ?? null,
            metadata: ['product_id' => (int) $line['product_id'], 'actor_id' => $user->id, 'approval_record_id' => $approval?->id, 'approval_limit_percent' => $approvalLimit],
        );

        return back()->with('success', $requiresApproval
            ? __('Discount saved. Independent manager approval is required before checkout.')
            : ($existingType === null ? __('Discount applied.') : __('The previous discount was replaced.')));
    })->middleware('can:pos_sales.apply_discount')->name('pos.cart.discount');

    Route::post('pos/cart/open-price', function (Request $request, OpenPricePolicy $policy, PosContextResolver $contextResolver) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.open_price'), 403);
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:500'],
            'expected_revision' => ['required', 'integer', 'min:0'],
        ]);

        /** @var array<int, array<string, mixed>> $sessionCart */
        $sessionCart = $request->session()->get('pos.cart', []);
        $cart = collect($sessionCart);
        $index = $cart->search(fn (array $line): bool => (int) $line['product_id'] === (int) $data['product_id']);
        abort_if($index === false, 404);
        $line = $cart[$index];
        if ((int) ($line['pricing_revision'] ?? 0) !== (int) $data['expected_revision']) {
            return back()->withErrors(['open_price' => __('The open price changed in another request. Review it and try again.')]);
        }

        $context = $contextResolver->resolve($user);
        if (! $context->isReady() || $context->store === null) {
            return back()->withErrors(['open_price' => $context->disabledReason]);
        }
        $store = $context->store;
        $price = app(EffectivePriceResolver::class)->resolve((int) $line['product_id'], $store->id);
        abort_if($price === null || ! $price->open_price_allowed, 422, __('Open price is not enabled for this product.'));
        $reference = DecimalMoney::normalize((string) ($price->reference_amount ?? $price->amount), 4);
        $policy->validateOrThrow(
            referenceAmount: $reference,
            requestedAmount: (string) $data['amount'],
            minimum: $price->open_price_minimum === null ? null : (string) $price->open_price_minimum,
            maximum: $price->open_price_maximum === null ? null : (string) $price->open_price_maximum,
            hasPermission: true,
            reason: (string) $data['reason'],
        );

        $before = ['amount' => $line['open_price_amount'] ?? null, 'revision' => (int) ($line['pricing_revision'] ?? 0)];
        $approvalLimit = PosFinancialSettingRegistry::numericValue(PosFinancialSettingRegistry::OPEN_PRICE_APPROVAL_LIMIT);
        $requiresApproval = $policy->requiresApproval($reference, (string) $data['amount'], $approvalLimit);
        $checkoutToken = (string) $request->session()->get('pos.checkout_token', Str::uuid());
        $request->session()->put('pos.checkout_token', $checkoutToken);
        $sourceHash = $policy->fingerprint([
            'product_id' => (int) $line['product_id'],
            'store_id' => (int) $store->id,
            'price_line_id' => (int) $price->id,
            'price_updated_at' => (string) $price->updated_at,
            'reference' => $reference,
            'minimum' => $price->open_price_minimum,
            'maximum' => $price->open_price_maximum,
            'requested_amount' => DecimalMoney::normalize((string) $data['amount'], 4),
            'reason' => trim((string) $data['reason']),
            'approval_limit' => $approvalLimit,
        ]);
        $approval = null;
        if ($requiresApproval) {
            $approval = app(RequestApproval::class)->execute(new ApprovalRequestData(
                sourceType: 'pos_open_price',
                sourceId: 'cart:'.$checkoutToken.':product:'.$line['product_id'].':revision:'.((int) $before['revision'] + 1),
                sourceVersion: (string) $price->id.':'.(string) $price->updated_at,
                requestedAction: 'approve_open_price',
                requestPermission: 'pos_sales.open_price',
                branchId: (int) $store->branch_id,
                storeId: (int) $store->id,
                reasonText: trim((string) $data['reason']),
                limitContext: [
                    'product_id' => (int) $line['product_id'],
                    'reference_amount' => $reference,
                    'minimum' => $price->open_price_minimum,
                    'maximum' => $price->open_price_maximum,
                    'requested_amount' => DecimalMoney::normalize((string) $data['amount'], 4),
                    'approval_limit_percent' => $approvalLimit,
                ],
                sourceHash: $sourceHash,
                idempotencyKey: 'POS-OPEN-PRICE:'.$checkoutToken.':'.$line['product_id'].':'.((int) $before['revision'] + 1),
                expiresAt: now()->addMinutes(30),
                decisionPermission: 'pos_sales.open_price_approve',
            ));
        }

        $line['open_price_amount'] = DecimalMoney::normalize((string) $data['amount'], 4);
        $line['open_price_reason'] = trim((string) $data['reason']);
        $line['open_price_approval_id'] = $approval?->id;
        $line['open_price_approval_state'] = $approval?->approval_state->value;
        $line['pricing_revision'] = $before['revision'] + 1;
        $cart[$index] = $line;
        $request->session()->put('pos.cart', $cart->values()->all());
        app(\App\Modules\Retail\Support\PosOpenOrderManager::class)->persistSession($request, $user, $context);

        app(RecordAuditEvent::class)->execute(
            category: 'pricing',
            event: 'pos_cart_open_price_set',
            before: $before,
            after: ['reference' => $reference, 'amount' => $line['open_price_amount'], 'minimum' => $price->open_price_minimum, 'maximum' => $price->open_price_maximum, 'revision' => $line['pricing_revision']],
            branchId: (int) $store->branch_id,
            storeId: (int) $store->id,
            reasonText: (string) $data['reason'],
            metadata: ['product_id' => (int) $line['product_id'], 'actor_id' => $user->id],
        );

        return back()->with('success', $requiresApproval
            ? __('Open price saved. Independent manager approval is required before checkout.')
            : __('Open price applied to the basket line.'));
    })->middleware('can:pos_sales.open_price')->name('pos.cart.open-price');

    Route::post('pos/cart/tax', function (Request $request, PosContextResolver $contextResolver) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.apply_tax'), 403);
        $context = $contextResolver->resolve($user);
        if (! $context->isReady() || $context->store === null) {
            return back()->withErrors(['tax' => $context->disabledReason]);
        }
        $store = $context->store;
        $data = $request->validate(['tax_applicable' => ['required', 'boolean']]);
        $applicable = (bool) $data['tax_applicable'];
        $setting = null;
        if ($applicable) {
            $effective = TaxSetting::query()
                ->where('status', 'active')
                ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
                ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>', now()))
                ->get();
            if ($effective->count() !== 1 || blank($effective->first()?->rate)) {
                return back()->withErrors(['tax' => __('Tax cannot be enabled until exactly one effective policy with a configured rate exists.')]);
            }
            $setting = $effective->first();
        }

        $before = (bool) $request->session()->get('pos.tax_applicable', false);
        app(\App\Modules\Retail\Support\PosOpenOrderManager::class)->setTax($request, $user, $context, $applicable);
        app(RecordAuditEvent::class)->execute(
            category: 'retail', event: 'pos_cart_tax_selection_changed',
            before: ['tax_applicable' => $before],
            after: ['tax_applicable' => $applicable, 'tax_setting_id' => $setting?->id, 'rate_snapshot' => $setting?->rate],
            branchId: (int) $store->branch_id,
            storeId: (int) $store->id,
            reasonText: __('Invoice tax selection changed at POS.'),
            metadata: ['actor_id' => $user->id],
        );

        return back()->with('success', $applicable ? __('Tax enabled for this basket.') : __('Tax disabled for this basket.'));
    })->middleware('can:pos_sales.apply_tax')->name('pos.cart.tax');

    Route::post('pos/session-legacy/cart/clear', function (Request $request) {
        abort_unless($request->user()?->can('pos_sales.create'), 403);
        $request->session()->forget('pos.cart');
        $request->session()->forget('pos.tax_applicable');

        return back();
    })->middleware('can:pos_sales.create')->name('pos.cart.clear.legacy');

    Route::post('pos/session-legacy/suspend', function (Request $request, RetailSaleAction $action, PosContextResolver $contextResolver) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.create'), 403);
        $context = $contextResolver->resolve($user);
        if (! $context->isReady() || $context->store === null) {
            return back()->withErrors(['cart' => $context->disabledReason]);
        }
        $store = $context->store;
        /** @var array<int, array{product_id: int, quantity: numeric-string}> $cart */
        $cart = $request->session()->get('pos.cart', []);
        $token = (string) $request->session()->get('pos.checkout_token', Str::uuid());
        $sale = $action->create(
            $user,
            $store,
            $cart,
            'SUSPEND:'.$user->id.':'.$token,
            true,
            [],
            ['tax_applicable' => (bool) $request->session()->get('pos.tax_applicable', false)],
            $request->session()->get('pos.customer_id') ? Customer::query()->visibleFrom($user, (int) $store->branch_id, (int) $store->id)->where('status', 'active')->findOrFail((int) $request->session()->get('pos.customer_id')) : null,
        );
        $request->session()->forget('pos.cart');
        $request->session()->forget('pos.checkout_token');
        $request->session()->forget('pos.tax_applicable');
        $request->session()->forget('pos.customer_id');

        return redirect()->route('pos')->with('success', __('Sale suspended. Resume code: :code', ['code' => $sale->suspendedSale?->getAttribute('resume_code')]));
    })->middleware('can:pos_sales.create')->name('pos.suspend.legacy');

    Route::post('pos/session-legacy/checkout', function (Request $request, RetailSaleAction $action, PosContextResolver $contextResolver) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.create'), 403);
        $context = $contextResolver->resolve($user);
        if (! $context->isReady() || $context->store === null) {
            return back()->withInput()->withErrors(['payments' => $context->disabledReason]);
        }
        $store = $context->store;
        /** @var array<int, array{product_id: int, quantity: numeric-string}> $cart */
        $cart = $request->session()->get('pos.cart', []);

        $validated = $request->validate([
            'checkout_token' => ['required', 'uuid'],
            'tax_applicable' => ['nullable', 'boolean'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'payments.*.amount' => ['nullable', 'numeric', 'gte:0'],
            'payments.*.tendered' => ['nullable', 'numeric', 'gte:0'],
            'payments.*.evidence_reference' => ['nullable', 'string', 'max:190'],
            'payments.*.evidence' => ['nullable', 'file'],
            'payments.*.gift_card_identifier' => ['nullable', 'string', 'max:100'],
        ]);

        $customer = null;
        $customerId = $request->session()->get('pos.customer_id');
        if ($customerId !== null) {
            $customer = Customer::query()->visibleFrom($user, (int) $store->branch_id, (int) $store->id)->where('status', 'active')->findOrFail((int) $customerId);
        }

        $tenders = [];
        foreach ($validated['payments'] as $index => $payment) {
            if (empty($payment['method_id'])) {
                continue;
            }

            $attachmentId = null;
            if ($request->hasFile("payments.{$index}.evidence")) {
                abort_unless($user->can('pos_sales.payment_evidence_upload'), 403);
                $attachment = app(StoreAttachment::class)->execute(
                    $request->file("payments.{$index}.evidence"),
                    'payment_evidence',
                    new AttachmentSourceReference(
                        branchId: (int) $store->branch_id,
                        storeId: (int) $store->id,
                        visibility: 'private',
                    ),
                );
                $attachmentId = $attachment->id;
            }

            $method = PaymentMethod::query()->findOrFail($payment['method_id']);
            $giftCard = null;
            if ((string) $method->type === 'gift_card') {
                abort_unless($user->can('gift_cards.redeem'), 403);
                $identifier = trim((string) ($payment['gift_card_identifier'] ?? ''));
                $giftCard = GiftCard::query()
                    ->visibleTo($user)
                    ->where('identifier', $identifier)
                    ->where('branch_id', $store->branch_id)
                    ->where('store_id', $store->id)
                    ->whereIn('status', ['active', 'partially_used'])
                    ->firstOrFail();
            }

            $tenders[] = [
                'method' => $method,
                'amount' => isset($payment['amount']) ? (string) $payment['amount'] : '0.00',
                'tendered' => isset($payment['tendered']) ? (string) $payment['tendered'] : null,
                'evidence_reference' => $payment['evidence_reference'] ?? null,
                'evidence_attachment_id' => $attachmentId,
                'gift_card' => $giftCard,
            ];
        }
        if ($tenders === []) {
            return back()->withInput()->withErrors(['payments' => __('Select at least one payment method.')]);
        }

        try {
            $sale = $action->create(
                $user,
                $store,
                $cart,
                'CHECKOUT:'.$user->id.':'.$validated['checkout_token'],
                false,
                $tenders,
                ['tax_applicable' => (bool) ($validated['tax_applicable'] ?? false)],
                $customer,
            );
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withInput()->withErrors(['payments' => \App\Support\UserSafeError::message($e)]);
        }

        $request->session()->forget('pos.cart');
        $request->session()->forget('pos.checkout_token');
        $request->session()->forget('pos.tax_applicable');
        $request->session()->forget('pos.customer_id');

        return redirect()->route('sales.show', $sale)->with('success', __('Sale completed successfully.'));
    })->middleware('can:pos_sales.create')->name('pos.checkout.legacy');

    Route::get('pos/suspended', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('suspended_sales.view'), 403);
        $sales = Sale::query()->visibleTo($user)->with(['lines', 'suspendedSale', 'store'])->where('status', 'suspended')->latest()->paginate(20);

        return view('pages.pos.suspended', compact('sales'));
    })->middleware('can:suspended_sales.view')->name('pos.suspended');

    Route::get('pos/suspended/{sale}/resume', function (Request $request, Sale $sale, RetailSaleAction $action) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.create'), 403);
        abort_unless(Sale::query()->visibleTo($user)->whereKey($sale->id)->exists(), 404);

        try {
            $preview = $action->suspendedResumePreview($user, $sale);
        } catch (InvalidArgumentException $exception) {
            return redirect()->route('pos.suspended')->withErrors(['resume' => \App\Support\UserSafeError::message($exception)]);
        }

        $paymentMethods = PaymentMethod::query()->where('status', 'active')->orderBy('code')->get();
        $cashMethod = $paymentMethods->first(fn (PaymentMethod $method): bool => $method->isCash());
        $electronicMethods = $paymentMethods->reject(fn (PaymentMethod $method): bool => $method->isCash())->values();
        $resumeTokenKey = 'pos.suspended.resume_token.'.$sale->id;
        $resumeToken = (string) $request->session()->get($resumeTokenKey, '');
        if ($resumeToken === '') {
            $resumeToken = (string) Str::uuid();
            $request->session()->put($resumeTokenKey, $resumeToken);
        }
        $cashRoundingConfigurationError = null;
        try {
            $cashDenomination = app(PosCalculationService::class)->cashRoundingDenomination();
        } catch (PosFinancialConfigurationException $exception) {
            $cashDenomination = null;
            $cashRoundingConfigurationError = \App\Support\UserSafeError::message($exception);
        }

        return view('pages.pos.resume', compact('sale', 'preview', 'cashMethod', 'electronicMethods', 'resumeToken', 'cashDenomination', 'cashRoundingConfigurationError'));
    })->middleware('can:pos_sales.create')->name('pos.suspended.resume');

    Route::post('pos/suspended/{sale}/resume', function (Request $request, Sale $sale, RetailSaleAction $action) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.create'), 403);
        abort_unless(Sale::query()->visibleTo($user)->whereKey($sale->id)->exists(), 404);

        $validated = $request->validate([
            'resume_token' => ['required', 'uuid'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'payments.*.amount' => ['nullable', 'numeric', 'gte:0'],
            'payments.*.tendered' => ['nullable', 'numeric', 'gte:0'],
            'payments.*.evidence_reference' => ['nullable', 'string', 'max:190'],
            'payments.*.evidence' => ['nullable', 'file'],
            'payments.*.gift_card_identifier' => ['nullable', 'string', 'max:100'],
        ]);
        $resumeTokenKey = 'pos.suspended.resume_token.'.$sale->id;
        $expectedToken = (string) $request->session()->get($resumeTokenKey, '');
        abort_unless($expectedToken !== '' && hash_equals($expectedToken, (string) $validated['resume_token']), 419);

        $tenders = [];
        foreach ($validated['payments'] as $index => $payment) {
            if (empty($payment['method_id'])) {
                continue;
            }

            $attachmentId = null;
            if ($request->hasFile("payments.{$index}.evidence")) {
                abort_unless($user->can('pos_sales.payment_evidence_upload'), 403);
                $attachment = app(StoreAttachment::class)->execute(
                    $request->file("payments.{$index}.evidence"),
                    'payment_evidence',
                    new AttachmentSourceReference(
                        branchId: (int) $sale->branch_id,
                        storeId: (int) $sale->store_id,
                        visibility: 'private',
                    ),
                );
                $attachmentId = $attachment->id;
            }

            $method = PaymentMethod::query()->findOrFail($payment['method_id']);
            $giftCard = null;
            if ((string) $method->type === 'gift_card') {
                abort_unless($user->can('gift_cards.redeem'), 403);
                $identifier = trim((string) ($payment['gift_card_identifier'] ?? ''));
                $giftCard = GiftCard::query()
                    ->visibleTo($user)
                    ->where('identifier', $identifier)
                    ->where('branch_id', $sale->branch_id)
                    ->where('store_id', $sale->store_id)
                    ->whereIn('status', ['active', 'partially_used'])
                    ->firstOrFail();
            }

            $tenders[] = [
                'method' => $method,
                'amount' => isset($payment['amount']) ? (string) $payment['amount'] : '0.00',
                'tendered' => isset($payment['tendered']) ? (string) $payment['tendered'] : null,
                'evidence_reference' => $payment['evidence_reference'] ?? null,
                'evidence_attachment_id' => $attachmentId,
                'gift_card' => $giftCard,
            ];
        }
        if ($tenders === []) {
            return back()->withErrors(['payments' => __('Select at least one payment method.')]);
        }

        try {
            $sale = $action->finalizeSuspended($user, $sale, $tenders);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return back()->withInput()->withErrors(['payments' => \App\Support\UserSafeError::message($exception)]);
        }

        $request->session()->forget($resumeTokenKey);

        return redirect()->route('sales.show', $sale)->with('success', __('Suspended sale resumed and completed.'));
    })->middleware('can:pos_sales.create')->name('pos.suspended.finalize');

    Route::get('sales', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.view'), 403);
        if (! $request->filled('store_id') && ($contextStoreId = app(\App\Modules\Platform\Support\WorkContext::class)->id($user)) !== null) { $request->merge(['store_id' => $contextStoreId]); }
        $perPage = in_array($request->integer('per_page'), [20, 50, 100], true) ? $request->integer('per_page') : 20;
        $sort = in_array((string) $request->string('sort'), ['created_at', 'document_number', 'status', 'total'], true) ? (string) $request->string('sort') : 'created_at';
        $direction = (string) $request->string('direction') === 'asc' ? 'asc' : 'desc';
        $query = Sale::query()->visibleTo($user)->with(['store', 'cashier'])->withCount(['lines', 'payments']);
        $query->when($request->filled('q'), function ($query) use ($request): void {
            $term = trim((string) $request->string('q'));
            $query->where(function ($scope) use ($term): void {
                $scope->where('document_number', 'like', "%{$term}%")
                    ->orWhere('idempotency_key', 'like', "%{$term}%");
            });
        });
        $query->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')));
        $query->when($request->filled('store_id'), fn ($query) => $query->where('store_id', $request->integer('store_id')));
        $query->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->string('date_from')));
        $query->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->string('date_to')));
        $sales = $query->orderBy($sort, $direction)->orderBy('id', $direction)->paginate($perPage)->withQueryString();
        $stores = Store::query()->visibleTo($user)->orderBy('code')->get();
        $todaySales = Sale::query()->visibleTo($user)->approved()->whereDate('approved_at', today())
            ->when($request->filled('store_id'), fn ($builder) => $builder->where('store_id', $request->integer('store_id')));
        $sevenDayStart = today()->subDays(6);
        $trend = Sale::query()->visibleTo($user)->approved()->whereDate('approved_at', '>=', $sevenDayStart)
            ->when($request->filled('store_id'), fn ($builder) => $builder->where('store_id', $request->integer('store_id')))
            ->selectRaw('DATE(approved_at) as sale_day, COUNT(*) as invoice_count, SUM(total) as sales_total')
            ->groupByRaw('DATE(approved_at)')->orderBy('sale_day')->get()->keyBy('sale_day');
        $trendDays = collect(range(0, 6))->map(function (int $offset) use ($sevenDayStart, $trend): array {
            $date = $sevenDayStart->copy()->addDays($offset);
            $row = $trend->get($date->toDateString());
            return ['date' => $date, 'count' => (int) ($row?->invoice_count ?? 0), 'total' => (string) ($row?->sales_total ?? '0.00')];
        });
        $paymentSummary = SalePayment::query()->whereHas('sale', fn ($builder) => $builder->visibleTo($user)->approved()->whereDate('approved_at', today())
            ->when($request->filled('store_id'), fn ($scope) => $scope->where('store_id', $request->integer('store_id'))))
            ->selectRaw('method_code, SUM(amount) as collected_total')->groupBy('method_code')->orderByDesc('collected_total')->limit(6)->get();
        $topProducts = SaleLine::query()->whereHas('sale', fn ($builder) => $builder->visibleTo($user)->approved()->whereDate('approved_at', '>=', $sevenDayStart)
            ->when($request->filled('store_id'), fn ($scope) => $scope->where('store_id', $request->integer('store_id'))))
            ->selectRaw('product_id, item_code, name_ar, name_en, SUM(quantity) as units, SUM(net_amount) as net_total')
            ->groupBy('product_id', 'item_code', 'name_ar', 'name_en')->orderByDesc('units')->limit(5)->get();
        $dashboard = [
            'today_total' => (string) (clone $todaySales)->sum('total'),
            'today_count' => (clone $todaySales)->count(),
            'average_ticket' => (string) ((clone $todaySales)->avg('total') ?? '0.00'),
            'discount_total' => (string) (clone $todaySales)->sum('discount_total'),
            'returns_count' => RetailReturn::query()->visibleTo($user)->whereDate('created_at', today())->count(),
            'currency_code' => (string) ((clone $todaySales)->value('currency_code') ?: 'EGP'),
            'open_shifts' => PosShift::query()->visibleTo($user)->active()->count(),
            'suspended_count' => Sale::query()->visibleTo($user)->where('status', 'suspended')->count(),
            'trend' => $trendDays,
            'payment_summary' => $paymentSummary,
            'top_products' => $topProducts,
        ];

        return view('pages.sales.index', compact('sales', 'stores', 'dashboard', 'sort', 'direction'));
    })->middleware('can:pos_sales.view')->name('sales.index');

    Route::get('sales/invoices', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.view'), 403);
        if (! $request->filled('store_id') && ($contextStoreId = app(\App\Modules\Platform\Support\WorkContext::class)->id($user)) !== null) { $request->merge(['store_id' => $contextStoreId]); }
        $perPage = in_array($request->integer('per_page'), [20, 50, 100], true) ? $request->integer('per_page') : 20;
        $query = Sale::query()->visibleTo($user)->approved()->with(['store', 'cashier'])->withCount('payments');
        $query->when($request->filled('q'), fn ($query) => $query->where('document_number', 'like', '%'.trim((string) $request->string('q')).'%'));
        $query->when($request->filled('store_id'), fn ($query) => $query->where('store_id', $request->integer('store_id')));
        $query->when($request->filled('date_from'), fn ($query) => $query->whereDate('approved_at', '>=', $request->string('date_from')));
        $query->when($request->filled('date_to'), fn ($query) => $query->whereDate('approved_at', '<=', $request->string('date_to')));
        $sales = $query->latest('approved_at')->latest('id')->paginate($perPage)->withQueryString();
        $stores = Store::query()->visibleTo($user)->orderBy('code')->get();

        return view('pages.sales.invoices', compact('sales', 'stores'));
    })->middleware('can:pos_sales.view')->name('sales.invoices');

    Route::get('payments', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.payment_view'), 403);
        $query = SalePayment::query()
            ->whereHas('sale', fn ($saleQuery) => $saleQuery->visibleTo($user)->approved())
            ->with(['sale.store', 'sale.cashier', 'paymentMethod', 'creator', 'evidenceAttachment']);
        $query->when($request->filled('q'), fn ($query) => $query->whereHas('sale', fn ($saleQuery) => $saleQuery->where('document_number', 'like', '%'.trim((string) $request->string('q')).'%')));
        $query->when($request->filled('method_id'), fn ($query) => $query->where('payment_method_id', $request->integer('method_id')));
        $query->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->string('date_from')));
        $query->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->string('date_to')));
        $payments = $query->latest('id')->paginate(20)->withQueryString();
        $methods = PaymentMethod::query()->orderBy('code')->get();

        return view('pages.payments.index', compact('payments', 'methods'));
    })->middleware('can:pos_sales.payment_view')->name('payments.index');

    Route::get('payment-evidence', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.payment_evidence_view'), 403);
        $query = SalePayment::query()
            ->whereNotNull('evidence_attachment_id')
            ->whereHas('sale', fn ($saleQuery) => $saleQuery->visibleTo($user)->approved())
            ->with(['sale.store', 'paymentMethod', 'creator', 'evidenceAttachment']);
        $query->when($request->filled('q'), fn ($query) => $query->whereHas('sale', fn ($saleQuery) => $saleQuery->where('document_number', 'like', '%'.trim((string) $request->string('q')).'%')));
        $query->when($request->filled('method_id'), fn ($query) => $query->where('payment_method_id', $request->integer('method_id')));
        $evidencePayments = $query->latest('id')->paginate(20)->withQueryString();
        $methods = PaymentMethod::query()->where('requires_evidence', true)->orderBy('code')->get();

        return view('pages.payments.evidence', compact('evidencePayments', 'methods'));
    })->middleware('can:pos_sales.payment_evidence_view')->name('payments.evidence');

    Route::get('payment-evidence/{attachment}', function (Request $request, Attachment $attachment) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.payment_evidence_view'), 403);

        return app(DeliverAttachment::class)->execute(
            $attachment,
            static function (User $viewer, Attachment $candidate): bool {
                if (! $viewer->can('pos_sales.payment_evidence_view')
                    || $candidate->purpose !== 'payment_evidence'
                    || $candidate->source_type !== SalePayment::class) {
                    return false;
                }

                return SalePayment::query()
                    ->whereKey((int) $candidate->source_id)
                    ->where('evidence_attachment_id', $candidate->id)
                    ->whereHas('sale', fn ($saleQuery) => $saleQuery->visibleTo($viewer)->approved())
                    ->exists();
            },
        );
    })->middleware('can:pos_sales.payment_evidence_view')->name('payments.evidence.show');

    Route::get('sales/{sale}', function (Request $request, Sale $sale) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.view'), 403);
        abort_unless(Sale::query()->visibleTo($user)->whereKey($sale->id)->exists(), 404);
        $sale->load(['store', 'cashier', 'lines.product', 'shift.cashDrawer', 'payments.paymentMethod', 'payments.evidenceAttachment']);

        return view('pages.sales.show', compact('sale'));
    })->middleware('can:pos_sales.view')->name('sales.show');

    Route::get('sales/{sale}/print', function (Request $request, Sale $sale) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.print'), 403);
        abort_unless(Sale::query()->visibleTo($user)->whereKey($sale->id)->exists(), 404);
        abort_unless($sale->status === 'approved', 422);
        $sale->load(['store.company', 'branch', 'customer', 'cashier', 'lines', 'payments.paymentMethod']);
        app(RecordAuditEvent::class)->execute(
            category: 'retail', event: 'sale_a4_printed', source: $sale,
            branchId: (int) $sale->branch_id, storeId: (int) $sale->store_id,
            metadata: ['format' => 'a4', 'reprint' => true],
        );

        return view('pages.sales.print', compact('sale'));
    })->middleware('can:pos_sales.print')->name('sales.print');

    Route::get('sales/{sale}/receipt/thermal', function (Request $request, Sale $sale) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pos_sales.print'), 403);
        abort_unless(Sale::query()->visibleTo($user)->whereKey($sale->id)->where('status', 'approved')->exists(), 404);
        $sale->load(['store.company', 'branch', 'customer', 'cashier', 'lines', 'payments.paymentMethod']);
        app(RecordAuditEvent::class)->execute(
            category: 'retail', event: 'sale_thermal_receipt_printed', source: $sale,
            branchId: (int) $sale->branch_id, storeId: (int) $sale->store_id,
            metadata: ['format' => 'thermal', 'reprint' => true],
        );

        return view('pages.sales.thermal', compact('sale'));
    })->middleware('can:pos_sales.print')->name('sales.receipt.thermal');
});
