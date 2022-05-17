Tłumaczenie $Projectname
========================

Procedura tłumaczenia na język polski
-------------------------------------

Tłumaczenie interfejsu użytkownika, ekranów kontekstowej pomocy oraz dokumentacji na język polski odbiega nieco od ogólnej procedury przyjetej w $Projectname. Po prostu, zrezygnowano z przygotowania pliku translacyjnego hmessages.po za pośrednictwem serwisu [Transifex][1], tak jak to jest zalecane w $Projectname i posłużono się sporządzeniem tych plików przy pomocy ogólnie dostępnych narzędzi translacyjnych przeznaczonych do tworzenia plików [gettext](https://www.gnu.org/software/gettext/) rozszerzenia .po, .mo, .pot), takich jak [poedit](https://poedit.net/) i inne.

Trzeba podkreślić, że to odstępstwo dotyczy przygotowania pliku hmessages.po i organizacji prac nad tłumaczeniem.

Tłumaczenie $Projectname na język polski jest obecnie wydzielone w odrębny podprojekt, utrzymywany w [repozytorium na GitHub] https://github.com/astabski/hubzilla-pl)

Projekt ten obejmuje wszystkie pliki potrzebne do przetłumaczenia interfesju użytkownika, pomocy kontekstowej i oficjalnej dokumentacji, zawarte w następujących katalogach kodu $Projectname: 

- view/pl
- doc/context/pl
- doc/macros/pl
- doc/pl

Projekt ten jest obecnie podstawą oficjalnego polskiego tłumaczenia $Projectname. Po każdej istotnej zmianie, osoba kierująca projektem tłumaczenia zgłasza odpowiednie żądanie PR do drzewa żródłowego $Projectname.

### Zgłaszanie poprawek

Jeśli chcesz zgłosić jakieś zmiany  w istniejącym tekście tłumaczenia, otwórz nową sprawę na stronie https://github.com/astabski/hubzilla-pl/issues i podaj tam szczegóły proponowanych zmian. 

Możesz też dokonać poprawek w tym projekcie, zgłaszając odpowiednio przygotowane żądanie PR.   

### Nowe tłumaczenia 

Jeśli chcesz pomóc, tworząc tłumaczenia jeszcze nie przetłumaczonych dokumentów $Projectname, dołącz do projektu https://github.com/astabski/hubzilla-pl. W tym celu umieść na stronie https://github.com/astabski/hubzilla-pl/issues odpowiednią wiadomość. Otrzymasz odpowiedź z dokładną instrukcją.   

Ogólne zasady tłumaczeń obowiązujące w $Projectname 
---------------------------------------------------

Jeśli chcesz samodzielnie przenieść swoją pracę do drzewa źródłowego $Projectname, skontaktuj się z zespołem $Projectname i zadaj pytania.

Proces jest tłumaczenia prosty, a oprogramowanie $Projectname jest dostarczane ze wszystkimi niezbędnymi narzędziami.

Lokalizacją przetłumaczonych plików jest w kodzie źródłowym katalog `/view/LNG-CODE/`, gdzie `LNG-CODE` jest używanym kodem języka, np. `de` dla niemieckiego albo `pl` dla polskiego.

W przypadku szablonów wiadomości e-mail (pliki `*.tpl`) po prostu trzeba umieścić je w katalogu i gotowe. Przetłumaczone łańcuchy pochodzą z pliku `hmessages.po` z serwisu Transifex, który należy przetłumaczyć na plik PHP używany przez $Projectname. Aby to zrobić, trzeba umieścić plik w wymienionym wyżej katalogu i użyć narzędzia `po2php` z katalogu `util` w instalacji $Projectname.

Zakładając, że chcesz przetłumaczyć polską wersję umieszczoną pliku `view/pl/hmessages.po`, wykonaj następujące czynności.

1. Przejdź w wierszu polecenia do katalogu głównego instalacji $Projectname

2. Wykonaj skrypt `po2php`, który jest umieszczono tłumaczenia dla pliku `hstrings.php`, który jest używany w $Projectname.

       $> php util/po2php.php view/pl/hmessages.po

   Dane wyjściowe skryptu zostaną umieszczone w `view/de/hstrings.php, bo tam
   froemdoca oczekuje tego pliku, więc możesz natychmiast przetestować swoje
   tłumaczenie.
                                  
3. Odwiedź swoją stronę $Projectname, aby sprawdzić, czy nadal działa w języku, który właśnie przetłumaczyłeś. Jeśli nie, spróbuj znaleźć błąd, najprawdopodobniej PHP da ci wskazówkę w opisie błędu w `log/warnings.about`.

   W celu debugowania możesz również spróbować "uruchomić" plik za pomocą PHP. Nie powinno to dawać żadnych wyników, jeśli plik jest w porządku, ale może dać wskazówkę dotyczącą wyszukiwania błędu.

       $> php view/de/hstrings.php

4. Zatwierdź te dwa pliki z sensownym komunikatem o zatwierdzeniu do repozytorium git, wypchnij je do rozwidlenia repozytorium $Projectname na github i wydaj żądanie ściągnięcia dla tego zatwierdzenia.

Narzędzia
---------

Oprócz skryptu po2php, jest jeszcze w katalogu "util" w drzewie źródłowym $Projectname kilka narzędzi do tłumaczenia. Jeśli tylko chcesz przetłumacz $Projectname na inny język, którego  nie potrzebujesz najbardziej, ale da Ci to wyobrażenie o procesie
tłumaczenia $Projectname. 

Więcej informacji można znaleźć w pliku utils/README.

Znane problemy
--------------

* $Projectname używa ustawień języka przeglądarki odwiedzających, aby określić
  język interfejsu użytkownika. W większości przypadków to działa, ale są pewne
  znane dziwactwa.
* wczesne tłumaczenia są oparte na przekładach Friendica, jeśli znajdziesz jakieś
  błędy, daj nam znać lub popraw je w Transifex.

Linki
------

[1]:   http://www.transifex.com/projects/p/hubzilla/


#include doc/pl/macros/main_footer.bb;
