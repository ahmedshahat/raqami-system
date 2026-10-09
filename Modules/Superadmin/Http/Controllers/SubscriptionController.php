<?php

namespace Modules\Superadmin\Http\Controllers;

use App\Business;
use App\System;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Superadmin\Entities\Package;
use Modules\Superadmin\Entities\Subscription;
use Modules\Superadmin\Entities\SuperadminCoupon;
use Modules\Superadmin\Notifications\SubscriptionOfflinePaymentActivationConfirmation;
use Notification;
use Paystack;
use Pesapal;
use Razorpay\Api\Api;
use Srmklive\PayPal\Services\ExpressCheckout;
use Stripe\Charge;
use Stripe\Customer;
use Stripe\Stripe;
use Yajra\DataTables\Facades\DataTables;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use MyFatoorah\Library\API\Payment\MyFatoorahPaymentStatus;

class SubscriptionController extends BaseController
{
    protected $provider;

    public function __construct(ModuleUtil $moduleUtil = null)
    {
        if (! defined('CURL_SSLVERSION_TLSv1_2')) {
            define('CURL_SSLVERSION_TLSv1_2', 6);
        }

        if (! defined('CURLOPT_SSLVERSION')) {
            define('CURLOPT_SSLVERSION', 6);
        }

        $this->mfConfig = [
            'apiKey'      => config('myfatoorah.api_key'),
            'isTest'      => config('myfatoorah.test_mode'),
            'countryCode' => config('myfatoorah.country_iso'),
        ];

        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        if (! auth()->user()->can('superadmin.access_package_subscriptions')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        //Get active subscription and upcoming subscriptions.
        $active = Subscription::active_subscription($business_id);

        $nexts = Subscription::upcoming_subscriptions($business_id);
        $waiting = Subscription::waiting_approval($business_id);

        $packages = Package::active()->orderby('sort_order')->get();

        //Get all module permissions and convert them into name => label
        $permissions = $this->moduleUtil->getModuleData('superadmin_package');
        $permission_formatted = [];
        foreach ($permissions as $permission) {
            foreach ($permission as $details) {
                $permission_formatted[$details['name']] = $details['label'];
            }
        }

        $intervals = ['days' => __('lang_v1.days'), 'months' => __('lang_v1.months'), 'years' => __('lang_v1.years')];

        return view('superadmin::subscription.index')
            ->with(compact('packages', 'active', 'nexts', 'waiting', 'permission_formatted', 'intervals'));
    }

    /**
     * Show pay form for a new package.
     *
     * @return Response
     */
    public function pay(Request $request, $package_id, $form_register = null)
    {
        if (! auth()->user()->can('superadmin.access_package_subscriptions')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            DB::beginTransaction();

            $business_id = request()->session()->get('user.business_id');

            $package = Package::active()->find($package_id);

            //Check if superadmin only package
            if ($package->is_private == 1 && ! auth()->user()->can('superadmin')) {
                $output = ['success' => 0, 'msg' => __('superadmin::lang.not_allowed_for_package')];

                return redirect()
                        ->back()
                        ->with('status', $output);
            }

            //Check if one time only package
            if (empty($form_register) && $package->is_one_time) {
                $count_subcriptions = Subscription::where('business_id', $business_id)
                                                ->where('package_id', $package_id)
                                                ->count();

                if ($count_subcriptions > 0) {
                    $output = ['success' => 0, 'msg' => __('superadmin::lang.maximum_subscription_limit_exceed')];

                    return redirect()
                        ->back()
                        ->with('status', $output);
                }
            }

            //Check for free package & subscribe it.
            if ($package->price == 0) {
                $gateway = null;
                $payment_transaction_id = 'FREE';
                $user_id = request()->session()->get('user.id');

                $this->_add_subscription(null,0,$business_id, $package, $gateway, $payment_transaction_id, $user_id);

                DB::commit();

                if (empty($form_register)) {
                    $output = ['success' => 1, 'msg' => __('lang_v1.success')];

                    return redirect()
                        ->action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'index'])
                        ->with('status', $output);
                } else {
                    $output = ['success' => 1, 'msg' => __('superadmin::lang.registered_and_subscribed')];

                    return redirect()
                        ->action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'index'])
                        ->with('status', $output);
                }
            }

            $gateways = $this->_payment_gateways();

            $system_currency = System::getCurrency();

            DB::commit();

            if (empty($form_register)) {
                $layout = 'layouts.app';
            } else {
                $layout = 'layouts.auth';
            }

            $user = request()->session()->get('user');
            

            $offline_payment_details = System::getProperty('offline_payment_details');

             // coupon related code
             $coupon_status = ['status' => '', 'msg' => ""];
             $package_price_after_discount = 0;
             $discount_amount = 0;
            //  check code in request
            if ($request->has('code')) {
                $coupon = SuperadminCoupon::where('coupon_code', $request->code)->first();
                 // check coupon fount or not
                if($coupon){

                    $package_ids = json_decode($coupon->applied_on_packages);
                    $business_ids = json_decode($coupon->applied_on_business);
                    $current_date = Carbon::now()->toDateString();
                    // check all condition 
                    if(($coupon->is_active == 1) && ((is_array($package_ids) && in_array($package_id, $package_ids)) || is_null($coupon->applied_on_packages)) && ((is_array($business_ids) && in_array($business_id, $business_ids)) || is_null($coupon->applied_on_business)) &&  (Carbon::parse($coupon->expiry_date)->greaterThanOrEqualTo($current_date) || is_null($coupon->expiry_date))){
                        // check discount type and calculate amount after discount
                        if($coupon->discount_type == 'fixed'){
                            $discount_amount = $coupon->discount;
                            $package_price_after_discount = (float)$package->price - $coupon->discount; 
                        }elseif($coupon->discount_type == 'percentage'){

                            $discount_amount = $package->price * ($coupon->discount / 100);
                            $package_price_after_discount =  (float) $package->price - $discount_amount;
                        }

                        // after discount if package price <= 0
                        if($package_price_after_discount <= 0){
                            $gateway = null;
                            $payment_transaction_id = 'FREE';
                            $user_id = request()->session()->get('user.id');
                            
                            $this->_add_subscription($request->code,0, $business_id, $package_id, $gateway, $payment_transaction_id, $user_id);
            
                            return redirect()
                            ->action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'index'])
                            ->with('status', ['success' => 1, 'msg' => __('lang_v1.success')]);
                        }

                        $coupon_status = ['status' => 'success', 'msg' => "successfull"];

                    } else {
                            // check deactive
                            if($coupon->is_active == 0){
                                $coupon_status = ['status' => 'danger', 'msg' => __('superadmin::lang.coupon_is_deactive')];
                            }
                            // check coupon with this package
                           else if((is_array($package_ids) && !in_array($package_id, $package_ids)) && !is_null($coupon->applied_on_packages)){
                                
                                $coupon_status = ['status' => 'danger', 'msg' => __('superadmin::lang.coupon_not_matched_with_package')];
                            }
                            // check coupon with this business
                            else if((is_array($business_ids) && !in_array($business_id, $business_ids)) && !is_null($coupon->applied_on_business)){
                                
                                $coupon_status = ['status' => 'danger', 'msg' => __('superadmin::lang.coupon_not_matched_with_business')];
                            }
                            //  check expiry date
                           else if (Carbon::parse($current_date)->greaterThanOrEqualTo($coupon->expiry_date) && !is_null($coupon->expiry_date)) {

                                $coupon_status = ['status' => 'danger', 'msg' => __('superadmin::lang.coupon_expired')];
                            }
                    }
                } else {
                    $coupon_status = ['status' => 'danger', 'msg' => __('superadmin::lang.invalid_coupon')];
                }
               
            }
            return view('superadmin::subscription.pay')
            ->with(compact('package', 'gateways', 'system_currency', 'layout', 'user', 'offline_payment_details', 'coupon_status', 'package_price_after_discount', 'discount_amount'));
        } catch (\Exception $e) {
            DB::rollBack();

            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0, 'msg' => 'File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage()];


            return redirect()
                ->action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'index'])
                ->with('status', $output);
        }
    }

    /**
     * Show pay form for a new package.
     *
     * @return Response
     */
    public function registerPay($package_id, Request $request)
    {
        return $this->pay($request, $package_id, 1);
    }

    /**
     * Save the payment details and add subscription details
     * package_id becomes null for pesapal, and it's received from session
     * @return Response
     */
    public function confirm($package_id = null, Request $request)
    {
        if (! auth()->user()->can('superadmin.access_package_subscriptions')) {
            abort(403, 'Unauthorized action.');
        }

        try {

            //Disable in demo
            if (config('app.env') == 'demo') {
                $output = ['success' => 0,
                    'msg' => 'Feature disabled in demo!!',
                ];

                return back()->with('status', $output);
            }

            //Confirm for pesapal payment gateway
            if (isset($this->_payment_gateways()['pesapal']) && ! empty($request->merchant_reference) && (strpos($request->merchant_reference, 'PESAPAL') !== false)) {
                $package_id = request()->session()->get('pesapal.package_id');
                return $this->confirm_pesapal($package_id, $request);
            }

            DB::beginTransaction();

            $business_id = request()->session()->get('user.business_id');
            $business_name = request()->session()->get('business.name');
            $user_id = request()->session()->get('user.id');
            $package = Package::active()->find($package_id);

            if (empty($package)) {
                throw new \Exception('الباقة غير موجودة أو غير مفعلة');
            }

            //Call the payment method
            $pay_function = 'pay_'.request()->gateway;

            $payment_transaction_id = null;
            if (method_exists($this, $pay_function)) {
                $payment_transaction_id = $this->$pay_function($business_id, $business_name, $package, $request);
            } else {
                throw new \Exception('طريقة الدفع المختارة غير متاحة');
            }

            // رابط إيصال التحويل الذي تم حفظه داخل pay_offline
            $payment_receipt_path = $request->attributes->get('payment_receipt_path');

            //Add subscription details after payment is successful
            $this->_add_subscription(
                request()->coupon_code,
                request()->price,
                $business_id,
                $package_id,
                request()->gateway,
                $payment_transaction_id,
                $user_id,
                false,
                $payment_receipt_path
            );

            DB::commit();

            $msg = __('lang_v1.success');
            if (request()->gateway == 'offline') {
                $msg = __('superadmin::lang.notification_sent_for_approval');
            }
            $output = ['success' => 1, 'msg' => $msg];
        } catch (\Exception $e) {
            DB::rollBack();

            // حذف صورة الإيصال إذا تم رفعها ثم فشل تسجيل الاشتراك
            $payment_receipt_path = $request->attributes->get('payment_receipt_path');
            if (! empty($payment_receipt_path)) {
                $absolute_receipt_path = public_path($payment_receipt_path);
                if (is_file($absolute_receipt_path)) {
                    @unlink($absolute_receipt_path);
                }
            }

            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0, 'msg' => $e->getMessage()];
        }

        return redirect()
            ->action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'index'])
            ->with('status', $output);
    }

    /**
     * Confirm for pesapal gateway
     * when payment gateway is PesaPal payment gateway request package_id
     * is transaction_id & merchant_reference in session contains
     * the package_id.
     *
     * @return Response
     */
    protected function confirm_pesapal($transaction_id, $request)
    {
        $merchant_reference = $request->merchant_reference;
        $pesapal_session = $request->session()->pull('pesapal');

        if ($pesapal_session['ref'] == $merchant_reference) {
            $package_id = $pesapal_session['package_id'];

            $business_id = request()->session()->get('user.business_id');
            $business_name = request()->session()->get('business.name');
            $user_id = request()->session()->get('user.id');
            $package = Package::active()->find($package_id);

            $this->_add_subscription(null, 0, $business_id, $package, 'pesapal', $transaction_id, $user_id);
            $output = ['success' => 1, 'msg' => __('superadmin::lang.waiting_for_confirmation')];

            return redirect()
                ->action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'index'])
                ->with('status', $output);
        }
    }

    /**
     * Stripe payment method
     *
     * @return Response
     */
    protected function pay_stripe($business_id, $business_name, $package, $request)
    {
        Stripe::setApiKey(env('STRIPE_SECRET_KEY'));

        $metadata = ['business_id' => $business_id, 'business_name' => $business_name, 'stripe_email' => $request->stripeEmail, 'package_name' => $package->name];

        $customer = Customer::create([
            'name' => 'Stripe User',
            'email' => $request->stripeEmail,
            'source' => $request->stripeToken,
            'metadata' => $metadata,
            'description' => 'Stripe payment',
        ]);

        // "address" => ["city" => $city, "country" => $country, "line1" => $address, "line2" => "", "postal_code" => $zipCode, "state" => $state]

        $system_currency = System::getCurrency();

        $charge = Charge::create([
            'amount' => $request->price * 100,
            'currency' => strtolower($system_currency->code),
            //"source" => $request->stripeToken,
            'customer' => $customer,
            'metadata' => $metadata,
        ]);

        return $charge->id;
    }

    /**
     * Offline payment method
     *
     * @return Response
     */
    protected function pay_offline($business_id, $business_name, $package, $request)
    {

        //Disable in demo
        if (config('app.env') == 'demo') {
            throw new \Exception('Feature disabled in demo!!');
        }

        // التحقق من وجود صورة إشعار التحويل
        if (! $request->hasFile('payment_receipt')) {
            throw new \Exception('يرجى إرفاق صورة إشعار التحويل قبل إرسال الطلب');
        }

        $receipt_file = $request->file('payment_receipt');

        // التحقق من سلامة الملف
        if (! $receipt_file->isValid()) {
            throw new \Exception('تعذر رفع صورة إشعار التحويل، يرجى المحاولة مرة أخرى');
        }

        // السماح بالصور فقط وبحد أقصى 5 ميجابايت
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
        $receipt_extension = strtolower($receipt_file->getClientOriginalExtension());

        if (! in_array($receipt_extension, $allowed_extensions)) {
            throw new \Exception('صيغة الصورة غير مسموحة. الصيغ المتاحة: JPG, JPEG, PNG, WEBP');
        }

        if ($receipt_file->getSize() > 5 * 1024 * 1024) {
            throw new \Exception('حجم صورة التحويل يجب ألا يتجاوز 5 ميجابايت');
        }

        // حفظ صورة الإيصال داخل public/uploads/offline_payment_receipts
        try {
            $receipt_dir = public_path('uploads/offline_payment_receipts');

            if (! file_exists($receipt_dir)) {
                if (! mkdir($receipt_dir, 0755, true) && ! is_dir($receipt_dir)) {
                    throw new \Exception('تعذر إنشاء مجلد إيصالات التحويل');
                }
            }

            if (! is_writable($receipt_dir)) {
                throw new \Exception('مجلد إيصالات التحويل غير قابل للكتابة');
            }

            $receipt_filename = time().'_'.Str::random(16).'.'.$receipt_extension;
            $receipt_file->move($receipt_dir, $receipt_filename);

            // المسار النسبي الذي يتم حفظه مع الاشتراك
            $receipt_path = 'uploads/offline_payment_receipts/'.$receipt_filename;

            // تمرير المسار إلى دالة confirm ليتم حفظه في جدول subscriptions
            $request->attributes->set('payment_receipt_path', $receipt_path);
        } catch (\Exception $e) {
            \Log::error('Failed to store offline payment receipt. File:'.$e->getFile().' Line:'.$e->getLine().' Message:'.$e->getMessage());
            throw new \Exception('تعذر حفظ صورة التحويل، يرجى المحاولة مرة أخرى');
        }

        //Send notification
        $email = System::getProperty('email');
        $business = Business::find($business_id);

        if (! $this->moduleUtil->IsMailConfigured()) {
            return null;
        }

        $system_currency = System::getCurrency();
        $notification_package = clone $package;
        $notification_package->price = $system_currency->symbol.number_format(
            $package->price,
            2,
            $system_currency->decimal_separator,
            $system_currency->thousand_separator
        );

        // لو فشل البريد يظل طلب الاشتراك محفوظًا وينتظر الموافقة
        try {
            $notification = new SubscriptionOfflinePaymentActivationConfirmation($business, $notification_package);

            if (! empty($receipt_path) && property_exists($notification, 'receiptPath')) {
                $notification->receiptPath = public_path($receipt_path);
            }

            Notification::route('mail', $email)
                ->notify($notification);
        } catch (\Exception $e) {
            \Log::error('Offline payment notification failed. File:'.$e->getFile().' Line:'.$e->getLine().' Message:'.$e->getMessage());
        }

        return null;
    }


    /**
     * Paypal payment method - redirect to paypal url for payments
     *
     * @return Response
     */
    public function paypalExpressCheckout(Request $request)
    {
        $price = $request->input('price');
        $package_name = $request->input('package_name');

        $accessToken = $this->generatePaypalAccessToken();

        // check paypal mode 
        if(env('PAYPAL_MODE') == 'sandbox'){
            $url = config('paypal.baseURL.sandbox') . '/v2/checkout/orders';
        }else if(env('PAYPAL_MODE') == 'live'){
            $url = config('paypal.baseURL.production') . '/v2/checkout/orders';
        }
        $system_currency = System::getCurrency();
        $currency_code = $system_currency->code;


        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken,
        ])->post($url, [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'amount' => [
                        'currency_code' => $currency_code,
                        'value' => number_format($price,2),
                    ],
                    'description' => $package_name,
                ],
            ],
        ]);
        $data = $response->json();
        return $data;
    }

    public function capturePaypalOrder(Request $request){
            try {
                $orderId = $request->input('orderID');
                $accessToken = $this->generatePaypalAccessToken();
                // check paypal mode 
                if(env('PAYPAL_MODE') == 'sandbox'){
                    $url = config('paypal.baseURL.sandbox') . '/v2/checkout/orders/' . $orderId . '/capture';
                }else if(env('PAYPAL_MODE') == 'live'){
                    $url = config('paypal.baseURL.production') . '/v2/checkout/orders/' . $orderId . '/capture';
                }

                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $accessToken,
                ])->post($url,[
                    'intent' => 'CAPTURE',
                ]);
        
                $data = $response->json();
        
            if ($response->successful() && $data['status'] === 'COMPLETED') {
                $price = $data['purchase_units'][0]['payments']['captures'][0]['amount']['value'];
                $transaction_id = $data['purchase_units'][0]['payments']['captures'][0]['id'];
    
                $coupon_code = $request->input('coupon_code');
                $package_id = $request->input('package_id');
                $business_id = $request->input('business_id');
                $gateway = $request->input('gateway');
                $user_id = $request->input('user_id');
    
                if(isset($coupon_code)){
                    $coupon_code = $coupon_code;
                } else{
                    $coupon_code = null;  
                }
    
                $this->_add_subscription($coupon_code, $price, $business_id, $package_id, $gateway,$transaction_id, $user_id);
    
                $output = ['success' => true,
                    'msg' => __('lang_v1.success'),
                ];
                Session::flash('status', ['success' => 1, 'msg' => __('lang_v1.success')]);
            }

            } catch (\Exception $e) {
                DB::rollBack();
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }

    public function generatePaypalAccessToken(){
        // Construct the credentials
        $credentials = base64_encode(config('paypal.client_id'). ':' . config('paypal.app_secret'));

        // check paypal mode 
        if(env('PAYPAL_MODE') == 'sandbox'){
            $url = config('paypal.baseURL.sandbox') . '/v1/oauth2/token';
        }else if(env('PAYPAL_MODE') == 'live'){
            $url = config('paypal.baseURL.production') . '/v1/oauth2/token';
        }

        // Send the request to obtain the access token
        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . $credentials,
        ])
            ->asForm()
            ->post($url, [
                'grant_type' => 'client_credentials',
            ]);

        $data = $response->json();
        $accessToken = $data['access_token'];

        return $accessToken;
    }

    /**
     * Razor pay payment method
     *
     * @return Response
     */
    protected function pay_razorpay($business_id, $business_name, $package, $request)
    {
        $razorpay_payment_id = $request->razorpay_payment_id;
        $razorpay_api = new Api(env('RAZORPAY_KEY_ID'), env('RAZORPAY_KEY_SECRET'));

        $payment = $razorpay_api->payment->fetch($razorpay_payment_id)->capture(['amount' => $request->price * 100]); // Captures a payment

        if (empty($payment->error_code)) {
            return $payment->id;
        } else {
            $error_description = $payment->error_description;
            throw new \Exception($error_description);
        }
    }

    /**
     * Redirect the User to Paystack Payment Page
     *
     * @return Url
     */
    public function getRedirectToPaystack()
    {
        return Paystack::getAuthorizationUrl()->redirectNow();
    }

    /**
     * Obtain Paystack payment information
     *
     * @return void
     */
    public function postPaymentPaystackCallback()
    {
        $payment = Paystack::getPaymentData();
        $business_id = $payment['data']['metadata']['business_id'];
        $package_id = $payment['data']['metadata']['package_id'];
        $gateway = $payment['data']['metadata']['gateway'];
        $payment_transaction_id = $payment['data']['reference'];
        $user_id = $payment['data']['metadata']['user_id'];
        $price = $payment['data']['amount'] / 100;

        if(isset($payment['data']['metadata']['coupon_code'])){
            $coupon_code = $payment['data']['metadata']['coupon_code'];
        } else{
            $coupon_code = null; 
        }


        if ($payment['status']) {
            //Add subscription
            $this->_add_subscription($coupon_code, $price, $business_id, $package_id, $gateway, $payment_transaction_id, $user_id);

            return redirect()
                ->action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'index'])
                ->with('status', ['success' => 1, 'msg' => __('lang_v1.success')]);
        } else {
            return redirect()
                ->action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'pay'], [$package_id])
                ->with('status', ['success' => 0, 'msg' => __('messages.something_went_wrong')]);
        }
    }

    /**
     * Obtain Flutterwave payment information
     *
     * @return response
     */
    public function postFlutterwavePaymentCallback(Request $request)
    {
        $url = 'https://api.flutterwave.com/v3/transactions/'.$request->get('transaction_id').'/verify';
        $header = [
            'Content-Type: application/json',
            'Authorization: Bearer '.env('FLUTTERWAVE_SECRET_KEY'),
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => $header,
        ]);
        $response = curl_exec($curl);
        curl_close($curl);

        $payment = json_decode($response, true);


        if ($payment['status'] == 'success') {
            //Add subscription
            $business_id = $payment['data']['meta']['business_id'];
            $package_id = $payment['data']['meta']['package_id'];
            $gateway = $payment['data']['meta']['gateway'];
            $payment_transaction_id = $payment['data']['tx_ref'];
            $user_id = $payment['data']['meta']['user_id'];
            $price = $payment['data']['amount'];

            if(isset($payment['data']['meta']['coupon_code'])){
                $coupon_code = $payment['data']['meta']['coupon_code'];
            } else{
                $coupon_code = null;  
            }

           
            $this->_add_subscription($coupon_code,$price, $business_id, $package_id, $gateway, $payment_transaction_id, $user_id);

            return redirect()
                ->action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'index'])
                ->with('status', ['success' => 1, 'msg' => __('lang_v1.success')]);
        } else {
            $package_id = $payment['data']['meta']['package_id'];
            return redirect()
                ->action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'pay'], [$package_id])
                ->with('status', ['success' => 0, 'msg' => __('messages.something_went_wrong')]);
        }
    }

    /**
     * Show the specified resource.
     *
     * @return Response
     */
    public function show($id)
    {
        if (! auth()->user()->can('superadmin.access_package_subscriptions')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $subscription = Subscription::where('business_id', $business_id)
                                    ->with(['package', 'created_user', 'business'])
                                    ->find($id);

        $system_settings = System::getProperties([
            'invoice_business_name',
            'email',
            'invoice_business_landmark',
            'invoice_business_city',
            'invoice_business_zip',
            'invoice_business_state',
            'invoice_business_country',
        ]);
        $system = [];
        foreach ($system_settings as $setting) {
            $system[$setting['key']] = $setting['value'];
        }

        return view('superadmin::subscription.show_subscription_modal')
            ->with(compact('subscription', 'system'));
    }

     /**
     * Get MyFatoorah Payment Information
     * Provide the callback method with the paymentId
     */

    public function myfatoorahcallback() {
        try {
            $paymentId = request('paymentId');

            $mfObj = new MyFatoorahPaymentStatus($this->mfConfig);
            $data  = $mfObj->getPaymentStatus($paymentId, 'PaymentId');

           
            
            if ($data->InvoiceStatus == 'Paid') {
                //Add subscription
                $package_id = $data->CustomerReference;
                $payment_transaction_id = $data->InvoiceReference;
                $price = $data->InvoiceValue;

                $UserDefinedField = json_decode($data->UserDefinedField);

                $coupon_code = $UserDefinedField->coupon_code;
                $business_id = $UserDefinedField->business_id;
                $user_id = $UserDefinedField->user_id;
                
                $this->_add_subscription($coupon_code, $price, $business_id, $package_id, 'myfatoorsh', $payment_transaction_id, $user_id);

                return redirect()
                ->action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'index'])
                ->with('status', ['success' => 1, 'msg' => __('lang_v1.success')]);
                                
            }elseif($data->InvoiceStatus == 'Failed'){

                return redirect()
                ->action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'index'])
                ->with('status', ['success' => 0, 'msg' => $data->InvoiceError]);

            }else if($data->InvoiceStatus == 'Expired'){

                return redirect()
                ->action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'index'])
                ->with('status', ['success' => 0, 'msg' => $data->InvoiceError]);

            }


        } catch (Exception $ex) {
            $exMessage = __('myfatoorah.' . $ex->getMessage());
            $response  = ['success' => 'false', 'Message' => $exMessage];

            return redirect()
                ->action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'pay'], [$data->CustomerReference])
                ->with('status', $response);
        }
    }



    /**
     * Retrieves list of all subscriptions for the current business
     *
     * @return \Illuminate\Http\Response
     */
    public function allSubscriptions()
    {
        if (! auth()->user()->can('superadmin.access_package_subscriptions')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $subscriptions = Subscription::where('subscriptions.business_id', $business_id)
                        ->leftjoin(
                            'packages as P',
                            'subscriptions.package_id',
                            '=',
                            'P.id'
                        )
                        ->leftjoin(
                            'users as U',
                            'subscriptions.created_id',
                            '=',
                            'U.id'
                        )
                        ->addSelect(
                            'P.name as package_name',
                            DB::raw("CONCAT(COALESCE(U.surname, ''), ' ', COALESCE(U.first_name, ''), ' ', COALESCE(U.last_name, '')) as created_by"),
                            'subscriptions.*'
                        );

        return Datatables::of($subscriptions)
             ->editColumn(
                 'start_date',
                 '@if(!empty($start_date)){{@format_date($start_date)}}@endif'
             )
             ->editColumn(
                 'end_date',
                 '@if(!empty($end_date)){{@format_date($end_date)}}@endif'
             )
             ->editColumn(
                 'trial_end_date',
                 '@if(!empty($trial_end_date)){{@format_date($trial_end_date)}}@endif'
             )
             ->editColumn(
                 'package_price',
                 '<span class="display_currency" data-currency_symbol="true">{{$package_price}}</span>'
             )
             ->editColumn(
                 'created_at',
                 '@if(!empty($created_at)){{@format_date($created_at)}}@endif'
             )
             ->filterColumn('created_by', function ($query, $keyword) {
                 $query->whereRaw("CONCAT(COALESCE(U.surname, ''), ' ', COALESCE(U.first_name, ''), ' ', COALESCE(U.last_name, '')) like ?", ["%{$keyword}%"]);
             })
             ->addColumn('action', function ($row) {
                 return '<button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline  tw-dw-btn-primary btn-modal" data-container=".view_modal" data-href="'.action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'show'], $row->id).'" ><i class="fa fa-eye" aria-hidden="true"></i> '.__('messages.view').'</button>';
             })
             ->rawColumns(['package_price', 'action'])
             ->make(true);
    }

    public function forceActive($id){

        $current_date = \Carbon::today();


        $business_id = request()->session()->get('user.business_id');
     
        if (request()->ajax()) {
            try {
                //Get active subscription
                $active = Subscription::active_subscription($business_id);
                if($active){
                    $active->end_date = $current_date->subDays(1)->toDateString();
                    $active->update();
                }

                $subscription = Subscription::find($id);
                $package = Package::find($subscription->package_id);

                //Calculate end date
                $end_date = $this->calculate_end_date($package);
                $current_date = \Carbon::today();
                $subscription->start_date = $current_date->toDateString();
                $subscription->end_date = $end_date;
                $subscription->update();

                $output = ['success' => true,
                    'msg' => __('lang_v1.success'),
                ];

            } catch (\Exception $e) {
                DB::rollBack();
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }


    }

    public function calculate_end_date($package) {
       
        $start_date = \Carbon::today();
        if ($package->interval == 'days') {
            $end_date = $start_date->addDays($package->interval_count)->toDateString();
        } elseif ($package->interval == 'months') {
            $end_date = $start_date->addMonths($package->interval_count)->toDateString();
        } elseif ($package->interval == 'years') {
            $end_date = $start_date->addYears($package->interval_count)->toDateString();
        }

        return $end_date;
    }
    
    /**
     * تجهيز الدفع وتوجيه العميل لبوابة كاشير (الإصدار الثالث V3 - Payment Sessions)
     */
    public function kashierPay(Request $request, $package_id)
    {
        try {
            $business_id = $request->session()->get('user.business_id');
            $user = auth()->user(); 
            $package = \Modules\Superadmin\Entities\Package::find($package_id);
            
            if (!$package) {
                return back()->with('status', ['success' => 0, 'msg' => 'الباقة غير موجودة']);
            }

            $amount = $request->get('price', $package->price);
            $amount = number_format((float)$amount, 2, '.', ''); 
            $orderId = 'KSH_' . $business_id . '_' . time(); 
            
            $mid = env('KASHIER_MERCHANT_ID');
            $apiKey = env('KASHIER_API_KEY');       
            $secretKey = env('KASHIER_SECRET_KEY'); 
            $mode = strtolower(env('KASHIER_MODE', 'sandbox'));
            
            $callbackUrl = route('kashier.callback') . "?pkg_id={$package_id}&amt={$amount}"; 

            $apiUrl = ($mode == 'live' || $mode == 'production') 
                ? 'https://api.kashier.io/v3/payment/sessions' 
                : 'https://test-api.kashier.io/v3/payment/sessions';

            $customerEmail = $user ? $user->email : 'admin@raqmycloud.com';
            $customerRef = $user ? (string)$user->id : (string)$business_id;

            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => $secretKey,
                'api-key' => $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post($apiUrl, [
                'merchantId' => $mid,
                'order' => $orderId,
                'amount' => $amount,
                'currency' => 'EGP',
                'merchantRedirect' => $callbackUrl,
                'display' => 'ar', 
                'type' => 'one-time',
                'allowedMethods' => 'card,wallet', 
                'expireAt' => gmdate("Y-m-d\TH:i:s.000\Z", strtotime("+1 hour")),
                'customer' => [
                    'email' => $customerEmail,
                    'reference' => $customerRef
                ]
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                $sessionUrl = $responseData['sessionUrl'] ?? null;

                if ($sessionUrl) {
                    return redirect()->away($sessionUrl);
                }
            }

            return back()->with('status', ['success' => 0, 'msg' => 'Error: ' . $response->body()]);

        } catch (\Exception $e) {
            \Log::emergency("File:" . $e->getFile() . "Line:" . $e->getLine() . "Message:" . $e->getMessage());
            return back()->with('status', ['success' => 0, 'msg' => __('messages.something_went_wrong')]);
        }
    }

    /**
     * استلام الرد من كاشير بعد الدفع وتفعيل الباقة (الإصدار الثالث V3)
     */

    public function kashierCallback(Request $request)
    {
        try {
            $secretKey = env('KASHIER_SECRET_KEY'); 
            
            // قراءة المتغيرات بالأسماء الجديدة من كاشير V3
            $paymentStatus = $request->query('paymentStatus');
            $merchantOrderId = $request->query('merchantOrderId');
            $transactionId = $request->query('transactionId');
            $amount = $request->query('amount');
            $currency = $request->query('currency');
            $receivedSignature = $request->query('signature'); // هنا السر! (signature بدل hash)
            
            // المتغيرات الخاصة بنظامنا
            $package_id = $request->query('pkg_id');
            $originalAmount = $request->query('amt', $amount);

            // بناء مسار التشفير للمطابقة
            $path = "/?payment={$merchantOrderId}.{$amount}.{$currency}.{$transactionId}";
            $calculatedSignature = hash_hmac('sha256', $path, $secretKey);

            // التحقق من نجاح العملية (كاشير رجعت SUCCESS ومعاها رقم معاملة حقيقي)
            if ($paymentStatus === 'SUCCESS' && !empty($transactionId)) {
                
                $business_id = $request->session()->get('user.business_id');
                $user_id = $request->session()->get('user.id');
                
                // حماية خارقة: لو العميل متصفحه مسح الجلسة، هنجيب رقمه من رقم الطلب (KSH_1_178...)
                if (empty($business_id) && !empty($merchantOrderId)) {
                    $parts = explode('_', $merchantOrderId); 
                    if (isset($parts[1])) {
                        $business_id = $parts[1];
                        $user_id = 1; // تعيين لمدير النظام لتفادي أي أخطاء
                    }
                }
                // التحقق من أن رقم المعاملة لم يُستخدم من قبل
                $transactionExists = Subscription::where('payment_transaction_id', $transactionId)->exists();
                
                if ($transactionExists) {
                    return redirect()
                        ->action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'index'])
                        ->with('status', ['success' => 0, 'msg' => 'هذه العملية تم تفعيلها مسبقاً!']);
                }

                // تفعيل الباقة فوراً
                if ($business_id) {
                    $this->_add_subscription(null, $originalAmount, $business_id, $package_id, 'Kashier', $transactionId, $user_id);

                    return redirect()
                        ->action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'index'])
                        ->with('status', ['success' => 1, 'msg' => 'تم الدفع وتفعيل الاشتراك بنجاح!']);
                }
            }

            // لو الدفع مرفوض من البنك
            return redirect()
                ->action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'pay'], [$package_id])
                ->with('status', ['success' => 0, 'msg' => 'تم الدفع ولكن فشل توثيق العملية من النظام.']);

        } catch (\Exception $e) {
            \Log::emergency("File:" . $e->getFile() . "Line:" . $e->getLine() . "Message:" . $e->getMessage());
            return redirect()
                ->action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'pay'], [$request->query('pkg_id')])
                ->with('status', ['success' => 0, 'msg' => __('messages.something_went_wrong')]);
        }
    }
}
