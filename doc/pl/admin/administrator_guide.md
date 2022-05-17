### Przegląd

$Projectname to więcej niż prosta aplikacja internetowa. Jest to złożony system komunikacyjny, który bardziej przypomina serwer poczty elektronicznej niż serwer WWW. Aby zapewnić niezawodność i wydajność, wiadomości są dostarczane w tle i umieszczane w kolejce do późniejszego dostarczenia, gdy lokacje są wyłączone. Ten rodzaj funkcjonalności wymaga nieco więcej zasobów hosta niż typowy blog. Nie każdy dostawca hostingu PHP-MySQL będzie w stanie obsługiwać $Projectname. Tak więc, przed instalacją zapoznaj się z wymaganiami i potwierdź je u dostawcy usług hostingowych.

Bardzo staraliśmy się, aby Hubzilla działała na zwykłych platformach hostingowych, takich jak te używane do hostowania blogów Wordpress i stron internetowych Drupal. Będzie ona działać na większości systemów VPS Linux. Platformy Windows LAMP, takie jak XAMPP i WAMP, nie są obecnie oficjalnie obsługiwane, jednak mile widziane są poprawki, jeśli uda Ci się je uruchomić.

### Gdzie można znaleźć więcej pomocy

Jeśli napotkasz problemy lub sam masz jakiś problem, które nie zostały opisane w tej dokumentacji, poinformuj nas o tym za pośrednictwem narzędzia do [śledzenia problemów na serwisie Framagit](https://framagit.org/hubzilla/core/issues). Prosimy o jak najdokładniejsze opisanie swojego środowiska operacyjnego i podanie jak największej ilości informacji o wszelkich komunikatach o błędach, które mogą się pojawić, abyśmy mogli zapobiec ich występowaniu w przyszłości. Ze względu na dużą różnorodność istniejących systemów operacyjnych i platform PHP możemy mieć ograniczone możliwości debugowania instalacji PHP lub pozyskiwania brakujących modułów, ale dołożymy wszelkich starań, aby rozwiązać wszelkie ogólne problemy z kodem.

### Zanim zaczniesz

#### Wybierz nazwę domeny lub subdomeny dla swojego serwera

Platformę $Projectname można zainstalować tylko w katalogu głównym domeny lub subdomeny i nie może ona działać na niestandardowych portach TCP.

#### Zdecyduj, czy będziesz używać SSL i uzyskaj certyfikat SSL przed instalacją oprogramowania

POWINNO się używać SSL. Jeśli używasz SSL, MUSISZ użyć certyfikatu uznawanego przez przeglądarki. **NIE WOLNO używać certyfikatów z podpisem własnym!**

Przetestuj swój certyfikat przed instalacją. Narzędzie internetowe do testowania certyfikatu jest dostępne pod adresem http://www.digicert.com/help/. Odwiedzając witrynę po raz pierwszy, użyj adresu URL SSL (https://), jeśli protokół SSL jest dostępny. Pozwoli to uniknąć późniejszych problemów. Procedura instalacji nie pozwoli na użycie certyfikatu, który nie jest zaufany dla przeglądarki.

To ograniczenie zostało wprowadzone, ponieważ Twoje publiczne wpisy mogą zawierać odniesienia do obrazów na Twoim portalu. Inni członkowie przeglądający swój strumień na innych portalach otrzymają  w swojej przeglądarce ostrzeżenia, jeśli Twój certyfikat nie jest zaufany. To może zmylić wiele osób, ponieważ jest to zdecentralizowana sieć i otrzymają ostrzeżenie o Twoim portalu podczas przeglądania własnego portalu i mogą pomyśleć, że ich własny portal ma problem. Te ostrzeżenia są bardzo techniczne i przerażające dla niektórych osób, z których wielu nie będzie wiedziało, jak postępować i podporządkuje się zaleceniom przeglądarki. Jest to destrukcyjne dla społeczności. Zdajemy sobie sprawę z problemów związanych z obecną infrastrukturą certyfikatów i zgadzamy się, że istnieje wiele problemów, ale to nie zmienia wymagania - szyfrowanie połączeń HTTP jest konieczne.

Bezpłatne certyfikaty zgodne z przeglądarkami są dostępne od dostawców, takich jak StartSSL czy LetsEncrypt.

Jeśli NIE używasz SSL, może wystąpić opóźnienie do minuty dla startowego skryptu instalacyjnego - podczas sprawdzania portu SSL, aby zobaczyć, czy tam jest wszystko w porządku. Podczas komunikowania się z nowymi witrynami Hubzilla zawsze najpierw próbuje połączyć się z portem SSL, zanim powróci do mniej bezpiecznego połączenia. Jeśli nie używasz SSL, Twój serwer WWW NIE MOŻE w ogóle nasłuchiwać na porcie 443.

Jeśli używasz LetsEncrypt do dostarczania certyfikatów i tworzenia pliku pod _well-known_ lub _acme-challenge_, aby LetsEncrypt mógł zweryfikować własność domeny, usuń lub zmień nazwę katalogu _.well-known_ zaraz po wygenerowaniu certyfikatu. $Projectname zapewni własną procedurę obsługi usług *.well-know* po zainstalowaniu, a istniejący katalog w tej lokalizacji może uniemożliwić poprawne działanie niektórych z tych usług. Nie powinno to stanowić problemu w przypadku Apache, ale może to być problem z Nginx lub innymi serwerami internetowymi.

### Wdrożenie

Nowy portal można wdrożyć na kilka sposobów:

* ręczna inastalaja na istniejącym serwerze;
* automatyczna instalacja na istniejącym serwerze przy użyciu skryptu instalacyjnego;
* automatyczne wdrożenie przy użyciu prywatnego serwera wirtualnego OpenShift (VPS).

### Wymagania

* Apache z włączonym modułem _mod-rewrite_ i ustawioną dyrektywą "AllowOverride All", tak aby można było stosować plik _.htaccess_. Niektóre osoby z powodzeniem stosowały Nginx czy Lighttpd. Przykładowe skrypty konfiguracyjne są dostępne na  tej platformie w [doc/install](). Apache and Nginx mają najlepsze wsparcie.

* PHP 7.1 lub w wersji wyższej.
	* _Proszę mieć na uwadze, że w niektórych środowiskach hostinu współdzielonego, wersja wiersza poleceń PHP różni się od wersji serwera internetowego_

* Dostęp do wiersza poleceń PHP z ustawionym w pliku php.ini parametrem _register_argc_argv_ na true i bez ograniczeń dostawcy hostingu w zakresie stosowania funkcji _exec()_ i _proc_open()_.

* Rozszerzenia curl, gd (z obsługą co najmmniej jpeg i png), mysqli, mbstring, zip i openssl. Tozszerzenie imagick nie jest wymagane ale jest zalecane.

* Wymagane jest rozszerzenie xml, jeśli chce sie mieć działajacą obsługę webdav.

* Jakaś forma serwera pocztowego lub bramy pocztowej, taka jak działa PHP mail().

* Serwer bazy danych Mysql 5.x lub MariaDB lub PostgreSQL.

* Możliwość planowania zadań dla crona.

* WYMAGANA jest instalacja w katalogu głównym hosta WWW (wirtualnego hosta w Apache i bloku w Nginx).

### Instalacja ręczna

##### Krok 1.

Rozpakuj pliki $Projectname do katalogu głównego obszaru dokumentów serwera WWW. Jeśli kopiujesz drzewo katalogów na swój serwer WWW, upewnij się, że dołączasz ukryte pliki, takie jak _.htaccess_.

Jeśli możesz to zrobić, zalecamy użycie Git do sklonowania repozytorium źródłowego zamiast używania spakowanego pliku tar lub zip. To znacznie ułatwia późniejszą aktualizację oprogramowania. Polecenie Linux do sklonowania repozytorium do katalogu "mywebsite: wyglądałoby tak:

    git clone https://framagit.org/hubzilla/core.git mywebsite

a następnie, w dowolnym momencie, możesz pobrać najnowsze zmiany za pomocą:

    git pull

upewnij się, że istniejeją foldery `store/[data]/smarty3` i `store` i że są one możliwe do zapisu przez właściciela procesu serwera WWW:

    mkdir -p "store/[data]/smarty3"
    chmod -R 777 store

To uprawnienie (777) jest bardzo niebezpieczne i jeśli masz wystarczające uprawnienia i wiedzę powinieneś umożliwić zapisywanie w tych katalogach tylko przez serwer WWW i użytkownika, który uruchomia crona (patrz poniżej), jeśli jest taki. W wielu współdzielonych środowiskach hostingowych może to być trudne, bez zgłoszenia problemu u dostawcy. Powyższe uprawnienia pozwolą oprogramowaniu działać, ale nie są optymalne.

Aby działały niektóre internetowe narzędzia administracyjne, serwer WWW musi mieć możliwość zapisu w następujących katalogach:

* _addon_
* _extend_
* _view/theme_
* _widget_

##### Krok 2.

Utwórz pustą bazę danych i zanotuj szczegóły dostępu (nazwa hosta, nazwa użytkownika, hasło, nazwa bazy danych). Biblioteki bazy danych PDO powracają do komunikacji przez gniazdo uniksowe, gdy nazwą hosta jest _localhost_, ale niektóre osoby zgłosiły problemy z implementacją gniazda. Użyj gniazd, jeśli Twoje uprawnienia na to pozwalają. W przeciwnym razie, jeśli baza danych jest udostępniana na hoście _localhost_, jako nazwę hosta wpisz _127.0.0.1_.

Wewnętrznie używamy teraz biblioteki PDO do połączeń z bazą danych. Jeśli masz do czynienia z konfigyracją bazy danych, którą nie możesz obsłużyć poprzez formularz konfiguracyjny (ma przykład w przypadku uzywania MySQL z nietypową lokalizacją gniazd) - możesz podać ciąg połączenia PDO jako nazwę hosta. Na przykład:

	:/path/to/socket.file

W razie potrzeby nadal trzeba wypełnić w formularzu konfiguracyjnym wszystkie inne wartości mające zastosowanie.

##### Krok 3.

Utwórz pusty plik o nazwie _.htconfig.php_ i uczyń go możliwymm do zapisania przez serwer WWW. Krok ten wykonaj, jeśli wiesz, że serwer WWW nie będzie mógł sam utworzyć tego pliku.

##### Krok 4.

Odwiedź swoją witrynę za pomocą przeglądarki internetowej i postępuj zgodnie z instrukcjami. Zanotuj wszelkie komunikaty o błędach i popraw je przed kontynuowaniem. Jeśli używasz protokołu SSL (od znanego urzędu autoryzacyjnego), użyj schematu _https_ w adresie URL swojej witryny.

##### Krok 5.

Jeśli automatyczna instalacja nie powiedzie się z jakiegoś powodu, sprawdź następujące rzeczy:

* Czy istnieje plik _.htconfig.php_? Jeśli nie, edytuj plik _htconfig.php_ i zmień w nim ustawienia systemowe. Następnie zmień jego nazwę na _.htconfig.php_.
* Czy baza danych jest wypełniona. Jeśli nie, zaimportuj treść skryptu _install/schema_xxxxx.sql_ w phpmyadmin lub wierszu poleceń mysql (zamień 'xxxxx' na własciwy typ bazy danych).

##### Krok 6.

Po udanej instalacji odwiedż ponownie swoją witrynę i zarejestruj swoje osobiste konto. Błędy rejestracji powinny dać sie naprawić automatycznie.

Jeśli w tym momencie wystąpiła jakakolwiek *krytyczna* awaria, to na ogół przyczyna leży w źle funkcjonującej bazie danych. W takim przypadku, aby zacząć od nowa, usuń lub zmień nazwę pliku _.htconfig.php_ i usuń tabele bazy danych.

Aby Twoje konto miało dostęp administratora, powinno to być utworzone jako pierwsze, a adres e-mail podany podczas rejestracji musi być zgodny z adresem administratora podanym podczas instalacji. Jeśli stało sie inaczej, aby dać dostęp administracyjny jakiemuś kontu, dodaj _4096_ w rekordzie tabeli _account_roles_ tego konta.

Ze względu na bezpieczeństwo witryny, nie ma możliwości zapewnienia dostępu administracyjnego za pomocą formularzy konfiguracyjnych.

##### Krok 7. BARDZO WAŻNY!

Skonfiguruj zadanie Crona lub *zadanie zaplanowane*, tak aby uruchamiać menedżera Crona co 10-15 minut w celu przetwarzania i konserwacji w tle. Przykład:

	cd /base/directory; /path/to/php Zotlabs/Daemon/Master.php Cron


Zmień tutaj `/base/directory` i `/path/to/php` na właściwe dla siebie ścieżki.

Jeśli używasz serwera linuksowego, uruchom polecenie `crontab -e` i dodaj wiersz taki jak poniżej, zmieniając odpowiednio ścieżki i ustawienia:

	*/10 * * * *	cd /home/myname/mywebsite; /usr/bin/php Zotlabs/Daemon/Master.php Cron > /dev/null 2>&1

Lokalizację PHP na ogół można ustalić wykonując polecenie _which php_. Jeśli masz problemy z ustawienie Crona, skontaktuj się z dostawcą hostingu w celu uzyskania pomocy. Hubzilla nie będzie działać prawidłowo bez tego kroku.

Powinno się również sprawdzić ustawienie parametru _App::$config['system']['php_path']_ w pliku _.htconfig.php_. Powinno to wyglądać tak (zmień to zgodnie z lokalizacją PHP w swoim systemie):


	App::$config['system']['php_path'] = '/usr/local/php56/bin/php';

#### Oficjalne dodatki

##### Instalacja

Przejdź do swojej witryny. Następnie sklonuj repozytorium dodatków (osobno). Nadamy temu repozytorium pseudonim `hzaddons`. Możesz pobrać inne repozytoria dodatków Hubzilla, nadając im różne pseudonimy:

    cd mywebsite
    util/add_addon_repo https://framagit.org/hubzilla/addons.git hzaddons

##### Aktualizacja

W celu aktualizacji drzewa dodatków, powinno się, z poziomu głównego katalogu witryny, wydać polecenie aktualizacji tego repozytorium:

    cd mywebsite
    util/update_addon_repo hzaddons

Stwórz reprezentację dokumentacji online z możliwością wyszukiwania. Możesz to zrobić za każdym razem, gdy dokumentacja jest aktualizowana:

    cd mywebsite
    util/importdoc

### Automatyczna instalacja poprzez skrypt .homeinstall

Istnieje skrypt powłoki _.homeinstall/hubzilla-setup.sh_, który po uruchomieniu zainstaluje Hubzillę i jego zależności na nowej instalacji stabilnej dystrybucji Debiana 9 (Stetch). Powinien działać na podobnych systemach Linux, ale wyniki mogą się różnić.

#### Wymagania

Skrypt instalacyjny został pierwotnie zaprojektowany dla małego serwera sprzętowego za routerem domowym. Jednak został przetestowany też na kilku systemach z Debian 9:

* Home-PC (Debian-9.2-amd64) i Rapberry-Pi 3 (Rasbian = Debian 9.3)
	* Połączenie z Internetem i domowy router
	* Mini-PC lub Raspi połaczone z router
	* Napęd USB dla kopii zapasowych
	* Świeża instalacja Debian na swoim mini-pc
	* Router z otwartymi portami 80 i 443 dla Debiana

#### Etapy instalacji

1. _apt-get install git_
1. _mkdir -p /var/www/html_
1. _cd /var/www/html_
1. _git clone https://framagit.org/hubzilla/core.git ._
1. _nano .homeinstall/hubzilla-config.txt_
1. _cd .homeinstall/_
1. _./hubzilla-setup.sh_
1. _service apache2 reload_
1. Open your domain with a browser and step throught the initial configuration of $Projectname.

### Zalecane dodatki

Zalecamy zainstalowanie następujących dodatków we wszystkich publicznych witrynach:

	nsfw - hide inappropriate posts/comments
	superblock - block content from offensive channels

### Dodatki federacyjne

Kilka społeczności internetowych zaczęło łączyć się przy użyciu wspólnych protokołów. Stosowane protokoły mają nieco ograniczone możliwości. Na przykład protokół GNU-Social nie oferuje żadnych trybów prywatności, a protokół Diaspora
jest nieco bardziej restrykcyjny w zakresie dozwolonych rodzajów komunikacji. Wszystkie komentarze muszą być podpisane w bardzo unikalny sposób przez oryginalnego autora. Rozważany jest również protokół ActivityPub, który może być obsługiwany w przyszłości. Żaden inny istniejący protokół nie obsługuje lokalizacji nomadycznej używanej w tym projekcie. Stwarza to pewne problemy z obsługą, ponieważ niektóre funkcje działają w niektórych sieciach, a w innych nie. Niemniej jednak protokoły federacyjne umożliwiają nawiązywanie połączeń ze znacznie większą społecznością ludzi na całym świecie. Są dostarczane jako dodatki.

* _diaspora_ - protokół diaspory używany przez Diasporę i Friendica. Najpierw należy włączyć „Diaspora Statistics” (statystyki), aby włączyć wszystkie dostępne funkcje.

* _gnusoc_ - protokół społecznościowy GNU, używany przez GNU-Social, Mastodon i kilka innych społeczności. Ten dodatek wymaga najpierw zainstalowania usługi _pubsubhubbub_ (także dodatku).

Każdy członek Twojej sieci musi indywidualnie zdecydować, czy zezwolić na te protokoły, ponieważ mogą one kolidować z kilkoma pożądanymi podstawowymi funkcjami i możliwościami Hubzilla (takimi jak migracja kanałów i klonowanie). Robi się to
na swojej stronie _Ustawienia_ -> _Ustawienia funkcji i dodatków_. Administrator może również ustawić:

	util/config system.diaspora_allowed 1
	util/config system.gnusoc_allowed 1

i włączać te protokoły automatycznie dla wszystkich nowo tworzonych kanałów.

### Klasy usług

Klasy usług pozwalają na ustawienie limitów zasobów systemowych poprzez ograniczenie tego, co mogą robić poszczególne konta, w tym przechowywania plików i najwyższych limitów wpisów. Zdefiniuj niestandardowe klasy usług zgodnie ze swoimi potrzebami w pliku _.htconfig.php_. Dla przykładu utwórzmy klasę standard i premium, używając następujący kod:

    // Service classes

    App::$config['system']['default_service_class']='standard'; // this is the default service class that is attached to every new account

    // configuration for standard service class
    App::$config['service_class']['standard'] =
    array('photo_upload_limit'=>2097152, // total photo storage limit per channel (here 2MB)
    'total_identities' =>1, // number of channels an account can create
    'total_items' =>0, // number of top level posts a channel can create. Applies only to top level posts of the channel user, other posts and comments are unaffected
    'total_pages' =>100, // number of pages a channel can create
    'total_channels' =>100, // number of channels the user can add, other users can still add this channel, even if the limit is reached
    'attach_upload_limit' =>2097152, // total attachment storage limit per channel (here 2MB)
    'chatters_inroom' =>20);

    // configuration for premium service class
    App::$config['service_class']['premium'] =
    array('photo_upload_limit'=>20000000000, // total photo storage limit per channel (here 20GB)
    'total_identities' =>20, // number of channels an account can create
    'total_items' =>20000, // number of top level posts a channel can create. Applies only to top level posts of the channel user, other posts and comments are unaffected
    'total_pages' =>400, // number of pages a channel can create
    'total_channels' =>2000, // number of channels the user can add, other users can still add this channel, even if the limit is reached
    'attach_upload_limit' =>20000000000, // total attachment storage limit per channel (here 20GB)
    'chatters_inroom' =>100);

Aby zastosować klasę usług do istniejącego konta, użyj narzędzia wiersza poleceń z katalogu głównego instalacji Hubzilla:

* uzyskanie listy klas usług:

	util/service_class


* ustawienie domyślnej klasy usług na _firstclass_:

	util/config system default_service_class firstclass

* uzyskanie listy usług, które należą do klasy _firstclass_:

	util/service_class firstclass

* ustawienie całkowitego  użycia dysku ze zdjęciami _firstclass_ na 10 milionów bajtów

	util/service_class firstclass photo_upload_limit 10000000

* ustawienie konta z identyfikatorem 5 na klasę _firstclass_ (z potwierdzeniem):

	util/service_class --account=5 firstclass

* ustawienie konta, które jest właścicielem kanału `blogchan` na klasę _firstclass_ (z potwierdzeniem)

	util/service_class --channel=blogchan firstclass

**Opcje limitu klas usług**

##### Opcje limitów klas usług:

* _photo_upload_limit_ - maksymalna łączna powierzchnia dysku na przesłane pliki (w bajtach)
* _attach_upload_limit_ - maksymalna powierzchnia dysku na przesyłane załączniki plikow (w bajtach)
* _total_items_ - maksymalna liczba wpisów na najwyższym poziomie
* _total_pages_ - maksymalna liczba stron Comanche
* _total_identities_ - maksymalna liczba kanałów posiadanych na koncie
* _total_channels_ - maksymalna liczba kanałów
* _total_feeds_ - maksymalna liczba kanałów RSS

* _minimum_feedcheck_minutes_ - najniższe ustawienie dozwolone dla odpytywania kanałów RSS
* _chatrooms_ - maksymalna liczba czatów
* _chatters_inroom_ - maksymalna liczba rozmówców w czacie
* _access_tokens_ - maksymalna liczba tokenów dostępu gościa na kanał

### Zarządzanie motywami

#### Przykład zarządzania repozytorium

1) Przejdź na poziom katalogu głównego serwera:

  ```
  root@hub:/root# cd /var/www
  ```

2) Dodaj repozytorium motywu i nadaj mu nazwę

  ```
  root@hub:/var/www# util/add_theme_repo https://github.com/DeadSuperHero/redmatrix-themes.git DeadSuperHero
  ```
3) Zaktualizuj repozytorium motywu

  ```
  root@hub:/var/www#  util/update_theme_repo DeadSuperHero
  ```

### Katalog kanałów

#### Słowa kluczowe

Na stronie katalogu kanałów może pojawiać się  chmura słów kluczowych. Jeśli chcesz ukryć te słowa kluczowe, które są pobierane z serwera katalogów, możesz użyć narzędzia _config_:

    util/config system disable_directory_keywords 1

Jeśli twój portal pracuje w trybie autonomicznym, ponieważ nie chcesz łączyć się z globalną siecią, możesz zamiast tego ustawić opcję systemową _directory_server_ na wartość pustą:

    util/config system directory_server ""

### Administrowanie

#### Administrowanie witryną

Administracja witryną jest zwykle wykonywana za pośrednictwem strony administratora znajdującej się na ścieżce _/admin_ adresu URL Twojej witryny. Aby uzyskać dostęp do tej strony, trzeba mieć uprawnienia administratora na serwerze. Prawa administracyjne są przyznawane pierwszemu kontu, które zostało zarejestrowane w witrynie, pod warunkiem, że adres e-mail tego konta dokładnie odpowiada adresowi e-mail podanemu jako adres e-mail administratora podczas konfiguracji.

Istnieje kilka sposobów, w jakie może to się nie powieść i pozostawić system bez konta administratora, na przykład jeśli pierwsze konto, które zostało utworzone, miało inny adres e-mail niż adres e-mail administratora, który został podany podczas konfiguracji.

Ze względów bezpieczeństwa w systemie nie ma strony internetowej ani interfejsu, który daje dostęp administratora. Jeśli potrzebujesz poprawić sytuację, w której system nie ma konta administratora, musisz to zrobić edytując tabelę kont w bazie danych. Nie ma innego wyjścia. Aby to zrobić, będziesz musiał zlokalizować wpis w tabeli kont, który należy do żądanego administratora i ustawić _account_roles_ dla tego wpisu na _4096_. Będziesz wtedy mógł uzyskać dostęp do strony administratora z menu profilu twojego systemu lub bezpośrednio na ścieżce _/admin_.

Portal może mieć wielu administratorów i nie ma ograniczeń co do ich liczby. Powtórz powyższą procedurę dla każdego konta, któremu chcesz przyznać uprawnienia administracyjne.

### Rozwiązywanie problemów

#### Pliki dzienników

Plik dziennika systemowego jest niezwykle przydatnym źródłem informacji do śledzenia błędów. Można to włączyć na stronie konfiguracji _admin/log_. Ustawienie poziomu o wartości *LOGGER_DEBUG* jest preferowany w stabilnej instalacji produkcyjnej. Większość problemów związanych z komunikacją lub przechowywaniem jest tutaj wymieniona. Ustawienie na *LOGGER_DATA* zapewnia znacznie więcej szczegółów, ale może wypełnić dysk. W obu przypadkach zalecamy użycie *logrotate* w systemie operacyjnym do cyklicznego tworzenia dzienników i usuwania starszych wpisów.

Na dole twojego *.htconfig.php* znajduje się kilka linii (zakomentowanych), które umożliwiają rejestrowanie błędów PHP. Zgłaszane są problemy ze składnią i wykonywaniem kodu i jest to też pierwszym miejscem, w którym należy szukać problemów, które powodują "biały ekran" lub pustą stronę. Zwykle jest to wynikiem problemów z kodem lub składnią. Błędy bazy danych są zgłaszane do pliku dziennika systemowego, ale uznaliśmy, że przydatne jest umieszczenie w katalogu najwyższego poziomu pliku *dbfail.out*, który gromadzi tylko informacje o problemach związanych z bazą danych. Jeśli plik istnieje i można go zapisać, będą rejestrowane w nim błędy bazy danych, a także w pliku dziennika systemowego.

W przypadku błędów "500: problemy mogą być często rejestrowane w dziennikach serwera internetowego, często w */var/log/apache2/error.log* lub podobnym. Zapoznaj się z dokumentacją systemu operacyjnego.

Istnieją trzy różne obiekty dziennika.

**Pierwsza to dziennik błędów bazy danych**. Jest on używane tylko wtedy, gdy utworzy się plik o nazwie **_dbfail.out_** w folderze głównym swojej witryny i pozwala na zapisywanie w nim przez serwer WWW. Jeśli masz jakiekolwiek zapytania do bazy danych, które nie powiodły się, wszystkie są zgłaszane tutaj. Zwykle wskazują na literówki w naszych zapytaniach, ale występują również w przypadku rozłączenia serwera bazy danych lub uszkodzenia tabel. W rzadkich przypadkach zobaczymy tutaj warunki wyścigu, w których dwa procesy próbowały utworzyć wpis *xchan* lub *cache* z tym samym identyfikatorem. Należy zbadać wszelkie inne błędy (zwłaszcza błędy uporczywe).

**Drugi to dziennik błędów PHP**. Plik **_php.out_** jest tworzony przez procesor języka i zgłasza tylko problemy powstałe w środowisku językowym. Znowu mogą to być błędy składniowe lub błędy programistyczne, ale generalnie są one fatalne i skutkują "białym ekranem";
na przykład PHP kończy działanie. Prawdopodobnie powinieneś zajrzeć do tego pliku też, jeśli coś pójdzie nie tak, co nie powoduje białego ekranu. Często zdarza się, że plik ten jest pusty przez wiele dni.

Na dole dostarczonego pliku *.htconfig.php* znajduje się kilka linii, które, jeśli nie są zakomentowane, włączają dziennik PHP (niezwykle przydatny do znajdowania źródła błędów białego ekranu). Nie jest to robione domyślnie ze względu na potencjalne problemy z własnością pliku dziennika i uprawnieniami do zapisu oraz fakt, że domyślnie nie ma rotacji pliku dziennika.

**Trzeci to "dziennik aplikacji"**. Jest to używane przez Hubzillę do zgłaszania tego, co dzieje się w programie i zwykle zapisywane są tu wszelkie trudności lub nieoczekiwane dane, które otrzymaliśmy. Jego nazwę (ścieżkę) trzeba podać na stronie "Administracja - Logi" (/admin/logs), np. *hubzilla.log* wskazuje na plik o tej nazwie zlokalizowany w katalogu głównym Hubzilla. Czasem zgłaszane są tu również komunikaty o stanie "pulsu", aby wskazać, że osiągnęliśmy określony punkt w skrypcie. Jest to dla nas najważniejszy plik dziennika, ponieważ tworzymy go samodzielnie wyłącznie w celu zgłaszania stanu zadań w tle i wszystkiego, co wydaje się dziwne lub nie na miejscu. Te błędy mogą być "śmiertelne", ale też niegroźne i po prostu nieoczekiwane. Jeśli wykonujesz zadanie i występuje problem, daj nam znać, co znajduje się w tym pliku, gdy wystąpił problem. Proszę nie wysyłaj nam 100 milionów zrzutów, bo tylko nas wkurzysz! Tylko kilka odpowiednich wierszy, ab można było wykluczyć kilkaset tysięcy wierszy kodu i skoncentrować się na tym, gdzie zaczyna się pojawiać problem.

To są dzienniki Twojego serwisu. Zgłaszamy poważne problemy na każdym poziomie dziennika. Gorąco polecamy poziom dziennika *DEBUG* dla większości witryn. Dostarcza on trochę dodatkowych informacji i nie tworzy dużych plików dziennika. Kiedy pojawia się problem, który uniemożliwia wszelkie próby śledzenia, możesz wtedy włączyć na krótki czas poziom *DATA*, aby uchwycić wszystkie szczegóły struktur, z którymi mieliśmy do czynienia w tym czasie. Ten poziom dziennika zajmuje dużo miejsca, więc jest zalecany tylko na krótkie okresy lub w przypadku witryn testowych dla programistów.

Zalecamy skonfigurowanie *logrotate* zarówno dla dziennika php, jak i dziennika aplikacji. Zazwyczaj co tydzień lub dwa zaglądam do *dbfail.out*, naprawiam zgłoszone problemy i zaczynam od nowego pliku. Podobnie jest z plikiem dziennika PHP. Odwołuję się do tego od czasu do czasu, aby sprawdzić, czy jest coś, co wymaga naprawy.

Jeśli coś pójdzie nie tak i nie jest to błąd krytyczny, warto zajrzeć do pliku dziennika aplikacji. Można zrobić to tak:

```
tail -f logfile.out
```

ponieważ powtarzaja się wpisy dla operacju, która ma problemy. MOzna wstawić w kodzie dodatkowe instrukcje rejestracji, jeśli nie ma żadnej wskazówki, co się dzieje. Nawet coś tak prostego jak "got here" lub wydrukować wartości zmiennej, która może być podejrzana. Zachecamy aby to robić. Gdy już znajdziesz to, czego potrzebujesz, możesz wykonać:

```
git checkout file.php
```

aby natychmiast wyczyścić wszystkie dodane elementy rejestrowania. Skorzystaj z informacji z tego dziennika i wszelkich szczegółów, które możesz podać podczas badania problemu, aby zgłosić błąd - chyba że analiza wskazuje na źródło problemu. W takim przypadku po prostu to napraw.

##### Rotowanie plików dziennika

1. Włącz dodatek *Logrot* w [oficjalnym repozytorium dodatków hubzilla](https://framagit.org/hubzilla/addons).
1. Utwórz katalog w swoim katalogu głównym o nazwie `log` z uprawnieniami do zapisu przez serwer WWW.
1. Przejdź do ustawień administratora programu *Logrot* i wprowadź nazwę folderu, a także maksymalny rozmiar i liczbę zachowanych plików dziennika.

#### Zgłaszanie problemów

Zgłaszając problemy, staraj się podać jak najwięcej szczegółów, które mogą być potrzebne programistom do odtworzenia problemu i podać pełny tekst wszystkich komunikatów o błędach.

Zachęcamy do dołożenia wszelkich starań, aby wykorzystać te dzienniki w połączeniu z posiadanym kodem źródłowym w celu rozwiązywania problemów i znajdowania ich przyczyn. Społeczność często jest w stanie pomóc, ale tylko Ty masz dostęp do
plików dziennika swojej witryny i ich udostępnianie jest uważane za zagrożenie bezpieczeństwa.

Jeśli problem z kodem został odkryty, zgłoś go w bugtrackerze projektu (https://framagit.org/hubzilla/core/issues). Ponownie podaj jak najwięcej szczegółów, aby uniknąć ciągłego zadawania pytań o konfigurację lub powielanie problemu, abyśmy mogli przejść od razu do problemu i dowiedzieć się, co z nim zrobić. Zapraszamy również do oferowania własnych rozwiązań i przesyłania poprawek. W rzeczywistości zachęcamy do tego, ponieważ wszyscy jesteśmy wolontariuszami i mamy mało wolnego czasu. Im więcej osób pomaga, tym łatwiejsze jest obciążenie pracą dla wszystkich. W porządku, jeśli Twoje rozwiązanie nie jest idealne. Wszystko pomaga i być może uda nam się to poprawić.

