<?php

use App\Models\Admin;
use App\Models\Order;
use App\Models\Store;
use App\Models\AdminWallet;
use App\Models\DeliveryMan;
use App\Models\WalletPayment;
use App\CentralLogics\Helpers;
use App\CentralLogics\OrderLogic;
use App\Models\AccountTransaction;
use Illuminate\Support\Facades\DB;
use App\Mail\OrderVerificationMail;
use Illuminate\Support\Facades\App;
use App\CentralLogics\CustomerLogic;
use Illuminate\Support\Facades\Mail;

if (! function_exists('translate')) {
    function translate($key, $replace = [])
    {
        if(strpos($key, 'validation.') === 0 || strpos($key, 'passwords.') === 0 || strpos($key, 'pagination.') === 0 || strpos($key, 'order_texts.') === 0) {
            return trans($key, $replace);
        }

        $key = strpos($key, 'messages.') === 0?substr($key,9):$key;
        $local = app()->getLocale();
        try {
            $lang_array = include(base_path('resources/lang/' . $local . '/messages.php'));
            $processed_key = ucfirst(str_replace('_', ' ', Helpers::remove_invalid_charcaters($key)));

            if (!array_key_exists($key, $lang_array)) {
                $lang_array[$key] = $processed_key;
                $str = "<?php return " . var_export($lang_array, true) . ";";
                file_put_contents(base_path('resources/lang/' . $local . '/messages.php'), $str);
                $result = $processed_key;
            } else {
                $result = trans('messages.' . $key, $replace);
            }
        } catch (\Exception $exception) {
            info($exception->getMessage());
            $result = trans('messages.' . $key, $replace);
        }

        return $result;
    }
}

if (! function_exists('collect_cash_fail')) {
    function collect_cash_fail($data){
        return 0;
    }
}
if (! function_exists('collect_cash_success')) {
    function collect_cash_success($data){

        try {
            $account_transaction = new AccountTransaction();
            if($data->attribute === 'store_collect_cash_payments'){
                $store = Store::where('vendor_id', $data->attribute_id)->first();
                $store->status = 1;
                $store->save();
                $user_data = $store?->vendor;
                $current_balance = $user_data?->wallet?->collected_cash ?? 0;
                $account_transaction->from_type = 'store';
                $account_transaction->from_id = $store?->vendor?->id;
                $account_transaction->created_by = 'store';
            }
            elseif($data->attribute === 'deliveryman_collect_cash_payments'){
                $user_data = DeliveryMan::findOrFail($data->attribute_id);
                $user_data->status = 1;
                $user_data->save();
                $current_balance = $user_data?->wallet?->collected_cash ?? 0;
                $account_transaction->from_type = 'deliveryman';
                $account_transaction->from_id = $user_data->id;
                $account_transaction->created_by = 'deliveryman';
            }
            else{
                return 0;
            }
            $account_transaction->method = $data->payment_method;
            $account_transaction->ref = $data->attribute;
            $account_transaction->amount = $data->payment_amount;
            $account_transaction->current_balance = $current_balance;

            DB::beginTransaction();
            $account_transaction->save();
            $user_data?->wallet?->decrement('collected_cash', $account_transaction->amount);
            AdminWallet::where('admin_id', Admin::where('role_id', 1)->first()->id)->increment('digital_received',  $account_transaction->amount );

            DB::commit();


        } catch (\Exception $exception) {
            info($exception->getMessage());
            DB::rollBack();

        }


        try {
            if($data->attribute == 'deliveryman_collect_cash_payments' && config('mail.status') && Helpers::get_mail_status('cash_collect_mail_status_dm') == 1 ){
                Mail::to($user_data['email'])->send(new \App\Mail\CollectCashMail($account_transaction,$user_data['f_name']));
            }
        } catch (\Exception $exception) {
            info($exception->getMessage());
        }
        return true;
    }
}

const TELEPHONE_CODES = [
    ["name" => 'UK (+44)', "code" => '+44'],
    ["name" => 'USA (+1)', "code" => '+1'],
    ["name" => 'Algeria (+213)', "code" => '+213'],
    ["name" => 'Andorra (+376)', "code" => '+376'],
    ["name" => 'Angola (+244)', "code" => '+244'],
    ["name" => 'Anguilla (+1264)', "code" => '+1264'],
    ["name" => 'Antigua & Barbuda (+1268)', "code" => '+1268'],
    ["name" => 'Argentina (+54)', "code" => '+54'],
    ["name" => 'Armenia (+374)', "code" => '+374'],
    ["name" => 'Aruba (+297)', "code" => '+297'],
    ["name" => 'Australia (+61)', "code" => '+61'],
    ["name" => 'Austria (+43)', "code" => '+43'],
    ["name" => 'Azerbaijan (+994)', "code" => '+994'],
    ["name" => 'Bahamas (+1242)', "code" => '+1242'],
    ["name" => 'Bahrain (+973)', "code" => '+973'],
    ["name" => 'Bangladesh (+880)', "code" => '+880'],
    ["name" => 'Barbados (+1246)', "code" => '+1246'],
    ["name" => 'Belarus (+375)', "code" => '+375'],
    ["name" => 'Belgium (+32)', "code" => '+32'],
    ["name" => 'Belize (+501)', "code" => '+501'],
    ["name" => 'Benin (+229)', "code" => '+229'],
    ["name" => 'Bermuda (+1441)', "code" => '+1441'],
    ["name" => 'Bhutan (+975)', "code" => '+975'],
    ["name" => 'Bolivia (+591)', "code" => '+591'],
    ["name" => 'Bosnia Herzegovina (+387)', "code" => '+387'],
    ["name" => 'Botswana (+267)', "code" => '+267'],
    ["name" => 'Brazil (+55)', "code" => '+55'],
    ["name" => 'Brunei (+673)', "code" => '+673'],
    ["name" => 'Bulgaria (+359)', "code" => '+359'],
    ["name" => 'Burkina Faso (+226)', "code" => '+226'],
    ["name" => 'Burundi (+257)', "code" => '+257'],
    ["name" => 'Cambodia (+855)', "code" => '+855'],
    ["name" => 'Cameroon (+237)', "code" => '+237'],
    ["name" => 'Canada (+1)', "code" => '+1'],
    ["name" => 'Cape Verde Islands (+238)', "code" => '+238'],
    ["name" => 'Cayman Islands (+1345)', "code" => '+1345'],
    ["name" => 'Central African Republic (+236)', "code" => '+236'],
    ["name" => 'Chile (+56)', "code" => '+56'],
    ["name" => 'China (+86)', "code" => '+86'],
    ["name" => 'Colombia (+57)', "code" => '+57'],
    ["name" => 'Comoros (+269)', "code" => '+269'],
    ["name" => 'Congo (+242)', "code" => '+242'],
    ["name" => 'Cook Islands (+682)', "code" => '+682'],
    ["name" => 'Costa Rica (+506)', "code" => '+506'],
    ["name" => 'Croatia (+385)', "code" => '+385'],
    ["name" => 'Cuba (+53)', "code" => '+53'],
    ["name" => 'Cyprus North (+90392)', "code" => '+90392'],
    ["name" => 'Cyprus South (+357)', "code" => '+357'],
    ["name" => 'Czech Republic (+42)', "code" => '+42'],
    ["name" => 'Denmark (+45)', "code" => '+45'],
    ["name" => 'Djibouti (+253)', "code" => '+253'],
    ["name" => 'Dominica (+1767)', "code" => '+1767'],
    ["name" => 'Dominican Republic (+1809)', "code" => '+1809'],
    ["name" => 'Ecuador (+593)', "code" => '+593'],
    ["name" => 'Egypt (+20)', "code" => '+20'],
    ["name" => 'El Salvador (+503)', "code" => '+503'],
    ["name" => 'Equatorial Guinea (+240)', "code" => '+240'],
    ["name" => 'Eritrea (+291)', "code" => '+291'],
    ["name" => 'Estonia (+372)', "code" => '+372'],
    ["name" => 'Ethiopia (+251)', "code" => '+251'],
    ["name" => 'Falkland Islands (+500)', "code" => '+500'],
    ["name" => 'Faroe Islands (+298)', "code" => '+298'],
    ["name" => 'Fiji (+679)', "code" => '+679'],
    ["name" => 'Finland (+358)', "code" => '+358'],
    ["name" => 'France (+33)', "code" => '+33'],
    ["name" => 'French Guiana (+594)', "code" => '+594'],
    ["name" => 'French Polynesia (+689)', "code" => '+689'],
    ["name" => 'Gabon (+241)', "code" => '+241'],
    ["name" => 'Gambia (+220)', "code" => '+220'],
    ["name" => 'Georgia (+7880)', "code" => '+7880'],
    ["name" => 'Germany (+49)', "code" => '+49'],
    ["name" => 'Ghana (+233)', "code" => '+233'],
    ["name" => 'Gibraltar (+350)', "code" => '+350'],
    ["name" => 'Greece (+30)', "code" => '+30'],
    ["name" => 'Greenland (+299)', "code" => '+299'],
    ["name" => 'Grenada (+1473)', "code" => '+1473'],
    ["name" => 'Guadeloupe (+590)', "code" => '+590'],
    ["name" => 'Guam (+671)', "code" => '+671'],
    ["name" => 'Guatemala (+502)', "code" => '+502'],
    ["name" => 'Guinea (+224)', "code" => '+224'],
    ["name" => 'Guinea - Bissau (+245)', "code" => '+245'],
    ["name" => 'Guyana (+592)', "code" => '+592'],
    ["name" => 'Haiti (+509)', "code" => '+509'],
    ["name" => 'Honduras (+504)', "code" => '+504'],
    ["name" => 'Hong Kong (+852)', "code" => '+852'],
    ["name" => 'Hungary (+36)', "code" => '+36'],
    ["name" => 'Iceland (+354)', "code" => '+354'],
    ["name" => 'India (+91)', "code" => '+91'],
    ["name" => 'Indonesia (+62)', "code" => '+62'],
    ["name" => 'Iran (+98)', "code" => '+98'],
    ["name" => 'Iraq (+964)', "code" => '+964'],
    ["name" => 'Ireland (+353)', "code" => '+353'],
    ["name" => 'Israel (+972)', "code" => '+972'],
    ["name" => 'Italy (+39)', "code" => '+39'],
    ["name" => 'Jamaica (+1876)', "code" => '+1876'],
    ["name" => 'Japan (+81)', "code" => '+81'],
    ["name" => 'Jordan (+962)', "code" => '+962'],
    ["name" => 'Kazakhstan (+7)', "code" => '+7'],
    ["name" => 'Kenya (+254)', "code" => '+254'],
    ["name" => 'Kiribati (+686)', "code" => '+686'],
    ["name" => 'Korea North (+850)', "code" => '+850'],
    ["name" => 'Korea South (+82)', "code" => '+82'],
    ["name" => 'Kuwait (+965)', "code" => '+965'],
    ["name" => 'Kyrgyzstan (+996)', "code" => '+996'],
    ["name" => 'Laos (+856)', "code" => '+856'],
    ["name" => 'Latvia (+371)', "code" => '+371'],
    ["name" => 'Lebanon (+961)', "code" => '+961'],
    ["name" => 'Lesotho (+266)', "code" => '+266'],
    ["name" => 'Liberia (+231)', "code" => '+231'],
    ["name" => 'Libya (+218)', "code" => '+218'],
    ["name" => 'Liechtenstein (+417)', "code" => '+417'],
    ["name" => 'Lithuania (+370)', "code" => '+370'],
    ["name" => 'Luxembourg (+352)', "code" => '+352'],
    ["name" => 'Macao (+853)', "code" => '+853'],
    ["name" => 'Macedonia (+389)', "code" => '+389'],
    ["name" => 'Madagascar (+261)', "code" => '+261'],
    ["name" => 'Malawi (+265)', "code" => '+265'],
    ["name" => 'Malaysia (+60)', "code" => '+60'],
    ["name" => 'Maldives (+960)', "code" => '+960'],
    ["name" => 'Mali (+223)', "code" => '+223'],
    ["name" => 'Malta (+356)', "code" => '+356'],
    ["name" => 'Marshall Islands (+692)', "code" => '+692'],
    ["name" => 'Martinique (+596)', "code" => '+596'],
    ["name" => 'Mauritania (+222)', "code" => '+222'],
    ["name" => 'Mayotte (+269)', "code" => '+269'],
    ["name" => 'Mexico (+52)', "code" => '+52'],
    ["name" => 'Micronesia (+691)', "code" => '+691'],
    ["name" => 'Moldova (+373)', "code" => '+373'],
    ["name" => 'Monaco (+377)', "code" => '+377'],
    ["name" => 'Montserrat (+1664)', "code" => '+1664'],
    ["name" => 'Morocco (+212)', "code" => '+212'],
    ["name" => 'Mozambique (+258)', "code" => '+258'],
    ["name" => 'Myanmar (+95)', "code" => '+95'],
    ["name" => 'Namibia (+264)', "code" => '+264'],
    ["name" => 'Nauru (+674)', "code" => '+674'],
    ["name" => 'Nepal (+977)', "code" => '+977'],
    ["name" => 'Netherlands (+31)', "code" => '+31'],
    ["name" => 'New Caledonia (+687)', "code" => '+687'],
    ["name" => 'New Zealand (+64)', "code" => '+64'],
    ["name" => 'Nicaragua (+505)', "code" => '+505'],
    ["name" => 'Niger (+227)', "code" => '+227'],
    ["name" => 'Nigeria (+234)', "code" => '+234'],
    ["name" => 'Niue (+683)', "code" => '+683'],
    ["name" => 'Norfolk Islands (+672)', "code" => '+672'],
    ["name" => 'Northern Marianas (+670)', "code" => '+670'],
    ["name" => 'Norway (+47)', "code" => '+47'],
    ["name" => 'Oman (+968)', "code" => '+968'],
    ["name" => 'Palau (+680)', "code" => '+680'],
    ["name" => 'Panama (+507)', "code" => '+507'],
    ["name" => 'Papua New Guinea (+675)', "code" => '+675'],
    ["name" => 'Paraguay (+595)', "code" => '+595'],
    ["name" => 'Peru (+51)', "code" => '+51'],
    ["name" => 'Philippines (+63)', "code" => '+63'],
    ["name" => 'Poland (+48)', "code" => '+48'],
    ["name" => 'Portugal (+351)', "code" => '+351'],
    ["name" => 'Qatar (+974)', "code" => '+974'],
    ["name" => 'Reunion (+262)', "code" => '+262'],
    ["name" => 'Romania (+40)', "code" => '+40'],
    ["name" => 'Russia (+7)', "code" => '+7'],
    ["name" => 'Rwanda (+250)', "code" => '+250'],
    ["name" => 'San Marino (+378)', "code" => '+378'],
    ["name" => 'Sao Tome & Principe (+239)', "code" => '+239'],
    ["name" => 'Saudi Arabia (+966)', "code" => '+966'],
    ["name" => 'Senegal (+221)', "code" => '+221'],
    ["name" => 'Serbia (+381)', "code" => '+381'],
    ["name" => 'Seychelles (+248)', "code" => '+248'],
    ["name" => 'Sierra Leone (+232)', "code" => '+232'],
    ["name" => 'Singapore (+65)', "code" => '+65'],
    ["name" => 'Slovak Republic (+421)', "code" => '+421'],
    ["name" => 'Slovenia (+386)', "code" => '+386'],
    ["name" => 'Solomon Islands (+677)', "code" => '+677'],
    ["name" => 'Somalia (+252)', "code" => '+252'],
    ["name" => 'South Africa (+27)', "code" => '+27'],
    ["name" => 'Spain (+34)', "code" => '+34'],
    ["name" => 'Sri Lanka (+94)', "code" => '+94'],
    ["name" => 'St. Helena (+290)', "code" => '+290'],
    ["name" => 'St. Kitts (+1869)', "code" => '+1869'],
    ["name" => 'St. Lucia (+1758)', "code" => '+1758'],
    ["name" => 'Sudan (+249)', "code" => '+249'],
    ["name" => 'Suriname (+597)', "code" => '+597'],
    ["name" => 'Swaziland (+268)', "code" => '+268'],
    ["name" => 'Sweden (+46)', "code" => '+46'],
    ["name" => 'Switzerland (+41)', "code" => '+41'],
    ["name" => 'Syria (+963)', "code" => '+963'],
    ["name" => 'Taiwan (+886)', "code" => '+886'],
    ["name" => 'Tajikstan (+7)', "code" => '+7'],
    ["name" => 'Thailand (+66)', "code" => '+66'],
    ["name" => 'Togo (+228)', "code" => '+228'],
    ["name" => 'Tonga (+676)', "code" => '+676'],
    ["name" => 'Trinidad & Tobago (+1868)', "code" => '+1868'],
    ["name" => 'Tunisia (+216)', "code" => '+216'],
    ["name" => 'Turkey (+90)', "code" => '+90'],
    ["name" => 'Turkmenistan (+7)', "code" => '+7'],
    ["name" => 'Turkmenistan (+993)', "code" => '+993'],
    ["name" => 'Turks & Caicos Islands (+1649)', "code" => '+1649'],
    ["name" => 'Tuvalu (+688)', "code" => '+688'],
    ["name" => 'Uganda (+256)', "code" => '+256'],
    ["name" => 'Ukraine (+380)', "code" => '+380'],
    ["name" => 'United Arab Emirates (+971)', "code" => '+971'],
    ["name" => 'Uruguay (+598)', "code" => '+598'],
    ["name" => 'Uzbekistan (+7)', "code" => '+7'],
    ["name" => 'Vanuatu (+678)', "code" => '+678'],
    ["name" => 'Vatican City (+379)', "code" => '+379'],
    ["name" => 'Venezuela (+58)', "code" => '+58'],
    ["name" => 'Vietnam (+84)', "code" => '+84'],
    ["name" => 'Virgin Islands - British (+1284)', "code" => '+1284'],
    ["name" => 'Virgin Islands - US (+1340)', "code" => '+1340'],
    ["name" => 'Wallis & Futuna (+681)', "code" => '+681'],
    ["name" => 'Yemen (North)(+969)', "code" => '+969'],
    ["name" => 'Yemen (South)(+967)', "code" => '+967'],
    ["name" => 'Zambia (+260)', "code" => '+260'],
    ["name" => 'Zimbabwe (+263)', "code" => '+263'],
];

function order_place($data) {
    $order = Order::find($data->attribute_id);
    $order->order_status='confirmed';
    if($order->payment_method != 'partial_payment'){
        $order->payment_method=$data->payment_method;
    }
    // $order->transaction_reference=$data->transaction_ref;
    $order->payment_status='paid';
    $order->confirmed=now();
    $order->save();
    OrderLogic::update_unpaid_order_payment(order_id:$order->id, payment_method:$data->payment_method);
    try {
        Helpers::send_order_notification($order);
        $address = json_decode($order->delivery_address, true);

        $order_verification_mail_status = Helpers::get_mail_status('order_verification_mail_status_user');
        if ( config('order_delivery_verification') == 1 && $order_verification_mail_status == '1' && $order->is_guest == 0) {
            Mail::to($order->customer->email)->send(new OrderVerificationMail($order->otp,$order->customer->f_name));
        }

        if ($order->is_guest == 1 && config('mail.status') && $order_verification_mail_status == '1' && isset($address['contact_person_email'])) {
            Mail::to($address['contact_person_email'])->send(new OrderVerificationMail($order->otp,$order->customer->f_name));
        }
    } catch (\Exception $e) {
        info($e);
    }

}

function order_failed($data) {
    $order = Order::find($data->attribute_id);
    $order->order_status='failed';
    if($order->payment_method != 'partial_payment'){
        $order->payment_method=$data->payment_method;
    }
    $order->failed=now();
    $order->save();
}

function wallet_success($data) {
    $order = WalletPayment::find($data->attribute_id);
    $order->payment_method=$data->payment_method;
    // $order->transaction_reference=$data->transaction_ref;
    $order->payment_status='success';
    $order->save();
    $wallet_transaction = CustomerLogic::create_wallet_transaction($data->payer_id, $data->payment_amount, 'add_fund',$data->payment_method);
    if($wallet_transaction)
    {
        $mail_status = Helpers::get_mail_status('add_fund_mail_status_user');
        try{
            if(config('mail.status') && $mail_status == '1') {
                Mail::to($wallet_transaction->user->email)->send(new \App\Mail\AddFundToWallet($wallet_transaction));
            }
        }catch(\Exception $ex)
        {
            info($ex->getMessage());
        }
    }
}

function wallet_failed($data) {
    $order = WalletPayment::find($data->attribute_id);
    $order->payment_status='failed';
    $order->payment_method=$data->payment_method;
    $order->save();
}

if (!function_exists('addon_published_status')) {
    function addon_published_status($module_name)
    {
        $is_published = 0;
        try {
            $full_data = include("Modules/{$module_name}/Addon/info.php");
            $is_published = $full_data['is_published'] == 1 ? 1 : 0;
            return $is_published;
        } catch (\Exception $exception) {
            return 0;
        }
    }
}

if (!function_exists('image_asset')) {
    function image_asset($path)
    {
        $cdnUrl = env('IMAGE_ASSET_URL'); // Retrieve from environment variable
        return $cdnUrl ? rtrim($cdnUrl, '/') . '/' . ltrim($path, '/') : asset($path); // Fallback to asset helper
    }
}

if (!function_exists('config_settings')) {
    function config_settings($key, $settings_type)
    {
        try {
            $config = DB::table('addon_settings')->where('key_name', $key)
                ->where('settings_type', $settings_type)->first();
        } catch (Exception $exception) {
            return null;
        }
        return (isset($config)) ? $config : null;
    }
}
