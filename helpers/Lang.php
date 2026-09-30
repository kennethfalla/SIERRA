<?php
// helpers/Lang.php - Lightweight English / Filipino UI translation helper.
//
// The language preference is stored in a cookie ("lang") so it applies
// across the whole application.
//
// Two complementary translation layers:
//   1. Server side: echo t('Home');  ->  renders the Filipino text directly.
//   2. Client side: echo lang_apply_js();  ->  scans the rendered page and
//      translates every remaining hard-coded English UI string so that the
//      entire system responds to the language toggle.
//
// Pages that render a language toggle: landing page navbar and Profile page.

function app_lang(): string
{
    static $lang = null;
    if ($lang === null) {
        $lang = (isset($_COOKIE['lang']) && in_array($_COOKIE['lang'], ['en', 'fil'], true))
            ? $_COOKIE['lang']
            : 'en';
    }
    return $lang;
}

// Central English -> Filipino dictionary. Keys are lowercase (trimmed) so the
// same phrase always resolves regardless of HTML case. Values are the
// Filipino (technical) rendering.
function lang_dict(): array
{
    static $dict = null;
    if ($dict === null) {
        $dict = [

            // ---------- Navigation / main menu ----------
            'home' => 'Tahanan',
            'dashboard' => 'Dashboard',
            'submit report' => 'Mag-ulat ng Isyu',
            'my reports' => 'Aking mga Ulat',
            'announcements' => 'Mga Anunsyo',
            'notifications' => 'Mga Notipikasyon',
            'profile' => 'Profile',
            'settings' => 'Mga Setting',
            'system settings' => 'Mga Setting ng Sistema',
            'manage reports' => 'Pamahalaan ang mga Ulat',
            'all reports' => 'Lahat ng mga Ulat',
            'reporters directory' => 'Direktoryo ng mga Nag-uulat',
            'user management' => 'Pamamahala ng mga Gumagamit',
            'manage users' => 'Pamahalaan ang mga Gumagamit',
            'audit logs' => 'Mga Tala ng Pag-audit',
            'post announcement' => 'Mag-post ng Anunsyo',
            'logout' => 'Mag-logout',
            'sign in' => 'Mag-sign In',
            'register' => 'Magrehistro',
            'how it works' => 'Paano Ito Gumagana',
            'map' => 'Mapa',
            'stats' => 'Estadistika',
            'statistics' => 'Estadistika',
            'about lgu' => 'Tungkol sa LGU',
            'faq' => 'FAQ',
            'faqs' => 'FAQ',
            'back to profile' => 'Bumalik sa Profile',
            'menu' => 'Menu',
            'main navigation' => 'Pangunahing Nabigasyon',
            'environmental reporting' => 'Reporting Pangkapaligiran',
            'open navigation menu' => 'Buksan ang menu ng nabigasyon',
            'close sidebar menu' => 'Isara ang menu ng sidebar',
            'hide sidebar' => 'Itago ang sidebar',
            'show menu' => 'Ipakita ang menu',

            // ---------- Authentication ----------
            'login' => 'Mag-login',
            'log in' => 'Mag-login',
            'jump to your dashboard' => 'Pumunta sa iyong dashboard',
            'welcome back' => 'Maligayang pagbabalik',
            'welcome to' => 'Maligayang pagdating sa',
            'create free account' => 'Gumawa ng Libreng Account',
            'join now' => 'Sumali Na',
            'register now' => 'Magrehistro Ngayon',
            'forgot password' => 'Nakalimutan ang Password',
            'reset password' => 'I-reset ang Password',
            'confirm password' => 'Kumpirmahin ang Password',
            'new password' => 'Bagong Password',
            'current password' => 'Kasalukuyang Password',
            'email' => 'Email',
            'email address' => 'Email Address',
            'mobile number' => 'Numero ng Cellphone',
            'contact number' => 'Numero ng Kontak',
            'phone number' => 'Numero ng Telepono',
            'password' => 'Password',
            'remember me' => 'Tandaan ako',
            "don't have an account?" => "Wala ka pang account?",
            'already have an account?' => 'May account ka na?',
            'create an account' => 'Gumawa ng Account',
            'sign in to your account' => 'Mag-sign in sa iyong account',
            'enter your email address' => 'Ilagay ang iyong email address',
            'enter your password' => 'Ilagay ang iyong password',
            'full name' => 'Buong Pangalan',
            'first name' => 'Unang Pangalan',
            'first name *' => 'Unang Pangalan *',
            'last name' => 'Apelyido',
            'last name *' => 'Apelyido *',
            'middle name' => 'Gitnang Pangalan',
            'birthdate' => 'Petsa ng Kapanganakan',
            'date of birth' => 'Petsa ng Kapanganakan',
            'gender' => 'Kasarian',
            'male' => 'Lalaki',
            'female' => 'Babae',
            'select gender' => 'Pumili ng Kasarian',
            'are you a resident of san isidro?' => 'Residente ka ba ng San Isidro?',
            'yes' => 'Oo',
            'no' => 'Hindi',
            'resident' => 'Residente',
            'non-resident' => 'Hindi Residente',
            'barangay of residence' => 'Barangay na Tinitirhan',
            'select barangay' => 'Pumili ng Barangay',
            'street / purok' => 'Kalsada / Purok',
            'street/purok' => 'Kalsada / Purok',
            'complete address' => 'Kumpletong Address',
            'residential address' => 'Tirahan',
            'send reset link' => 'Ipadala ang Reset Link',
            'send otp' => 'Ipadala ang OTP',
            'enter otp' => 'Ilagay ang OTP',
            'verify otp' => 'I-verify ang OTP',
            'resend code' => 'Ipadala muli ang Code',
            'verify code' => 'I-verify ang Code',
            'verify' => 'I-verify',
            'back to login' => 'Bumalik sa Login',
            'session expired' => 'Wala nang bisa ang Session',
            'logout' => 'Mag-logout',

            // ---------- Common buttons / actions ----------
            'submit' => 'Ipasa',
            'save' => 'I-save',
            'save changes' => 'I-save ang mga Pagbabago',
            'save report' => 'I-save ang Ulat',
            'update' => 'I-update',
            'update report' => 'I-update ang Ulat',
            'cancel' => 'Kanselahin',
            'close' => 'Isara',
            'delete' => 'Tanggalin',
            'edit' => 'I-edit',
            'view' => 'Tingnan',
            'view all' => 'Tingnan Lahat',
            'see all' => 'Tingnan Lahat',
            'view details' => 'Tingnan ang Mga Detalye',
            'details' => 'Mga Detalye',
            'search' => 'Maghanap',
            'search reports' => 'Maghanap ng mga Ulat',
            'search users' => 'Maghanap ng mga Gumagamit',
            'search announcements' => 'Maghanap ng mga Anunsyo',
            'filter' => 'Salain',
            'filters' => 'Mga Filter',
            'apply' => 'Ilapat',
            'apply filters' => 'Ilapat ang Mga Filter',
            'clear' => 'I-clear',
            'clear all' => 'I-clear Lahat',
            'clear filters' => 'I-clear ang mga Filter',
            'clear filter' => 'I-clear ang Filter',
            'confirm' => 'Kumpirmahin',
            'continue' => 'Magpatuloy',
            'back' => 'Bumalik',
            'next' => 'Susunod',
            'previous' => 'Nauna',
            'first' => 'Una',
            'last' => 'Huli',
            'load more' => 'Mag-load ng Higit Pa',
            'show more' => 'Ipakita ang Higit Pa',
            'show less' => 'Ipakita ang Mas Kaunti',
            'show' => 'Ipakita',
            'entries' => 'mga entry',
            'results' => 'mga resulta',
            'result' => 'resulta',
            'of' => 'ng',
            'total' => 'Kabuuan',
            'loading' => 'Naglo-load',
            'loading...' => 'Naglo-load...',
            'please wait' => 'Mangyaring maghintay',
            'processing' => 'Ini-process',
            'upload' => 'Mag-upload',
            'upload photo' => 'Mag-upload ng Larawan',
            'upload photos' => 'Mag-upload ng mga Larawan',
            'add photo' => 'Magdagdag ng Larawan',
            'attach photo' => 'Mag-attach ng Larawan',
            'attached photos' => 'Mga Nakakabit na Larawan',
            'remove' => 'Alisin',
            'download' => 'I-download',
            'export' => 'I-export',
            'export pdf' => 'I-export sa PDF',
            'export csv' => 'I-export sa CSV',
            'print' => 'I-print',
            'generate report' => 'Bumuo ng Ulat',
            'copy' => 'Kopyahin',
            'copy link' => 'Kopyahin ang Link',
            'share' => 'I-share',
            'select' => 'Pumili',
            'select all' => 'Piliin Lahat',
            'deselect' => 'Alisin ang Pagpili',
            'select file' => 'Pumili ng File',
            'choose file' => 'Pumili ng File',
            'no file chosen' => 'Walang napiling file',
            'drag and drop or click to upload' => 'I-drag at i-drop o i-click para mag-upload',
            'start' => 'Simulan',
            'finish' => 'Tapusin',
            'reset' => 'I-reset',
            'refresh' => 'I-refresh',
            'reload' => 'I-reload',
            'add' => 'Magdagdag',
            'create' => 'Gumawa',
            'retry' => 'Subukan Muli',

            // ---------- Report domain ----------
            'report' => 'Ulat',
            'reports' => 'Mga Ulat',
            'report an issue' => 'Mag-ulat ng Isyu',
            'new report' => 'Bagong Ulat',
            'submit new report' => 'Mag-ulat ng Bagong Isyu',
            'report id' => 'ID ng Ulat',
            'report title' => 'Pamagat ng Ulat',
            'report type' => 'Uri ng Ulat',
            'report category' => 'Kategorya ng Ulat',
            'description' => 'Paglalarawan',
            'reports filed' => 'Mga Nakapag-isang Ulat',
            'total reports' => 'Kabuuang mga Ulat',
            'recent reports' => 'Mga Kamakailang Ulat',
            'latest reports' => 'Mga Pinakabagong Ulat',
            'my recent reports' => 'Aking mga Kamakailang Ulat',
            'resolved reports' => 'Mga Nalutas na Ulat',
            'pending reports' => 'Mga Nakabinbing Ulat',
            'in progress reports' => 'Mga Ulat sa Proseso',
            'rejected reports' => 'Mga Tinanggihang Ulat',
            'unresolved reports' => 'Mga Hindi Nalutas na Ulat',
            'new reports today' => 'Mga Bagong Ulat Ngayon',
            'report status' => 'Katayuan ng Ulat',
            'resolution rate' => 'Rate ng Pagkalutas',
            'resolution details' => 'Mga Detalye ng Pagkalutas',
            'resolution evidence' => 'Mga Ebidensya ng Pagkalutas',
            'resolution notes' => 'Mga Tala sa Pagkalutas',
            'resolution date' => 'Petsa ng Pagkalutas',
            'action taken' => 'Ginawang Aksyon',
            'approved by' => 'Inaprubahan ni',
            'date reported' => 'Petsa ng Pag-ulat',
            'reported on' => 'Naiulat Noong',
            'related reports' => 'Mga Kaugnay na Ulat',
            'no upcoming reports' => 'Walang paparating na mga ulat',
            'reporter' => 'Nag-ulat',
            'report details' => 'Mga Detalye ng Ulat',
            'edit report' => 'I-edit ang Ulat',
            'verify report' => 'I-verify ang Ulat',
            'verified' => 'Na-verify',
            'unverified' => 'Hindi Na-verify',
            'enter tracking code' => 'Ilagay ang Tracking Code',
            'track your report' => 'Subaybayan ang Iyong Ulat',
            'track report' => 'Subaybayan ang Ulat',
            'track status' => 'Subaybayan ang Katayuan',
            'tracking code' => 'Tracking Code',
            'timeline' => 'Kronolohiya',
            'history' => 'Kasaysayan',
            'case number' => 'Numero ng Kaso',
            'flagged' => 'Minarkahan',
            'under review' => 'Sinusuri',
            'on notice' => 'May Abiso',

            // ---------- Report categories / statuses / priority ----------
            'illegal dumping' => 'Illegal na Pagtatapon',
            'illegal logging' => 'Illegal na Pagtotroso',
            'uncollected garbage' => 'Hindi Nakolektang Basura',
            'drainage blockage' => 'Bara sa Drainage',
            'water pollution' => 'Polusyon sa Tubig',
            'air pollution' => 'Polusyon sa Hangin',
            'noise pollution' => 'Polusyon sa Ingay',
            'land pollution' => 'Polusyon sa Lupa',
            'flooding' => 'Paglilindol',
            'others' => 'Iba pa',
            'other' => 'Iba pa',
            'all categories' => 'Lahat ng Kategorya',
            'status' => 'Katayuan',
            'statuses' => 'Mga Katayuan',
            'all statuses' => 'Lahat ng Katayuan',
            'pending' => 'Nakabinbin',
            'in progress' => 'Kasalukuyan',
            'resolved' => 'Nalutas',
            'rejected' => 'Tinanggihan',
            'deleted' => 'Tinanggal',
            'submitted' => 'Naipasa',
            'open' => 'Bukas',
            'closed' => 'Sarado',
            'priority' => 'Priyoridad',
            'priorities' => 'Mga Priyoridad',
            'all priorities' => 'Lahat ng Priyoridad',
            'low' => 'Mababa',
            'medium' => 'Katamtaman',
            'high' => 'Mataas',
            'critical' => 'Kritikal',
            'urgent' => 'Apurahan',
            'normal' => 'Normal',
            'category' => 'Kategorya',
            'categories' => 'Mga Kategorya',

            // ---------- People / roles ----------
            'admin' => 'Admin',
            'menro staff' => 'MENRO Staff',
            'barangay official' => 'Opisyal ng Barangay',
            'citizen' => 'Mamamayan',
            'user' => 'Gumagamit',
            'users' => 'Mga Gumagamit',
            'total users' => 'Kabuuang mga Gumagamit',
            'user id' => 'ID ng Gumagamit',
            'username' => 'Username',
            'role' => 'Tungkulin',
            'roles' => 'Mga Tungkulin',
            'all roles' => 'Lahat ng Tungkulin',
            'all users' => 'Lahat ng mga Gumagamit',
            'active' => 'Aktibo',
            'inactive' => 'Hindi Aktibo',
            'suspended' => 'Sinuspinde',
            'banned' => 'Binawalan',
            'joined' => 'Sumali',
            'member since' => 'Miyembro mula pa',
            'registered' => 'Nakarehistro',
            'account status' => 'Katayuan ng Account',
            'last active' => 'Huling Aktibo',
            'last login' => 'Huling Pag-login',
            'actions' => 'Mga Aksyon',
            'activated' => 'Na-activate',
            'deactivated' => 'Na-deactivate',

            // ---------- Dates / time ----------
            'today' => 'Ngayon',
            'yesterday' => 'Kahapon',
            'this week' => 'Ngayong Linggo',
            'this month' => 'Ngayong Buwan',
            'this year' => 'Ngayong Taon',
            'last 7 days' => 'Nakaraang 7 Araw',
            'last 30 days' => 'Nakaraang 30 Araw',
            'last 90 days' => 'Nakaraang 90 Araw',
            'all time' => 'Lahat ng Panahon',
            'date' => 'Petsa',
            'date range' => 'Saklaw ng Petsa',
            'date filed' => 'Petsa ng Paghain',
            'date created' => 'Petsa ng Paggawa',
            'date updated' => 'Petsa ng Pag-update',
            'updated at' => 'Na-update Noong',
            'created at' => 'Ginawa Noong',
            'created' => 'Ginawa',
            'updated' => 'Na-update',
            'jan' => 'Ene',
            'feb' => 'Peb',
            'mar' => 'Mar',
            'apr' => 'Abr',
            'jun' => 'Hun',
            'jul' => 'Hul',
            'aug' => 'Ago',
            'sep' => 'Set',
            'oct' => 'Okt',
            'nov' => 'Nob',
            'dec' => 'Dis',
            'just now' => 'Ngayon lang',
            'minutes ago' => 'minuto ang nakalipas',
            'hours ago' => 'oras ang nakalipas',
            'days ago' => 'araw ang nakalipas',
            'min ago' => 'min ang nakalipas',
            'hrs ago' => 'oras ang nakalipas',

            // ---------- Empty states / messages ----------
            'no results found' => 'Walang nahanap na resulta',
            'no records found' => 'Walang nahanap na talaan',
            'no reports yet' => 'Wala pang mga ulat',
            'no reports found' => 'Walang nahanap na mga ulat',
            'no data available' => 'Walang available na data',
            'no notifications yet' => 'Wala pang mga notipikasyon',
            'new reports and escalations will appear here' => 'Lalabas dito ang mga bagong ulat at pag-akyat ng isyu',
            'nothing to show' => 'Wala pang maipapakita',
            'nothing found' => 'Walang nahanap',
            'no announcements yet' => 'Wala pang mga anunsyo',
            'no announcements' => 'Walang mga anunsyo',
            'an empty state' => 'Walang laman',
            'start by filing your first report' => 'Magsimula sa pamamagitan ng paghain ng iyong unang ulat',
            'start by posting your first announcement' => 'Magsimula sa pamamagitan ng pag-post ng iyong unang anunsyo',
            'no upcoming events' => 'Walang paparating na kaganapan',
            'no selections made' => 'Walang ginawang pagpili',
            'no file selected' => 'Walang napiling file',
            'please fill out this field' => 'Paki-kumpletuhin ang field na ito',
            'required' => 'Kinakailangan',
            'this field is required' => 'Kinakailangan ang field na ito',
            'invalid email' => 'Mali ang email address',
            'invalid email address' => 'Mali ang email address',
            'password does not match' => 'Hindi tugma ang password',
            'password changed successfully' => 'Matagumpay na nabago ang password',
            'operation successful' => 'Matagumpay ang operasyon',
            'saved successfully' => 'Matagumpay na na-save',
            'updated successfully' => 'Matagumpay na na-update',
            'deleted successfully' => 'Matagumpay na natanggal',
            'submitted successfully' => 'Matagumpay na naipasa',
            'report submitted successfully' => 'Matagumpay na naipasa ang ulat',
            'something went wrong' => 'May nangyaring problema',
            'an error occurred' => 'May naganap na error',
            'please try again' => 'Mangyaring subukan muli',
            'error' => 'Error',
            'success' => 'Tagumpay',
            'warning' => 'Babala',
            'info' => 'Impormasyon',
            'are you sure?' => 'Sigurado ka ba?',
            'are you sure you want to delete this?' => 'Sigurado ka bang gusto mong tanggalin ito?',
            'are you sure you want to logout from your account?' => 'Sigurado ka bang gusto mong mag-logout sa iyong account?',
            'are you sure you want to mark this as resolved?' => 'Sigurado ka bang gusto mong markahan ito bilang nalutas?',
            'this action cannot be undone' => 'Hindi maaaring i-undo ang aksyon na ito',
            'confirm logout' => 'Kumpirmahin ang Pag-logout',
            'customize what you want to see' => 'I-customize kung ano ang gusto mong makita',
            'settings updated' => 'Na-update ang mga setting',
            'settings saved' => 'Na-save ang mga setting',

            // ---------- Profile sections ----------
            'my account' => 'Aking Account',
            'my profile' => 'Aking Profile',
            'edit profile' => 'I-edit ang Profile',
            'basic information' => 'Pangunahing Impormasyon',
            'contact information' => 'Impormasyon ng Kontak',
            'address information' => 'Impormasyon ng Address',
            'personal information' => 'Impormasyong Personal',
            'change password' => 'Palitan ang Password',
            'password requirements' => 'Mga Kinakailangan sa Password',
            'activity log' => 'Mga Tala ng Aktibidad',
            'activity logs' => 'Mga Tala ng Aktibidad',
            'total activities' => 'Kabuuang mga Aktibidad',
            'pdf export settings' => 'Mga Setting ng Pag-export ng PDF',
            'pdf export' => 'Pag-export ng PDF',
            'pdf template' => 'PDF Template',
            'logo' => 'Logo',
            'language settings' => 'Mga Setting ng Wika',
            'language' => 'Wika',
            'lang settings' => 'Mga Setting ng Wika',
            'account' => 'Account',
            'legal & about' => 'Legal at Tungkol',
            'legal and about' => 'Legal at Tungkol',
            'about' => 'Tungkol',
            'about us' => 'Tungkol sa Amin',
            'terms of service' => 'Mga Tuntunin ng Serbisyo',
            'terms' => 'Mga Tuntunin',
            'privacy notice' => 'Abiso sa Pribasya',
            'privacy' => 'Pribasya',
            'privacy policy' => 'Patakaran sa Pribasya',
            'support' => 'Suporta',
            'help' => 'Tulong',
            'help center' => 'Sentro ng Tulong',
            'help and support' => 'Tulong at Suporta',
            'frequently asked questions' => 'Mga Madalas Itanong',
            'session' => 'Session',
            'manage your account details' => 'Pamahalaan ang mga detalye ng iyong account',
            'choose a section to manage your account' => 'Pumili ng seksyon para pamahalaan ang iyong account',
            'choose the language used across the application. changes apply immediately.' => 'Piliin ang wikang gagamitin sa buong aplikasyon. Ilalapat agad ang mga pagbabago.',
            'the default language of the application.' => 'Ang default na wika ng aplikasyon.',
            'use technical filipino words across the application.' => 'Gumamit ng teknikal na Filipino sa buong aplikasyon.',
            'change profile photo' => 'Palitan ang Larawan ng Profile',
            'change photo' => 'Palitan ang Larawan',
            'crop photo' => 'I-crop ang Larawan',
            'crop & upload' => 'I-crop at I-upload',
            'choose from gallery' => 'Pumili mula sa Gallery',
            'choose an avatar' => 'Pumili ng Avatar',
            'no avatar' => 'Walang Avatar',
            'or' => 'o',
            'phone otp' => 'OTP sa Telepono',
            'verify phone number' => 'I-verify ang Numero ng Telepono',
            'confirm email address' => 'Kumpirmahin ang Email Address',
            "we've sent a 6-digit otp to your new mobile number. enter it below to verify." => 'Nagpadala kami ng 6-digit OTP sa iyong bagong numero. Ilagay ito sa ibaba para i-verify.',
            "we've sent a confirmation code to your new email address. enter it below to confirm." => 'Nagpadala kami ng confirmation code sa iyong bagong email. Ilagay ito sa ibaba para kumpirmahin.',
            'expires in 10 minutes.' => 'Mag-eexpire sa loob ng 10 minuto.',
            'expires in 30 minutes.' => 'Mag-eexpire sa loob ng 30 minuto.',

            // ---------- Landing page / hero ----------
            'good to see you,' => 'Magandang makita ka,',
            'scroll' => 'Mag-scroll',
            'scroll to explore' => 'Mag-scroll upang galugarin',
            'see something wrong in your neighborhood? drainage blockage, illegal dumping, or uncollected garbage?' => 'May nakikita ka bang problema sa inyong lugar? Bara sa drainage, illegal na pagtatapon, o hindi nakolektang basura?',
            'report it in seconds, track it in real-time, and help keep san isidro clean.' => 'Iulat ito sa ilang segundo, subaybayan nang real-time, at tumulong linisin ang San Isidro.',
            'start reporting' => 'Simulang Mag-ulat',
            'browse announcements' => 'Tingnan ang mga Anunsyo',
            'san isidro at a glance' => 'San Isidro sa Isang Sulyap',
            'live report overview' => 'Live na Pangkalahatang-Tanaw ng Ulat',
            'monthly report trend' => 'Buwanang Trend ng Ulat',
            'resolution performance' => 'Pagganap sa Pagkalutas',
            'community impact' => 'Epekto sa Komunidad',
            'posts' => 'mga post',
            'reporter updates' => 'mga update ng nag-uulat',
            'menro alerts & report updates' => 'Mga alerto ng MENRO at update ng ulat',
            'mark all as read' => 'Markahan lahat bilang nabasa',
            'view all notifications' => 'Tingnan ang lahat ng notipikasyon',

            // ---------- / misc ----------
            'select language' => 'Pumili ng Wika',
            'english' => 'Inglés',
            'filipino' => 'Filipino',
            'skip to main content' => 'Laktawan papunta sa pangunahing nilalaman',
            'close modal' => 'Isara ang modal',
            'previous slide' => 'Naunang slide',
            'next slide' => 'Susunod na slide',
            'image' => 'Larawan',
            'photo' => 'Larawan',
            'images' => 'Mga Larawan',
            'photos' => 'Mga Larawan',
            'view larger image' => 'Tingnan ang mas malaking larawan',

            // ---------- Maintenance page ----------
            'system under maintenance' => 'Sistema sa Ilalim ng Pagpapanatili',
            "we'll be right back" => 'Babalik kami sa ilang sandali',
            'staff login' => 'Pag-login ng Staff',
            'our team is performing routine maintenance or resolving an issue.' => 'Ang aming pangkat ay gumagawa ng regular na pagpapanatili o inaayos ang isang isyu.',
            'please check back shortly. thank you for your patience.' => 'Mangyaring bumalik sa ilang sandali. Salamat sa inyong pagtitiis.',
            'contact the menro office if you need immediate assistance.' => 'Makipag-ugnayan sa tanggapan ng MENRO kung kailangan mo ng agarang tulong.',
            'is temporarily offline' => 'ay pansamantalang offline',

            // ---------- Upload / progress ----------
            'uploading...' => 'Nag-u-upload...',
            'submitting...' => 'Iniipasa...',
            'sending...' => 'Ipinapadala...',
            'saving...' => 'Ini-save...',
            'deleting...' => 'Tinatanggal...',
            'updating...' => 'Ina-update...',
            'demo account' => 'Demo na Account',
            'demo accounts' => 'Mga Demo na Account',
            'one-click login for testing' => 'Isang-click na pag-login para sa pagsubok',
        ];
    }
    return $dict;
}

function t(string $en, string $fil = ''): string
{
    if (app_lang() !== 'fil') {
        return $en;
    }
    if ($fil !== '') {
        return $fil;
    }
    $key = strtolower(trim($en));
    return lang_dict()[$key] ?? $en;
}

// Shared JavaScript used by every language dropdown widget.
function lang_toggle_js(): string
{
    return <<<'JS'
document.querySelectorAll('[data-lang-root]').forEach(function (root) {
    var btn = root.querySelector('[data-lang-btn]');
    var menu = root.querySelector('[data-lang-menu]');
    if (!btn || !menu) return;
    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        e.preventDefault();
        var isOpen = root.classList.toggle('open');
        btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
});
document.querySelectorAll('[data-lang-opt]').forEach(function (opt) {
    opt.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var lang = opt.getAttribute('data-lang-opt');
        document.cookie = 'lang=' + lang + '; path=/; max-age=31536000; SameSite=Lax';
        window.location.reload();
    });
});
document.addEventListener('click', function (e) {
    document.querySelectorAll('[data-lang-root].open').forEach(function (root) {
        if (!root.contains(e.target)) {
            root.classList.remove('open');
            var b = root.querySelector('[data-lang-btn]');
            if (b) b.setAttribute('aria-expanded', 'false');
        }
    });
});
JS;
}

// Client-side translator: scans the rendered page and converts every remaining
// hard-coded English UI string to Filipino. Register this on EVERY page
// (via layouts/sidebar.php for authenticated pages, and public page footers).
function lang_apply_js(): string
{
    $dict = json_encode(lang_dict(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return <<<JS
<script>
(function () {
    'use strict';
    var DICT = {$dict};

    function getLang() {
        var m = document.cookie.match(/(?:^|;\s*)lang=([a-z]+)/);
        return m ? m[1] : '';
    }

    function preserveCase(src, out) {
        if (!src || !out) return out;
        if (src === src.toUpperCase() && src !== src.toLowerCase()) {
            return out.toUpperCase();
        }
        var c0 = src.charAt(0);
        if (c0 === c0.toLowerCase() && c0 !== c0.toUpperCase()) {
            return out.charAt(0).toLowerCase() + out.slice(1);
        }
        return out;
    }

    function isSkipped(el) {
        var p = el;
        while (p && p.nodeType === 1) {
            var tag = p.tagName.toLowerCase();
            if (tag === 'script' || tag === 'style' || tag === 'textarea' || tag === 'code' ||
                tag === 'pre' || tag === 'kbd' || tag === 'svg' || tag === 'noscript') {
                return true;
            }
            if (p.getAttribute && (p.hasAttribute('data-no-translate') || p.classList.contains('no-translate'))) {
                return true;
            }
            p = p.parentNode;
        }
        return false;
    }

    function translateTextNode(node) {
        var raw = node.nodeValue;
        if (!raw) return;
        var m = raw.match(/^(\s*)(.*?)(\s*)\$/s);
        if (!m) return;
        var lead = m[1], core = m[2], trail = m[3];
        if (!core) return;
        var key = core.toLowerCase();
        if (DICT.hasOwnProperty(key) && DICT[key] !== undefined && DICT[key] !== key) {
            node.nodeValue = lead + preserveCase(core, String(DICT[key])) + trail;
        }
    }

    function translateAttr(el, attr) {
        var v = el.getAttribute(attr);
        if (!v || !v.trim()) return;
        var k = v.trim().toLowerCase();
        if (DICT.hasOwnProperty(k) && DICT[k] !== undefined && DICT[k] !== k) {
            el.setAttribute(attr, preserveCase(v.trim(), String(DICT[k])));
        }
    }

    function translateAttrs(el) {
        ['placeholder', 'title', 'aria-label', 'alt'].forEach(function (attr) {
            translateAttr(el, attr);
        });
        var tag = el.tagName ? el.tagName.toLowerCase() : '';
        if (tag === 'input') {
            var type = (el.getAttribute('type') || '').toLowerCase();
            if (type === 'submit' || type === 'button' || type === 'reset') {
                translateAttr(el, 'value');
            }
        }
    }

    function run() {
        var walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {
            acceptNode: function (n) {
                if (!n.nodeValue || !n.nodeValue.trim()) return NodeFilter.FILTER_REJECT;
                if (n.parentNode && n.parentNode.nodeType === 1 && isSkipped(n.parentNode)) return NodeFilter.FILTER_REJECT;
                return NodeFilter.FILTER_ACCEPT;
            }
        });
        var nodes = [];
        while (walker.nextNode()) nodes.push(walker.currentNode);
        nodes.forEach(translateTextNode);

        var els = document.querySelectorAll('body *');
        els.forEach(function (el) {
            if (isSkipped(el)) return;
            translateAttrs(el);
        });
    }

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    ready(function () {
        if (getLang() !== 'fil') return;
        document.documentElement.setAttribute('lang', 'fil');
        run();
    });
})();
</script>
JS;
}

// Shared CSS for the language dropdown widget.
function lang_toggle_css(): string
{
    return <<<'CSS'
.lang-dropdown { position: relative; }
.lang-toggle-btn {
    display: inline-flex; align-items: center; gap: 0.5rem;
    background: #f0fdf4; color: #047857;
    border: 1px solid #a7f3d0; border-radius: 0.75rem;
    padding: 0.5rem 0.85rem; font-size: 0.8rem; font-weight: 600;
    cursor: pointer; transition: all 0.2s ease; line-height: 1;
}
.lang-toggle-btn:hover { background: #d1fae5; border-color: #6ee7b7; }
.lang-toggle-btn i { font-size: 0.85rem; }
.lang-toggle-btn .lang-chev { font-size: 0.65rem; transition: transform 0.2s ease; }
.lang-dropdown.open .lang-chev { transform: rotate(180deg); }
.lang-menu {
    position: absolute; top: calc(100% + 0.5rem); right: 0;
    min-width: 175px; background: #fff; border: 1px solid #eceff1; border-radius: 0.85rem;
    box-shadow: 0 18px 40px -12px rgba(2, 44, 34, 0.25);
    padding: 0.4rem; z-index: 60;
    opacity: 0; visibility: hidden; transform: translateY(-6px);
    transition: opacity 0.18s ease, transform 0.18s ease, visibility 0.18s;
}
.lang-dropdown.open .lang-menu { opacity: 1; visibility: visible; transform: translateY(0); }
.lang-menu button {
    display: flex; align-items: center; justify-content: space-between; gap: 0.75rem;
    width: 100%; text-align: left; padding: 0.55rem 0.75rem; border-radius: 0.55rem;
    font-size: 0.82rem; font-weight: 500; color: #374151;
    background: none; border: none; cursor: pointer; font-family: inherit;
}
.lang-menu button:hover { background: #f0fdf4; color: #047857; }
.lang-menu button.active { background: #ecfdf5; color: #047857; font-weight: 700; }
.lang-menu .lang-code {
    font-size: 0.68rem; font-weight: 700; color: #059669;
    background: #ecfdf5; border-radius: 0.35rem; padding: 0.1rem 0.4rem; letter-spacing: 0.02em;
}
@media (max-width: 640px) {
    .lang-menu { right: auto; left: 0; }
}
.lang-option-card {
    position: relative; display: block; width: 100%; text-align: left;
    background: #ffffff; border: 2px solid #e7efe9; border-radius: 0.9rem;
    padding: 0.9rem 1rem 1rem; cursor: pointer; transition: all 0.2s ease;
    font-family: inherit;
}
.lang-option-card:hover { border-color: #6ee7b7; background: #fafffb; }
.lang-option-card.active { border-color: #059669; background: #f0fdf4; }
.lang-option-card .check {
    position: absolute; top: 0.75rem; right: 0.75rem;
    width: 1.4rem; height: 1.4rem; border-radius: 50%;
    background: #e2e8f0; color: transparent;
    display: flex; align-items: center; justify-content: center; font-size: 0.7rem;
    transition: all 0.2s ease;
}
.lang-option-card.active .check { background: #059669; color: #ffffff; }
@media (max-width: 480px) {
    .lang-option-card { padding: 0.8rem 0.85rem 0.9rem; }
}
.lang-list {
    display: flex; flex-direction: column;
    background: #ffffff; border: 1px solid #e5e7eb; border-radius: 0.9rem;
    overflow: hidden; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
}
.lang-list-option {
    display: flex; align-items: center; justify-content: space-between; gap: 0.75rem;
    width: 100%; padding: 0.85rem 1rem;
    background: #ffffff; border: none; border-bottom: 1px solid #f1f5f4;
    cursor: pointer; font-family: inherit; transition: background 0.2s ease;
}
.lang-list-option:last-child { border-bottom: none; }
.lang-list-option:hover { background: #fafffb; }
.lang-list-option.active { background: #f0fdf4; }
.lang-list-option .lang-radio {
    flex-shrink: 0; display: flex; align-items: center; justify-content: center;
    width: 1.35rem; height: 1.35rem; border-radius: 50%;
    background: #e2e8f0; color: transparent; font-size: 0.68rem;
    transition: all 0.2s ease;
}
.lang-list-option.active .lang-radio { background: #059669; color: #ffffff; }

/* Icon-only language button */
.lang-icon-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 2.5rem; height: 2.5rem; flex-shrink: 0;
    background: #f0fdf4; color: #047857;
    border: 1px solid #a7f3d0; border-radius: 0.7rem;
    cursor: pointer; transition: background 0.18s ease, border-color 0.18s ease;
}
.lang-icon-btn:hover { background: #d1fae5; border-color: #6ee7b7; }
.lang-icon-btn i { font-size: 0.95rem; }

/* Segmented language switch (no dropdown) */
.lang-seg {
    display: inline-flex; align-items: center; gap: 2px;
    background: #eaf2ee; border: 1px solid #d7e5de; border-radius: 999px; padding: 3px;
}
.lang-seg button {
    display: inline-flex; align-items: center; justify-content: center;
    border: none; background: transparent; cursor: pointer;
    padding: 0.32rem 0.72rem; border-radius: 999px;
    font-size: 0.75rem; font-weight: 700; color: #5b6b64;
    font-family: inherit; transition: color 0.18s ease, background 0.18s ease, box-shadow 0.18s ease;
    line-height: 1;
}
.lang-seg button:hover { color: #047857; }
.lang-seg button.active {
    background: #ffffff; color: #047857;
    box-shadow: 0 1px 3px rgba(2, 44, 34, 0.18);
}
.lang-seg button:focus-visible { outline: 2px solid #10a37f; outline-offset: 2px; }
.nav-mobile-lang .lang-seg { width: 100%; }
.nav-mobile-lang .lang-seg button { flex: 1 1 0; padding: 0.6rem 0; font-size: 0.82rem; }
CSS;
}

// Render a language toggle widget (globe button + dropdown menu).
function lang_toggle_widget(): string
{
    $current = app_lang();
    $label   = $current === 'fil' ? t('Filipino') : 'English';
    $en_act  = $current === 'en' ? 'active' : '';
    $fil_act = $current === 'fil' ? 'active' : '';
    return '<div class="lang-dropdown" data-lang-root>' .
        '<button type="button" class="lang-toggle-btn" data-lang-btn aria-haspopup="true" aria-expanded="false" aria-label="Select language">' .
        '<i class="fas fa-globe" aria-hidden="true"></i><span data-lang-label class="no-translate">' . htmlspecialchars($label) . '</span>' .
        '<i class="fas fa-chevron-down lang-chev" aria-hidden="true"></i></button>' .
        '<div class="lang-menu" data-lang-menu role="menu">' .
        '<button type="button" role="menuitem" data-lang-opt="en" class="' . $en_act . '"><span class="no-translate">English</span><span class="lang-code">EN</span></button>' .
        '<button type="button" role="menuitem" data-lang-opt="fil" class="' . $fil_act . '"><span class="no-translate">Filipino</span><span class="lang-code">FIL</span></button>' .
        '</div></div>';
}

// Render a compact icon-only language button (globe) that opens the same
// language menu — for the landing navbar.
function lang_icon_widget(): string
{
    $current = app_lang();
    $en_act  = $current === 'en' ? 'active' : '';
    $fil_act = $current === 'fil' ? 'active' : '';
    $label   = $current === 'fil' ? 'Filipino' : 'English';
    return '<div class="lang-dropdown" data-lang-root>' .
        '<button type="button" class="lang-icon-btn" data-lang-btn aria-haspopup="true" aria-expanded="false" aria-label="Language: ' . htmlspecialchars($label) . '" title="' . htmlspecialchars($label) . '">' .
        '<i class="fas fa-globe" aria-hidden="true"></i></button>' .
        '<div class="lang-menu" data-lang-menu role="menu">' .
        '<button type="button" role="menuitem" data-lang-opt="en" class="' . $en_act . '"><span class="no-translate">English</span><span class="lang-code">EN</span></button>' .
        '<button type="button" role="menuitem" data-lang-opt="fil" class="' . $fil_act . '"><span class="no-translate">Filipino</span><span class="lang-code">FIL</span></button>' .
        '</div></div>';
}

// Render a segmented language switch (no dropdown) for compact surfaces like
// the landing navbar and the mobile burger menu. Relies on the same
// [data-lang-opt] click handler emitted by lang_toggle_js().
function lang_segmented_widget(): string
{
    $current = app_lang();
    $en_act  = $current === 'en' ? ' active' : '';
    $fil_act = $current === 'fil' ? ' active' : '';
    return '<div class="lang-seg no-translate" role="group" aria-label="Language">' .
        '<button type="button" data-lang-opt="en" class="no-translate' . $en_act . '" aria-pressed="' . ($current === 'en' ? 'true' : 'false') . '">English</button>' .
        '<button type="button" data-lang-opt="fil" class="no-translate' . $fil_act . '" aria-pressed="' . ($current === 'fil' ? 'true' : 'false') . '">Filipino</button>' .
        '</div>';
}