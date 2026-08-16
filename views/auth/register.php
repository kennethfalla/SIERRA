<?php
// views/auth/register.php - 3-STEP REGISTRATION WIZARD WITH REAL OTP & DYNAMIC PASSWORD RULES
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/helpers/SecurityHelper.php';
require_once BASE_PATH . 'helpers/SettingsHelper.php';

if (isLoggedIn()) {
    header("Location: " . BASE_URL . "index.php?page=dashboard");
    exit();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$csrf_token = InputSanitizer::generateCsrfToken();

$database = new Database();
$db = $database->getConnection();

// Get barangays for San Isidro
$barangays = $db->query("SELECT id, name FROM barangays ORDER BY name");

// Load dynamic settings
$system_name = SettingsHelper::get('system_name', 'Sierra');
$lgu_logo = SettingsHelper::get('lgu_logo', '');
$logo_url = $lgu_logo ? BASE_URL . $lgu_logo : '';

// ============================================
// DYNAMIC PASSWORD RULES FROM SECURITY SETTINGS
// ============================================
$pwd_min = (int) SettingsHelper::get('password_min_length', 8);
$pwd_require_upper   = (int) SettingsHelper::get('password_require_upper', 1);
$pwd_require_lower   = (int) SettingsHelper::get('password_require_lower', 1);
$pwd_require_number  = (int) SettingsHelper::get('password_require_number', 1);
$pwd_require_special = (int) SettingsHelper::get('password_require_special', 1);

// Complete list of Philippine Provinces
$provinces = [
    'Abra', 'Agusan del Norte', 'Agusan del Sur', 'Aklan', 'Albay', 'Antique', 'Apayao', 'Aurora',
    'Basilan', 'Bataan', 'Batanes', 'Batangas', 'Benguet', 'Biliran', 'Bohol', 'Bukidnon', 'Bulacan',
    'Cagayan', 'Camarines Norte', 'Camarines Sur', 'Camiguin', 'Capiz', 'Catanduanes', 'Cavite', 'Cebu',
    'Cotabato', 'Davao de Oro', 'Davao del Norte', 'Davao del Sur', 'Davao Occidental', 'Davao Oriental',
    'Dinagat Islands', 'Eastern Samar', 'Guimaras', 'Ifugao', 'Ilocos Norte', 'Ilocos Sur', 'Iloilo',
    'Isabela', 'Kalinga', 'La Union', 'Laguna', 'Lanao del Norte', 'Lanao del Sur', 'Leyte', 'Maguindanao',
    'Marinduque', 'Masbate', 'Metro Manila', 'Misamis Occidental', 'Misamis Oriental', 'Mountain Province',
    'Negros Occidental', 'Negros Oriental', 'Northern Samar', 'Nueva Ecija', 'Nueva Vizcaya',
    'Occidental Mindoro', 'Oriental Mindoro', 'Palawan', 'Pampanga', 'Pangasinan', 'Quezon', 'Quirino',
    'Rizal', 'Romblon', 'Samar', 'Sarangani', 'Siquijor', 'Sorsogon', 'South Cotabato', 'Southern Leyte',
    'Sultan Kudarat', 'Sulu', 'Surigao del Norte', 'Surigao del Sur', 'Tarlac', 'Tawi-Tawi', 'Zambales',
    'Zamboanga del Norte', 'Zamboanga del Sur', 'Zamboanga Sibugay'
];
sort($provinces);

$municipalities = [
    'Abra' => ['Bangued', 'Boliney', 'Bucay', 'Bucloc', 'Daguioman', 'Danglas', 'Dolores', 'La Paz', 'Lacub', 'Lagangilang', 'Lagayan', 'Langiden', 'Licuan-Baay', 'Luba', 'Malibcong', 'Manabo', 'Peñarrubia', 'Pidigan', 'Pilar', 'Sallapadan', 'San Isidro', 'San Juan', 'San Quintin', 'Tayum', 'Tineg', 'Tubo', 'Villaviciosa'],
    'Agusan del Norte' => ['Buenavista', 'Butuan', 'Cabadbaran', 'Carmen', 'Jabonga', 'Kitcharao', 'Las Nieves', 'Magallanes', 'Nasipit', 'Remedios T. Romualdez', 'Santiago', 'Tubay'],
    'Agusan del Sur' => ['Bayugan', 'Bunawan', 'Esperanza', 'La Paz', 'Loreto', 'Prosperidad', 'Rosario', 'San Francisco', 'San Luis', 'Santa Josefa', 'Sibagat', 'Talacogon', 'Trento', 'Veruela'],
    'Aklan' => ['Altavas', 'Balete', 'Banga', 'Batan', 'Buruanga', 'Ibajay', 'Kalibo', 'Lezo', 'Libacao', 'Madalag', 'Makato', 'Malay', 'Malinao', 'Nabas', 'New Washington', 'Numancia', 'Tangalan'],
    'Albay' => ['Bacacay', 'Camalig', 'Daraga', 'Guinobatan', 'Jovellar', 'Legazpi', 'Libon', 'Ligao', 'Malilipot', 'Malinao', 'Manito', 'Oas', 'Pio Duran', 'Polangui', 'Rapu-Rapu', 'Santo Domingo', 'Tabaco', 'Tiwi'],
    'Antique' => ['Anini-y', 'Barbaza', 'Belison', 'Bugasong', 'Caluya', 'Culasi', 'Hamtic', 'Laua-an', 'Libertad', 'Pandan', 'Patnongon', 'San Jose', 'San Remigio', 'Sebaste', 'Sibalom', 'Tibiao', 'Tobias Fornier', 'Valderrama'],
    'Apayao' => ['Calanasan', 'Conner', 'Flora', 'Kabugao', 'Luna', 'Pudtol', 'Santa Marcela'],
    'Aurora' => ['Baler', 'Casiguran', 'Dilasag', 'Dinalungan', 'Dingalan', 'Dipaculao', 'Maria Aurora', 'San Luis'],
    'Basilan' => ['Akbar', 'Al-Barka', 'Hadji Mohammad Ajul', 'Hadji Muhtamad', 'Isabela', 'Lamitan', 'Lantawan', 'Maluso', 'Sumisip', 'Tabuan-Lasa', 'Tipo-Tipo', 'Tuburan', 'Ungkaya Pukan'],
    'Bataan' => ['Abucay', 'Bagac', 'Balanga', 'Dinalupihan', 'Hermosa', 'Limay', 'Mariveles', 'Morong', 'Orani', 'Orion', 'Pilar', 'Samal'],
    'Batanes' => ['Basco', 'Itbayat', 'Ivana', 'Mahatao', 'Sabtang', 'Uyugan'],
    'Batangas' => ['Agoncillo', 'Alitagtag', 'Balayan', 'Balete', 'Batangas City', 'Bauan', 'Calaca', 'Calatagan', 'Cuenca', 'Ibaan', 'Laurel', 'Lemery', 'Lian', 'Lipa', 'Lobo', 'Mabini', 'Malvar', 'Mataasnakahoy', 'Nasugbu', 'Padre Garcia', 'Rosario', 'San Jose', 'San Juan', 'San Luis', 'San Nicolas', 'San Pascual', 'Santa Teresita', 'Santo Tomas', 'Taal', 'Tanauan', 'Taysan', 'Tingloy', 'Tuy'],
    'Benguet' => ['Atok', 'Baguio', 'Bakun', 'Bokod', 'Buguias', 'Itogon', 'Kabayan', 'Kapangan', 'Kibungan', 'La Trinidad', 'Mankayan', 'Sablan', 'Tuba', 'Tublay'],
    'Biliran' => ['Almeria', 'Biliran', 'Cabucgayan', 'Caibiran', 'Culaba', 'Kawayan', 'Maripipi', 'Naval'],
    'Bohol' => ['Alburquerque', 'Alicia', 'Anda', 'Antequera', 'Baclayon', 'Balilihan', 'Batuan', 'Bien Unido', 'Bilar', 'Buenavista', 'Calape', 'Candijay', 'Carmen', 'Catigbian', 'Clarin', 'Corella', 'Cortes', 'Dagohoy', 'Danao', 'Dauis', 'Dimiao', 'Duero', 'Garcia Hernandez', 'Getafe', 'Guindulman', 'Inabanga', 'Jagna', 'Lila', 'Loay', 'Loboc', 'Loon', 'Mabini', 'Maribojoc', 'Panglao', 'Pilar', 'President Carlos P. Garcia', 'Sagbayan', 'San Isidro', 'San Miguel', 'San Jose', 'Sierra Bullones', 'Sikatuna', 'Tagbilaran', 'Talibon', 'Trinidad', 'Tubigon', 'Ubay', 'Valencia'],
    'Bukidnon' => ['Baungon', 'Cabanglasan', 'Damulog', 'Dangcagan', 'Don Carlos', 'Impasugong', 'Kadingilan', 'Kalilangan', 'Kibawe', 'Kitaotao', 'Lantapan', 'Libona', 'Malaybalay', 'Malitbog', 'Manolo Fortich', 'Maramag', 'Pangantucan', 'Quezon', 'San Fernando', 'Sumilao', 'Talakag', 'Valencia'],
    'Bulacan' => ['Angat', 'Balagtas', 'Baliuag', 'Bocaue', 'Bulakan', 'Bustos', 'Calumpit', 'Doña Remedios Trinidad', 'Guiguinto', 'Hagonoy', 'Malolos', 'Marilao', 'Meycauayan', 'Norzagaray', 'Obando', 'Pandi', 'Paombong', 'Plaridel', 'Pulilan', 'San Ildefonso', 'San Jose Del Monte', 'San Miguel', 'San Rafael', 'Santa Maria'],
    'Cagayan' => ['Abulug', 'Alcala', 'Allacapan', 'Amulung', 'Aparri', 'Baggao', 'Ballesteros', 'Buguey', 'Calayan', 'Camalaniugan', 'Claveria', 'Enrile', 'Gattaran', 'Gonzaga', 'Iguig', 'Lal-lo', 'Lasam', 'Pamplona', 'Peñablanca', 'Piat', 'Rizal', 'Sanchez-Mira', 'Santa Ana', 'Santa Praxedes', 'Santa Teresita', 'Santo Niño', 'Solana', 'Tuao', 'Tuguegarao'],
    'Camarines Norte' => ['Basud', 'Capalonga', 'Daet', 'Jose Panganiban', 'Labo', 'Mercedes', 'Paracale', 'San Lorenzo Ruiz', 'San Vicente', 'Santa Elena', 'Talobatib', 'Vinzons'],
    'Camarines Sur' => ['Baao', 'Balatan', 'Bato', 'Bombon', 'Buhi', 'Bula', 'Cabusao', 'Calabanga', 'Camaligan', 'Canaman', 'Caramoan', 'Del Gallego', 'Gainza', 'Garchitorena', 'Goa', 'Iriga', 'Lagonoy', 'Libmanan', 'Lupi', 'Magarao', 'Milaor', 'Minalabac', 'Nabua', 'Naga', 'Ocampo', 'Pamplona', 'Pasacao', 'Pili', 'Presentacion', 'Ragay', 'Sagñay', 'San Fernando', 'San Jose', 'Sipocot', 'Siruma', 'Tigaon', 'Tinambac'],
    'Capiz' => ['Cuartero', 'Dao', 'Dumalag', 'Dumarao', 'Ivisan', 'Jamindan', 'Maayon', 'Mambusao', 'Panay', 'Panitan', 'Pilar', 'Pontevedra', 'President Roxas', 'Roxas', 'Sapian', 'Sigma', 'Tapaz'],
    'Catanduanes' => ['Bagamanoc', 'Baras', 'Bato', 'Caramoran', 'Gigmoto', 'Pandan', 'Panganiban', 'San Andres', 'San Miguel', 'Viga', 'Virac'],
    'Cavite' => ['Alfonso', 'Amadeo', 'Bacoor', 'Carmona', 'Cavite City', 'Dasmariñas', 'General Emilio Aguinaldo', 'General Mariano Alvarez', 'General Trias', 'Imus', 'Indang', 'Kawit', 'Magallanes', 'Maragondon', 'Mendez', 'Naic', 'Noveleta', 'Rosario', 'Silang', 'Tagaytay', 'Tanza', 'Ternate', 'Trece Martires'],
    'Cebu' => ['Alcantara', 'Alcoy', 'Alegria', 'Aloguinsan', 'Argao', 'Asturias', 'Badian', 'Balamban', 'Bantayan', 'Barili', 'Bogo', 'Boljoon', 'Borbon', 'Carcar', 'Carmen', 'Catmon', 'Cebu City', 'Compostela', 'Consolacion', 'Cordova', 'Daanbantayan', 'Dalaguete', 'Danao', 'Dumanjug', 'Ginatilan', 'Lapu-Lapu', 'Liloan', 'Madridejos', 'Malabuyoc', 'Mandaue', 'Medellin', 'Minglanilla', 'Moalboal', 'Naga', 'Oslob', 'Pilar', 'Pinamungajan', 'Poro', 'Ronda', 'Samboan', 'San Fernando', 'San Francisco', 'San Remigio', 'Santa Fe', 'Santander', 'Sibonga', 'Sogod', 'Tabogon', 'Tabuelan', 'Talisay', 'Toledo', 'Tuburan', 'Tudela'],
    'Cotabato' => ['Alamada', 'Aleosan', 'Antipas', 'Arakan', 'Banisilan', 'Carmen', 'Kabacan', 'Kidapawan', 'Libungan', 'Magpet', 'Makilala', 'Matalam', 'Midsayap', 'Mlang', 'Pigcawayan', 'Pikit', 'President Roxas', 'Tulunan'],
    'Davao de Oro' => ['Compostela', 'Laak', 'Mabini', 'Maco', 'Maragusan', 'Mawab', 'Monkayo', 'Montevista', 'Nabunturan', 'New Bataan', 'Pantukan'],
    'Davao del Norte' => ['Asuncion', 'Braulio E. Dujali', 'Carmen', 'Kapalong', 'New Corella', 'Panabo', 'Samal', 'San Isidro', 'Santo Tomas', 'Tagum', 'Talaingod'],
    'Davao del Sur' => ['Bansalan', 'Davao City', 'Digos', 'Hagonoy', 'Kiblawan', 'Magsaysay', 'Malalag', 'Matanao', 'Padada', 'Santa Cruz', 'Sulop'],
    'Davao Occidental' => ['Don Marcelino', 'Jose Abad Santos', 'Malita', 'Santa Maria', 'Sarangani'],
    'Davao Oriental' => ['Baganga', 'Banaybanay', 'Boston', 'Caraga', 'Cateel', 'Governor Generoso', 'Lupon', 'Manay', 'Mati', 'San Isidro', 'Tarragona'],
    'Dinagat Islands' => ['Basilisa', 'Cagdianao', 'Dinagat', 'Libjo', 'Loreto', 'San Jose', 'Tubajon'],
    'Eastern Samar' => ['Arteche', 'Balangiga', 'Balangkayan', 'Borongan', 'Can-avid', 'Dolores', 'General MacArthur', 'Giporlos', 'Guiuan', 'Hernani', 'Jipapad', 'Lawaan', 'Llorente', 'Maydolong', 'Mercedes', 'Oras', 'Quinapondan', 'Salcedo', 'San Julian', 'San Policarpo', 'Sulat', 'Taft'],
    'Guimaras' => ['Buenavista', 'Jordan', 'Nueva Valencia', 'San Lorenzo', 'Sibunag'],
    'Ifugao' => ['Aguinaldo', 'Alfonso Lista', 'Asipulo', 'Banaue', 'Hingyon', 'Hungduan', 'Kiangan', 'Lagawe', 'Lamut', 'Mayoyao', 'Tinoc'],
    'Ilocos Norte' => ['Adams', 'Bacarra', 'Badoc', 'Bangui', 'Banna', 'Batac', 'Burgos', 'Carasi', 'Currimao', 'Dingras', 'Dumalneg', 'Laoag', 'Marcos', 'Nueva Era', 'Pagudpud', 'Paoay', 'Pasuquin', 'Piddig', 'Pinili', 'San Nicolas', 'Sarrat', 'Solsona', 'Vintar'],
    'Ilocos Sur' => ['Alilem', 'Banayoyo', 'Bantay', 'Burgos', 'Cabugao', 'Candon', 'Caoayan', 'Cervantes', 'Galimuyod', 'Gregorio del Pilar', 'Lidlidda', 'Magsingal', 'Nagbukel', 'Narvacan', 'Quirino', 'Salcedo', 'San Emilio', 'San Esteban', 'San Ildefonso', 'San Juan', 'San Vicente', 'Santa', 'Santa Catalina', 'Santa Cruz', 'Santa Lucia', 'Santa Maria', 'Santiago', 'Santo Domingo', 'Sigay', 'Sinait', 'Sugpon', 'Suyo', 'Tagudin', 'Vigan'],
    'Iloilo' => ['Ajuy', 'Alimodian', 'Anilao', 'Badiangan', 'Balasan', 'Banate', 'Barotac Nuevo', 'Barotac Viejo', 'Batad', 'Bingawan', 'Cabatuan', 'Calinog', 'Carles', 'Concepcion', 'Dingle', 'Dueñas', 'Dumangas', 'Estancia', 'Guimbal', 'Igbaras', 'Iloilo City', 'Janiuay', 'Lambunao', 'Leganes', 'Lemery', 'Leon', 'Maasin', 'Miagao', 'Mina', 'New Lucena', 'Oton', 'Passi', 'Pavia', 'Pototan', 'San Dionisio', 'San Enrique', 'San Joaquin', 'San Miguel', 'San Rafael', 'Santa Barbara', 'Sara', 'Tigbauan', 'Tubungan', 'Zarraga'],
    'Isabela' => ['Alicia', 'Angadanan', 'Aurora', 'Benito Soliven', 'Burgos', 'Cabagan', 'Cabatuan', 'Cauayan', 'Cordon', 'Delfin Albano', 'Dinapigue', 'Divilacan', 'Echague', 'Gamu', 'Ilagan', 'Jones', 'Luna', 'Maconacon', 'Mallig', 'Naguilian', 'Palanan', 'Quezon', 'Quirino', 'Ramon', 'Reina Mercedes', 'Roxas', 'San Agustin', 'San Guillermo', 'San Isidro', 'San Manuel', 'San Mariano', 'San Mateo', 'San Pablo', 'Santa Maria', 'Santiago', 'Santo Tomas', 'Tumauini'],
    'Kalinga' => ['Balbalan', 'Lubuagan', 'Pasil', 'Pinukpuk', 'Rizal', 'Tabuk', 'Tanudan', 'Tinglayan'],
    'La Union' => ['Agoo', 'Aringay', 'Bacnotan', 'Bagulin', 'Balaoan', 'Bangar', 'Bauang', 'Burgos', 'Caba', 'Luna', 'Naguilian', 'Pugo', 'Rosario', 'San Fernando', 'San Gabriel', 'San Juan', 'Santo Tomas', 'Santol', 'Sudipen', 'Tubao'],
    'Laguna' => ['Alaminos', 'Bay', 'Biñan', 'Cabuyao', 'Calamba', 'Calauan', 'Cavinti', 'Famy', 'Kalayaan', 'Liliw', 'Los Baños', 'Luisiana', 'Lumban', 'Mabitac', 'Magdalena', 'Majayjay', 'Nagcarlan', 'Paete', 'Pagsanjan', 'Pakil', 'Pangil', 'Pila', 'Rizal', 'San Pablo', 'San Pedro', 'Santa Cruz', 'Santa Maria', 'Santa Rosa', 'Siniloan', 'Victoria'],
    'Lanao del Norte' => ['Bacolod', 'Baloi', 'Baroy', 'Iligan', 'Kapatagan', 'Kauswagan', 'Kolambugan', 'Lala', 'Linamon', 'Magsaysay', 'Maigo', 'Matungao', 'Munai', 'Nunungan', 'Pantao Ragat', 'Pantar', 'Poona Piagapo', 'Salvador', 'Sapad', 'Sultan Naga Dimaporo', 'Tagoloan', 'Tangcal', 'Tubod'],
    'Lanao del Sur' => ['Amai Manabilang', 'Bacolod-Kalawi', 'Balabagan', 'Balindong', 'Bayang', 'Binidayan', 'Buadiposo-Buntong', 'Bubong', 'Butig', 'Calanogas', 'Ditsaan-Ramain', 'Ganassi', 'Kapai', 'Kapatagan', 'Lumba-Bayabao', 'Lumbatan', 'Lumbayanague', 'Madalum', 'Madamba', 'Maguing', 'Malabang', 'Marantao', 'Marawi', 'Masiu', 'Mulondo', 'Pagayawan', 'Piagapo', 'Poona Bayabao', 'Pualas', 'Saguiaran', 'Sultan Dumalondong', 'Tagoloan', 'Tamparan', 'Taraka', 'Tubaran', 'Tugaya', 'Wao'],
    'Leyte' => ['Abuyog', 'Alangalang', 'Albuera', 'Babatngon', 'Barugo', 'Bato', 'Baybay', 'Burauen', 'Calubian', 'Capoocan', 'Carigara', 'Dagami', 'Dulag', 'Hilongos', 'Hindang', 'Inopacan', 'Isabel', 'Jaro', 'Javier', 'Julita', 'Kananga', 'La Paz', 'Leyte', 'MacArthur', 'Mahaplag', 'Matag-ob', 'Matalom', 'Mayorga', 'Merida', 'Ormoc', 'Palo', 'Palompon', 'Pastrana', 'San Isidro', 'San Miguel', 'Santa Fe', 'Tabango', 'Tabontabon', 'Tacloban', 'Tanauan', 'Tolosa', 'Tunga', 'Villaba'],
    'Maguindanao del Norte' => ['Barira', 'Buldon', 'Datu Blah T. Sinsuat', 'Datu Odin Sinsuat', 'Kabuntalan', 'Matanog', 'Northern Kabuntalan', 'Parang', 'Sultan Kudarat', 'Sultan Mastura', 'Talitay', 'Upi'],
    'Maguindanao del Sur' => ['Ampatuan', 'Buluan', 'Datu Abdullah Sangki', 'Datu Anggal Midtimbang', 'Datu Hoffer Ampatuan', 'Datu Montawal', 'Datu Paglas', 'Datu Piang', 'Datu Salibo', 'Datu Saudi-Ampatuan', 'Datu Unsay', 'General Salipada K. Pendatun', 'Guindulungan', 'Mamasapano', 'Mangudadatu', 'Pagalungan', 'Paglat', 'Pandag', 'Rajah Buayan', 'Shariff Aguak', 'Shariff Saydona Mustapha', 'South Upi', 'Sultan sa Barongis', 'Talayan'],
    'Marinduque' => ['Boac', 'Buenavista', 'Gasan', 'Mogpog', 'Santa Cruz', 'Torrijos'],
    'Masbate' => ['Aroroy', 'Baleno', 'Balud', 'Batuan', 'Cataingan', 'Cawayan', 'Claveria', 'Dimasalang', 'Esperanza', 'Mandaon', 'Masbate City', 'Milagros', 'Mobo', 'Monreal', 'Palanas', 'Pio V. Corpuz', 'Placer', 'San Fernando', 'San Jacinto', 'San Pascual', 'Uson'],
    'Metro Manila' => ['Caloocan', 'Las Piñas', 'Makati', 'Malabon', 'Mandaluyong', 'Manila', 'Marikina', 'Muntinlupa', 'Navotas', 'Parañaque', 'Pasay', 'Pasig', 'Pateros', 'Quezon City', 'San Juan', 'Taguig', 'Valenzuela'],
    'Mountain Province' => ['Barlig', 'Bauko', 'Besao', 'Bontoc', 'Natonin', 'Paracelis', 'Sabangan', 'Sadanga', 'Sagada', 'Tadian'],
    'Negros Occidental' => ['Bacolod', 'Bago', 'Binalbagan', 'Calatrava', 'Candoni', 'Cauayan', 'Enrique B. Magalona', 'Escalante', 'Himamaylan', 'Hinigaran', 'Hinoba-an', 'Ilog', 'Isabela', 'Kabankalan', 'La Carlota', 'La Castellana', 'Manapla', 'Moises Padilla', 'Murcia', 'Pontevedra', 'Pulupandan', 'Sagay', 'Salvador Benedicto', 'San Carlos', 'San Enrique', 'Silay', 'Sipalay', 'Talisay', 'Toboso', 'Valladolid', 'Victorias'],
    'Negros Oriental' => ['Amlan', 'Ayungon', 'Bacong', 'Bais', 'Basay', 'Bayawan', 'Bindoy', 'Canlaon', 'Dauin', 'Dumaguete', 'Guihulngan', 'Jimalalud', 'La Libertad', 'Mabinay', 'Manjuyod', 'Pamplona', 'San Jose', 'Santa Catalina', 'Siaton', 'Sibulan', 'Tanjay', 'Tayasan', 'Valencia', 'Zamboanguita'],
    'Northern Samar' => ['Allen', 'Biri', 'Bobon', 'Capul', 'Catarman', 'Catubig', 'Gamay', 'Laoang', 'Lapinig', 'Las Navas', 'Lavezares', 'Mapanas', 'Mondragon', 'Palapag', 'Pambujan', 'Rosario', 'San Antonio', 'San Isidro', 'San Jose', 'San Roque', 'San Vicente', 'Silvino Lobos', 'Victoria'],
    'Nueva Ecija' => ['Aliaga', 'Bongabon', 'Cabanatuan', 'Cabiao', 'Carranglan', 'Cuyapo', 'Gabaldon', 'Gapan', 'General Mamerto Natividad', 'General Tinio', 'Guimba', 'Jaen', 'Laur', 'Licab', 'Llanera', 'Lupao', 'Muñoz', 'Nampicuan', 'Palayan', 'Pantabangan', 'Peñaranda', 'Quezon', 'Rizal', 'San Antonio', 'San Isidro', 'San Jose', 'San Leonardo', 'Santa Rosa', 'Santo Domingo', 'Talavera', 'Talugtug', 'Zaragoza'],
    'Nueva Vizcaya' => ['Alfonso Castañeda', 'Ambaguio', 'Aritao', 'Bagabag', 'Bambang', 'Bayombong', 'Diadi', 'Dupax del Norte', 'Dupax del Sur', 'Kasibu', 'Kayapa', 'Quezon', 'Santa Fe', 'Solano', 'Villaverde'],
    'Occidental Mindoro' => ['Abra de Ilog', 'Calintaan', 'Looc', 'Lubang', 'Magsaysay', 'Mamburao', 'Paluan', 'Rizal', 'Sablayan', 'San Jose', 'Santa Cruz'],
    'Oriental Mindoro' => ['Baco', 'Bansud', 'Bongabong', 'Bulalacao', 'Calapan', 'Gloria', 'Mansalay', 'Naujan', 'Pinamalayan', 'Pola', 'Puerto Galera', 'Roxas', 'San Teodoro', 'Socorro', 'Victoria'],
    'Palawan' => ['Aborlan', 'Agutaya', 'Araceli', 'Balabac', 'Bataraza', 'Brooke\'s Point', 'Busuanga', 'Cagayancillo', 'Coron', 'Culion', 'Cuyo', 'Dumaran', 'El Nido', 'Kalayaan', 'Linapacan', 'Magsaysay', 'Narra', 'Puerto Princesa', 'Quezon', 'Rizal', 'Roxas', 'San Vicente', 'Sofronio Española', 'Taytay'],
    'Pampanga' => ['Angeles', 'Apalit', 'Arayat', 'Bacolor', 'Candaba', 'Floridablanca', 'Guagua', 'Lubao', 'Mabalacat', 'Macabebe', 'Magalang', 'Masantol', 'Mexico', 'Minalin', 'Porac', 'San Fernando', 'San Luis', 'San Simon', 'Santa Ana', 'Santa Rita', 'Santo Tomas', 'Sasmuan'],
    'Pangasinan' => ['Agno', 'Aguilar', 'Alaminos', 'Alcala', 'Anda', 'Asingan', 'Balungao', 'Bani', 'Basista', 'Bautista', 'Bayambang', 'Binalonan', 'Binmaley', 'Bolinao', 'Bugallon', 'Burgos', 'Calasiao', 'Dasol', 'Dagupan', 'Infanta', 'Labrador', 'Laoac', 'Lingayen', 'Mabini', 'Malasiqui', 'Manaoag', 'Mangaldan', 'Mangatarem', 'Mapandan', 'Natividad', 'Pozorrubio', 'Rosales', 'San Carlos', 'San Fabian', 'San Jacinto', 'San Manuel', 'San Nicolas', 'San Quintin', 'Santa Barbara', 'Santa Maria', 'Santo Tomas', 'Sison', 'Sual', 'Tayug', 'Umingan', 'Urbiztondo', 'Urdaneta', 'Villasis'],
    'Quezon' => ['Agdangan', 'Alabat', 'Atimonan', 'Buenavista', 'Burdeos', 'Calauag', 'Candelaria', 'Catanauan', 'Dolores', 'General Luna', 'General Nakar', 'Guinayangan', 'Gumaca', 'Infanta', 'Jomalig', 'Lopez', 'Lucban', 'Lucena', 'Macalelon', 'Mauban', 'Mulanay', 'Padre Burgos', 'Pagbilao', 'Panukulan', 'Patnanungan', 'Perez', 'Pitogo', 'Plaridel', 'Polillo', 'Quezon', 'Real', 'Sampaloc', 'San Andres', 'San Antonio', 'San Francisco', 'San Narciso', 'Sariaya', 'Tagkawayan', 'Tayabas', 'Tiaong', 'Unisan'],
    'Quirino' => ['Aglipay', 'Cabarroguis', 'Diffun', 'Maddela', 'Nagtipunan', 'Saguday'],
    'Rizal' => ['Angono', 'Antipolo', 'Baras', 'Binangonan', 'Cainta', 'Cardona', 'Jalajala', 'Morong', 'Pililla', 'Rodriguez', 'San Mateo', 'Tanay', 'Taytay', 'Teresa'],
    'Romblon' => ['Alcantara', 'Banton', 'Cajidiocan', 'Calatrava', 'Concepcion', 'Corcuera', 'Ferrol', 'Looc', 'Magdiwang', 'Odiongan', 'Romblon', 'San Agustin', 'San Andres', 'San Fernando', 'San Jose', 'Santa Fe', 'Santa Maria'],
    'Samar' => ['Almagro', 'Basey', 'Calbayog', 'Calbiga', 'Catbalogan', 'Daram', 'Gandara', 'Hinabangan', 'Jiabong', 'Marabut', 'Matuguinao', 'Motiong', 'Pagsanghan', 'Paranas', 'Pinabacdao', 'San Jorge', 'San Jose de Buan', 'San Sebastian', 'Santa Margarita', 'Santa Rita', 'Santo Niño', 'Tagapul-an', 'Talalora', 'Tarangnan', 'Villareal', 'Zumarraga'],
    'Sarangani' => ['Alabel', 'Glan', 'Kiamba', 'Maasim', 'Maitum', 'Malapatan', 'Malungon'],
    'Siquijor' => ['Enrique Villanueva', 'Larena', 'Lazi', 'Maria', 'San Juan', 'Siquijor'],
    'Sorsogon' => ['Barcelona', 'Bulan', 'Bulusan', 'Casiguran', 'Castilla', 'Donsol', 'Gubat', 'Irosin', 'Juban', 'Magallanes', 'Matnog', 'Pilar', 'Prieto Diaz', 'Santa Magdalena', 'Sorsogon City'],
    'South Cotabato' => ['Banga', 'General Santos', 'Koronadal', 'Lake Sebu', 'Norala', 'Polomolok', 'Santo Niño', 'Surallah', 'Tampakan', 'Tantangan', 'T\'boli', 'Tupi'],
    'Southern Leyte' => ['Anahawan', 'Bontoc', 'Hinunangan', 'Hinundayan', 'Libagon', 'Liloan', 'Limasawa', 'Maasin', 'Macrohon', 'Malitbog', 'Padre Burgos', 'Pintuyan', 'Saint Bernard', 'San Francisco', 'San Juan', 'San Ricardo', 'Silago', 'Sogod', 'Tomas Oppus'],
    'Sultan Kudarat' => ['Bagumbayan', 'Columbio', 'Esperanza', 'Isulan', 'Kalamansig', 'Lambayong', 'Lebak', 'Lutayan', 'Palimbang', 'President Quirino', 'Senator Ninoy Aquino', 'Tacurong'],
    'Sulu' => ['Banguingui', 'Hadji Panglima Tahil', 'Indanan', 'Jolo', 'Kalingalan Caluang', 'Lugus', 'Luuk', 'Maimbung', 'Old Panamao', 'Omar', 'Pandami', 'Panglima Estino', 'Pangutaran', 'Parang', 'Pata', 'Patikul', 'Siasi', 'Talipao', 'Tapul', 'Tongkil'],
    'Surigao del Norte' => ['Alegria', 'Bacuag', 'Basilisa', 'Burgos', 'Cagdianao', 'Claver', 'Dapa', 'Del Carmen', 'Dinagat', 'General Luna', 'Gigaquit', 'Libjo', 'Loreto', 'Mainit', 'Malimono', 'Pilar', 'Placer', 'San Benito', 'San Francisco', 'San Isidro', 'San Jose', 'Santa Monica', 'Sison', 'Socorro', 'Surigao', 'Tagana-an', 'Tubajon', 'Tubod'],
    'Surigao del Sur' => ['Barobo', 'Bayabas', 'Bislig', 'Cagwait', 'Cantilan', 'Carmen', 'Carrascal', 'Cortes', 'Hinatuan', 'Lanuza', 'Lianga', 'Lingig', 'Madrid', 'Marihatag', 'San Agustin', 'San Miguel', 'Tagbina', 'Tago', 'Tandag'],
    'Tarlac' => ['Anao', 'Bamban', 'Camiling', 'Capas', 'Concepcion', 'Gerona', 'La Paz', 'Mayantoc', 'Moncada', 'Paniqui', 'Pura', 'Ramos', 'San Clemente', 'San Jose', 'San Manuel', 'Santa Ignacia', 'Tarlac City', 'Victoria'],
    'Tawi-Tawi' => ['Bongao', 'Languyan', 'Mapun', 'Panglima Sugala', 'Sapa-Sapa', 'Sibutu', 'Simunul', 'Sitangkai', 'South Ubian', 'Tandubas', 'Turtle Islands'],
    'Zambales' => ['Botolan', 'Cabangan', 'Candelaria', 'Castillejos', 'Iba', 'Masinloc', 'Olongapo', 'Palauig', 'San Antonio', 'San Felipe', 'San Marcelino', 'San Narciso', 'Santa Cruz', 'Subic'],
    'Zamboanga del Norte' => ['Baliguian', 'Dapitan', 'Dipolog', 'Godod', 'Gutalac', 'Jose Dalman', 'Kalawit', 'Katipunan', 'La Libertad', 'Labason', 'Leon B. Postigo', 'Liloy', 'Manukan', 'Mutia', 'Piñan', 'Polanco', 'President Manuel A. Roxas', 'Rizal', 'Salug', 'Sergio Osmeña Sr.', 'Siayan', 'Sibuco', 'Sibutad', 'Sindangan', 'Siocon', 'Sirawai', 'Tampilisan'],
    'Zamboanga del Sur' => ['Aurora', 'Bayog', 'Dimataling', 'Dinas', 'Dumalinao', 'Dumingag', 'Guipos', 'Josefina', 'Kumalarang', 'Labangan', 'Lakewood', 'Lapuyan', 'Mahayag', 'Margosatubig', 'Midsalip', 'Molave', 'Pagadian', 'Pitogo', 'Ramon Magsaysay', 'San Miguel', 'San Pablo', 'Sominot', 'Tabina', 'Tambulig', 'Tigbao', 'Tukuran', 'Vincenzo A. Sagun'],
    'Zamboanga Sibugay' => ['Alicia', 'Buug', 'Diplahan', 'Imelda', 'Ipil', 'Kabasalan', 'Mabuhay', 'Malangas', 'Naga', 'Olutanga', 'Payao', 'Roseller Lim', 'Siay', 'Talusan', 'Titay', 'Tungawan'],
];
// Get municipalities for selected province (AJAX)
if (isset($_GET['action']) && $_GET['action'] === 'get_municipalities' && isset($_GET['province'])) {
    header('Content-Type: application/json');
    $province = $_GET['province'];
    $result = isset($municipalities[$province]) ? $municipalities[$province] : [];
    echo json_encode($result);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Create Account - <?php echo htmlspecialchars($system_name); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        * { font-family: 'Manrope', sans-serif; }
        
        .input-group {
            position: relative;
            margin-bottom: 1rem;
        }
        
        .input-field {
            width: 100%;
            padding: 0.75rem 1rem 0.5rem 2.5rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 0.75rem;
            font-size: 0.875rem;
            transition: all 0.2s;
            background: #ffffff;
            color: #1e293b;
            height: 48px;
        }
        
        .input-field:focus {
            border-color: #10A37F;
            box-shadow: 0 0 0 3px rgba(16, 163, 127, 0.1);
            outline: none;
        }
        
        .input-field.error {
            border-color: #ef4444;
        }
        
        .input-field.valid {
            border-color: #10A37F;
        }
        
        .input-icon {
            position: absolute;
            left: 0.875rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.875rem;
            pointer-events: none;
            z-index: 2;
        }
        
        .floating-label {
            position: absolute;
            left: 2.5rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.875rem;
            pointer-events: none;
            transition: all 0.2s ease;
            background: transparent;
            padding: 0 0.25rem;
        }
        
        .input-field:focus ~ .floating-label,
        .input-field:not(:placeholder-shown) ~ .floating-label {
            top: 0;
            transform: translateY(-50%);
            font-size: 0.65rem;
            color: #10A37F;
            background: white;
            padding: 0 0.25rem;
        }
        
        .input-field:focus ~ .input-icon,
        .input-field:not(:placeholder-shown) ~ .input-icon {
            top: 1rem;
            transform: translateY(0);
            font-size: 0.75rem;
        }
        
        /* SELECT2 FLOATING LABEL FIX */
        .select2-container {
            width: 100% !important;
        }
        
        .select2-container .select2-selection--single {
            height: 48px !important;
            border: 1.5px solid #e2e8f0 !important;
            border-radius: 0.75rem !important;
            padding: 0.5rem 2.5rem 0.5rem 1rem !important;
            font-size: 0.875rem !important;
            display: flex !important;
            align-items: center !important;
            background: #ffffff !important;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #1e293b !important;
            line-height: 30px !important;
            padding-left: 2.5rem !important;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__placeholder {
            color: #94a3b8 !important;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 44px !important;
            right: 10px !important;
        }
        
        .select2-container.select2-container--open .select2-selection--single {
            border-color: #10A37F !important;
            box-shadow: 0 0 0 3px rgba(16, 163, 127, 0.1) !important;
        }
        
        .select2-container--default.select2-container--focus .select2-selection--single {
            border-color: #10A37F !important;
            box-shadow: 0 0 0 3px rgba(16, 163, 127, 0.1) !important;
        }
        
        .select2-with-icon {
            position: relative;
            width: 100%;
        }
        
        .select2-with-icon .select2-icon {
            position: absolute;
            left: 0.875rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.875rem;
            z-index: 10;
            pointer-events: none;
        }
        
        .select2-with-icon .select2-label {
            position: absolute;
            left: 2.5rem;
            top: 0.75rem;
            color: #94a3b8;
            font-size: 0.875rem;
            pointer-events: none;
            transition: all 0.2s ease;
            background: transparent;
            padding: 0 0.25rem;
            z-index: 10;
        }
        
        .select2-with-icon.select2-active .select2-label,
        .select2-with-icon.has-value .select2-label {
            top: -0.5rem;
            font-size: 0.65rem;
            color: #10A37F;
            background: white;
        }
        
        .select2-dropdown {
            border: 1.5px solid #e2e8f0 !important;
            border-radius: 0.75rem !important;
            overflow: hidden !important;
        }
        
        .select2-search--dropdown .select2-search__field {
            border: 1.5px solid #e2e8f0 !important;
            border-radius: 0.5rem !important;
            padding: 0.5rem 1rem !important;
            font-size: 0.875rem !important;
            font-family: 'Manrope', sans-serif !important;
        }
        
        .select2-search--dropdown .select2-search__field:focus {
            border-color: #10A37F !important;
            box-shadow: 0 0 0 3px rgba(16, 163, 127, 0.1) !important;
            outline: none !important;
        }
        
        .select2-results__option {
            padding: 0.5rem 1rem !important;
            font-size: 0.875rem !important;
            font-family: 'Manrope', sans-serif !important;
        }
        
        .select2-results__option--highlighted {
            background: #10A37F !important;
            color: white !important;
        }
        
        .select2-container--default .select2-results__option[aria-selected="true"] {
            background: #ecfdf5 !important;
            color: #10A37F !important;
        }
        
        .password-toggle {
            position: absolute;
            right: 0.875rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            cursor: pointer;
            background: none;
            border: none;
            font-size: 0.875rem;
            z-index: 3;
        }
        
        .password-toggle:hover {
            color: #10A37F;
        }
        
        /* STEP INDICATOR */
        .step-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
        }
        
        .step-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .step-dot.active {
            background: #10A37F;
            transform: scale(1.2);
            box-shadow: 0 0 0 4px rgba(16, 163, 127, 0.2);
        }
        
        .step-dot.completed {
            background: #10A37F;
        }
        
        .step-line {
            width: 40px;
            height: 2px;
            background: #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .step-line.completed {
            background: #10A37F;
        }
        
        .step-label {
            font-size: 0.6rem;
            font-weight: 600;
            color: #94a3b8;
            text-align: center;
            margin-top: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .step-label.active {
            color: #10A37F;
        }
        
        .step-container {
            display: none;
            animation: fadeIn 0.3s ease-out;
        }
        
        .step-container.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* RESIDENT SELECTOR */
        .resident-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin: 1rem 0;
        }
        
        .resident-option {
            padding: 1.5rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            background: white;
        }
        
        .resident-option:hover {
            border-color: #10A37F;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 163, 127, 0.1);
        }
        
        .resident-option.selected {
            border-color: #10A37F;
            background: #ecfdf5;
            box-shadow: 0 4px 12px rgba(16, 163, 127, 0.15);
        }
        
        .resident-option i {
            font-size: 2rem;
            color: #10A37F;
            display: block;
            margin-bottom: 0.5rem;
        }
        
        .resident-option .label {
            font-weight: 600;
            color: #1e293b;
        }
        
        .resident-option .sub-label {
            font-size: 0.75rem;
            color: #94a3b8;
            margin-top: 0.25rem;
        }
        
        /* OTP INPUT */
        .otp-container {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            margin: 1.5rem 0;
        }
        
        .otp-input {
            width: 50px;
            height: 60px;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
            border: 2px solid #e2e8f0;
            border-radius: 0.75rem;
            transition: all 0.2s;
            background: white;
            color: #1e293b;
        }
        
        .otp-input:focus {
            border-color: #10A37F;
            box-shadow: 0 0 0 3px rgba(16, 163, 127, 0.2);
            outline: none;
        }
        
        .otp-input.filled {
            border-color: #10A37F;
            background: #ecfdf5;
        }
        
        .otp-input.error {
            border-color: #ef4444;
            background: #fef2f2;
        }
        
        /* PASSWORD STRENGTH */
        .strength-meter {
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            margin-top: 4px;
            overflow: hidden;
        }
        
        .strength-meter-fill {
            height: 100%;
            border-radius: 2px;
            transition: all 0.3s ease;
            width: 0%;
        }
        
        .strength-text {
            font-size: 0.6875rem;
            margin-top: 2px;
            font-weight: 500;
        }
        
        .requirement {
            transition: all 0.2s ease;
            font-size: 0.6875rem;
        }
        
        .requirement.met { color: #10A37F; }
        .requirement.unmet { color: #94a3b8; }
        
        /* SUCCESS SCREEN */
        .success-screen {
            text-align: center;
            padding: 2rem 1rem;
        }
        
        .success-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #10A37F, #0D8568);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            animation: successPop 0.6s ease-out;
        }
        
        @keyframes successPop {
            0% { transform: scale(0); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .success-icon i {
            font-size: 3rem;
            color: white;
        }
        
        /* BACKGROUND */
        .floating-shape {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            z-index: 0;
            pointer-events: none;
            animation: float 20s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(30px, -30px); }
        }
        
        /* SCROLLBAR */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: #10A37F; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #0D8568; }
        ::-webkit-scrollbar:horizontal { display: none; }
        
        /* RESPONSIVE */
        @media (max-width: 640px) {
            .resident-selector {
                grid-template-columns: 1fr;
            }
            .otp-container {
                gap: 0.5rem;
            }
            .otp-input {
                width: 40px;
                height: 50px;
                font-size: 1.2rem;
            }
            .step-line {
                width: 20px;
            }
            .select2-container .select2-selection--single {
                height: 44px !important;
                padding: 0.3rem 2.5rem 0.3rem 1rem !important;
            }
            .select2-container--default .select2-selection--single .select2-selection__rendered {
                line-height: 28px !important;
                padding-left: 2rem !important;
            }
        }
        
        /* Loading spinner */
        .register-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f8;
            border-top: 2px solid #10A37F;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Duplicate validation error */
        .duplicate-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 0.75rem;
            padding: 1rem;
            margin-bottom: 1rem;
            display: none;
        }
        .duplicate-error.show {
            display: block;
        }
        .duplicate-error i {
            color: #ef4444;
            margin-right: 0.5rem;
        }
        .duplicate-error p {
            color: #dc2626;
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* Brand logo */
        .brand-logo {
            max-height: 60px;
            width: auto;
            object-fit: contain;
        }
        @media (max-width: 640px) {
            .brand-logo {
                max-height: 50px;
            }
        }
    </style>
</head>
<body class="relative min-h-screen" style="background: linear-gradient(135deg, #f0f7f4 0%, #e6f0ec 100%);">
    <div class="floating-shape top-[-100px] right-[-100px] w-[300px] h-[300px] opacity-15" style="background: #10A37F;"></div>
    <div class="floating-shape bottom-[-100px] left-[-100px] w-[350px] h-[350px] opacity-10" style="background: #0D8568; animation-delay: -5s;"></div>
    
    <div class="relative z-10 min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-2xl mx-auto">
            
            <div class="bg-white rounded-2xl shadow-xl" style="background: rgba(255, 255, 255, 0.98); border: 1px solid rgba(0, 0, 0, 0.06);">
                
                <!-- Back Button -->
                <div class="p-4 pb-0">
                    <a href="<?php echo BASE_URL; ?>index.php" class="inline-flex items-center gap-2 text-gray-500 hover:text-[#10A37F] transition-all group">
                        <i class="fas fa-arrow-left text-sm group-hover:-translate-x-1 transition-transform"></i>
                        <span class="text-sm font-medium">Back</span>
                    </a>
                </div>
                
                <div class="p-6 md:p-8 pt-4">
                    
                    <!-- Header -->
                    <div class="text-center mb-6">
                        <?php if ($logo_url): ?>
                            <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="<?php echo htmlspecialchars($system_name); ?> Logo" class="brand-logo mx-auto mb-4">
                        <?php else: ?>
                            <div class="w-14 h-14 rounded-xl flex items-center justify-center shadow-lg mb-4 mx-auto" style="background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%);">
                                <i class="fas fa-leaf text-white text-2xl"></i>
                            </div>
                        <?php endif; ?>
                        <h2 class="text-2xl font-bold text-gray-800">Create Account</h2>
                        <p class="text-gray-500 text-sm mt-1">Join <?php echo htmlspecialchars($system_name); ?> and help keep San Isidro clean</p>
                    </div>
                    
                    <!-- Error Messages -->
                    <?php if (isset($_SESSION['errors'])): ?>
                        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 rounded-lg p-3 text-xs mb-4 space-y-1">
                            <?php foreach ($_SESSION['errors'] as $err): ?>
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-exclamation-circle text-red-500 text-xs"></i>
                                    <span><?php echo htmlspecialchars($err); ?></span>
                                </div>
                            <?php endforeach; unset($_SESSION['errors']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 rounded-lg p-3 text-xs mb-4">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-exclamation-triangle text-red-500 text-xs"></i>
                                <span><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Step Indicator -->
                    <div class="step-indicator">
                        <div class="flex flex-col items-center">
                            <div class="flex items-center">
                                <div class="step-dot active" id="dot1"></div>
                                <div class="step-line" id="line1"></div>
                            </div>
                            <span class="step-label active" id="label1">Account</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <div class="flex items-center">
                                <div class="step-dot" id="dot2"></div>
                                <div class="step-line" id="line2"></div>
                            </div>
                            <span class="step-label" id="label2">Verify</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <div class="flex items-center">
                                <div class="step-dot" id="dot3"></div>
                            </div>
                            <span class="step-label" id="label3">Done</span>
                        </div>
                    </div>
                    
                    <!-- DUPLICATE ERROR DISPLAY -->
                    <div id="duplicateError" class="duplicate-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <p id="duplicateErrorMessage"></p>
                    </div>
                    
                    <!-- ============================================ -->
                    <!-- STEP 1: ACCOUNT INFORMATION -->
                    <!-- ============================================ -->
                    <div class="step-container active" id="step1">
                        <form id="step1Form" onsubmit="return false;">
                            <input type="hidden" name="action" value="register">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            
                            <!-- Personal Information -->
                            <div class="flex items-center gap-2 mb-4 pb-2 border-b border-gray-100">
                                <i class="fas fa-user-circle text-[#10A37F] text-base"></i>
                                <span class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Personal Information</span>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="input-group">
                                    <i class="fas fa-user input-icon"></i>
                                    <input type="text" name="first_name" id="first_name" required class="input-field" placeholder=" " pattern="[a-zA-Z0-9\s\-\.]{2,50}">
                                    <label for="first_name" class="floating-label">First Name <span class="text-red-400">*</span></label>
                                </div>
                                <div class="input-group">
                                    <i class="fas fa-user input-icon"></i>
                                    <input type="text" name="last_name" id="last_name" required class="input-field" placeholder=" " pattern="[a-zA-Z\s\-\.]{2,50}">
                                    <label for="last_name" class="floating-label">Last Name <span class="text-red-400">*</span></label>
                                </div>
                            </div>
                            
                            <!-- Contact Information -->
                            <div class="flex items-center gap-2 mb-4 mt-6 pb-2 border-b border-gray-100">
                                <i class="fas fa-address-card text-[#10A37F] text-base"></i>
                                <span class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Contact Information</span>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="input-group">
                                    <i class="fas fa-phone input-icon"></i>
                                    <input type="tel" name="contact_number" id="contact_number" required class="input-field" placeholder=" " maxlength="11" inputmode="numeric" pattern="09[0-9]{9}">
                                    <label for="contact_number" class="floating-label">Mobile Number <span class="text-red-400">*</span></label>
                                    <span class="text-red-500 text-xs mt-1 hidden" id="phoneError"></span>
                                </div>
                                <div class="input-group">
                                    <i class="fas fa-envelope input-icon"></i>
                                    <input type="email" name="email" id="email" required class="input-field" placeholder=" " autocomplete="email">
                                    <label for="email" class="floating-label">Email Address <span class="text-red-400">*</span></label>
                                    <span class="text-xs mt-1 flex items-center gap-1 text-gray-400 hidden" id="emailChecking"><i class="fas fa-spinner fa-spin"></i> Checking email...</span>
                                    <span class="text-red-500 text-xs mt-1 hidden" id="emailError"></span>
                                    <span class="text-[#10A37F] text-xs mt-1 hidden" id="emailSuccess"><i class="fas fa-check-circle mr-0.5"></i>Email looks good</span>
                                </div>
                            </div>
                            <p class="text-[10px] text-gray-400 mt-[-8px] ml-2 mb-2">Enter 11-digit number starting with 09 (e.g., 09123456789)</p>
                            
                            <!-- Account Security -->
                            <div class="flex items-center gap-2 mb-4 mt-6 pb-2 border-b border-gray-100">
                                <i class="fas fa-lock text-[#10A37F] text-base"></i>
                                <span class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Account Security</span>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <div class="input-group">
                                        <i class="fas fa-lock input-icon"></i>
                                        <input type="password" name="password" id="password" required class="input-field" placeholder=" " autocomplete="new-password" minlength="8" maxlength="16">
                                        <label for="password" class="floating-label">Password <span class="text-red-400">*</span></label>
                                        <button type="button" id="togglePassword" class="password-toggle">
                                            <i class="fas fa-eye-slash"></i>
                                        </button>
                                    </div>
                                    
                                    <div class="strength-meter mt-[-4px] ml-2 mr-2">
                                        <div class="strength-meter-fill" id="strengthFill"></div>
                                    </div>
                                    <div class="strength-text text-[11px] ml-2" id="strengthText"></div>
                                    
                                    <!-- ============================================ -->
                                    <!-- DYNAMIC PASSWORD REQUIREMENTS -->
                                    <!-- ============================================ -->
                                    <div class="password-requirements text-[11px] text-gray-400 mt-2 ml-2 mb-3 space-y-0.5">
                                        <div class="requirement flex items-center gap-1" id="reqLength">
                                            <i class="far fa-circle text-[8px]"></i> 
                                            <span>Between <?php echo $pwd_min; ?> and 16 characters</span>
                                        </div>
                                        <?php if ($pwd_require_upper): ?>
                                        <div class="requirement flex items-center gap-1" id="reqUpper">
                                            <i class="far fa-circle text-[8px]"></i> 
                                            <span>At least 1 uppercase letter (A-Z)</span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($pwd_require_lower): ?>
                                        <div class="requirement flex items-center gap-1" id="reqLower">
                                            <i class="far fa-circle text-[8px]"></i> 
                                            <span>At least 1 lowercase letter (a-z)</span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($pwd_require_number): ?>
                                        <div class="requirement flex items-center gap-1" id="reqNumber">
                                            <i class="far fa-circle text-[8px]"></i> 
                                            <span>At least 1 number (0-9)</span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($pwd_require_special): ?>
                                        <div class="requirement flex items-center gap-1" id="reqSpecial">
                                            <i class="far fa-circle text-[8px]"></i> 
                                            <span>At least 1 special character (!@#$%^&*)</span>
                                        </div>
                                        <?php endif; ?>
                                        <div class="requirement flex items-center gap-1" id="reqNoSpace">
                                            <i class="far fa-circle text-[8px]"></i> 
                                            <span>No spaces allowed</span>
                                        </div>
                                    </div>
                                    <!-- END DYNAMIC PASSWORD REQUIREMENTS -->
                                </div>
                                
                                <div>
                                    <div class="input-group">
                                        <i class="fas fa-check-circle input-icon"></i>
                                        <input type="password" name="confirm_password" id="confirmPwd" required class="input-field" placeholder=" " autocomplete="new-password" minlength="8" maxlength="16">
                                        <label for="confirmPwd" class="floating-label">Confirm Password <span class="text-red-400">*</span></label>
                                    </div>
                                    <p id="matchMsg" class="text-[11px] text-gray-400 ml-2 mt-1"></p>
                                </div>
                            </div>
                            
                            <!-- Resident Status -->
                            <div class="flex items-center gap-2 mb-4 mt-6 pb-2 border-b border-gray-100">
                                <i class="fas fa-home text-[#10A37F] text-base"></i>
                                <span class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Resident Status</span>
                            </div>
                            
                            <p class="text-sm text-gray-600 mb-3">Are you a resident of San Isidro, Nueva Ecija?</p>
                            
                            <div class="resident-selector">
                                <div class="resident-option selected" id="residentYes" onclick="selectResident('yes')">
                                    <i class="fas fa-check-circle"></i>
                                    <div class="label">Yes, I am a resident</div>
                                    <div class="sub-label">I live in San Isidro</div>
                                </div>
                                <div class="resident-option" id="residentNo" onclick="selectResident('no')">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <div class="label">No, I'm a non-resident</div>
                                    <div class="sub-label">I'm from another area</div>
                                </div>
                            </div>
                            <input type="hidden" name="is_resident" id="is_resident" value="yes">
                            
                            <!-- Address Fields - Resident -->
                            <div id="residentFields">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="input-group" style="position: relative;">
                                        <i class="fas fa-map-marker-alt input-icon" style="z-index: 20;"></i>
                                        <div class="select2-with-icon" id="barangayWrapper" style="width: 100%;">
                                            <select name="barangay_id" id="barangay" required style="width: 100%; padding-left: 2.5rem;">
                                                <option value=""></option>
                                                <?php while ($brgy = $barangays->fetch(PDO::FETCH_ASSOC)): ?>
                                                    <option value="<?php echo $brgy['id']; ?>"><?php echo htmlspecialchars($brgy['name']); ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                            <span class="select2-icon"><i class="fas fa-map-marker-alt"></i></span>
                                            <span class="select2-label">Barangay <span class="text-red-400">*</span></span>
                                        </div>
                                    </div>
                                    <div class="input-group">
                                        <i class="fas fa-road input-icon"></i>
                                        <input type="text" name="purok_street" id="purok_street" required class="input-field" placeholder=" ">
                                        <label for="purok_street" class="floating-label">Purok/Street/Subdivision <span class="text-red-400">*</span></label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Address Fields - Non-Resident -->
                            <div id="nonResidentFields" style="display: none;">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="input-group" style="position: relative;">
                                        <i class="fas fa-map input-icon" style="z-index: 20;"></i>
                                        <div class="select2-with-icon" id="provinceWrapper" style="width: 100%;">
                                            <select name="province" id="province" style="width: 100%; padding-left: 2.5rem;">
                                                <option value=""></option>
                                                <?php foreach ($provinces as $prov): ?>
                                                    <option value="<?php echo htmlspecialchars($prov); ?>"><?php echo htmlspecialchars($prov); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <span class="select2-icon"><i class="fas fa-map"></i></span>
                                            <span class="select2-label">Province <span class="text-red-400">*</span></span>
                                        </div>
                                    </div>
                                    <div class="input-group" style="position: relative;">
                                        <i class="fas fa-city input-icon" style="z-index: 20;"></i>
                                        <div class="select2-with-icon" id="municipalityWrapper" style="width: 100%;">
                                            <select name="municipality" id="municipality" style="width: 100%; padding-left: 2.5rem;">
                                                <option value=""></option>
                                            </select>
                                            <span class="select2-icon"><i class="fas fa-city"></i></span>
                                            <span class="select2-label">Municipality <span class="text-red-400">*</span></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="input-group">
                                    <i class="fas fa-road input-icon"></i>
                                    <input type="text" name="non_resident_address" id="non_resident_address" class="input-field" placeholder=" ">
                                    <label for="non_resident_address" class="floating-label">Barangay/Street <span class="text-gray-400 text-[9px] ml-1">Optional</span></label>
                                </div>
                            </div>
                            
                            <!-- Terms -->
                            <div class="flex items-start gap-2 mt-6">
                                <input type="checkbox" id="terms" required class="mt-0.5 w-4 h-4 rounded border-gray-300 text-[#10A37F] focus:ring-[#10A37F]">
                                <label for="terms" class="text-xs text-gray-600 leading-relaxed">
                                    I agree to the <a href="#" class="text-[#10A37F] hover:underline font-medium">Terms of Service</a> and 
                                    <a href="#" class="text-[#10A37F] hover:underline font-medium">Privacy Policy</a>.
                                </label>
                            </div>
                            
                            <!-- Next Button -->
                            <button type="button" onclick="validateAndProceed()" class="w-full mt-6 text-white font-semibold py-3 rounded-xl transition-all hover:scale-[0.98] hover:shadow-lg text-base" style="background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%);">
                                <span>Continue <i class="fas fa-arrow-right ml-2"></i></span>
                            </button>
                        </form>
                    </div>
                    
                    <!-- ============================================ -->
                    <!-- STEP 2: OTP VERIFICATION -->
                    <!-- ============================================ -->
                    <div class="step-container" id="step2">
                        <div class="text-center mb-6">
                            <div class="w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center mx-auto mb-3">
                                <i class="fas fa-shield-alt text-blue-500 text-2xl"></i>
                            </div>
                            <h3 class="text-xl font-bold text-gray-800">Verify Your Account</h3>
                            <p class="text-gray-500 text-sm mt-1">Enter the 6-digit code sent to your mobile number</p>
                            <p class="text-sm font-medium text-[#10A37F] mt-2" id="otpPhoneDisplay">+63 912 345 6789</p>

                        </div>
                        
                        <div class="otp-container" id="otpContainer">
                            <input type="text" class="otp-input" id="otp1" maxlength="1" inputmode="numeric" pattern="[0-9]" autofocus>
                            <input type="text" class="otp-input" id="otp2" maxlength="1" inputmode="numeric" pattern="[0-9]">
                            <input type="text" class="otp-input" id="otp3" maxlength="1" inputmode="numeric" pattern="[0-9]">
                            <input type="text" class="otp-input" id="otp4" maxlength="1" inputmode="numeric" pattern="[0-9]">
                            <input type="text" class="otp-input" id="otp5" maxlength="1" inputmode="numeric" pattern="[0-9]">
                            <input type="text" class="otp-input" id="otp6" maxlength="1" inputmode="numeric" pattern="[0-9]">
                        </div>
                        
                        <div id="otpError" class="text-red-500 text-sm text-center hidden">
                            <i class="fas fa-exclamation-circle mr-1"></i>
                            <span>Please enter all 6 digits.</span>
                        </div>
                        
                        <div id="otpSuccess" class="text-green-500 text-sm text-center hidden">
                            <i class="fas fa-check-circle mr-1"></i>
                            <span>Code verified! Creating account...</span>
                        </div>
                        
                        <div id="registerLoading" class="text-center hidden">
                            <div class="register-spinner mx-auto mb-2"></div>
                            <p class="text-sm text-gray-500">Creating your account...</p>
                        </div>
                        
                        <div class="text-center mt-4">
                            <p class="text-sm text-gray-500">
                                Didn't receive the code? 
                                <button type="button" onclick="resendOTP()" class="text-[#10A37F] font-semibold hover:underline" id="resendBtn">
                                    Resend
                                </button>
                            </p>
                            <p class="text-xs text-gray-400 mt-1" id="resendTimer"></p>
                        </div>
                        
                        <div class="flex gap-3 mt-6">
                            <button onclick="goToStep1()" class="flex-1 px-4 py-2.5 border border-gray-200 rounded-xl hover:bg-gray-50 transition-all font-medium text-sm">
                                <i class="fas fa-arrow-left mr-2"></i>Back
                            </button>
                            <button onclick="verifyOTP()" class="flex-1 px-4 py-2.5 text-white rounded-xl font-semibold text-sm transition-all hover:scale-[0.98]" style="background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%);">
                                <span id="verifyBtnText">Verify</span>
                                <span id="verifySpinner" class="hidden">
                                    <i class="fas fa-spinner fa-spin mr-1"></i> Verifying...
                                </span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- ============================================ -->
                    <!-- STEP 3: SUCCESS -->
                    <!-- ============================================ -->
                    <div class="step-container" id="step3">
                        <div class="success-screen">
                            <div class="success-icon">
                                <i class="fas fa-leaf"></i>
                            </div>
                            <h2 class="text-2xl font-bold text-gray-800 mb-2">Account Created!</h2>
                            <p class="text-gray-500 text-sm mb-4">Welcome to <?php echo htmlspecialchars($system_name); ?>, the Environmental Reporting System of San Isidro, Nueva Ecija.</p>
                            
                            <div class="bg-green-50 rounded-xl p-4 mb-6 text-left">
                                <div class="flex items-center gap-3">
                                    <i class="fas fa-check-circle text-green-500"></i>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-800">Registration Complete</p>
                                        <p class="text-xs text-gray-500">Your account has been successfully created.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-yellow-50 rounded-xl p-4 mb-6 text-left border border-yellow-200">
                                <div class="flex items-start gap-3">
                                    <i class="fas fa-info-circle text-yellow-600 mt-0.5"></i>
                                    <div>
                                        <p class="text-sm font-semibold text-yellow-800">Demo Mode</p>
                                        <p class="text-xs text-yellow-700">Your account is automatically verified. In production, you will need to verify your mobile number.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <a href="<?php echo BASE_URL; ?>index.php?page=login" class="w-full inline-block text-center text-white font-semibold py-3 rounded-xl transition-all hover:scale-[0.98] hover:shadow-lg" style="background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%);">
                                <i class="fas fa-sign-in-alt mr-2"></i>Proceed to Login
                            </a>
                        </div>
                    </div>
                    
                    <!-- Already registered -->
                    <div class="text-center mt-6 pt-4 border-t border-gray-100">
                        <p class="text-sm text-gray-500">
                            Already have an account? 
                            <a href="login.php" class="text-[#10A37F] font-semibold hover:underline">Sign in</a>
                        </p>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
    
    <!-- jQuery (required for Select2) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
    // ============================================
    // DYNAMIC PASSWORD SETTINGS FROM SERVER
    // ============================================
    const passwordSettings = {
        minLength: <?php echo $pwd_min; ?>,
        requireUpper: <?php echo $pwd_require_upper ? 'true' : 'false'; ?>,
        requireLower: <?php echo $pwd_require_lower ? 'true' : 'false'; ?>,
        requireNumber: <?php echo $pwd_require_number ? 'true' : 'false'; ?>,
        requireSpecial: <?php echo $pwd_require_special ? 'true' : 'false'; ?>
    };
    
    // ============================================
    // INIT SELECT2 FOR SEARCHABLE DROPDOWNS
    // ============================================
    $(document).ready(function() {
        // Initialize Select2 for Barangay
        $('#barangay').select2({
            placeholder: '',
            allowClear: true,
            width: '100%',
            dropdownParent: $('#residentFields'),
            language: {
                noResults: function() {
                    return 'No barangay found';
                },
                searching: function() {
                    return 'Searching...';
                }
            }
        });

        // Barangay floating label events
        $('#barangay').on('select2:open', function() {
            $('#barangayWrapper').addClass('select2-active');
            $('.select2-search__field').attr('placeholder', 'Search your barangay');
        });

        $('#barangay').on('select2:close', function() {
            $('#barangayWrapper').removeClass('select2-active');
            updateSelect2Label('#barangay', '#barangayWrapper');
        });

        $('#barangay').on('change', function() {
            updateSelect2Label('#barangay', '#barangayWrapper');
        });

        $('#barangay').on('select2:select', function() {
            $('#barangayWrapper').addClass('has-value');
        });

        updateSelect2Label('#barangay', '#barangayWrapper');

        // Initialize Select2 for Province
        $('#province').select2({
            allowClear: true,
            width: '100%',
            dropdownParent: $('#nonResidentFields'),
            language: {
                noResults: function() {
                    return 'No province found';
                },
                searching: function() {
                    return 'Searching...';
                }
            }
        });
        
        // Initialize Select2 for Municipality
        $('#municipality').select2({
            allowClear: true,
            width: '100%',
            dropdownParent: $('#nonResidentFields'),
            language: {
                noResults: function() {
                    return 'No municipality found';
                },
                searching: function() {
                    return 'Searching...';
                }
            }
        });
        
        function updateSelect2Label(selectId, wrapperId) {
            const select = $(selectId);
            const wrapper = $(wrapperId);
            const val = select.val();
            
            if (val && val !== '') {
                wrapper.addClass('has-value');
            } else {
                wrapper.removeClass('has-value');
            }
        }
        
        $('#province').on('change', function() {
            updateSelect2Label('#province', '#provinceWrapper');
            loadMunicipalities($(this).val());
        });
        
        $('#municipality').on('change', function() {
            updateSelect2Label('#municipality', '#municipalityWrapper');
        });

        $('#municipality').on('select2:select', function() {
            $('#municipalityWrapper').addClass('has-value');
        });
        
        $('#province').on('select2:open', function() {
            $('#provinceWrapper').addClass('select2-active');
            $('.select2-search__field').attr('placeholder', 'Search your province');
        });
        $('#province').on('select2:close', function() {
            $('#provinceWrapper').removeClass('select2-active');
            updateSelect2Label('#province', '#provinceWrapper');
        });
        
        $('#municipality').on('select2:open', function() {
            $('#municipalityWrapper').addClass('select2-active');
            $('.select2-search__field').attr('placeholder', 'Search your municipality');
        });
        $('#municipality').on('select2:close', function() {
            $('#municipalityWrapper').removeClass('select2-active');
            updateSelect2Label('#municipality', '#municipalityWrapper');
        });
        
        updateSelect2Label('#province', '#provinceWrapper');
        updateSelect2Label('#municipality', '#municipalityWrapper');
    });
    
    // ============================================
    // LOAD MUNICIPALITIES
    // ============================================
    function loadMunicipalities(province) {
        const municipalitySelect = $('#municipality');
        
        if (!province) {
            municipalitySelect.empty().append('<option value="">Select Municipality</option>').trigger('change');
            return;
        }
        
        municipalitySelect.empty().append('<option value="">Loading...</option>').trigger('change');
        
        fetch('<?php echo BASE_URL; ?>views/auth/register.php?action=get_municipalities&province=' + encodeURIComponent(province))
            .then(response => response.json())
            .then(data => {
                municipalitySelect.empty().append('<option value=""></option>');
                data.forEach(mun => {
                    municipalitySelect.append('<option value="' + mun + '">' + mun + '</option>');
                });
                municipalitySelect.trigger('change');
                setTimeout(() => {
                    $('#municipalityWrapper').removeClass('has-value');
                }, 100);
            })
            .catch(() => {
                municipalitySelect.empty().append('<option value="">Error loading municipalities</option>').trigger('change');
                alert('Error loading municipalities. Please select again.');
            });
    }
    
    // ============================================
    // STEP NAVIGATION
    // ============================================
    let currentStep = 1;
    let resendCount = 0;
    let resendTimer = null;
    let timerSeconds = 60;
    
    function showStep(step) {
        document.querySelectorAll('.step-container').forEach(el => el.classList.remove('active'));
        document.getElementById('step' + step).classList.add('active');
        
        document.querySelectorAll('.step-dot').forEach(el => el.classList.remove('active', 'completed'));
        document.querySelectorAll('.step-line').forEach(el => el.classList.remove('completed'));
        document.querySelectorAll('.step-label').forEach(el => el.classList.remove('active'));
        
        for (let i = 1; i <= 3; i++) {
            const dot = document.getElementById('dot' + i);
            const label = document.getElementById('label' + i);
            if (i < step) {
                dot.classList.add('completed');
            } else if (i === step) {
                dot.classList.add('active');
                label.classList.add('active');
            }
        }
        
        for (let i = 1; i < step; i++) {
            const line = document.getElementById('line' + i);
            if (line) line.classList.add('completed');
        }
        
        currentStep = step;
    }
    
    function goToStep1() {
        showStep(1);
        clearInterval(resendTimer);
        document.getElementById('resendTimer').textContent = '';
        document.getElementById('resendBtn').disabled = false;
    }
    
    // ============================================
    // RESIDENT SELECTOR
    // ============================================
    function selectResident(type) {
        document.querySelectorAll('.resident-option').forEach(el => el.classList.remove('selected'));
        
        if (type === 'yes') {
            document.getElementById('residentYes').classList.add('selected');
            document.getElementById('residentFields').style.display = 'block';
            document.getElementById('nonResidentFields').style.display = 'none';
            document.getElementById('is_resident').value = 'yes';
            document.getElementById('barangay').required = true;
            document.getElementById('purok_street').required = true;
            document.getElementById('province').required = false;
            document.getElementById('municipality').required = false;
        } else {
            document.getElementById('residentNo').classList.add('selected');
            document.getElementById('residentFields').style.display = 'none';
            document.getElementById('nonResidentFields').style.display = 'block';
            document.getElementById('is_resident').value = 'no';
            document.getElementById('barangay').required = false;
            document.getElementById('purok_street').required = false;
            document.getElementById('province').required = true;
            document.getElementById('municipality').required = true;
            
            setTimeout(() => {
                $('#province').select2({
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $('#nonResidentFields')
                });
                $('#municipality').select2({
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $('#nonResidentFields')
                });
                $('#province').select2('open');
            }, 200);
        }
    }
    
    // ============================================
    // STEP 1: VALIDATION & SEND OTP
    // ============================================
    function validateAndProceed() {
        // Hide duplicate error
        document.getElementById('duplicateError').classList.remove('show');
        
        // Get form data
        const firstName = document.getElementById('first_name').value.trim();
        const lastName = document.getElementById('last_name').value.trim();
        const contact = document.getElementById('contact_number').value.trim();
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;
        const confirmPwd = document.getElementById('confirmPwd').value;
        const isResident = document.getElementById('is_resident').value;
        
        // Validate names
        if (firstName.length < 2) {
            alert('Please enter your first name (minimum 2 characters).');
            document.getElementById('first_name').focus();
            return;
        }
        
        if (lastName.length < 2) {
            alert('Please enter your last name (minimum 2 characters).');
            document.getElementById('last_name').focus();
            return;
        }
        
        // Validate phone
        if (!/^09[0-9]{9}$/.test(contact)) {
            alert('Please enter a valid 11-digit mobile number starting with 09.');
            document.getElementById('contact_number').focus();
            return;
        }
        
        // Validate email
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            alert('Please enter a valid email address.');
            document.getElementById('email').focus();
            return;
        }
        
        // Validate password
        if (password.length < passwordSettings.minLength || password.length > 16) {
            alert('Password must be between ' + passwordSettings.minLength + ' and 16 characters.');
            document.getElementById('password').focus();
            return;
        }
        
        if (password !== confirmPwd) {
            alert('Passwords do not match.');
            document.getElementById('confirmPwd').focus();
            return;
        }
        
        // Validate password strength (using dynamic rules)
        const score = checkStrength(password);
        if (score < 3) {
            alert('Please choose a stronger password (at least "Fair" strength).');
            document.getElementById('password').focus();
            return;
        }
        
        // Validate address
        if (isResident === 'yes') {
            const barangay = document.getElementById('barangay').value;
            if (!barangay) {
                alert('Please select your barangay.');
                document.getElementById('barangay').focus();
                return;
            }
            const purok = document.getElementById('purok_street').value.trim();
            if (!purok) {
                alert('Please enter your Purok/Street/Subdivision.');
                document.getElementById('purok_street').focus();
                return;
            }
        } else {
            const province = $('#province').val();
            const municipality = $('#municipality').val();
            if (!province || !municipality) {
                alert('Please select your province and municipality.');
                if (!province) $('#province').select2('open');
                else $('#municipality').select2('open');
                return;
            }
        }
        
        if (!document.getElementById('terms').checked) {
            alert('Please agree to the Terms of Service and Privacy Policy.');
            return;
        }
        
        // ============================================
        // CHECK FOR DUPLICATE EMAIL/NUMBER
        // ============================================
        const formData = new FormData();
        formData.append('action', 'check_duplicate');
        formData.append('email', email);
        formData.append('contact_number', contact);
        
        const btn = document.querySelector('#step1 button[onclick="validateAndProceed()"]');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Checking...';
        btn.disabled = true;
        
        fetch('<?php echo BASE_URL; ?>controllers/AuthController.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            btn.innerHTML = originalText;
            btn.disabled = false;
            
            if (data.error) {
                const errorDiv = document.getElementById('duplicateError');
                const errorMsg = document.getElementById('duplicateErrorMessage');
                errorMsg.textContent = data.error;
                errorDiv.classList.add('show');
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return;
            }
            
            // No duplicates - send OTP
            sendRegistrationOTP();
        })
        .catch(error => {
            console.error('Error checking duplicates:', error);
            btn.innerHTML = originalText;
            btn.disabled = false;
            alert('Network error. Please try again.');
        });
    }
    
    // ============================================
    // SEND OTP TO SERVER
    // ============================================
    function sendRegistrationOTP() {
        const form = document.getElementById('step1Form');
        const formData = new FormData(form);
        formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
        formData.append('action', 'send_registration_otp');
        
        const btn = document.querySelector('#step1 button[onclick="validateAndProceed()"]');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Sending OTP...';
        btn.disabled = true;
        
        fetch('<?php echo BASE_URL; ?>controllers/AuthController.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(data => {
            btn.innerHTML = originalText;
            btn.disabled = false;
            
            if (data.success) {
                // Store registration data for Step 2 & 3
                proceedToStep2();
            } else {
                alert(data.error || 'Failed to send OTP. Please try again.');
            }
        })
        .catch(error => {
            btn.innerHTML = originalText;
            btn.disabled = false;
            alert('Network error. Please try again.');
        });
    }
    
    function proceedToStep2() {
        const isResident = document.getElementById('is_resident').value;
        const province = isResident === 'no' ? $('#province').val() : '';
        const municipality = isResident === 'no' ? $('#municipality').val() : '';
        
        window.registrationData = {
            first_name: document.getElementById('first_name').value.trim(),
            last_name: document.getElementById('last_name').value.trim(),
            contact_number: document.getElementById('contact_number').value.trim(),
            email: document.getElementById('email').value.trim(),
            password: document.getElementById('password').value,
            confirm_password: document.getElementById('confirmPwd').value,
            is_resident: isResident,
            barangay_id: isResident === 'yes' ? document.getElementById('barangay').value : '',
            purok_street: isResident === 'yes' ? document.getElementById('purok_street').value : '',
            province: province,
            municipality: municipality,
            non_resident_address: isResident === 'no' ? document.getElementById('non_resident_address').value : '',
            csrf_token: document.querySelector('input[name="csrf_token"]').value
        };
        
        const formattedPhone = formatPhoneNumber(document.getElementById('contact_number').value.trim());
        document.getElementById('otpPhoneDisplay').textContent = formattedPhone;
        
        showStep(2);
        document.getElementById('otp1').focus();
        
        document.querySelectorAll('.otp-input').forEach(input => {
            input.value = '';
            input.classList.remove('filled', 'error');
        });
        document.getElementById('otpError').classList.add('hidden');
        document.getElementById('otpSuccess').classList.add('hidden');
        document.getElementById('registerLoading').classList.add('hidden');
        
        startResendTimer();
    }
    
    // ============================================
    // OTP FUNCTIONS
    // ============================================
    function formatPhoneNumber(phone) {
        if (phone.length === 11) {
            return '+63 ' + phone.substring(1, 4) + ' ' + phone.substring(4, 7) + ' ' + phone.substring(7, 11);
        }
        return phone;
    }
    
    document.querySelectorAll('.otp-input').forEach((input, index, arr) => {
        input.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length === 1) {
                this.classList.add('filled');
                if (index < arr.length - 1) {
                    arr[index + 1].focus();
                }
            } else {
                this.classList.remove('filled');
            }
            document.getElementById('otpError').classList.add('hidden');
        });
        
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && !this.value && index > 0) {
                arr[index - 1].focus();
            }
        });
        
        input.addEventListener('paste', function(e) {
            e.preventDefault();
            const paste = (e.clipboardData || window.clipboardData).getData('text');
            const digits = paste.replace(/[^0-9]/g, '');
            const otpInputs = document.querySelectorAll('.otp-input');
            for (let i = 0; i < Math.min(digits.length, otpInputs.length); i++) {
                otpInputs[i].value = digits[i];
                otpInputs[i].classList.add('filled');
            }
        });
    });
    
    function verifyOTP() {
        const inputs = document.querySelectorAll('.otp-input');
        let entered = '';
        let allFilled = true;
        
        inputs.forEach(input => {
            entered += input.value;
            if (!input.value) allFilled = false;
        });
        
        if (!allFilled) {
            document.getElementById('otpError').textContent = 'Please enter all 6 digits.';
            document.getElementById('otpError').classList.remove('hidden');
            document.getElementById('otpSuccess').classList.add('hidden');
            document.getElementById('registerLoading').classList.add('hidden');
            return;
        }
        
        document.getElementById('verifyBtnText').classList.add('hidden');
        document.getElementById('verifySpinner').classList.remove('hidden');
        document.getElementById('otpError').classList.add('hidden');
        document.getElementById('otpSuccess').classList.add('hidden');
        document.getElementById('registerLoading').classList.add('hidden');
        
        const formData = new FormData();
        formData.append('action', 'verify_registration_otp');
        formData.append('otp', entered);
        formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
        
        fetch('<?php echo BASE_URL; ?>controllers/AuthController.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(data => {
            document.getElementById('verifyBtnText').classList.remove('hidden');
            document.getElementById('verifySpinner').classList.add('hidden');
            
            if (data.success) {
                document.getElementById('otpSuccess').textContent = '✓ Code verified! Creating account...';
                document.getElementById('otpSuccess').classList.remove('hidden');
                document.getElementById('registerLoading').classList.remove('hidden');
                submitRegistration();
            } else {
                document.getElementById('otpError').textContent = data.message || 'Invalid or expired code. Please try again.';
                document.getElementById('otpError').classList.remove('hidden');
                document.querySelectorAll('.otp-input').forEach(input => {
                    input.value = '';
                    input.classList.remove('filled', 'error');
                });
                document.getElementById('otp1').focus();
            }
        })
        .catch(error => {
            document.getElementById('verifyBtnText').classList.remove('hidden');
            document.getElementById('verifySpinner').classList.add('hidden');
            alert('Network error. Please try again.');
        });
    }
    
    function submitRegistration() {
        const formData = new FormData();
        formData.append('action', 'register');
        formData.append('csrf_token', window.registrationData.csrf_token);
        formData.append('first_name', window.registrationData.first_name);
        formData.append('last_name', window.registrationData.last_name);
        formData.append('contact_number', window.registrationData.contact_number);
        formData.append('email', window.registrationData.email);
        formData.append('password', window.registrationData.password);
        formData.append('confirm_password', window.registrationData.confirm_password);
        formData.append('is_resident', window.registrationData.is_resident);
        
        if (window.registrationData.is_resident === 'yes') {
            formData.append('barangay_id', window.registrationData.barangay_id);
            formData.append('purok_street', window.registrationData.purok_street);
        } else {
            formData.append('province', window.registrationData.province);
            formData.append('municipality', window.registrationData.municipality);
            formData.append('non_resident_address', window.registrationData.non_resident_address || '');
        }
        
        document.getElementById('registerLoading').classList.remove('hidden');
        document.getElementById('otpSuccess').classList.add('hidden');
        document.getElementById('verifyBtnText').classList.add('hidden');
        document.getElementById('verifySpinner').classList.remove('hidden');
        
        fetch('<?php echo BASE_URL; ?>controllers/AuthController.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return response.json();
            }
            if (response.redirected) {
                showStep(3);
                document.getElementById('registerLoading').classList.add('hidden');
                document.getElementById('verifyBtnText').classList.remove('hidden');
                document.getElementById('verifySpinner').classList.add('hidden');
                return { success: true, message: 'Registration successful!' };
            }
            return response.text().then(text => {
                try { return JSON.parse(text); }
                catch(e) { return { error: 'Registration failed. Please try again.' }; }
            });
        })
        .then(data => {
            document.getElementById('verifyBtnText').classList.remove('hidden');
            document.getElementById('verifySpinner').classList.add('hidden');
            
            if (data && data.success) {
                showStep(3);
                document.getElementById('registerLoading').classList.add('hidden');
            } else if (data && data.error) {
                document.getElementById('otpError').textContent = data.error;
                document.getElementById('otpError').classList.remove('hidden');
                document.getElementById('registerLoading').classList.add('hidden');
                showStep(2);
            } else {
                document.getElementById('otpError').textContent = 'Registration failed. Please try again.';
                document.getElementById('otpError').classList.remove('hidden');
                document.getElementById('registerLoading').classList.add('hidden');
                showStep(2);
            }
        })
        .catch(error => {
            console.error('Registration error:', error);
            document.getElementById('otpError').textContent = 'Network error. Please try again.';
            document.getElementById('otpError').classList.remove('hidden');
            document.getElementById('registerLoading').classList.add('hidden');
            document.getElementById('verifyBtnText').classList.remove('hidden');
            document.getElementById('verifySpinner').classList.add('hidden');
            showStep(2);
        });
    }
    
    function resendOTP() {
        if (resendCount >= 3) {
            alert('Maximum resend limit reached. Please try again later.');
            return;
        }
        
        resendCount++;
        document.getElementById('resendBtn').disabled = true;
        startResendTimer();
        
        document.querySelectorAll('.otp-input').forEach(input => {
            input.value = '';
            input.classList.remove('filled', 'error');
        });
        document.getElementById('otpError').classList.add('hidden');
        document.getElementById('otpSuccess').classList.add('hidden');
        document.getElementById('registerLoading').classList.add('hidden');
        
        const btn = document.getElementById('resendBtn');
        btn.textContent = 'Sending...';
        
        const formData = new FormData();
        formData.append('action', 'send_registration_otp');
        formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
        
        fetch('<?php echo BASE_URL; ?>controllers/AuthController.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(data => {
            btn.textContent = 'Resend';
            if (data.success) {
                startResendTimer();
                document.getElementById('otpError').classList.add('hidden');
                document.getElementById('otp1').focus();
            } else {
                alert(data.error || 'Failed to resend OTP. Please try again.');
                document.getElementById('resendBtn').disabled = false;
            }
        })
        .catch(error => {
            btn.textContent = 'Resend';
            document.getElementById('resendBtn').disabled = false;
            alert('Network error. Please try again.');
        });
    }
    
    function startResendTimer() {
        timerSeconds = 60;
        document.getElementById('resendBtn').disabled = true;
        document.getElementById('resendTimer').textContent = 'Resend available in ' + timerSeconds + 's';
        
        clearInterval(resendTimer);
        resendTimer = setInterval(() => {
            timerSeconds--;
            document.getElementById('resendTimer').textContent = 'Resend available in ' + timerSeconds + 's';
            
            if (timerSeconds <= 0) {
                clearInterval(resendTimer);
                document.getElementById('resendTimer').textContent = '';
                document.getElementById('resendBtn').disabled = false;
            }
        }, 1000);
    }
    
    // ============================================
    // PASSWORD STRENGTH (DYNAMIC)
    // ============================================
    function checkStrength(password) {
        const min = passwordSettings.minLength;
        if (password.length < min || password.length > 16) return 0;
        
        let score = 0;
        if (password.length >= 8) score += 1;
        if (password.length >= 10) score += 1;
        if (password.length >= 12) score += 1;
        if (password.length >= 14) score += 1;
        if (/[A-Z]/.test(password) && passwordSettings.requireUpper) score += 1;
        if (/[a-z]/.test(password) && passwordSettings.requireLower) score += 1;
        if (/[0-9]/.test(password) && passwordSettings.requireNumber) score += 1;
        if (/[!@#$%^&*()\-_=+{};:,<.>]/.test(password) && passwordSettings.requireSpecial) score += 1;
        if (!/\s/.test(password)) score += 1;
        
        const commonPasswords = ['password', 'password123', '12345678', 'qwerty123', 'admin123', 'letmein', 'abc12345', '123456789'];
        if (commonPasswords.includes(password.toLowerCase())) score = 0;
        if (/(.)\1{2,}/.test(password)) score -= 1;
        
        return Math.max(0, Math.min(10, score));
    }
    
    function getStrengthLabel(score) {
        if (score <= 1) return { text: 'Very Weak', class: 'very-weak' };
        if (score <= 3) return { text: 'Weak', class: 'weak' };
        if (score <= 5) return { text: 'Fair', class: 'fair' };
        if (score <= 7) return { text: 'Good', class: 'good' };
        return { text: 'Strong!', class: 'strong' };
    }
    
    function updateStrengthMeter(score) {
        const label = getStrengthLabel(score);
        const fill = document.getElementById('strengthFill');
        const text = document.getElementById('strengthText');
        
        fill.className = 'strength-meter-fill';
        if (score > 0) {
            fill.classList.add('strength-' + label.class);
            text.className = 'strength-text ' + label.class;
            text.textContent = label.text;
        } else {
            text.className = 'strength-text';
            text.textContent = '';
        }
    }
    
    function updateRequirements(password) {
        const min = passwordSettings.minLength;
        const checks = {
            reqLength: password.length >= min && password.length <= 16,
            reqUpper: passwordSettings.requireUpper ? /[A-Z]/.test(password) : true,
            reqLower: passwordSettings.requireLower ? /[a-z]/.test(password) : true,
            reqNumber: passwordSettings.requireNumber ? /[0-9]/.test(password) : true,
            reqSpecial: passwordSettings.requireSpecial ? /[!@#$%^&*()\-_=+{};:,<.>]/.test(password) : true,
            reqNoSpace: !/\s/.test(password)
        };
        
        for (const [id, met] of Object.entries(checks)) {
            const el = document.getElementById(id);
            if (!el) continue;
            el.className = 'requirement flex items-center gap-1';
            const icon = el.querySelector('i');
            const span = el.querySelector('span');
            
            if (password.length === 0) {
                icon.className = 'far fa-circle text-[8px]';
                el.classList.add('unmet');
                el.classList.remove('met');
            } else if (met) {
                icon.className = 'fas fa-check-circle text-[8px]';
                el.classList.add('met');
                el.classList.remove('unmet');
            } else {
                icon.className = 'far fa-circle text-[8px]';
                el.classList.add('unmet');
                el.classList.remove('met');
            }
        }
    }
    
    function checkMatch() {
        const pwd = document.getElementById('password').value;
        const confirm = document.getElementById('confirmPwd').value;
        const msg = document.getElementById('matchMsg');
        
        if (confirm.length === 0) { msg.innerHTML = ''; return; }
        if (pwd === confirm) {
            msg.innerHTML = '<span class="text-[#10A37F]"><i class="fas fa-check-circle mr-0.5"></i>Passwords match</span>';
        } else {
            msg.innerHTML = '<span class="text-red-500"><i class="fas fa-times-circle mr-0.5"></i>Passwords do not match</span>';
        }
    }
    
    // Password event listeners
    document.getElementById('password').addEventListener('input', function() {
        const score = checkStrength(this.value);
        updateStrengthMeter(score);
        updateRequirements(this.value);
        checkMatch();
    });
    
    document.getElementById('confirmPwd').addEventListener('input', checkMatch);
    
    // ============================================
    // PHONE VALIDATION
    // ============================================
    document.getElementById('contact_number').addEventListener('input', function(e) {
        let v = e.target.value.replace(/[^0-9]/g, '');
        if (v.length > 11) v = v.substring(0, 11);
        e.target.value = v;
        const error = document.getElementById('phoneError');
        if (v.length > 0 && !/^09/.test(v)) {
            error.textContent = 'Must start with 09';
            error.classList.remove('hidden');
        } else if (v.length > 0 && v.length < 11) {
            error.textContent = 'Must be 11 digits';
            error.classList.remove('hidden');
        } else if (v.length === 11 && /^09[0-9]{9}$/.test(v)) {
            error.classList.add('hidden');
        } else {
            error.classList.add('hidden');
        }
    });
    
    // ============================================
    // EMAIL DOMAIN VALIDATION (LIVE, BEFORE SUBMIT)
    // Runs on blur (immediately) and while typing (debounced),
    // so the MX-check feedback shows before the user ever
    // reaches the "Continue" button — not just on submit.
    // ============================================
    let emailCheckTimer = null;
    let emailCheckController = null;
    let emailCheckedValue = null;   // last value we successfully validated

    function resetEmailFeedback() {
        document.getElementById('emailChecking').classList.add('hidden');
        document.getElementById('emailError').classList.add('hidden');
        document.getElementById('emailError').innerHTML = '';
        document.getElementById('emailSuccess').classList.add('hidden');
        document.getElementById('email').classList.remove('error', 'valid');
    }

    function checkEmailLive(email) {
        // Only bother hitting the server once it's a plausible email
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            resetEmailFeedback();
            return;
        }
        if (email === emailCheckedValue) {
            // Already validated this exact value (e.g. user edited away and
            // back again) - restore the success state instead of re-checking.
            document.getElementById('emailChecking').classList.add('hidden');
            document.getElementById('emailError').classList.add('hidden');
            document.getElementById('emailSuccess').classList.remove('hidden');
            document.getElementById('email').classList.add('valid');
            document.getElementById('email').classList.remove('error');
            return;
        }

        if (emailCheckController) emailCheckController.abort();
        emailCheckController = new AbortController();

        document.getElementById('emailError').classList.add('hidden');
        document.getElementById('emailSuccess').classList.add('hidden');
        document.getElementById('emailChecking').classList.remove('hidden');

        const formData = new FormData();
        formData.append('action', 'check_duplicate');
        formData.append('email', email);

        fetch('<?php echo BASE_URL; ?>controllers/AuthController.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            signal: emailCheckController.signal
        })
        .then(response => response.json())
        .then(data => {
            // Ignore stale responses if the field has changed since this request went out
            if (document.getElementById('email').value.trim() !== email) return;

            document.getElementById('emailChecking').classList.add('hidden');

            if (data.error) {
                emailCheckedValue = null;
                const emailError = document.getElementById('emailError');
                const suggestionMatch = data.error.match(/Did you mean ([^\?]+)\?/);
                if (suggestionMatch) {
                    const suggestion = suggestionMatch[1].trim();
                    const before = data.error.substring(0, suggestionMatch.index);
                    emailError.innerHTML = '<i class="fas fa-exclamation-circle mr-0.5"></i>' + before +
                        'Did you mean <button type="button" class="underline font-semibold text-[#10A37F] hover:text-[#0d8c69]" onmousedown="event.preventDefault()" onclick="applyEmailSuggestion(\'' + suggestion.replace(/'/g, "\\'") + '\')">' + suggestion + '</button>?';
                } else {
                    emailError.innerHTML = '<i class="fas fa-exclamation-circle mr-0.5"></i>' + data.error;
                }
                emailError.classList.remove('hidden');
                document.getElementById('email').classList.add('error');
                document.getElementById('email').classList.remove('valid');
            } else {
                emailCheckedValue = email;
                document.getElementById('emailSuccess').classList.remove('hidden');
                document.getElementById('email').classList.add('valid');
                document.getElementById('email').classList.remove('error');
            }
        })
        .catch(error => {
            if (error.name === 'AbortError') return;
            document.getElementById('emailChecking').classList.add('hidden');
        });
    }

    function applyEmailSuggestion(suggestion) {
        const emailField = document.getElementById('email');
        emailField.value = suggestion;
        emailField.dispatchEvent(new Event('input'));
        emailField.focus();
        clearTimeout(emailCheckTimer);
        checkEmailLive(suggestion);
    }

    document.getElementById('email').addEventListener('input', function(e) {
        const email = e.target.value.trim();
        resetEmailFeedback();
        clearTimeout(emailCheckTimer);
        if (email.length === 0) return;
        // Debounce while the user is still typing so we're not firing a
        // DNS lookup on every keystroke.
        emailCheckTimer = setTimeout(() => checkEmailLive(email), 600);
    });

    document.getElementById('email').addEventListener('blur', function(e) {
        const email = e.target.value.trim();
        if (email.length === 0) return;
        clearTimeout(emailCheckTimer);
        checkEmailLive(email);
    });

    // ============================================
    // PASSWORD TOGGLE
    // ============================================
    document.getElementById('togglePassword').addEventListener('click', function() {
        const pwd = document.getElementById('password');
        const type = pwd.type === 'password' ? 'text' : 'password';
        pwd.type = type;
        this.querySelector('i').classList.toggle('fa-eye');
        this.querySelector('i').classList.toggle('fa-eye-slash');
    });
    
    // ============================================
    // FLOATING LABELS
    // ============================================
    document.querySelectorAll('.input-field').forEach(input => {
        if (input.value) input.dispatchEvent(new Event('input'));
    });
    
    // ============================================
    // KEYBOARD SHORTCUTS
    // ============================================
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            if (currentStep === 2) {
                verifyOTP();
            }
            if (currentStep === 1) {
                validateAndProceed();
            }
        }
    });
    </script>
</body>
</html>