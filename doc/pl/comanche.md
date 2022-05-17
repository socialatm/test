Język opisu stron internetowych Comanche
========================================

Comanche to język znaczników podobny do bbcode, za pomocą którego można tworzyć rozbudowane i złożone strony internetowe, składając je z szeregu komponentów - z których niektóre są wstępnie skonstruowane, a inne można zdefiniować w locie. Firmalnie, Comanche jest językiem opisu stron, umozliwiajacym konstruowanie statycznych stron internetowych.

Comanche przede wszystkim wybiera, jakie treści pojawią się w różnych regionach strony. Różne regiony mają nazwy i te nazwy mogą się zmieniać w zależności od wybranego szablonu układu.

<a name="templates"></a>

## Szablony stron
Obecnie istnieje pięć szablonów układu, ale portal może zapewniać dodatkowe układy

**default**

Szablon *default* definuje region 'nav' u góry strony, 'aside' jako pasek boczny o stałej szerokości, 'content' dla głównego obszaru treści i 'footer' dla stopki strony. Jest to szablon domyślny, jak wskazuje na to nazwa.

**full**

Szablon *full* definiuje to samo, co szablon "default", z tym wyjątkiem, że nie ma obszaru 'aside'.

**choklet**

Szablon *choklet* zapewnia szereg płynnych stylów układu, które można określać według odmiany:

* (odmiana domyślna) - układ dwukolumnowy, podobny do szablonu domyślnego, ale bardziej płynny;
* bannertwo - układ dwukolumnowy z regionem bannera, zgodny z szablonem "default" na małych wyświetlaczach;
* three - układ trzykolumnowy (dodano region 'right_aside' do szablonu domyślnego);
* edgestwo - układ dwukolumnowy ze stałymi marginesami bocznymi;
* edgesthree - układ trzykolumnowy ze stałymi marginesami bocznymi;
* full - układ trzykolumnowy ze stałymi marginesami bocznymi i dodatkowym obszarem "header" poniżej paska nawigacji

**redable** (sic)

Szablon do czytania dłuższych tekstów na pełnym ekranie (czyli bez paska nawigacyjnego). Trzy kolumny: 'aside', 'content' i 'right_aside'. Aby zapewnić maksymalną czytelność, zaleca się używanie tylko środkowej kolumny treści.

**zen**

Daje swobodę robienia wszystkiego samemu. Po prostu pusta strona z obszarem zawartości.

Użyj znacznika `template`, aby wybrać szablon układu (w tym przypadku "full"):

```
	[template]full[/template]
```

W celu wyboru szablonu "choklet" w odmianie "three" użyj:

```
	[template=three]choklet[/template]
```

Jeśli nie określono innego szablonu, zostanie użyty szablon domyślny. Szablon może używać dowolnych nazw dla regionów zawartości. Używa się znaczników `region`, aby zdecydować, jakie treści umieścić w odpowiednich regionach.

Zostały też zdefiniowne trzy "makra", które można wykorzystać"

```
	$htmlhead - zastępowane treścią nagłówka witryny.
	$nav - zastępowane zawartością paska nawigacyjnego witryny.
	$content - zastępowane główną zawartością strony.
```

Domyślnie, `$nav` jest umieszczne w regionie "nav" a `$content` w regionie "content". Makra te są potrzebne tylko wtedy, gdy chcesz zmienić rozmieszczenie tych elementów, tak aby zmienić kolejność lub przenieść je do innych regionów strony.

W celu wybrania motywu dla swojej strony, trzeba użyć znacznika 'theme'.

```
	[theme]suckerberg[/theme]
```

Polecenie to wybierze motyw o nazwie "suckerberg". Domyślnie zostanie użyty preferowany motyw Twojego kanału.

```
	[theme=passion]suckerberg[/theme]
```

W tym przypadku, wybrany zostanie motywu o nazwie "suckerberg" i schemat "passion" (wariant motywu). Alternatywnie można użyć do tego skondensowanej notacji motywu.

```
	[theme]suckerberg:passion[/theme]
```

Notacja skondensowana nie jest częścią samego Comanche, ale jest rozpoznawana przez platformę Hubzilla jako specyfikator motywu.

<a name="regions"></a>

## Regiony

Jak wspomniano powyżej, każdy region ma nazwę. Konkretny region określa się za pomocą tagu "region", który zawiera jego nazwę. Jakakolwiek treść, którą chce się umieścić w tym regionie, powinna być umieszczona pomiędzy tagiem otwierającym a zamykającym region.

```
	[region=htmlhead]....content goes here....[/region]
	[region=aside]....content goes here....[/region]
	[region=nav]....content goes here....[/region]
	[region=content]....content goes here....[/region]
```

<a name="assets"></a>

## CSS i Javascript

Jest możliwość włączenia bibliotek Javascript i CSS do regionu htmlhead. Obecnie wykorzystujemy bibliteki jQuery (js), Bootstrap (css/js) oraz Foundation (css/js).

Poniży kod spowoduje nadpisanie wybranych motywów htmlhead.

```
	[region=htmlhead]
		[css]bootstrap[/css]
		[js]jquery[/js]
		[js]bootstrap[/js]
	[/region]

```

<a name="menus-blocs"></a>

## Menu i bloki

Nasze narzędzia do tworzenia stron internetowych umożliwiają tworzenie również menu i bloków treści. Zapewniają one wyrenderowanie jakichś fragmentów treści strony, które można umieścić w dowolnych regionach i w dowolnej kolejności. Każdy z nich ma nazwę, którą określa się podczas tworzenia menu lub bloku.

```
	[menu]mymenu[/menu]
```

Powyższy kod umieszcza menu o nazwie "mymenu" na stronie, ale musi ono znajdować się w jakimś regionie strony. 

```
	[menu=horizontal]mymenu[/menu]
```

Ten kod umieszcza menu o nazwie "mymenu" na stronie, tak jak kod poprzedni, ale dodatkowo zastosowano tu klasę CSS "horizontal" dla bloku menu. Klasa "horizontal" została zdefiniowana w motywie redbasic. Może, ale nie musi być dostępna w innych motywach. 

```
	[menu][var=wrap]none[/var]mymenu[/menu]
```

Zmienna [var=wrap]none[/var] w umieszczona w powyższym bloku usuwa otaczający menu element div.

W poniższym przykładzie, w jakimś regionie umieszcza się blok o nazwie "contributors":

```
	[block]contributors[/block]
```

Nastęþny przykład pokazuje blok o nazwie "contributors", podobnie jak powyżej, ale z zastosowaniem klasy "someclass". Zastępuje to domyślną klasę bloków "bblock widget":

```
	[block=someclass]contributors[/block]
```

W poniższym przykładzie, zmienna [var=wrap]none[/var] umieszczona w bloku "contributors" usuwa z bloku otaczający go element div.

```
	[block][var=wrap]none[/var]contributors[/block]
```

## Widżety

Widgety to wykonywalne aplikacje dostarczane przez system, które możesz umieścić na swojej stronie. Niektóre widżety przyjmują argumenty, które pozwalają dostosować widżet do własnych potrzeb.

Podstawowy system dostarcza:

* profile - widżet, który powiela pasek boczny profilu na stronie kanału. Ten widżet nie wymaga żadnych argumentów;

* tagcloud - udostępnia chmurę tagów kategorii; argumenty  

	* count - maksymalna liczba tagów na liście

Widżety i argumenty są określane za pomocą znaczników 'widget' i 'var'.

```
	[widget=recent_visitors][var=count]24[/var][/widget]
```

Spowoduje to załadowanie widżetu "recent_visitors" i dostarczenie go z argumentem "count" ustawionym na "24".
 
## Komentarze

Znacznik 'comment' służy do wydzielenia komentarzy. Komentarze te nie pojawią się na wyrenderowanej stronie.

```
	[comment]This is a comment[/comment]
```

## Instrukcje warunkowe

Do wyboru rozwiązań można używać konstrukcji 'if'. Są one obecnie oparte systemowej zmiennej konfiguracyjnej lub bieżącym obserwatorze.

```
	[if $config.system.foo]
		... zmienna konfiguracyjna system.foo przyjmuje wartość 'true'.
	[else]
		... zmienna konfiguracyjna system.foo przyjmuje wartość 'false'.
 	[/if]

	[if $observer]
		... ta treść będzie wyświetlana tylko uwierzytelnionym odwiedzającym
	[/if]
```

Klauzula 'else' jest opcjonalna. 

Oprócz oceny logicznej obsługiwanych jest kilka testów.

```
	[if $config.system.foo == bar]
		... zmienna konfiguracyjna system.foo jest równa łańcuchowi 'bar'
	[/if]
	[if $config.system.foo != bar]
		... zmienna konfiguracyjna system.foo nie jest równa łańcuchowi 'bar'
	[/if]
	[if $config.system.foo {} bar ]
		... zmienna konfiguracyjna system.foo jest prostą tablicą zawierającą wartość 'bar'
	[/if]
	[if $config.system.foo {*} bar]
		... zmienna konfiguracyjna system.foo jest prostą tablicą zawierającą klucz o nazwie 'bar'
	[/if]
```

## Złożony przykład

```
	[comment]Użycie istniejącego szablonu strony, który zapewnia region banera a pod nim 3 kolumny[/comment]

	[template]3-column-with-header[/template]

	[comment]Użycie motywu "darknight"[/comment]

	[theme]darkknight[/theme]

	[comment]Użycie istniejącego menu nawigacyjnego witryny[/comment]

	[region=nav]$nav[/region]

	[region=side]

		[comment]Użycie wybranego przeze mnie menu i kilku widżetów[/comment]

		[menu]myfavouritemenu[/menu]

		[widget=recent_visitors]
			[var=count]24[/var]
			[var=names_only]1[/var]
		[/widget]

		[widget=tagcloud][/widget]
		[block]donate[/block]

	[/region]



	[region=middle]

		[comment]Show the normal page content[/comment]

		$content

	[/region]



	[region=right]

		[comment]Show my condensed channel "wall" feed and allow interaction if the observer is allowed to interact[/comment]

		[widget]channel[/widget]

	[/region]

```

#include doc/macros/main_footer.bb;
