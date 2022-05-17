<?php

// Ustaw następujące parametry instalacji bazy danych
// Skopiuj i lub zmień nazwę tego pliku na .htconfig.php

$db_host = '{{$dbhost}}';
$db_port = '{{$dbport}}';
$db_user = '{{$dbuser}}';
$db_pass = '{{$dbpass}}';
$db_data = '{{$dbdata}}';
$db_type = '{{$dbtype}}'; // liczba całkowita. 0 lub nieustawienie dla mysql, 1 dla postgres

/*
 * Uwaga: wiele z poniższych ustawień będzie dostępnych w panelu administracyjnym
 * po pomyślnej instalacji portalu. Po ustawieniu w panelu administracyjnym, opcje
 * te są przechowywane w DB - a ustawienie DB zastępują  wszelkie odpowiadające
 * in ustawienie w tym pliku
 *
 * Narzędzie wiersza poleceń util/config może bezpośrednio wysyłać zapytania i
 * ustawiać elementy bazy danych, jeśli z jakiegoś powodu panel administracyjny
 * nie jest dostępny a ustawienia systemowe wymagają modyfikacji.
 *
 */ 

// Wybierz legalną domyślną strefę czasową. Dla obszaru Polski jest to „Europe/Warsaw”.
// Można to zmienić później i ma to związek tylko z sygnaturami czasowymi dla
// anonimowych przeglądających.

App::$config['system']['timezone'] = '{{$timezone}}';

// Jaki jest adres URL Twojego portalu? NIE DODAWAJ KOŃCOWEGO UKOŚNIKA!

App::$config['system']['baseurl'] = '{{$siteurl}}';
App::$config['system']['sitename'] = "Hubzilla";
App::$config['system']['location_hash'] = '{{$site_id}}';

// Te wiersze ustawiają dodatkowe nagłówki bezpieczeństwa, które mają być
// wysyłane ze wszystkimi odpowiedziami. Możesz ustawić transport_security_header
// na 0, jeśli twój serwer już wysyła ten nagłówek. Może okazać się konieczne
// wyłączenie content_security_policy, jeśli chcesz uruchamiać wtyczkę Piwik
// umieszczać  na stronach inne zasoby zewnętrzne.

App::$config['system']['transport_security_header'] = 1;
App::$config['system']['content_security_policy'] = 1;
App::$config['system']['ssl_cookie_protection'] = 1;

// Masz do wyboru REGISTER_OPEN, REGISTER_APPROVE lub REGISTER_CLOSED.
// Upewnij się, że utworzyłeś swoje własne konto osobiste przed ustawieniem
// REGISTER_CLOSED. Tekst "register_text" (jeśli jest ustawiony) będzie widoczny
// w widocznym miejscu na stronie rejestracji. REGISTER_APPROVE wymaga ustawienia
// "admin_email" na adres e-mail już zarejestrowanej osoby, która może autoryzować
// albo zatwierdź czy też odrzuć żądanie.

App::$config['system']['register_policy'] = REGISTER_OPEN;
App::$config['system']['register_text'] = '';
App::$config['system']['admin_email'] = '{{$adminmail}}';

// Zalecamy pozostawienie tego ustawienia na 1. Ustaw na 0, aby umożliwić osobom
// rejestrowanie się bez udowadniania, że są właścicielami adresu e-mail, na który
// się rejestrują.

App::$config['system']['verify_email'] = 1;

// Ograniczenia dostępu do portalu. Domyślnie tworzone są  portale prywatne.
// Masz do wyboru ACCESS_PRIVATE, ACCESS_PAID, ACCESS_TIERED i ACCESS_FREE.
// Jeśli pozostawisz ustawienie REGISTER_OPEN powyżej, każdy bedzie się mógł
// zarejestrować na Twoim portalu, jednak portal ten nie będzie nigdzie
// wyświetlany jako witryna z otwartą resjestracją.
// Używamy polityki dostępu do systemu (poniżej) aby określić, czy portal ma być
// umieszczony w katalogu jako portal otwarty, w którym każdy może tworzyć konta.
// Twój inny wybór to: paid, tiered lub free.  

App::$config['system']['access_policy'] = ACCESS_PRIVATE;

// Jeśli prowadzisz portal publiczny, możesz zezwolić, aby osoby były kierowane
// do "strony sprzedaży", na której można szczegółowo opisać funkcje, zasady lub
// plany usług. To musi być bezwzględny adres URL zaczynający się od http:// lub
// https: //.

App::$config['system']['sellpage'] = '';

// Maksymalny rozmiar importowanej wiadomości, 0 to brak ograniczeń

App::$config['system']['max_import_size'] = 200000;

// Lokalizacja procesora wiersza poleceń PHP (CLI PHP)

App::$config['system']['php_path'] = '{{$phpath}}';

// Skonfiguruj sposób komunikacji z serwerami katalogowymi.
// DIRECTORY_MODE_NORMAL = klient katalogu, znajdziemy katalog
// DIRECTORY_MODE_SECONDARY = buforowanie katalogu lub kopii lustrzanej
// DIRECTORY_MODE_PRIMARY = główny serwer katalogów - jeden na dziedzinę
// DIRECTORY_MODE_STANDALONE = "poza siecią" lub prywatne usługi katalogowe

App::$config['system']['directory_mode']  = DIRECTORY_MODE_NORMAL;

// domyślny motyw systemowy

App::$config['system']['theme'] = 'redbasic';

// Konfiguracja rejstracji błędów PHP.
// Zanim to zrobisz, upewnij się, że serwer WWW ma uprawnienia
// tworzenie i zapisywanie php.out w katalogu WWW najwyższego poziomu,
// lub zmień nazwę (poniżej) na plik lub ścieżkę, jeśli jest to dozwolone.

// Odkomentuj te 4 linie, aby włączyć rejestrowanie błędów PHP.
//error_reporting(E_ERROR | E_WARNING | E_PARSE ); 
//ini_set('error_log','php.out'); 
//ini_set('log_errors','1'); 
//ini_set('display_errors', '0');
